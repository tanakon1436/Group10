<?php
// === START: DEBUGGING AND ERROR REPORTING ===
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// === END: DEBUGGING AND ERROR REPORTING ===

session_start();

// ตรวจสอบว่า user ล็อกอินหรือยัง
if (!isset($_SESSION['user_id'])) {
    header("Location: login-v1.php");
    exit;
}

// ** NEW: รับค่าการค้นหาและกรองปีจาก URL **
$selected_year = isset($_GET['year']) && is_numeric($_GET['year']) ? (int)$_GET['year'] : null;
$search_term = trim($_GET['search'] ?? '');

// เชื่อมต่อฐานข้อมูล
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "group10";

$conn = new mysqli($servername, $username, $password, $dbname);
$db_error = null;
if ($conn->connect_error) {
    $db_error = "Connection failed: " . $conn->connect_error;
}

// ดึงข้อมูลผู้ใช้จาก session
$user_id = $_SESSION['user_id']; // User_id ของอาจารย์ที่ล็อกอินอยู่
$current_user_name = "ชื่อ ผู้ใช้งาน (ไม่พบข้อมูล)";
$user_role = "";

// --- กำหนดค่าเริ่มต้นสำหรับตัวแปรสถานะ ---
$status_message = null; 
$status_type = 'info'; 

if (!$db_error) {
    // 1. ดึงข้อมูลชื่อและบทบาทของผู้ใช้
    $sql_user = "SELECT first_name, last_name, role FROM User WHERE User_id = ?";
    $stmt_user = $conn->prepare($sql_user);
    if ($stmt_user) {
        $stmt_user->bind_param("i", $user_id);
        $stmt_user->execute();
        $result_user = $stmt_user->get_result();
        if ($user = $result_user->fetch_assoc()) {
            $current_user_name = htmlspecialchars($user['first_name'] . " " . $user['last_name']);
            $user_role = $user['role'];
        }
        $stmt_user->close();
    } else {
         $db_error .= (empty($db_error) ? '' : ' | ') . "User Query prepare failed: " . $conn->error;
    }
}


// *************************************************************
// 2. การจัดการการแจ้งเตือน (Notifications) - ดึงข้อมูลจริงจาก DB
// *************************************************************

$notifications = [];
$unread_count = 0;

if (!$db_error) {
    // ดึงข้อมูลการแจ้งเตือนที่ถูกส่งถึง User_id ที่ล็อกอินอยู่เท่านั้น
    $sql_noti = "SELECT Noti_id, message, date_time, status FROM Notification WHERE User_id = ? ORDER BY date_time DESC";
    $stmt_noti = $conn->prepare($sql_noti);

    if ($stmt_noti) {
        $stmt_noti->bind_param("i", $user_id);
        $stmt_noti->execute();
        $result_noti = $stmt_noti->get_result();

        while ($row = $result_noti->fetch_assoc()) {
            $is_read = ($row['status'] === 'read');
            
            // ตรวจสอบสถานะและนับ
            if (!$is_read) {
                $unread_count++;
            }

            // คำนวณเวลาที่ผ่านมา (อย่างง่าย)
            $timestamp = strtotime($row['date_time']);
            $time_diff = time() - $timestamp;

            if ($time_diff < 60) {
                $time_ago = $time_diff . ' วินาทีที่แล้ว';
            } elseif ($time_diff < 3600) {
                $time_ago = floor($time_diff / 60) . ' นาทีที่แล้ว';
            } elseif ($time_diff < 86400) {
                $time_ago = floor($time_diff / 3600) . ' ชั่วโมงที่แล้ว';
            } else {
                $time_ago = date('d/m/Y H:i', $timestamp);
            }

            $notifications[] = [
                'id' => $row['Noti_id'],
                'message' => htmlspecialchars($row['message']), 
                'time' => $time_ago,
                'is_read' => $is_read
            ];
        }
        $stmt_noti->close();
    } else {
        $db_error .= (empty($db_error) ? '' : ' | ') . "Notification Query prepare failed: " . $conn->error;
    }
}


