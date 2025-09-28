<?php
// === START: DEBUGGING AND ERROR REPORTING ===
// เปิดการแสดงข้อผิดพลาด PHP เพื่อช่วยในการดีบัก (ปิดเมื่อใช้งานจริง)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// === END: DEBUGGING AND ERROR REPORTING ===

// ตรวจสอบว่ามีการส่งคำค้นหา 'q' มาจาก AJAX หรือไม่
if (!isset($_GET['q'])) {
    exit;
}

// 1. กำหนดค่าเชื่อมต่อฐานข้อมูล (DB: group10)
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "group10";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    // ถ้าเชื่อมต่อฐานข้อมูลไม่ได้ ให้แสดงข้อผิดพลาด
    echo '<div class="p-4 bg-red-100 text-red-700 border border-red-300 rounded-lg shadow-md">⚠️ ข้อผิดพลาดฐานข้อมูล (Connection): ไม่สามารถเชื่อมต่อได้ (' . htmlspecialchars($conn->connect_error) . ')</div>';
    exit;
}

// 2. รับคำค้นหาและเตรียม Query
$query = trim($_GET['q']);
// ใช้ % เพื่อค้นหาบางส่วนของชื่อ/นามสกุล
$search_param = "%" . $query . "%";
$output = '';

// SQL: ค้นหาในฟิลด์ first_name หรือ last_name โดยมีบทบาทเป็น 'normal' เท่านั้น
$sql = "SELECT User_id, first_name, last_name, Department, avatar 
        FROM User 
        WHERE role = 'normal' 
        AND (first_name LIKE ? OR last_name LIKE ?)
        ORDER BY first_name";

$stmt = $conn->prepare($sql);
if ($stmt) {
    // ผูกค่า parameter: "ss" คือสองตัวแปรเป็น string
    $stmt->bind_param("ss", $search_param, $search_param);
    
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        
        // 3. สร้าง HTML Output
        if ($result->num_rows > 0) {
            
            while ($row = $result->fetch_assoc()) {
                // *** การจัดการ Avatar Path: อ้างอิงจากโฟลเดอร์ img/ ***
                $avatar_filename = htmlspecialchars($row['avatar']);
                $avatar_path = !empty($avatar_filename) ? 'img/' . $avatar_filename : '';
                
                $avatar_html = '';
                if (!empty($avatar_path)) {
                    // หากมีรูปภาพ ให้ใช้ onerror เพื่อจัดการกรณีที่ไฟล์รูปภาพไม่พบ
                    $avatar_html = '<img src="' . $avatar_path . '" alt="ผู้ใช้งาน" class="w-16 h-16 rounded-full object-cover border-2 border-blue-400" onerror="this.onerror=null; this.src=\'https://placehold.co/64x64/cccccc/333333?text=A\';">';
                } else {
                    // หากไม่มีรูปภาพ ให้แสดงไอคอน default
                    $avatar_html = '
                    <div class="w-16 h-16 rounded-full bg-blue-300 flex items-center justify-center text-white text-2xl font-bold">
                        <i class="fas fa-user"></i>
                    </div>';
                }

                $output .= '
                <div class="flex justify-between items-center bg-[#eff6ff] p-4 rounded-xl shadow-lg border border-blue-200 hover:shadow-xl transition-shadow duration-300">
                    <div class="flex items-center space-x-4">
                        ' . $avatar_html . '
                        <div>
                            <p class="text-lg font-bold text-blue-800">' . htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) . '</p>
                            <p class="text-gray-600 text-sm"><i class="fas fa-graduation-cap mr-1"></i> อาจารย์</p>
                            <p class="text-gray-500 text-sm"><i class="fas fa-building mr-1"></i> ' . htmlspecialchars($row['Department']) . '</p>
                            <p class="text-xs text-gray-400 mt-1">ID: ' . htmlspecialchars($row['User_id']) . '</p>
                        </div>
                    </div>
                    <div class="flex space-x-2">
                        <!-- ปุ่มแก้ไข -->
                        <button onclick="window.location.href=\'Staff_manage_update.php?id=' . $row['User_id'] . '\'" 
                                class="p-3 rounded-full bg-yellow-400 text-white shadow-md hover:bg-yellow-500 transition-colors duration-150"
                                title="แก้ไขข้อมูล">
                            <i class="fas fa-pencil-alt text-lg"></i>
                        </button>
                        <!-- ปุ่มลบ (เรียกใช้ฟังก์ชัน JS จาก Staff_manage.php) -->
                        <button onclick="confirmDelete(' . $row['User_id'] . ')" 
                                class="p-3 rounded-full bg-red-600 text-white shadow-md hover:bg-red-700 transition-colors duration-150"
                                title="ลบข้อมูล">
                            <i class="fas fa-trash-alt text-lg"></i>
                        </button>
                    </div>
                </div>';
            }
        } else {
            $output = '<div class="p-4 bg-yellow-100 text-yellow-700 border border-yellow-300 rounded-lg shadow-md">ไม่พบอาจารย์ที่ตรงกับคำค้นหา "' . htmlspecialchars($query) . '"</div>';
        }
    } else {
         // ข้อผิดพลาดในการ Execute
        $output = '<div class="p-4 bg-red-100 text-red-700 border border-red-300 rounded-lg shadow-md">เกิดข้อผิดพลาดในการรันคำสั่ง SQL: ' . htmlspecialchars($stmt->error) . '</div>';
    }


    $stmt->close();
} else {
    // ข้อผิดพลาดในการ Prepare
    $output = '<div class="p-4 bg-red-100 text-red-700 border border-red-300 rounded-lg shadow-md">เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL: ' . htmlspecialchars($conn->error) . '</div>';
}

$conn->close();
echo $output;

?>
