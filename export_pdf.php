<?php
// === การกำหนดค่าเริ่มต้น ===
session_start();
// ตรวจสอบว่า user ล็อกอินหรือยัง
if (!isset($_SESSION['user_id'])) {
    exit('Unauthorized access.');
}

ini_set('display_errors', 1);
error_reporting(E_ALL);

// กำหนดค่าการเชื่อมต่อฐานข้อมูล
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'group10');

$user_id = $_SESSION['user_id'];

// กำหนดการแมปประเภทผลงานสำหรับ Dropdown และการแสดงผล (ภาษาอังกฤษ => ภาษาไทย)
$publication_types = [
    'all' => 'ทุกประเภท',
    'Journal' => 'บทความวารสาร',
    'Conference' => 'นำเสนอในการประชุม',
    'Thesis' => 'วิทยานิพนธ์/ภาคนิพนธ์',
    'Other' => 'อื่นๆ',
];

// ฟังก์ชันสำหรับแปลงประเภทผลงานเป็นภาษาไทย
function translate_type($type, $mapping) {
    return $mapping[$type] ?? $type;
}

// 1. ฟังก์ชันเชื่อมต่อและดึงข้อมูลผลงานตีพิมพ์ (เฉพาะของผู้ใช้ปัจจุบัน)
function fetchPublicationsForExport($user_id, $year, $title, $pub_type) {
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
    $types = 'i'; 
    
    if (!empty($year) && $year !== 'all') { 
        $sql .= " AND p.publish_year = ?";
        $types .= "s";
        $params[] = $year;
    }
    
    if (!empty($pub_type) && $pub_type !== 'all') {
        $sql .= " AND p.type = ?";
        $types .= "s";
        $params[] = $pub_type;
    }

    if (!empty($title)) {
        $sql .= " AND p.title LIKE ?";
        $search_like = "%" . $title . "%";
        $types .= "s";
        $params[] = $search_like;
    }
    
    $sql .= " ORDER BY p.publish_year DESC, p.title ASC";

    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        $conn->close();
        return false;
    }

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
    }

    $stmt->close();
    $conn->close();
    return $data;
}

// 2. ดึงชื่อผู้ใช้ปัจจุบันและข้อมูลรายงาน
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
$sql_user = "SELECT first_name, last_name FROM User WHERE User_id = ?";
$stmt_user = $conn->prepare($sql_user);
$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$user_info = $stmt_user->get_result()->fetch_assoc();
$reporter_name = htmlspecialchars($user_info['first_name'] . " " . $user_info['last_name']);
$stmt_user->close();
$conn->close();

// 3. ดึงค่าตัวกรองจาก GET (มาจากปุ่มใน publication_report.php)
$selected_year = $_GET['year'] ?? 'all';
$selected_type = $_GET['type'] ?? 'all';
$selected_title = $_GET['title'] ?? '';

// 4. ดึงข้อมูลรายงาน
$report_data = fetchPublicationsForExport($user_id, $selected_year, $selected_title, $selected_type);

// 5. สร้างข้อความสรุปการกรองเพื่อแสดงในรายงาน
$filter_summary = "ปี: " . ($selected_year !== 'all' ? $selected_year : 'ทุกปี');
$filter_summary .= " | ประเภท: " . translate_type($selected_type, $publication_types);
$filter_summary .= !empty($selected_title) ? " | ชื่อผลงาน (ค้นหา): " . htmlspecialchars($selected_title) : '';