// *************************************************************
// 3. การจัดการ POST Request สำหรับ Mark All As Read
// *************************************************************
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_read_all']) && !$db_error) {
    $sql_update = "UPDATE Notification SET status = 'read' WHERE User_id = ? AND status = 'unread'";
    $stmt_update = $conn->prepare($sql_update);
    $stmt_update->bind_param("i", $user_id);

    if ($stmt_update->execute()) {
        $stmt_update->close();
        // Redirect กลับไปที่หน้า publications.php
        header("Location: publications.php?update_status=success_read");
        exit();
    } else {
        $stmt_update->close();
        $status_message = "❌ เกิดข้อผิดพลาดในการทำเครื่องหมายว่าอ่านแล้ว: " . $conn->error;
        $status_type = 'error';
    }
}


// *************************************************************
// 4. ตรวจสอบสถานะหลังการ Redirect
// *************************************************************
if (isset($_GET['update_status']) && $_GET['update_status'] === 'success_read') {
    $status_message = "✅ ทำเครื่องหมายการแจ้งเตือนทั้งหมดว่าอ่านแล้วเรียบร้อย";
    $status_type = 'success';
    // ล้างพารามิเตอร์ GET ออกจาก URL หลังจาก 3 วินาที (ป้องกัน Form Resubmission)
    // หมายเหตุ: การใช้ header("Refresh:...") อาจไม่ทำงานในทุกสภาพแวดล้อม แต่เป็นวิธีแก้ปัญหาที่พบบ่อย
    // ในการใช้งานจริงควรใช้ JavaScript ในการล้าง URL 
    // สำหรับโค้ดนี้ จะขอแสดงผลข้อความตามปกติ
    // $redirect_url = strtok($_SERVER["REQUEST_URI"], '?');
    // header("Refresh: 3; URL=$redirect_url"); 
}


// *************************************************************
// 5. QUERY ข้อมูลผลงานตีพิมพ์ และ Filter/Search
// *************************************************************

$publications = [];
$where_clauses = ["Author_id = ?"];
$params = [$user_id];
$bind_types = "i"; // Initial binding type for User_id

// 5A. เพิ่มเงื่อนไขการค้นหาถ้ามี
if (!empty($search_term)) {
    // Search in title OR journal
    $where_clauses[] = "(title LIKE ? OR journal LIKE ?)";
    $like_term = "%{$search_term}%";
    array_push($params, $like_term, $like_term);
    $bind_types .= "ss";
}

// 5B. เพิ่มเงื่อนไขการกรองตามปีถ้ามี
if ($selected_year) {
    $where_clauses[] = "publish_year = ?";
    $params[] = $selected_year;
    $bind_types .= "i";
}

if (!$db_error) {
    $pub_sql = "SELECT Pub_id, title, journal, publish_year, status, file_path 
                FROM Publication 
                WHERE " . implode(" AND ", $where_clauses) . " 
                ORDER BY publish_year DESC";
    
    $pub_stmt = $conn->prepare($pub_sql);
    if ($pub_stmt) {
        // ใช้ call_user_func_array เพื่อ bind_param ด้วยอาร์เรย์ของพารามิเตอร์
        // ต้องส่ง types เป็นพารามิเตอร์แรก และพารามิเตอร์ที่เหลือคือค่าที่ต้องการ bind
        $bind_params = array_merge([$bind_types], $params);
        call_user_func_array([$pub_stmt, 'bind_param'], refValues($bind_params));

        $pub_stmt->execute();
        $pub_result = $pub_stmt->get_result();
        
        while ($row = $pub_result->fetch_assoc()) {
            $publications[] = $row;
        }
        $pub_stmt->close();
    } else {
        $db_error .= (empty($db_error) ? '' : ' | ') . "Publication Query prepare failed: " . $conn->error;
    }
}
// Helper function for call_user_func_array with references
function refValues($arr){
    if (strnatcmp(phpversion(),'5.3') >= 0) // PHP >= 5.3
    {
        $refs = array();
        foreach($arr as $key => $value)
            $refs[$key] = &$arr[$key];
        return $refs;
    }
    return $arr;
}

// 5C. ดึงรายการปีที่มีผลงานทั้งหมดสำหรับ Dropdown Filter
$available_years = [];
if (!$db_error) {
    $year_sql = "SELECT DISTINCT publish_year FROM Publication WHERE Author_id = ? AND publish_year IS NOT NULL ORDER BY publish_year DESC";
    $year_stmt = $conn->prepare($year_sql);
    if ($year_stmt) {
        $year_stmt->bind_param("i", $user_id);
        $year_stmt->execute();
        $year_result = $year_stmt->get_result();
        
        while ($row = $year_result->fetch_assoc()) {
            $available_years[] = (int)$row['publish_year'];
        }
        $year_stmt->close();
    }
}


