<?php
// === การกำหนดค่าเริ่มต้น ===
// เปิดการรายงานข้อผิดพลาด
ini_set('display_errors', 1);
error_reporting(E_ALL);

// กำหนดค่าการเชื่อมต่อฐานข้อมูล
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'group10');

// กำหนดการแมปประเภทผลงานสำหรับ Dropdown (ภาษาอังกฤษ => ภาษาไทย)
$publication_types = [
    'all' => '-- ทุกประเภท --',
    'Journal' => 'บทความวารสาร',
    'Conference' => 'นำเสนอในการประชุม',
    'Thesis' => 'วิทยานิพนธ์/ภาคนิพนธ์',
    'Other' => 'อื่นๆ',
];

// 1. ฟังก์ชันเชื่อมต่อและดึงข้อมูลผลงานตีพิมพ์ พร้อมตัวกรองใหม่
function fetchPublications($year, $search_term, $pub_type) {
    // สร้างการเชื่อมต่อ
    $conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
    if ($conn->connect_error) {
        error_log("Database Connection Error: " . $conn->connect_error);
        return false;
    }

    // SQL Query หลัก - ใช้ WHERE 1=1 เพื่อให้ง่ายต่อการเพิ่มเงื่อนไข AND
    $sql = "
        SELECT 
            p.title, 
            p.publish_year, 
            p.type,
            u.first_name, 
            u.last_name 
        FROM Publication p
        JOIN User u ON p.Author_id = u.User_id
        WHERE 1=1 
    ";
    
    $params = [];
    $types = '';
    
    // 1. เงื่อนไข: ปีที่ตีพิมพ์
    if (!empty($year) && $year !== 'all') { 
        $sql .= " AND p.publish_year = ?";
        $types .= "s";
        $params[] = $year;
    }
    
    // 2. เงื่อนไข: ประเภทผลงาน
    if (!empty($pub_type) && $pub_type !== 'all') {
        $sql .= " AND p.type = ?";
        $types .= "s";
        $params[] = $pub_type;
    }

    // 3. เงื่อนไข: คำค้นหา (ค้นจากชื่อเรื่อง, ชื่อจริง, หรือนามสกุล)
    if (!empty($search_term)) {
        $sql .= " AND (
            p.title LIKE ? OR 
            u.first_name LIKE ? OR 
            u.last_name LIKE ?
        )";
        // การใช้ LIKE %...% ต้องใส่ % ในตัวแปรที่จะ bind_param
        $search_like = "%" . $search_term . "%";
        $types .= "sss";
        $params[] = $search_like;
        $params[] = $search_like;
        $params[] = $search_like;
    }
    
    // จัดเรียงข้อมูล
    $sql .= " ORDER BY p.publish_year DESC, p.title ASC";

    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        $conn->close();
        return false;
    }

    // ผูกค่าตัวแปรแบบไดนามิก
    if (!empty($params)) {
        // ใช้ Spread Operator (...) เพื่อส่ง array ของพารามิเตอร์ไป bind_param
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

// 2. ฟังก์ชันดึงปีที่มีผลงานตีพิมพ์ทั้งหมดสำหรับ Filter Dropdown
function fetchDistinctYears() {
    $conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
    if ($conn->connect_error) {
        error_log("Database Connection Error for years: " . $conn->connect_error);
        return [];
    }

    $sql = "SELECT DISTINCT publish_year FROM Publication WHERE publish_year IS NOT NULL ORDER BY publish_year DESC";
    $result = $conn->query($sql);
    
    $years = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $years[] = $row['publish_year'];
        }
    }

    $conn->close();
    return $years;
}

// --- Logic หลัก ---

// 1. ดึงปีที่มีอยู่ทั้งหมดสำหรับตัวกรอง
$available_years = fetchDistinctYears();

// 2. ดึงค่าตัวกรองทั้งหมดจาก GET
$raw_year = $_GET['year'] ?? 'all';
$raw_search = $_GET['search'] ?? '';
$raw_type = $_GET['pub_type'] ?? 'all';

// Sanitize และ Normalize ค่า
$selected_year = ($raw_year !== 'all' && is_numeric($raw_year)) ? filter_var($raw_year, FILTER_SANITIZE_NUMBER_INT) : 'all';
// ใช้ FILTER_UNSAFE_RAW เพื่อให้รองรับภาษาไทยได้ดี
$selected_search = filter_var($raw_search, FILTER_UNSAFE_RAW); 
$selected_type = array_key_exists($raw_type, $publication_types) ? $raw_type : 'all';

// 3. ดึงข้อมูลรายงานตามตัวกรองทั้งหมด
$data = fetchPublications($selected_year, $selected_search, $selected_type);

