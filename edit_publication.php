<?php
// *************************************************************
// ** DEBUGGING BLOCK: เปิดการแสดงข้อผิดพลาดของ PHP **
// *************************************************************
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// *************************************************************

session_start();

// ตรวจสอบว่า user ล็อกอินหรือยัง
if (!isset($_SESSION['user_id'])) {
    header("Location: login-v1.php");
    exit;
}

// กำหนดค่าการเชื่อมต่อฐานข้อมูล
$servername = "localhost";
$username = "root"; 
$password = "";    
$dbname = "group10"; 
$upload_dir = 'uploads/'; // โฟลเดอร์สำหรับเก็บไฟล์ (ต้องสร้างโฟลเดอร์นี้)

$conn = new mysqli($servername, $username, $password, $dbname);
$db_error = null;
if ($conn->connect_error) {
    $db_error = "Connection failed: " . $conn->connect_error;
}

// ข้อมูลประเภทผลงานสำหรับ Dropdown (อัปเดตตามที่ผู้ใช้ร้องขอ)
$publication_types = [
    'Journal' => 'บทความวารสาร',
    'Conference' => 'นำเสนอในการประชุม',
    'Thesis' => 'วิทยานิพนธ์/ภาคนิพนธ์',
    'Other' => 'อื่นๆ',
];


// ดึงข้อมูลผู้ใช้จาก session
$user_id = $_SESSION['user_id'];
$current_user_name = "ชื่อ ผู้ใช้งาน (ไม่พบข้อมูล)";
$user_role = "";
$pub_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$publication = null;
$message = '';
$message_type = ''; // 'success' หรือ 'error'

// *************************************************************
// ** 1. ดึงข้อมูลผู้ใช้และผลงานตีพิมพ์เดิม (Initial GET) **
// *************************************************************
if (!$db_error) {
    // 1.1 ดึงข้อมูลชื่อผู้ใช้
    $sql_user = "SELECT first_name, last_name, role FROM User WHERE User_id = ?";
    $stmt_user = $conn->prepare($sql_user);
    if ($stmt_user) {
        $stmt_user->bind_param("i", $user_id);
        $stmt_user->execute();
        $result_user = $stmt_user->get_result();
        if ($user = $result_user->fetch_assoc()) {
            $current_user_name = htmlspecialchars($user['first_name'] . " " . $user['last_name']);
            $user_role = $user['role'];
        }
        $stmt_user->close();
    }

    // 1.2 ดึงข้อมูลผลงานตีพิมพ์เพื่อแก้ไข (ต้องตรงกับ Author_id)
    if ($pub_id > 0) {
        // NOTE: คอลัมน์ 'journal' ถูกใช้เพื่อเก็บ 'ประเภทของผลงาน' ชั่วคราวสำหรับการสาธิตนี้
        $sql_pub = "SELECT Pub_id, title, journal, publish_year, status, file_path 
                    FROM Publication 
                    WHERE Pub_id = ? AND Author_id = ?";
        $stmt_pub = $conn->prepare($sql_pub);
        if ($stmt_pub) {
            $stmt_pub->bind_param("ii", $pub_id, $user_id);
            $stmt_pub->execute();
            $result_pub = $stmt_pub->get_result();
            if ($result_pub->num_rows === 1) {
                $publication = $result_pub->fetch_assoc();
            } else {
                // ไม่พบผลงาน หรือไม่ใช่เจ้าของ
                $db_error = "ไม่พบผลงานตีพิมพ์ ID #{$pub_id} หรือคุณไม่มีสิทธิ์แก้ไขผลงานนี้";
                $pub_id = 0; // ป้องกันการอัปเดตหากไม่พบข้อมูล
            }
            $stmt_pub->close();
        } else {
            $db_error = "Publication Query prepare failed: " . $conn->error;
        }
    } else {
        $db_error = "ไม่พบรหัสผลงานที่ระบุ";
    }
}

