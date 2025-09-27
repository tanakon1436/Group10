<?php
// ================= เชื่อมต่อฐานข้อมูล =================
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "group10";

$conn = new mysqli($servername, $username, $password, $dbname);
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// รับ Pub_id จาก URL
$pub_id = isset($_GET['Pub_id']) ? intval($_GET['Pub_id']) : 0;

// โหลดข้อมูลผลงาน
$sql = "SELECT * FROM Publication WHERE Pub_id = $pub_id";
$result = $conn->query($sql);
$publication = $result->fetch_assoc();

// โหลดชื่อผู้ใช้สำหรับ dropdown
$users_result = $conn->query("SELECT User_id, first_name, last_name FROM User");

// ================= บันทึกการแก้ไข =================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title     = $_POST['title'];
    $author    = $_POST['author'];
    $co_author = $_POST['co_author'];
    $manual    = $_POST['manual'];

    // ===== จัดการไฟล์ =====
    $file_path = $publication['file_path']; // ใช้ไฟล์เก่าเป็นค่าเริ่มต้น
    if (isset($_FILES['fileUpload']) && $_FILES['fileUpload']['error'] == 0) {
        $upload_dir = "uploads/";
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        $file_name   = time() . "_" . basename($_FILES["fileUpload"]["name"]);
        $target_file = $upload_dir . $file_name;

        if (move_uploaded_file($_FILES["fileUpload"]["tmp_name"], $target_file)) {
            $file_path = $target_file;
        }
    }

    $sql_update = "UPDATE Publication SET 
                    title=?,
                    Author_id=?,
                    Co_Author=?,
                    Manual=?,
                    file_path=?
                   WHERE Pub_id=?";
    $stmt = $conn->prepare($sql_update);
    $stmt->bind_param("sisssi", $title, $author, $co_author, $manual, $file_path, $pub_id);

    if ($stmt->execute()) {
        echo "<script>alert('บันทึกเรียบร้อยแล้ว!'); window.location.href='list-of-published.php';</script>";
        exit();
    } else {
        echo "Error: " . $stmt->error;
    }
    $stmt->close();
}

// ดึงข้อมูลผู้ร่วมตีพิมพ์ปัจจุบัน
$sql = "SELECT co_author FROM publication WHERE pub_id=$pub_id LIMIT 1";
$result = $conn->query($sql);
$co_author = $result->num_rows ? $result->fetch_assoc()['co_author'] : "";
?>

