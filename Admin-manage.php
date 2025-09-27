<?php
// --- เชื่อมต่อฐานข้อมูล ---
$servername = "localhost";
$username = "root";      // ตามค่า XAMPP ของคุณ
$password = "";          // ตามค่า XAMPP ของคุณ
$dbname = "group10";

$conn = new mysqli($servername, $username, $password, $dbname);

// ตรวจสอบการเชื่อมต่อ
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// --- ดึงข้อมูลอาจารย์ ---
$sql = "SELECT * FROM User WHERE role='normal'";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>จัดการข้อมูลอาจารย์และเจ้าหน้าที่</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">

  <!-- Header -->
  <header class="bg-blue-100 relative flex justify-between items-center px-4 py-3 shadow">
    <button class="text-3xl">&#9776;</button>
    <h1 class="absolute left-1/2 transform -translate-x-1/2 text-xl font-semibold">
      ระบบ จัดการการตีพิมพ์ Admin
    </h1>
    <button class="text-2xl">👤</button>
  </header>

  <div class="flex">
    <!-- Sidebar -->
    <aside class="bg-white w-56 min-h-screen shadow-md flex flex-col">
      <nav class="flex flex-col">
        
         <a href="#" class="px-4 py-3 bg-blue-100 font-semibold border-l-4 border-blue-500">
            <span class="text-xl mr-3"></span>จัดการข้อมูลอาจารย์และเจ้าหน้าที่
         </a>
         <a href="history.php" class="flex items-center px-4 py-2 hover:bg-blue-50 transition">
            <span class="text-xl mr-3"></span>คู่มือการใช้งานระบบ
         </a>
        
         <a href="history.php" class="flex items-center px-4 py-2 hover:bg-blue-50 transition">
            <span class="text-xl mr-3"></span>ประวัติการแก้ไขข้อมูล
         </a>
        
         <a href="history.php" class="flex items-center px-4 py-2 hover:bg-blue-50 transition">
            <span class="text-xl mr-3"></span>ตรวจสอบชื่อผู้ใช้และรหัสผ่าน
         </a>
       
         <a href="history.php" class="flex items-center px-4 py-2 hover:bg-blue-50 transition">
            <span class="text-xl mr-3"></span>ตรวจสอบสถานะบัญชี
         </a>
        
         <a href="history.php" class="flex items-center px-4 py-2 hover:bg-blue-50 transition">
            <span class="text-xl mr-3"></span>ติดต่อกับเจ้าหน้าที่
         </a>
      </nav>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 p-6">
      <h2 class="text-lg font-semibold mb-4">จัดการข้อมูลอาจารย์และเจ้าหน้าที่</h2>

      <!-- ช่องค้นหา -->
      <div class="mb-4 flex items-center">
        <input type="text" placeholder="ค้นหา..." 
               class="flex-1 px-3 py-2 rounded-full bg-blue-100 focus:outline-none focus:ring-2 focus:ring-blue-300">
      </div>

      <!-- ปุ่มเพิ่มอาจารย์ -->
      <button onclick="location.href='add-names-teacher-and-admin.php'" 
        class="flex items-center space-x-2 px-4 py-2 bg-blue-200 rounded-lg shadow hover:bg-blue-300 mb-4">
    <span>➕</span>
    <span>เพิ่มอาจารย์</span>
</button>


      <!-- การ์ดข้อมูลอาจารย์ -->
      <div class="space-y-4">
        <?php if ($result->num_rows > 0): ?>
          <?php while($row = $result->fetch_assoc()): ?>
            <div class="flex justify-between items-center bg-blue-100 p-4 rounded-lg shadow">
              <div class="flex items-center space-x-4">
                <?php if(!empty($row['avatar'])): ?>
                  <img src="img/<?php echo $row['avatar']; ?>" alt="อาจารย์" class="w-16 h-16 rounded-full object-cover">
                <?php endif; ?>
                <div>
                  <p class="font-bold">อาจารย์</p>
                  <p><?php echo $row['first_name'] . ' ' . $row['last_name']; ?></p>
                  <p><?php echo $row['Department']; ?></p>
                </div>
              </div>
              <div class="flex space-x-2">
                <button class="text-gray-600 text-2xl">➖</button>
                <button class="text-gray-600 text-2xl">✏️</button>
              </div>
            </div>
          <?php endwhile; ?>
        <?php else: ?>
          <p>ไม่มีข้อมูลอาจารย์</p>
        <?php endif; ?>
      </div>
    </main>
  </div>

</body>
</html>

<?php $conn->close(); ?>
