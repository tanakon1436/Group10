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
// 1. ดึงข้อมูลผู้ใช้ปัจจุบันที่ต้องการแก้ไข
// ------------------------------------
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    // แจ้งเตือนและ Redirect หากไม่มี ID หรือ ID ไม่ถูกต้อง
    echo "<script>alert('ไม่พบ User ID ที่ต้องการแก้ไข'); window.location.href='Admin-manage.php';</script>";
    exit();
}
$user_id = (int)$_GET['id'];

// เตรียมคำสั่ง SQL เพื่อดึงข้อมูล
$stmt_fetch = $conn->prepare("SELECT first_name, last_name, tel, Department, email, role, Username, Password, avatar FROM User WHERE User_id = ?");
$stmt_fetch->bind_param("i", $user_id);
$stmt_fetch->execute();
$result_fetch = $stmt_fetch->get_result();

if ($result_fetch->num_rows === 0) {
    // แจ้งเตือนและ Redirect หากไม่พบผู้ใช้
    $stmt_fetch->close();
    echo "<script>alert('ไม่พบผู้ใช้งาน ID: " . $user_id . "'); window.location.href='Admin-manage.php';</script>";
    exit();
}
// เก็บข้อมูลผู้ใช้เดิมไว้ในตัวแปร $user_data
$user_data = $result_fetch->fetch_assoc();
$stmt_fetch->close();
// ------------------------------------


// ------------------------------------
// 2. การจัดการฟอร์ม POST (เมื่อมีการบันทึกการแก้ไข)
// ------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // A. รับและทำความสะอาดข้อมูล
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name  = trim($_POST['last_name'] ?? '');
    $tel        = trim($_POST['tel'] ?? '');
    $department = trim($_POST['address'] ?? ''); // แมปจาก 'address' ในฟอร์ม เป็น 'Department' ใน DB
    $email      = trim($_POST['email'] ?? '');
    $role       = $_POST['role'] ?? 'normal';
    $username   = trim($_POST['username'] ?? '');
    $password   = trim($_POST['password'] ?? '');
    $confirm    = trim($_POST['confirm_password'] ?? '');
    $old_avatar = $user_data['avatar']; // รูปเดิม

    // B. การตรวจสอบรหัสผ่าน
    $is_password_changed = false;
    $new_password = null;

    if (!empty($password)) {
        if ($password !== $confirm) {
            echo "<script>alert('รหัสผ่านใหม่ไม่ตรงกัน');</script>";
            // ไม่ทำการอัปเดตต่อ
        } else {
            $is_password_changed = true;
            $new_password = $password;
        }
    }

    // C. การจัดการรูปโปรไฟล์
    $avatar_name = $old_avatar; // ตั้งค่าเริ่มต้นเป็นรูปเดิม
    if (!empty($_FILES['avatar']['name'])) {
        $target_dir = "img/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        $ext = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
        $new_avatar_name = uniqid('avatar_') . '.' . $ext;
        $target_file = $target_dir . $new_avatar_name;

        if (move_uploaded_file($_FILES['avatar']['tmp_name'], $target_file)) {
            // ลบรูปเก่าถ้ามีและไม่ใช่รูป Default
            if (!empty($old_avatar) && file_exists($target_dir . $old_avatar)) {
                @unlink($target_dir . $old_avatar);
            }
            $avatar_name = $new_avatar_name;
        } else {
            // กรณีอัปโหลดรูปไม่สำเร็จ
            echo "<script>alert('เกิดข้อผิดพลาดในการอัปโหลดรูปโปรไฟล์');</script>";
            // ไม่ทำการอัปเดตต่อ
        }
    }
    
    // D. สร้างคำสั่ง UPDATE DYNAMICALLY
    $update_fields = [
        "first_name" => $first_name, 
        "last_name" => $last_name, 
        "tel" => $tel, 
        "Department" => $department, 
        "email" => $email, 
        "role" => $role, 
        "Username" => $username, 
        "avatar" => $avatar_name
    ];
    
    $sql_parts = [];
    $bind_params = "";
    $bind_values = [];

    // เพิ่ม fields ที่อัปเดต
    foreach ($update_fields as $key => $value) {
        $sql_parts[] = "$key = ?";
        $bind_params .= "s";
        $bind_values[] = $value;
    }

    // เพิ่ม Password ถ้ามีการแก้ไข
    if ($is_password_changed) {
        $sql_parts[] = "Password = ?";
        $bind_params .= "s";
        $bind_values[] = $new_password;
    }

    // เพิ่ม User_id เป็นเงื่อนไข WHERE
    $bind_params .= "i";
    $bind_values[] = $user_id;

    $sql = "UPDATE User SET " . implode(", ", $sql_parts) . " WHERE User_id = ?";
    
    // E. Execute UPDATE
    $stmt_update = $conn->prepare($sql);
    $stmt_update->bind_param($bind_params, ...$bind_values);

    if ($stmt_update->execute()) {
        echo "<script>alert('แก้ไขข้อมูลผู้ใช้ ID: " . $user_id . " เรียบร้อยแล้ว'); window.location.href='Admin-manage.php';</script>";
        $stmt_update->close();
        $conn->close();
        exit();
    } else {
        echo "<script>alert('Error: " . $stmt_update->error . "');</script>";
        $stmt_update->close();
    }
    // อัปเดตข้อมูลใน $user_data เพื่อแสดงผลล่าสุดในฟอร์ม (ถ้า execute ไม่สำเร็จ)
    $user_data = array_merge($user_data, $_POST); 
}

