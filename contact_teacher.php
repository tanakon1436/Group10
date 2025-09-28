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

// 1. เชื่อมฐานข้อมูล
$conn = new mysqli("localhost","root","","group10");
if($conn->connect_error) {
    $db_error = "เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล: " . $conn->connect_error;
    $pending_count = 0;
} else {
    $db_error = null;
}

// --- กำหนดค่าเริ่มต้นสำหรับตัวแปรสถานะ ---
$status_message = null; 
$status_type = 'info'; 

// ดึงข้อมูลผู้ใช้ที่เข้าสู่ระบบจริงจาก SESSION
$current_user_name = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];
$staff_user_id = $_SESSION['user_id']; // ID ของเจ้าหน้าที่ผู้ส่ง

// 2. ดึงจำนวนผลงานที่รออนุมัติสำหรับแสดงใน Notification Bell
$pending_count = 0;
if (!$db_error) {
    // โค้ดนี้ใช้ status = 'waiting' ตามข้อมูล Publication ที่คุณให้มา
    $sql_count = "SELECT COUNT(*) AS count FROM Publication WHERE status = 'waiting'";
    $result_count = $conn->query($sql_count);
    if ($result_count && $row = $result_count->fetch_assoc()) {
        $pending_count = (int)$row['count'];
    }
}

// =================================================================
// 3. โค้ดส่วนหลัก: ดึงรายชื่ออาจารย์ทั้งหมดจาก DB (User.role = 'normal')
// =================================================================
$teachers = [];
if (!$db_error) {
    // ดึงผู้ใช้ที่มี role เป็น 'normal' (สมมติว่าเป็นอาจารย์/ผู้ใช้ที่ต้องการติดต่อ)
    $sql_teachers = "SELECT User_id, first_name, last_name, email FROM User WHERE role = 'normal' ORDER BY last_name ASC";
    $result_teachers = $conn->query($sql_teachers);
    
    if ($result_teachers) {
        while ($row = $result_teachers->fetch_assoc()) {
            $teachers[] = $row;
        }
    } else {
        $status_message = "❌ เกิดข้อผิดพลาดในการดึงข้อมูลอาจารย์: " . $conn->error;
        $status_type = 'error';
    }
}

// =================================================================
// 4. การจัดการ POST Request จาก Modal เพื่อส่งข้อความ/สถานะ
// =================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$db_error) {
    if (isset($_POST['teacher_id'], $_POST['status_action'])) {
        $teacher_id = (int)$_POST['teacher_id']; // ID ผู้รับ
        $status_action = $_POST['status_action'];
        $message = trim($_POST['message'] ?? ''); // ค่าว่างเปล่า

        // --- 1. ดึงชื่ออาจารย์เพื่อแสดงผลในข้อความสำเร็จ ---
        $stmt_teacher = $conn->prepare("SELECT first_name, last_name FROM User WHERE User_id = ?");
        $stmt_teacher->bind_param("i", $teacher_id);
        $stmt_teacher->execute();
        $teacher_res = $stmt_teacher->get_result()->fetch_assoc();
        $teacher_name = $teacher_res ? $teacher_res['first_name'] . ' ' . $teacher_res['last_name'] : 'ผู้ใช้ท่านหนึ่ง';
        $stmt_teacher->close();

        // --- 2. กำหนดข้อความสำหรับแสดงผลและข้อความแจ้งเตือน ---
        $status_text_thai = match($status_action) {
            'revision' => 'แจ้งให้ทำการแก้ไขผลงาน',
            'approved' => 'แจ้งผลงานได้รับการอนุมัติสำเร็จ',
            'rejected' => 'แจ้งปฎิเสธผลงาน',
            default => 'ส่งข้อความสถานะ'
        };

        // ข้อความที่จะบันทึกในตาราง Notification (ถึงผู้รับ)
        $notification_message = "เจ้าหน้าที่ **{$current_user_name}** ได้ส่งข้อความ: '{$status_text_thai}' ถึงคุณ";
        $current_datetime = date('Y-m-d H:i:s');
        $notification_status = 'unread'; // สถานะเริ่มต้น

        // --- 3. บันทึก Notification ลงในฐานข้อมูล (User_id คือ ID ของผู้รับ) ---
        // เราละเว้น Pub_id เพราะหน้านี้ไม่เชื่อมโยงกับ Publication ID
        $sql_insert_noti = "INSERT INTO Notification (User_id, message, date_time, status) VALUES (?, ?, ?, ?)";
        $stmt_noti = $conn->prepare($sql_insert_noti);

        if (!$stmt_noti) {
            $status_message = "❌ Error Preparing Notification Statement: " . $conn->error;
            $status_type = 'error';
        } else {
            $stmt_noti->bind_param("isss", $teacher_id, $notification_message, $current_datetime, $notification_status);
            
            if ($stmt_noti->execute()) {
                $stmt_noti->close();
                // Redirect พร้อมข้อความ Success
                header("Location: contact_teacher.php?update_status=success&action=" . urlencode($status_action) . "&name=" . urlencode($teacher_name));
                exit();
            } else {
                // Handle DB insertion error
                $status_message = "❌ เกิดข้อผิดพลาดในการบันทึกการแจ้งเตือน: " . $stmt_noti->error;
                $status_type = 'error';
                $stmt_noti->close();
            }
        }
    }
}

