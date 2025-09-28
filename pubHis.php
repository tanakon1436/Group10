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
$stmt->close();

// *************************************************************
// 1. การดึงข้อมูลการแจ้งเตือน (Notifications) - ใช้ข้อมูลจำลองชั่วคราว
// *************************************************************
// หมายเหตุ: ควรกำหนดการดึงข้อมูล Notification จริงเหมือนที่ทำใน Home-PR.php 
// แต่เพื่อให้สอดคล้องกับโค้ดต้นฉบับ จะใช้ข้อมูลจำลองเพื่อนับจำนวนที่ยังไม่ได้อ่าน
$notifications = [
    // ข้อมูลการแจ้งเตือนจำลอง...
];
$unread_count = 0; // ตั้งค่าเป็น 0 เพื่อความง่ายในการดีบักหน้าประวัติ

// *************************************************************
// 2. การดึงข้อมูลประวัติการแก้ไข (Publication History Data Fetch)
// *************************************************************

// **ตารางที่จำเป็น:** // 1. PublicationHistory (History_id, Pub_id, Edited_by, change_detail, edit_date)
// 2. Publication (Pub_id, title) -- สมมติว่ามีตาราง Publication ที่มีฟิลด์ title
// 3. User (User_id)
// เงื่อนไข: ดึงเฉพาะประวัติที่อาจารย์ท่านนี้เป็นผู้แก้ไข (Edited_by = $user_id)

$history_records = [];

// คำสั่ง SQL ที่ JOIN ตาราง PublicationHistory กับ Publication และกรองด้วย User_id ที่ล็อกอิน
// **สมมติว่ามีตาราง Publication (ซึ่งจำเป็นต้องมีเพื่อให้ JOIN ได้)**
$history_query = "
    SELECT 
        ph.change_detail, 
        ph.edit_date,
        p.title 
    FROM 
        PublicationHistory ph
    JOIN 
        Publication p ON ph.Pub_id = p.Pub_id
    WHERE 
        ph.Edited_by = ? 
    ORDER BY 
        ph.edit_date DESC
";

// เตรียมและประมวลผลคำสั่ง SQL
if ($stmt_history = $conn->prepare($history_query)) {
    $stmt_history->bind_param("i", $user_id);
    $stmt_history->execute();
    $history_result = $stmt_history->get_result();
    
    // ดึงข้อมูลทั้งหมด
    while ($row = $history_result->fetch_assoc()) {
        // กำหนด action_type อย่างง่ายจาก change_detail เพื่อให้แสดงผลสวยงาม
        $action_type = 'แก้ไขข้อมูล';
        if (strpos($row['change_detail'], 'Added new publication') !== false) {
            $action_type = 'สร้างผลงานใหม่';
        } elseif (strpos($row['change_detail'], 'Deleted publication') !== false) {
             $action_type = 'ลบข้อมูล';
        }
        
        $history_records[] = [
            'title' => $row['title'],
            'action_type' => $action_type,
            'description' => htmlspecialchars($row['change_detail']),
            'timestamp' => $row['edit_date']
        ];
    }
    $stmt_history->close();
} else {
    // แสดงข้อความ error หากมีปัญหาในการเตรียมคำสั่ง SQL
    // ในกรณีที่คุณยังไม่ได้สร้างตาราง Publication อาจเกิด error ที่นี่
    echo "<script>console.error('SQL Prepare Error: " . $conn->error . "');</script>";
}

// ฟังก์ชันสำหรับแปลงวันที่ให้อ่านง่าย
function formatThaiDate($timestamp) {
    // ตรวจสอบว่าเป็นวันที่ที่ถูกต้องหรือไม่
    if (!$timestamp || strtotime($timestamp) === false) {
        return 'ไม่ระบุวันที่';
    }
    return date('d/m/Y H:i', strtotime($timestamp));
}

