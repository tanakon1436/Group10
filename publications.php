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

// เชื่อมต่อฐานข้อมูล
$servername = "localhost";
$username = "root"; // ตรวจสอบชื่อผู้ใช้ฐานข้อมูล
$password = "";    // ตรวจสอบรหัสผ่านฐานข้อมูล
$dbname = "group10"; // ตรวจสอบชื่อฐานข้อมูล

$conn = new mysqli($servername, $username, $password, $dbname);
$db_error = null;
if ($conn->connect_error) {
    // บันทึกข้อผิดพลาดการเชื่อมต่อ
    $db_error = "Connection failed: " . $conn->connect_error;
}

// ดึงข้อมูลผู้ใช้จาก session
$user_id = $_SESSION['user_id'];
$current_user_name = "ชื่อ ผู้ใช้งาน (ไม่พบข้อมูล)";
$user_role = "";

// *************************************************************
// ** DOWNLOAD HANDLER ถูกลบออกไปแล้วตามคำขอ **
// *************************************************************

// *************************************************************
// ** CONTINUING NORMAL PAGE DISPLAY LOGIC **
// *************************************************************

$publications = [];

if (!$db_error) {
    // 1. ดึงข้อมูลชื่อและบทบาทของผู้ใช้
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
    } else {
         $db_error .= (empty($db_error) ? '' : ' | ') . "User Query prepare failed: " . $conn->error;
    }


    // *************************************************************
    // ** 2. QUERY ข้อมูลผลงานตีพิมพ์: ดึง file_path **
    // *************************************************************
    $pub_sql = "SELECT Pub_id, title, journal, publish_year, status, file_path 
                FROM Publication 
                WHERE Author_id = ?  
                ORDER BY publish_year DESC";
    
    $pub_stmt = $conn->prepare($pub_sql);
    if ($pub_stmt) {
        $pub_stmt->bind_param("i", $user_id);
        $pub_stmt->execute();
        $pub_result = $pub_stmt->get_result();
        
        while ($row = $pub_result->fetch_assoc()) {
            $publications[] = $row;
        }
        $pub_stmt->close();
    } else {
        $db_error .= (empty($db_error) ? '' : ' | ') . "Publication Query prepare failed: " . $conn->error;
    }
}

// *** ข้อมูลการแจ้งเตือน (Notifications) ยังคงจำลอง ***
$notifications = [
    [
        'sender' => 'Tarathep Madmun (Admin)',
        'message' => 'ผลงานตีพิมพ์ "การวิจัยปัจจัย..." ได้รับการตรวจสอบและอนุมัติแล้ว',
        'time' => '10 นาทีที่แล้ว',
        'is_read' => false
    ],
    [
        'sender' => 'ฝ่ายธุรการ',
        'message' => 'กรุณาอัปเดตข้อมูลส่วนตัวในระบบภายในสัปดาห์นี้',
        'time' => '1 ชั่วโมงที่แล้ว',
        'is_read' => false
    ],
];
$unread_count = count(array_filter($notifications, fn($n) => !$n['is_read']));

