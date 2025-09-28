<?php
// -------------------- DB Connection --------------------
// ใช้ mysqli เพื่อความสอดคล้องกับไฟล์ HomeallPage-v1.php
$conn = new mysqli("localhost", "root", "", "group10");
if($conn->connect_error) { 
    die("Connection failed: " . $conn->connect_error); 
}

// -------------------- 1. Get Teacher ID and Validation --------------------
$teacherId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

if ($teacherId === 0) {
    // ถ้าไม่มี user_id ให้ redirect กลับไปหน้าหลัก
    header("Location: HomeallPage-v1.php");
    exit();
}

// -------------------- 2. Fetch Teacher Data --------------------
$stmt = $conn->prepare("SELECT first_name, last_name, Department, role, avatar FROM User WHERE User_id = ? AND role = 'normal'");
$stmt->bind_param("i", $teacherId);
$stmt->execute();
$result = $stmt->get_result();
$teacher = $result->fetch_assoc();

if (!$teacher) {
    die("ไม่พบข้อมูลอาจารย์"); 
}
$stmt->close();

$full_name = htmlspecialchars($teacher['first_name'] . " " . $teacher['last_name']);
$department = htmlspecialchars($teacher['Department']);
$role = htmlspecialchars($teacher['role']);

// กำหนดเส้นทางรูปภาพ Avatar
$avatar_filename = htmlspecialchars($teacher['avatar']);
// ใช้ 'img/default-avatar.png' เป็นรูปสำรองหากไม่มีรูปภาพ
$avatar_path = !empty($avatar_filename) ? "img/" . $avatar_filename : 'img/default-avatar.png';


// -------------------- 3. Fetch Publications (Count and List) --------------------
$pubs_sql = "
    SELECT Pub_id, title, publish_year, file_path 
    FROM Publication 
    WHERE Author_id = ? AND status = 'approved' 
    ORDER BY publish_year DESC, Pub_id DESC";
    
$stmt_pubs = $conn->prepare($pubs_sql);
$stmt_pubs->bind_param("i", $teacherId);
$stmt_pubs->execute();
$pubs_result = $stmt_pubs->get_result();

$publications = [];
while ($row = $pubs_result->fetch_assoc()) {
    $publications[] = $row;
}
$pub_count = count($publications);
$stmt_pubs->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ข้อมูลอาจารย์: <?= $full_name; ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

<style>
    /* Styling for the avatar container */
    .avatar-container {
        width: 180px;
        height: 180px;
        background-color: #e5e7eb; /* gray-200 */
        border-radius: 9999px; /* rounded-full */
        overflow: hidden;
        border: 5px solid #d1d5db; /* gray-300 */
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }
    .avatar-img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
</style>
</head>
<body class="bg-gray-100 font-sans text-gray-800 min-h-screen">

