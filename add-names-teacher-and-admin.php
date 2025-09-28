<?php
// เริ่ม session
session_start();

// ตรวจสอบการเข้าสู่ระบบ: หากไม่มี session หรือ role ไม่ใช่ admin ให้ redirect
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login-v1.php");
    exit();
}

// --- เชื่อมต่อฐานข้อมูล ---
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "group10";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ------------------------------------
// 1. การจัดการฟอร์ม POST
// ------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ใช้ mysqli_real_escape_string สำหรับการทำความสะอาดพื้นฐาน (แม้ว่าจะใช้ prepared statement)
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name  = trim($_POST['last_name'] ?? '');
    $tel        = trim($_POST['tel'] ?? '');
    // Department ถูกแมปมาจาก field 'address' ใน form
    $department = trim($_POST['address'] ?? ''); 
    $email      = trim($_POST['email'] ?? '');
    $role       = $_POST['role'] ?? 'normal';
    $username   = trim($_POST['username'] ?? '');
    $password   = trim($_POST['password'] ?? '');
    $confirm    = trim($_POST['confirm_password'] ?? '');

    // ตรวจสอบรหัสผ่านตรงกัน
    if ($password !== $confirm) {
        // ใช้ JavaScript alert (ตามโค้ดเดิม)
        echo "<script>alert('รหัสผ่านไม่ตรงกัน');</script>";
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
                die("อัปโหลดรูปไม่สำเร็จ");
            }
        }

        // บันทึกข้อมูลลงฐานข้อมูลด้วย Prepared Statement
        $stmt = $conn->prepare("INSERT INTO User (first_name, last_name, tel, Department, email, role, Username, Password, avatar)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssssss", $first_name, $last_name, $tel, $department, $email, $role, $username, $password, $avatar_name);

        if ($stmt->execute()) {
            echo "<script>alert('เพิ่มข้อมูลเรียบร้อย'); window.location.href='Admin-manage.php';</script>";
            $stmt->close();
            $conn->close();
            exit();
        } else {
            // Error handling
            echo "<script>alert('Error: " . $stmt->error . "');</script>";
        }

        $stmt->close();
    }
}

// ------------------------------------
// 2. ข้อมูลผู้ใช้ปัจจุบันและจำนวนรายการรออนุมัติ (สำหรับ Header/Sidebar)
// ------------------------------------
$current_user_name = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];

$pending_approvals = 0;
// ดึงจำนวนรายการรออนุมัติจริงจากฐานข้อมูล
$sql_count = "SELECT COUNT(*) AS pending_count FROM Publication WHERE status = 'Waiting'"; 
$result_count = $conn->query($sql_count);

if ($result_count && $result_count->num_rows > 0) {
    $row_count = $result_count->fetch_assoc();
    $pending_approvals = (int)$row_count['pending_count'];
    $result_count->free();
}

// ------------------------------------
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>เพิ่มรายชื่ออาจารย์และเจ้าหน้าที่ (Admin)</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

  <style>
    /* สไตล์ Header คล้าย Admin Dashboard อื่นๆ */
    .top-header {
        background-color: #cce4f9; /* สีฟ้าอ่อน */
        padding: 1rem 2rem;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }
  </style>
</head>
<!-- ใช้ class body ที่เป็นมาตรฐานของ Admin Dashboard -->
<body class="bg-gray-50 font-sans flex min-h-screen">

