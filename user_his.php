<?php
// เริ่ม session
session_start();

// ตรวจสอบการเข้าสู่ระบบ: หากไม่มี session หรือ role ไม่ใช่ admin ให้ redirect ไปหน้า login
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    // ในสถานการณ์จริง ควรใช้ URL ที่ถูกต้อง เช่น login.php
    header("Location: login-v1.php");
    exit();
}

// --- เชื่อมต่อฐานข้อมูล ---
$servername = "localhost";
$db_user = "root";
$db_pass = "";
$dbname = "group10"; // ใช้ชื่อฐานข้อมูลตามที่ระบุ

$conn = new mysqli($servername, $db_user, $db_pass, $dbname);
if ($conn->connect_error) {
    // แสดงข้อความ error ที่ชัดเจน
    die("Connection failed: " . $conn->connect_error);
}

// ------------------------------------
// 1. การจัดการการค้นหาและเงื่อนไข
// ------------------------------------
// รับค่าค้นหาจาก URL
$search_query = $_GET['search'] ?? '';
// เตรียมค่าค้นหาสำหรับ LIKE operator
$search_term = "%" . $search_query . "%";
$result = null;
$error_msg = null;

try {
    // *** ปรับปรุง SQL: ใช้ UNION ALL รวม LoginHistory และ PublicationHistory แทน User_History ***
    $history_subquery = "
        (
            -- 1. ประวัติการเข้าสู่ระบบ
            SELECT
                Login_id AS history_id,
                User_id,
                time AS action_time,
                'LOGIN' AS action,
                CASE success WHEN 1 THEN 'เข้าสู่ระบบสำเร็จ' ELSE 'เข้าสู่ระบบล้มเหลว' END AS action_detail
            FROM
                LoginHistory
            
            UNION ALL
            
            -- 2. ประวัติการแก้ไขผลงาน
            SELECT
                History_id AS history_id,
                Edited_by AS User_id,
                edit_date AS action_time,
                'PUBLICATION_EDIT' AS action,
                change_detail AS action_detail
            FROM
                PublicationHistory
        )
    ";

    $sql = "
        SELECT
            uh.history_id,
            uh.action,
            uh.action_time,
            uh.action_detail,
            u.first_name,
            u.last_name,
            u.Department,
            u.role
        FROM
            $history_subquery AS uh
        JOIN
            User u ON uh.User_id = u.User_id
    ";

    $where_clauses = [];
    $param_types = "";
    $params = [];

    // เพิ่มเงื่อนไขการค้นหา
    if (!empty($search_query)) {
        // ค้นหาจากชื่อ, นามสกุล, กิจกรรม, หรือรายละเอียดกิจกรรม
        $where_clauses[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR uh.action LIKE ? OR uh.action_detail LIKE ?)";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
        $param_types = "ssss";
    }

    if (!empty($where_clauses)) {
        $sql .= " WHERE " . implode(" AND ", $where_clauses);
    }

    // เรียงลำดับจากกิจกรรมล่าสุด
    $sql .= " ORDER BY uh.action_time DESC";

    // เตรียมและดำเนินการคำสั่ง SQL
    $stmt = $conn->prepare($sql);

    if (!empty($params)) {
        // ใช้ call_user_func_array เพื่อ bind parameters ได้อย่างถูกต้อง
        $stmt->bind_param($param_types, ...$params); 
    }

    $stmt->execute();
    $result = $stmt->get_result();

} catch (mysqli_sql_exception $e) {
    // จัดการข้อผิดพลาดในการดึงข้อมูลจริง
    $error_msg = "เกิดข้อผิดพลาดในการดึงข้อมูลประวัติ: " . $e->getMessage();
}


// ------------------------------------
// 2. ข้อมูลผู้ใช้ปัจจุบันและจำนวนรายการรออนุมัติ
// ------------------------------------
$current_user_name = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];

$pending_approvals = 0;
// ดึงจำนวนรายการรออนุมัติจริงจากฐานข้อมูล
$sql_count = "SELECT COUNT(*) AS pending_count FROM Publication WHERE status = 'Waiting'"; 
$result_count = $conn->query($sql_count);

if ($result_count && $result_count->num_rows > 0) {
    $row_count = $result_count->fetch_assoc();
    $pending_approvals = (int)$row_count['pending_count'];
    $result_count->free();
}

