<?php
session_start();

// 1. ตรวจสอบว่า user ล็อกอินหรือยัง
if (!isset($_SESSION['user_id'])) {
    header("Location: login-v1.php");
    exit;
}

// 2. เชื่อมต่อฐานข้อมูล
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "group10";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 3. ดึงข้อมูลผู้ใช้จาก session
$user_id = $_SESSION['user_id'];

$message = '';
$message_type = '';
$user_data = [];

// *************************************************************
// 4. Form Submission Logic (POST)
// *************************************************************
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 4a. Get and sanitize input
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $tel = trim($_POST['tel'] ?? '');
    $department = trim($_POST['department'] ?? '');
    
    // ไม่มีการอัปเดต Username เนื่องจากควรจะเป็น Unique key และมักไม่เปลี่ยนบ่อย

    // 4b. Validation
    if (empty($first_name) || empty($last_name) || empty($email) || empty($department)) {
        $message = 'กรุณากรอกข้อมูลชื่อ, นามสกุล, อีเมล และหน่วยงานให้ครบถ้วน';
        $message_type = 'error';
    } else {
        try {
            // 4c. Update User data
            $update_sql = "UPDATE User SET 
                            first_name = ?, 
                            last_name = ?, 
                            email = ?, 
                            tel = ?, 
                            Department = ? 
                           WHERE User_id = ?";
            
            $stmt = $conn->prepare($update_sql);
            // Bind: s=first_name, s=last_name, s=email, s=tel, s=Department, i=User_id
            $stmt->bind_param("sssssi", $first_name, $last_name, $email, $tel, $department, $user_id);
            $stmt->execute();
            $stmt->close();

            $message = 'บันทึกการแก้ไขข้อมูลส่วนตัวเรียบร้อยแล้ว!';
            $message_type = 'success';

        } catch (mysqli_sql_exception $e) {
            $message = "เกิดข้อผิดพลาดในการบันทึกข้อมูล: " . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// *************************************************************
// 5. Fetch current User data (หลังการอัปเดต หรือเมื่อเข้าสู่หน้าครั้งแรก)
// *************************************************************
$fetch_sql = "SELECT first_name, last_name, Username, email, tel, role, Department FROM User WHERE User_id = ?";
$stmt_fetch = $conn->prepare($fetch_sql);
$stmt_fetch->bind_param("i", $user_id);
$stmt_fetch->execute();
$result_fetch = $stmt_fetch->get_result();
$user_data = $result_fetch->fetch_assoc();
$current_user_name = htmlspecialchars($user_data['first_name'] . " " . $user_data['last_name']);
$stmt_fetch->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>แก้ไขข้อมูลส่วนตัว | ระบบ จัดการการตีพิมพ์ผลงานของอาจารย์</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  
  <style>
      /* กำหนดสีหลักของธีม (สีน้ำเงิน) */
      .text-theme { color: #1d4ed8; } /* blue-700 */
      .bg-theme-light { background-color: #eff6ff; } /* blue-50 */
      .hover-bg-theme { background-color: #dbeafe; } /* blue-100 */
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
          height: 100px; /* ขนาดโลโก้ */
          object-fit: contain;
      }
      /* Custom style for active menu in this page */
      .profile-active {
        background-color: #dbeafe !important; /* bg-blue-100 */
      }
  </style>
</head>
<body class="flex min-h-screen font-sans bg-gray-100">

<aside class="w-64 bg-white shadow-lg p-6 flex flex-col sticky top-0 h-screen">
    <div class="flex flex-col items-center border-b pb-4 mb-4">
        <img src="./img/img_psu.png" alt="PSU Logo" class="psu-logo ">
        <span class="text-xs font-semibold text-gray-600">ระบบจัดการการตีพิมพ์</span>
    </div>

    <a href="edit_profile.php" title="แก้ไขข้อมูลส่วนตัว">
        <div class="flex items-center px-1 py-3 mb-4 bg-gray-50 rounded-lg border border-gray-200 profile-active transition duration-150">
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
        <a href="publications.php" class="flex items-center p-3 rounded-lg text-gray-700 hover:bg-blue-100 hover:text-blue-700 transition-colors duration-150">
            <i class="fas fa-list-alt w-5 h-5 mr-3"></i> รายการผลงานตีพิมพ์
        </a>
        <a href="add_publication.php" class="flex items-center p-3 rounded-lg mb-3 text-gray-700 hover:bg-blue-100 hover:text-blue-700 transition-colors duration-150">
            <i class="fas fa-plus-circle w-5 h-5 mr-3"></i> เพิ่มผลงานตีพิมพ์
        </a>
        <a href="history.php" class="flex items-center p-3 rounded-lg text-gray-700 hover:bg-blue-100 hover:text-blue-700 transition-colors duration-150">
            <i class="fas fa-history w-5 h-5 mr-3"></i> ประวัติการแก้ไข
        </a>
        <a href="usermannual.php" class="flex items-center p-3 rounded-lg text-gray-700 hover:bg-blue-100 hover:text-blue-700 transition-colors duration-150">
            <i class="fas fa-book w-5 h-5 mr-3"></i> คู่มือการใช้งาน
        </a>
        <a href="contact.php" class="flex items-center p-3 rounded-lg text-gray-700 hover:bg-blue-100 hover:text-blue-700 transition-colors duration-150">
            <i class="fas fa-phone-alt w-5 h-5 mr-3"></i> ช่องทางติดต่อ
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
        <h1 class="text-3xl font-bold text-gray-800">แก้ไขข้อมูลส่วนตัว</h1>
        
        <div class="flex items-center space-x-4 right-icons">
            <a href="#" title="แจ้งเตือน" class="relative">
                <i class="fas fa-bell text-2xl"></i>
            </a>
            <a href="edit_profile.php" title="แก้ไขข้อมูลส่วนตัว">
                <i class="fas fa-user-circle text-2xl"></i>
            </a>
            <a href="edit_profile.php" class="text-gray-600 font-medium hidden sm:block hover:text-blue-700 transition-colors duration-150">
              <?= $current_user_name; ?>
            </a>
        </div>
    </header>

    <?php if ($message): ?>
        <div class="p-4 mb-4 rounded-lg <?= $message_type === 'success' ? 'bg-green-100 text-green-700 border border-green-200' : 'bg-red-100 text-red-700 border border-red-200'; ?>">
            <p class="font-semibold"><?= htmlspecialchars($message); ?></p>
        </div>
    <?php endif; ?>

    <section class="bg-white p-8 rounded-xl shadow-2xl">
        <h2 class="text-2xl font-semibold text-gray-800 mb-6 border-b pb-3">ข้อมูลบัญชีผู้ใช้งาน</h2>
        
        <form method="POST" action="edit_profile.php" class="space-y-6">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Username (ไม่สามารถแก้ไขได้)</label>
                    <input type="text" disabled
                           class="mt-1 block w-full border border-gray-200 rounded-lg shadow-inner p-3 bg-gray-50 text-gray-500"
                           value="<?= htmlspecialchars($user_data['Username'] ?? ''); ?>">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">ตำแหน่ง/บทบาท</label>
                    <input type="text" disabled
                           class="mt-1 block w-full border border-gray-200 rounded-lg shadow-inner p-3 bg-gray-50 text-gray-500"
                           value="<?= htmlspecialchars($user_data['role'] ?? ''); ?>">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="first_name" class="block text-sm font-medium text-gray-700 mb-1">ชื่อจริง <span class="text-red-500">*</span></label>
                    <input type="text" name="first_name" id="first_name" required 
                           class="mt-1 block w-full border border-gray-300 rounded-lg shadow-sm p-3 focus:ring-blue-500 focus:border-blue-500"
                           value="<?= htmlspecialchars($user_data['first_name'] ?? ''); ?>">
                </div>

                <div>
                    <label for="last_name" class="block text-sm font-medium text-gray-700 mb-1">นามสกุล <span class="text-red-500">*</span></label>
                    <input type="text" name="last_name" id="last_name" required 
                           class="mt-1 block w-full border border-gray-300 rounded-lg shadow-sm p-3 focus:ring-blue-500 focus:border-blue-500"
                           value="<?= htmlspecialchars($user_data['last_name'] ?? ''); ?>">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email <span class="text-red-500">*</span></label>
                    <input type="email" name="email" id="email" required 
                           class="mt-1 block w-full border border-gray-300 rounded-lg shadow-sm p-3 focus:ring-blue-500 focus:border-blue-500"
                           value="<?= htmlspecialchars($user_data['email'] ?? ''); ?>">
                </div>

                <div>
                    <label for="tel" class="block text-sm font-medium text-gray-700 mb-1">เบอร์โทรศัพท์</label>
                    <input type="text" name="tel" id="tel"
                           class="mt-1 block w-full border border-gray-300 rounded-lg shadow-sm p-3 focus:ring-blue-500 focus:border-blue-500"
                           value="<?= htmlspecialchars($user_data['tel'] ?? ''); ?>">
                </div>
            </div>

            <div>
                <label for="department" class="block text-sm font-medium text-gray-700 mb-1">หน่วยงาน/ภาควิชา <span class="text-red-500">*</span></label>
                <input type="text" name="department" id="department" required 
                       class="mt-1 block w-full border border-gray-300 rounded-lg shadow-sm p-3 focus:ring-blue-500 focus:border-blue-500"
                       value="<?= htmlspecialchars($user_data['Department'] ?? ''); ?>">
            </div>

            <div class="pt-4 border-t border-gray-200">
                <button type="submit" class="w-full sm:w-auto bg-blue-600 text-white px-6 py-3 rounded-xl shadow-lg hover:bg-blue-700 transition-colors duration-200 text-lg font-medium flex items-center justify-center">
                    <i class="fas fa-user-edit mr-3"></i> บันทึกการแก้ไขข้อมูล
                </button>
                <p class="mt-4 text-sm text-gray-500">หากต้องการเปลี่ยนรหัสผ่าน กรุณาติดต่อผู้ดูแลระบบ</p>
            </div>

        </form>
    </section>
</main>
</div>

</body>
</html>