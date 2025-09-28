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

// 1. เชื่อมต่อฐานข้อมูล
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "group10";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ดึงข้อมูลผู้ใช้ปัจจุบันและจำนวนงานที่รออนุมัติสำหรับ Header
$current_user_name = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];

$pending_count = 0;
$sql_count = "SELECT COUNT(*) AS count FROM Publication WHERE status = 'waiting'";
$result_count = $conn->query($sql_count);
if ($result_count && $row = $result_count->fetch_assoc()) {
    $pending_count = (int)$row['count'];
}

$status_message = null;
$status_type = 'info'; // 'success', 'error', 'warning'


// ------------------------------------
// 2. ตรวจสอบว่ามีการ submit form (POST Logic)
// ------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $first_name = trim($_POST['first_name'] ?? '');
    $last_name  = trim($_POST['last_name'] ?? '');
    $tel        = trim($_POST['tel'] ?? '');
    // เดิมใช้ 'address' แต่ใน DB คือ 'Department'
    $department = trim($_POST['address'] ?? ''); 
    $email      = trim($_POST['email'] ?? '');
    $role       = $_POST['role'] ?? 'normal';
    $input_username = trim($_POST['username'] ?? ''); // ใช้ชื่อตัวแปรต่างจาก $username ใน DB connection
    $input_password = trim($_POST['password'] ?? ''); 
    $confirm    = trim($_POST['confirm_password'] ?? '');

    // ตรวจสอบรหัสผ่านตรงกัน
    if ($input_password !== $confirm) {
        $status_message = "❌ รหัสผ่านที่กรอกไม่ตรงกัน กรุณาลองใหม่อีกครั้ง";
        $status_type = 'error';
    } else {
        // อัปโหลดรูป (ถ้ามี)
        $avatar_name = null;
        if (!empty($_FILES['avatar']['name'])) {
            $target_dir = "img/";
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            $ext = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
            $avatar_name = uniqid('avatar_') . '.' . $ext;
            $target_file = $target_dir . $avatar_name;

            if (!move_uploaded_file($_FILES['avatar']['tmp_name'], $target_file)) {
                // หากอัปโหลดไม่สำเร็จ
                $status_message = "❌ อัปโหลดรูปโปรไฟล์ไม่สำเร็จ กรุณาลองใหม่อีกครั้ง";
                $status_type = 'error';
                goto end_of_post; // ข้ามการบันทึกฐานข้อมูล
            }
        }

        // บันทึกข้อมูลลงฐานข้อมูล
        // NOTE: ใช้ $department แทน $address
        $stmt = $conn->prepare("INSERT INTO User (first_name, last_name, tel, Department, email, role, Username, Password, avatar)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssssss", $first_name, $last_name, $tel, $department, $email, $role, $input_username, $input_password, $avatar_name);

        if ($stmt->execute()) {
            // Success: ใช้ Redirect เพื่อป้องกันการ Submit ซ้ำ
            header("Location: staff_addTeacher.php?status=success_add");
            exit();
        } else {
            // Error: แสดงข้อผิดพลาดจากฐานข้อมูล
            $status_message = "❌ Error: บันทึกข้อมูลไม่สำเร็จ (" . $stmt->error . ")";
            $status_type = 'error';
        }

        $stmt->close();
    }
}
// Label สำหรับ goto หากอัปโหลดไฟล์ล้มเหลว
end_of_post:

