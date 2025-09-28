<?php
// เริ่ม session
session_start();

// ตรวจสอบการเข้าสู่ระบบ: หากไม่มี session หรือ role ไม่ใช่ admin ให้ redirect ไปหน้า login
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login-v1.php");
    exit();
}

// --- เชื่อมต่อฐานข้อมูล ---
$servername = "localhost";
$db_user = "root";
$db_pass = "";
$dbname = "group10";

$conn = new mysqli($servername, $db_user, $db_pass, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ------------------------------------
// 1. การจัดการการค้นหา
// ------------------------------------
$search_query = $_GET['search'] ?? '';
$search_condition = "";

if (!empty($search_query)) {
    // สร้างเงื่อนไข WHERE สำหรับค้นหาชื่อและนามสกุล
    $search_term = "%" . $search_query . "%";
    
    // เตรียมคำสั่ง SQL สำหรับการค้นหา
    $stmt_search = $conn->prepare("
        SELECT User_id, first_name, last_name, Department, role, avatar 
        FROM User 
        WHERE role IN ('normal', 'staff') AND (
            first_name LIKE ? OR 
            last_name LIKE ? OR 
            Department LIKE ? OR
            role LIKE ?
        )
    ");
    $stmt_search->bind_param("ssss", $search_term, $search_term, $search_term, $search_term);
    $stmt_search->execute();
    $search_result = $stmt_search->get_result();
    $result = $search_result;
} else {
    // --- ดึงข้อมูลอาจารย์และเจ้าหน้าที่ (ยกเว้น admin) ---
    // ดึงข้อมูลทั้งหมดของ role 'normal' และ 'staff'
    $sql = "SELECT User_id, first_name, last_name, Department, role, avatar FROM User WHERE role IN ('normal', 'staff')";
    $result = $conn->query($sql);
}

// ------------------------------------
// 2. การจัดการสถานะการลบ (รับค่า delete_status จาก Admin_manage_delete.php)
// ------------------------------------
$delete_status = $_GET['delete_status'] ?? '';
$status_message = null;

if ($delete_status === 'success') {
    $status_message = "<div class=\"mb-4 p-3 bg-green-100 text-green-700 border border-green-300 rounded-lg shadow font-semibold\">✅ ลบผู้ใช้งานเรียบร้อยแล้ว</div>";
} elseif (strpos($delete_status, 'fail') !== false) {
    // กำหนดข้อความแจ้งเตือนตามรหัสสถานะที่มาจาก Admin_manage_delete.php
    $error_detail = match ($delete_status) {
        'fail_no_id' => 'ไม่พบ User ID ที่ต้องการลบในคำขอ',
        'fail_not_found' => 'ไม่พบผู้ใช้งาน ID นั้นในระบบ',
        'fail_db' => 'เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล',
        default => 'เกิดข้อผิดพลาดบางอย่างในการลบ',
    };
    $status_message = "<div class=\"mb-4 p-3 bg-red-100 text-red-700 border border-red-300 rounded-lg shadow font-semibold\">❌ ลบผู้ใช้งานไม่สำเร็จ: " . $error_detail . "</div>";
}

$current_user_name = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];

// ------------------------------------
// 3. ดึงจำนวนรายการรออนุมัติจริงจากฐานข้อมูล
// ------------------------------------
$pending_approvals = 0;
// สมมติ: ตาราง Publication มีคอลัมน์ 'status' และค่า 'Pending' หมายถึงรายการที่รออนุมัติ
$sql_count = "SELECT COUNT(*) AS pending_count FROM Publication WHERE status = 'Pending'";
$result_count = $conn->query($sql_count);

if ($result_count && $result_count->num_rows > 0) {
    $row_count = $result_count->fetch_assoc();
    $pending_approvals = (int)$row_count['pending_count'];
}

// ------------------------------------
?>

<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>จัดการข้อมูลอาจารย์และเจ้าหน้าที่</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

  <style>
    /* สไตล์ Header คล้าย Home-PR/StaffPage */
    .top-header {
        background-color: #cce4f9; /* สีฟ้าอ่อน */
        padding: 1rem 2rem;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }
  </style>
</head>
<body class="bg-gray-50 font-sans flex min-h-screen">

<!-- Sidebar (ปรับปรุง UI) -->
<aside class="w-64 bg-white shadow-xl p-6 flex flex-col sticky top-0 h-screen z-10">
    <!-- สีน้ำเงิน -->
    <h2 class="text-2xl font-extrabold text-blue-700 mb-6 border-b pb-4">Admin Menu</h2>
    <nav class="w-full flex-grow">
        <!-- เมนูที่กำลังใช้งาน: สีน้ำเงิน -->
        <a href="Admin-manage.php" class="flex items-center p-3 rounded-xl mb-3 text-white bg-blue-600 shadow-md hover:bg-blue-700 font-semibold transition-colors duration-150">
            <i class="fas fa-users-cog w-5 h-5 mr-3"></i> จัดการข้อมูลอาจารย์
        </a>
        
        <!-- เมนูอื่นๆ -->
        <a href="usermannual.php" class="flex items-center p-3 rounded-xl text-gray-700 hover:bg-blue-50 hover:text-blue-700 transition-colors duration-150">
            <i class="fas fa-book w-5 h-5 mr-3"></i> คู่มือการใช้งานระบบ
        </a>
        <a href="user_his.php" class="flex items-center p-3 rounded-xl text-gray-700 hover:bg-blue-50 hover:text-blue-700 transition-colors duration-150">
            <i class="fas fa-history w-5 h-5 mr-3"></i> ประวัติการเข้าใช้งาน
        </a>
        <a href="check_credentials.php" class="flex items-center p-3 rounded-xl text-gray-700 hover:bg-blue-50 hover:text-blue-700 transition-colors duration-150">
            <i class="fas fa-key w-5 h-5 mr-3"></i> ตรวจสอบชื่อ/รหัสผ่าน
        </a>
        
        <hr class="my-6 border-gray-200">
        
        <!-- ปุ่มออกจากระบบ: สีแดง -->
      <a href="logout.php" class="flex items-center p-3 rounded-lg text-red-500 hover:bg-red-50 transition-colors duration-150">
        <i class="fas fa-sign-out-alt w-5 h-5 mr-3"></i> ออกจากระบบ
      </a>

        
    </nav>
</aside>

<!-- ✅ DIV นี้คือ Main Content Wrapper ที่ใช้ flex-col -->
<div class="flex-1 flex flex-col">
    <!-- Header (ปรับปรุง UI) -->
    <header class="top-header flex items-center justify-between sticky top-0 z-10">
        <!-- สีน้ำเงิน -->
        <h1 class="text-xl md:text-2xl font-bold text-gray-700 tracking-wide">
            <i class="fas fa-tools mr-2 text-blue-800"></i> ระบบจัดการ (Admin)
        </h1>
        <div class="flex items-center space-x-4">
            
            <!-- ไอคอนกระดิ่งแจ้งเตือน (รายการรออนุมัติ) -->
            
            <span class="text-gray-700 font-medium hidden sm:block text-sm">
                ผู้ดูแลระบบ: <?= htmlspecialchars($current_user_name); ?>
            </span>
            <!-- สีน้ำเงิน -->
            <div class="w-8 h-8 rounded-full bg-blue-700 text-white flex items-center justify-center font-bold">A</div>
        </div>
    </header>

    <!-- Main Content (ใช้ flex-1 เพื่อผลัก Footer ลงไป) -->
    <main class="flex-1 p-8">
        <h2 class="text-3xl font-bold text-gray-800 mb-6 border-b pb-3">จัดการข้อมูลอาจารย์และเจ้าหน้าที่</h2>
        
        <!-- แสดงข้อความสถานะการลบ (แทนที่ delete_msg) -->
        <?php if ($status_message): ?>
             <?= $status_message; ?>
        <?php endif; ?>

        <!-- Search Form and Add Button -->
        <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
            <form method="GET" action="Admin-manage.php" class="flex w-full md:w-2/3">
                <input type="text" name="search" placeholder="ค้นหาชื่อ, แผนก, หรือบทบาท (Staff/Normal)..." 
                       value="<?= htmlspecialchars($search_query); ?>"
                       class="flex-grow px-5 py-3 border border-gray-300 rounded-l-full text-base focus:ring-4 focus:ring-blue-300 focus:border-blue-500 transition-all shadow-inner">
                <button type="submit" class="bg-blue-600 text-white px-6 py-3 rounded-r-full font-bold hover:bg-blue-700 transition-colors duration-200 shadow-lg">
                    <i class="fas fa-search"></i>
                </button>
            </form>
            
            <!-- ปุ่มเพิ่ม: สีน้ำเงิน -->
            <a href="add-names-teacher-and-admin.php" 
               class="flex items-center space-x-2 px-6 py-3 bg-blue-600 text-white rounded-full shadow-lg hover:bg-blue-700 transition-colors font-semibold w-full md:w-auto justify-center">
                <i class="fas fa-user-plus"></i>
                <span>เพิ่มอาจารย์/เจ้าหน้าที่</span>
            </a>
        </div>


        <!-- Table/Card Data Section -->
        <div class="bg-white p-6 rounded-2xl shadow-2xl">
            <?php if (!empty($search_query)): ?>
                <p class="text-lg font-semibold text-gray-700 mb-4">
                    ผลการค้นหา: **<?= $result->num_rows; ?>** รายการ 
                </p>
            <?php endif; ?>
            
            <div class="space-y-4">
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while($row = $result->fetch_assoc()): ?>
                        <div class="flex justify-between items-center bg-blue-50 p-4 rounded-xl shadow border border-blue-200 hover:shadow-lg transition-shadow duration-200">
                            <div class="flex items-center space-x-4">
                                <?php 
                                // ตั้งค่าสถานะและสีตามบทบาท: อาจารย์=สีน้ำเงิน, เจ้าหน้าที่=สีเขียว
                                $role_label = ($row['role'] === 'staff') ? 'เจ้าหน้าที่' : 'อาจารย์';
                                // เจ้าหน้าที่=สีเขียว
                                $role_color = ($row['role'] === 'staff') ? 'bg-green-500' : 'bg-blue-500';
                                
                                // ตั้งค่ารูป Avatar หรือใช้อักษรย่อถ้าไม่มีรูป
                                $avatar_content = '';
                                if (!empty($row['avatar'])) {
                                    // หากมีรูปภาพ ให้นำมาแสดงผล
                                    $avatar_content = '<img src="img/' . htmlspecialchars($row['avatar']) . '" alt="Avatar" class="w-12 h-12 rounded-full object-cover">';
                                } else {
                                    // หากไม่มีรูปภาพ ให้แสดงอักษรย่อ
                                    $avatar_content = '<div class="w-12 h-12 rounded-full ' . $role_color . ' text-white flex items-center justify-center font-bold text-xl">' . strtoupper(substr($row['first_name'], 0, 1)) . '</div>';
                                }
                                ?>
                                
                                <!-- แสดง Avatar หรืออักษรย่อ -->
                                <?= $avatar_content; ?>
                                
                                <div>
                                    <p class="font-bold text-gray-800 text-lg flex items-center">
                                        <!-- ไฮไลท์บทบาทด้วยสีใหม่ -->
                                        <span class="<?= $role_color; ?> text-white text-xs px-2 py-0.5 rounded-full mr-2 shadow-sm"><?= $role_label; ?></span>
                                        <?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?>
                                    </p>
                                    <p class="text-gray-600 text-sm mt-1">
                                        <i class="fas fa-building mr-1 text-blue-500"></i>
                                        <?= htmlspecialchars($row['Department'] ?? 'N/A'); ?>
                                    </p>
                                </div>
                            </div>
                            <div class="flex space-x-3">
                                <!-- ปุ่มแก้ไข -->
                                <a href="edit-user.php?id=<?= $row['User_id']; ?>" 
                                   class="text-blue-600 p-2 rounded-full hover:bg-blue-200 transition-colors" title="แก้ไขข้อมูล">
                                    <i class="fas fa-edit text-xl"></i>
                                </a>
                                <!-- ปุ่มลบ: เปลี่ยนไปเรียก Admin_manage_delete.php -->
                                <a href="Admin_manage_delete.php?id=<?= $row['User_id']; ?>" 
                                   onclick="return confirm('คุณแน่ใจหรือไม่ว่าต้องการลบผู้ใช้: <?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?>?')"
                                   class="text-red-600 p-2 rounded-full hover:bg-red-200 transition-colors" title="ลบผู้ใช้">
                                    <i class="fas fa-trash-alt text-xl"></i>
                                </a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p class="text-center py-8 text-gray-500">
                        <i class="fas fa-exclamation-circle mr-2"></i> 
                        ไม่พบข้อมูลอาจารย์หรือเจ้าหน้าที่ตามการค้นหา
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="p-4 bg-gray-200 text-center text-gray-600 text-sm">
        &copy; <?php echo date("Y"); ?> ระบบจัดการผลงาน | มหาวิทยาลัยสงขลานครินทร์ (PSU)
    </footer>

</div> <!-- End of flex-1 flex flex-col -->

<script>
    /**
     * ฟังก์ชันนำทางไปยังหน้าจัดการอนุมัติ (approve.php)
     */
    function redirectToApprovals() {
        const url = "approved.php";
        // เปลี่ยน location เพื่อนำทางไปยังหน้า approved.php ทันที
        window.location.href = url; 
    }
</script>

</body>
</html>

<?php 
// ปิดการเชื่อมต่อฐานข้อมูล
if (isset($stmt_search) && $stmt_search instanceof mysqli_stmt) {
    $stmt_search->close();
}
// ปิดการเชื่อมต่อสำหรับ Query นับรายการรออนุมัติ
if (isset($result_count) && $result_count instanceof mysqli_result) {
    // ไม่มี stmt_count เพราะใช้ $conn->query() ตรงๆ
    $result_count->free();
}
$conn->close(); 
?>
