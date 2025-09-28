<?php
// === START: DEBUGGING AND ERROR REPORTING ===
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// === END: DEBUGGING AND ERROR REPORTING ===

session_start();

// 1. กำหนดค่าการเชื่อมต่อฐานข้อมูล
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "group10";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    $db_error = "Connection failed: " . $conn->connect_error;
} else {
    $db_error = null;
}

// === 2. จัดการคำขอ AJAX (Send Notification) ===
// ตรวจสอบว่าเป็นคำขอ POST ที่มาจากการส่ง Modal Form หรือไม่
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_notification') {
    // ล้าง Output Buffer เพื่อป้องกัน PHP Warning/Notice ปนมากับ JSON
    ob_clean(); 
    header('Content-Type: application/json');
    
    // ตรวจสอบการเชื่อมต่อ
    if ($db_error) {
        // หากเกิดข้อผิดพลาด DB ให้ส่ง JSON error กลับไปทันที
        echo json_encode(['success' => false, 'message' => 'Database connection error: ' . $db_error]);
        exit();
    }
    
    $teacher_id = filter_var($_POST['User_id'], FILTER_VALIDATE_INT);
    $message = trim($_POST['message']);

    if ($teacher_id && !empty($message)) {
        // ใช้ NOW() สำหรับ date_time และตั้งค่า status เป็น 'new'
        $stmt = $conn->prepare("INSERT INTO Notification (User_id, message, date_time, status) VALUES (?, ?, NOW(), 'new')");
        
        if ($stmt === false) {
             echo json_encode(['success' => false, 'message' => 'SQL prepare failed: ' . $conn->error]);
             $conn->close();
             exit();
        }

        $stmt->bind_param("is", $teacher_id, $message);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'ส่งข้อความแจ้งเตือนเรียบร้อยแล้ว']);
        } else {
            echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการบันทึกข้อมูล: ' . $stmt->error]);
        }
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'ข้อมูลไม่สมบูรณ์ (ID หรือข้อความว่างเปล่า)']);
    }
    $conn->close();
    exit(); // <<< จุดสำคัญ: ต้อง exit() เพื่อหยุดการสร้างหน้า HTML
}
// === END: จัดการคำขอ AJAX ===


// === 3. โหลดหน้าหลัก (Staff_manage.php) ===
// ตรวจสอบการเข้าสู่ระบบ: หากไม่มี session หรือ role ไม่ใช่ staff ให้ redirect ไปหน้า login
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    // ป้องกันการ redirect หากอยู่ในขั้นตอน AJAX (แม้ว่า AJAX ควรจะ exit ไปแล้วก็ตาม)
    if (!isset($_POST['action'])) {
        header("Location: login-v1.php");
        exit();
    }
}

$teachers = [];
if (!$db_error) {
    // ดึงข้อมูลอาจารย์เท่านั้น (role='normal')
    $sql = "SELECT User_id, first_name, last_name, Department, avatar FROM User WHERE role='normal' ORDER BY first_name";
    $result = $conn->query($sql);
    if ($result) {
        while($row = $result->fetch_assoc()) {
            $teachers[] = $row;
        }
    } else {
        $db_error = "Query failed: " . $conn->error;
    }
}

