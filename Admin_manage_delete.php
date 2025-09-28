<?php
// เริ่ม session
session_start();

// ตรวจสอบการเข้าสู่ระบบ: หากไม่มี session หรือ role ไม่ใช่ admin ให้ redirect
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login-v1.php");
    exit();
}

// --- เชื่อมต่อฐานข้อมูล ---
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "group10";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    // ในกรณีที่เชื่อมต่อฐานข้อมูลล้มเหลว
    header("Location: Admin-manage.php?delete_status=fail_db");
    exit();
}

// ตรวจสอบว่ามีการส่ง ID มาหรือไม่
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    // หากไม่มี ID ให้ redirect กลับพร้อมสถานะล้มเหลว
    header("Location: Admin-manage.php?delete_status=fail_no_id");
    exit();
}

$delete_id = (int)$_GET['id'];
$delete_status = "fail"; // สถานะเริ่มต้น

// ใช้ Prepared Statement เพื่อลบผู้ใช้
// ลบเฉพาะผู้ใช้ที่มี role เป็น 'normal' หรือ 'staff' เท่านั้น ป้องกันการลบ admin ตัวเองโดยไม่ได้ตั้งใจ
$stmt = $conn->prepare("DELETE FROM User WHERE User_id = ? AND role IN ('normal', 'staff')");

if ($stmt === false) {
    // หากเตรียมคำสั่ง SQL ล้มเหลว
    $delete_status = "fail_prepare";
} else {
    $stmt->bind_param("i", $delete_id);
    
    if ($stmt->execute()) {
        // ตรวจสอบว่ามีแถวที่ถูกลบจริงหรือไม่
        if ($stmt->affected_rows > 0) {
            $delete_status = "success";
        } else {
            // ไม่พบผู้ใช้งาน ID นั้น หรือผู้ใช้เป็น admin
            $delete_status = "fail_not_found";
        }
    } else {
        // หาก execute ล้มเหลว
        $delete_status = "fail_execute";
    }

    $stmt->close();
}

$conn->close();

// Redirect กลับไปยังหน้าจัดการพร้อมสถานะการลบ
header("Location: Admin-manage.php?delete_status=" . $delete_status);
exit();
?>
