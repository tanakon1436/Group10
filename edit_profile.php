<?php
// === START: DEBUGGING AND ERROR REPORTING ===
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// === END: DEBUGGING AND ERROR REPORTING ===

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

// --- กำหนดค่าเริ่มต้นสำหรับตัวแปร ---
$message = ''; // สำหรับข้อความอัปเดตโปรไฟล์
$message_type = '';
$user_data = [];
$status_message = null; // สำหรับข้อความสถานะการแจ้งเตือน
$status_type = 'info';


// *************************************************************
// LOGIC A: การจัดการการแจ้งเตือน (Notifications)
// *************************************************************

// A1. ดึงข้อมูลการแจ้งเตือน
$notifications = [];
$unread_count = 0;

$sql_noti = "SELECT Noti_id, message, date_time, status FROM Notification WHERE User_id = ? ORDER BY date_time DESC";
$stmt_noti = $conn->prepare($sql_noti);
$stmt_noti->bind_param("i", $user_id);
$stmt_noti->execute();
$result_noti = $stmt_noti->get_result();

while ($row = $result_noti->fetch_assoc()) {
    $is_read = ($row['status'] === 'read');
    if (!$is_read) {
        $unread_count++;
    }
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
        'id' => $row['Noti_id'],
        'message' => htmlspecialchars($row['message']), 
        'time' => $time_ago,
        'is_read' => $is_read
    ];
}
$stmt_noti->close();


// *************************************************************
// LOGIC B: Form Submission Logic (POST)
// *************************************************************
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // B1. ตรวจสอบว่าเป็น POST สำหรับ "Mark all as read" หรือไม่
    if (isset($_POST['mark_read_all'])) {
        $sql_update = "UPDATE Notification SET status = 'read' WHERE User_id = ? AND status = 'unread'";
        $stmt_update = $conn->prepare($sql_update);
        $stmt_update->bind_param("i", $user_id);

        if ($stmt_update->execute()) {
            $stmt_update->close();
            // Redirect เพื่อป้องกันการส่งฟอร์มซ้ำ และแสดงข้อความสถานะ
            header("Location: edit_profile.php?update_status=success_read");
            exit();
        } else {
            $stmt_update->close();
            $status_message = "❌ เกิดข้อผิดพลาดในการทำเครื่องหมายว่าอ่านแล้ว: " . $conn->error;
            $status_type = 'error';
        }
    } 
    // B2. ถ้าไม่ใช่ ก็จะเป็น POST สำหรับอัปเดตโปรไฟล์
    else {
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $tel = trim($_POST['tel'] ?? '');
        $department = trim($_POST['department'] ?? '');
        
        if (empty($first_name) || empty($last_name) || empty($email) || empty($department)) {
            $message = 'กรุณากรอกข้อมูลชื่อ, นามสกุล, อีเมล และหน่วยงานให้ครบถ้วน';
            $message_type = 'error';
        } else {
            try {
                $update_sql = "UPDATE User SET first_name = ?, last_name = ?, email = ?, tel = ?, Department = ? WHERE User_id = ?";
                $stmt = $conn->prepare($update_sql);
                $stmt->bind_param("sssssi", $first_name, $last_name, $email, $tel, $department, $user_id);
                $stmt->execute();
                $stmt->close();
                $message = 'บันทึกการแก้ไขข้อมูลส่วนตัวเรียบร้อยแล้ว!';
                $message_type = 'success';
            } catch (mysqli_sql_exception $e) {
                $message = "เกิดข้อผิดพลาดในการบันทึกข้อมูล: " . $e->getMessage();
                $message_type = 'error';
            }
        }
    }
}

// *************************************************************
// LOGIC C: ตรวจสอบสถานะหลังการ Redirect (สำหรับแจ้งเตือน)
// *************************************************************
if (isset($_GET['update_status']) && $_GET['update_status'] === 'success_read') {
    $status_message = "✅ ทำเครื่องหมายการแจ้งเตือนทั้งหมดว่าอ่านแล้วเรียบร้อย";
    $redirect_url = strtok($_SERVER["REQUEST_URI"], '?');
    header("Refresh: 3; URL=$redirect_url"); // แสดงข้อความ 3 วินาทีแล้วค่อยรีเฟรช
    $status_type = 'success';
}