// ดึงข้อมูลผู้ใช้ปัจจุบัน (ใช้ใน Header)
$current_user_name = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];
$pending_count = 0; 
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการข้อมูลอาจารย์ - Staff</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

    <style>
        /* สไตล์สำหรับ Modal (ซ่อนไว้ก่อน) */
        .modal-overlay {
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 50;
        }
        /* สไตล์สำหรับ Header */
        .top-header {
            background-color: #cce4f9; 
            padding: 1rem 2rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body class="flex min-h-screen font-sans bg-gray-50">

<aside class="w-64 bg-white shadow-xl p-6 flex flex-col sticky top-0 h-screen z-10">
    <h2 class="text-2xl font-extrabold text-blue-800 mb-6 border-b pb-4">Staff Menu</h2>
    <nav class="w-full flex-grow">
        <a href="staffPage.php" class="flex items-center p-3 rounded-xl mb-3 text-gray-700 hover:bg-blue-50 hover:text-blue-700 transition-colors duration-150">
            <i class="fas fa-arrow-left w-5 h-5 mr-3"></i> กลับสู่หน้าหลัก
        </a>
        <a href="Staff_manage.php" class="flex items-center p-3 rounded-xl mb-3 text-white bg-blue-600 shadow-md hover:bg-blue-700 font-semibold transition-colors duration-150">
            <i class="fas fa-edit w-5 h-5 mr-3"></i> จัดการข้อมูลอาจารย์
        </a>
        <div class="px-0 pt-4 border-t border-gray-200">
            <a href="logout.php" class="flex items-center p-3 rounded-lg text-red-500 hover:bg-red-50 transition-colors duration-150">
                <i class="fas fa-sign-out-alt w-5 h-5 mr-3"></i> ออกจากระบบ
            </a>
        </div>
    </nav>
</aside>

<div class="flex-1 flex flex-col">
    <header class="top-header flex items-center justify-between sticky top-0 z-10">
        <h1 class="text-xl md:text-2xl font-bold text-gray-700 tracking-wide">
            <i class="fas fa-users-cog mr-2 text-blue-800"></i> จัดการข้อมูลอาจารย์ (Staff)
        </h1>
        <div class="flex items-center space-x-4 right-icons">
            <span class="text-gray-700 font-medium hidden sm:block text-sm">
            <?= htmlspecialchars($current_user_name); ?>
            </span>
            <a href="approve.php" title="คำขออนุมัติผลงาน" class="relative">
                <i class="fas fa-bell text-xl text-blue-700"></i>
                <?php if ($pending_count > 0): ?>
                    <span class="absolute -top-1 -right-1 bg-red-600 text-white text-xs font-bold rounded-full h-5 w-5 flex items-center justify-center ring-2 ring-white">
                        <?= $pending_count ?>
                    </span>
                <?php endif; ?>
            </a>
            <a href="#" title="โปรไฟล์ผู้ใช้งาน">
                <i class="fas fa-user-circle text-xl text-blue-700"></i>
            </a>
        </div>
    </header>

    <main class="flex-1 p-8">
        <section class="bg-white p-6 rounded-2xl shadow-2xl">
            <h2 class="text-2xl font-bold text-gray-800 mb-6 pb-2 border-b-2 border-blue-200">รายชื่ออาจารย์ในระบบ</h2>

            <!-- ช่องค้นหา -->
            <div class="mb-6 flex items-center space-x-4">
                <input type="text" id="searchInput" placeholder="ค้นหาชื่อ-นามสกุล หรือแผนก..." 
                       class="flex-1 px-5 py-3 border border-gray-300 rounded-full focus:outline-none focus:ring-2 focus:ring-blue-400 shadow-inner transition-all">

                <!-- ปุ่มเพิ่มอาจารย์ (ยังคงอยู่ แต่ไม่มีลิงก์) -->
                <button onclick="/* location.href='Staff-add-teacher.php' */" 
                    class="flex items-center space-x-2 px-6 py-3 bg-green-600 text-white rounded-full font-semibold shadow-md hover:bg-green-700 transition-colors duration-200">
                    <i class="fas fa-user-plus"></i>
                    <span>เพิ่มอาจารย์</span>
                </button>
            </div>

            <!-- การ์ดข้อมูล -->
            <div id="userList" class="space-y-4">
            <?php if ($db_error): ?>
                <div class="p-4 bg-red-100 text-red-700 border border-red-300 rounded-lg shadow-md">
                    <p>⚠️ ไม่สามารถเชื่อมต่อฐานข้อมูลได้: <?= htmlspecialchars($db_error); ?></p>
                </div>
            <?php elseif (empty($teachers)): ?>
                <div class="p-4 bg-yellow-100 text-yellow-700 border border-yellow-300 rounded-lg shadow-md">
                    <p>ไม่พบข้อมูลอาจารย์ที่มีบทบาท 'normal' ในระบบ</p>
                </div>
            <?php else: ?>
                <?php foreach ($teachers as $row): 
                    $fullName = htmlspecialchars($row['first_name'] . ' ' . $row['last_name']);
                ?>
                <div class="flex justify-between items-center bg-blue-50 p-4 rounded-xl shadow-lg border border-blue-200 hover:shadow-xl transition-shadow duration-300">
                    <div class="flex items-center space-x-4">
                        <?php if(!empty($row['avatar'])): ?>
                        <img src="img/<?php echo $row['avatar']; ?>" alt="ผู้ใช้งาน" class="w-16 h-16 rounded-full object-cover border-2 border-blue-400">
                        <?php else: ?>
                        <div class="w-16 h-16 rounded-full bg-blue-300 flex items-center justify-center text-white text-2xl font-bold">
                            <i class="fas fa-user"></i>
                        </div>
                        <?php endif; ?>
                        <div>
                            <p class="text-lg font-bold text-blue-800"><?= $fullName ?></p>
                            <p class="text-gray-600 text-sm"><i class="fas fa-graduation-cap mr-1"></i> อาจารย์</p>
                            <p class="text-gray-500 text-sm"><i class="fas fa-building mr-1"></i> <?php echo $row['Department']; ?></p>
                            <p class="text-xs text-gray-400 mt-1">ID: <?php echo $row['User_id']; ?></p>
                        </div>
                    </div>
                    <div class="flex space-x-2">
                        <!-- ปุ่มแจ้งเตือน/ส่งข้อความ -->
                        <button onclick="openNotificationModal(<?php echo $row['User_id']; ?>, '<?= $fullName ?>')" 
                                class="p-3 rounded-full bg-blue-600 text-white shadow-md hover:bg-blue-700 transition-colors duration-150"
                                title="ส่งข้อความแจ้งเตือน">
                            <i class="fas fa-bell text-lg"></i>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
            </div>
        </section>
    </main>

    <footer class="p-4 bg-gray-200 text-center text-gray-600 text-sm mt-auto">
        &copy; <?php echo date("Y"); ?> ระบบจัดการการตีพิมพ์ผลงาน | มหาวิทยาลัยสงขลานครินทร์ (PSU)
    </footer>
</div>

<!-- Notification Modal Structure -->
<div id="notificationModal" class="fixed inset-0 hidden items-center justify-center modal-overlay transition-opacity duration-300">
    <div class="bg-white rounded-xl p-8 w-full max-w-md shadow-2xl transform transition-all duration-300 scale-95 opacity-0" id="modalContent">
        <h3 class="text-2xl font-bold text-gray-800 mb-4 border-b pb-2 flex justify-between items-center">
            ส่งข้อความถึงอาจารย์
            <button onclick="closeNotificationModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                <i class="fas fa-times"></i>
            </button>
        </h3>
        <p class="text-lg text-blue-700 mb-4">อาจารย์: <span id="teacherName" class="font-semibold"></span></p>
        
        <form id="notificationForm">
            <input type="hidden" name="User_id" id="modalUserId">
            <input type="hidden" name="action" value="send_notification">

            <textarea name="message" id="messageInput" rows="5" 
                      class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 shadow-inner resize-y" 
                      placeholder="พิมพ์ข้อความที่ต้องการแจ้งเตือน..."></textarea>
            
            <div id="statusMessage" class="mt-3 text-sm font-medium"></div>

            <button type="submit" id="sendButton"
                    class="mt-6 w-full py-3 bg-blue-600 text-white rounded-lg font-semibold shadow-lg hover:bg-blue-700 transition-colors duration-200 flex items-center justify-center">
                <i class="fas fa-paper-plane mr-2"></i> ส่งข้อความ
            </button>
        </form>
    </div>
</div>

<script>
    const modal = document.getElementById('notificationModal');
    const modalContent = document.getElementById('modalContent');
    const teacherName = document.getElementById('teacherName');
    const modalUserId = document.getElementById('modalUserId');
    const notificationForm = document.getElementById('notificationForm');
    const messageInput = document.getElementById('messageInput');
    const statusMessage = document.getElementById('statusMessage');
    const sendButton = document.getElementById('sendButton');

    function openNotificationModal(userId, fullName) {
        teacherName.textContent = fullName;
        modalUserId.value = userId;
        messageInput.value = ''; 
        statusMessage.textContent = '';
        sendButton.disabled = false;
        sendButton.innerHTML = '<i class="fas fa-paper-plane mr-2"></i> ส่งข้อความ';

        modal.classList.remove('hidden');
        modal.classList.add('flex');
        
        setTimeout(() => {
            modalContent.classList.remove('opacity-0', 'scale-95');
            modalContent.classList.add('opacity-100', 'scale-100');
        }, 10);
    }

    function closeNotificationModal() {
        modalContent.classList.remove('opacity-100', 'scale-100');
        modalContent.classList.add('opacity-0', 'scale-95');
        
        setTimeout(() => {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }, 300);
    }

    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            closeNotificationModal();
        }
    });

    // Handle form submission via AJAX
    notificationForm.addEventListener('submit', async function(e) {
        e.preventDefault();

        if (messageInput.value.trim() === '') {
            statusMessage.className = 'mt-3 text-sm font-medium text-red-600';
            statusMessage.textContent = 'กรุณาพิมพ์ข้อความแจ้งเตือน';
            return;
        }

        const formData = new FormData(notificationForm);
        
        // แสดง Loading State
        sendButton.disabled = true;
        sendButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> กำลังส่ง...';
        statusMessage.className = 'mt-3 text-sm font-medium text-blue-600';
        statusMessage.textContent = 'กำลังดำเนินการ...';
        
        try {
            const response = await fetch('Staff_manage.php', {
                method: 'POST',
                body: formData
            });

            // ตรวจสอบ Content-Type ก่อนพยายามแปลงเป็น JSON
            const contentType = response.headers.get("content-type");
            let result;

            if (contentType && contentType.includes("application/json")) {
                result = await response.json();
            } else {
                // ถ้าไม่ใช่ JSON แสดงว่า PHP มี Output อื่น ๆ ปนมา (เช่น HTML)
                const errorText = await response.text();
                
                // แจ้งเตือนใน Console พร้อมแสดงส่วนหนึ่งของข้อความ HTML ที่เป็นปัญหา
                console.error("Server Response was NOT JSON. Received HTML/Text:", errorText);
                
                // โยนข้อผิดพลาดออกไป
                throw new Error("เซิร์ฟเวอร์ไม่ได้ตอบกลับเป็น JSON ที่ถูกต้อง (พบโค้ด HTML/ข้อความอื่น ๆ ใน Response). กรุณาตรวจสอบ Console.");
            }
            
            // --- การจัดการผลลัพธ์ JSON ---
            if (result.success) {
                statusMessage.className = 'mt-3 text-sm font-medium text-green-600';
                statusMessage.textContent = result.message;
                messageInput.value = ''; 
                
                setTimeout(closeNotificationModal, 2000); 

            } else {
                statusMessage.className = 'mt-3 text-sm font-medium text-red-600';
                statusMessage.textContent = result.message || 'เกิดข้อผิดพลาดในการส่งข้อความ';
            }

        } catch (error) {
            console.error('Fetch error:', error);
            // แสดงข้อความแจ้งเตือนไปยังผู้ใช้
            statusMessage.className = 'mt-3 text-sm font-medium text-red-600';
            statusMessage.textContent = error.message || 'เกิดข้อผิดพลาดในการเชื่อมต่อเครือข่าย';
        } finally {
            sendButton.disabled = false;
            sendButton.innerHTML = '<i class="fas fa-paper-plane mr-2"></i> ส่งข้อความ';
        }
    });


    // (AJAX Search logic)
    const searchInput = document.getElementById('searchInput');
    const userList = document.getElementById('userList');

    searchInput.addEventListener('keyup', function() {
        // ไม่มีไฟล์ Staff-manage-search.php ดังนั้นฟังก์ชันนี้ไม่มีผลในการค้นหาจริง
    });
</script>

</body>
</html>

<?php 
if (!$db_error && $conn->ping()) {
    $conn->close(); 
}
?>
