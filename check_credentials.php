<?php
// เริ่ม session
session_start();

// ตรวจสอบการเข้าสู่ระบบ: หากไม่มี session หรือ role ไม่ใช่ admin ให้ redirect ไปหน้า login
// *สมมติว่าเฉพาะ admin เท่านั้นที่สามารถดูข้อมูลนี้ได้
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

// ดึงข้อมูลผู้ใช้งานทั้งหมด (รวมถึง Username และ Password)
// เลือกเฉพาะ 'normal' (อาจารย์) และ 'staff' (เจ้าหน้าที่) เพื่อแสดงผล
$sql = "SELECT first_name, last_name, Username, Password, email, role 
        FROM User 
        WHERE role IN ('normal', 'staff')
        ORDER BY role DESC, last_name"; // จัดเรียงตามบทบาทและนามสกุล

$result = $conn->query($sql);

// ดึงชื่อผู้ใช้ปัจจุบันสำหรับแสดงใน Header
$current_user_name = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];

// ดึงจำนวนรายการรออนุมัติจริงจากฐานข้อมูล
$pending_approvals = 0;
$sql_count = "SELECT COUNT(*) AS pending_count FROM Publication WHERE status = 'Waiting'"; // ใช้ 'Waiting' ตามข้อมูล SQL Dump
$result_count = $conn->query($sql_count);

if ($result_count && $result_count->num_rows > 0) {
    $row_count = $result_count->fetch_assoc();
    $pending_approvals = (int)$row_count['pending_count'];
    $result_count->free();
}

?>

<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ตรวจสอบชื่อผู้ใช้และรหัสผ่าน</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

  <style>
    .top-header {
        background-color: #cce4f9; /* สีฟ้าอ่อน */
        padding: 1rem 2rem;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }
  </style>
</head>
<body class="bg-gray-50 font-sans flex min-h-screen">

<!-- Sidebar -->
<aside class="w-64 bg-white shadow-xl p-6 flex flex-col sticky top-0 h-screen z-10">
    <h2 class="text-2xl font-extrabold text-blue-700 mb-6 border-b pb-4">Admin Menu</h2>
    <nav class="w-full flex-grow">
        
        <a href="Admin-manage.php" class="flex items-center p-3 rounded-xl mb-3 text-gray-700 hover:bg-blue-50 hover:text-blue-700 transition-colors duration-150">
            <i class="fas fa-users-cog w-5 h-5 mr-3"></i> จัดการข้อมูลอาจารย์
        </a>
        <a href="usermannual.php" class="flex items-center p-3 rounded-xl text-gray-700 hover:bg-blue-50 hover:text-blue-700 transition-colors duration-150">
            <i class="fas fa-book w-5 h-5 mr-3"></i> คู่มือการใช้งานระบบ
        </a>
        <a href="user_his.php" class="flex items-center p-3 rounded-xl text-gray-700 hover:bg-blue-50 hover:text-blue-700 transition-colors duration-150">
            <i class="fas fa-history w-5 h-5 mr-3"></i> ประวัติการเข้าใช้งาน
        </a>
        <!-- เมนูที่กำลังใช้งาน: เน้นสีน้ำเงิน -->
        <a href="check_credentials.php" class="flex items-center p-3 rounded-xl text-white bg-blue-600 shadow-md hover:bg-blue-700 font-semibold transition-colors duration-150">
            <i class="fas fa-key w-5 h-5 mr-3"></i> ตรวจสอบชื่อ/รหัสผ่าน
        </a>
        
        <hr class="my-6 border-gray-200">
        
        <!-- ปุ่มออกจากระบบ -->
        <a href="logout.php" class="flex items-center p-3 rounded-lg text-red-500 hover:bg-red-50 transition-colors duration-150">
            <i class="fas fa-sign-out-alt w-5 h-5 mr-3"></i> ออกจากระบบ
        </a>
    </nav>
</aside>