// ตรวจสอบสถานะหลังการ Redirect
if (isset($_GET['status']) && $_GET['status'] === 'success_add') {
     $status_message = "✅ เพิ่มข้อมูลอาจารย์/เจ้าหน้าที่เรียบร้อยแล้ว";
     $status_type = 'success';
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เพิ่มรายชื่ออาจารย์/เจ้าหน้าที่</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* สไตล์สีหลักของธีม Staff */
        .text-theme { color: #1d4ed8; } 
        .bg-theme-light { background-color: #eff6ff; } 
        .border-theme-light { border-color: #bfdbfe; } 
        
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
        /* สไตล์สำหรับกล่องข้อความสถานะ */
        .status-success { background-color: #d1fae5; color: #065f46; border-color: #34d399; }
        .status-error { background-color: #fee2e2; color: #991b1b; border-color: #f87171; }
    </style>
</head>
<body class="flex min-h-screen font-sans bg-gray-50">

<!-- Sidebar (เมนูย่อเหลือปุ่มกลับและออกจากระบบเท่านั้น) -->
<aside class="w-64 bg-white shadow-xl p-6 flex flex-col sticky top-0 h-screen z-20">
    <h2 class="text-2xl font-extrabold text-blue-800 mb-6 border-b pb-4">Staff Menu</h2>
    <nav class="w-full flex-grow">
        <!-- ปุ่มกลับสู่หน้าหลัก (staffPage.php) -->
        <a href="staffPage.php" class="flex items-center p-3 rounded-xl mb-3 text-white bg-blue-600 shadow-md font-semibold hover:bg-blue-700 transition-colors duration-150">
            <i class="fas fa-arrow-left w-5 h-5 mr-3"></i> กลับสู่หน้าหลัก
        </a>
        
        <div class="px-0 pt-4 border-t border-gray-200 mt-auto">
          <a href="logout.php" class="flex items-center p-3 rounded-lg text-red-500 hover:bg-red-50 transition-colors duration-150">
            <i class="fas fa-sign-out-alt w-5 h-5 mr-3"></i> ออกจากระบบ
          </a>
        </div>
        
    </nav>
</aside>

<div class="flex-1 flex flex-col">
    <!-- Header สไตล์ Home-PR -->
    <header class="top-header flex items-center justify-between sticky top-0 z-10">
        <h1 class="text-xl md:text-2xl font-bold text-gray-700 tracking-wide">
            <i class="fas fa-tools mr-2 text-blue-800"></i> ระบบจัดการผลงาน (Staff)
        </h1>
        <!-- Notification Bell -->
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
        <h1 class="text-4xl font-extrabold text-gray-800 mb-8">
            <i class="fas fa-user-plus text-blue-600 mr-2"></i> เพิ่มข้อมูลอาจารย์ / เจ้าหน้าที่
        </h1>
        
        <!-- กล่องข้อความสถานะ (Status Message Box) -->
        <?php if ($status_message): ?>
            <div class="mb-6 p-4 rounded-lg shadow-md font-medium border-l-4 
                <?= $status_type === 'success' ? 'status-success border-green-500' : 'status-error border-red-500' ?>">
                <?= $status_message; ?>
            </div>
        <?php endif; ?>

        <!-- Form Container -->
        <div class="bg-white rounded-2xl shadow-2xl p-8 max-w-4xl mx-auto border border-blue-100">
            <form class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-6" method="POST" enctype="multipart/form-data">
                
                <!-- ชื่อจริง -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">ชื่อจริง <span class="text-red-500">*</span></label>
                    <input type="text" name="first_name" class="w-full border border-gray-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" placeholder="กรอกชื่อจริง" required>
                </div>
                <!-- นามสกุล -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">นามสกุล <span class="text-red-500">*</span></label>
                    <input type="text" name="last_name" class="w-full border border-gray-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" placeholder="กรอกนามสกุล" required>
                </div>

                <!-- เบอร์โทรศัพท์ -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">เบอร์โทรศัพท์ <span class="text-red-500">*</span></label>
                    <input type="tel" name="tel" class="w-full border border-gray-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" placeholder="08XXXXXXXX" required>
                </div>
                <!-- ภาควิชา / คณะ (Department) -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">ภาควิชา / คณะ <span class="text-red-500">*</span></label>
                    <input type="text" name="address" class="w-full border border-gray-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" placeholder="เช่น วิทยาการคอมพิวเตอร์" required>
                </div>

                <!-- อีเมล -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">อีเมล <span class="text-red-500">*</span></label>
                    <input type="email" name="email" class="w-full border border-gray-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" placeholder="example@psu.ac.th" required>
                </div>
                <!-- ตำแหน่ง (Role) -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">ตำแหน่ง <span class="text-red-500">*</span></label>
                    <select name="role" class="w-full border border-gray-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                        <option value="normal">อาจารย์ (Normal User)</option>
                        <option value="staff">เจ้าหน้าที่ (Staff)</option>
                    </select>
                </div>

                <!-- บัญชีผู้ใช้ -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">ชื่อบัญชีผู้ใช้ <span class="text-red-500">*</span></label>
                    <input type="text" name="username" class="w-full border border-gray-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" placeholder="ชื่อสำหรับเข้าสู่ระบบ" required>
                </div>
                <!-- รูปโปรไฟล์ -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">รูปโปรไฟล์</label>
                    <input type="file" name="avatar" accept="image/*" class="w-full mt-1 file:bg-blue-500 file:text-white file:border-none file:py-2 file:px-4 file:rounded-xl file:mr-4 file:hover:bg-blue-600 file:cursor-pointer border border-gray-300 rounded-xl p-2 transition-colors">
                </div>

                <!-- รหัสผ่าน -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">รหัสผ่าน <span class="text-red-500">*</span></label>
                    <input type="password" name="password" class="w-full border border-gray-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" placeholder="กรอกรหัสผ่าน" required>
                </div>
                <!-- ยืนยันรหัสผ่าน -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">ยืนยันรหัสผ่าน <span class="text-red-500">*</span></label>
                    <input type="password" name="confirm_password" class="w-full border border-gray-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" placeholder="ยืนยันรหัสผ่านอีกครั้ง" required>
                </div>

                <!-- Submit/Cancel Button -->
                <div class="col-span-1 md:col-span-2 flex justify-center gap-6 pt-6">
                    <a href="staffPage.php" class="bg-gray-300 text-gray-800 px-8 py-3 rounded-full font-bold hover:bg-gray-400 transition-colors shadow-lg">
                        <i class="fas fa-times mr-2"></i> ยกเลิก
                    </a>
                    <button type="submit" class="bg-blue-600 text-white px-8 py-3 rounded-full font-bold hover:bg-blue-700 transition-colors shadow-lg">
                        <i class="fas fa-save mr-2"></i> บันทึกข้อมูล
                    </button>
                </div>
            </form>
        </div>
    </main>

    <footer class="p-4 bg-gray-200 text-center text-gray-600 text-sm mt-auto">
        &copy; <?php echo date("Y"); ?> ระบบจัดการการตีพิมพ์ผลงาน | มหาวิทยาลัยสงขลานครินทร์ (PSU)
    </footer>
</div>

</body>
</html>