<!-- Main Content Container -->
<div class="max-w-7xl mx-auto p-6">
    <!-- Back Button -->
    <a href="HomeallPage-v1.php" class="inline-flex items-center space-x-2 px-4 py-2 bg-blue-600 text-white rounded-lg shadow hover:bg-blue-700 transition duration-150 mb-6">
        <i class="fas fa-arrow-left"></i> 
        <span>ย้อนกลับ</span>
    </a>

    <header class="mb-8">
        <h1 class="text-3xl font-bold text-gray-800">ข้อมูลอาจารย์: <?= $full_name; ?></h1>
        <p class="text-gray-500">ระบบค้นหาผลงานตีพิมพ์</p>
    </header>

    <!-- Profile and Summary Container -->
    <div class="flex flex-col md:flex-row gap-8">

        <!-- Left Panel: Profile Info -->
        <div class="md:w-1/3 bg-white p-6 rounded-xl shadow-lg flex flex-col items-center text-center">
            
            <!-- Avatar -->
            <div class="avatar-container mb-4">
                <img src="<?= $avatar_path; ?>" 
                     onerror="this.onerror=null;this.src='img/default-avatar.png';" 
                     alt="Avatar of <?= $full_name; ?>" 
                     class="avatar-img">
            </div>

            <div class="text-2xl font-bold text-blue-700 mb-1">
                <?= $full_name; ?>
            </div>
            <div class="text-md text-gray-700 mb-1">
                สังกัด: <?= $department; ?>
            </div>
            <div class="text-sm text-gray-500 italic">
                (สถานะ: <?= $role === 'normal' ? 'อาจารย์/นักวิจัย' : $role; ?>)
            </div>
        </div>

        <!-- Right Panel: Publications Summary and List -->
        <div class="md:w-2/3 flex flex-col gap-6">

            <!-- Summary Card -->
            <div class="bg-blue-600 text-white rounded-xl p-5 shadow-lg">
                <div class="text-lg font-semibold">สรุปผลงานตีพิมพ์ที่ได้รับการอนุมัติ</div>
                <div class="text-4xl font-extrabold mt-1"><?= $pub_count; ?></div>
                <div class="text-sm">เรื่อง / ชิ้นงาน</div>
            </div>

            <!-- Publication List -->
            <div class="bg-white p-6 rounded-xl shadow-lg">
                <h2 class="text-xl font-bold text-gray-800 mb-4 border-b pb-2">รายการผลงานตีพิมพ์</h2>
                
                <?php if ($pub_count > 0): ?>
                    <div class="space-y-4">
                    <?php 
                    $count = 0;
                    foreach ($publications as $pub): 
                        $count++;
                    ?>
                        <div class="publication-item border rounded-lg p-4 bg-gray-50 transition duration-200 hover:shadow-md" data-pub-id="<?= $pub['Pub_id']; ?>">
                            <div class="flex items-start justify-between cursor-pointer" onclick="toggleDetails('<?= $pub['Pub_id']; ?>', this)">
                                <div class="flex-1 min-w-0 pr-4">
                                    <span class="text-md font-semibold text-blue-600 mr-2"><?= $count; ?>.</span>
                                    <span class="text-lg font-medium text-gray-900 leading-snug block md:inline"><?php echo htmlspecialchars($pub['title']); ?></span>
                                    <p class="text-sm text-gray-500 mt-1 md:mt-0 md:inline block"> (ปี <?php echo $pub['publish_year']; ?>)</p>
                                </div>
                                <i class="fas fa-chevron-down text-gray-500 transition-transform duration-300"></i>
                            </div>
                            
                            <!-- รายละเอียดที่ซ่อนอยู่ -->
                            <div id="details-<?= $pub['Pub_id']; ?>" class="details mt-4 text-gray-700 space-y-3 hidden border-t border-gray-200 pt-4">
                                <p class="text-sm">รหัสสิ่งพิมพ์: **<?php echo $pub['Pub_id']; ?>**</p>

                                <?php if(!empty($pub['file_path'])): ?>
                                    <!-- ปุ่มดูเอกสาร (PDF Viewer) -->
                                    <a href="<?php echo htmlspecialchars($pub['file_path']); ?>" target="_blank"
                                       class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-full shadow-md 
                                              hover:bg-green-700 transition-colors duration-200 text-sm font-medium">
                                        <i class="fas fa-file-pdf mr-2"></i> ดูเอกสาร (PDF Viewer)
                                    </a>
                                <?php else: ?>
                                    <p class="text-red-500 text-sm"><i class="fas fa-exclamation-triangle mr-2"></i> ไม่พบไฟล์เอกสาร</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-center text-gray-500 py-6">อาจารย์ท่านนี้ยังไม่มีผลงานตีพิมพ์ที่ได้รับการอนุมัติ</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function toggleDetails(pubId, buttonElement) {
    const details = document.getElementById(`details-${pubId}`);
    const icon = buttonElement.querySelector('i');
    
    details.classList.toggle('hidden');
    icon.classList.toggle('rotate-180');
}
</script>

</body>
</html>
