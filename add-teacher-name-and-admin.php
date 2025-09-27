<?php
// add-names-teacher-and-admin.php

// ---------- CONFIG ----------
$db_host = 'localhost';
$db_user = 'root';
$db_pass = ''; // ถ้ามี password ให้ใส่ที่นี่
$db_name = 'group10';

// โฟลเดอร์สำหรับเก็บรูป avatar
$upload_dir = __DIR__ . '/img/';
$upload_dir_web = 'img/'; // path สำหรับเก็บใน DB (relative)
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}
// ------------------------------------------------

// start session เพื่อเก็บข้อความแจ้งเตือน
session_start();

// ตัวแปรเก็บข้อความแสดงผล
$errors = [];
$success = '';

// เชื่อมต่อฐานข้อมูล (mysqli)
$mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($mysqli->connect_errno) {
    die("ไม่สามารถเชื่อมต่อฐานข้อมูลได้: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error);
}

// ตั้ง charset
$mysqli->set_charset('utf8mb4');

// ถ้าเป็นการ Submit form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ดึงค่าและ trim
    $first_name = isset($_POST['first_name']) ? trim($_POST['first_name']) : '';
    $last_name  = isset($_POST['last_name']) ? trim($_POST['last_name']) : '';
    $username   = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password   = isset($_POST['password']) ? $_POST['password'] : '';
    $email      = isset($_POST['email']) ? trim($_POST['email']) : '';
    $tel        = isset($_POST['tel']) ? trim($_POST['tel']) : '';
    $department = isset($_POST['department']) ? trim($_POST['department']) : null;
    $role       = isset($_POST['role']) ? trim($_POST['role']) : '';
    
    // VALIDATION พื้นฐาน
    if ($first_name === '') $errors[] = 'กรุณากรอก ชื่อจริง';
    if ($last_name === '') $errors[]  = 'กรุณากรอก นามสกุล';
    if ($username === '') $errors[]   = 'กรุณากรอก ชื่อบัญชีผู้ใช้';
    if ($password === '') $errors[]   = 'กรุณากรอก รหัสผ่าน';
    if ($email === '') $errors[]      = 'กรุณากรอก อีเมล';
    if ($tel === '') $errors[]        = 'กรุณากรอก เบอร์โทรศัพท์';

    // เช็ครูปแบบอีเมล
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'รูปแบบอีเมลไม่ถูกต้อง';
    }

    // ตรวจสอบว่าชื่อผู้ใช้มีใน DB หรือยัง
    if ($username !== '') {
        $stmt = $mysqli->prepare("SELECT User_id FROM `User` WHERE `Username` = ?");
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $errors[] = 'ชื่อบัญชีผู้ใช้ (Username) นี้ถูกใช้งานแล้ว';
        }
        $stmt->close();
    }

    // จัดการการอัปโหลด avatar (ถ้ามี)
    $avatar_db_path = null;
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['avatar'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'เกิดข้อผิดพลาดในการอัปโหลดรูป';
        } else {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif'];
            if (!array_key_exists($mime, $allowed)) {
                $errors[] = 'รองรับเฉพาะไฟล์รูปภาพ (jpg, png, gif)';
            } else {
                $ext = $allowed[$mime];
                $safe_name = preg_replace('/[^a-zA-Z0-9-_\.]/', '_', pathinfo($file['name'], PATHINFO_FILENAME));
                $new_name = $safe_name . '_' . time() . '.' . $ext;
                $dest = $upload_dir . $new_name;

                if (!move_uploaded_file($file['tmp_name'], $dest)) {
                    $errors[] = 'ไม่สามารถบันทึกรูปได้';
                } else {
                    $avatar_db_path = $upload_dir_web . $new_name;
                }
            }
        }
    }

    // ถ้าไม่มี error ให้บันทึกลง DB (เก็บ password แบบ plaintext)
    if (empty($errors)) {
        $status = '1';

        $sql = "INSERT INTO `User` (`first_name`, `last_name`, `Username`, `Password`, `email`, `tel`, `Department`, `role`, `avatar`, `status`)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $mysqli->prepare($sql);
        if (!$stmt) {
            $errors[] = 'เตรียมคำสั่ง SQL ไม่สำเร็จ: ' . $mysqli->error;
        } else {
            $stmt->bind_param(
                'ssssssssss',
                $first_name,
                $last_name,
                $username,
                $password, // เก็บ plaintext
                $email,
                $tel,
                $department,
                $role,
                $avatar_db_path,
                $status
            );
            if ($stmt->execute()) {
                $success = 'เพิ่มข้อมูลสำเร็จแล้ว';
            } else {
                $errors[] = 'ไม่สามารถบันทึกข้อมูล: ' . $stmt->error;
                if ($avatar_db_path && file_exists($upload_dir . basename($avatar_db_path))) {
                    @unlink($upload_dir . basename($avatar_db_path));
                }
            }
            $stmt->close();
        }
    }

    $_SESSION['form_errors'] = $errors;
    $_SESSION['form_success'] = $success;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>เพิ่มรายชื่ออาจารย์และเจ้าหน้าที่</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-blue-50 font-sans">