// *************************************************************
// LOGIC D: ดึงข้อมูลผู้ใช้ปัจจุบัน (หลังการอัปเดต หรือเมื่อเข้าสู่หน้าครั้งแรก)
// *************************************************************
$fetch_sql = "SELECT first_name, last_name, Username, email, tel, role, Department FROM User WHERE User_id = ?";
$stmt_fetch = $conn->prepare($fetch_sql);
$stmt_fetch->bind_param("i", $user_id);
$stmt_fetch->execute();
$result_fetch = $stmt_fetch->get_result();
$user_data = $result_fetch->fetch_assoc();
$current_user_name = htmlspecialchars($user_data['first_name'] . " " . $user_data['last_name']);
$stmt_fetch->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>แก้ไขข้อมูลส่วนตัว | ระบบ จัดการการตีพิมพ์ผลงานของอาจารย์</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  
  <style>
      .text-theme { color: #1d4ed8; }
      .bg-theme-light { background-color: #eff6ff; }
      .hover-bg-theme { background-color: #dbeafe; }
      .right-icons > a {
          display: flex;
          align-items: center;
          justify-content: center;
          width: 40px;
          height: 40px;
          border-radius: 9999px;
          color: #1d4ed8;
      }
      .right-icons > a:hover { background-color: #dbeafe; }
      .psu-logo { height: 100px; object-fit: contain; }
      .profile-active { background-color: #dbeafe !important; }
      .status-success { background-color: #d1fae5; color: #065f46; border-color: #34d399; }
      .status-error { background-color: #fee2e2; color: #991b1b; border-color: #f87171; }
  </style>
</head>
<body class="flex min-h-screen font-sans bg-gray-100">

<aside class="w-64 bg-white shadow-lg p-6 flex flex-col sticky top-0 h-screen">
    <div class="flex flex-col items-center border-b pb-4 mb-4">
        <img src="./img/img_psu.png" alt="PSU Logo" class="psu-logo ">
        <span class="text-xs font-semibold text-gray-600">ระบบจัดการการตีพิมพ์</span>
    </div>

    <a href="edit_profile.php" title="แก้ไขข้อมูลส่วนตัว">
        <div class="flex items-center px-1 py-3 mb-4 bg-gray-50 rounded-lg border border-gray-200 profile-active transition duration-150">
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
        <h1 class="text-3xl font-bold text-gray-800">แก้ไขข้อมูลส่วนตัว</h1>
        
        <div class="flex items-center space-x-4 right-icons">
            <!-- Bell Icon with Badge -->
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

    <!-- Display Area for Messages -->
    <?php if ($message): ?>
        <div class="p-4 mb-4 rounded-lg <?= $message_type === 'success' ? 'bg-green-100 text-green-700 border border-green-200' : 'bg-red-100 text-red-700 border border-red-200'; ?>">
            <p class="font-semibold"><?= htmlspecialchars($message); ?></p>
        </div>
    <?php endif; ?>
    <?php if ($status_message): ?>
        <div class="mb-6 p-4 rounded-lg shadow-md font-medium border-l-4 
            <?= $status_type === 'success' ? 'status-success border-green-500' : 'status-error border-red-500' ?>">
            <?= $status_message; ?>
        </div>
    <?php endif; ?>

    <section class="bg-white p-8 rounded-xl shadow-2xl">
        <h2 class="text-2xl font-semibold text-gray-800 mb-6 border-b pb-3">ข้อมูลบัญชีผู้ใช้งาน</h2>
        
        <form method="POST" action="edit_profile.php" class="space-y-6">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Username (ไม่สามารถแก้ไขได้)</label>
                    <input type="text" disabled
                           class="mt-1 block w-full border border-gray-200 rounded-lg shadow-inner p-3 bg-gray-50 text-gray-500"
                           value="<?= htmlspecialchars($user_data['Username'] ?? ''); ?>">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">ตำแหน่ง/บทบาท</label>
                    <input type="text" disabled
                           class="mt-1 block w-full border border-gray-200 rounded-lg shadow-inner p-3 bg-gray-50 text-gray-500"
                           value="<?= htmlspecialchars($user_data['role'] ?? ''); ?>">
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="first_name" class="block text-sm font-medium text-gray-700 mb-1">ชื่อจริง <span class="text-red-500">*</span></label>
                    <input type="text" name="first_name" id="first_name" required 
                           class="mt-1 block w-full border border-gray-300 rounded-lg shadow-sm p-3 focus:ring-blue-500 focus:border-blue-500"
                           value="<?= htmlspecialchars($user_data['first_name'] ?? ''); ?>">
                </div>
                <div>
                    <label for="last_name" class="block text-sm font-medium text-gray-700 mb-1">นามสกุล <span class="text-red-500">*</span></label>
                    <input type="text" name="last_name" id="last_name" required 
                           class="mt-1 block w-full border border-gray-300 rounded-lg shadow-sm p-3 focus:ring-blue-500 focus:border-blue-500"
                           value="<?= htmlspecialchars($user_data['last_name'] ?? ''); ?>">
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email <span class="text-red-500">*</span></label>
                    <input type="email" name="email" id="email" required 
                           class="mt-1 block w-full border border-gray-300 rounded-lg shadow-sm p-3 focus:ring-blue-500 focus:border-blue-500"
                           value="<?= htmlspecialchars($user_data['email'] ?? ''); ?>">
                </div>
                <div>
                    <label for="tel" class="block text-sm font-medium text-gray-700 mb-1">เบอร์โทรศัพท์</label>
                    <input type="text" name="tel" id="tel"
                           class="mt-1 block w-full border border-gray-300 rounded-lg shadow-sm p-3 focus:ring-blue-500 focus:border-blue-500"
                           value="<?= htmlspecialchars($user_data['tel'] ?? ''); ?>">
                </div>
            </div>
            <div>
                <label for="department" class="block text-sm font-medium text-gray-700 mb-1">หน่วยงาน/ภาควิชา <span class="text-red-500">*</span></label>
                <input type="text" name="department" id="department" required 
                       class="mt-1 block w-full border border-gray-300 rounded-lg shadow-sm p-3 focus:ring-blue-500 focus:border-blue-500"
                       value="<?= htmlspecialchars($user_data['Department'] ?? ''); ?>">
            </div>
            <div class="pt-4 border-t border-gray-200">
                <button type="submit" class="w-full sm:w-auto bg-blue-600 text-white px-6 py-3 rounded-xl shadow-lg hover:bg-blue-700 transition-colors duration-200 text-lg font-medium flex items-center justify-center">
                    <i class="fas fa-user-edit mr-3"></i> บันทึกการแก้ไขข้อมูล
                </button>
                <p class="mt-4 text-sm text-gray-500">หากต้องการเปลี่ยนรหัสผ่าน กรุณาติดต่อผู้ดูแลระบบ</p>
            </div>
        </form>
    </section>
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
                    <div class="p-3 rounded-lg border <?= $notification['is_read'] ? 'bg-gray-50 border-gray-200 text-gray-700' : 'bg-blue-100 border-blue-300 font-semibold shadow-sm'; ?>">
                        <p class="text-sm flex items-center">
                            <i class="<?= $notification['is_read'] ? 'far fa-envelope-open text-gray-500' : 'fas fa-envelope text-blue-600'; ?> mr-2"></i>
                            <span class="font-bold">ข้อความ:</span>
                        </p>
                        <p class="mt-1 ml-5 text-base leading-snug break-words"><?= $notification['message']; ?></p> 
                        <p class="text-xs text-right mt-1 <?= $notification['is_read'] ? 'text-gray-500' : 'text-blue-700'; ?>">
                            <?= $notification['time']; ?>
                        </p>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <div class="p-3 border-t flex justify-end bg-gray-50">
            <form method="POST" action="edit_profile.php" onsubmit="return confirm('คุณต้องการทำเครื่องหมายว่าอ่านแล้วทั้งหมดหรือไม่?');">
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

<!-- JavaScript for Modal -->
<script>
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