// ฟังก์ชันสำหรับกำหนดสีของสถานะ
function getStatusBadge(string $status): string {
    switch ($status) {
        case 'Approved':
        case 'approved': 
            $class = 'bg-green-100 text-green-700 border-green-300';
            $icon = 'fas fa-check-circle';
            $thaiStatus = 'อนุมัติแล้ว';
            break;
        case 'Pending':
        case 'Waiting': 
        case 'waiting': 
            $class = 'bg-yellow-100 text-yellow-700 border-yellow-300 animate-pulse';
            $icon = 'fas fa-clock';
            $thaiStatus = 'รอการอนุมัติ';
            break;
        case 'Rejected':
        case 'rejected':
            $class = 'bg-red-100 text-red-700 border-red-300';
            $icon = 'fas fa-times-circle';
            $thaiStatus = 'ถูกปฏิเสธ';
            break;
        case 'Revision':
        case 'revision':
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
  <title>รายการผลงานตีพิมพ์ | ระบบ จัดการการตีพิมพ์ผลงานของอาจารย์</title>
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
          height: 100px; 
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
        <!-- ต้องแน่ใจว่า path ของรูปภาพถูกต้อง -->
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
        <h1 class="text-3xl font-bold text-gray-800">
             <i class="fas fa-list-alt text-blue-600 mr-2"></i> รายการผลงานตีพิมพ์
        </h1>
        
        <div class="flex items-center space-x-4 right-icons">
            <a href="#" id="notification-bell" title="แจ้งเตือน" class="relative">
                <i class="fas fa-bell text-2xl"></i>
                <?php if ($unread_count > 0): ?>
                    <span class="absolute top-0 right-0 block h-5 w-5 rounded-full ring-2 ring-white bg-red-500 text-white text-xs font-bold flex items-center justify-center -mt-1 -mr-1">
                        <?= $unread_count; ?>
                    </span>
                <?php endif; ?>
            </a>
            <a href="edit_profile.php" title="แก้ไขข้อมูลส่วนตัว">
                <i class="fas fa-user-circle text-2xl"></i>
            </a>
            <span class="text-gray-600 font-medium hidden sm:block hover:text-blue-700 transition-colors duration-150">
              <?= $current_user_name; ?>
            </span>
        </div>
    </header>

    <div class="bg-white p-6 rounded-xl shadow-2xl">
        <h2 class="text-2xl font-bold text-gray-800 mb-6 pb-2 border-b-2 border-blue-200">ผลงานตีพิมพ์ทั้งหมดของท่าน (<?= count($publications); ?> รายการ)</h2>

        <?php if ($db_error): ?>
            <div class="p-4 bg-red-100 text-red-700 border border-red-300 rounded-lg shadow-md mb-4">
                <p>⚠️ **ข้อผิดพลาดฐานข้อมูล:** ไม่สามารถดึงข้อมูลผลงานได้:</p>
                <p class="font-mono text-sm mt-1 break-words"><?= htmlspecialchars($db_error); ?></p>
                <p class="text-sm mt-2 font-semibold">
                    วิธีแก้ไขเบื้องต้น:
                    <ol class="list-decimal list-inside ml-2 mt-1 font-normal">
                        <li>ตรวจสอบค่าตัวแปร `$servername`, `$username`, `$password`, `$dbname` ในโค้ดว่าตรงกับข้อมูล MySQL/MariaDB ของคุณหรือไม่ (โดยเฉพาะรหัสผ่าน)</li>
                        <li>ตรวจสอบว่าตาราง `User` และ `Publication` มีอยู่จริงในฐานข้อมูล `group10`</li>
                    </ol>
                </p>
            </div>
        <?php endif; ?>

        <?php if (empty($publications)): ?>
            <div class="text-center py-10 border-4 border-dashed border-gray-200 rounded-xl bg-gray-50">
                <i class="fas fa-exclamation-circle text-4xl text-gray-400 mb-3"></i>
                <p class="text-lg text-gray-600 font-semibold">ไม่พบผลงานตีพิมพ์ในระบบ</p>
                <a href="add_publication.php" class="mt-4 inline-block bg-blue-600 text-white px-5 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                    <i class="fas fa-plus-circle mr-2"></i> เพิ่มผลงานตีพิมพ์ใหม่
                </a>
            </div>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($publications as $pub): 
                    $file_path = htmlspecialchars($pub['file_path'] ?? '');
                    // ตรวจสอบว่ามีไฟล์หรือไม่
                    $has_file = !empty($file_path) && file_exists($file_path);
                ?>
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center p-5 rounded-xl border-l-4 
                        <?php 
                            // กำหนดสีของเส้นขอบตามสถานะ
                            if (in_array($pub['status'], ['Approved', 'approved'])) echo 'border-green-500 bg-green-50/70';
                            else if (in_array($pub['status'], ['Pending', 'Waiting', 'waiting'])) echo 'border-yellow-500 bg-yellow-50/70';
                            else if (in_array($pub['status'], ['Rejected', 'rejected'])) echo 'border-red-500 bg-red-50/70';
                            else if (in_array($pub['status'], ['Revision', 'revision'])) echo 'border-orange-500 bg-orange-50/70';
                            else echo 'border-gray-300 bg-gray-50';
                        ?>
                        shadow-md hover:shadow-lg transition-shadow duration-300">
                        
                        <div class="flex-1 min-w-0 mb-3 md:mb-0 md:mr-4">
                            <!-- ชื่อผลงาน -->
                            <h3 class="text-xl font-bold text-blue-800 truncate leading-snug">
                                <?= htmlspecialchars($pub['title']); ?>
                            </h3>
                            <!-- รายละเอียด -->
                            <div class="text-gray-600 text-sm mt-1 space-y-1">
                                <p><i class="fas fa-book-open mr-2 text-blue-400"></i> **วารสาร/ประชุม:** <?= htmlspecialchars($pub['journal'] ?? 'ไม่ระบุ'); ?></p>
                                
                                <!-- ปรับการแสดงผลสำหรับคอลัมน์ YEAR -->
                                <p><i class="fas fa-calendar-alt mr-2 text-blue-400"></i> **ปีที่ตีพิมพ์:** <?= htmlspecialchars($pub['publish_year'] ?? 'N/A'); ?>
                                </p>
                                
                                <p class="text-xs text-gray-400 mt-1">ID: #<?= $pub['Pub_id']; ?></p>
                            </div>
                        </div>

                        <!-- สถานะและการดำเนินการ -->
                        <div class="flex flex-col items-start md:items-end space-y-2 md:space-y-1">
                            <!-- สถานะ (Badge) -->
                            <?= getStatusBadge($pub['status']); ?>

                            <!-- ปุ่มดำเนินการ -->
                            <div class="flex space-x-2 mt-3 md:mt-0">
                                <a href="edit_publication.php?id=<?= $pub['Pub_id']; ?>" 
                                   class="px-4 py-2 text-sm bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors font-medium"
                                   title="แก้ไขรายละเอียดผลงาน">
                                    <i class="fas fa-pen mr-1"></i> แก้ไข
                                </a>

                                <?php if ($has_file): ?>
                                    <!-- ปุ่ม PDF Viewer: ลิงก์ตรงไปยัง file_path และเปิดในแท็บใหม่ -->
                                    <a href="<?= $file_path; ?>" 
                                        target="_blank"
                                        class="px-4 py-2 text-sm bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors font-medium"
                                        title="ดูเอกสารในรูปแบบ PDF">
                                        <i class="fas fa-file-pdf mr-1"></i> ดู PDF
                                    </a>
                                <?php else: ?>
                                    <!-- ปุ่ม PDF Viewer แบบ Disabled เมื่อไม่มีไฟล์ -->
                                    <button disabled
                                        class="px-4 py-2 text-sm bg-gray-400 text-white rounded-lg cursor-not-allowed font-medium"
                                        title="ไม่มีไฟล์สำหรับดู">
                                        <i class="fas fa-file-pdf mr-1"></i> ไม่มีไฟล์
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</main>
</div>

<!-- Notification Modal Structure (คัดลอกมาจาก Home-PR.php) -->
<div id="notification-modal" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-lg mx-4 overflow-hidden transform transition-all">
        <div class="flex justify-between items-center p-5 border-b bg-blue-50">
            <h3 class="text-xl font-bold text-blue-700">
                <i class="fas fa-bell mr-2"></i> ข้อความแจ้งเตือน (<?= $unread_count; ?> ข้อความใหม่)
            </h3>
            <button id="close-modal-btn" class="text-gray-500 hover:text-gray-700 text-2xl">
                &times;
            </button>
        </div>

        <div class="p-4 max-h-96 overflow-y-auto space-y-3">
            <?php if (empty($notifications)): ?>
                <p class="text-gray-500 text-center py-4">ไม่มีข้อความแจ้งเตือนใหม่</p>
            <?php else: ?>
                <?php foreach ($notifications as $notification): ?>
                    <div class="p-3 rounded-lg <?= $notification['is_read'] ? 'bg-gray-50 text-gray-700' : 'bg-blue-100 border border-blue-200 font-semibold shadow-sm'; ?>">
                        <p class="text-sm">
                            <i class="<?= $notification['is_read'] ? 'far fa-envelope-open text-gray-500' : 'fas fa-envelope text-blue-600'; ?> mr-2"></i>
                            **<?= htmlspecialchars($notification['sender']); ?>** ส่งข้อความ:
                        </p>
                        <p class="mt-1 ml-5 text-base leading-snug"><?= htmlspecialchars($notification['message']); ?></p>
                        <p class="text-xs text-right mt-1 <?= $notification['is_read'] ? 'text-gray-500' : 'text-blue-600'; ?>">
                            <?= htmlspecialchars($notification['time']); ?>
                        </p>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <div class="p-3 border-t flex justify-end">
            <button class="text-blue-600 hover:text-blue-800 text-sm font-medium">ทำเครื่องหมายว่าอ่านแล้วทั้งหมด</button>
        </div>
    </div>
</div>

<script>
    const bellIcon = document.getElementById('notification-bell');
    const modal = document.getElementById('notification-modal');
    const closeModalBtn = document.getElementById('close-modal-btn');

    // Function to open the modal
    bellIcon.addEventListener('click', (e) => {
        e.preventDefault(); // Prevent default link behavior
        modal.classList.remove('hidden');
    });

    // Function to close the modal using the 'x' button
    closeModalBtn.addEventListener('click', () => {
        modal.classList.add('hidden');
    });

    // Function to close the modal when clicking outside of it
    modal.addEventListener('click', (e) => {
        // Check if the click occurred directly on the modal backdrop (not on the modal content)
        if (e.target === modal) {
            modal.classList.add('hidden');
        }
    });

    // Function to close the modal when pressing the ESC key
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