<header class="flex items-center justify-between bg-blue-100 px-6 py-4 shadow relative font-sans">
  <button id="menuBtn" class="space-y-1 cursor-pointer z-50">
    <div class="w-6 h-0.5 bg-black"></div>
    <div class="w-6 h-0.5 bg-black"></div>
    <div class="w-6 h-0.5 bg-black"></div>
  </button>
  <h1 class="absolute left-1/2 -translate-x-1/2 text-lg font-semibold">
    ระบบ เพิ่มรายชื่ออาจารย์และเจ้าหน้าที่
  </h1>
  <div class="w-8 h-8 flex items-center justify-center text-xl">👤</div>
</header>

<div class="flex">
<aside id="sidebar" class="fixed top-0 left-0 w-64 bg-blue-50 min-h-screen border-r transform -translate-x-full transition-transform duration-300 z-40">
  <nav class="flex flex-col text-sm pt-16">
    <a href="#" class="px-6 py-3 flex items-center gap-3 hover:bg-blue-100">🔍 ค้นหาผลงานตีพิมพ์</a>
    <a href="#" class="px-6 py-3 flex items-center gap-3 hover:bg-blue-100">✏️ แก้ไขข้อมูลอาจารย์</a>
    <a href="#" class="px-6 py-3 flex items-center gap-3 bg-gray-200 font-semibold">➕ เพิ่มข้อมูลอาจารย์ / เจ้าหน้าที่</a>
    <a href="#" class="px-6 py-3 flex items-center gap-3 hover:bg-blue-100">🔔 ตั้งการแจ้งเตือน</a>
    <a href="#" class="px-6 py-3 flex items-center gap-3 hover:bg-blue-100">📄 รายงานผล / ดาวน์โหลด PDF</a>
    <a href="#" class="px-6 py-3 flex items-center gap-3 hover:bg-red-100 text-red-600 ">⬅️ ออกจากระบบ </a>
  </nav>

</aside>

<div id="overlay" class="fixed inset-0 bg-black bg-opacity-40 hidden z-30"></div>

<main class="flex-1 p-8">
<div class="bg-blue-100 rounded-lg shadow p-6 max-w-3xl mx-auto">

<?php
if (!empty($_SESSION['form_errors'])) {
    echo '<div class="mb-4 p-3 bg-red-100 text-red-700 rounded">';
    foreach ($_SESSION['form_errors'] as $e) echo '<div>- ' . htmlspecialchars($e) . '</div>';
    echo '</div>';
    unset($_SESSION['form_errors']);
}
if (!empty($_SESSION['form_success'])) {
    echo '<div class="mb-4 p-3 bg-green-100 text-green-700 rounded">' . htmlspecialchars($_SESSION['form_success']) . '</div>';
    unset($_SESSION['form_success']);
}
?>

