<?php
// เริ่ม session
session_start();

// เชื่อมต่อ Database
$servername = "localhost";
$db_user = "root";
$db_pass = "";
$dbname = "group10";

$conn = new mysqli($servername, $db_user, $db_pass, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$error = ""; // เก็บข้อความ error

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $user = trim($_POST["username"] ?? "");
    $pass = trim($_POST["password"] ?? "");

    if ($user === "" || $pass === "") {
        $error = "กรุณากรอกชื่อผู้ใช้และรหัสผ่าน";
    } else {
        // ตรวจสอบ username + password
        $stmt = $conn->prepare("SELECT User_id, Username, role, first_name, last_name 
                                FROM `User` 
                                WHERE Username = ? AND Password = ? 
                                LIMIT 1");
        $stmt->bind_param("ss", $user, $pass);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res && $res->num_rows === 1) {
            // login สำเร็จ
            $row = $res->fetch_assoc();
            $user_id = (int)$row['User_id'];

            // เก็บ session (ชื่อต้องตรงกับหน้า dashboard)
            $_SESSION["user_id"] = $user_id;
            $_SESSION["username"] = $row['Username'];
            $_SESSION["role"] = $row['role'];
            $_SESSION["first_name"] = $row['first_name'];
            $_SESSION["last_name"] = $row['last_name'];

            // บันทึก LoginHistory (success = 1)
            $ins = $conn->prepare("INSERT INTO LoginHistory (User_id, `time`, success) VALUES (?, NOW(), 1)");
            if ($ins) {
                $ins->bind_param("i", $user_id);
                $ins->execute();
                $ins->close();
            }

            $stmt->close();

            // ✅ Redirect ตาม role
            if ($row['role'] === "admin") {
                header("Location: Admin-manage.php");
                exit();
            } else {
                header("Location: Home-PR.php");
                exit();
            }
        } else {
            $stmt->close();
            $error = "❌ ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title>Log in</title>
  <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-white font-sans m-0">

  <header class="bg-[#cce4f9] py-4 text-center text-2xl font-bold">
    เข้าสู่ระบบ
  </header>

  <div class="max-w-md mx-auto mt-12 p-8 bg-[#e6f2ff] rounded-2xl text-center">
    <h2 class="text-xl font-semibold mb-6">ยินดีต้อนรับ</h2>

    <!-- แสดง error ถ้ามี -->
    <?php if (!empty($error)) : ?>
      <p class="text-red-500 mb-4 font-bold"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>

    <form class="space-y-6" method="POST" action="">
      <div class="text-left">
        <label for="username" class="font-bold block mb-1">บัญชีผู้ใช้ </label>
        <input type="text" id="username" name="username" required
               placeholder="กรอกชื่อบัญชีผู้ใช้"
               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-400">
      </div>

      <div class="text-left relative">
        <label for="password" class="font-bold block mb-1">รหัสผ่าน </label>
        <input type="password" id="password" name="password" required
               placeholder="กรอกรหัสผ่าน"
               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-400">
        <span class="absolute right-3 top-1/2 -translate-y-1/2 cursor-pointer text-gray-600">
          <i class="fas fa-eye"></i>
        </span>
      </div>

      <button type="submit" 
              class="w-full py-3 bg-white border border-gray-300 rounded-lg font-bold text-lg hover:bg-gray-100">
        เข้าสู่ระบบ
      </button>
    </form>
  </div>
</body>
</html>
<?php $conn->close(); ?>