// 4. สร้างข้อความสรุปการกรองเพื่อแสดงในรายงาน
$filter_summary = "ปี: " . ($selected_year !== 'all' ? $selected_year : 'ทุกปี');
$filter_summary .= " | ประเภท: " . ($selected_type !== 'all' ? $publication_types[$selected_type] : 'ทุกประเภท');
$filter_summary .= !empty($selected_search) ? " | ค้นหา: " . htmlspecialchars($selected_search) : '';


// 5. ตรวจสอบข้อมูลที่พบ
if ($data === false) {
    // กรณีฐานข้อมูลมีปัญหา (fetchPublications คืนค่า false)
     $report_content = '<div class="p-6 bg-red-100 border border-red-400 rounded-xl text-red-800 shadow-md max-w-2xl mx-auto mt-10">
        <p class="font-semibold text-center"><i class="fas fa-database mr-2"></i> ไม่สามารถเชื่อมต่อฐานข้อมูลได้ หรือมีข้อผิดพลาดในการดึงข้อมูล</p>
    </div>';
    $data_found = false;
} elseif (count($data) == 0) {
    // กรณีไม่พบข้อมูล
    $report_content = '<div class="p-6 bg-yellow-50 border border-yellow-300 rounded-xl text-yellow-800 shadow-md max-w-2xl mx-auto mt-10">
        <p class="font-semibold text-center"><i class="fas fa-exclamation-triangle mr-2"></i> ไม่พบข้อมูลรายงานตีพิมพ์ที่ตรงกับเงื่อนไข</p>
        <p class="text-center text-sm mt-2">เงื่อนไขปัจจุบัน: ' . $filter_summary . '</p>
    </div>';
    $data_found = false;
} else {
    $data_found = true;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายงานการตีพิมพ์</title>
    <!-- โหลด Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- โหลด Font Awesome สำหรับ Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <style>
        body { 
            font-family: "Inter", "Tahoma", "TH Sarabun New", sans-serif;
            background-color: #f4f7f9;
        }
        
        .report-table th, .report-table td {
            padding: 12px;
            border: 1px solid #e5e7eb;
        }
        .report-table th {
             white-space: nowrap; /* ป้องกัน header หด */
        }

        @media print {
            body { 
                background-color: white !important; 
                margin: 0;
            }
            .print-area {
                box-shadow: none !important;
                border-radius: 0 !important;
                padding: 0 !important;
            }
            table {
                font-size: 10pt; 
            }
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body class="p-4 sm:p-8">

    <div class="max-w-6xl mx-auto bg-white p-6 sm:p-10 rounded-xl shadow-2xl print-area">

        <!-- Header Section -->
        <header class="mb-8 pb-4 border-b-4 border-blue-600">
            <h1 class="text-3xl font-extrabold text-blue-800 text-center mb-2">รายงานการตีพิมพ์ผลงานอาจารย์</h1>
            <p class="text-center text-gray-600 text-lg">
                <i class="fas fa-filter mr-2 text-blue-500"></i>
                <b>ตัวกรองปัจจุบัน:</b> 
                <span class="font-semibold text-blue-600">
                    <?= $filter_summary; ?>
                </span>
            </p>
        </header>

        <!-- Filter Section & Navigation (No Print) -->
        <section class="mb-8 p-4 bg-gray-50 border border-gray-200 rounded-lg shadow-inner no-print">
            
            <div class="flex flex-wrap gap-3 mb-4">
                <!-- ปุ่ม 1: กลับหน้าหลักเจ้าหน้าที่ -->
                <a href="staffPage.php" 
                   class="inline-flex items-center bg-gray-300 text-gray-800 py-2 px-4 rounded-full font-semibold hover:bg-gray-400 transition-colors duration-200 shadow-md">
                    <i class="fas fa-arrow-circle-left mr-2"></i> กลับหน้าหลักเจ้าหน้าที่
                </a>
                
                <!-- ปุ่ม 2: เรียกดูประวัติส่วนตัว (staff_pub_his.php) -->
                <a href="staff_pub_his.php" 
                   class="inline-flex items-center bg-indigo-600 text-white py-2 px-4 rounded-full font-semibold hover:bg-indigo-700 transition-colors duration-200 shadow-md">
                   <i class="fas fa-history mr-2 text-white-800"></i> เรียกดูประวัติการตีพิมพ์
                </a>
            </div>
            
            <h2 class="text-xl font-bold text-gray-700 mb-4 flex items-center mt-4 pt-4 border-t border-gray-300">
                <i class="fas fa-search mr-2 text-blue-600"></i> ตัวกรองรายงาน
            </h2>
            
            <form method="GET" action="dowload_report.php" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                
                <!-- 1. ช่องค้นหา (ชื่ออาจารย์ / ชื่อรายงาน) -->
                <div class="md:col-span-2">
                    <label for="search_input" class="text-gray-600 font-medium block mb-1">ค้นหา (ชื่ออาจารย์ หรือ ชื่อรายงาน):</label>
                    <input type="text" name="search" id="search_input" 
                           value="<?= htmlspecialchars($selected_search) ?>"
                           placeholder="เช่น: สมศรี หรือ Deep Learning"
                           class="border border-gray-300 rounded-lg p-2.5 text-base focus:ring-blue-500 focus:border-blue-500 transition w-full">
                </div>

                <!-- 2. เลือกปี -->
                <div>
                    <label for="year_select" class="text-gray-600 font-medium block mb-1">เลือกปี:</label>
                    <select name="year" id="year_select" 
                            class="border border-gray-300 rounded-lg p-2.5 text-base focus:ring-blue-500 focus:border-blue-500 transition w-full">
                        <option value="all" <?= $selected_year === 'all' ? 'selected' : '' ?>>-- ทุกปี --</option>
                        <?php foreach($available_years as $year_option): ?>
                            <option value="<?= htmlspecialchars($year_option) ?>"
                                    <?= $selected_year === $year_option ? 'selected' : '' ?>>
                                <?= htmlspecialchars($year_option) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- 3. เลือกประเภทผลงาน -->
                <div>
                    <label for="type_select" class="text-gray-600 font-medium block mb-1">เลือกประเภท:</label>
                    <select name="pub_type" id="type_select" 
                            class="border border-gray-300 rounded-lg p-2.5 text-base focus:ring-blue-500 focus:border-blue-500 transition w-full">
                        <?php foreach($publication_types as $key => $value): ?>
                            <option value="<?= htmlspecialchars($key) ?>"
                                    <?= $selected_type === $key ? 'selected' : '' ?>>
                                <?= htmlspecialchars($value) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- 4. ปุ่มแสดงผล -->
                <button type="submit" class="md:col-span-4 bg-blue-600 text-white py-2.5 px-4 rounded-lg font-semibold hover:bg-blue-700 transition-colors duration-200 shadow-md">
                    <i class="fas fa-search mr-2"></i> แสดงรายงานตามตัวกรอง
                </button>
            </form>
        </section>

        <?php if($data_found): ?>
            <!-- Report Table -->
            <div class="overflow-x-auto">
                <table class="report-table min-w-full text-left text-base rounded-lg overflow-hidden">
                    <thead>
                        <tr class="bg-blue-600 text-white text-lg shadow-md">
                            <th class="p-3 w-1/4 rounded-tl-lg">ชื่ออาจารย์</th>
                            <th class="p-3 w-2/5">ชื่อรายงาน</th>
                            <th class="p-3 w-1/5 text-center">ประเภท</th>
                            <th class="p-3 w-1/12 text-center rounded-tr-lg">ปี</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($data as $row): ?>
                        <tr class="bg-white border-b border-gray-100 hover:bg-blue-50 transition-colors duration-150">
                            <td class="p-3 font-medium text-gray-800">
                                <?= htmlspecialchars($row['first_name']." ".$row['last_name']) ?>
                            </td>
                            <td class="p-3 text-gray-700">
                                <?= htmlspecialchars($row['title']) ?>
                            </td>
                            <td class="p-3 text-center text-sm font-medium">
                                <!-- แสดงผลเป็นภาษาไทยจาก Mapping -->
                                <span class="px-2 py-1 rounded-full text-xs font-semibold
                                    <?= $row['type'] === 'Journal' ? 'bg-green-100 text-green-800' : '' ?>
                                    <?= $row['type'] === 'Conference' ? 'bg-purple-100 text-purple-800' : '' ?>
                                    <?= $row['type'] === 'Thesis' ? 'bg-blue-100 text-blue-800' : '' ?>
                                    <?= $row['type'] === 'Other' ? 'bg-gray-100 text-gray-800' : '' ?>">
                                    <?= htmlspecialchars($publication_types[$row['type']] ?? $row['type']) ?>
                                </span>
                            </td>
                            <td class="p-3 text-center font-bold text-gray-600">
                                <?= htmlspecialchars($row['publish_year']) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Print Button (ซ่อนเมื่อพิมพ์) -->
            <div class="mt-8 text-center no-print">
                <button onclick="window.print()" 
                        class="bg-green-600 text-white py-3 px-8 rounded-full font-bold text-lg 
                               hover:bg-green-700 transition-colors duration-200 shadow-xl flex items-center justify-center mx-auto">
                    <i class="fas fa-print mr-3"></i> 
                    พิมพ์รายงาน (Print Report)
                </button>
            </div>
        <?php else: ?>
            <!-- No Data Message -->
            <?= $report_content; ?>
        <?php endif; ?>

    </div>
</body>
</html>
