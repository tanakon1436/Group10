<?php
// *************************************************************
// ** DEBUGGING BLOCK: เปิดการแสดงข้อผิดพลาดของ PHP **
// *************************************************************
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// *************************************************************

session_start();

// ตรวจสอบว่า user ล็อกอินหรือยัง
if (!isset($_SESSION['user_id'])) {
    header("Location: login-v1.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$pub_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($pub_id === 0) {
    die("Error: ไม่พบรหัสผลงานที่ต้องการดาวน์โหลด");
}

// เชื่อมต่อฐานข้อมูล
$servername = "localhost";
$username = "root"; 
$password = "";    
$dbname = "group10"; 

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Error: การเชื่อมต่อฐานข้อมูลล้มเหลว: " . $conn->connect_error);
}

// *************************************************************
// ** 1. ดึงข้อมูลไฟล์จาก Pub_id และตรวจสอบความเป็นเจ้าของ (Author_id) **
// *************************************************************
$sql = "SELECT title, file_path 
        FROM Publication 
        WHERE Pub_id = ? AND Author_id = ?"; // ต้องเป็น Author_id เท่านั้นที่ดาวน์โหลดได้

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Error: การเตรียมคำสั่ง SQL ล้มเหลว: " . $conn->error);
}

$stmt->bind_param("ii", $pub_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // ไม่พบผลงาน หรือ ผลงานนั้นไม่ได้เป็นของ User นี้ (การพยายามเข้าถึงไฟล์ที่ไม่ใช่ของตัวเอง)
    die("Error: ไม่พบไฟล์ หรือ คุณไม่มีสิทธิ์เข้าถึงไฟล์นี้ (ID: $pub_id)");
}

$pub = $result->fetch_assoc();
$file_path = $pub['file_path'];
$original_filename = $pub['title']; // ใช้ชื่อผลงานเป็นชื่อไฟล์ดาวน์โหลด

$stmt->close();
$conn->close();

if (empty($file_path) || !file_exists($file_path)) {
    die("Error: ไฟล์เอกสารไม่พบในเซิร์ฟเวอร์ (Path: " . htmlspecialchars($file_path) . ")");
}

// *************************************************************
// ** 2. ส่ง HTTP HEADERS เพื่อบังคับการดาวน์โหลด **
// *************************************************************

// กำหนด MIME Type 
$mime = mime_content_type($file_path);
if ($mime === false) {
    $mime = 'application/octet-stream'; // ค่า default หากไม่สามารถตรวจจับประเภทไฟล์
}

// กำหนดชื่อไฟล์สำหรับดาวน์โหลด
$extension = pathinfo($file_path, PATHINFO_EXTENSION);
$download_filename = preg_replace("/[^a-zA-Z0-9\s]/", "_", $original_filename) . '.' . $extension;

// ป้องกันปัญหาแคช
header('Pragma: public');
header('Expires: 0');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Cache-Control: private', false); 

// ส่งประเภทเนื้อหา
header("Content-Type: $mime");

// ส่งขนาดไฟล์
header("Content-Length: " . filesize($file_path));

// บังคับให้ดาวน์โหลดด้วยชื่อไฟล์ใหม่
header("Content-Disposition: attachment; filename=\"$download_filename\";");

// อ่านไฟล์และส่งไปยัง output
readfile($file_path);
exit;
?>
