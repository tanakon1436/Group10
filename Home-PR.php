<?php
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
$user_id = $_SESSION['user_id'];

$sql = "SELECT first_name, last_name, role FROM User WHERE User_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ระบบ จัดการการตีพิมพ์ผลงานของอาจารย์</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">

  <!-- Header -->
<header class="bg-blue-100 relative flex justify-between items-center px-4 py-3 shadow">
  <!-- ปุ่มเมนู (ซ้าย) -->
  <button class="text-2xl">&#9776;</button>

  <!-- ชื่อระบบ จัดให้อยู่กลางหน้าจอ -->
  <h1 class="absolute left-1/2 transform -translate-x-1/2 text-lg font-semibold">
    ระบบ จัดการการตีพิมพ์ผลงานของอาจารย์
  </h1>

  <!-- ไอคอนด้านขวา -->
  <div class="flex items-center space-x-3">
    <button class="text-xl">🔔</button>
    <button class="text-xl">👤</button>
  </div>
</header>

<div class="flex">
  <!-- Sidebar -->
  <aside class="bg-white w-56 min-h-screen shadow-md flex flex-col justify-between">
    <div>
      <div class="flex items-center px-4 py-4 border-b">
        <div class="text-2xl">👤</div>
        <span class="ml-3">
          <?php echo htmlspecialchars($user['first_name'] . " " . $user['last_name']); ?>
        </span>
      </div>
      <nav class="mt-2 flex flex-col">
        <a href="Home-PR.php" class="block p-2 rounded-lg mb-2 text-blue-700 bg-blue-100 hover:bg-blue-200 hover:text-blue-900">
          <span class="text-xl mr-3">🏠</span> หน้าหลัก
        </a>
        <a href="history.php" class="flex items-center px-4 py-2 hover:bg-blue-50 transition">
          <span class="text-xl mr-3">⏳</span> ประวัติการแก้ไข
        </a>
        <a href="usermannual.php" class="flex items-center px-4 py-2 hover:bg-blue-50 transition">
          <span class="text-xl mr-3">📖</span> คู่มือการใช้งาน
        </a>
        <a href="contact.php" class="flex items-center px-4 py-2 hover:bg-blue-50 transition">
          <span class="text-xl mr-3">📞</span> ช่องทางติดต่อ
        </a>
      </nav>
    </div>

    <div class="px-4 py-4 border-t">
      <a href="logout.php" class="flex items-center text-red-500 hover:underline">
        <span class="text-xl mr-3">⏻</span> ออกจากระบบ
      </a>
    </div>
  </aside>

  <!-- Main Content -->
  <main class="flex-1 flex flex-col items-center justify-center p-6">
    <div class="space-x-6">
      <a href="publications.php">
        <button class="bg-blue-100 px-6 py-3 rounded-lg shadow hover:bg-blue-200 transition">
          รายการผลงานตีพิมพ์
        </button>
      </a>
      <a href="add_publication.php">
        <button class="bg-blue-100 px-6 py-3 rounded-lg shadow hover:bg-blue-200 transition">
          เพิ่มผลงานตีพิมพ์
        </button>
      </a>
    </div>
  </main>
</div>

</body>
</html>
<?php
$stmt->close();
$conn->close();
?>
