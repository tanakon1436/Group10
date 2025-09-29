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
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "group10";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    $db_error = "Connection failed: " . $conn->connect_error;
    $conn = null;
} else {
    $db_error = null;
}

// 2. ดึง User_id ที่ต้องการแก้ไขจาก URL
$user_id_to_update = $_GET['id'] ?? null;
if (!$user_id_to_update) {
    header("Location: Staff_manage.php");
    exit();
}

$user_data = [];
$update_success = null;
$update_error = null;
$avatar_upload_error = null; // ตัวแปรสำหรับข้อผิดพลาดในการอัปโหลดรูป

/**
 * ฟังก์ชันดึงข้อมูลอาจารย์เฉพาะคน
 */
function fetchUserData($conn, $id) {
    if (!$conn) return null;
    $sql = "SELECT User_id, first_name, last_name, Department, email, avatar, role FROM User WHERE User_id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return null;
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    
    // ตรวจสอบว่าพบผู้ใช้และบทบาทเป็น 'normal' หรือไม่
    if ($data && $data['role'] === 'normal') {
        unset($data['role']); 
        return $data;
    }
    return null;
}

// 2.1 ดึงข้อมูลผู้ใช้ปัจจุบันเพื่อใช้ในการประมวลผล POST และแสดงผล
// ต้องดึงข้อมูลนี้ก่อน POST เพื่อให้ได้ค่า 'avatar' เดิม
$user_data = fetchUserData($conn, $user_id_to_update);
if (!$user_data) {
    header("Location: Staff_manage.php");
    exit();
}

// 3. ดึงข้อมูลผู้ใช้ปัจจุบันและจำนวนผลงานที่รออนุมัติสำหรับ Header
$current_user_name = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];
$pending_count = 0;
if (!$db_error) {
    $sql_count = "SELECT COUNT(*) AS count FROM Publication WHERE status = 'waiting'";
    $result_count = $conn->query($sql_count);
    if ($result_count && $row = $result_count->fetch_assoc()) {
        $pending_count = (int)$row['count'];
    }
}

// Helper function สำหรับ bind_param (ยังคงไว้สำหรับ PHP เก่า)
function refValues($arr){
    if (strnatcmp(phpversion(),'5.3') >= 0) {
        $refs = array();
        foreach($arr as $key => $value)
            $refs[$key] = &$arr[$key];
        return $refs;
    }
    return $arr;
}

