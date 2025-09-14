<?php
// -------------------- DB Connection --------------------
$pdo = new PDO("mysql:host=localhost;dbname=group10;charset=utf8", "root", "");

// สมมติเลือกอาจารย์คนแรก
$teacherId = 1;

// ดึงข้อมูลอาจารย์
$stmt = $pdo->prepare("SELECT first_name, last_name, Department, role FROM User WHERE User_id = ?");
$stmt->execute([$teacherId]);
$teacher = $stmt->fetch();

// นับจำนวนผลงานตีพิมพ์
$stmtPub = $pdo->prepare("SELECT COUNT(*) FROM Publication WHERE Author_id = ?");
$stmtPub->execute([$teacherId]);
$pubCount = $stmtPub->fetchColumn();

// ดึงผลงานล่าสุด 1 ชิ้น
$stmtWork = $pdo->prepare("SELECT title, publish_year FROM Publication WHERE Author_id = ? ORDER BY publish_year DESC LIMIT 1");
$stmtWork->execute([$teacherId]);
$work = $stmtWork->fetch();
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ข้อมูลอาจารย์</title>
<script src="https://cdn.tailwindcss.com"></script>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
</head>
<body class="bg-white font-sans text-gray-800">

<!-- Header -->
<header class="flex items-center justify-between p-3 bg-blue-100">
    <button class="text-2xl">☰</button>
    <h1 class="text-lg font-semibold">ข้อมูลอาจารย์</h1>
    <div class="text-2xl">👤</div>
</header>
<br></br>
<button class="flex items-center space-x-2 px-4 py-2 bg-blue-200 rounded-lg shadow hover:bg-blue-300 mb-4">
        <span></span>
        <span><i class="fa fa-arrow-left"></i> ย้อนกลับ </span>
      </button>
<!-- Container -->
<div class="p-6 flex flex-col md:flex-row gap-6">

    <!-- Left Panel -->
    <div class="md:w-1/2 flex flex-col gap-4">
        <div class="flex items-center gap-2">
            
            <span class="font-semibold">
                <?php echo htmlspecialchars($teacher['first_name'] . " " . $teacher['last_name']); ?>
            </span>
        </div>
        <div class="w-full h-72 bg-white border rounded-lg flex flex-col items-center justify-center text-center">
            <div class="text-lg font-semibold">
                <?php echo htmlspecialchars($teacher['first_name'] . " " . $teacher['last_name']); ?>
            </div>
            <div class="text-sm text-gray-600">
                <?php echo htmlspecialchars($teacher['Department']); ?>
            </div>
            <div class="text-sm text-gray-600">
                <?php echo htmlspecialchars($teacher['role']); ?>
            </div>
        </div>
    </div>

    <!-- Right Panel -->
    <div class="md:w-1/2 flex flex-col gap-4">

        <!-- Summary -->
        <div class="bg-blue-200 rounded-lg p-4">
            <div>ผลงานตีพิมพ์ : <?php echo $pubCount; ?></div>
        </div>

        <!-- Dropdown -->
        <form method="get" class="flex flex-col gap-3">
            <select class="p-2 rounded border">
                <option>ผลงานตีพิมพ์</option>
            </select>
            <div class="bg-blue-50 p-3 rounded">
                <?php 
                if ($work) {
                    echo "ชื่อผลงาน: " . htmlspecialchars($work['title']) . " - " . $work['publish_year'];
                } else {
                    echo "ยังไม่มีข้อมูลผลงาน";
                }
                ?>
            </div>
        </form>
    </div>
</div>
</body>
</html>
