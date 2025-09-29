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
$db_error = null;
if($conn->connect_error) {
    $db_error = "เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล: " . $conn->connect_error;
}

// --- กำหนดค่าคงที่และตัวแปรเริ่มต้น ---
$status_message = null; 
$status_type = 'info'; 
$current_user_id = $_SESSION['user_id'];
$current_user_data = [];

// ดึงข้อมูลผู้ใช้ที่เข้าสู่ระบบจริงจาก SESSION
$current_user_name = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];
$pending_count = 0; // ต้องดึงจำนวนผลงานที่รออนุมัติเหมือนเดิมเพื่อใช้ใน Header

// 2. ดึงจำนวนผลงานที่รออนุมัติสำหรับแสดงใน Notification Bell
if (!$db_error) {
    $sql_count = "SELECT COUNT(*) AS count FROM Publication WHERE status = 'waiting'";
    $result_count = $conn->query($sql_count);
    if ($result_count && $row = $result_count->fetch_assoc()) {
        $pending_count = (int)$row['count'];
    }
}

// 3. ดึงข้อมูลผู้ใช้ปัจจุบันเพื่อแสดงในฟอร์ม
if (!$db_error) {
    // ต้องดึงคอลัมน์ password มาด้วยเพื่อใช้ในการตรวจสอบรหัสผ่านปัจจุบัน
    $stmt_fetch = $conn->prepare("SELECT User_id, first_name, last_name, email, password FROM User WHERE User_id = ?");
    if ($stmt_fetch) {
        $stmt_fetch->bind_param("i", $current_user_id);
        if ($stmt_fetch->execute()) {
            $result = $stmt_fetch->get_result();
            if ($result->num_rows === 1) {
                $current_user_data = $result->fetch_assoc();
            } else {
                 $status_message = "❌ ไม่พบข้อมูลผู้ใช้งานในระบบ";
                 $status_type = 'error';
            }
        } else {
            $status_message = "❌ เกิดข้อผิดพลาดในการดึงข้อมูลผู้ใช้: " . $stmt_fetch->error;
            $status_type = 'error';
        }
        $stmt_fetch->close();
    }
}


