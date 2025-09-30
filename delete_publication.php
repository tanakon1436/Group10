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

// เชื่อมต่อฐานข้อมูล
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "group10";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    header("Location: publications.php?delete_status=error_db&error_msg=" . urlencode("Connection failed: " . $conn->connect_error));
    exit;
}

$user_id = $_SESSION['user_id'];
$pub_id_to_delete = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($pub_id_to_delete <= 0) {
    header("Location: publications.php?delete_status=error_invalid");
    exit;
}

// 1. ตรวจสอบสิทธิ์: ต้องเป็นเจ้าของผลงานเท่านั้นที่ลบได้
$sql_check = "SELECT Author_id, file_path FROM Publication WHERE Pub_id = ?";
$stmt_check = $conn->prepare($sql_check);
if ($stmt_check) {
    $stmt_check->bind_param("i", $pub_id_to_delete);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    $publication = $result_check->fetch_assoc();
    $stmt_check->close();

    // ถ้าไม่พบผลงาน หรือ Author_id ไม่ตรงกับ User_id ที่ล็อกอินอยู่
    if (!$publication || $publication['Author_id'] != $user_id) {
        header("Location: publications.php?delete_status=error_permission");
        exit;
    }
} else {
    header("Location: publications.php?delete_status=error_db_prep");
    exit;
}

// --- เริ่มการดำเนินการลบ ---
// การลบต้องคำนึงถึง Foreign Key Constraints
// ลำดับการลบ: 1. PublicationHistory -> 2. Notification (ที่เชื่อมกับ Pub_id) -> 3. Publication

$conn->begin_transaction();
$delete_success = true;
$file_to_delete = $publication['file_path'];

try {
    // 1. ลบจาก PublicationHistory
    $sql_history = "DELETE FROM PublicationHistory WHERE Pub_id = ?";
    $stmt_history = $conn->prepare($sql_history);
    $stmt_history->bind_param("i", $pub_id_to_delete);
    if (!$stmt_history->execute()) {
        throw new Exception("Error deleting history: " . $stmt_history->error);
    }
    $stmt_history->close();

    // 2. ลบจาก Notification (ในกรณีที่มีการแจ้งเตือนที่อ้างอิงถึง Pub_id นี้)
    $sql_noti = "DELETE FROM Notification WHERE Pub_id = ?";
    $stmt_noti = $conn->prepare($sql_noti);
    $stmt_noti->bind_param("i", $pub_id_to_delete);
    if (!$stmt_noti->execute()) {
        throw new Exception("Error deleting notifications: " . $stmt_noti->error);
    }
    $stmt_noti->close();

    // 3. ลบจาก Publication (ตารางหลัก)
    $sql_pub = "DELETE FROM Publication WHERE Pub_id = ?";
    $stmt_pub = $conn->prepare($sql_pub);
    $stmt_pub->bind_param("i", $pub_id_to_delete);
    if (!$stmt_pub->execute()) {
        throw new Exception("Error deleting publication: " . $stmt_pub->error);
    }
    $stmt_pub->close();
    
    // ถ้าทุกอย่างสำเร็จ
    $conn->commit();

    // 4. ลบไฟล์จริงออกจากเซิร์ฟเวอร์ (ถ้ามี)
    if (!empty($file_to_delete) && file_exists($file_to_delete)) {
        unlink($file_to_delete);
    }
    
    header("Location: publications.php?delete_status=success");
    exit;

} catch (Exception $e) {
    $conn->rollback();
    // Redirect พร้อมข้อความ Error ที่เข้ารหัส URL
    header("Location: publications.php?delete_status=error&error_msg=" . urlencode("Transaction failed: " . $e->getMessage()));
    exit;
} finally {
    $conn->close();
}
?>