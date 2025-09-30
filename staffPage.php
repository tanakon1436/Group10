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
// NOTE: ข้อมูลเชื่อมต่อฐานข้อมูลถูกกำหนดตายตัวตามที่ท่านให้มา
$conn = new mysqli("localhost","root","","group10");
if($conn->connect_error) {
    // หากเชื่อมต่อไม่ได้
    $db_error = "เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล: " . $conn->connect_error;
    $pending_count = 0;
} else {
    $db_error = null;
}

// --- กำหนดค่าคงที่และตัวแปรเริ่มต้น ---
$status_message = null; 
$status_type = 'info'; 

// กำหนดการแมปประเภทผลงานสำหรับ Dropdown (ภาษาอังกฤษ => ภาษาไทย)
$publication_types = [
    'all' => '-- ทุกประเภท --',
    'Journal' => 'บทความวารสาร',
    'Conference' => 'นำเสนอในการประชุม',
    'Thesis' => 'วิทยานิพนธ์/ภาคนิพนธ์',
    'Other' => 'อื่นๆ',
];

// ดึงข้อมูลผู้ใช้ที่เข้าสู่ระบบจริงจาก SESSION
$current_user_name = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];

// 2. ดึงจำนวนผลงานที่รออนุมัติสำหรับแสดงใน Notification Bell
$pending_count = 0;
if (!$db_error) {
    // ใช้ status = 'waiting' ตาม SQL Dump ล่าสุด
    $sql_count = "SELECT COUNT(*) AS count FROM Publication WHERE status = 'waiting'";
    $result_count = $conn->query($sql_count);
    if ($result_count && $row = $result_count->fetch_assoc()) {
        $pending_count = (int)$row['count'];
    }
}


// 3. การจัดการการค้นหาและข้อมูลตัวกรอง (Filters)
$search_query = $_GET['search'] ?? '';
// NEW: รับค่าตัวกรองประเภทผลงาน (pub_type)
$selected_type = $_GET['pub_type'] ?? 'all'; 

// === เริ่มดึงข้อมูลจริงสำหรับตัวกรอง (Quick Filters) ===
// ข้อมูลเหล่านี้จะถูกดึงมาเสมอเพื่อให้ Quick Filters แสดงผลทันที

// ดึงข้อมูลอาจารย์ที่มีผลงานจริง พร้อมนับจำนวนผลงาน (Top 5 Authors)
$filter_authors = [];
if (!$db_error) {
    $sql_authors = "
        SELECT 
            u.User_id, 
            u.first_name, 
            u.last_name, 
            COUNT(p.Pub_id) as count
        FROM User u
        JOIN Publication p ON u.User_id = p.Author_id
        GROUP BY u.User_id, u.first_name, u.last_name
        ORDER BY count DESC
        LIMIT 5";

    $authors_res = $conn->query($sql_authors);
    if ($authors_res) {
        while($author = $authors_res->fetch_assoc()){
            $filter_authors[] = [
                'name' => $author['first_name'].' '.$author['last_name'], 
                'first_name_only' => $author['first_name'], 
                'count' => (int)$author['count'], 
                'id' => $author['User_id']
            ];
        }
    }
}

// ดึงชื่อผลงานวิจัยยอดนิยมจริง (Top 5 Latest Titles)
$filter_works = [];
if (!$db_error) {
    // ดึง 5 ผลงานล่าสุดจากตาราง Publication
    $sql_works = "SELECT title FROM Publication ORDER BY Pub_id DESC LIMIT 5";
    $works_res = $conn->query($sql_works);
    if ($works_res) {
        while($work = $works_res->fetch_assoc()){
            $filter_works[] = $work['title'];
        }
    }
}


// ดึงปีที่เผยแพร่จริงและจำนวนผลงานในแต่ละปี (Top 5 Latest Years)
$filter_years = [];
if (!$db_error) {
    $sql_years = "
        SELECT publish_year, COUNT(Pub_id) as count
        FROM Publication 
        WHERE publish_year IS NOT NULL 
        GROUP BY publish_year 
        ORDER BY publish_year DESC 
        LIMIT 5";

    $years_res = $conn->query($sql_years);
    if ($years_res) {
        while($year = $years_res->fetch_assoc()){
            $filter_years[] = [
                'range' => $year['publish_year'], 
                'count' => (int)$year['count']
            ];
        }
    }
}

// === สิ้นสุดการดึงข้อมูลจริงสำหรับตัวกรอง ===