// *************************************************************
// ** 2. การจัดการการส่งฟอร์ม (POST Submission) **
// *************************************************************
if ($_SERVER["REQUEST_METHOD"] == "POST" && $pub_id > 0 && $publication) {
    // ดึงค่าจากฟอร์ม
    $new_title = trim($_POST['title']);
    // ค่าใหม่สำหรับประเภทผลงาน (ใช้คอลัมน์ journal ใน DB)
    $new_pub_type = trim($_POST['publication_type']); 
    $new_publish_year = (int)$_POST['publish_year'];
    
    // ** การแก้ไขที่ 2: เปลี่ยนสถานะเป็น 'Pending' เสมอเมื่อมีการแก้ไข **
    $new_status = 'waiting';
    
    // ค่าเดิมของ file_path
    $old_file_path = $publication['file_path'];
    $new_file_path = $old_file_path;
    $file_upload_success = true;

    // การจัดการไฟล์อัปโหลด
    if (isset($_FILES['publication_file']) && $_FILES['publication_file']['error'] == UPLOAD_ERR_OK) {
        $temp_file = $_FILES['publication_file']['tmp_name'];
        $file_name = basename($_FILES['publication_file']['name']);
        
        // สร้างชื่อไฟล์ที่ไม่ซ้ำกัน
        $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
        $safe_title = preg_replace("/[^a-zA-Z0-9\s]/", "_", $new_title);
        $new_file_name = $pub_id . '_' . time() . '_' . $safe_title . '.' . $file_extension;
        $target_file = $upload_dir . $new_file_name;

        // ตรวจสอบและสร้างโฟลเดอร์ uploads ถ้ายังไม่มี
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        // ย้ายไฟล์
        if (move_uploaded_file($temp_file, $target_file)) {
            // อัปโหลดสำเร็จ
            $new_file_path = $target_file;
            
            // ลบไฟล์เก่าถ้ามีและไฟล์ใหม่ถูกอัปโหลด
            if ($old_file_path && file_exists($old_file_path)) {
                @unlink($old_file_path); 
            }
        } else {
            $file_upload_success = false;
            $message = '⚠️ การอัปโหลดไฟล์ใหม่ล้มเหลว กรุณาลองอีกครั้ง';
            $message_type = 'error';
        }
    }

    // อัปเดตฐานข้อมูล (ถ้าการอัปโหลดไฟล์สำเร็จ หรือไม่มีการอัปโหลดไฟล์ใหม่)
    if ($file_upload_success) {
        $sql_update = "UPDATE Publication 
                       SET title = ?, journal = ?, publish_year = ?, status = ?, file_path = ? 
                       WHERE Pub_id = ? AND Author_id = ?";
        
        $stmt_update = $conn->prepare($sql_update);

        if ($stmt_update) {
            // ** การแก้ไขที่ 1: เปลี่ยน type definition string เป็น "ssissii" (7 ตัว) **
            // ผูกค่า: (title(s), journal[ประเภท](s), publish_year(i), status(s), file_path(s), Pub_id(i), Author_id(i))
            $stmt_update->bind_param("ssissii", 
                $new_title,          // s
                $new_pub_type,       // s
                $new_publish_year,   // i
                $new_status,         // s
                $new_file_path,      // s
                $pub_id,             // i
                $user_id             // i
            );
            
            if ($stmt_update->execute()) {
                // อัปเดตสำเร็จ
                $message = '✅ แก้ไขผลงานตีพิมพ์เรียบร้อยแล้ว สถานะถูกเปลี่ยนเป็น "รอการอนุมัติ"';
                $message_type = 'success';
                
                // รีเฟรชข้อมูล $publication เพื่อแสดงค่าใหม่ในฟอร์ม
                $publication = [
                    'Pub_id' => $pub_id,
                    'title' => $new_title,
                    'journal' => $new_pub_type, // อัปเดตค่าประเภท
                    'publish_year' => $new_publish_year,
                    'status' => $new_status, // สถานะใหม่คือ Pending
                    'file_path' => $new_file_path
                ];

            } else {
                $message = '❌ ข้อผิดพลาดในการอัปเดตฐานข้อมูล: ' . $stmt_update->error;
                $message_type = 'error';
            }
            $stmt_update->close();
        } else {
            $message = '❌ ข้อผิดพลาดในการเตรียมคำสั่ง SQL: ' . $conn->error;
            $message_type = 'error';
        }
    }
}