// 6. กำหนด Header
header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายงานผลงานตีพิมพ์ - <?= $reporter_name ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <style>
        /* กำหนดฟอนต์ไทยมาตรฐาน */
        body {
            font-family: 'TH Sarabun New', Tahoma, sans-serif; 
            font-size: 14pt; 
        }

        /* ส่วนสำคัญสำหรับการสั่งพิมพ์ (Print to PDF) */
        @media print {
            .no-print {
                display: none !important; /* ซ่อนปุ่มสั่งพิมพ์ */
            }
            body {
                margin: 0 !important; /* ลบขอบเอกสาร */
            }
            .print-container {
                box-shadow: none !important;
                border: none !important;
                padding: 0 !important;
            }
            /* จัดรูปแบบตารางสำหรับพิมพ์ */
            .print-table th, .print-table td {
                border: 1px solid #000 !important;
                padding: 5px !important;
                font-size: 11pt;
            }
            .print-table th {
                background-color: #e5e7eb !important; /* สีเทาอ่อน */
                color: #374151 !important; /* สีเทาเข้ม */
                -webkit-print-color-adjust: exact; /* บังคับให้พิมพ์สีพื้นหลัง */
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body class="bg-gray-100 p-4 sm:p-8">

    <div class="max-w-4xl mx-auto bg-white p-6 sm:p-10 rounded-xl shadow-2xl print-container">

        <header class="mb-6 pb-3 border-b-4 border-blue-600">
            <h1 class="text-3xl font-extrabold text-blue-800 text-center mb-1">รายงานผลงานตีพิมพ์ส่วนตัว</h1>
            <p class="text-center text-gray-600 text-base">
                <i class="fas fa-user-circle mr-1 text-blue-500"></i> 
                <strong>ผู้ทำรายงาน:</strong> <?= $reporter_name ?>
                <span class="mx-2 text-gray-400">|</span>
                <i class="fas fa-calendar-alt mr-1 text-blue-500"></i>
                <strong>วันที่:</strong> <?= date('d/m/Y H:i') ?>
            </p>
        </header>
        
        <div class="mb-6 p-3 bg-blue-50 border border-blue-200 rounded-lg text-sm text-blue-800 font-medium">
            <i class="fas fa-filter mr-2"></i>
            <b>เงื่อนไขการกรอง:</b> <?= $filter_summary ?>
        </div>

        <h2 class="text-xl font-bold text-gray-700 mb-4">รายการผลงานตีพิมพ์ที่ผ่านการอนุมัติ (<?= count($report_data) ?> รายการ)</h2>
        
        <?php if (empty($report_data)): ?>
            <div class="p-6 bg-yellow-50 border border-yellow-300 rounded-xl text-yellow-800 text-center">
                <i class="fas fa-exclamation-triangle mr-2"></i> ไม่พบผลงานที่ตรงตามเงื่อนไข
            </div>
        <?php else: ?>
            <div class="overflow-x-auto shadow-lg rounded-lg border border-gray-200">
                <table class="w-full text-left print-table">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="px-3 py-2 w-[5%] text-xs text-gray-600 uppercase">ลำดับ</th>
                            <th class="px-3 py-2 w-[45%] text-xs text-gray-600 uppercase">ชื่อผลงาน</th>
                            <th class="px-3 py-2 w-[10%] text-center text-xs text-gray-600 uppercase">ปี</th>
                            <th class="px-3 py-2 w-[15%] text-center text-xs text-gray-600 uppercase">ประเภท</th>
                            <th class="px-3 py-2 w-[25%] text-xs text-gray-600 uppercase">วารสาร/สถานที่ตีพิมพ์</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php $i = 1; foreach ($report_data as $pub): ?>
                            <tr class="hover:bg-blue-50 transition-colors duration-150">
                                <td class="px-3 py-2 text-sm text-center font-medium text-gray-900"><?= $i++ ?></td>
                                <td class="px-3 py-2 text-sm text-gray-700"><?= htmlspecialchars($pub['title']) ?></td>
                                <td class="px-3 py-2 text-sm text-center font-bold text-gray-600"><?= htmlspecialchars($pub['publish_year']) ?></td>
                                <td class="px-3 py-2 text-center text-xs font-semibold">
                                     <span class="px-2 py-1 rounded-full 
                                        <?= $pub['type'] === 'Journal' ? 'bg-green-100 text-green-800' : '' ?>
                                        <?= $pub['type'] === 'Conference' ? 'bg-purple-100 text-purple-800' : '' ?>
                                        <?= $pub['type'] === 'Thesis' ? 'bg-blue-100 text-blue-800' : '' ?>
                                        <?= $pub['type'] === 'Other' ? 'bg-gray-100 text-gray-800' : '' ?>">
                                        <?= translate_type($pub['type'], $publication_types) ?>
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-sm text-gray-500"><?= htmlspecialchars($pub['journal'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <div class="mt-8 text-center no-print">
            
            <button onclick="window.print()" 
                     class="bg-red-600 text-white py-3 px-8 rounded-full font-bold text-lg 
                            hover:bg-red-700 transition-colors duration-200 shadow-xl flex items-center justify-center mx-auto">
                <i class="fas fa-file-pdf mr-3"></i> 
                สั่งพิมพ์/บันทึกรายงานเป็น PDF
            </button>
        </div>

    </div>
</body>
</html>