<!-- Sidebar -->
<aside class="w-64 bg-white shadow-xl p-6 flex flex-col sticky top-0 h-screen z-10">
    <h2 class="text-2xl font-extrabold text-blue-700 mb-6 border-b pb-4">Admin Menu</h2>
    <nav class="w-full flex-grow">
        
        <!-- จัดการข้อมูลอาจารย์ (ถือว่าเป็นส่วนที่ active เนื่องจากเป็นงานภายใต้การจัดการ) -->
        <a href="Admin-manage.php" class="flex items-center p-3 rounded-xl mb-3 text-white bg-blue-600 shadow-md hover:bg-blue-700 font-semibold transition-colors duration-150">
            <i class="fas fa-users-cog w-5 h-5 mr-3"></i> จัดการข้อมูลอาจารย์
        </a>
        
        <a href="user_his.php" class="flex items-center p-3 rounded-xl mb-3 text-gray-700 hover:bg-blue-50 hover:text-blue-700 transition-colors duration-150">
            <i class="fas fa-history w-5 h-5 mr-3"></i> ประวัติการเข้าใช้งาน
        </a>
        <a href="usermannual.php" class="flex items-center p-3 rounded-xl text-gray-700 hover:bg-blue-50 hover:text-blue-700 transition-colors duration-150">
            <i class="fas fa-book w-5 h-5 mr-3"></i> คู่มือการใช้งานระบบ
        </a>
        <a href="check_credentials.php" class="flex items-center p-3 rounded-xl text-gray-700 hover:bg-blue-50 hover:text-blue-700 transition-colors duration-150">
            <i class="fas fa-key w-5 h-5 mr-3"></i> ตรวจสอบชื่อ/รหัสผ่าน
        </a>
        
        <hr class="my-6 border-gray-200">
        
        <!-- ปุ่มออกจากระบบ -->
        <a href="logout.php" class="flex items-center p-3 rounded-lg text-red-500 hover:bg-red-50 transition-colors duration-150">
            <i class="fas fa-sign-out-alt w-5 h-5 mr-3"></i> ออกจากระบบ
        </a>
    </nav>
</aside>

