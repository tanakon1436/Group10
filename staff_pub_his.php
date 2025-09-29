<?php
// === START: DEBUGGING AND ERROR REPORTING (ช่วยให้เห็นข้อผิดพลาด PHP ที่ซ่อนอยู่) ===
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

// 1. เชื่อมฐานข้อมูล
$conn = new mysqli("localhost","root","","group10");
if($conn->connect_error) {
    // หากเชื่อมต่อไม่ได้
    $db_error = "เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล: " . $conn->connect_error;
    $pending_count = 0;
} else {
    $db_error = null;
}

// --- กำหนดค่าคงที่และตัวแปรเริ่มต้น ---
$current_user_name = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];
$search_query = $_GET['search'] ?? '';
$history_records = [];

// 2. ดึงจำนวนผลงานที่รออนุมัติสำหรับแสดงใน Notification Bell
$pending_count = 0;
if (!$db_error) {
    $sql_count = "SELECT COUNT(*) AS count FROM Publication WHERE status = 'waiting'";
    $result_count = $conn->query($sql_count);
    if ($result_count && $row = $result_count->fetch_assoc()) {
        $pending_count = (int)$row['count'];
    }
}

// 3. ดึงข้อมูลประวัติการแก้ไข
if (!$db_error) {
    // SQL: ดึงข้อมูลจาก PublicationHistory และ Join กับ Publication (ชื่อผลงาน) และ User (ชื่อผู้แก้ไข)
    $sql = "
        SELECT 
            ph.History_id, 
            ph.change_detail, 
            ph.edit_date,
            p.title AS publication_title,
            e.first_name AS editor_first_name,
            e.last_name AS editor_last_name
        FROM PublicationHistory ph
        JOIN Publication p ON ph.Pub_id = p.Pub_id
        JOIN User e ON ph.Edited_by = e.User_id
        WHERE 
            p.title LIKE ? OR 
            ph.change_detail LIKE ? OR 
            e.first_name LIKE ? OR 
            e.last_name LIKE ?
        ORDER BY ph.edit_date DESC"; // เรียงจากล่าสุดไปเก่าสุด

    $stmt = $conn->prepare($sql);
    
    if ($stmt) {
        $search_param = "%" . $search_query . "%";
        // Bind parameters 4 ครั้งสำหรับ title, change_detail, editor_first_name, editor_last_name
        $stmt->bind_param("ssss", $search_param, $search_param, $search_param, $search_param);
        
        if ($stmt->execute()) {
            $results = $stmt->get_result();
            while ($row = $results->fetch_assoc()) {
                $history_records[] = $row;
            }
        }
        $stmt->close();
    }
}