// ------------------------------------
// 3. ข้อมูลผู้ใช้ปัจจุบันและจำนวนรายการรออนุมัติ (สำหรับ Header/Sidebar)
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
  <title>แก้ไขข้อมูลผู้ใช้งาน ID: <?= $user_id; ?></title>
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
        
        <!-- เมนูที่กำลังใช้งาน: สีน้ำเงิน -->
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
            <i class="fas fa-user-edit mr-2 text-blue-800"></i> แก้ไขข้อมูลผู้ใช้งาน ID: <?= $user_id; ?>
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
        <h2 class="text-3xl font-bold text-gray-800 mb-6 border-b pb-3">แก้ไขข้อมูล: <?= htmlspecialchars($user_data['first_name'] . ' ' . $user_data['last_name']); ?></h2>
        
        <div class="bg-white rounded-2xl shadow-2xl p-8 max-w-4xl mx-auto border border-blue-200">
            <form class="grid grid-cols-1 md:grid-cols-2 gap-6" method="POST" enctype="multipart/form-data">
              
              <!-- User ID (ซ่อนไว้สำหรับอ้างอิงในการอัปเดต) -->
              <input type="hidden" name="user_id" value="<?= $user_id; ?>">

              <!-- Row 1: ชื่อจริง / นามสกุล -->
              <div>
                <label class="block text-sm font-semibold text-gray-700">ชื่อจริง <span class="text-red-500">*</span></label>
                <input type="text" name="first_name" class="w-full mt-1 border border-gray-300 rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all" placeholder="กรอกชื่อจริง" value="<?= htmlspecialchars($user_data['first_name']); ?>" required>
              </div>
              <div>
                <label class="block text-sm font-semibold text-gray-700">นามสกุล <span class="text-red-500">*</span></label>
                <input type="text" name="last_name" class="w-full mt-1 border border-gray-300 rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all" placeholder="กรอกนามสกุล" value="<?= htmlspecialchars($user_data['last_name']); ?>" required>
              </div>

              <!-- Row 2: เบอร์โทร / คณะ (Department) -->
              <div>
                <label class="block text-sm font-semibold text-gray-700">เบอร์โทรศัพท์ <span class="text-red-500">*</span></label>
                <input type="text" name="tel" class="w-full mt-1 border border-gray-300 rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all" placeholder="กรอกเบอร์โทรศัพท์" value="<?= htmlspecialchars($user_data['tel']); ?>" required>
              </div>
              <div>
                <label class="block text-sm font-semibold text-gray-700">คณะ/หน่วยงาน <span class="text-red-500">*</span></label>
                <input type="text" name="address" class="w-full mt-1 border border-gray-300 rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all" placeholder="กรอกคณะ/หน่วยงาน" value="<?= htmlspecialchars($user_data['Department']); ?>" required>
              </div>

              <!-- Row 3: อีเมล / ตำแหน่ง -->
              <div>
                <label class="block text-sm font-semibold text-gray-700">อีเมล <span class="text-red-500">*</span></label>
                <input type="email" name="email" class="w-full mt-1 border border-gray-300 rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all" placeholder="กรอกอีเมล" value="<?= htmlspecialchars($user_data['email']); ?>" required>
              </div>
              <div>
                <label class="block text-sm font-semibold text-gray-700">ตำแหน่ง</label>
                <select name="role" class="w-full mt-1 border border-gray-300 rounded-lg px-4 py-2.5 bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all">
                  <option value="normal" <?= ($user_data['role'] === 'normal') ? 'selected' : ''; ?>>อาจารย์</option>
                  <option value="staff" <?= ($user_data['role'] === 'staff') ? 'selected' : ''; ?>>เจ้าหน้าที่</option>
                </select>
              </div>

              <!-- Row 4: บัญชีผู้ใช้ / รูปโปรไฟล์ -->
              <div>
                <label class="block text-sm font-semibold text-gray-700">บัญชีผู้ใช้ (Username) <span class="text-red-500">*</span></label>
                <input type="text" name="username" class="w-full mt-1 border border-gray-300 rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all" placeholder="กรอกชื่อบัญชีผู้ใช้" value="<?= htmlspecialchars($user_data['Username']); ?>" required>
              </div>
              
              <div class="flex flex-col">
                <label class="block text-sm font-semibold text-gray-700">รูปโปรไฟล์ปัจจุบัน</label>
                <div class="flex items-center space-x-4 mt-1">
                    <?php if (!empty($user_data['avatar'])): ?>
                        <img src="img/<?= htmlspecialchars($user_data['avatar']); ?>" alt="Current Avatar" class="w-16 h-16 rounded-full object-cover border-2 border-blue-300 shadow">
                    <?php else: ?>
                        <div class="w-16 h-16 rounded-full bg-gray-300 text-gray-600 flex items-center justify-center font-bold text-sm border-2 border-gray-400">ไม่มีรูป</div>
                    <?php endif; ?>
                    <input type="file" name="avatar" accept="image/*" class="flex-grow block text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 transition-all">
                </div>
              </div>


              <!-- Row 5: รหัสผ่าน / ยืนยันรหัสผ่าน (ไม่ต้องกรอกถ้าไม่ต้องการเปลี่ยน) -->
              <div class="col-span-full border-t border-dashed pt-4 mt-2">
                <p class="text-lg font-bold text-gray-700 mb-3">การเปลี่ยนรหัสผ่าน (เว้นว่างไว้หากไม่ต้องการเปลี่ยน)</p>
              </div>

              <div>
                <label class="block text-sm font-semibold text-gray-700">รหัสผ่านใหม่</label>
                <input type="password" name="password" class="w-full mt-1 border border-gray-300 rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-red-300 focus:border-red-500 transition-all" placeholder="กรอกรหัสผ่านใหม่ (ถ้าต้องการเปลี่ยน)">
              </div>
              <div>
                <label class="block text-sm font-semibold text-gray-700">ยืนยันรหัสผ่านใหม่</label>
                <input type="password" name="confirm_password" class="w-full mt-1 border border-gray-300 rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-red-300 focus:border-red-500 transition-all" placeholder="ยืนยันรหัสผ่านใหม่">
              </div>

              <div class="col-span-1 md:col-span-2 flex justify-center gap-6 pt-6 border-t mt-4">
                <a href="Admin-manage.php" class="bg-gray-200 text-gray-700 px-8 py-3 rounded-xl font-bold hover:bg-gray-300 transition-colors shadow-lg">
                    <i class="fas fa-arrow-left mr-2"></i> ยกเลิก / กลับ
                </a>
                <button type="submit" class="bg-green-600 text-white px-8 py-3 rounded-xl font-bold hover:bg-green-700 transition-colors shadow-lg shadow-green-300/50">
                    <i class="fas fa-check-circle mr-2"></i> บันทึกการแก้ไข
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
    // ปิดการเชื่อมต่อสำหรับ Query นับรายการรออนุมัติ
    if (isset($result_count) && $result_count instanceof mysqli_result) {
        $result_count->free();
    }
    $conn->close(); 
}
?>
