<?php
// === START: DEBUGGING AND ERROR REPORTING ===
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// === END: DEBUGGING AND ERROR REPORTING ===

// เริ่ม session
session_start();

// ตรวจสอบการเข้าสู่ระบบ: หากไม่มี session หรือ role ไม่ใช่ staff ให้ redirect ไปหน้า login
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header("Location: login-v1.php");
    exit();
}

// 1. กำหนดค่าและเชื่อมต่อฐานข้อมูล
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "group10";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    // หากเชื่อมต่อไม่ได้ จะแสดงข้อความข้อผิดพลาดที่ชัดเจน
    $db_error = "เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล: " . $conn->connect_error;
    // ไม่ die() ทันที แต่จะแสดงข้อผิดพลาดใน UI แทน
} else {
    $db_error = null;
}

// ดึงข้อมูลผู้ใช้ปัจจุบันและจำนวนงานที่รออนุมัติสำหรับ Header
$current_user_name = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];
$pending_count = 0;
$status_message = null;
$status_type = 'info';

// ------------------------------------
// 2. การจัดการ POST Request เพื่ออนุมัติ/ปฏิเสธ
// ------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$db_error) {
    if (isset($_POST['action'], $_POST['pub_id'])) {
        $pub_id = (int)$_POST['pub_id'];
        $action = $_POST['action'];
        
        // กำหนดสถานะใหม่
        $new_status = ($action === 'approve') ? 'approved' : 'rejected';

        // ป้องกัน SQL Injection โดยใช้ Prepared Statement
        $stmt = $conn->prepare("UPDATE Publication SET status = ? WHERE Pub_id = ?");
        
        // ตรวจสอบว่า prepare สำเร็จหรือไม่
        if ($stmt === false) {
             $status_message = "❌ เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL: " . $conn->error;
             $status_type = 'error';
        } else {
            $stmt->bind_param("si", $new_status, $pub_id);

            if ($stmt->execute()) {
                // Success: Redirect เพื่อป้องกันการ Submit ซ้ำ
                header("Location: approve.php?update_status=success&action={$action}");
                exit();
            } else {
                // Error: แสดงข้อผิดพลาดจากฐานข้อมูล
                $status_message = "❌ เกิดข้อผิดพลาดในการอัปเดตสถานะ: " . $stmt->error;
                $status_type = 'error';
            }
            $stmt->close();
        }
    }
}

// ตรวจสอบสถานะหลังการ Redirect
if (isset($_GET['update_status']) && $_GET['update_status'] === 'success') {
    $action = $_GET['action'] === 'approve' ? 'อนุมัติ' : 'ปฏิเสธ';
     $status_message = "✅ ทำการ{$action}ผลงานเรียบร้อยแล้ว";
     $status_type = 'success';
}

// ------------------------------------
// 3. ดึงรายการผลงานที่รออนุมัติ (status = 'waiting')
// ------------------------------------
$pending_publications = [];

if (!$db_error) {
    // ดึงจำนวนล่าสุดสำหรับ Notification Bell
    $sql_count = "SELECT COUNT(*) AS count FROM Publication WHERE status = 'waiting'";
    $result_count = $conn->query($sql_count);
    if ($result_count && $row = $result_count->fetch_assoc()) {
        $pending_count = (int)$row['count'];
    }

    // ดึงรายละเอียดผลงานที่รออนุมัติ พร้อมชื่อผู้ส่ง
    $sql = "SELECT p.*, u.first_name, u.last_name 
            FROM Publication p 
            JOIN User u ON p.Author_id = u.User_id 
            WHERE p.status = 'waiting' 
            ORDER BY p.Pub_id ASC";
            
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $pending_publications[] = $row;
        }
    } else {
        $db_error = "เกิดข้อผิดพลาดในการดึงข้อมูลผลงาน: " . $conn->error;
    }
}