// 4. การจัดการ POST Request เพื่ออนุมัติ/ปฏิเสธ (หากฟอร์มนี้ถูกใช้ร่วมกับหน้า approve)
// โค้ดส่วนนี้ไม่ได้ถูกเรียกใช้จากหน้านี้โดยตรง แต่เก็บไว้เพื่อความสมบูรณ์
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$db_error) {
    if (isset($_POST['action'], $_POST['pub_id'])) {
        $pub_id = (int)$_POST['pub_id'];
        $action = $_POST['action'];
        
        // กำหนดสถานะใหม่
        $new_status = ($action === 'approve') ? 'approved' : 'rejected';

        // ป้องกัน SQL Injection โดยใช้ Prepared Statement
        $stmt = $conn->prepare("UPDATE Publication SET status = ? WHERE Pub_id = ?");
        
        if ($stmt === false) {
             $status_message = "❌ เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL: " . $conn->error;
             $status_type = 'error';
        } else {
            $stmt->bind_param("si", $new_status, $pub_id);

            if ($stmt->execute()) {
                // Success: Redirect ไปหน้า approve.php เพื่อแสดงผลลัพธ์
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

// =================================================================
// 5. โค้ดส่วนหลัก: การจัดการการค้นหาและดึงผลงาน (ถูกรันเสมอ)
// =================================================================
$search_results = [];
if (!$db_error) {
    
    $where_clauses = [];
    $params = [];
    $types = '';
    
    // 1. Search Query Condition (ใช้เงื่อนไขค้นหาก็ต่อเมื่อมีคำค้นหา)
    if (!empty($search_query)) {
        $search_param = "%" . $search_query . "%";
        $is_year = (bool) preg_match('/^\d{4}$/', $search_query);

        // เงื่อนไขค้นหาใน Title, first_name, last_name, และ publish_year
        $search_condition = " (p.title LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? ";
        $params[] = $search_param; $types .= 's';
        $params[] = $search_param; $types .= 's';
        $params[] = $search_param; $types .= 's';
        
        if ($is_year) {
            $search_condition .= " OR p.publish_year = ? ";
            $params[] = $search_query; $types .= 's';
        }
        $search_condition .= ") ";
        $where_clauses[] = $search_condition;
    }

    // 2. Type Filter Condition (ใช้ตัวกรองประเภทก็ต่อเมื่อไม่ได้เลือก 'all')
    if ($selected_type !== 'all' && array_key_exists($selected_type, $publication_types)) {
        $where_clauses[] = " p.type = ? ";
        $params[] = $selected_type; $types .= 's';
    }

    // สร้างส่วน WHERE ของ SQL
    $where_sql = count($where_clauses) > 0 ? " WHERE " . implode(" AND ", $where_clauses) : "";

    // SQL: ดึงข้อมูลผลงาน
    $sql_search = "
        SELECT 
            p.Pub_id,
            p.title,
            p.type,
            p.publish_year,
            p.status,
            p.file_path,
            u.first_name,
            u.last_name
        FROM Publication p
        JOIN User u ON p.Author_id = u.User_id
        " . $where_sql . " 
        ORDER BY p.publish_year DESC, p.title ASC";

    $stmt_search = $conn->prepare($sql_search);

    if ($stmt_search) {
        // Bind parameters dynamically
        if (!empty($params)) {
             $stmt_search->bind_param($types, ...$params); 
        }

        if ($stmt_search->execute()) {
            $results = $stmt_search->get_result();
            while ($row = $results->fetch_assoc()) {
                $search_results[] = $row;
            }
        } else {
             $status_message = "❌ เกิดข้อผิดพลาดในการรันคำสั่งค้นหา: " . $stmt_search->error;
             $status_type = 'error';
        }
        $stmt_search->close();
    } else {
        $status_message = "❌ เกิดข้อผิดพลาดในการเตรียมคำสั่งค้นหา: " . $conn->error;
        $status_type = 'error';
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบจัดการการตีพิมพ์ของเจ้าหน้าที่</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

    <style>
        /* กำหนดสีหลักของธีมใหม่ (สีน้ำเงิน) */
        .text-theme { color: #1d4ed8; } /* blue-700 */
        .bg-theme-light { background-color: #eff6ff; } /* blue-50 */
        .border-theme-light { border-color: #bfdbfe; } /* blue-200 */
        .hover-bg-theme { background-color: #dbeafe; } /* blue-100 */

        /* สไตล์สำหรับ Header ที่คล้าย Home-PR */
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
        
        .status-success { background-color: #d1fae5; color: #065f46; border-color: #34d399; }
        .status-error { background-color: #fee2e2; color: #991b1b; border-color: #f87171; }
        
        /* สไตล์สำหรับ PDF Modal */
        .pdf-modal {
            z-index: 9999; /* ให้ Pop-up อยู่ด้านบนสุด */
            backdrop-filter: blur(5px);
        }
        #pdfIframe {
            border: none;
        }
    </style>
</head>
<body class="flex min-h-screen font-sans bg-gray-50">

<aside class="w-64 bg-white shadow-xl p-6 flex flex-col sticky top-0 h-screen z-10">
    <h2 class="text-2xl font-extrabold text-blue-800 mb-6 border-b pb-4">Staff Menu</h2>
    <nav class="w-full flex-grow">
        <a href="staffPage.php" class="flex items-center p-3 rounded-xl mb-3 text-white bg-blue-600 shadow-md hover:bg-blue-700 font-semibold transition-colors duration-150">
            <i class="fas fa-search w-5 h-5 mr-3"></i> ค้นหาผลงานตีพิมพ์
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
            <i class="fas fa-tools mr-2 text-blue-800"></i> ระบบจัดการผลงาน (Staff)
        </h1>
        <!-- เพิ่ม Notification Badge สำหรับการอนุมัติผลงาน -->
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
        
        <section class="bg-white p-6 rounded-2xl shadow-2xl">
            <form method="GET" action="staffPage.php" id="mainSearchForm">
                <div class="flex items-center mb-6">
                    <input type="text" name="search" placeholder="ค้นหาชื่อผลงาน, ชื่ออาจารย์, หรือปี..." 
                           value="<?= htmlspecialchars($search_query); ?>"
                           class="flex-grow border border-gray-300 rounded-l-full p-4 px-6 text-lg focus:ring-4 focus:ring-blue-300 focus:border-blue-500 transition-all shadow-inner">
                    <button type="submit" class="bg-blue-600 text-white p-4 px-8 rounded-r-full font-bold hover:bg-blue-700 transition-colors duration-200 text-lg shadow-lg">
                        <i class="fas fa-search"></i> ค้นหา
                    </button>
                    <!-- Hidden input เพื่อส่งค่า pub_type ไปด้วยในการค้นหาหลัก -->
                    <input type="hidden" name="pub_type" value="<?= htmlspecialchars($selected_type); ?>">
                </div>
            </form>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mt-8">
                <!-- ชื่อผลงาน (ดึงข้อมูลจริง) - คลิกเพื่อค้นหา -->
                <div class="bg-theme-light p-6 rounded-xl border border-blue-300 shadow-lg">
                    <h3 class="text-xl font-bold text-blue-700 mb-3 pb-3 border-b border-blue-200 flex items-center">
                        <i class="fas fa-book-open mr-2"></i> ชื่อผลงานล่าสุด
                    </h3>
                    <div class="space-y-3">
                        <?php if (empty($filter_works)): ?>
                            <p class="text-gray-500 text-sm italic">ไม่มีข้อมูลผลงานในระบบ</p>
                        <?php else: ?>
                            <?php foreach ($filter_works as $work): ?>
                                <!-- เพิ่ม onclick เพื่อให้คลิกแล้วค้นหา -->
                                <div class="text-gray-700 text-base leading-snug cursor-pointer p-2 rounded-lg hover:bg-blue-100 transition-colors duration-150"
                                     onclick="document.getElementsByName('search')[0].value='<?= htmlspecialchars(addslashes($work)); ?>'; document.getElementById('mainSearchForm').submit();">
                                    <span class="truncate block"><?= htmlspecialchars($work); ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ชื่ออาจารย์ (ดึงข้อมูลจริง) - คลิกเพื่อค้นหา และแก้ไข CSS ให้ตัวนับอยู่บรรทัดเดียวกัน -->
                <div class="bg-theme-light p-6 rounded-xl border border-blue-300 shadow-lg">
                    <h3 class="text-xl font-bold text-blue-700 mb-3 pb-3 border-b border-blue-200 flex items-center">
                        <i class="fas fa-user-tie mr-2"></i> อาจารย์ที่มีผลงาน
                    </h3>
                    <div class="space-y-3">
                        <?php if (empty($filter_authors)): ?>
                            <p class="text-gray-500 text-sm italic">ไม่มีข้อมูลอาจารย์ที่มีผลงาน</p>
                        <?php else: ?>
                            <?php foreach ($filter_authors as $author): ?>
                                <!-- แก้ไข: ใช้ 'first_name_only' ในการค้นหาตามคำขอของผู้ใช้ -->
                                <div class="flex justify-between items-center flex-nowrap text-gray-700 cursor-pointer p-2 rounded-lg hover:bg-blue-100 transition-colors duration-150"
                                     onclick="document.getElementsByName('search')[0].value='<?= htmlspecialchars(addslashes($author['first_name_only'])); ?>'; document.getElementById('mainSearchForm').submit();">
                                    <span class="font-medium truncate"><?= htmlspecialchars($author['name']); ?></span>
                                    <span class="bg-blue-600 text-white text-xs font-bold px-3 py-1 rounded-full shadow-md ml-2 flex-shrink-0"><?= $author['count']; ?> ผลงาน</span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ปีที่พิมพ์เผยแพร่ (ดึงข้อมูลจริง) - คลิกเพื่อค้นหา -->
                <div class="bg-theme-light p-6 rounded-xl border border-blue-300 shadow-lg">
                    <h3 class="text-xl font-bold text-blue-700 mb-3 pb-3 border-b border-blue-200 flex items-center">
                        <i class="fas fa-calendar-alt mr-2"></i> ปีที่เผยแพร่
                    </h3>
                    <div class="space-y-3">
                         <?php if (empty($filter_years)): ?>
                            <p class="text-gray-500 text-sm italic">ไม่มีข้อมูลปีเผยแพร่</p>
                        <?php else: ?>
                            <?php foreach ($filter_years as $year): ?>
                                <!-- เพิ่ม onclick เพื่อให้คลิกแล้วค้นหา -->
                                <div class="flex justify-between items-center flex-nowrap text-gray-700 cursor-pointer p-2 rounded-lg hover:bg-blue-100 transition-colors duration-150"
                                     onclick="document.getElementsByName('search')[0].value='<?= htmlspecialchars(addslashes($year['range'])); ?>'; document.getElementById('mainSearchForm').submit();">
                                    <span class="font-medium"><?= htmlspecialchars($year['range']); ?></span>
                                    <span class="bg-blue-600 text-white text-xs font-bold px-3 py-1 rounded-full shadow-md ml-2 flex-shrink-0"><?= number_format($year['count']); ?> รายการ</span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- ส่วนแสดงผลการค้นหาจริง (แสดงผลเสมอ) -->
            <div class="mt-10">
                <?php 
                    $header_title = "ผลงานตีพิมพ์ทั้งหมดในระบบ";
                    // ปรับหัวข้อตามเงื่อนไขการค้นหา/การกรอง
                    if (!empty($search_query) && $selected_type !== 'all') {
                        $header_title = "ผลการค้นหาสำหรับ \"<span class='text-blue-600'>" . htmlspecialchars($search_query) . "</span>\" ในประเภท **" . htmlspecialchars($publication_types[$selected_type] ?? $selected_type) . "**";
                    } elseif (!empty($search_query)) {
                        $header_title = "ผลการค้นหาสำหรับ \"<span class='text-blue-600'>" . htmlspecialchars($search_query) . "</span>\"";
                    } elseif ($selected_type !== 'all') {
                        $header_title = "ผลงานตีพิมพ์ในประเภท **" . htmlspecialchars($publication_types[$selected_type] ?? $selected_type) . "**";
                    }
                ?>
                <h2 class="text-2xl font-bold text-gray-800 mb-6 border-b pb-2">
                    <?= $header_title; ?> (พบ <?= count($search_results); ?> รายการ)
                </h2>
                
                <!-- NEW: Publication Type Filter Form -->
                <form method="GET" action="staffPage.php" class="mb-6 p-4 bg-gray-50 rounded-xl border border-gray-200 shadow-inner flex flex-col md:flex-row items-stretch md:items-center space-y-3 md:space-y-0 md:space-x-4">
                    <label for="pub_type_filter" class="text-gray-700 font-semibold flex-shrink-0">กรองตามประเภทผลงาน:</label>
                    
                    <!-- Hidden input to maintain the original search query -->
                    <input type="hidden" name="search" value="<?= htmlspecialchars($search_query); ?>">

                    <!-- Dropdown Filter -->
                    <select name="pub_type" id="pub_type_filter" 
                            class="border border-gray-300 rounded-lg p-2.5 text-base focus:ring-blue-500 focus:border-blue-500 transition w-full md:w-auto flex-grow">
                        <?php foreach($publication_types as $key => $value): ?>
                            <option value="<?= htmlspecialchars($key) ?>"
                                    <?= $selected_type === $key ? 'selected' : '' ?>>
                                <?= htmlspecialchars($value) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <button type="submit" class="bg-blue-600 text-white py-2 px-4 rounded-lg font-semibold hover:bg-blue-700 transition-colors duration-200 flex-shrink-0 shadow-md">
                        <i class="fas fa-filter mr-1"></i> ใช้ตัวกรอง
                    </button>
                    <?php if ($selected_type !== 'all' || !empty($search_query)): ?>
                        <!-- ปุ่มล้างตัวกรองทั้งหมด -->
                        <a href="staffPage.php" class="text-red-500 hover:text-red-700 transition text-sm flex items-center flex-shrink-0 justify-center md:justify-start">
                            <i class="fas fa-times-circle mr-1"></i> ล้างการค้นหา/ตัวกรอง
                        </a>
                    <?php endif; ?>
                </form>
                <!-- END: Publication Type Filter Form -->


                <?php if (!empty($search_results)): ?>
                    <div class="space-y-6">
                        <?php foreach ($search_results as $pub): ?>
                            <div class="bg-white p-5 rounded-xl shadow-lg border-l-4 border-blue-600 hover:shadow-xl transition-shadow duration-300">
                                <div class="flex justify-between items-start">
                                    <h3 class="text-xl font-bold text-blue-800 mb-2"><?= htmlspecialchars($pub['title']); ?></h3>
                                    <!-- แสดงสถานะ -->
                                    <span class="text-sm font-semibold px-3 py-1 rounded-full 
                                        <?= $pub['status'] === 'approved' ? 'bg-green-100 text-green-700' : 
                                           ($pub['status'] === 'waiting' ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700'); ?>">
                                        <?= htmlspecialchars($pub['status']); ?>
                                    </span>
                                </div>
                                <p class="text-gray-600 mb-1"><i class="fas fa-user-tie mr-2 text-gray-400"></i> **อาจารย์:** <?= htmlspecialchars($pub['first_name'] . ' ' . $pub['last_name']); ?></p>
                                <p class="text-gray-600 mb-1"><i class="fas fa-calendar-alt mr-2 text-gray-400"></i> **ปีที่เผยแพร่:** <?= htmlspecialchars($pub['publish_year']); ?></p>
                                <p class="text-gray-600"><i class="fas fa-tag mr-2 text-gray-400"></i> **ประเภท:** <?= htmlspecialchars($pub['type']); ?></p>
                                
                                <!-- ปุ่มดูรายละเอียด (เปลี่ยนเป็นปุ่มเปิด Modal PDF) -->
                                <div class="mt-3 text-right">
                                    <?php
                                        // ⚠️ สำคัญ: ใช้ file_path ตาม DB ที่ให้มา
                                        $filePath = htmlspecialchars($pub['file_path'] ?? ''); 
                                        $pdfTitle = htmlspecialchars($pub['title']);
                                    ?>
                                    <?php if (!empty($filePath)): ?>
                                        <button type="button" 
                                                onclick="openPdfModal('<?= $pdfTitle; ?>', '<?= $filePath; ?>')"
                                                class="inline-flex items-center text-white bg-blue-600 hover:bg-blue-700 font-semibold transition-colors px-3 py-1 rounded-lg shadow-md">
                                            ดูรายละเอียด PDF <i class="fas fa-file-pdf ml-2 text-sm"></i>
                                        </button>
                                    <?php else: ?>
                                         <span class="text-sm text-red-500 italic">ไม่มีไฟล์ PDF</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="p-6 bg-yellow-50 border border-yellow-300 rounded-xl text-yellow-800 shadow-md">
                        <p class="font-semibold">
                            <i class="fas fa-exclamation-triangle mr-2"></i> 
                            ไม่พบผลงานที่ตรงกับเงื่อนไขการค้นหา
                            <?php if (!empty($search_query)): ?>
                                สำหรับคำค้นหา "<?= htmlspecialchars($search_query); ?>" 
                            <?php endif; ?>
                            <?php if ($selected_type !== 'all'): ?>
                                ในประเภท **<?= htmlspecialchars($publication_types[$selected_type] ?? $selected_type); ?>**
                            <?php endif; ?>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <footer class="p-4 bg-gray-200 text-center text-gray-600 text-sm mt-auto">
        &copy; <?php echo date("Y"); ?> ระบบจัดการการตีพิมพ์ผลงาน | มหาวิทยาลัยสงขลานครินทร์ (PSU)
    </footer>
</div>


<!-- ========================================================== -->
<!-- ส่วนแสดง Modal และ JavaScript สำหรับ PDF Viewer -->
<!-- ========================================================== -->

<!-- Modal สำหรับแสดง PDF (ซ่อนอยู่จนกว่าจะมีการกดปุ่ม) -->
<div id="pdfModal"
     class="pdf-modal fixed inset-0 bg-gray-900 bg-opacity-80 hidden flex items-center justify-center p-2 sm:p-4">
    
    <div class="bg-white w-full h-full max-w-6xl max-h-[95vh] rounded-xl shadow-2xl flex flex-col">
        
        <!-- ส่วนหัวของ Modal -->
        <div class="p-4 border-b border-gray-200 flex justify-between items-center">
            <h2 id="pdfModalTitle" class="text-xl font-semibold text-gray-800 truncate">เอกสารรายละเอียด</h2>
            <button id="closeButton"
                    class="text-gray-500 hover:text-gray-700 p-2 rounded-full hover:bg-gray-100 transition">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>

        <!-- ส่วนแสดง PDF ด้วย iframe -->
        <div class="flex-grow">
            <iframe id="pdfIframe" 
                    src="" 
                    class="w-full h-full rounded-b-xl"
                    title="PDF Viewer">
                <!-- ข้อความสำรองหากเบราว์เซอร์ไม่รองรับ iframe หรือ PDF -->
                <p class="p-4 text-center text-red-500">เบราว์เซอร์ของคุณไม่รองรับการแสดง PDF แบบฝัง กรุณาใช้ลิงก์นี้เพื่อดาวน์โหลด</p>
            </iframe>
        </div>

    </div>
</div>

<script>
    const pdfModal = document.getElementById('pdfModal');
    const closeButton = document.getElementById('closeButton');
    const pdfIframe = document.getElementById('pdfIframe');
    const pdfModalTitle = document.getElementById('pdfModalTitle');

    /**
     * เปิด Modal และโหลดไฟล์ PDF
     * @param {string} title - ชื่อผลงานที่จะแสดงบนหัว Modal
     * @param {string} pdfPath - Path หรือ URL ของไฟล์ PDF (ซึ่งมาจากคอลัมน์ file_path)
     */
    function openPdfModal(title, pdfPath) {
        if (!pdfPath) {
            // ใช้ console.error แทน alert() เพื่อไม่ให้รบกวนผู้ใช้
            console.error("ไม่พบไฟล์ PDF สำหรับผลงานนี้");
            return;
        }
        
        // 1. ตั้งค่าชื่อ Modal
        pdfModalTitle.textContent = "รายละเอียด: " + title;
        
        // 2. ตั้งค่าแหล่งที่มาของ PDF
        // เนื่องจากไฟล์ในฐานข้อมูลอยู่ในรูปแบบ 'uploads/...' ซึ่งเป็น Path สัมพัทธ์
        pdfIframe.src = pdfPath;
        
        // 3. แสดง Modal
        pdfModal.classList.remove('hidden');
        pdfModal.classList.add('flex');
    }

    /**
     * ปิด Modal
     */
    function closePdfModal() {
        pdfModal.classList.add('hidden');
        pdfModal.classList.remove('flex');
        // หยุดการโหลดเนื้อหาใน iframe เมื่อปิด Modal เพื่อประหยัดทรัพยากร
        pdfIframe.src = ''; 
        pdfModalTitle.textContent = 'เอกสารรายละเอียด';
    }

    // Event Listener สำหรับปุ่มปิด
    closeButton.addEventListener('click', closePdfModal);

    // Event Listener สำหรับการคลิกนอก Modal
    pdfModal.addEventListener('click', (e) => {
        if (e.target === pdfModal) {
            closePdfModal();
        }
    });
</script>

</body>
</html>
<?php 
// ปิดการเชื่อมต่อฐานข้อมูลเมื่อสิ้นสุดการทำงานของสคริปต์
if (!$db_error && isset($conn)) {
    $conn->close(); 
}
?>
