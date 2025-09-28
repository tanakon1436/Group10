<?php
// 1. ต้องเรียกใช้ session_start() ก่อนเสมอ เพื่อให้เข้าถึงข้อมูลเซสชันได้
session_start();

// 2. ล้างตัวแปรทั้งหมดในเซสชัน
$_SESSION = array();

// 3. ทำลายข้อมูลเซสชันบนเซิร์ฟเวอร์
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

// 4. นำทางผู้ใช้ไปยังหน้าล็อกอิน
header("Location: login-v1.php");
exit;
?>