<!-- Main Content Wrapper: เพิ่ม min-w-0 เพื่อป้องกันการบีบ Sidebar -->
<div class="flex-1 flex flex-col min-w-0">
    <!-- Header -->
    <header class="top-header flex items-center justify-between sticky top-0 z-10">
        <h1 class="text-xl md:text-2xl font-bold text-gray-700 tracking-wide">
            <i class="fas fa-user-plus mr-2 text-blue-800"></i> เพิ่มรายชื่ออาจารย์และเจ้าหน้าที่
        </h1>
        <div class="flex items-center space-x-4">
            
            <!-- ไอคอนกระดิ่งแจ้งเตือน (รายการรออนุมัติ) -->
            <button id="notification-bell" 
                    class="relative p-2 rounded-full text-gray-600 hover:bg-blue-100 transition-colors" 
                    onclick="redirectToApprovals()">
                <i class="fas fa-bell text-xl"></i>
                <!-- แสดงจำนวนรายการรออนุมัติจริง -->
                <?php if ($pending_approvals > 0): ?>
                    <!-- ป้ายสีแดงเมื่อมีรายการรออนุมัติ -->
                    <span class="absolute top-0 right-0 block h-4 w-4 rounded-full ring-2 ring-white bg-red-600 text-xs text-white flex items-center justify-center font-bold">
                        <?= $pending_approvals; ?>
                    </span>
                <?php endif; ?>
            </button>
            
            <span class="text-gray-700 font-medium hidden sm:block text-sm">
                ผู้ดูแลระบบ: <?= htmlspecialchars($current_user_name); ?>
            </span>
            <!-- สีน้ำเงิน -->
            <div class="w-8 h-8 rounded-full bg-blue-700 text-white flex items-center justify-center font-bold">A</div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-1 p-8">
        <h2 class="text-3xl font-bold text-gray-800 mb-6 border-b pb-3">ฟอร์มเพิ่มผู้ใช้งานใหม่</h2>
        
        <div class="bg-white rounded-2xl shadow-2xl p-8 max-w-4xl mx-auto border border-blue-200">
            <form class="grid grid-cols-1 md:grid-cols-2 gap-6" method="POST" enctype="multipart/form-data">
              
              <!-- Row 1: ชื่อจริง / นามสกุล -->
              <div>
                <label class="block text-sm font-semibold text-gray-700">ชื่อจริง <span class="text-red-500">*</span></label>
                <input type="text" name="first_name" class="w-full mt-1 border border-gray-300 rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all" placeholder="กรอกชื่อจริง" required>
              </div>
              <div>
                <label class="block text-sm font-semibold text-gray-700">นามสกุล <span class="text-red-500">*</span></label>
                <input type="text" name="last_name" class="w-full mt-1 border border-gray-300 rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all" placeholder="กรอกนามสกุล" required>
              </div>

              <!-- Row 2: เบอร์โทร / คณะ (Department) -->
              <div>
                <label class="block text-sm font-semibold text-gray-700">เบอร์โทรศัพท์ <span class="text-red-500">*</span></label>
                <input type="text" name="tel" class="w-full mt-1 border border-gray-300 rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all" placeholder="กรอกเบอร์โทรศัพท์" required>
              </div>
              <div>
                <label class="block text-sm font-semibold text-gray-700">คณะ/หน่วยงาน <span class="text-red-500">*</span></label>
                <!-- ใช้ name="address" เพื่อให้สอดคล้องกับ PHP เดิมที่แมปไป Department -->
                <input type="text" name="address" class="w-full mt-1 border border-gray-300 rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all" placeholder="กรอกคณะ/หน่วยงาน" required>
              </div>

              <!-- Row 3: อีเมล / ตำแหน่ง -->
              <div>
                <label class="block text-sm font-semibold text-gray-700">อีเมล <span class="text-red-500">*</span></label>
                <input type="email" name="email" class="w-full mt-1 border border-gray-300 rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all" placeholder="กรอกอีเมล" required>
              </div>
              <div>
                <label class="block text-sm font-semibold text-gray-700">ตำแหน่ง</label>
                <select name="role" class="w-full mt-1 border border-gray-300 rounded-lg px-4 py-2.5 bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all">
                  <option value="normal">อาจารย์</option>
                  <option value="staff">เจ้าหน้าที่</option>
                </select>
              </div>

              <!-- Row 4: บัญชีผู้ใช้ / รูปโปรไฟล์ -->
              <div>
                <label class="block text-sm font-semibold text-gray-700">บัญชีผู้ใช้ (Username) <span class="text-red-500">*</span></label>
                <input type="text" name="username" class="w-full mt-1 border border-gray-300 rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all" placeholder="กรอกชื่อบัญชีผู้ใช้" required>
              </div>
              <div>
                <label class="block text-sm font-semibold text-gray-700">รูปโปรไฟล์</label>
                <input type="file" name="avatar" accept="image/*" class="w-full mt-1 block text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 transition-all">
              </div>

              <!-- Row 5: รหัสผ่าน / ยืนยันรหัสผ่าน -->
              <div>
                <label class="block text-sm font-semibold text-gray-700">รหัสผ่าน <span class="text-red-500">*</span></label>
                <input type="password" name="password" class="w-full mt-1 border border-gray-300 rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all" placeholder="กรอกรหัสผ่าน" required>
              </div>
              <div>
                <label class="block text-sm font-semibold text-gray-700">ยืนยันรหัสผ่าน <span class="text-red-500">*</span></label>
                <input type="password" name="confirm_password" class="w-full mt-1 border border-gray-300 rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all" placeholder="ยืนยันรหัสผ่าน" required>
              </div>

              <div class="col-span-1 md:col-span-2 flex justify-center gap-6 pt-6 border-t mt-4">
                <a href="Admin-manage.php" class="bg-gray-200 text-gray-700 px-8 py-3 rounded-xl font-bold hover:bg-gray-300 transition-colors shadow-lg">
                    <i class="fas fa-times mr-2"></i> ยกเลิก
                </a>
                <button type="submit" class="bg-blue-600 text-white px-8 py-3 rounded-xl font-bold hover:bg-blue-700 transition-colors shadow-lg shadow-blue-300/50">
                    <i class="fas fa-save mr-2"></i> เพิ่มข้อมูล
                </button>
              </div>
            </form>
        </div>
    </main>

    <!-- Footer -->
    <footer class="p-4 bg-gray-200 text-center text-gray-600 text-sm mt-auto">
        &copy; <?php echo date("Y"); ?> ระบบจัดการผลงาน | มหาวิทยาลัยสงขลานครินทร์ (PSU)
    </footer>

</div> <!-- End of flex-1 flex flex-col -->

<script>
    /**
     * ฟังก์ชันนำทางไปยังหน้าจัดการอนุมัติ (approved.php)
     */
    function redirectToApprovals() {
        const url = "approved.php"; 
        window.location.href = url; 
    }
</script>

</body>
</html>

<?php 
// ปิดการเชื่อมต่อฐานข้อมูลในตอนท้าย
if (isset($conn)) {
    $conn->close(); 
}
?>
