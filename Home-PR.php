<?php
// === START: DEBUGGING AND ERROR REPORTING ===
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// === END: DEBUGGING AND ERROR REPORTING ===

session_start();

// ตรวจสอบว่า user ล็อกอินหรือยัง
if (!isset($_SESSION['user_id'])) {
    header("Location: login-v1.php");
    exit;
}

// เชื่อมต่อฐานข้อมูล
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "group10";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ดึงข้อมูลผู้ใช้จาก session
$user_id = $_SESSION['user_id']; // User_id ของอาจารย์ที่ล็อกอินอยู่

$sql = "SELECT first_name, last_name, role FROM User WHERE User_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$current_user_name = htmlspecialchars($user['first_name'] . " " . $user['last_name']);
$user_role = $user['role'];
$stmt->close();

// --- กำหนดค่าเริ่มต้นสำหรับตัวแปรสถานะ ---
$status_message = null; 
$status_type = 'info'; 

// *************************************************************
// 1. การจัดการการแจ้งเตือน (Notifications)
// *************************************************************

// 1.1 ดึงข้อมูลการแจ้งเตือนจริงจากฐานข้อมูล
$notifications = [];
$unread_count = 0;

// ดึงข้อมูลการแจ้งเตือนที่ถูกส่งถึง User_id ที่ล็อกอินอยู่เท่านั้น (ใช้ Noti_id ตามโครงสร้าง DB)
$sql_noti = "SELECT Noti_id, message, date_time, status FROM Notification WHERE User_id = ? ORDER BY date_time DESC";
$stmt_noti = $conn->prepare($sql_noti);
$stmt_noti->bind_param("i", $user_id);
$stmt_noti->execute();
$result_noti = $stmt_noti->get_result();

while ($row = $result_noti->fetch_assoc()) {
    $is_read = ($row['status'] === 'read');
    
    // ตรวจสอบสถานะและนับ
    if (!$is_read) {
        $unread_count++;
    }

    // คำนวณเวลาที่ผ่านมา (อย่างง่าย)
    $timestamp = strtotime($row['date_time']);
    $time_diff = time() - $timestamp;

    if ($time_diff < 60) {
        $time_ago = $time_diff . ' วินาทีที่แล้ว';
    } elseif ($time_diff < 3600) {
        $time_ago = floor($time_diff / 60) . ' นาทีที่แล้ว';
    } elseif ($time_diff < 86400) {
        $time_ago = floor($time_diff / 3600) . ' ชั่วโมงที่แล้ว';
    } else {
        $time_ago = date('d/m/Y H:i', $timestamp);
    }

    $notifications[] = [
        'id' => $row['Noti_id'], // ใช้ Noti_id ตามโครงสร้างตาราง
        // ข้อความจะถูกแสดงตามที่ Staff/Admin/System บันทึกไว้ในฟิลด์ message
        'message' => htmlspecialchars($row['message']), 
        'time' => $time_ago,
        'is_read' => $is_read
    ];
}
$stmt_noti->close();

// *************************************************************
// 2. การจัดการ POST Request สำหรับ Mark All As Read
// *************************************************************
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_read_all'])) {
    // อัปเดตสถานะการแจ้งเตือนทั้งหมดที่ยังไม่ได้อ่านให้เป็น 'read' (อัปเดตเฉพาะของ User_id นี้เท่านั้น)
    $sql_update = "UPDATE Notification SET status = 'read' WHERE User_id = ? AND status = 'unread'";
    $stmt_update = $conn->prepare($sql_update);
    $stmt_update->bind_param("i", $user_id);

    if ($stmt_update->execute()) {
        $stmt_update->close();
        // Redirect เพื่อป้องกัน Form Resubmission และแสดงข้อความสถานะ
        header("Location: Home-PR.php?update_status=success_read");
        exit();
    } else {
        $stmt_update->close();
        $status_message = "❌ เกิดข้อผิดพลาดในการทำเครื่องหมายว่าอ่านแล้ว: " . $conn->error;
        $status_type = 'error';
    }
}

// *************************************************************
// 3. ตรวจสอบสถานะหลังการ Redirect
// *************************************************************
if (isset($_GET['update_status']) && $_GET['update_status'] === 'success_read') {
    $status_message = "✅ ทำเครื่องหมายการแจ้งเตือนทั้งหมดว่าอ่านแล้วเรียบร้อย";
    // ล้างพารามิเตอร์ GET ออกจาก URL
    $redirect_url = strtok($_SERVER["REQUEST_URI"], '?');
    header("Refresh: 3; URL=$redirect_url"); // รีเฟรชหลังจาก 3 วินาที
    $status_type = 'success';
}