<!-- Main Content Wrapper: แก้ไขโดยการเพิ่ม min-w-0 ที่นี่ -->
<div class="flex-1 flex flex-col min-w-0">
    <!-- Header -->
    <header class="top-header flex items-center justify-between sticky top-0 z-10">
        <h1 class="text-xl md:text-2xl font-bold text-gray-700 tracking-wide">
            <i class="fas fa-tools mr-2 text-blue-800"></i> ระบบจัดการ (Admin)
        </h1>
        <div class="flex items-center space-x-4">
            
            
            
            <span class="text-gray-700 font-medium hidden sm:block text-sm">
                ผู้ดูแลระบบ: <?= htmlspecialchars($current_user_name); ?>
            </span>
            <div class="w-8 h-8 rounded-full bg-blue-700 text-white flex items-center justify-center font-bold">A</div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-1 p-8">
        <h2 class="text-3xl font-bold text-gray-800 mb-6 border-b pb-3">ตรวจสอบชื่อผู้ใช้และรหัสผ่าน</h2>
        <p class="mb-6 text-gray-600">ข้อมูลนี้แสดง Username และ Password ของอาจารย์และเจ้าหน้าที่เพื่อวัตถุประสงค์ในการดูแลระบบเท่านั้น</p>
        
        <!-- Data Table -->
        <div class="bg-white shadow-2xl rounded-2xl overflow-hidden">
            <!-- overflow-x-auto ช่วยให้ตารางมี scrollbar เมื่อกว้างเกินไป -->
            <div class="overflow-x-auto"> 
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-blue-100">
                        <tr>
                            <th class="px-6 py-3 text-left text-sm font-bold text-blue-800 uppercase tracking-wider">ตำแหน่ง</th>
                            <th class="px-6 py-3 text-left text-sm font-bold text-blue-800 uppercase tracking-wider">ชื่อ-นามสกุล</th>
                            <th class="px-6 py-3 text-left text-sm font-bold text-blue-800 uppercase tracking-wider">ชื่อผู้ใช้ (Username)</th>
                            <th class="px-6 py-3 text-left text-sm font-bold text-blue-800 uppercase tracking-wider">รหัสผ่าน (Password)</th>
                            <th class="px-6 py-3 text-left text-sm font-bold text-blue-800 uppercase tracking-wider">อีเมล</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-100">
                        <?php if ($result->num_rows > 0): ?>
                            <?php while($row = $result->fetch_assoc()): ?>
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php 
                                            // กำหนดสีและข้อความตามบทบาท
                                            $role_display = ($row['role'] === 'staff') ? 
                                                '<span class="px-3 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">เจ้าหน้าที่</span>' : 
                                                '<span class="px-3 py-1 text-xs font-semibold rounded-full bg-indigo-100 text-indigo-800">อาจารย์</span>';
                                            echo $role_display;
                                        ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-gray-900 font-medium">
                                        <?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-gray-700">
                                        <?= htmlspecialchars($row['Username']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap font-mono text-sm text-red-600">
                                        <!-- เน้นรหัสผ่านด้วยสีแดงและฟอนต์ mono -->
                                        <?= htmlspecialchars($row['Password']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-blue-500">
                                        <?= htmlspecialchars($row['email']); ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="px-6 py-10 text-center text-gray-500 text-lg">
                                    <i class="fas fa-info-circle mr-2"></i> ไม่พบข้อมูลผู้ใช้งานที่ต้องตรวจสอบ
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="p-4 bg-gray-200 text-center text-gray-600 text-sm mt-auto">
        &copy; <?php echo date("Y"); ?> ระบบจัดการผลงาน | มหาวิทยาลัยสงขลานครินทร์ (PSU)
    </footer>

</div> <!-- End of flex-1 flex flex-col min-w-0 -->

<script>
    /**
     * ฟังก์ชันนำทางไปยังหน้าจัดการอนุมัติ (approve.php)
     */
    function redirectToApprovals() {
        const url = "approved.php"; // สมมติว่าหน้าจัดการอนุมัติคือ approved.php
        window.location.href = url; 
    }
</script>

</body>
</html>

<?php 
// ปิดการเชื่อมต่อฐานข้อมูล
$conn->close(); 
?>
