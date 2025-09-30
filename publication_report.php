<?php
// === การกำหนดค่าเริ่มต้น ===
session_start();
// ตรวจสอบว่า user ล็อกอินหรือยัง
if (!isset($_SESSION['user_id'])) {
    header("Location: login-v1.php");
    exit;
}

ini_set('display_errors', 1);
error_reporting(E_ALL);

// กำหนดค่าการเชื่อมต่อฐานข้อมูล
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'group10');

$user_id = $_SESSION['user_id']; // ID ผู้ใช้ปัจจุบัน

// กำหนดการแมปประเภทผลงานสำหรับ Dropdown และการแสดงผล (ภาษาอังกฤษ => ภาษาไทย)
$publication_types = [
    'all' => '-- ทุกประเภท --',
    'Journal' => 'บทความวารสาร',
    'Conference' => 'นำเสนอในการประชุม',
    'Thesis' => 'วิทยานิพนธ์/ภาคนิพนธ์',
    'Other' => 'อื่นๆ',
];

// ฟังก์ชันสำหรับแปลงประเภทผลงานเป็นภาษาไทย (ใช้สำหรับการแสดงผลเท่านั้น)
function translate_type($type, $mapping) {
    return $mapping[$type] ?? $type;
}

// 1. ฟังก์ชันเชื่อมต่อและดึงข้อมูลผลงานตีพิมพ์ (เฉพาะของผู้ใช้ปัจจุบัน)
function fetchPublications($user_id, $year, $search_term, $pub_type) {
    global $publication_types;
    $conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
    if ($conn->connect_error) {
        error_log("Database Connection Error: " . $conn->connect_error);
        return false;
    }

    $sql = "
        SELECT 
            p.title, 
            p.publish_year, 
            p.type,
            p.journal
        FROM Publication p
        WHERE p.Author_id = ? AND p.status = 'approved' 
    ";
    
    $params = [$user_id];
    $types = 'i'; // i สำหรับ User_id (int)
    
    // 1. เงื่อนไข: ปีที่ตีพิมพ์
    if (!empty($year) && $year !== 'all') { 
        $sql .= " AND p.publish_year = ?";
        $types .= "s";
        $params[] = $year;
    }
    
    // 2. เงื่อนไข: ประเภทผลงาน
    if (!empty($pub_type) && $pub_type !== 'all' && array_key_exists($pub_type, $publication_types)) {
        $sql .= " AND p.type = ?";
        $types .= "s";
        $params[] = $pub_type;
    }

    // 3. เงื่อนไข: คำค้นหา (ค้นจากชื่อเรื่อง)
    if (!empty($search_term)) {
        $sql .= " AND p.title LIKE ?";
        $search_like = "%" . $search_term . "%";
        $types .= "s";
        $params[] = $search_like;
    }
    
    $sql .= " ORDER BY p.publish_year DESC, p.title ASC";

    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        $conn->close();
        return false;
    }

    // ผูกค่าตัวแปรแบบไดนามิก: ใช้ Spread Operator
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params); 
    }

    $data = [];
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
    } else {
        error_log("Execute failed: " . $stmt->error);
    }

    $stmt->close();
    $conn->close();
    return $data;
}

// 2. ฟังก์ชันดึงปีที่มีผลงานตีพิมพ์ทั้งหมดสำหรับ Filter Dropdown (เฉพาะของผู้ใช้)
function fetchDistinctYears($user_id) {
    $conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
    if ($conn->connect_error) {
        return [];
    }

    $sql = "SELECT DISTINCT publish_year FROM Publication WHERE Author_id = ? AND publish_year IS NOT NULL AND status = 'approved' ORDER BY publish_year DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $years = [];
    while ($row = $result->fetch_assoc()) {
        $years[] = $row['publish_year'];
    }

    $stmt->close();
    $conn->close();
    return $years;
}

// 3. ดึงชื่อผู้ใช้ปัจจุบัน
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
$sql_user = "SELECT first_name, last_name FROM User WHERE User_id = ?";
$stmt_user = $conn->prepare($sql_user);
$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$user_info = $stmt_user->get_result()->fetch_assoc();
$current_user_name = htmlspecialchars($user_info['first_name'] . " " . $user_info['last_name']);
$stmt_user->close();
$conn->close();

// --- Logic หลัก ---
$available_years = fetchDistinctYears($user_id);