// ตรวจสอบสถานะหลังการ Redirect
if (isset($_GET['update_status']) && $_GET['update_status'] === 'success') {
    $teacher_name = htmlspecialchars($_GET['name'] ?? 'อาจารย์');
    $action_code = $_GET['action'];
    $action_text = match($action_code) {
        'revision' => 'แจ้งแก้ไขผลงาน',
        'approved' => 'แจ้งผลงานอนุมัติสำเร็จ',
        'rejected' => 'แจ้งปฎิเสธผลงาน',
        default => 'ส่งข้อความสถานะ'
    };

    $status_message = "✅ บันทึกการแจ้งเตือน '{$action_text}' ถึง **{$teacher_name}** เรียบร้อยแล้ว (การแจ้งเตือนถูกบันทึกในตาราง Notification)";
    $status_type = 'success';
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ติดต่ออาจารย์</title>
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
        
        /* สไตล์สำหรับ Modal ติดต่ออาจารย์ */
        .contact-modal {
            z-index: 9999; 
            backdrop-filter: blur(3px);
            background-color: rgba(0, 0, 0, 0.6);
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
        <a href="Staff_manage.php" class="flex items-center p-3 rounded-xl text-gray-700 hover:bg-blue-50 hover:text-blue-700 transition-colors duration-150">
            <i class="fas fa-edit w-5 h-5 mr-3"></i> แก้ไขข้อมูลอาจารย์
        </a>
        <a href="staff_addTeacher.php" class="flex items-center p-3 rounded-xl text-gray-700 hover:bg-blue-50 hover:text-blue-700 transition-colors duration-150">
            <i class="fas fa-user-plus w-5 h-5 mr-3"></i> เพิ่มข้อมูลอาจารย์/เจ้าหน้าที่
        </a>
        <a href="contact_teacher.php" class="flex items-center p-3 rounded-xl text-white bg-blue-600 shadow-md hover:bg-blue-700 font-semibold transition-colors duration-150">
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
            <i class="fas fa-comments mr-2 text-blue-800"></i> ติดต่ออาจารย์เพื่อแจ้งสถานะ
        </h1>
        <!-- Notification Badge และ User Info -->
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
            <a href="#" title="โปรไฟล์ผู้ใช้งาน">
                <i class="fas fa-user-circle text-xl"></i>
            </a>
        </div>
    </header>

    <main class="p-8">
         <!-- กล่องข้อความสถานะ/ข้อผิดพลาด (Status Message Box) -->
        <?php if ($db_error): ?>
             <div class="mb-6 p-4 rounded-lg shadow-md font-medium border-l-4 status-error border-red-500">
                <h2 class="font-bold text-lg">⚠️ เกิดข้อผิดพลาดร้ายแรง</h2>
                <p>โปรดตรวจสอบการตั้งค่าฐานข้อมูล: <?= htmlspecialchars($db_error); ?></p>
            </div>
        <?php elseif ($status_message): ?>
            <div class="mb-6 p-4 rounded-lg shadow-md font-medium border-l-4 
                <?= $status_type === 'success' ? 'status-success border-green-500' : 'status-error border-red-500' ?>">
                <?= $status_message; ?>
            </div>
        <?php endif; ?>
        
        <section class="bg-white p-6 rounded-2xl shadow-2xl">
            <h2 class="text-2xl font-bold text-gray-800 mb-6 border-b pb-2 flex items-center">
                <i class="fas fa-chalkboard-teacher mr-2 text-blue-600"></i> รายชื่อผู้ใช้/อาจารย์ทั้งหมด (<?= count($teachers); ?> ท่าน)
            </h2>

            <?php if (empty($teachers)): ?>
                <div class="p-6 bg-yellow-50 border border-yellow-300 rounded-xl text-yellow-800 shadow-md">
                    <p class="font-semibold"><i class="fas fa-exclamation-triangle mr-2"></i> ไม่พบข้อมูลผู้ใช้ในระบบ</p>
                    <p class="text-sm mt-1">โปรดตรวจสอบว่าคุณมีข้อมูลในตาราง `User` ที่มี `role` เป็น `'normal'` หรือไม่</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                    <?php foreach ($teachers as $teacher): ?>
                        
                        <!-- CARD ผู้ใช้/อาจารย์ -->
                        <div class="bg-theme-light p-5 rounded-xl border border-blue-300 shadow-md flex flex-col justify-between hover:shadow-lg transition-shadow duration-300">
                            
                            <!-- ส่วนชื่อและอีเมล -->
                            <div class="mb-4 pb-3 border-b border-blue-200">
                                <h3 class="text-lg font-bold text-blue-800">
                                    <i class="fas fa-user-circle mr-2"></i> <?= htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?>
                                </h3>
                                <p class="text-sm text-gray-600 truncate mt-1"><i class="fas fa-envelope mr-1"></i> <?= htmlspecialchars($teacher['email'] ?? 'ไม่มีอีเมล'); ?></p>
                            </div>

                            <!-- ปุ่มติดต่อ (อยู่ตรงกลางด้านล่างและกว้างเต็ม) -->
                            <button type="button" 
                                    onclick="openContactModal(<?= $teacher['User_id']; ?>, '<?= htmlspecialchars(addslashes($teacher['first_name'] . ' ' . $teacher['last_name'])); ?>')"
                                    class="w-full flex items-center justify-center text-white bg-blue-600 hover:bg-blue-700 font-semibold transition-colors py-2 rounded-lg shadow-md text-sm mt-2">
                                <i class="fas fa-paper-plane mr-2"></i> ติดต่ออาจารย์
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <footer class="p-4 bg-gray-200 text-center text-gray-600 text-sm mt-auto">
        &copy; <?php echo date("Y"); ?> ระบบจัดการการตีพิมพ์ผลงาน | มหาวิทยาลัยสงขลานครินทร์ (PSU)
    </footer>
</div>


<!-- ========================================================== -->
<!-- Modal สำหรับติดต่ออาจารย์ (ซ่อนอยู่) -->
<!-- ========================================================== -->
<div id="contactModal"
     class="contact-modal fixed inset-0 hidden flex items-center justify-center p-4">
    
    <div class="bg-white w-full max-w-lg rounded-xl shadow-2xl flex flex-col">
        <form method="POST" action="contact_teacher.php">
            
            <!-- ส่วนหัวของ Modal -->
            <div class="p-5 border-b border-gray-200 flex justify-between items-center bg-blue-600 rounded-t-xl">
                <h2 class="text-xl font-bold text-white flex items-center">
                    <i class="fas fa-envelope-open-text mr-2"></i> ส่งข้อความถึง <span id="modalTeacherName" class="ml-2">...</span>
                </h2>
                <button type="button" onclick="closeContactModal()"
                        class="text-blue-200 hover:text-white p-1 rounded-full hover:bg-blue-700 transition">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <!-- ส่วนเนื้อหา Modal -->
            <div class="p-6 space-y-5">
                <input type="hidden" name="teacher_id" id="teacherIdInput">
                <input type="hidden" name="message" value=""> <!-- ซ่อน field message ตามคำขอ -->
                
                <p class="text-gray-700 font-semibold text-lg border-b pb-2">1. เลือกสถานะที่ต้องการแจ้ง:</p>
                
                <!-- กลุ่ม Radio Buttons 3 สถานะ -->
                <div class="space-y-3">
                    <label class="flex items-center p-3 bg-red-50 border border-red-300 rounded-lg cursor-pointer hover:bg-red-100 transition-colors">
                        <input type="radio" name="status_action" value="revision" required
                               class="form-radio h-5 w-5 text-red-600">
                        <span class="ml-3 font-medium text-red-800">แจ้งแก้ไขผลงาน</span>
                        <span class="text-xs ml-auto text-red-600">(สำหรับแจ้งให้อาจารย์แก้ไขผลงาน)</span>
                    </label>
                    
                    <label class="flex items-center p-3 bg-green-50 border border-green-300 rounded-lg cursor-pointer hover:bg-green-100 transition-colors">
                        <input type="radio" name="status_action" value="approved" required
                               class="form-radio h-5 w-5 text-green-600">
                        <span class="ml-3 font-medium text-green-800">แจ้งผลงานอนุมัติสำเร็จ</span>
                        <span class="text-xs ml-auto text-green-600">(สำหรับแจ้งว่าผลงานถูกอนุมัติเรียบร้อย)</span>
                    </label>
                    
                    <label class="flex items-center p-3 bg-yellow-50 border border-yellow-300 rounded-lg cursor-pointer hover:bg-yellow-100 transition-colors">
                        <input type="radio" name="status_action" value="rejected" required
                               class="form-radio h-5 w-5 text-yellow-800">
                        <span class="ml-3 font-medium text-yellow-800">แจ้งปฎิเสธผลงาน</span>
                        <span class="text-xs ml-auto text-yellow-600">(สำหรับแจ้งว่าผลงานถูกปฏิเสธ)</span>
                    </label>
                </div>
                
                <!-- ส่วน 'ข้อความเพิ่มเติม' ถูกลบออกตามคำขอ -->
                
            </div>

            <!-- ส่วนท้าย Modal (ปุ่มส่ง) -->
            <div class="p-5 border-t border-gray-200 flex justify-end space-x-3 bg-gray-50 rounded-b-xl">
                <button type="button" onclick="closeContactModal()"
                        class="px-5 py-2 border border-gray-300 rounded-full text-gray-700 hover:bg-gray-200 transition-colors font-medium shadow-sm">
                    ยกเลิก
                </button>
                <button type="submit"
                        class="px-5 py-2 bg-green-600 text-white rounded-full hover:bg-green-700 transition-colors font-bold shadow-lg">
                    <i class="fas fa-check-circle mr-2"></i> ยืนยันและส่งข้อความ
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    const contactModal = document.getElementById('contactModal');
    const teacherIdInput = document.getElementById('teacherIdInput');
    const modalTeacherName = document.getElementById('modalTeacherName');

    /**
     * เปิด Modal สำหรับส่งข้อความ
     * @param {number} teacherId - ID ของอาจารย์ที่เลือก
     * @param {string} teacherName - ชื่อเต็มของอาจารย์
     */
    function openContactModal(teacherId, teacherName) {
        // 1. ตั้งค่า ID และชื่ออาจารย์ใน Modal
        teacherIdInput.value = teacherId;
        modalTeacherName.textContent = teacherName;
        
        // 2. ล้างค่าสถานะและข้อความเก่า (สำคัญ: ต้องรีเซ็ต radio button ทุกครั้ง)
        document.querySelector('#contactModal form').reset();
        
        // 3. แสดง Modal
        contactModal.classList.remove('hidden');
        contactModal.classList.add('flex');
    }

    /**
     * ปิด Modal
     */
    function closeContactModal() {
        contactModal.classList.add('hidden');
        contactModal.classList.remove('flex');
    }

    // Event Listener สำหรับการคลิกนอก Modal เพื่อปิด
    contactModal.addEventListener('click', (e) => {
        // ตรวจสอบว่าคลิกที่พื้นหลัง Modal ไม่ใช่ตัวเนื้อหา Modal
        if (e.target === contactModal) {
            closeContactModal();
        }
    });
</script>

</body>
</html>
<?php 
// ปิดการเชื่อมต่อฐานข้อมูลเมื่อสิ้นสุดการทำงานของสคริปต์
if (!$db_error) {
    $conn->close(); 
}
?>