// --- 4. จัดการการส่งฟอร์ม (POST Submission) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $conn) {
    
    // ดึงค่าเก่าของ Avatar เพื่อใช้ในการลบไฟล์เดิม
    $old_avatar_filename = $user_data['avatar'] ?? null; 
    $new_avatar_filename = null;
    $upload_dir = 'img/';

    // 4.1. จัดการการอัปโหลดรูปภาพใหม่
    if (isset($_FILES['avatar_file']) && $_FILES['avatar_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['avatar_file'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/jpg'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        // ตรวจสอบความถูกต้องของไฟล์
        if (!in_array($file['type'], $allowed_types)) {
            $avatar_upload_error = "ประเภทไฟล์ไม่ถูกต้อง อนุญาตเฉพาะ JPEG, PNG, GIF เท่านั้น";
        } elseif ($file['size'] > $max_size) {
            $avatar_upload_error = "ขนาดไฟล์ใหญ่เกินไป (สูงสุด 5MB)";
        } else {
            // สร้างชื่อไฟล์ใหม่ที่ไม่ซ้ำกัน
            $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $new_avatar_filename = 'avatar_' . uniqid() . '.' . $file_ext;
            $target_file = $upload_dir . $new_avatar_filename;

            // ย้ายไฟล์
            if (!move_uploaded_file($file['tmp_name'], $target_file)) {
                $avatar_upload_error = "เกิดข้อผิดพลาดในการย้ายไฟล์ไปยังเซิร์ฟเวอร์";
                $new_avatar_filename = null; // ล้างชื่อไฟล์ถ้าการย้ายล้มเหลว
            }
        }
    }

    // 4.2 สร้างส่วนของ SQL Query สำหรับ UPDATE
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $department = trim($_POST['Department'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password_new = trim($_POST['password_new'] ?? '');
    
    $update_fields = [];
    $update_values = [];
    $update_types = "";
    
    // ฟิลด์ข้อมูลทั่วไป
    $update_fields[] = "first_name = ?"; $update_values[] = $first_name; $update_types .= "s";
    $update_fields[] = "last_name = ?"; $update_values[] = $last_name; $update_types .= "s";
    $update_fields[] = "Department = ?"; $update_values[] = $department; $update_types .= "s";
    $update_fields[] = "email = ?"; $update_values[] = $email; $update_types .= "s";

    // จัดการ Password
    if (!empty($password_new)) {
        // แนะนำให้ใช้ password_hash เพื่อความปลอดภัย แม้ว่าใน DB dump จะเป็น plain text
        $hashed_password = password_hash($password_new, PASSWORD_DEFAULT);
        $update_fields[] = "password = ?";
        $update_values[] = $hashed_password;
        $update_types .= "s";
    }

    // จัดการ Avatar (อัปเดตเฉพาะถ้ามีการอัปโหลดไฟล์ใหม่สำเร็จ และไม่มีข้อผิดพลาดอื่น ๆ)
    $is_avatar_updated = false;
    if ($new_avatar_filename && !$avatar_upload_error) {
        $update_fields[] = "avatar = ?";
        $update_values[] = $new_avatar_filename;
        $update_types .= "s";
        $is_avatar_updated = true;
    }
    
    // 4.3 เตรียมและรัน UPDATE Query
    $sql_update = "UPDATE User SET " . implode(", ", $update_fields) . " WHERE User_id = ?";
    $update_values[] = $user_id_to_update;
    $update_types .= "i";
    
    $stmt_update = $conn->prepare($sql_update);
    if ($stmt_update) {
        $bind_params = array_merge([$update_types], $update_values);
        call_user_func_array([$stmt_update, 'bind_param'], refValues($bind_params));

        if ($stmt_update->execute()) {
            $update_success = "อัปเดตข้อมูลอาจารย์ ID: " . $user_id_to_update . " เรียบร้อยแล้ว";
            
            // 4.4 ลบรูปเก่าทิ้ง (เฉพาะเมื่อมีการอัปเดต Avatar และการอัปเดต DB สำเร็จ)
            if ($is_avatar_updated && $old_avatar_filename) {
                $old_file_path = $upload_dir . $old_avatar_filename;
                // ตรวจสอบและลบไฟล์เก่า
                if (file_exists($old_file_path) && is_file($old_file_path)) {
                    @unlink($old_file_path); 
                }
            }
        } else {
            $update_error = "เกิดข้อผิดพลาดในการอัปเดตข้อมูล: " . $stmt_update->error;
        }
    } else {
        $update_error = "เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL: " . $conn->error;
    }
    
    // 5. ดึงข้อมูลล่าสุดเพื่อแสดงในฟอร์ม (ดึงใหม่หลังจากการอัปเดต)
    $user_data = fetchUserData($conn, $user_id_to_update);
}
// ----------------------------------------------------
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แก้ไขข้อมูลอาจารย์ - Staff</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

    <style>
        .text-theme { color: #1d4ed8; } 
        .bg-theme-light { background-color: #eff6ff; } 
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
    </style>
</head>
<body class="flex min-h-screen font-sans bg-gray-50">

<aside class="w-64 bg-white shadow-xl p-6 flex flex-col sticky top-0 h-screen z-10">
    <h2 class="text-2xl font-extrabold text-blue-800 mb-6 border-b pb-4">Staff Menu</h2>
    <nav class="w-full flex-grow">
        <a href="Staff_manage.php" class="flex items-center p-3 rounded-xl mb-3 text-white bg-blue-600 shadow-md hover:bg-blue-700 font-semibold transition-colors duration-150">
            <i class="fas fa-arrow-left w-5 h-5 mr-3"></i> กลับสู่หน้าจัดการอาจารย์
        </a>
        
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
            <i class="fas fa-user-edit mr-2 text-blue-800"></i> แก้ไขข้อมูลอาจารย์
        </h1>
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

    <main class="flex-1 p-8">
        <section class="bg-white p-6 rounded-2xl shadow-2xl max-w-4xl mx-auto">
            <h2 class="text-2xl font-bold text-gray-800 mb-6 pb-2 border-b-2 border-blue-200">
                แก้ไขข้อมูลอาจารย์: ID #<?php echo htmlspecialchars($user_data['User_id']); ?>
            </h2>

            <!-- Message Block -->
            <?php if ($db_error): ?>
                <div class="mb-4 p-4 bg-red-100 text-red-700 border border-red-300 rounded-lg shadow-md">
                    <p>⚠️ ข้อผิดพลาดฐานข้อมูล: <?= htmlspecialchars($db_error); ?></p>
                </div>
            <?php endif; ?>
            <?php if ($update_success): ?>
                <div class="mb-4 p-4 bg-green-100 text-green-700 border border-green-300 rounded-lg shadow-md">
                    <p><i class="fas fa-check-circle mr-2"></i> <?= htmlspecialchars($update_success); ?></p>
                </div>
            <?php endif; ?>
            <?php if ($update_error || $avatar_upload_error): ?>
                <div class="mb-4 p-4 bg-red-100 text-red-700 border border-red-300 rounded-lg shadow-md">
                    <p><i class="fas fa-times-circle mr-2"></i> 
                    <?php 
                        echo htmlspecialchars($update_error ?? '');
                        if ($update_error && $avatar_upload_error) echo "<br>";
                        echo htmlspecialchars($avatar_upload_error ?? '');
                    ?></p>
                </div>
            <?php endif; ?>

            <!-- Update Form - ต้องมี enctype="multipart/form-data" เพื่อรองรับการอัปโหลดไฟล์ -->
            <form action="Staff_manage_update.php?id=<?php echo htmlspecialchars($user_data['User_id']); ?>" 
                  method="POST" 
                  enctype="multipart/form-data"
                  class="space-y-6">

                <!-- 1. Avatar Section -->
                <div class="border border-blue-100 p-4 rounded-lg bg-blue-50">
                    <h3 class="text-lg font-semibold text-blue-700 mb-4 flex items-center">
                        <i class="fas fa-image mr-2"></i> รูปโปรไฟล์ (Avatar)
                    </h3>
                    <div class="flex items-center space-x-6">
                        <!-- รูปปัจจุบัน -->
                        <?php 
                        $current_avatar_path = !empty($user_data['avatar']) ? 'img/' . $user_data['avatar'] : '';
                        $has_avatar = !empty($current_avatar_path) && file_exists($current_avatar_path);
                        ?>
                        <div class="flex flex-col items-center">
                            <?php if ($has_avatar): ?>
                            <img src="<?= htmlspecialchars($current_avatar_path); ?>" alt="รูปปัจจุบัน" 
                                 class="w-24 h-24 object-cover rounded-full border-4 border-blue-400 shadow-lg">
                            <?php else: ?>
                            <div class="w-24 h-24 rounded-full bg-gray-300 flex items-center justify-center text-gray-600 text-4xl border-4 border-gray-400">
                                <i class="fas fa-user-circle"></i>
                            </div>
                            <?php endif; ?>
                            <p class="text-xs text-gray-500 mt-1">รูปปัจจุบัน</p>
                        </div>
                        
                        <!-- อัปโหลดรูปใหม่ -->
                        <div class="flex-1">
                            <label for="avatar_file" class="block text-sm font-medium text-gray-700 mb-2">อัปโหลดรูปภาพใหม่ (JPEG, PNG, GIF, สูงสุด 5MB)</label>
                            <input type="file" id="avatar_file" name="avatar_file" accept="image/jpeg, image/png, image/gif, image/jpg"
                                   class="block w-full text-sm text-gray-500
                                   file:mr-4 file:py-2 file:px-4
                                   file:rounded-full file:border-0
                                   file:text-sm file:font-semibold
                                   file:bg-blue-50 file:text-blue-700
                                   hover:file:bg-blue-100 transition duration-150">
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- First Name -->
                    <div>
                        <label for="first_name" class="block text-sm font-medium text-gray-700 mb-2">ชื่อ</label>
                        <input type="text" id="first_name" name="first_name" required 
                               value="<?php echo htmlspecialchars($user_data['first_name'] ?? ''); ?>"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 transition duration-150">
                    </div>
                    <!-- Last Name -->
                    <div>
                        <label for="last_name" class="block text-sm font-medium text-gray-700 mb-2">นามสกุล</label>
                        <input type="text" id="last_name" name="last_name" required 
                               value="<?php echo htmlspecialchars($user_data['last_name'] ?? ''); ?>"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 transition duration-150">
                    </div>
                </div>

                <!-- Department -->
                <div>
                    <label for="Department" class="block text-sm font-medium text-gray-700 mb-2">แผนก/สาขา</label>
                    <input type="text" id="Department" name="Department" required 
                           value="<?php echo htmlspecialchars($user_data['Department'] ?? ''); ?>"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 transition duration-150">
                </div>

                <!-- Email -->
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-2">อีเมล</label>
                    <input type="email" id="email" name="email" required 
                           value="<?php echo htmlspecialchars($user_data['email'] ?? ''); ?>"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 transition duration-150">
                </div>

                <!-- Password (Optional Update) -->
                <div>
                    <label for="password_new" class="block text-sm font-medium text-gray-700 mb-2">รหัสผ่านใหม่ (ว่างไว้หากไม่ต้องการเปลี่ยน)</label>
                    <input type="password" id="password_new" name="password_new" 
                           placeholder="********"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 transition duration-150">
                    <p class="text-xs text-gray-500 mt-1">หากใส่รหัสผ่านใหม่ ระบบจะทำการเข้ารหัส (Hash) ก่อนบันทึก</p>
                </div>

                <!-- Submit Button -->
                <div class="flex justify-end pt-6">
                    <button type="submit"
                            class="flex items-center space-x-2 px-8 py-3 bg-blue-600 text-white rounded-full font-semibold text-lg shadow-lg hover:bg-blue-700 transition-colors duration-200 transform hover:scale-105">
                        <i class="fas fa-save"></i>
                        <span>บันทึกการแก้ไข</span>
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
if ($conn) {
    $conn->close(); 
}
?>