// ฟังก์ชันสำหรับกำหนดสีของสถานะ
function getStatusBadge(string $status): string {
    $status = strtolower($status);
    
    switch ($status) {
        case 'approved': 
            $class = 'bg-green-100 text-green-700 border-green-300';
            $icon = 'fas fa-check-circle';
            $thaiStatus = 'อนุมัติแล้ว';
            break;
        case 'pending':
        case 'waiting': 
            $class = 'bg-yellow-100 text-yellow-700 border-yellow-300 animate-pulse';
            $icon = 'fas fa-clock';
            $thaiStatus = 'รอการอนุมัติ';
            break;
        case 'rejected':
            $class = 'bg-red-100 text-red-700 border-red-300';
            $icon = 'fas fa-times-circle';
            $thaiStatus = 'ถูกปฏิเสธ';
            break;
        case 'revision':
            $class = 'bg-orange-100 text-orange-700 border-orange-300';
            $icon = 'fas fa-pen-square';
            $thaiStatus = 'ร้องขอแก้ไข';
            break;
        default:
            $class = 'bg-gray-100 text-gray-700 border-gray-300';
            $icon = 'fas fa-info-circle';
            $thaiStatus = 'ไม่ระบุสถานะ';
    }
    
    return "<span class='inline-flex items-center px-3 py-1 text-sm font-semibold rounded-full border shadow-sm {$class}'>
                <i class='{$icon} mr-2'></i>
                {$thaiStatus}
            </span>";
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>รายการผลงานตีพิมพ์ | ระบบ จัดการการตีพิมพ์ผลงานของอาจารย์</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

  <style>
      /* กำหนดสีหลักของธีม (สีน้ำเงิน) */
      .text-theme { color: #1d4ed8; } /* blue-700 */
      .bg-theme-light { background-color: #eff6ff; } /* blue-50 */
      .hover-bg-theme { background-color: #dbeafe; } /* blue-100 */

      /* การจัดเรียงใน Header */
      .right-icons > a {
          display: flex;
          align-items: center;
          justify-content: center;
          width: 40px;
          height: 40px;
          border-radius: 9999px; /* full circle */
          color: #1d4ed8; /* blue-700 */
      }
      .right-icons > a:hover {
          background-color: #dbeafe; /* blue-100 */
      }
      .psu-logo {
          height: 100px; 
          object-fit: contain;
      }
      /* Custom style for active menu in this page */
      .menu-active {
        color: #1d4ed8 !important; /* text-blue-700 */
        background-color: #dbeafe !important; /* bg-blue-100 */
        font-weight: 600;
      }
      /* Status Message Styling */
      .status-success { background-color: #d1fae5; color: #065f46; border-color: #34d399; }
      .status-error { background-color: #fee2e2; color: #991b1b; border-color: #f87171; }
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
        <a href="publications.php" class="flex items-center p-3 rounded-lg mb-3 text-theme bg-blue-100 hover:bg-blue-200 hover:text-blue-900 font-semibold transition-colors duration-150 menu-active">
            <i class="fas fa-list-alt w-5 h-5 mr-3"></i> รายการผลงานตีพิมพ์
        </a>
        <a href="add_publication.php" class="flex items-center p-3 rounded-lg mb-3 text-gray-700 hover:bg-blue-100 hover:text-blue-700 transition-colors duration-150">
            <i class="fas fa-plus-circle w-5 h-5 mr-3"></i> เพิ่มผลงานตีพิมพ์
        </a>
        <a href="pubHis.php" class="flex items-center p-3 rounded-lg text-gray-700 hover:bg-blue-100 hover:text-blue-700 transition-colors duration-150">
            <i class="fas fa-history w-5 h-5 mr-3"></i> ประวัติการแก้ไข
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
        <h1 class="text-3xl font-bold text-gray-800">
             <i class="fas fa-list-alt text-blue-600 mr-2"></i> รายการผลงานตีพิมพ์
        </h1>
        
        <div class="flex items-center space-x-4 right-icons">
            <a href="#" id="notification-bell" title="แจ้งเตือน" class="relative">
                <i class="fas fa-bell text-2xl"></i>
                <?php if ($unread_count > 0): ?>
                    <span class="absolute top-0 right-0 block h-5 w-5 rounded-full ring-2 ring-white bg-red-500 text-white text-xs font-bold flex items-center justify-center -mt-1 -mr-1">
                        <?= $unread_count; ?>
                    </span>
                <?php endif; ?>
            </a>
            <a href="edit_profile.php" title="แก้ไขข้อมูลส่วนตัว">
                <i class="fas fa-user-circle text-2xl"></i>
            </a>
            <span class="text-gray-600 font-medium hidden sm:block hover:text-blue-700 transition-colors duration-150">
              <?= $current_user_name; ?>
            </span>
        </div>
    </header>

    <?php if ($status_message): ?>
        <div class="mb-6 p-4 rounded-lg shadow-md font-medium border-l-4 
            <?= $status_type === 'success' ? 'status-success border-green-500' : 'status-error border-red-500' ?>">
            <?= $status_message; ?>
        </div>
    <?php endif; ?>

    <div class="bg-white p-6 rounded-xl shadow-2xl">
        
        <!-- ** ส่วนที่ปรับปรุง: เพิ่มฟิลเตอร์ตามปีและค้นหา ** -->
        <div class="flex flex-col gap-4 md:flex-row md:justify-between items-start md:items-center mb-6 pb-2 border-b-2 border-blue-200">
            <h2 class="text-2xl font-bold text-gray-800 mb-2 md:mb-0">
                ผลงานตีพิมพ์ทั้งหมด (<?= count($publications); ?> รายการ)
            </h2>
            
            <form id="filter-form" action="publications.php" method="GET" class="flex flex-col sm:flex-row gap-3 w-full md:w-auto">
                <!-- Search Input -->
                <div class="relative w-full sm:w-64">
                    <input type="text" 
                           name="search"
                           id="search-input"
                           value="<?= htmlspecialchars($search_term); ?>"
                           placeholder="ค้นหาชื่อผลงาน/วารสาร..."
                           class="w-full pl-10 pr-4 py-2 text-base border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 transition duration-150 ease-in-out">
                    <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                </div>
                
                <!-- Year Filter Dropdown -->
                <div class="relative inline-block w-full sm:w-auto">
                    <label for="year-filter" class="sr-only">กรองตามปี</label>
                    <select id="year-filter" name="year"
                            class="form-select block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 rounded-md shadow-sm transition duration-150 ease-in-out">
                        
                        <option value="" <?= $selected_year === null ? 'selected' : '' ?>>-- กรองตามปีทั้งหมด --</option>
                        
                        <?php foreach ($available_years as $year): ?>
                            <option value="<?= $year; ?>" <?= $selected_year === $year ? 'selected' : '' ?>>
                                ปี <?= $year; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Hidden button to trigger form submission on change/enter -->
                <button type="submit" class="hidden"></button>
            </form>
        </div>
        <!-- ** สิ้นสุดส่วนที่ปรับปรุง ** -->

        <?php if ($db_error): ?>
            <div class="p-4 bg-red-100 text-red-700 border border-red-300 rounded-lg shadow-md mb-4">
                <p>⚠️ **ข้อผิดพลาดฐานข้อมูล:** ไม่สามารถดึงข้อมูลผลงานได้:</p>
                <p class="font-mono text-sm mt-1 break-words"><?= htmlspecialchars($db_error); ?></p>
            </div>
        <?php endif; ?>

        <?php if (empty($publications) && !$db_error): ?>
            <div class="text-center py-10 border-4 border-dashed border-gray-200 rounded-xl bg-gray-50">
                <i class="fas fa-exclamation-circle text-4xl text-gray-400 mb-3"></i>
                <p class="text-lg text-gray-600 font-semibold">
                    <?php if (!empty($search_term) || $selected_year): ?>
                        ไม่พบผลงานตีพิมพ์ที่ตรงกับเงื่อนไขการค้นหา/กรอง
                    <?php else: ?>
                        ไม่พบผลงานตีพิมพ์ในระบบ
                    <?php endif; ?>
                </p>
                <a href="add_publication.php" class="mt-4 inline-block bg-blue-600 text-white px-5 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                    <i class="fas fa-plus-circle mr-2"></i> เพิ่มผลงานตีพิมพ์ใหม่
                </a>
            </div>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($publications as $pub): 
                    $file_path = htmlspecialchars($pub['file_path'] ?? '');
                    // ตรวจสอบว่ามีไฟล์หรือไม่
                    // NOTE: ในสภาพแวดล้อมจำลอง file_exists อาจทำงานไม่ได้ หาก path ไม่ตรง
                    $has_file = !empty($file_path); 
                ?>
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center p-5 rounded-xl border-l-4 
                        <?php 
                            // กำหนดสีของเส้นขอบตามสถานะ
                            $status_lower = strtolower($pub['status']);
                            if (in_array($status_lower, ['approved'])) echo 'border-green-500 bg-green-50/70';
                            else if (in_array($status_lower, ['pending', 'waiting'])) echo 'border-yellow-500 bg-yellow-50/70';
                            else if (in_array($status_lower, ['rejected'])) echo 'border-red-500 bg-red-50/70';
                            else if (in_array($status_lower, ['revision'])) echo 'border-orange-500 bg-orange-50/70';
                            else echo 'border-gray-300 bg-gray-50';
                        ?>
                        shadow-md hover:shadow-lg transition-shadow duration-300">
                        
                        <div class="flex-1 min-w-0 mb-3 md:mb-0 md:mr-4">
                            <!-- ชื่อผลงาน -->
                            <h3 class="text-xl font-bold text-blue-800 truncate leading-snug">
                                <?= htmlspecialchars($pub['title']); ?>
                            </h3>
                            <!-- รายละเอียด -->
                            <div class="text-gray-600 text-sm mt-1 space-y-1">
                                <p><i class="fas fa-book-open mr-2 text-blue-400"></i> **วารสาร/ประชุม:** <?= htmlspecialchars($pub['journal'] ?? 'ไม่ระบุ'); ?></p>
                                
                                <p><i class="fas fa-calendar-alt mr-2 text-blue-400"></i> **ปีที่ตีพิมพ์:** <?= htmlspecialchars($pub['publish_year'] ?? 'N/A'); ?>
                                </p>
                                
                                <p class="text-xs text-gray-400 mt-1">ID: #<?= $pub['Pub_id']; ?></p>
                            </div>
                        </div>

                        <!-- สถานะและการดำเนินการ -->
                        <div class="flex flex-col items-start md:items-end space-y-2 md:space-y-1">
                            <!-- สถานะ (Badge) -->
                            <?= getStatusBadge($pub['status']); ?>

                            <!-- ปุ่มดำเนินการ -->
                            <div class="flex space-x-2 mt-3 md:mt-0">
                                <a href="edit_publication.php?id=<?= $pub['Pub_id']; ?>" 
                                   class="px-4 py-2 text-sm bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors font-medium"
                                   title="แก้ไขรายละเอียดผลงาน">
                                    <i class="fas fa-pen mr-1"></i> แก้ไข
                                </a>

                                <?php if ($has_file): ?>
                                    <!-- ปุ่ม PDF Viewer: ลิงก์ตรงไปยัง file_path และเปิดในแท็บใหม่ -->
                                    <a href="<?= $file_path; ?>" 
                                        target="_blank"
                                        class="px-4 py-2 text-sm bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors font-medium"
                                        title="ดูเอกสารในรูปแบบ PDF">
                                        <i class="fas fa-file-pdf mr-1"></i> ดู PDF
                                    </a>
                                <?php else: ?>
                                    <!-- ปุ่ม PDF Viewer แบบ Disabled เมื่อไม่มีไฟล์ -->
                                    <button disabled
                                        class="px-4 py-2 text-sm bg-gray-400 text-white rounded-lg cursor-not-allowed font-medium"
                                        title="ไม่มีไฟล์สำหรับดู">
                                        <i class="fas fa-file-pdf mr-1"></i> ไม่มีไฟล์
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</main>
</div>

<!-- Notification Modal -->
<div id="notification-modal" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-lg mx-4 overflow-hidden transform transition-all">
        <div class="flex justify-between items-center p-5 border-b bg-blue-50">
            <h3 class="text-xl font-bold text-blue-700">
                <i class="fas fa-bell mr-2"></i> ข้อความแจ้งเตือน (<?= $unread_count; ?> ข้อความใหม่)
            </h3>
            <button id="close-modal-btn" class="text-gray-500 hover:text-gray-700 text-2xl">
                &times;
            </button>
        </div>

        <div class="p-4 max-h-96 overflow-y-auto space-y-3">
            <?php if (empty($notifications)): ?>
                <p class="text-gray-500 text-center py-4">ไม่มีข้อความแจ้งเตือน</p>
            <?php else: ?>
                <?php foreach ($notifications as $notification): ?>
                    <div class="p-3 rounded-lg border 
                        <?= $notification['is_read'] ? 'bg-gray-50 border-gray-200 text-gray-700' : 'bg-blue-100 border-blue-300 font-semibold shadow-sm'; ?>">
                        <p class="text-sm flex items-center">
                            <i class="<?= $notification['is_read'] ? 'far fa-envelope-open text-gray-500' : 'fas fa-envelope text-blue-600'; ?> mr-2"></i>
                            <span class="font-bold">ข้อความ:</span>
                        </p>
                        <p class="mt-1 ml-5 text-base leading-snug break-words"><?= $notification['message']; ?></p> 
                        <p class="text-xs text-right mt-1 <?= $notification['is_read'] ? 'text-gray-500' : 'text-blue-700'; ?>">
                            <?= $notification['time']; ?>
                        </p>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <div class="p-3 border-t flex justify-end bg-gray-50">
            <!-- Form สำหรับส่งค่า Mark All As Read -->
            <form method="POST" action="publications.php" onsubmit="return confirm('คุณต้องการทำเครื่องหมายว่าอ่านแล้วทั้งหมดหรือไม่?');">
                <input type="hidden" name="mark_read_all" value="1">
                <button type="submit" 
                        class="text-blue-600 hover:text-blue-800 text-sm font-medium py-2 px-3 rounded-lg hover:bg-blue-100 transition duration-150"
                        <?= $unread_count === 0 ? 'disabled' : '' ?>>
                    <i class="fas fa-check-double mr-1"></i> ทำเครื่องหมายว่าอ่านแล้วทั้งหมด
                </button>
            </form>
        </div>
    </div>
</div>

<script>
    // ************************************************
    // JavaScript สำหรับการเปลี่ยนหน้าเมื่อเลือกปี หรือกดค้นหา
    // ************************************************
    document.addEventListener('DOMContentLoaded', function() {
        const yearFilter = document.getElementById('year-filter');
        const searchInput = document.getElementById('search-input');
        const filterForm = document.getElementById('filter-form');

        // ฟังก์ชันสำหรับอัปเดต URL เมื่อมีการเปลี่ยนค่าในฟิลเตอร์หรือค้นหา
        function updateFilterAndSearch() {
            const currentSearch = searchInput.value.trim();
            const currentYear = yearFilter.value;
            
            let url = 'publications.php?';
            const params = [];

            if (currentSearch) {
                params.push('search=' + encodeURIComponent(currentSearch));
            }

            if (currentYear) {
                params.push('year=' + encodeURIComponent(currentYear));
            }

            // ถ้ามีพารามิเตอร์ ให้สร้าง URL
            if (params.length > 0) {
                url += params.join('&');
            } else {
                url = 'publications.php';
            }

            window.location.href = url;
        }

        // 1. เมื่อมีการเปลี่ยนค่าใน Dropdown ปี
        if (yearFilter) {
            yearFilter.addEventListener('change', function() {
                updateFilterAndSearch();
            });
        }

        // 2. เมื่อมีการกด Enter ในช่องค้นหา
        if (searchInput) {
            searchInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault(); // ป้องกันการ submit แบบปกติ
                    updateFilterAndSearch();
                }
            });
        }
        
        // 3. เมื่อกดปุ่ม submit (แม้จะถูกซ่อนไว้ก็ตาม หรือถ้ามีการเพิ่มปุ่มค้นหาในอนาคต)
        filterForm.addEventListener('submit', function(e) {
            e.preventDefault();
            updateFilterAndSearch();
        });
    });

    // ************************************************
    // JavaScript สำหรับ Modal การแจ้งเตือน
    // ************************************************
    const bellIcon = document.getElementById('notification-bell');
    const modal = document.getElementById('notification-modal');
    const closeModalBtn = document.getElementById('close-modal-btn');

    // Function to open the modal
    bellIcon.addEventListener('click', (e) => {
        e.preventDefault(); 
        modal.classList.remove('hidden');
    });

    // Function to close the modal using the 'x' button
    closeModalBtn.addEventListener('click', () => {
        modal.classList.add('hidden');
    });

    // Function to close the modal when clicking outside of it
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.classList.add('hidden');
        }
    });

    // Function to close the modal when pressing the ESC key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !modal.classList.contains('hidden')) {
            modal.classList.add('hidden');
        }
    });
</script>

</body>
</html>
<?php
if (!$db_error) {
    $conn->close();
}
?>
