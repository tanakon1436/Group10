<?php
// === START: DEBUGGING AND ERROR REPORTING ===
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// === END: DEBUGGING AND ERROR REPORTING ===

// เริ่ม session
session_start();

// ตรวจสอบการเข้าสู่ระบบ: หากไม่มี session หรือ role ไม่ใช่ staff ให้ redirect ไปหน้า login
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header("Location: login-v1.php");
    exit();
}

// 1. เชื่อมฐานข้อมูล
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "group10";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    $db_error = "Connection failed: " . $conn->connect_error;
} else {
    $db_error = null;
}

// 2. ดึงข้อมูลอาจารย์เท่านั้น
$teachers = [];
if (!$db_error) {
    // ดึงเฉพาะข้อมูลที่จำเป็น
    $sql = "SELECT User_id, first_name, last_name, Department, avatar FROM User WHERE role='normal' ORDER BY first_name";
    $result = $conn->query($sql);
    if ($result) {
        while($row = $result->fetch_assoc()) {
            $teachers[] = $row;
        }
    }
}

// 3. ดึงข้อมูลผู้ใช้ปัจจุบันและจำนวนผลงานที่รออนุมัติสำหรับ Header
$current_user_name = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];
$pending_count = 0;
if (!$db_error) {
    $sql_count = "SELECT COUNT(*) AS count FROM Publication WHERE status = 'waiting'";
    $result_count = $conn->query($sql_count);
    if ($result_count && $row = $result_count->fetch_assoc()) {
        $pending_count = (int)$row['count'];
    }
}

