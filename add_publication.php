<?php
session_start();

// 1. ตรวจสอบว่า user ล็อกอินหรือยัง
if (!isset($_SESSION['user_id'])) {
    header("Location: login-v1.php");
    exit;
}

// 2. เชื่อมต่อฐานข้อมูล
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "group10";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 3. ดึงข้อมูลผู้ใช้จาก session
$user_id = $_SESSION['user_id'];

$sql = "SELECT first_name, last_name, role FROM User WHERE User_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$current_user_name = htmlspecialchars($user['first_name'] . " " . $user['last_name']);
$stmt->close();

$message = '';
$message_type = ''; // 'success' or 'error'
$title = ''; // สำหรับเก็บค่าเก่าในฟอร์ม
$publish_year = ''; // ใช้เก็บค่าเดิมที่ผู้ใช้กรอก (ค.ศ.)
$pub_type = '';

// *************************************************************
// 4. Form Submission Logic (POST)
// *************************************************************
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 4a. Get and sanitize input
    $title = trim($_POST['title'] ?? '');
    // *** แก้ไข: รับค่าเป็น ค.ศ. (C.E.) โดยตรง ***
    $publish_year_ce = (int)($_POST['publish_year'] ?? 0); 
    $pub_type = trim($_POST['pub_type'] ?? ''); 

    // 4b. File upload handling 
    $file_path = NULL;
    $target_dir = "uploads/"; 

    if (!empty($_FILES['publication_file']['name']) && $_FILES['publication_file']['error'] == 0) {
        // ตรวจสอบและสร้างโฟลเดอร์ uploads/ หากยังไม่มี
        if (!is_dir($target_dir)) {
             @mkdir($target_dir, 0777, true); 
        }
        
        $original_file_name = basename($_FILES['publication_file']['name']);
        $file_extension = pathinfo($original_file_name, PATHINFO_EXTENSION);
        $new_file_name = uniqid() . '.' . $file_extension;
        $target_file = $target_dir . $new_file_name;

        if (move_uploaded_file($_FILES['publication_file']['tmp_name'], $target_file)) {
            $file_path = $target_file; 
        } else {
            $message = 'เกิดข้อผิดพลาดในการย้ายไฟล์ไปยังเซิร์ฟเวอร์ โปรดตรวจสอบสิทธิ์การเขียนของโฟลเดอร์ **uploads/**';
            $message_type = 'error';
        }
    }
    
    if ($message_type === 'error') {
        // หากมี error จากการอัปโหลดไฟล์ ให้ข้ามไปแสดงข้อความ
        goto end_submission;
    }

    // 4c. Validate required fields
    if (empty($title) || $publish_year_ce === 0 || empty($pub_type)) {
        $message = 'กรุณากรอกข้อมูลให้ครบถ้วน: ชื่อผลงาน, ปีที่พิมพ์, และประเภทผลงาน';
        $message_type = 'error';
    } else if ($message_type !== 'error') {
        
        // *** โค้ดแก้ไข 1: ลบการแปลง พ.ศ. เป็น ค.ศ. เพราะรับค่ามาเป็น ค.ศ. แล้ว ***
        // $publish_year_ce = $publish_year_be - 543; 
        
        // ตรวจสอบปีที่พิมพ์ (ใช้ $publish_year_ce โดยตรง)
        // อนุญาตให้กรอกได้ถึงปีปัจจุบัน (date("Y")) หรือปีถัดไป (+1)
        if ($publish_year_ce < 1900 || $publish_year_ce > (date("Y") + 1)) {
            $message = 'ข้อผิดพลาด: ปีที่พิมพ์เผยแพร่ไม่ถูกต้อง กรุณาตรวจสอบปี (ค.ศ) อีกครั้ง';
            $message_type = 'error';
            // เก็บค่า ค.ศ. เดิมไว้แสดงผลในฟอร์ม
            $publish_year = $publish_year_ce;
            goto end_submission;
        }

        $status = 'waiting'; 
        $pub_year_str = (string)$publish_year_ce; // *** ใช้ปี ค.ศ. บันทึกลงฐานข้อมูล ***

        try {
            $conn->begin_transaction();
            
            // 4d. Insert into Publication table
            $pub_sql = "INSERT INTO Publication (Author_id, title, publish_year, type, file_path, status) 
                        VALUES (?, ?, ?, ?, ?, ?)";
            $stmt_pub = $conn->prepare($pub_sql);
            
            // ใช้ $pub_year_str (ปี ค.ศ.)
            $stmt_pub->bind_param("isssss", $user_id, $title, $pub_year_str, $pub_type, $file_path, $status);
            $stmt_pub->execute();

            $new_pub_id = $conn->insert_id;
            $stmt_pub->close();
            
            // 4e. Insert into PublicationHistory table
            $history_action = 'Added new publication'; 
            $history_sql = "INSERT INTO PublicationHistory (Pub_id, Edited_by, edit_date, change_detail) 
                            VALUES (?, ?, NOW(), ?)";
            $stmt_history = $conn->prepare($history_sql);
            
            $stmt_history->bind_param("iis", $new_pub_id, $user_id, $history_action); 
            
            $stmt_history->execute();
            $stmt_history->close();

            $conn->commit();
            $message = 'เพิ่มผลงานตีพิมพ์: "' . htmlspecialchars($title) . '" เรียบร้อยแล้ว! (สถานะ: รอการอนุมัติ)';
            $message_type = 'success';
            
            // Clear form data after success
            $title = $publish_year = $pub_type = '';

        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            $message = "เกิดข้อผิดพลาดในการบันทึกข้อมูล: " . $e->getMessage();
            $message_type = 'error';
            $publish_year = $publish_year_ce; // เก็บค่า ค.ศ. เดิมไว้แสดงผลในฟอร์ม
        }
    }
}
end_submission:
// หากเกิดข้อผิดพลาด ให้ใช้ค่า $publish_year (ค.ศ.) ในการแสดงผลฟอร์ม
if ($message_type === 'error' && $publish_year === '') {
    $publish_year = $_POST['publish_year'] ?? '';
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>เพิ่มผลงานตีพิมพ์ | ระบบ จัดการการตีพิมพ์ผลงานของอาจารย์</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

  <style>
      /* กำหนดสีหลักของธีม (สีน้ำเงิน) */
      .text-theme { color: #1d4ed8; } /* blue-700 */
      .bg-theme-light { background-color: #eff6ff; } /* blue-50 */
      .hover-bg-theme { background-color: #dbeafe; } /* blue-100 */

      /* การจัดเรียงใน Header */
      .right-icons > a {
          display: flex;
          align-items: center;
          justify-content: center;
          width: 40px;
          height: 40px;
          border-radius: 9999px; /* full circle */
          color: #1d4ed8; /* blue-700 */
      }
      .right-icons > a:hover {
          background-color: #dbeafe; /* blue-100 */
      }
      .psu-logo {
          height: 100px; /* ขนาดโลโก้ */
          object-fit: contain;
      }
      /* Custom style for active menu in this page */
      .menu-active {
        color: #1d4ed8 !important; /* text-blue-700 */
        background-color: #dbeafe !important; /* bg-blue-100 */
        font-weight: 600;
      }
  </style>
</head>
<body class="flex min-h-screen font-sans bg-gray-100">

<aside class="w-64 bg-white shadow-lg p-6 flex flex-col sticky top-0 h-screen">
    <div class="flex flex-col items-center border-b pb-4 mb-4">
        <img src="./img/img_psu.png" alt="PSU Logo" class="psu-logo ">
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
        <a href="publications.php" class="flex items-center p-3 rounded-lg text-gray-700 hover:bg-blue-100 hover:text-blue-700 transition-colors duration-150">
            <i class="fas fa-list-alt w-5 h-5 mr-3"></i> รายการผลงานตีพิมพ์
        </a>
        <a href="add_publication.php" class="flex items-center p-3 rounded-lg mb-3 menu-active hover:bg-blue-200 hover:text-blue-900 transition-colors duration-150">
            <i class="fas fa-plus-circle w-5 h-5 mr-3"></i> เพิ่มผลงานตีพิมพ์
        </a>
        <a href="pubHis.php" class="flex items-center p-3 rounded-lg text-gray-700 hover:bg-blue-100 hover:text-blue-700 transition-colors duration-150">
            <i class="fas fa-history w-5 h-5 mr-3"></i> ประวัติการแก้ไข
        </a>
        <a href="usermannual.php" class="flex items-center p-3 rounded-lg text-gray-700 hover:bg-blue-100 hover:text-blue-700 transition-colors duration-150">
            <i class="fas fa-book w-5 h-5 mr-3"></i> คู่มือการใช้งาน
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
        <h1 class="text-3xl font-bold text-gray-800">เพิ่มผลงานตีพิมพ์ใหม่</h1>
        
        <div class="flex items-center space-x-4 right-icons">
            <a href="#" title="แจ้งเตือน" class="relative">
                <i class="fas fa-bell text-2xl"></i>
            </a>
            <a href="edit_profile.php" title="แก้ไขข้อมูลส่วนตัว">
                <i class="fas fa-user-circle text-2xl"></i>
            </a>
            <a href="edit_profile.php" class="text-gray-600 font-medium hidden sm:block hover:text-blue-700 transition-colors duration-150">
              <?= $current_user_name; ?>
            </a>
        </div>
    </header>

    <?php if ($message): ?>
        <div class="p-4 mb-4 rounded-lg <?= $message_type === 'success' ? 'bg-green-100 text-green-700 border border-green-200' : 'bg-red-100 text-red-700 border border-red-200'; ?>">
            <p class="font-semibold"><?= htmlspecialchars($message); ?></p>
        </div>
    <?php endif; ?>

    <section class="bg-white p-8 rounded-xl shadow-2xl">
        <h2 class="text-2xl font-semibold text-gray-800 mb-6 border-b pb-3">กรอกรายละเอียดผลงาน</h2>
        
        <form method="POST" action="add_publication.php" enctype="multipart/form-data" class="space-y-6">
            
            <div>
                <label for="title" class="block text-sm font-medium text-gray-700 mb-1">ชื่อผลงาน <span class="text-red-500">*</span></label>
                <input type="text" name="title" id="title" required 
                       class="mt-1 block w-full border border-gray-300 rounded-lg shadow-sm p-3 focus:ring-blue-500 focus:border-blue-500"
                       placeholder="เช่น การวิจัยปัจจัยที่มีผลต่อความสำเร็จของเว็บไซด์"
                       value="<?= htmlspecialchars($title); ?>">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <!-- *** แก้ไข: ปรับ placeholder และ max ให้ตรงกับ ค.ศ. (C.E.) *** -->
                    <label for="publish_year" class="block text-sm font-medium text-gray-700 mb-1">ปีที่พิมพ์เผยแพร่ (ค.ศ.) <span class="text-red-500">*</span></label>
                    <input type="number" name="publish_year" id="publish_year" required 
                           class="mt-1 block w-full border border-gray-300 rounded-lg shadow-sm p-3 focus:ring-blue-500 focus:border-blue-500"
                           placeholder="เช่น 2025" min="1900" max="<?= date("Y") + 1; ?>"
                           value="<?= htmlspecialchars($publish_year); ?>">
                </div>

                <div>
                    <label for="pub_type" class="block text-sm font-medium text-gray-700 mb-1">ประเภทผลงาน <span class="text-red-500">*</span></label>
                    <select name="pub_type" id="pub_type" required
                            class="mt-1 block w-full border border-gray-300 rounded-lg shadow-sm p-3 focus:ring-blue-500 focus:border-blue-500 bg-white">
                        <option value="">-- เลือกประเภท --</option>
                        <option value="Journal" <?= $pub_type === 'Journal' ? 'selected' : ''; ?>>บทความวารสาร</option>
                        <option value="Conference" <?= $pub_type === 'Conference' ? 'selected' : ''; ?>>นำเสนอในการประชุม</option>
                        <option value="Thesis" <?= $pub_type === 'Thesis' ? 'selected' : ''; ?>>วิทยานิพนธ์/ภาคนิพนธ์</option>
                        <option value="Other" <?= $pub_type === 'Other' ? 'selected' : ''; ?>>อื่นๆ</option>
                    </select>
                </div>
            </div>

            <div>
                <label for="publication_file" class="block text-sm font-medium text-gray-700 mb-1">ไฟล์ผลงาน (PDF/Doc/ฯลฯ)</label>
                <input type="file" name="publication_file" id="publication_file" 
                       class="mt-1 block w-full text-sm text-gray-500
                       file:mr-4 file:py-2 file:px-4
                       file:rounded-full file:border-0
                       file:text-sm file:font-semibold
                       file:bg-blue-50 file:text-blue-700
                       hover:file:bg-blue-100">
                <p class="mt-1 text-xs text-gray-500">รองรับไฟล์: PDF, DOCX, ZIP (ไม่เกิน 50MB)</p>
            </div>

            <div class="pt-4 border-t border-gray-200">
                <button type="submit" class="w-full sm:w-auto bg-green-600 text-white px-6 py-3 rounded-xl shadow-lg hover:bg-green-700 transition-colors duration-200 text-lg font-medium flex items-center justify-center">
                    <i class="fas fa-save mr-3"></i> บันทึกผลงานตีพิมพ์
                </button>
            </div>

        </form>
    </section>
</main>
</div>

</body>
</html>
<?php
$conn->close();
?>
