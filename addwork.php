<?php
// ================== Database Connection ==================
$servername = "localhost";
$username   = "root";    
$password   = "";
$dbname     = "group10";

$conn = new mysqli($servername, $username, $password, $dbname);
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    die("❌ Database connection failed: " . $conn->connect_error);
}
// =========================================================

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title    = $_POST['title'];
    $author   = $_POST['author'];
    $coauthor = $_POST['coauthor'];
    $details  = $_POST['details'];

    $publish_year = date("Y");
    $journal      = $author;
    $type         = "บทความ";
    $visibility   = "public";
    $status       = "pending";
    $manual       = "ผู้ตีพิมพ์: $author\nผู้ร่วมตีพิมพ์: $coauthor\nรายละเอียด: $details";

    // ================== หา Author_id ==================
    $stmtUser = $conn->prepare("SELECT User_id FROM User WHERE first_name = ?");
    $stmtUser->bind_param("s", $author);
    $stmtUser->execute();
    $resultUser = $stmtUser->get_result();

    if ($row = $resultUser->fetch_assoc()) {
        $author_id = $row['User_id'];
    } else {
        // ถ้าไม่เจอ ให้ insert User ใหม่ (minimal info)
        $stmtInsertUser = $conn->prepare(
            "INSERT INTO User (first_name, last_name, Username, Password, email, tel, role, Department, status) 
             VALUES (?, '', ?, '', '', '', 'อาจารย์', '', '1')"
        );
        $username = strtolower(str_replace(" ", "", $author));
        $stmtInsertUser->bind_param("ss", $author, $username);
        $stmtInsertUser->execute();
        $author_id = $stmtInsertUser->insert_id;
        $stmtInsertUser->close();
    }
    $stmtUser->close();
    // ====================================================

    // ================== จัดการไฟล์อัปโหลด ==================
    $file_path = null;
    if (isset($_FILES['file']) && $_FILES['file']['error'] == 0) {
        $upload_dir = "uploads/";
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        $file_name   = time() . "_" . basename($_FILES["file"]["name"]);
        $target_file = $upload_dir . $file_name;

        if (move_uploaded_file($_FILES["file"]["tmp_name"], $target_file)) {
            $file_path = $target_file;
        }
    }
    // ========================================================

    // ================== Insert Publication ==================
    $sql = "INSERT INTO Publication 
            (title, publish_year, journal, type, file_path, visibility, status, Manual, Author_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sissssssi", 
        $title, $publish_year, $journal, $type, $file_path, $visibility, $status, $manual, $author_id
    );

    if ($stmt->execute()) {
        $message = "✅ บันทึกผลงานสำเร็จ!";
    } else {
        $message = "❌ เกิดข้อผิดพลาด: " . $stmt->error;
    }

    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title>ระบบจัดการการตีพิมพ์ผลงานของอาจารย์</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-blue-50 font-sans">

  <!-- Sidebar -->
  <div id="sidebar" class="fixed top-0 left-0 h-full w-64 bg-white shadow transform -translate-x-full transition-transform duration-300 z-50">
    <div class="p-4 border-b">
      <h2 class="text-lg font-semibold">เมนู</h2>
    </div>
    <nav class="flex flex-col p-4 space-y-2">
      <a href="index.php" class="px-3 py-2 rounded hover:bg-blue-100">หน้าหลัก</a>
      <a href="history.php" class="px-3 py-2 rounded hover:bg-blue-100">ประวัติการแก้ไข</a>
      <a href="manual.php" class="px-3 py-2 rounded hover:bg-blue-100">คู่มือการใช้งาน</a>
      <a href="contact.php" class="px-3 py-2 rounded hover:bg-blue-100">ช่องทางติดต่อ</a>
    </nav>
  </div>

  <!-- Navbar -->
  <header class="flex items-center justify-between bg-blue-100 px-4 py-2 shadow">
    <button id="menu-btn" class="text-gray-700">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none"
           viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M4 6h16M4 12h16M4 18h16" />
      </svg>
    </button>
    <h1 class="text-center text-lg font-medium text-black">
      ระบบจัดการการตีพิมพ์ผลงานของอาจารย์
    </h1>
    <div class="flex items-center gap-4">
      <button class="text-gray-700">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none"
             viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 
                   0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 
                   .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 
                   11-6 0v-1m6 0H9" />
        </svg>
      </button>
      <a href="profile.php" class="w-8 h-8 flex items-center justify-center text-xl rounded-full bg-white shadow">👤</a>
    </div>
  </header>

  <!-- Main -->
  <main class="p-6">
    <div class="max-w-3xl mx-auto bg-blue-100 p-6 rounded-lg shadow">
      <div class="flex items-center mb-4">
        <button class="mr-2 text-gray-700">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none"
               viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M15 19l-7-7 7-7" />
          </svg>
        </button>
        <h2 class="text-xl font-semibold flex-grow text-center">เพิ่มผลงานตีพิมพ์</h2>
      </div>

      <!-- แสดงข้อความ -->
      <?php if (!empty($message)): ?>
        <div class="mb-4 p-3 bg-green-200 text-green-800 rounded">
          <?= $message ?>
        </div>
      <?php endif; ?>

      <!-- Form -->
      <form action="" method="POST" enctype="multipart/form-data" class="space-y-4">
        <div>
          <label class="block font-medium mb-1">ชื่อผลงาน <span class="text-red-500">*</span></label>
          <input type="text" name="title" required
                 class="w-full border rounded px-3 py-2 focus:outline-none focus:ring focus:ring-blue-300">
        </div>
        <div>
          <label class="block font-medium mb-1">ชื่อผู้ตีพิมพ์ผลงาน <span class="text-red-500">*</span></label>
          <input type="text" name="author" required
                 class="w-full border rounded px-3 py-2 focus:outline-none focus:ring focus:ring-blue-300">
        </div>
        <div>
          <label class="block font-medium mb-1">ชื่อผู้ร่วมตีพิมพ์ผลงาน</label>
          <input type="text" name="coauthor"
                 class="w-full border rounded px-3 py-2 focus:outline-none focus:ring focus:ring-blue-300">
        </div>
        <div>
          <label class="block font-medium mb-1">รายละเอียด <span class="text-red-500">*</span></label>
          <textarea name="details" required rows="4"
                    class="w-full border rounded px-3 py-2 focus:outline-none focus:ring focus:ring-blue-300"></textarea>
        </div>
        <div>
          <label class="block font-medium mb-1">แนบไฟล์ผลงาน</label>
          <input type="file" name="file"
                 class="w-full border rounded px-3 py-2 focus:outline-none focus:ring focus:ring-blue-300">
        </div>
        <div class="flex justify-center gap-3">
          <button type="reset" class="px-6 py-2 bg-gray-300 rounded hover:bg-gray-400">ยกเลิก</button>
          <button type="submit" class="px-6 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">บันทึก</button>
        </div>
      </form>
    </div>
  </main>

  <script>
    const menuBtn = document.getElementById('menu-btn');
    const sidebar = document.getElementById('sidebar');
    menuBtn.addEventListener('click', () => {
      sidebar.classList.toggle('-translate-x-full');
    });
  </script>
</body>
</html>