?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการข้อมูลอาจารย์ - Staff</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

    <style>
        .text-theme { color: #1d4ed8; } 
        .bg-theme-light { background-color: #eff6ff; } 
        .top-header {
            background-color: #cce4f9; 
            padding: 1rem 2rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);
        }
        .right-icons > a {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 9999px; 
            color: #1d4ed8; 
        }
        .right-icons > a:hover {
            background-color: #dbeafe; 
        }
    </style>
</head>
<body class="flex min-h-screen font-sans bg-gray-50">

<aside class="w-64 bg-white shadow-xl p-6 flex flex-col sticky top-0 h-screen z-10">
    <h2 class="text-2xl font-extrabold text-blue-800 mb-6 border-b pb-4">Staff Menu</h2>
    <nav class="w-full flex-grow">
        <a href="staffPage.php" class="flex items-center p-3 rounded-xl mb-3 text-gray-700 hover:bg-blue-50 hover:text-blue-700 transition-colors duration-150">
            <i class="fas fa-arrow-left w-5 h-5 mr-3"></i> กลับสู่หน้าหลัก
        </a>
        <a href="Staff_manage.php" class="flex items-center p-3 rounded-xl mb-3 text-white bg-blue-600 shadow-md hover:bg-blue-700 font-semibold transition-colors duration-150">
            <i class="fas fa-edit w-5 h-5 mr-3"></i> จัดการข้อมูลอาจารย์
        </a>
        
        <div class="px-0 pt-4 border-t border-gray-200">
            <a href="logout.php" class="flex items-center p-3 rounded-lg text-red-500 hover:bg-red-50 transition-colors duration-150">
                <i class="fas fa-sign-out-alt w-5 h-5 mr-3"></i> ออกจากระบบ
            </a>
        </div>
        
    </nav>
</aside>

<div class="flex-1 flex flex-col">
    <header class="top-header flex items-center justify-between sticky top-0 z-10">
        <h1 class="text-xl md:text-2xl font-bold text-gray-700 tracking-wide">
            <i class="fas fa-users-cog mr-2 text-blue-800"></i> จัดการข้อมูลอาจารย์ (Staff)
        </h1>
        <div class="flex items-center space-x-4 right-icons">
            <span class="text-gray-700 font-medium hidden sm:block text-sm">
            <?= htmlspecialchars($current_user_name); ?>
            </span>
            <a href="approve.php" title="คำขออนุมัติผลงาน" class="relative">
                <i class="fas fa-bell text-xl"></i>
                <?php if ($pending_count > 0): ?>
                    <span class="absolute -top-1 -right-1 bg-red-600 text-white text-xs font-bold rounded-full h-5 w-5 flex items-center justify-center ring-2 ring-white">
                        <?= $pending_count ?>
                    </span>
                <?php endif; ?>
            </a>
            <a href="#" title="โปรไฟล์ผู้ใช้งาน">
                <i class="fas fa-user-circle text-xl"></i>
            </a>
        </div>
    </header>

    <main class="flex-1 p-8">
        <section class="bg-white p-6 rounded-2xl shadow-2xl">
            <h2 class="text-2xl font-bold text-gray-800 mb-6 pb-2 border-b-2 border-blue-200">รายชื่ออาจารย์ในระบบ</h2>

            <!-- ช่องค้นหา (ค้นหาเฉพาะชื่อ-นามสกุล) -->
            <div class="mb-6 flex items-center space-x-4">
                <input type="text" id="searchInput" placeholder="ค้นหาชื่อ-นามสกุล..." 
                       class="flex-1 px-5 py-3 border border-gray-300 rounded-full focus:outline-none focus:ring-2 focus:ring-blue-400 shadow-inner transition-all">

                <!-- ปุ่มเพิ่มอาจารย์ -->
                <button onclick="location.href='Staff-add-teacher.php'" 
                    class="flex items-center space-x-2 px-6 py-3 bg-blue-600 text-white rounded-full font-semibold shadow-md hover:bg-blue-700 transition-colors duration-200">
                    <i class="fas fa-user-plus"></i>
                    <span>เพิ่มอาจารย์</span>
                </button>
            </div>

            <!-- การ์ดข้อมูล -->
            <div id="userList" class="space-y-4">
            <?php if ($db_error): ?>
                <div class="p-4 bg-red-100 text-red-700 border border-red-300 rounded-lg shadow-md">
                    <p>⚠️ ไม่สามารถเชื่อมต่อฐานข้อมูลได้: <?= htmlspecialchars($db_error); ?></p>
                </div>
            <?php elseif (empty($teachers)): ?>
                <div class="p-4 bg-yellow-100 text-yellow-700 border border-yellow-300 rounded-lg shadow-md">
                    <p>ไม่พบข้อมูลอาจารย์ที่มีบทบาท 'normal' ในระบบ</p>
                </div>
            <?php else: ?>
                <?php foreach ($teachers as $row): 
                    // *** สำคัญ: กำหนด path รูปภาพที่นี่ โดยถือว่ารูปอยู่ในโฟลเดอร์ img/ ***
                    $avatar_path = !empty($row['avatar']) ? 'img/' . $row['avatar'] : '';
                    $has_avatar = !empty($avatar_path) && file_exists($avatar_path);
                ?>
                <div class="flex justify-between items-center bg-theme-light p-4 rounded-xl shadow-lg border border-blue-200 hover:shadow-xl transition-shadow duration-300">
                    <div class="flex items-center space-x-4">
                        <?php if($has_avatar): ?>
                        <img src="<?= htmlspecialchars($avatar_path); ?>" alt="ผู้ใช้งาน" class="w-16 h-16 rounded-full object-cover border-2 border-blue-400">
                        <?php else: ?>
                        <div class="w-16 h-16 rounded-full bg-blue-300 flex items-center justify-center text-white text-2xl font-bold">
                            <i class="fas fa-user"></i>
                        </div>
                        <?php endif; ?>
                        <div>
                            <p class="text-lg font-bold text-blue-800"><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></p>
                            <p class="text-gray-600 text-sm"><i class="fas fa-graduation-cap mr-1"></i> อาจารย์</p>
                            <p class="text-gray-500 text-sm"><i class="fas fa-building mr-1"></i> <?php echo htmlspecialchars($row['Department']); ?></p>
                            <p class="text-xs text-gray-400 mt-1">ID: <?php echo htmlspecialchars($row['User_id']); ?></p>
                        </div>
                    </div>
                    <div class="flex space-x-2">
                        <!-- ปุ่มแก้ไข -->
                        <button onclick="window.location.href='Staff_manage_update.php?id=<?php echo $row['User_id']; ?>'" 
                                class="p-3 rounded-full bg-yellow-400 text-white shadow-md hover:bg-yellow-500 transition-colors duration-150"
                                title="แก้ไขข้อมูล">
                            <i class="fas fa-pencil-alt text-lg"></i>
                        </button>
                        <!-- ปุ่มลบ -->
                        <button onclick="confirmDelete(<?php echo $row['User_id']; ?>)" 
                                class="p-3 rounded-full bg-red-600 text-white shadow-md hover:bg-red-700 transition-colors duration-150"
                                title="ลบข้อมูล">
                            <i class="fas fa-trash-alt text-lg"></i>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
            </div>
        </section>
    </main>

    <footer class="p-4 bg-gray-200 text-center text-gray-600 text-sm mt-auto">
        &copy; <?php echo date("Y"); ?> ระบบจัดการการตีพิมพ์ผลงาน | มหาวิทยาลัยสงขลานครินทร์ (PSU)
    </footer>
</div>

<script>
// ฟังก์ชันลบ (ใช้ confirm ตามที่โค้ดเดิมระบุ)
function confirmDelete(id) {
    if (confirm("คุณแน่ใจหรือไม่ว่าต้องการลบข้อมูลนี้?")) {
        // *** หมายเหตุ: ไฟล์ Staff_manage_delete.php จะต้องถูกสร้างขึ้นเพื่อรองรับการลบนี้ ***
        window.location.href = "Staff_manage_delete.php?id=" + id;
    }
}

const searchInput = document.getElementById('searchInput');
const userList = document.getElementById('userList');

// AJAX Search logic
searchInput.addEventListener('keyup', function() {
    const query = this.value.trim();
    
    // ถ้าคำค้นหาน้อยกว่า 2 ตัวอักษรและไม่ว่าง ให้หยุดทำงาน 
    if (query.length < 2 && query.length > 0) return; 

    const xhr = new XMLHttpRequest();
    // ** การเรียก Staff_manage_search.php ต้องแน่ใจว่าไฟล์อยู่ในไดเรกทอรีเดียวกัน **
    xhr.open('GET', 'Staff_manage_search.php?q=' + encodeURIComponent(query), true);
    xhr.onload = function() {
        // ตรวจสอบ status เป็น 200 (OK) หรือไม่
        if (this.status === 200) {
            userList.innerHTML = this.responseText;
        } else {
             // แสดงข้อความผิดพลาดที่ชัดเจน รวมถึง 404 ด้วย
             userList.innerHTML = '<div class="p-4 bg-red-100 text-red-700 border border-red-300 rounded-lg shadow-md">❌ เกิดข้อผิดพลาดในการโหลดผลการค้นหา (HTTP Status: ' + this.status + ') โปรดตรวจสอบว่าไฟล์ Staff_manage_search.php อยู่ในโฟลเดอร์เดียวกันหรือไม่</div>';
        }
    };
    xhr.onerror = function() {
        // กรณีเครือข่ายล้มเหลว
        userList.innerHTML = '<div class="p-4 bg-red-100 text-red-700 border border-red-300 rounded-lg shadow-md">❌ ข้อผิดพลาดเครือข่าย: ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ค้นหาได้</div>';
    };
    xhr.send();
});
</script>

</body>
</html>

<?php 
if (!$db_error) {
    $conn->close(); 
}
?>