// 4. การจัดการ POST Request เพื่ออัปเดตข้อมูล
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$db_error) {
    
    // --- A. ตรรกะสำหรับการอัปเดตข้อมูลส่วนตัว (Profile Update Logic) ---
    // ตรวจสอบว่าเป็นการอัปเดตโปรไฟล์ (ไม่มี password)
    if (isset($_POST['first_name'], $_POST['last_name'], $_POST['email']) && !isset($_POST['password'])) {
        
        // รับและทำความสะอาดข้อมูล
        $new_first_name = trim($_POST['first_name']);
        $new_last_name = trim($_POST['last_name']);
        $new_email = trim($_POST['email']);
        
        // ตรวจสอบความถูกต้องของอีเมล
        if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            $status_message = "❌ รูปแบบอีเมลไม่ถูกต้อง กรุณาตรวจสอบอีกครั้ง";
            $status_type = 'error';
        } else if (empty($new_first_name) || empty($new_last_name)) {
            $status_message = "❌ ชื่อและนามสกุลต้องไม่เป็นค่าว่าง";
            $status_type = 'error';
        } else {
            // เตรียมคำสั่ง SQL สำหรับอัปเดต
            $stmt_update = $conn->prepare("UPDATE User SET first_name = ?, last_name = ?, email = ? WHERE User_id = ?");
            
            if ($stmt_update === false) {
                 $status_message = "❌ เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL: " . $conn->error;
                 $status_type = 'error';
            } else {
                $stmt_update->bind_param("sssi", $new_first_name, $new_last_name, $new_email, $current_user_id);

                if ($stmt_update->execute()) {
                    // Success: อัปเดตข้อมูล Session และแสดงผลลัพธ์
                    $_SESSION['first_name'] = $new_first_name;
                    $_SESSION['last_name'] = $new_last_name;
                    
                    // ดึงข้อมูลใหม่มาแสดงในฟอร์มทันที
                    $current_user_data['first_name'] = $new_first_name;
                    $current_user_data['last_name'] = $new_last_name;
                    $current_user_data['email'] = $new_email;
                    
                    $current_user_name = $new_first_name . ' ' . $new_last_name;
                    
                    $status_message = "✅ อัปเดตข้อมูลส่วนตัวเรียบร้อยแล้ว";
                    $status_type = 'success';
                } else {
                    // Error: แสดงข้อผิดพลาดจากฐานข้อมูล
                    $status_message = "❌ เกิดข้อผิดพลาดในการอัปเดตข้อมูล: " . $stmt_update->error;
                    $status_type = 'error';
                }
                $stmt_update->close();
            }
        }
    }
    
    // --- B. ตรรกะสำหรับการเปลี่ยนรหัสผ่าน (Password Change Logic) ---
    // ตรวจสอบว่าเป็นการเปลี่ยนรหัสผ่าน (มี password)
    if (isset($_POST['password'], $_POST['new_password'], $_POST['confirm_new_password'])) {
        
        $password = $_POST['password'];
        $new_password = $_POST['new_password'];
        $confirm_new_password = $_POST['confirm_new_password'];

        // 1. ตรวจสอบข้อมูลเบื้องต้น
        if (empty($password) || empty($new_password) || empty($confirm_new_password)) {
            $status_message = "❌ กรุณากรอกรหัสผ่านปัจจุบัน รหัสผ่านใหม่ และยืนยันรหัสผ่านใหม่ให้ครบถ้วน";
            $status_type = 'error';
        } else if ($new_password !== $confirm_new_password) {
            $status_message = "❌ รหัสผ่านใหม่และการยืนยันรหัสผ่านใหม่ไม่ตรงกัน";
            $status_type = 'error';
        } 
        // NOTE: ไม่มีการตรวจสอบความยาวรหัสผ่านใหม่
        else {
            // 2. ดึงรหัสผ่านปัจจุบันจากฐานข้อมูล
            // ใช้ข้อมูลที่ดึงมาแล้วจาก $current_user_data เพื่อประหยาดการ Query ซ้ำ
            if (isset($current_user_data['password'])) {
                $stored_password = $current_user_data['password'];

                // 3. ตรวจสอบรหัสผ่านปัจจุบันที่ผู้ใช้กรอก
                // *** FIX: เนื่องจาก DB เก็บเป็น Plain Text จึงต้องใช้การเปรียบเทียบค่าโดยตรง (===) ***
                // *** หาก DB เก็บเป็น HASHED ต้องใช้ password_verify() แทน ***
                if ($password === $stored_password) {
                    
                    // 4. อัปเดตรหัสผ่านใหม่ (บันทึกเป็น Plain Text)
                    $password_to_save = $new_password; 

                    $stmt_update_pass = $conn->prepare("UPDATE User SET password = ? WHERE User_id = ?");
                    $stmt_update_pass->bind_param("si", $password_to_save, $current_user_id);

                    if ($stmt_update_pass->execute()) {
                        $status_message = "✅ เปลี่ยนรหัสผ่านเรียบร้อยแล้ว ท่านจะถูกนำไปหน้าเข้าสู่ระบบใน 3 วินาที";
                        $status_type = 'success';
                        // สั่ง Redirect ไปหน้า Login หลังจากเปลี่ยนรหัสผ่านสำเร็จ
                        header("Refresh: 3; URL=logout.php");
                    } else {
                        $status_message = "❌ เกิดข้อผิดพลาดในการเปลี่ยนรหัสผ่าน: " . $stmt_update_pass->error;
                        $status_type = 'error';
                    }
                    $stmt_update_pass->close();
                } else {
                    $status_message = "❌ รหัสผ่านปัจจุบันไม่ถูกต้อง กรุณาตรวจสอบอีกครั้ง";
                    $status_type = 'error';
                }
            } else {
                $status_message = "❌ ไม่พบข้อมูลรหัสผ่านสำหรับตรวจสอบ";
                $status_type = 'error';
            }
        }
    }
}
// หากมีการ POST ข้อมูลและอัปเดตสำเร็จ/ไม่สำเร็จ ให้ดึงข้อมูลล่าสุด (ถ้ามี) มาแสดงในฟอร์มอีกครั้งเพื่อป้องกันข้อมูลหายจากการรีเฟรช

