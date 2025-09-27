<?php
// เริ่ม session (ถ้าต้องใช้)
session_start();

// เชื่อมต่อฐานข้อมูล
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "group10";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ตรวจสอบว่ามีการ submit form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $first_name = trim($_POST['first_name'] ?? '');
    $last_name  = trim($_POST['last_name'] ?? '');
    $tel        = trim($_POST['tel'] ?? '');
    $address    = trim($_POST['address'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $role       = $_POST['role'] ?? 'normal';
    $username   = trim($_POST['username'] ?? '');
    $password   = trim($_POST['password'] ?? '');
    $confirm    = trim($_POST['confirm_password'] ?? '');

    // ตรวจสอบรหัสผ่านตรงกัน
    if ($password !== $confirm) {
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

        // บันทึกข้อมูลลงฐานข้อมูล
        $stmt = $conn->prepare("INSERT INTO User (first_name, last_name, tel, Department, email, role, Username, Password, avatar)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssssss", $first_name, $last_name, $tel, $address, $email, $role, $username, $password, $avatar_name);

        if ($stmt->execute()) {
            echo "<script>alert('เพิ่มข้อมูลเรียบร้อย'); window.location.href='Admin-manage.php';</script>";
        } else {
            echo "Error: " . $stmt->error;
        }

        $stmt->close();
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>เพิ่มรายชื่ออาจารย์และเจ้าหน้าที่</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-blue-50 font-sans">
  <!-- Header และ Sidebar เหมือนเดิม -->
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
    <aside id="sidebar"
           class="fixed top-0 left-0 w-64 bg-blue-50 min-h-screen border-r transform -translate-x-full transition-transform duration-300 z-40">
      <nav class="flex flex-col text-sm pt-16">
        <a href="#" class="px-6 py-3 flex items-center gap-3 hover:bg-blue-100"><span>🔍</span> ค้นหาผลงานตีพิมพ์</a>
        <a href="#" class="px-6 py-3 flex items-center gap-3 hover:bg-blue-100"><span>✏️</span> แก้ไขข้อมูลอาจารย์</a>
        <a href="#" class="px-6 py-3 flex items-center gap-3 bg-gray-200 font-semibold"><span>➕</span> เพิ่มข้อมูลอาจารย์ / เจ้าหน้าที่</a>
        <a href="#" class="px-6 py-3 flex items-center gap-3 hover:bg-blue-100"><span>🔔</span> ตั้งการแจ้งเตือน</a>
        <a href="#" class="px-6 py-3 flex items-center gap-3 hover:bg-blue-100"><span>📄</span> รายงานผล / ดาวน์โหลด PDF</a>
      </nav>
    </aside>
    <div id="overlay" class="fixed inset-0 bg-black bg-opacity-40 hidden z-30"></div>

    <main class="flex-1 p-8">
      <div class="bg-blue-100 rounded-lg shadow p-6 max-w-3xl mx-auto">
        <form class="grid grid-cols-2 gap-6" method="POST" enctype="multipart/form-data">
          <!-- ฟิลด์ form เหมือนเดิม -->
          <div>
            <label class="block text-sm font-medium">ชื่อจริง <span class="text-red-500">*</span></label>
            <input type="text" name="first_name" class="w-full mt-1 border rounded-md px-3 py-2 focus:ring focus:ring-blue-300" placeholder="กรอกชื่อจริง" required>
          </div>
          <div>
            <label class="block text-sm font-medium">นามสกุล <span class="text-red-500">*</span></label>
            <input type="text" name="last_name" class="w-full mt-1 border rounded-md px-3 py-2" placeholder="กรอกนามสกุล" required>
          </div>

          <div>
            <label class="block text-sm font-medium">เบอร์โทรศัพท์ <span class="text-red-500">*</span></label>
            <input type="text" name="tel" class="w-full mt-1 border rounded-md px-3 py-2" placeholder="กรอกเบอร์โทรศัพท์" required>
          </div>
          <div>
            <label class="block text-sm font-medium">คณะ <span class="text-red-500">*</span></label>
            <input type="text" name="address" class="w-full mt-1 border rounded-md px-3 py-2" placeholder="กรอกที่อยู่" required>
          </div>

          <div>
            <label class="block text-sm font-medium">อีเมล <span class="text-red-500">*</span></label>
            <input type="email" name="email" class="w-full mt-1 border rounded-md px-3 py-2" placeholder="กรอกอีเมล" required>
          </div>
          <div>
            <label class="block text-sm font-medium">ตำแหน่ง</label>
            <select name="role" class="w-full mt-1 border rounded-md px-3 py-2">
              <option value="normal">อาจารย์</option>
              <option value="staff">เจ้าหน้าที่</option>
            </select>
          </div>

          <div>
            <label class="block text-sm font-medium">บัญชีผู้ใช้ <span class="text-red-500">*</span></label>
            <input type="text" name="username" class="w-full mt-1 border rounded-md px-3 py-2" placeholder="กรอกชื่อบัญชีผู้ใช้" required>
          </div>
          <div>
            <label class="block text-sm font-medium">รูปโปรไฟล์</label>
            <input type="file" name="avatar" accept="image/*" class="w-full mt-1 border rounded-md px-3 py-2">
          </div>

          <div>
            <label class="block text-sm font-medium">รหัสผ่าน <span class="text-red-500">*</span></label>
            <input type="password" name="password" class="w-full mt-1 border rounded-md px-3 py-2" placeholder="กรอกรหัสผ่าน" required>
          </div>
          <div>
            <label class="block text-sm font-medium">ยืนยันรหัสผ่าน <span class="text-red-500">*</span></label>
            <input type="password" name="confirm_password" class="w-full mt-1 border rounded-md px-3 py-2" placeholder="ยืนยันรหัสผ่าน" required>
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
