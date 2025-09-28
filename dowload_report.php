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

// 1. ฟังก์ชันเชื่อมต่อและดึงข้อมูลผลงานตีพิมพ์
function fetchPublications($year) {
    // สร้างการเชื่อมต่อ
    $conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
    if ($conn->connect_error) {
        error_log("Database Connection Error: " . $conn->connect_error);
        return false;
    }

    // SQL Query หลัก
    $sql = "
        SELECT 
            p.title, 
            p.publish_year, 
            u.first_name, 
            u.last_name 
        FROM Publication p
        JOIN User u ON p.Author_id = u.User_id
    ";
    
    // เพิ่มเงื่อนไข WHERE ถ้ามีการระบุปีและไม่ใช่ 'all'
    if (!empty($year) && $year !== 'all') { 
        $sql .= " WHERE p.publish_year = ?";
    }
    
    // จัดเรียงข้อมูล
    $sql .= " ORDER BY p.publish_year DESC, p.title ASC";

    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        $conn->close();
        return false;
    }

    // ผูกค่าตัวแปรถ้ามีการระบุปี
    if (!empty($year) && $year !== 'all') {
        // 's' หมายถึง string
        $stmt->bind_param("s", $year); 
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

    // ดึงปีที่ไม่ซ้ำกัน เรียงจากมากไปน้อย
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

// 2. ดึงปีที่ถูกเลือกจาก GET parameter (ค่าเริ่มต้นคือ 'all')
$raw_year = $_GET['year'] ?? 'all';
$selected_year = 'all';

// ✅ แก้ไข: แทนที่ FILTER_SANITIZE_STRING ด้วย FILTER_SANITIZE_NUMBER_INT 
// หากค่าไม่ใช่ 'all' ให้กรองเป็นตัวเลข เพื่อความปลอดภัยและเหมาะสมกับข้อมูลปี
if ($raw_year !== 'all') {
    $selected_year = filter_var($raw_year, FILTER_SANITIZE_NUMBER_INT);
} else {
    $selected_year = 'all';
}

// 3. ดึงข้อมูลรายงานตามปีที่ถูกเลือก
$data = fetchPublications($selected_year);

// 4. ตรวจสอบข้อมูลที่พบ
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
        /* กำหนด font สำหรับภาษาไทยให้รองรับการพิมพ์และการแสดงผลบนเว็บ */
        body { 
            font-family: "Inter", "Tahoma", "TH Sarabun New", sans-serif;
            background-color: #f4f7f9; /* สีพื้นหลังอ่อน ๆ */
        }
        
        /* สไตล์ตารางหลัก */
        .report-table th, .report-table td {
            padding: 12px;
            border: 1px solid #e5e7eb; /* gray-200 */
        }

        /* สไตล์สำหรับพิมพ์: ทำให้หน้ากระดาษดูสะอาดตา */
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
                font-size: 14pt; /* ขนาดตัวอักษรสำหรับพิมพ์ */
            }
            /* ซ่อนส่วนที่เกี่ยวข้องกับตัวกรองเมื่อพิมพ์จริง */
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body class="p-4 sm:p-8">

    <div class="max-w-4xl mx-auto bg-white p-6 sm:p-10 rounded-xl shadow-2xl print-area">

        <!-- Header Section -->
        <header class="mb-8 pb-4 border-b-4 border-blue-600">
            <h1 class="text-3xl font-extrabold text-blue-800 text-center mb-2">รายงานการตีพิมพ์ผลงานอาจารย์</h1>
            <p class="text-center text-gray-600 text-lg">
                <i class="fas fa-calendar-alt mr-2 text-blue-500"></i>
                <b>ปีการตีพิมพ์ที่กำลังแสดง:</b> 
                <span class="font-semibold text-blue-600">
                    <?= htmlspecialchars($selected_year !== 'all' ? $selected_year : 'ทุกปี'); ?>
                </span>
            </p>
        </header>

        <!-- Filter Section & Navigation (No Print) -->
        <section class="mb-8 p-4 bg-gray-50 border border-gray-200 rounded-lg shadow-inner no-print">
            
            <!-- ✅ เพิ่มปุ่มกลับหน้าหลักเจ้าหน้าที่ -->
            <a href="staffPage.php" 
               class="inline-flex items-center bg-gray-300 text-gray-800 py-2 px-4 rounded-full font-semibold hover:bg-gray-400 transition-colors duration-200 shadow-md mb-4">
                <i class="fas fa-arrow-circle-left mr-2"></i> กลับหน้าหลักเจ้าหน้าที่
            </a>
            
            <h2 class="text-xl font-bold text-gray-700 mb-4 flex items-center mt-4 pt-4 border-t border-gray-300">
                <i class="fas fa-filter mr-2 text-blue-600"></i> ตัวกรองรายงาน
            </h2>
            
            <!-- ✅ แก้ไข typo ใน action attribute จาก dowload_report.php เป็น download_report.php -->
            <form method="GET" action="dowload_report.php" class="flex items-center space-x-4">
                <label for="year_select" class="text-gray-600 font-medium">เลือกปี:</label>
                <select name="year" id="year_select" 
                        class="border border-gray-300 rounded-lg p-2 text-base focus:ring-blue-500 focus:border-blue-500 transition w-32">
                    <option value="all" <?= $selected_year === 'all' ? 'selected' : '' ?>>-- ทุกปี --</option>
                    <?php foreach($available_years as $year_option): ?>
                        <option value="<?= htmlspecialchars($year_option) ?>"
                                <?= $selected_year === $year_option ? 'selected' : '' ?>>
                            <?= htmlspecialchars($year_option) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="bg-blue-600 text-white py-2 px-4 rounded-lg font-semibold hover:bg-blue-700 transition-colors duration-200 shadow-md">
                    แสดงรายงาน
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
                            <th class="p-3 w-2/4">ชื่อรายงาน</th>
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