?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ประวัติการแก้ไขผลงาน - ระบบจัดการการตีพิมพ์</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

    <style>
        /* กำหนดสีหลักของธีมใหม่ (สีน้ำเงิน) */
        .text-theme { color: #1d4ed8; } /* blue-700 */
        .bg-theme-light { background-color: #eff6ff; } /* blue-50 */
        .border-theme-light { border-color: #bfdbfe; } /* blue-200 */
        .hover-bg-theme { background-color: #dbeafe; } /* blue-100 */
        .top-header {
            background-color: #cce4f9; /* สีฟ้าอ่อนตามที่เคยใช้ในหน้า login */
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
        .table-custom th {
            background-color: #1d4ed8; /* blue-700 */
            color: white;
            padding: 1rem;
            text-align: left;
        }
        .table-custom tr:nth-child(even) {
            background-color: #f3f4f6; /* gray-100 */
        }
        .table-custom td {
            padding: 1rem;
            border-bottom: 1px solid #e5e7eb; /* gray-200 */
        }
    </style>
</head>
<body class="flex min-h-screen font-sans bg-gray-50">

<aside class="w-64 bg-white shadow-xl p-6 flex flex-col sticky top-0 h-screen z-10">
    <h2 class="text-2xl font-extrabold text-blue-800 mb-6 border-b pb-4">Staff Menu</h2>
    <nav class="w-full flex-grow">
        <a href="staffPage.php" class="flex items-center p-3 rounded-xl mb-3 text-gray-700 hover:bg-blue-50 hover:text-blue-700 transition-colors duration-150">
            <i class="fas fa-search w-5 h-5 mr-3"></i> ค้นหาผลงานตีพิมพ์
        </a>
        <a href="approve.php" class="flex items-center p-3 rounded-xl mb-3 text-gray-700 hover:bg-blue-50 hover:text-blue-700 transition-colors duration-150 relative">
            <i class="fas fa-bell w-5 h-5 mr-3"></i> อนุมัติผลงาน
            <?php if ($pending_count > 0): ?>
                <span class="absolute right-3 top-3 bg-red-600 text-white text-xs font-bold rounded-full h-5 w-5 flex items-center justify-center ring-2 ring-white">
                    <?= $pending_count ?>
                </span>
            <?php endif; ?>
        </a>
        <a href="Staff_manage.php" class="flex items-center p-3 rounded-xl text-gray-700 hover:bg-blue-50 hover:text-blue-700 transition-colors duration-150">
            <i class="fas fa-edit w-5 h-5 mr-3"></i> แก้ไขข้อมูลอาจารย์
        </a>
        <a href="staff_addTeacher.php" class="flex items-center p-3 rounded-xl text-gray-700 hover:bg-blue-50 hover:text-blue-700 transition-colors duration-150">
            <i class="fas fa-user-plus w-5 h-5 mr-3"></i> เพิ่มข้อมูลอาจารย์/เจ้าหน้าที่
        </a>
        <a href="contact_teacher.php" class="flex items-center p-3 rounded-xl text-gray-700 hover:bg-blue-50 hover:text-blue-700 transition-colors duration-150">
        <i class="fas fa-comments w-5 h-5 mr-3"></i> ติดต่ออาจารย์
        </a>
        <a href="dowload_report.php" class="flex items-center p-3 rounded-xl text-gray-700 hover:bg-blue-50 hover:text-blue-700 transition-colors duration-150">
            <i class="fas fa-file-alt w-5 h-5 mr-3"></i> รายงานผล/ดาวน์โหลด PDF
        </a>
        <a href="staff_pub_his.php" class="flex items-center p-3 rounded-xl mb-3 text-white bg-blue-600 shadow-md hover:bg-blue-700 font-semibold transition-colors duration-150">
            <i class="fas fa-history w-5 h-5 mr-3"></i> ประวัติการแก้ไขผลงาน
        </a>
        <a href="usermannual.php" class="flex items-center p-3 rounded-xl text-gray-700 hover:bg-blue-50 hover:text-blue-700 transition-colors duration-150">
            <i class="fas fa-book w-5 h-5 mr-3"></i> คู่มือการใช้งาน
        </a>
        
        <!-- ปุ่มออกจากระบบที่ย้ายมาอยู่ใน Sidebar -->
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
            <i class="fas fa-history mr-2 text-blue-800"></i> ประวัติการแก้ไขผลงาน (Staff)
        </h1>
        <!-- Notification Badge และ User Profile -->
        <div class="flex items-center space-x-4 right-icons">
            <span class="text-gray-700 font-medium hidden sm:block text-sm">
            <?= htmlspecialchars($current_user_name); ?>
            </span>
            <a href="approve.php" title="คำขออนุมัติผลงาน" class="relative">
                <i class="fas fa-bell text-xl"></i>
                <?php if ($pending_count > 0): ?>
                    <span class="absolute -top-1 -right-1 bg-red-600 text-white text-xs font-bold rounded-full h-5 w-5 flex items-center justify-center ring-2 ring-white">
                        <?= $pending_count ?>
                    </span>
                <?php endif; ?>
            </a>
            <a href="staff_edit_profile.php" title="โปรไฟล์ผู้ใช้งาน">
                <i class="fas fa-user-circle text-xl"></i>
            </a>
        </div>
    </header>

    <main class="p-8">
        <section class="bg-white p-6 rounded-2xl shadow-2xl">
            <h2 class="text-2xl font-bold text-gray-800 mb-6 border-b pb-3 flex items-center">
                 <i class="fas fa-list-alt mr-2 text-blue-600"></i> บันทึกประวัติการแก้ไขผลงาน
            </h2>

            <?php if ($db_error): ?>
                <div class="mb-6 p-4 rounded-lg shadow-md font-medium border-l-4 border-red-500 bg-red-50 text-red-800">
                    <h2 class="font-bold text-lg">⚠️ เกิดข้อผิดพลาดร้ายแรง</h2>
                    <p>โปรดตรวจสอบการตั้งค่าฐานข้อมูล: <?= htmlspecialchars($db_error); ?></p>
                </div>
            <?php else: ?>
                
                <!-- Search Form -->
                <form method="GET" action="staff_pub_his.php" class="mb-6">
                    <div class="flex items-center">
                        <input type="text" name="search" placeholder="ค้นหาจากชื่อผลงาน, ผู้แก้ไข หรือรายละเอียดการเปลี่ยนแปลง..." 
                               value="<?= htmlspecialchars($search_query); ?>"
                               class="flex-grow border border-gray-300 rounded-l-full p-4 px-6 text-base focus:ring-4 focus:ring-blue-300 focus:border-blue-500 transition-all shadow-inner">
                        <button type="submit" class="bg-blue-600 text-white p-4 px-8 rounded-r-full font-bold hover:bg-blue-700 transition-colors duration-200 text-base shadow-lg">
                            <i class="fas fa-search"></i> ค้นหา
                        </button>
                    </div>
                </form>

                <!-- History Table -->
                <div class="overflow-x-auto rounded-xl shadow-lg border border-gray-200">
                    <table class="min-w-full divide-y divide-gray-200 table-custom">
                        <thead>
                            <tr>
                                <th class="w-1/12 rounded-tl-xl">ID</th>
                                <th class="w-4/12">ชื่อผลงาน</th>
                                <th class="w-4/12">รายละเอียดการเปลี่ยนแปลง</th>
                                <th class="w-2/12">ผู้แก้ไข</th>
                                <th class="w-1/12 rounded-tr-xl">วันที่แก้ไข</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (!empty($history_records)): ?>
                                <?php foreach ($history_records as $record): ?>
                                    <tr>
                                        <td class="font-mono text-sm text-gray-600"><?= htmlspecialchars($record['History_id']); ?></td>
                                        <td class="font-semibold text-blue-800"><?= htmlspecialchars($record['publication_title']); ?></td>
                                        <td>
                                            <p class="text-sm text-gray-700 whitespace-pre-wrap leading-snug">
                                                <?= htmlspecialchars($record['change_detail']); ?>
                                            </p>
                                        </td>
                                        <td>
                                            <i class="fas fa-user-tag text-blue-500 mr-1"></i>
                                            <?= htmlspecialchars($record['editor_first_name'] . ' ' . $record['editor_last_name']); ?>
                                        </td>
                                        <td>
                                            <span class="text-sm text-gray-500">
                                                <?= date('d/m/Y H:i', strtotime($record['edit_date'])); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center py-8 text-gray-500 font-medium">
                                        <i class="fas fa-info-circle mr-2"></i> 
                                        <?php if ($search_query): ?>
                                            ไม่พบประวัติการแก้ไขที่ตรงกับคำค้นหา "<?= htmlspecialchars($search_query); ?>"
                                        <?php else: ?>
                                            ไม่พบประวัติการแก้ไขผลงานในระบบ
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            <?php endif; ?>
        </section>
    </main>

    <footer class="p-4 bg-gray-200 text-center text-gray-600 text-sm mt-auto">
        &copy; <?php echo date("Y"); ?> ระบบจัดการการตีพิมพ์ผลงาน | มหาวิทยาลัยสงขลานครินทร์ (PSU)
    </footer>
</div>

<?php 
// ปิดการเชื่อมต่อฐานข้อมูลเมื่อสิ้นสุดการทำงานของสคริปต์
if (!$db_error && isset($conn)) {
    $conn->close(); 
}
?>
</body>
</html>