<?php if (!empty($message)) echo "<p>$message</p>"; ?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ระบบ จัดการการตีพิมพ์ผลงานของอาจารย์</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-white font-sans">
    <!-- Header -->
    <header class="flex items-center justify-between bg-blue-100 p-4 shadow fixed top-0 left-0 w-full z-40">
        <div class="flex items-center gap-3">
            <button id="menuBtn" class="text-2xl">&#9776;</button>
            <h1 class="text-lg font-semibold">ระบบ จัดการการตีพิมพ์ผลงานของอาจารย์</h1>
        </div>
        <div class="flex gap-4 text-xl">
            <button>🔔</button>
            <button>👤</button>
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
            <a href="#" class="flex items-center gap-2 hover:text-blue-600"><span>📘</span> คู่มือการใช้งาน</a>
            <a href="#" class="flex items-center gap-2 hover:text-blue-600"><span>📞</span> ช่องทางติดต่อ</a>
        </nav>
        <div class="absolute bottom-0 w-full border-t p-4">
            <a href="login.php" class="flex items-center gap-2 text-red-600 hover:text-red-800"><span>↩️</span> ออกจากระบบ</a>
        </div>
    </aside>

    <script>
        // Toggle Sidebar
        const menuBtn = document.getElementById("menuBtn");
        const sidebar = document.getElementById("sidebar");

        menuBtn.addEventListener("click", () => {
            sidebar.classList.toggle("-translate-x-full");
        });

        // โหลดชื่อผู้ใช้จาก localStorage
        window.onload = function() {
            const username = localStorage.getItem("username") || "ผศ.XXX XXXXX";
            document.getElementById("username").innerText = username;
        }
    </script>

    <!-- Main -->
    <main class="p-6">
        <h2 class="text-xl font-semibold text-center mb-6">แก้ไขผลงานตีพิมพ์</h2>

        <form method="post" enctype="multipart/form-data" class="bg-blue-100 p-4 rounded-xl space-y-4">
            <!-- ชื่อผลงาน -->
            <div>
                <label class="block mb-1 font-medium">ชื่อผลงาน</label>
                <input type="text" name="title" value="<?= htmlspecialchars($publication['title']) ?>" class="w-full p-2 rounded border border-gray-300">
            </div>

            <!-- ผู้พิมพ์ผลงาน -->
            <div>
                <label class="block mb-1 font-medium">ผู้พิมพ์ผลงาน</label>
                <select name="author" class="w-full p-2 rounded border border-gray-300">
                    <?php $users_result->data_seek(0); while($user = $users_result->fetch_assoc()): ?>
                        <option value="<?= $user['User_id'] ?>" <?= $user['User_id']==$publication['Author_id']?'selected':'' ?>>
                            <?= $user['first_name'].' '.$user['last_name'] ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <!-- ผู้ร่วมตีพิมพ์ -->
            <div>
                <label class="block mb-1 font-medium">ผู้ร่วมตีพิมพ์</label>
                <input type="text" name="co_author" class="w-full p-2 rounded border border-gray-300" 
                       value="<?= htmlspecialchars($co_author) ?>" placeholder="กรอกชื่อผู้ร่วมตีพิมพ์">
            </div>

            <!-- รายละเอียด -->
            <div>
                <label class="block mb-1 font-medium">รายละเอียดบทความ</label>
                <textarea name="manual" rows="4" class="w-full p-2 rounded border border-gray-300"><?= htmlspecialchars($publication['Manual']) ?></textarea>
            </div>

            <!-- แนบไฟล์ -->
            <div>
                <label class="block mb-2 font-medium text-gray-700">ไฟล์แนบ</label>

                <?php if (!empty($publication['file_path'])): ?>
                    <div class="mb-3 flex items-center gap-2 p-3 bg-green-50 border border-green-200 rounded-lg">
                        <span class="text-green-600">📂</span>
                        <p class="text-sm text-green-700">
                            ไฟล์ปัจจุบัน: 
                            <a href="<?= $publication['file_path'] ?>" target="_blank" class="underline hover:text-green-900">ดูไฟล์</a>
                        </p>
                    </div>
                <?php endif; ?>

                <div class="relative w-full">
                    <input type="file" name="fileUpload" id="fileUpload" class="hidden">
                    <label for="fileUpload" class="flex items-center justify-center px-4 py-2 bg-white border border-gray-300 rounded-lg shadow-sm cursor-pointer hover:bg-gray-50 transition">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4a1 1 0 011-1h8a1 1 0 011 1v12m-4 0v4m-4-4h8" />
                        </svg>
                        <span class="text-gray-700">เลือกไฟล์ใหม่</span>
                    </label>
                    <p id="fileName" class="mt-2 text-sm text-gray-500 italic"></p>
                </div>
            </div>

            <script>
                // แสดงชื่อไฟล์ที่เลือก
                document.getElementById("fileUpload").addEventListener("change", function() {
                    const fileName = this.files[0] ? this.files[0].name : "";
                    document.getElementById("fileName").innerText = fileName ? "📄 " + fileName : "";
                });
            </script>

            <!-- ปุ่ม -->
            <div class="flex justify-center gap-4 pt-4">
                <a href="list-of-published.php" class="bg-gray-300 px-6 py-2 rounded-lg">ย้อนกลับ</a>
                <button type="submit" class="bg-blue-500 text-white px-6 py-2 rounded-lg">บันทึก</button>
            </div>
        </form>
    </main>
</body>
</html>