?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แก้ไขข้อมูลส่วนตัว (Staff)</title>
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
    </style>
</head>
<body class="flex min-h-screen font-sans bg-gray-50">

<aside class="w-64 bg-white shadow-xl p-6 flex flex-col sticky top-0 h-screen z-10">
    <h2 class="text-2xl font-extrabold text-blue-800 mb-6 border-b pb-4">Staff Menu</h2>
    <nav class="w-full flex-grow">
        <!-- New active link: แก้ไขข้อมูลส่วนตัว -->
        <a href="staff_edit_profile.php" class="flex items-center p-3 rounded-xl mb-3 text-white bg-blue-600 shadow-md hover:bg-blue-700 font-semibold transition-colors duration-150">
            <i class="fas fa-user-cog w-5 h-5 mr-3"></i> แก้ไขข้อมูลส่วนตัว
        </a>
        <a href="staffPage.php" class="flex items-center p-3 rounded-xl mb-3 text-gray-700 hover:bg-blue-50 hover:text-blue-700 transition-colors duration-150">
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
            <i class="fas fa-user-cog mr-2 text-blue-800"></i> แก้ไขข้อมูลส่วนตัว (Staff)
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
            <a href="staff_edit_profile.php" title="โปรไฟล์ผู้ใช้งาน" class="bg-blue-100">
                <i class="fas fa-user-circle text-xl"></i>
            </a>
        </div>
    </header>

    <main class="p-8">
        <h2 class="text-3xl font-extrabold text-gray-800 mb-6 border-b pb-2">จัดการโปรไฟล์ผู้ใช้งาน</h2>
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
        
        <!-- ส่วนที่ 1: อัปเดตข้อมูลส่วนตัว -->
        <section class="bg-white p-8 rounded-2xl shadow-2xl max-w-2xl mx-auto mb-10">
            <h3 class="text-2xl font-bold text-blue-700 mb-6 border-b pb-3">แก้ไขข้อมูลส่วนตัว</h3>
            <form method="POST" action="staff_edit_profile.php">
                
                <!-- รหัสผู้ใช้งาน (แสดงอย่างเดียว) -->
                <div class="mb-6">
                    <label for="user_id" class="block text-sm font-medium text-gray-600 mb-2">รหัสผู้ใช้งาน (Staff ID)</label>
                    <input type="text" id="user_id" name="user_id" readonly
                           value="<?= htmlspecialchars($current_user_data['User_id'] ?? 'N/A'); ?>"
                           class="w-full border border-gray-300 bg-gray-100 rounded-lg p-3 text-gray-500 cursor-not-allowed">
                </div>

                <!-- ชื่อจริง -->
                <div class="mb-6">
                    <label for="first_name" class="block text-sm font-medium text-gray-700 mb-2">ชื่อจริง</label>
                    <input type="text" id="first_name" name="first_name" required
                           value="<?= htmlspecialchars($current_user_data['first_name'] ?? ''); ?>"
                           class="w-full border border-gray-300 rounded-lg p-3 focus:ring-blue-500 focus:border-blue-500 transition-all shadow-inner">
                </div>

                <!-- นามสกุล -->
                <div class="mb-6">
                    <label for="last_name" class="block text-sm font-medium text-gray-700 mb-2">นามสกุล</label>
                    <input type="text" id="last_name" name="last_name" required
                           value="<?= htmlspecialchars($current_user_data['last_name'] ?? ''); ?>"
                           class="w-full border border-gray-300 rounded-lg p-3 focus:ring-blue-500 focus:border-blue-500 transition-all shadow-inner">
                </div>

                <!-- อีเมล -->
                <div class="mb-6">
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-2">อีเมล</label>
                    <input type="email" id="email" name="email" required
                           value="<?= htmlspecialchars($current_user_data['email'] ?? ''); ?>"
                           class="w-full border border-gray-300 rounded-lg p-3 focus:ring-blue-500 focus:border-blue-500 transition-all shadow-inner">
                    <p class="mt-1 text-xs text-gray-500">ใช้สำหรับติดต่อและรับการแจ้งเตือน</p>
                </div>

                <!-- ปุ่มบันทึก -->
                <div class="flex justify-end pt-4 border-t border-gray-100">
                    <button type="submit" 
                            class="inline-flex items-center text-white bg-green-600 hover:bg-green-700 font-bold transition-colors px-6 py-3 rounded-lg shadow-xl text-lg">
                        <i class="fas fa-save mr-2"></i> บันทึกข้อมูลส่วนตัว
                    </button>
                </div>

            </form>
        </section>
        
        <!-- ส่วนที่ 2: เปลี่ยนรหัสผ่าน -->
        <section class="bg-white p-8 rounded-2xl shadow-2xl max-w-2xl mx-auto">
            <h3 class="text-2xl font-bold text-red-700 mb-6 border-b pb-3">เปลี่ยนรหัสผ่าน</h3>
            <form method="POST" action="staff_edit_profile.php">
                
                <!-- รหัสผ่านปัจจุบัน -->
                <div class="mb-6">
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-2">รหัสผ่านปัจจุบัน</label>
                    <input type="password" id="password" name="password" required
                           class="w-full border border-gray-300 rounded-lg p-3 focus:ring-red-500 focus:border-red-500 transition-all shadow-inner"
                           placeholder="กรุณากรอกรหัสผ่านปัจจุบัน">
                </div>
                
                <!-- รหัสผ่านใหม่ -->
                <div class="mb-6">
                    <label for="new_password" class="block text-sm font-medium text-gray-700 mb-2">รหัสผ่านใหม่</label>
                    <input type="password" id="new_password" name="new_password" required
                           class="w-full border border-gray-300 rounded-lg p-3 focus:ring-red-500 focus:border-red-500 transition-all shadow-inner"
                           placeholder="รหัสผ่านใหม่">
                </div>
                
                <!-- ยืนยันรหัสผ่านใหม่ -->
                <div class="mb-6">
                    <label for="confirm_new_password" class="block text-sm font-medium text-gray-700 mb-2">ยืนยันรหัสผ่านใหม่</label>
                    <input type="password" id="confirm_new_password" name="confirm_new_password" required
                           class="w-full border border-gray-300 rounded-lg p-3 focus:ring-red-500 focus:border-red-500 transition-all shadow-inner"
                           placeholder="ยืนยันรหัสผ่านใหม่">
                </div>

                <!-- ปุ่มเปลี่ยนรหัสผ่าน -->
                <div class="flex justify-end pt-4 border-t border-gray-100">
                    <button type="submit" 
                            class="inline-flex items-center text-white bg-red-600 hover:bg-red-700 font-bold transition-colors px-6 py-3 rounded-lg shadow-xl text-lg">
                        <i class="fas fa-key mr-2"></i> เปลี่ยนรหัสผ่าน
                    </button>
                </div>
            </form>
        </section>
        
    </main>

    <footer class="p-4 bg-gray-200 text-center text-gray-600 text-sm mt-auto">
        &copy; <?php echo date("Y"); ?> ระบบจัดการการตีพิมพ์ผลงาน | มหาวิทยาลัยสงขลานครินทร์ (PSU)
    </footer>
</div>

</body>
</html>
<?php 
// ปิดการเชื่อมต่อฐานข้อมูลเมื่อสิ้นสุดการทำงานของสคริปต์
if (!$db_error && isset($conn)) {
    $conn->close(); 
}
?>