// ฟังก์ชันสำหรับกำหนดสีของสถานะ (คัดลอกมาจาก publications.php)
function getStatusBadge(string $status): string {
    switch ($status) {
        case 'Approved': case 'approved': 
            $class = 'bg-green-100 text-green-700 border-green-300';
            $icon = 'fas fa-check-circle';
            $thaiStatus = 'อนุมัติแล้ว';
            break;
        case 'Pending': case 'Waiting': case 'waiting': 
            $class = 'bg-yellow-100 text-yellow-700 border-yellow-300 animate-pulse';
            $icon = 'fas fa-clock';
            $thaiStatus = 'รอการอนุมัติ';
            break;
        case 'Rejected': case 'rejected':
            $class = 'bg-red-100 text-red-700 border-red-300';
            $icon = 'fas fa-times-circle';
            $thaiStatus = 'ถูกปฏิเสธ';
            break;
        case 'Revision': case 'revision':
            $class = 'bg-orange-100 text-orange-700 border-orange-300';
            $icon = 'fas fa-pen-square';
            $thaiStatus = 'ร้องขอแก้ไข';
            break;
        default:
            $class = 'bg-gray-100 text-gray-700 border-gray-300';
            $icon = 'fas fa-info-circle';
            $thaiStatus = 'ไม่ระบุสถานะ';
    }
    
    return "<span class='inline-flex items-center px-3 py-1 text-sm font-semibold rounded-full border shadow-sm {$class}'>
                <i class='{$icon} mr-2'></i>
                {$thaiStatus}
            </span>";
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>แก้ไขผลงานตีพิมพ์ | ระบบ จัดการการตีพิมพ์ผลงานของอาจารย์</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

  <style>
      .text-theme { color: #1d4ed8; } 
      .bg-theme-light { background-color: #eff6ff; } 
      .hover-bg-theme { background-color: #dbeafe; } 
      .right-icons > a {
          display: flex; align-items: center; justify-content: center;
          width: 40px; height: 40px; border-radius: 9999px;
          color: #1d4ed8; 
      }
      .right-icons > a:hover { background-color: #dbeafe; } 
      .psu-logo { height: 100px; object-fit: contain; }
      /* Custom style for active menu in this page */
      .menu-active {
        color: #1d4ed8 !important; 
        background-color: #dbeafe !important; 
        font-weight: 600;
      }
  </style>
</head>
<body class="flex min-h-screen font-sans bg-gray-100">

<aside class="w-64 bg-white shadow-lg p-6 flex flex-col sticky top-0 h-screen">
    <div class="flex flex-col items-center border-b pb-4 mb-4">
        <img src="./img/img_psu.png" alt="PSU Logo" class="psu-logo"> 
        <span class="text-xs font-semibold text-gray-600">ระบบจัดการการตีพิมพ์</span>
    </div>

    <a href="edit_profile.php" title="แก้ไขข้อมูลส่วนตัว">
        <div class="flex items-center px-1 py-3 mb-4 bg-gray-50 rounded-lg border border-gray-200 hover:bg-gray-100 transition duration-150">
            <i class="fas fa-user-circle text-2xl text-gray-600 ml-1 mr-3"></i>
            <span class="text-sm font-semibold text-gray-700 truncate">
              <?= $current_user_name; ?>
            </span>
        </div>
    </a>

    <nav class="w-full flex-grow">
        <a href="Home-PR.php" class="flex items-center p-3 rounded-lg mb-3 text-gray-700 hover:bg-blue-100 hover:text-blue-700 transition-colors duration-150">
            <i class="fas fa-home w-5 h-5 mr-3"></i> หน้าหลัก
        </a>
        <a href="publications.php" class="flex items-center p-3 rounded-lg mb-3 menu-active transition-colors duration-150">
            <i class="fas fa-list-alt w-5 h-5 mr-3"></i> รายการผลงานตีพิมพ์
        </a>
        <a href="add_publication.php" class="flex items-center p-3 rounded-lg mb-3 text-gray-700 hover:bg-blue-100 hover:text-blue-700 transition-colors duration-150">
            <i class="fas fa-plus-circle w-5 h-5 mr-3"></i> เพิ่มผลงานตีพิมพ์
        </a>
        <a href="history.php" class="flex items-center p-3 rounded-lg text-gray-700 hover:bg-blue-100 hover:text-blue-700 transition-colors duration-150">
            <i class="fas fa-history w-5 h-5 mr-3"></i> ประวัติการแก้ไข
        </a>
        <a href="usermannual.php" class="flex items-center p-3 rounded-lg text-gray-700 hover:bg-blue-100 hover:text-blue-700 transition-colors duration-150">
            <i class="fas fa-book w-5 h-5 mr-3"></i> คู่มือการใช้งาน
        </a>
        <a href="contact.php" class="flex items-center p-3 rounded-lg text-gray-700 hover:bg-blue-100 hover:text-blue-700 transition-colors duration-150">
            <i class="fas fa-phone-alt w-5 h-5 mr-3"></i> ช่องทางติดต่อ
        </a>
    </nav>

    <div class="px-0 pt-4 border-t border-gray-200">
      <a href="logout.php" class="flex items-center p-3 rounded-lg text-red-500 hover:bg-red-50 transition-colors duration-150">
        <i class="fas fa-sign-out-alt w-5 h-5 mr-3"></i> ออกจากระบบ
      </a>
    </div>
</aside>

<main class="flex-1 p-8">
    <header class="flex items-center justify-between mb-8 pb-4 border-b border-gray-300">
        <h1 class="text-3xl font-bold text-gray-800">
             <i class="fas fa-pen-square text-blue-600 mr-2"></i> แก้ไขผลงานตีพิมพ์
        </h1>
        
        <div class="flex items-center space-x-4 right-icons">
            <a href="#" id="notification-bell" title="แจ้งเตือน" class="relative">
                <i class="fas fa-bell text-2xl"></i>
            </a>
            <a href="edit_profile.php" title="แก้ไขข้อมูลส่วนตัว">
                <i class="fas fa-user-circle text-2xl"></i>
            </a>
            <span class="text-gray-600 font-medium hidden sm:block hover:text-blue-700 transition-colors duration-150">
              <?= $current_user_name; ?>
            </span>
        </div>
    </header>

    <div class="bg-white p-8 rounded-xl shadow-2xl">
        <?php if ($db_error): ?>
            <div class="p-4 bg-red-100 text-red-700 border border-red-300 rounded-lg shadow-md mb-6">
                <p>⚠️ **ข้อผิดพลาด:** <?= htmlspecialchars($db_error); ?></p>
                <p class="text-sm mt-1">กรุณากลับไปที่ <a href="publications.php" class="font-semibold underline hover:text-red-900">รายการผลงานตีพิมพ์</a></p>
            </div>
        <?php elseif (!$publication): ?>
            <div class="p-4 bg-red-100 text-red-700 border border-red-300 rounded-lg shadow-md mb-6">
                <p>⚠️ **ข้อผิดพลาด:** ไม่พบข้อมูลผลงานที่ต้องการแก้ไข</p>
                <p class="text-sm mt-1">กรุณาตรวจสอบ ID ผลงานอีกครั้ง</p>
            </div>
        <?php else: ?>
            <h2 class="text-2xl font-bold text-gray-800 mb-6 pb-2 border-b-2 border-blue-200">
                ผลงาน: <span class="text-blue-700">#<?= $publication['Pub_id']; ?> <?= htmlspecialchars($publication['title']); ?></span>
            </h2>

            <?php if ($message): ?>
                <div class="p-4 rounded-lg shadow-md mb-6 
                    <?= $message_type === 'success' ? 'bg-green-100 text-green-700 border border-green-300' : 'bg-red-100 text-red-700 border border-red-300'; ?>">
                    <p class="font-semibold"><i class="fas fa-info-circle mr-2"></i><?= $message; ?></p>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" action="edit_publication.php?id=<?= $pub_id; ?>">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- คอลัมน์ซ้าย: ข้อมูลหลัก -->
                    <div>
                        <div class="mb-4">
                            <label for="title" class="block text-sm font-medium text-gray-700 mb-1">ชื่อผลงานตีพิมพ์ (Title)</label>
                            <input type="text" id="title" name="title" required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 shadow-sm"
                                value="<?= htmlspecialchars($publication['title'] ?? ''); ?>">
                        </div>
                        
                        <!-- FIELD: ประเภทผลงาน -->
                        <div class="mb-4">
                            <label for="publication_type" class="block text-sm font-medium text-gray-700 mb-1">ประเภทของผลงาน (Publication Type)</label>
                            <select id="publication_type" name="publication_type" required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 shadow-sm bg-white">
                                <option value="">-- เลือกประเภทผลงาน --</option>
                                <?php 
                                // ใช้ค่าจาก DB (column 'journal') เพื่อตรวจสอบการเลือก
                                $pub_type = $publication['journal'] ?? '';
                                ?>
                                <?php foreach ($publication_types as $value => $label): ?>
                                    <option value="<?= htmlspecialchars($value); ?>"
                                        <?= ($pub_type == $value) ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="text-xs text-gray-500 mt-1">ค่านี้จะถูกบันทึกในคอลัมน์ `journal` ของฐานข้อมูล</p>
                        </div>
                        <!-- END FIELD -->

                        <div class="mb-4">
                            <label for="publish_year" class="block text-sm font-medium text-gray-700 mb-1">ปีที่ตีพิมพ์ (Publish Year)</label>
                            <input type="number" id="publish_year" name="publish_year" required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 shadow-sm"
                                min="1900" max="<?= date('Y') + 1; ?>"
                                value="<?= htmlspecialchars($publication['publish_year'] ?? date('Y')); ?>">
                        </div>
                    </div>

                    <!-- คอลัมน์ขวา: สถานะและไฟล์ -->
                    <div>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">สถานะปัจจุบัน (Status)</label>
                            <div class="p-3 border border-gray-300 rounded-lg bg-gray-50 flex justify-between items-center shadow-sm">
                                <?= getStatusBadge($publication['status'] ?? 'N/A'); ?>
                                <span class="text-xs text-gray-500 ml-4">
                                    สถานะถูกกำหนดโดย Admin/Reviewer
                                </span>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="publication_file" class="block text-sm font-medium text-gray-700 mb-1">ไฟล์เอกสาร (PDF/DOC)</label>
                            <p class="text-xs text-gray-500 mb-2">เลือกไฟล์ใหม่เพื่อแทนที่ไฟล์เดิมเท่านั้น</p>
                            
                            <!-- แสดงสถานะไฟล์เดิม -->
                            <div class="p-3 border border-dashed border-gray-400 rounded-lg bg-blue-50/70 mb-3">
                                <p class="text-sm font-semibold text-gray-700 flex items-center">
                                    <i class="fas fa-file-alt mr-2 text-blue-500"></i> ไฟล์เดิม: 
                                    <?php if ($publication['file_path'] && file_exists($publication['file_path'])): ?>
                                        <span class="text-green-600 ml-2 truncate"><?= basename($publication['file_path']); ?></span>
                                        <a href="<?= htmlspecialchars($publication['file_path']); ?>" target="_blank" class="text-xs text-blue-600 hover:text-blue-800 underline ml-2 whitespace-nowrap" title="ดูไฟล์">
                                            (PDF Viewer)
                                        </a>
                                    <?php else: ?>
                                        <span class="text-red-600 ml-2">ยังไม่มีไฟล์เอกสาร</span>
                                    <?php endif; ?>
                                </p>
                            </div>
                            
                            <!-- Input สำหรับอัปโหลดไฟล์ใหม่ -->
                            <input type="file" id="publication_file" name="publication_file" accept=".pdf, .doc, .docx"
                                class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                        </div>

                        <div class="text-sm text-gray-500 mt-4 font-semibold text-red-600">
                            <i class="fas fa-exclamation-triangle mr-1"></i> **สำคัญ:** เมื่อกดบันทึก การแก้ไขข้อมูลจะทำให้สถานะผลงานถูกเปลี่ยนกลับไปเป็น **"รอการอนุมัติ"** เพื่อให้ผู้ดูแลตรวจสอบใหม่
                        </div>
                    </div>
                </div>

                <div class="mt-8 pt-6 border-t border-gray-200 flex justify-end space-x-4">
                    <a href="publications.php" class="px-6 py-3 bg-gray-300 text-gray-800 rounded-lg hover:bg-gray-400 transition-colors font-semibold">
                        <i class="fas fa-times-circle mr-2"></i> ยกเลิก
                    </a>
                    <button type="submit" class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors font-semibold shadow-md">
                        <i class="fas fa-save mr-2"></i> บันทึกการแก้ไข
                    </button>
                </div>
            </form>

        <?php endif; ?>
    </div>
</main>
</div>

<!-- Notification Modal Structure (จำลอง) -->
<div id="notification-modal" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-lg mx-4 overflow-hidden transform transition-all">
        <div class="flex justify-between items-center p-5 border-b bg-blue-50">
            <h3 class="text-xl font-bold text-blue-700">
                <i class="fas fa-bell mr-2"></i> ข้อความแจ้งเตือน
            </h3>
            <button id="close-modal-btn" class="text-gray-500 hover:text-gray-700 text-2xl">
                &times;
            </button>
        </div>
        <div class="p-4 max-h-96 overflow-y-auto space-y-3">
             <p class="text-gray-500 text-center py-4">ไม่มีข้อความแจ้งเตือนใหม่ (จำลอง)</p>
        </div>
        <div class="p-3 border-t flex justify-end">
            <button class="text-blue-600 hover:text-blue-800 text-sm font-medium">ทำเครื่องหมายว่าอ่านแล้วทั้งหมด</button>
        </div>
    </div>
</div>

<script>
    // JavaScript สำหรับ Modal (จำลอง)
    const bellIcon = document.getElementById('notification-bell');
    const modal = document.getElementById('notification-modal');
    const closeModalBtn = document.getElementById('close-modal-btn');

    bellIcon.addEventListener('click', (e) => {
        e.preventDefault(); 
        modal.classList.remove('hidden');
    });

    closeModalBtn.addEventListener('click', () => {
        modal.classList.add('hidden');
    });

    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.classList.add('hidden');
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !modal.classList.contains('hidden')) {
            modal.classList.add('hidden');
        }
    });
</script>

</body>
</html>
<?php
// ปิดการเชื่อมต่อฐานข้อมูลเมื่อเสร็จสิ้น
if (!$db_error && isset($conn) && $conn->ping()) {
    $conn->close();
}
?>