// ฟังก์ชันสำหรับกำหนดสีตามประเภทการดำเนินการ
function getActionBadge($action_type) {
    switch ($action_type) {
        case 'แก้ไขข้อมูล':
            return 'bg-yellow-100 text-yellow-800 border-yellow-300';
        case 'สร้างผลงานใหม่':
            return 'bg-green-100 text-green-800 border-green-300';
        case 'ส่งตรวจสอบ':
            return 'bg-blue-100 text-blue-800 border-blue-300';
        case 'ลบข้อมูล':
            return 'bg-red-100 text-red-800 border-red-300';
        default:
            return 'bg-gray-100 text-gray-800 border-gray-300';
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ประวัติการแก้ไข | ระบบ จัดการการตีพิมพ์ผลงานของอาจารย์</title>
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
      .history-card:hover {
          transform: translateY(-2px);
          box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
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
        <a href="publications.php" class="flex items-center p-3 rounded-lg mb-3 text-gray-700 hover:bg-blue-100 hover:text-blue-700 transition-colors duration-150">
            <i class="fas fa-list-alt w-5 h-5 mr-3"></i> รายการผลงานตีพิมพ์
        </a>
        <a href="add_publication.php" class="flex items-center p-3 rounded-lg mb-3 text-gray-700 hover:bg-blue-100 hover:text-blue-700 transition-colors duration-150">
            <i class="fas fa-plus-circle w-5 h-5 mr-3"></i> เพิ่มผลงานตีพิมพ์
        </a>
        <!-- ลิงก์นี้คือหน้าปัจจุบัน และถูกตั้งค่าให้เป็น active -->
        <a href="pubHis.php" class="flex items-center p-3 rounded-lg mb-3 text-gray-700 hover:bg-blue-100 hover:text-blue-700 transition-colors duration-150 menu-active">
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
        <h1 class="text-3xl font-bold text-gray-800">ประวัติการแก้ไขผลงานตีพิมพ์</h1>
        
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

    <div class="bg-white rounded-xl shadow-2xl p-6 md:p-8">
        <h2 class="text-2xl font-semibold text-gray-700 mb-6 border-b pb-3">
            การดำเนินการทั้งหมดของ <?= $current_user_name; ?>
        </h2>

        <?php if (empty($history_records)): ?>
            <div class="text-center py-10 bg-gray-50 rounded-lg border border-gray-200">
                <i class="fas fa-box-open text-5xl text-gray-400 mb-4"></i>
                <p class="text-lg text-gray-600">ยังไม่มีประวัติการดำเนินการแก้ไขใดๆ ในระบบ</p>
            </div>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($history_records as $record): ?>
                    <div class="history-card p-4 border rounded-xl shadow-md transition-all duration-300 bg-white hover:bg-gray-50">
                        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center">
                            
                            <!-- วันที่และเวลา -->
                            <div class="text-sm font-medium text-gray-500 mb-2 sm:mb-0">
                                <i class="far fa-clock mr-2 text-blue-500"></i>
                                <?= formatThaiDate($record['timestamp']); ?>
                            </div>

                            <!-- ประเภทการดำเนินการ (Badge) -->
                            <span class="px-3 py-1 text-xs font-semibold rounded-full border <?= getActionBadge($record['action_type']); ?>">
                                <i class="fas fa-tag mr-1"></i> <?= htmlspecialchars($record['action_type']); ?>
                            </span>
                        </div>

                        <!-- ชื่อผลงาน -->
                        <h3 class="text-lg font-bold text-gray-800 mt-2 mb-1">
                           <i class="fas fa-file-alt text-lg text-gray-600 mr-2"></i> <?= htmlspecialchars($record['title']); ?>
                        </h3>

                        <!-- รายละเอียดการดำเนินการ -->
                        <p class="text-gray-600 text-sm pl-7">
                            **รายละเอียด:** <?= $record['description']; ?>
                        </p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</main>
</div>

<!-- Modal for Notifications -->
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
            <!-- เนื่องจากนี่คือหน้า history จึงไม่ได้ใส่ logic สำหรับ Mark All as Read ไว้ -->
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
$conn->close();
?>