// ปิดการเชื่อมต่อฐานข้อมูล
if (!$db_error) {
    // Note: การเชื่อมต่อจะถูกปิดที่นี่ (หรือก่อนหน้าหากมีข้อผิดพลาดร้ายแรง)
    // แต่เนื่องจากโค้ดเดิมไม่ได้ปิด ผมจะยังคงคอมเมนต์ไว้เพื่อให้โค้ดนี้ทำงานได้
    // $conn->close();
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบอนุมัติผลงานตีพิมพ์</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .top-header {
            background-color: #cce4f9; 
            padding: 1rem 2rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);
        }
        .right-icons > a {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 9999px; 
            color: #1d4ed8; 
        }
        .right-icons > a:hover {
            background-color: #dbeafe; 
        }
        .status-success { background-color: #d1fae5; color: #065f46; border-color: #34d399; }
        .status-error { background-color: #fee2e2; color: #991b1b; border-color: #f87171; }
        .status-warning { background-color: #fffbeb; color: #92400e; border-color: #fbbf24; }
    </style>
</head>
<body class="flex min-h-screen font-sans bg-gray-50">

<!-- Sidebar (เมนูย่อ) -->
<aside class="w-64 bg-white shadow-xl p-6 flex flex-col sticky top-0 h-screen z-20">
    <h2 class="text-2xl font-extrabold text-blue-800 mb-6 border-b pb-4">Staff Menu</h2>
    <nav class="w-full flex-grow">
        <!-- ปุ่มกลับสู่หน้าหลัก (staffPage.php) -->
        <a href="staffPage.php" class="flex items-center p-3 rounded-xl mb-3 text-white bg-blue-600 shadow-md font-semibold hover:bg-blue-700 transition-colors duration-150">
            <i class="fas fa-arrow-left w-5 h-5 mr-3"></i> กลับสู่หน้าหลัก
        </a>
        
        <div class="px-0 pt-4 border-t border-gray-200 mt-auto">
          <a href="logout.php" class="flex items-center p-3 rounded-lg text-red-500 hover:bg-red-50 transition-colors duration-150">
            <i class="fas fa-sign-out-alt w-5 h-5 mr-3"></i> ออกจากระบบ
          </a>
        </div>
        
    </nav>
</aside>

<div class="flex-1 flex flex-col">
    <!-- Header สไตล์ Home-PR -->
    <header class="top-header flex items-center justify-between sticky top-0 z-10">
        <h1 class="text-xl md:text-2xl font-bold text-gray-700 tracking-wide">
            <i class="fas fa-check-square mr-2 text-blue-800"></i> ระบบอนุมัติผลงานตีพิมพ์
        </h1>
        <!-- Notification Bell -->
        <div class="flex items-center space-x-4 right-icons">
            <span class="text-gray-700 font-medium hidden sm:block text-sm">
            <?= htmlspecialchars($current_user_name); ?>
            </span>
            <a href="approve.php" title="คำขออนุมัติผลงาน" class="relative bg-blue-100">
                <i class="fas fa-bell text-xl"></i>
                <?php if ($pending_count > 0): ?>
                    <span class="absolute -top-1 -right-1 bg-red-600 text-white text-xs font-bold rounded-full h-5 w-5 flex items-center justify-center ring-2 ring-white">
                        <?= $pending_count ?>
                    </span>
                <?php endif; ?>
            </a>
            <a href="#" title="โปรไฟล์ผู้ใช้งาน">
                <i class="fas fa-user-circle text-xl"></i>
            </a>
        </div>
    </header>

    <main class="flex-1 p-8">
        
        <!-- กล่องข้อความสถานะ/ข้อผิดพลาด (Status Message Box) -->
        <?php if ($db_error): ?>
             <div class="mb-6 p-4 rounded-lg shadow-md font-medium border-l-4 status-error border-red-500">
                <h2 class="font-bold text-lg">⚠️ เกิดข้อผิดพลาดร้ายแรง</h2>
                <p>โปรดตรวจสอบการตั้งค่าฐานข้อมูลในไฟล์ PHP ของคุณ: <?= htmlspecialchars($db_error); ?></p>
            </div>
        <?php elseif ($status_message): ?>
            <div class="mb-6 p-4 rounded-lg shadow-md font-medium border-l-4 
                <?= $status_type === 'success' ? 'status-success border-green-500' : 'status-error border-red-500' ?>">
                <?= $status_message; ?>
            </div>
        <?php endif; ?>

        <h1 class="text-4xl font-extrabold text-gray-800 mb-8">
            รายการผลงานที่รออนุมัติ (<?= $pending_count; ?> รายการ)
        </h1>
        
        <div class="space-y-6">
            <?php if (empty($pending_publications) && !$db_error): ?>
                <div class="bg-yellow-50 border-l-4 border-yellow-500 text-yellow-800 p-4 rounded-lg shadow-md">
                    <p class="font-bold"><i class="fas fa-info-circle mr-2"></i> ไม่มีผลงานที่รอการอนุมัติในขณะนี้</p>
                    <p class="text-sm">ทุกผลงานได้รับการตรวจสอบเรียบร้อยแล้ว</p>
                </div>
            <?php else: ?>
                <?php foreach ($pending_publications as $pub): ?>
                    <!-- Card สำหรับแต่ละผลงาน -->
                    <div class="bg-white p-6 rounded-xl shadow-lg border border-gray-200 hover:shadow-xl transition-shadow duration-300">
                        <div class="flex justify-between items-start mb-4 border-b pb-3">
                            <h2 class="text-xl font-bold text-blue-700 leading-snug">
                                <?= htmlspecialchars($pub['title'] ?? 'N/A'); ?>
                            </h2>
                            <span class="text-xs font-semibold px-3 py-1 bg-gray-200 text-gray-600 rounded-full">
                                ID: <?= $pub['Pub_id']; ?>
                            </span>
                        </div>
                        
                        <p class="text-gray-600 mb-2">
                            <span class="font-semibold text-gray-800"><i class="fas fa-user-edit mr-1"></i> ผู้ส่ง:</span> 
                            <?= htmlspecialchars($pub['first_name'] . ' ' . $pub['last_name'] ?? 'ไม่ระบุชื่อ'); ?>
                        </p>
                        <p class="text-gray-600 mb-4">
                            <span class="font-semibold text-gray-800"><i class="fas fa-calendar-alt mr-1"></i> ปี:</span> 
                            <?= htmlspecialchars($pub['publish_year'] ?? 'N/A'); ?>
                        </p>

                        <div class="flex space-x-4 pt-3 border-t">
                            <!-- ปุ่มอนุมัติ -->
                            <form method="POST" class="inline-block" onsubmit="return confirm('คุณแน่ใจที่จะอนุมัติผลงานนี้หรือไม่?');">
                                <input type="hidden" name="pub_id" value="<?= $pub['Pub_id']; ?>">
                                <input type="hidden" name="action" value="approve">
                                <button type="submit" class="bg-green-500 text-white px-5 py-2 rounded-lg font-semibold hover:bg-green-600 transition-colors shadow-md">
                                    <i class="fas fa-check-circle mr-1"></i> อนุมัติ
                                </button>
                            </form>

                            <!-- ปุ่มปฏิเสธ -->
                            <form method="POST" class="inline-block" onsubmit="return confirm('คุณแน่ใจที่จะปฏิเสธผลงานนี้หรือไม่?');">
                                <input type="hidden" name="pub_id" value="<?= $pub['Pub_id']; ?>">
                                <input type="hidden" name="action" value="reject">
                                <button type="submit" class="bg-red-500 text-white px-5 py-2 rounded-lg font-semibold hover:bg-red-600 transition-colors shadow-md">
                                    <i class="fas fa-times-circle mr-1"></i> ปฏิเสธ
                                </button>
                            </form>
                            
                            <!-- **การแก้ไข: ปุ่มดูรายละเอียด เปลี่ยนเป็นลิงก์เปิด PDF Viewer ในแท็บใหม่** -->
                            <?php 
                                $file_path = htmlspecialchars($pub['file_path'] ?? '#');
                                // ตรวจสอบว่ามี file_path และไม่เป็นค่าว่าง
                                $is_file_available = !empty($pub['file_path']);
                                $link_class = $is_file_available ? 
                                    'bg-gray-300 text-gray-800 hover:bg-gray-400 cursor-pointer' : 
                                    'bg-gray-100 text-gray-500 cursor-not-allowed';
                                $link_href = $is_file_available ? $file_path : 'javascript:void(0)';
                                $link_target = $is_file_available ? '_blank' : '_self';
                            ?>
                            <a href="<?= $link_href; ?>" 
                                target="<?= $link_target; ?>"
                                class="<?= $link_class; ?> px-5 py-2 rounded-lg font-semibold transition-colors shadow-md inline-flex items-center"
                                title="<?= $is_file_available ? 'คลิกเพื่อเปิดไฟล์เอกสารในแท็บใหม่' : 'ไม่มีไฟล์เอกสารแนบ'; ?>">
                                <i class="fas fa-eye mr-1"></i> ดูเอกสาร (PDF)
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

    <footer class="p-4 bg-gray-200 text-center text-gray-600 text-sm mt-auto">
        &copy; <?php echo date("Y"); ?> ระบบจัดการการตีพิมพ์ผลงาน | มหาวิทยาลัยสงขลานครินทร์ (PSU)
    </footer>
</div>

</body>
</html>
<?php
// ปิดการเชื่อมต่อฐานข้อมูล
if (!$db_error && isset($conn) && $conn->ping()) {
    $conn->close();
}
?>
