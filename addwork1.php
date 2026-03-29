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

// ตรวจสอบว่ามี success จาก redirect
if (isset($_GET['success'])) {
    $message = "✅ บันทึกผลงานสำเร็จ!";
}

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
    $manual       = $details;
    $coauthor     =  $coauthor;

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

    // ================== ตรวจสอบซ้ำก่อนบันทึก ==================
    $checkStmt = $conn->prepare("SELECT Pub_id FROM Publication WHERE title = ? AND Author_id = ?");
    $checkStmt->bind_param("si", $title, $author_id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();

    if ($checkResult->num_rows > 0) {
        // มีผลงานซ้ำ
        $message = "⚠️ มีผลงานนี้อยู่แล้ว!";
    } else {
        // Insert งานใหม่
        $stmt = $conn->prepare(
            "INSERT INTO Publication 
                (title, publish_year, journal, type, file_path, visibility, status, Manual, Author_id, co_author)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param(
            "sissssssss", 
            $title, $publish_year, $journal, $type, $file_path, $visibility, $status, $manual, $author_id, $coauthor
        );

        if ($stmt->execute()) {
            $stmt->close();
            $checkStmt->close();
            // ใช้ redirect เพื่อรีเซ็ต POST และป้องกันซ้ำ
            header("Location: " . $_SERVER['PHP_SELF'] . "?success=1");
            exit();
        } else {
            $message = "❌ เกิดข้อผิดพลาด: " . $stmt->error;
            $stmt->close();
        }
    }

    $checkStmt->close();
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title>เพิ่มผลงานตีพิมพ์</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-white font-sans">

  <!-- Header -->
  <header class="flex items-center justify-between bg-blue-100 p-4 shadow">
    <div class="flex items-center gap-3">
      <button id="menuBtn" class="text-2xl">&#9776;</button>
      <h1 class="text-lg font-semibold">ระบบ จัดการการตีพิมพ์ผลงานของอาจารย์</h1>
    </div>
    <div class="flex gap-4 text-xl">
      <button>🔔</button>
      <a href="profile.php">👤</a>
    </div>
  </header>

  <!-- Sidebar -->
  <aside id="sidebar" class="fixed top-0 left-0 w-64 h-full bg-white shadow transform -translate-x-full transition-transform duration-300 z-30">
    <div class="p-4 border-b">
      <div class="flex items-center gap-2">
        <span class="text-2xl">👤</span>
        <span id="username" class="font-medium">กำลังโหลด...</span>
      </div>
    </div>
    <nav class="p-4 space-y-4">
      <a href="list-of-published.php" class="flex items-center gap-2 hover:text-blue-600"><span>🏠</span> หน้าหลัก</a>
      <a href="history.php" class="flex items-center gap-2 hover:text-blue-600"><span>⏳</span> ประวัติการแก้ไข</a>
      <a href="manual.php" class="flex items-center gap-2 hover:text-blue-600"><span>📘</span> คู่มือการใช้งาน</a>
      <a href="contact.php" class="flex items-center gap-2 hover:text-blue-600"><span>📞</span> ช่องทางติดต่อ</a>
    </nav>
    <div class="absolute bottom-0 w-full border-t p-4">
      <a href="login.php" class="flex items-center gap-2 text-red-600 hover:text-red-800"><span>↩️</span> ออกจากระบบ</a>
    </div>
  </aside>

  <!-- Main -->
  <main class="p-6">
    <h2 class="text-xl font-semibold text-center mb-6">เพิ่มผลงานตีพิมพ์</h2>

    <?php if (!empty($message)): ?>
      <div class="mb-4 p-3 <?= isset($_GET['success']) ? 'bg-green-200 text-green-800' : 'bg-red-200 text-red-800' ?> rounded">
        <?= $message ?>
      </div>
    <?php endif; ?>

    <form id="publicationForm" action="" method="POST" enctype="multipart/form-data"
          class="bg-blue-100 p-6 rounded-xl space-y-4 max-w-2xl mx-auto">

      <div>
        <label class="block mb-1 font-medium">ชื่อผลงาน <span class="text-red-500">*</span></label>
        <input type="text" name="title" required
               class="w-full p-2 rounded border border-gray-300">
      </div>

      <div>
        <label class="block mb-1 font-medium">ชื่อผู้ตีพิมพ์ผลงาน <span class="text-red-500">*</span></label>
        <input type="text" name="author" required
               class="w-full p-2 rounded border border-gray-300">
      </div>

      <div>
        <label class="block mb-1 font-medium">ชื่อผู้ร่วมตีพิมพ์ผลงาน</label>
        <input type="text" name="coauthor"
               class="w-full p-2 rounded border border-gray-300">
      </div>

      <div>
        <label class="block mb-1 font-medium">รายละเอียด <span class="text-red-500">*</span></label>
        <textarea name="details" rows="4" required
                  class="w-full p-2 rounded border border-gray-300"></textarea>
      </div>

      <div>
        <label class="block mb-2 font-medium text-gray-700">แนบไฟล์ผลงาน</label>
        <div class="relative w-full">
          <input type="file" name="file" id="fileUpload" class="hidden">
          <label for="fileUpload" class="flex items-center justify-center px-4 py-2 bg-white border border-gray-300 rounded-lg shadow-sm cursor-pointer hover:bg-gray-50 transition">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4a1 1 0 011-1h8a1 1 0 011 1v12m-4 0v4m-4-4h8" />
            </svg>
            <span class="text-gray-700">เลือกไฟล์</span>
          </label>
          <p id="fileName" class="mt-2 text-sm text-gray-500 italic"></p>
        </div>
      </div>

      <div class="flex justify-center gap-4 pt-4">
        <button type="reset" class="bg-gray-300 px-6 py-2 rounded-lg hover:bg-gray-400">ยกเลิก</button>
        <button type="submit" class="bg-blue-500 text-white px-6 py-2 rounded-lg hover:bg-blue-600">บันทึก</button>
      </div>
    </form>
  </main>

  <script>
    // Toggle Sidebar
    const menuBtn = document.getElementById("menuBtn");
    const sidebar = document.getElementById("sidebar");
    menuBtn.addEventListener("click", () => {
      sidebar.classList.toggle("-translate-x-full");
    });

    // Show selected file name
    document.getElementById("fileUpload").addEventListener("change", function() {
      const fileName = this.files[0] ? this.files[0].name : "";
      document.getElementById("fileName").innerText = fileName ? "📄 " + fileName : "";
    });
  </script>
</body>
</html>