// 1. ดึงค่าตัวกรองจาก POST
$raw_year = $_POST['year'] ?? 'all';
$raw_search = $_POST['search'] ?? '';
$raw_type = $_POST['pub_type'] ?? 'all';

// Sanitize และ Normalize ค่า
$selected_year = ($raw_year !== 'all' && is_numeric($raw_year)) ? filter_var($raw_year, FILTER_SANITIZE_NUMBER_INT) : 'all';
$selected_search = filter_var($raw_search, FILTER_UNSAFE_RAW); 
$selected_type = array_key_exists($raw_type, $publication_types) ? $raw_type : 'all';

// 2. ดึงข้อมูลรายงานตามตัวกรองทั้งหมด
$report_data = fetchPublications($user_id, $selected_year, $selected_search, $selected_type);

// 3. สร้าง Query String สำหรับส่งออก PDF
$pdf_query = http_build_query([
    'year' => $selected_year,
    'type' => $selected_type,
    'title' => $selected_search
]);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดทำรายงานตีพิมพ์ | ระบบ จัดการการตีพิมพ์ผลงาน</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* ... CSS เดิม ... */
        .text-theme { color: #1d4ed8; }
        .menu-active {
            color: #1d4ed8 !important;
            background-color: #dbeafe !important;
            font-weight: 600;
        }
        .psu-logo {
            height: 100px;
            object-fit: contain;
        }
    </style>
</head>
<body class="flex min-h-screen font-sans bg-gray-100">

<aside class="w-64 bg-white shadow-lg p-6 flex flex-col sticky top-0 h-screen">
    <div class="flex flex-col items-center border-b pb-4 mb-4">
        <img src="./img/img_psu.png" alt="PSU Logo" class="psu-logo">
        <span class="text-xs font-semibold text-gray-600">ระบบจัดการการตีพิมพ์</span>
    </div>

    <a href="edit_profile.php" title="แก้ไขข้อมูลส่วนตัว">
        <div class="flex items-center px-1 py-3 mb-4 bg-gray-50 rounded-lg border border-gray-200 hover:bg-gray-100 transition duration-150">
            <i class="fas fa-user-circle text-2xl text-gray-600 ml-1 mr-3"></i>
            <span class="text-sm font-semibold text-gray-700 truncate">
                <?= $current_user_name; ?>
            </span>
        </div>
    </a>

    <nav class="w-full flex-grow">
        <a href="Home-PR.php" class="flex items-center p-3 rounded-lg mb-3 text-gray-700 hover:bg-blue-100 hover:text-blue-700 transition-colors duration-150">
            <i class="fas fa-home w-5 h-5 mr-3"></i> หน้าหลัก
        </a>
        <a href="publications.php" class="flex items-center p-3 rounded-lg mb-3 text-gray-700 hover:bg-blue-100 hover:text-blue-700 transition-colors duration-150">
            <i class="fas fa-list-alt w-5 h-5 mr-3"></i> รายการผลงานตีพิมพ์
        </a>
        <a href="add_publication.php" class="flex items-center p-3 rounded-lg mb-3 text-gray-700 hover:bg-blue-100 hover:text-blue-700 transition-colors duration-150">
            <i class="fas fa-plus-circle w-5 h-5 mr-3"></i> เพิ่มผลงานตีพิมพ์
        </a>
        <a href="pubHis.php" class="flex items-center p-3 rounded-lg mb-3 text-gray-700 hover:bg-blue-100 hover:text-blue-700 transition-colors duration-150">
            <i class="fas fa-history w-5 h-5 mr-3"></i> ประวัติการแก้ไข
        </a>
        <a href="publication_report.php" class="flex items-center p-3 rounded-lg mb-3 text-theme bg-blue-100 hover:bg-blue-200 hover:text-blue-900 font-semibold transition-colors duration-150 menu-active">
            <i class="fas fa-chart-bar w-5 h-5 mr-3"></i> จัดทำรายงานตีพิมพ์
        </a>
        <a href="usermannual.php" class="flex items-center p-3 rounded-lg text-gray-700 hover:bg-blue-100 hover:text-blue-700 transition-colors duration-150">
            <i class="fas fa-book w-5 h-5 mr-3"></i> คู่มือการใช้งาน
        </a>
        </nav>

    <div class="px-0 pt-4 border-t border-gray-200">
        <a href="logout.php" class="flex items-center p-3 rounded-lg text-red-500 hover:bg-red-50 transition-colors duration-150">
            <i class="fas fa-sign-out-alt w-5 h-5 mr-3"></i> ออกจากระบบ
        </a>
    </div>
</aside>

<main class="flex-1 p-8">
    <header class="flex items-center justify-between mb-8 pb-4 border-b border-gray-300">
        <h1 class="text-3xl font-bold text-gray-800">จัดทำรายงานผลงานตีพิมพ์ส่วนตัว</h1>
        <div class="flex items-center space-x-4">
            <a href="edit_profile.php" class="text-gray-600 font-medium hidden sm:block hover:text-blue-700 transition-colors duration-150">
                <?= $current_user_name; ?>
            </a>
        </div>
    </header>

    <div class="bg-white rounded-xl shadow-2xl p-6 mb-8">
        <h2 class="text-xl font-semibold text-gray-700 mb-4">ตัวเลือกการกรองและค้นหา</h2>
        <form method="POST" action="publication_report.php" class="space-y-4">
            
            <div class="w-full">
                <label for="search_input" class="block text-sm font-medium text-gray-700 mb-1">ค้นหาจากชื่อผลงาน</label>
                <input type="text" id="search_input" name="search" value="<?= htmlspecialchars($selected_search ?? '') ?>" placeholder="ป้อนชื่อผลงานที่ต้องการค้นหา"
                       class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md border">
            </div>

            <div class="flex flex-col sm:flex-row gap-4 items-end">
                <div class="flex-1 w-full sm:w-1/2">
                    <label for="year" class="block text-sm font-medium text-gray-700 mb-1">กรองด้วยปีที่ตีพิมพ์</label>
                    <select id="year" name="year" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md border">
                        <option value="all" <?= $selected_year === 'all' ? 'selected' : '' ?>>-- ทั้งหมด --</option>
                        <?php foreach($available_years as $year): ?>
                            <option value="<?= htmlspecialchars($year) ?>" <?= $selected_year === $year ? 'selected' : '' ?>>
                                <?= htmlspecialchars($year) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="flex-1 w-full sm:w-1/2">
                    <label for="type" class="block text-sm font-medium text-gray-700 mb-1">กรองด้วยประเภทผลงาน</label>
                    <select id="type" name="pub_type" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md border">
                        <?php foreach($publication_types as $key => $value): ?>
                            <option value="<?= htmlspecialchars($key) ?>" <?= $selected_type === $key ? 'selected' : '' ?>>
                                <?= htmlspecialchars($value) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="w-full sm:w-auto">
                    <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-md shadow-md hover:bg-blue-700 transition-colors duration-200 w-full sm:w-auto flex items-center justify-center">
                        <i class="fas fa-search mr-2"></i> ค้นหา/สร้างรายงาน
                    </button>
                </div>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-xl shadow-2xl p-6">
        <h2 class="text-xl font-semibold text-gray-700 mb-4">ผลงานตีพิมพ์ที่พบ (<?= count($report_data); ?> รายการ)</h2>
        <?php if (empty($report_data)): ?>
            <p class="text-gray-500 text-center py-10">ไม่พบผลงานตีพิมพ์ที่ตรงตามเงื่อนไขที่เลือก หรือยังไม่มีผลงานที่ได้รับการอนุมัติ</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ลำดับ</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ชื่อผลงาน</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ปี</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ประเภท</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">วารสาร/สถานที่ตีพิมพ์</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php $i = 1; foreach ($report_data as $pub): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= $i++; ?></td>
                                <td class="px-6 py-4 whitespace-normal text-sm text-gray-900"><?= htmlspecialchars($pub['title']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($pub['publish_year']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= htmlspecialchars(translate_type($pub['type'], $publication_types)); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-normal text-sm text-gray-500"><?= htmlspecialchars($pub['journal'] ?? '-'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="mt-6 text-right">
                <a href="export_pdf.php?<?= $pdf_query ?>" target="_blank"
                        class="inline-flex items-center bg-red-600 text-white px-6 py-2 rounded-md shadow-md hover:bg-red-700 transition-colors duration-200 font-medium">
                    <i class="fas fa-file-pdf mr-2"></i> ส่งออกเป็น PDF
                </a>
            </div>

        <?php endif; ?>
    </div>

</main>
</div>

</body>
</html>