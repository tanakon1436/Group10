<?php
// ================= เชื่อมต่อฐานข้อมูล =================
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "group10";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ================= ลบผลงานถ้ามีการร้องขอ =================
if (isset($_GET['delete_pub_id'])) {
    $delete_id = intval($_GET['delete_pub_id']);
    $sql_delete = "DELETE FROM Publication WHERE Pub_id=$delete_id";
    if ($conn->query($sql_delete)) {
        header("Location: list-of-published.php"); // รีเฟรชหน้า
        exit;
    } else {
        echo "<p>❌ ลบผลงานไม่สำเร็จ: " . $conn->error . "</p>";
    }
}

// โหลดผลงานทั้งหมด
$sql = "SELECT p.Pub_id, p.title, p.status, u.first_name, u.last_name 
        FROM Publication p
        LEFT JOIN User u ON p.Author_id = u.User_id
        ORDER BY p.Pub_id ASC";
$result = $conn->query($sql);

// ฟังก์ชันแปลงสถานะเป็นป้ายสี
function renderStatus($status) {
    switch ($status) {
        case 'อนุมัติแล้ว':
            return '<span class="bg-green-500 text-white px-3 py-1 rounded-lg">อนุมัติแล้ว</span>';
        case 'ปฏิเสธ':
            return '<span class="bg-red-500 text-white px-3 py-1 rounded-lg">ปฏิเสธ</span>';
        default:
            return '<span class="bg-yellow-500 text-white px-3 py-1 rounded-lg">รออนุมัติ</span>';
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ระบบ จัดการการตีพิมพ์ผลงานของอาจารย์</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        function confirmDelete(pub_id) {
            if (confirm("คุณแน่ใจหรือไม่ว่าต้องการลบผลงานนี้?")) {
                // ส่งค่า delete_pub_id กลับมาที่ไฟล์เดียวกัน
                window.location.href = "list-of-published.php?delete_pub_id=" + pub_id;
            }
        }

        function toggleSidebar() {
            document.getElementById("sidebar").classList.toggle("-translate-x-full");
        }

        // โหลดชื่อผู้ใช้จาก localStorage
        window.onload = function() {
            const username = localStorage.getItem("username") || "ผศ.XXX XXXXX";
            document.getElementById("username").innerText = username;
        }
    </script>
</head>
<body class="bg-white font-sans">
    <!-- Header -->
    <header class="flex items-center justify-between bg-blue-100 p-4 shadow fixed top-0 left-0 w-full z-40">
        <div class="flex items-center gap-3">
            <button class="text-2xl" onclick="toggleSidebar()">&#9776;</button>
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

    <!-- เนื้อหาหน้าเว็บ -->
    <main class="p-6 pt-20">
        <div class="flex items-center justify-center mb-6">
            <h2 class="text-xl font-semibold">รายการผลงานตีพิมพ์</h2>
        </div>

        <?php if ($result && $result->num_rows > 0): ?>
            <?php $i = 1; while($row = $result->fetch_assoc()): ?>
                <div class="bg-blue-100 p-4 rounded-xl mb-4 flex justify-between items-center">
                    <div>
                        <p class="font-semibold">ผลงานที่ <?= $i++ ?></p>
                        <p>ชื่อผลงานวิจัย <?= htmlspecialchars($row['title']) ?></p>
                        <p class="text-sm text-gray-600">ผู้พิมพ์: <?= htmlspecialchars($row['first_name'].' '.$row['last_name']) ?></p>
                    </div>
                    <div class="flex items-center gap-3">
                        <?= renderStatus($row['status']) ?>
                        <button type="button" onclick="confirmDelete(<?= $row['Pub_id'] ?>)" class="bg-gray-200 p-2 rounded-lg">➖</button>
                        <a href="edit-of-published.php?Pub_id=<?= $row['Pub_id'] ?>" class="bg-gray-200 p-2 rounded-lg">✏️</a>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p class="text-center text-gray-500">ไม่มีผลงานตีพิมพ์</p>
        <?php endif; ?>
    </main>
</body>
</html>