<form class="grid grid-cols-2 gap-6" method="POST" enctype="multipart/form-data" novalidate>
  <div>
    <label class="block text-sm font-medium">ชื่อจริง <span class="text-red-500">*</span></label>
    <input name="first_name" type="text" placeholder="กรอกชื่อจริง" class="w-full mt-1 border rounded-md px-3 py-2" value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>">
  </div>
  <div>
    <label class="block text-sm font-medium">นามสกุล <span class="text-red-500">*</span></label>
    <input name="last_name" type="text" placeholder="กรอกนามสกุล" class="w-full mt-1 border rounded-md px-3 py-2" value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>">
  </div>

  <div>
    <label class="block text-sm font-medium">บัญชีผู้ใช้ <span class="text-red-500">*</span></label>
    <input name="username" type="text" placeholder="กรอกชื่อบัญชีผู้ใช้" class="w-full mt-1 border rounded-md px-3 py-2" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
  </div>
  <div>
    <label class="block text-sm font-medium">รหัสผ่าน <span class="text-red-500">*</span></label>
    <input name="password" type="password" placeholder="กรอกรหัสผ่าน" class="w-full mt-1 border rounded-md px-3 py-2">
  </div>

  <div>
    <label class="block text-sm font-medium">อีเมล <span class="text-red-500">*</span></label>
    <input name="email" type="email" placeholder="กรอกอีเมล" class="w-full mt-1 border rounded-md px-3 py-2" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
  </div>
  <div>
    <label class="block text-sm font-medium">เบอร์โทร <span class="text-red-500">*</span></label>
    <input name="tel" type="text" placeholder="กรอกเบอร์โทรศัพท์" class="w-full mt-1 border rounded-md px-3 py-2" value="<?php echo isset($_POST['tel']) ? htmlspecialchars($_POST['tel']) : ''; ?>">
  </div>

  <div>
  <label class="block text-sm font-medium">ตำแหน่ง <span class="text-red-500">*</span></label>
  <select name="role" class="w-full mt-1 border rounded-md px-3 py-2">
    <option value="อาจารย์" <?php echo (isset($_POST['role']) && $_POST['role']=='อาจารย์') ? 'selected' : ''; ?>>อาจารย์</option>
    <option value="เจ้าหน้าที่" <?php echo (isset($_POST['role']) && $_POST['role']=='เจ้าหน้าที่') ? 'selected' : ''; ?>>เจ้าหน้าที่</option>
  </select>
</div>

<div>
  <label class="block text-sm font-medium">แผนก / ภาควิชา <span class="text-red-500">*</span></label>
  <input name="department" type="text" placeholder="กรอกแผนก / ภาควิชา" class="w-full mt-1 border rounded-md px-3 py-2" value="<?php echo isset($_POST['department']) ? htmlspecialchars($_POST['department']) : ''; ?>">
</div>

<div class="col-span-2">
  <label class="block text-sm font-medium">รูปประจำตัว (avatar) <span class="text-red-500">*</span></label>
  <input name="avatar" type="file" accept="image/*" class="mt-1">
</div>


  <div class="col-span-2 flex justify-center gap-4 pt-4">
    <a href="index.php" class="bg-gray-300 px-6 py-2 rounded-lg hover:bg-gray-400">ยกเลิก</a>
    <button type="submit" class="bg-blue-500 text-white px-6 py-2 rounded-lg hover:bg-blue-600">เพิ่มข้อมูล</button>
  </div>
</form>

</div>
</main>
</div>

<script>
const menuBtn = document.getElementById("menuBtn");
const sidebar = document.getElementById("sidebar");
const overlay = document.getElementById("overlay");

menuBtn.addEventListener("click", () => {
  sidebar.classList.toggle("-translate-x-full");
  overlay.classList.toggle("hidden");
});

overlay.addEventListener("click", () => {
  sidebar.classList.add("-translate-x-full");
  overlay.classList.add("hidden");
});
</script>
</body>
</html>