// ------------------------------------
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ประวัติการเข้าใช้งาน - ระบบจัดการ (Admin)</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

    <style>
        /* สไตล์ Header คล้าย Home-PR/StaffPage */
        .top-header {
            background-color: #cce4f9; /* สีฟ้าอ่อน */
            padding: 1rem 2rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        /* สไตล์ตารางเพื่อให้รองรับการแสดงผลบนมือถือ */
        @media (max-width: 768px) {
            .table-responsive thead {
                display: none; /* ซ่อนหัวตารางบนมือถือ */
            }
            .table-responsive tr {
                display: block;
                margin-bottom: 0.75rem;
                border-bottom: 2px solid #e5e7eb;
            }
            .table-responsive td {
                display: block;
                text-align: right;
                padding-left: 50%; /* เว้นที่ให้ label */
                position: relative;
            }
            .table-responsive td::before {
                content: attr(data-label);
                position: absolute;
                left: 0;
                width: 50%;
                padding-left: 1rem;
                font-weight: bold;
                text-align: left;
                color: #374151;
            }
        }
    </style>
</head>
<body class="bg-gray-50 font-sans flex min-h-screen">

<!-- Sidebar -->
<aside class="w-64 bg-white shadow-xl p-6 flex flex-col sticky top-0 h-screen z-10">
    <!-- สีน้ำเงิน -->
    <h2 class="text-2xl font-extrabold text-blue-700 mb-6 border-b pb-4">Admin Menu</h2>
    <nav class="w-full flex-grow">
        <!-- จัดการข้อมูลอาจารย์ (ลิงก์ปกติ) -->
        <a href="Admin-manage.php" class="flex items-center p-3 rounded-xl mb-3 text-gray-700 hover:bg-blue-50 hover:text-blue-700 font-semibold transition-colors duration-150">
            <i class="fas fa-users-cog w-5 h-5 mr-3"></i> จัดการข้อมูลอาจารย์
        </a>
        
        <!-- ประวัติการเข้าใช้งาน (ลิงก์ที่กำลังใช้งาน) -->
        <a href="user_his.php" class="flex items-center p-3 rounded-xl mb-3 text-white bg-blue-600 shadow-md hover:bg-blue-700 font-semibold transition-colors duration-150">
            <i class="fas fa-history w-5 h-5 mr-3"></i> ประวัติการเข้าใช้งาน
        </a>
        
        <!-- เมนูอื่นๆ (ยึดตามโค้ดเดิม) -->
        <a href="usermannual.php" class="flex items-center p-3 rounded-xl text-gray-700 hover:bg-blue-50 hover:text-blue-700 transition-colors duration-150">
            <i class="fas fa-book w-5 h-5 mr-3"></i> คู่มือการใช้งานระบบ
        </a>
        <a href="check_credentials.php" class="flex items-center p-3 rounded-xl text-gray-700 hover:bg-blue-50 hover:text-blue-700 transition-colors duration-150">
            <i class="fas fa-key w-5 h-5 mr-3"></i> ตรวจสอบชื่อ/รหัสผ่าน
        </a>
        
        <hr class="my-6 border-gray-200">
        
        <!-- ปุ่มออกจากระบบ: สีแดง -->
        <a href="logout.php" class="flex items-center p-3 rounded-lg text-red-500 hover:bg-red-50 transition-colors duration-150">
            <i class="fas fa-sign-out-alt w-5 h-5 mr-3"></i> ออกจากระบบ
        </a>
    </nav>
</aside>

<!-- Main Content Wrapper: เพิ่ม min-w-0 เพื่อป้องกันการบีบ Sidebar -->
<div class="flex-1 flex flex-col min-w-0">
    <!-- Header -->
    <header class="top-header flex items-center justify-between sticky top-0 z-10">
        <h1 class="text-xl md:text-2xl font-bold text-gray-700 tracking-wide">
            <i class="fas fa-history mr-2 text-blue-800"></i> ประวัติการเข้าใช้งานของผู้ใช้
        </h1>
        <div class="flex items-center space-x-4">
            
            <span class="text-gray-700 font-medium hidden sm:block text-sm">
                ผู้ดูแลระบบ: <?= htmlspecialchars($current_user_name); ?>
            </span>
            <!-- สีน้ำเงิน -->
            <div class="w-8 h-8 rounded-full bg-blue-700 text-white flex items-center justify-center font-bold">A</div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-1 p-8">
        <h2 class="text-3xl font-bold text-gray-800 mb-6 border-b pb-3">บันทึกกิจกรรมของผู้ใช้ทั้งหมด</h2>
        
        <?php if (isset($error_msg) && $error_msg): ?>
             <div class="mb-4 p-3 bg-red-100 text-red-700 border border-red-300 rounded-lg shadow">
                 <?= htmlspecialchars($error_msg); ?>
             </div>
        <?php endif; ?>

        <!-- Search Form -->
        <div class="flex justify-between items-center mb-6">
            <form method="GET" action="user_his.php" class="flex w-full md:w-2/3">
                <input type="text" name="search" placeholder="ค้นหาชื่อผู้ใช้ กิจกรรม หรือรายละเอียด..." 
                        value="<?= htmlspecialchars($search_query); ?>"
                        class="flex-grow px-5 py-3 border border-gray-300 rounded-l-full text-base focus:ring-4 focus:ring-blue-300 focus:border-blue-500 transition-all shadow-inner">
                <button type="submit" class="bg-blue-600 text-white px-6 py-3 rounded-r-full font-bold hover:bg-blue-700 transition-colors duration-200 shadow-lg">
                    <i class="fas fa-search"></i>
                </button>
            </form>
        </div>


        <!-- Table/Card Data Section -->
        <div class="bg-white p-6 rounded-2xl shadow-2xl overflow-x-auto">
            <?php if (!empty($search_query) && $result): ?>
                <p class="text-lg font-semibold text-gray-700 mb-4">
                    ผลการค้นหา: **<?= $result->num_rows; ?>** รายการ 
                </p>
            <?php endif; ?>
            
            <?php if ($result && $result->num_rows > 0): ?>
                <table class="min-w-full bg-white border-collapse table-responsive">
                    <thead>
                        <tr class="bg-blue-100 text-blue-800 font-bold uppercase text-sm leading-normal">
                            <th class="py-3 px-6 text-left">เวลา/วันที่</th>
                            <th class="py-3 px-6 text-left">ผู้ใช้งาน</th>
                            <th class="py-3 px-6 text-left">บทบาท</th>
                            <th class="py-3 px-6 text-left">กิจกรรม</th>
                            <th class="py-3 px-6 text-left">รายละเอียด</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-700 text-sm font-light">
                        <?php while($row = $result->fetch_assoc()): 
                            // กำหนดสีตามบทบาท
                            $role_color = ($row['role'] === 'staff') ? 'bg-green-100 text-green-700' : 
                                          (($row['role'] === 'admin') ? 'bg-red-100 text-red-700' : 'bg-indigo-100 text-indigo-700');
                            
                            // จัดรูปแบบเวลา
                            $action_time = date('Y-m-d H:i:s', strtotime($row['action_time']));
                        ?>
                            <tr class="border-b border-gray-200 hover:bg-gray-100 transition-colors">
                                <td data-label="เวลา/วันที่" class="py-3 px-6 text-left whitespace-nowrap">
                                    <div class="font-medium text-gray-900"><?= htmlspecialchars($action_time); ?></div>
                                </td>
                                <td data-label="ผู้ใช้งาน" class="py-3 px-6 text-left">
                                    <div class="flex items-center">
                                        <div class="mr-2">
                                            <i class="fas fa-user-circle text-blue-500"></i>
                                        </div>
                                        <span><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></span>
                                    </div>
                                </td>
                                <td data-label="บทบาท" class="py-3 px-6 text-left">
                                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full <?= $role_color; ?>">
                                        <?= htmlspecialchars($row['role']); ?>
                                    </span>
                                </td>
                                <td data-label="กิจกรรม" class="py-3 px-6 text-left">
                                    <span class="text-sm font-bold text-gray-800"><?= htmlspecialchars($row['action']); ?></span>
                                </td>
                                <td data-label="รายละเอียด" class="py-3 px-6 text-left max-w-xs truncate" title="<?= htmlspecialchars($row['action_detail'] ?? 'ไม่มีรายละเอียด'); ?>">
                                    <?= htmlspecialchars($row['action_detail'] ?? '-'); ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="text-center py-8 text-gray-500">
                    <i class="fas fa-exclamation-circle mr-2"></i> 
                    <?php if (!empty($search_query)): ?>
                        ไม่พบประวัติการเข้าใช้งานตามการค้นหา
                    <?php else: ?>
                        ไม่พบประวัติการเข้าใช้งานในระบบ
                    <?php endif; ?>
                </p>
            <?php endif; ?>
        </div>
    </main>

    <!-- Footer -->
    <footer class="p-4 bg-gray-200 text-center text-gray-600 text-sm">
        &copy; <?php echo date("Y"); ?> ระบบจัดการผลงาน | มหาวิทยาลัยสงขลานครินทร์ (PSU)
    </footer>

</div> <!-- End of flex-1 flex flex-col -->

<script>
    /**
     * ฟังก์ชันนำทางไปยังหน้าจัดการอนุมัติ (approved.php)
     */
    function redirectToApprovals() {
        const url = "approved.php";
        // เปลี่ยน location เพื่อนำทางไปยังหน้า approved.php ทันที
        window.location.href = url; 
    }
</script>

</body>
</html>

<?php 
// ปิดการเชื่อมต่อฐานข้อมูล
if (isset($stmt) && $stmt instanceof mysqli_stmt) {
    $stmt->close();
}
if (isset($conn)) {
    $conn->close(); 
}
?>
