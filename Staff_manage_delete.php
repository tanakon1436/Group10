<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "group10";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("การเชื่อมต่อล้มเหลว: " . $conn->connect_error);
}

if (isset($_GET['id'])) {
    $user_id = intval($_GET['id']);

    // ตรวจสอบว่ามี user อยู่จริงไหม
    $check_sql = "SELECT * FROM User WHERE User_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $user_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();

    if ($result->num_rows > 0) {
        $sql = "DELETE FROM User WHERE User_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);

        if ($stmt->execute()) {
            echo "<script>alert('ลบข้อมูลเรียบร้อย'); window.location='Staff_manage.php';</script>";
        } else {
            echo "<script>alert('เกิดข้อผิดพลาดในการลบข้อมูล: " . $stmt->error . "'); window.location='Staff_manage.php';</script>";
        }
        $stmt->close();
    } else {
        echo "<script>alert('ไม่พบข้อมูลที่ต้องการลบ'); window.location='Staff_manage.php';</script>";
    }
    $check_stmt->close();
} else {
    echo "<script>alert('ไม่พบรหัสผู้ใช้'); window.location='Staff_manage.php';</script>";
}

$conn->close();
?>