?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>หน้าหลัก | ระบบ จัดการการตีพิมพ์ผลงานของอาจารย์</title>
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
      /* Status Message Styling */
      .status-success { background-color: #d1fae5; color: #065f46; border-color: #34d399; }
      .status-error { background-color: #fee2e2; color: #991b1b; border-color: #f87171; }
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
        <a href="Home-PR.php" class="flex items-center p-3 rounded-lg mb-3 text-theme bg-blue-100 hover:bg-blue-200 hover:text-blue-900 font-semibold transition-colors duration-150 menu-active">
            <i class="fas fa-home w-5 h-5 mr-3"></i> หน้าหลัก
        </a>
        <a href="publications.php" class="flex items-center p-3 rounded-lg mb-3 text-gray-700 hover:bg-blue-100 hover:text-blue-700 transition-colors duration-150">
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
        <h1 class="text-3xl font-bold text-gray-800">ระบบ จัดการการตีพิมพ์ผลงานของอาจารย์</h1>
        
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
            <a href="edit_profile.php" class="text-gray-600 font-medium hidden sm:block hover:text-blue-700 transition-colors duration-150">
              <?= $current_user_name; ?>
            </a>
        </div>
    </header>

    <?php if ($status_message): ?>
        <div class="mb-6 p-4 rounded-lg shadow-md font-medium border-l-4 
            <?= $status_type === 'success' ? 'status-success border-green-500' : 'status-error border-red-500' ?>">
            <?= $status_message; ?>
        </div>
    <?php endif; ?>

    <div class="flex flex-col items-center justify-center p-10 bg-white rounded-xl shadow-2xl min-h-[400px]">
        <h2 class="text-2xl font-semibold text-gray-700 mb-8">เมนูการจัดการผลงานตีพิมพ์</h2>
        <div class="flex flex-col sm:flex-row space-y-4 sm:space-y-0 sm:space-x-6">
            <a href="publications.php" class="block">
                <button class="bg-blue-600 text-white px-8 py-4 rounded-xl shadow-lg hover:bg-blue-700 transition-colors duration-200 text-lg font-medium w-full sm:w-64 flex items-center justify-center">
                    <i class="fas fa-list-alt mr-3"></i> รายการผลงานตีพิมพ์
                </button>
            </a>
            <a href="add_publication.php" class="block">
                <button class="bg-green-600 text-white px-8 py-4 rounded-xl shadow-lg hover:bg-green-700 transition-colors duration-200 text-lg font-medium w-full sm:w-64 flex items-center justify-center">
                    <i class="fas fa-plus-circle mr-3"></i> เพิ่มผลงานตีพิมพ์
                </button>
            </a>
        </div>
    </div>
</main>
</div>

<!-- Notification Modal -->
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
                <p class="text-gray-500 text-center py-4">ไม่มีข้อความแจ้งเตือน</p>
            <?php else: ?>
                <?php foreach ($notifications as $notification): ?>
                    <div class="p-3 rounded-lg border 
                        <?= $notification['is_read'] ? 'bg-gray-50 border-gray-200 text-gray-700' : 'bg-blue-100 border-blue-300 font-semibold shadow-sm'; ?>">
                        <p class="text-sm flex items-center">
                            <i class="<?= $notification['is_read'] ? 'far fa-envelope-open text-gray-500' : 'fas fa-envelope text-blue-600'; ?> mr-2"></i>
                            <span class="font-bold">ข้อความ:</span>
                        </p>
                        <!-- ข้อความที่บันทึกไว้ใน DB ซึ่งรวม Sender เข้ามาแล้ว -->
                        <p class="mt-1 ml-5 text-base leading-snug break-words"><?= $notification['message']; ?></p> 
                        <p class="text-xs text-right mt-1 <?= $notification['is_read'] ? 'text-gray-500' : 'text-blue-700'; ?>">
                            <?= $notification['time']; ?>
                        </p>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <div class="p-3 border-t flex justify-end bg-gray-50">
            <!-- Form สำหรับส่งค่า Mark All As Read -->
            <form method="POST" action="Home-PR.php" onsubmit="return confirm('คุณต้องการทำเครื่องหมายว่าอ่านแล้วทั้งหมดหรือไม่?');">
                <input type="hidden" name="mark_read_all" value="1">
                <button type="submit" 
                        class="text-blue-600 hover:text-blue-800 text-sm font-medium py-2 px-3 rounded-lg hover:bg-blue-100 transition duration-150"
                        <?= $unread_count === 0 ? 'disabled' : '' ?>>
                    <i class="fas fa-check-double mr-1"></i> ทำเครื่องหมายว่าอ่านแล้วทั้งหมด
                </button>
            </form>
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
$conn->close();
?>
