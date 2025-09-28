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
    // เปลี่ยนชื่อตัวแปรเป็น $input เพื่อรองรับทั้ง username และ email
    $input = trim($_POST["username_or_email"] ?? "");
    $pass = trim($_POST["password"] ?? "");

    if ($input === "" || $pass === "") {
        // แก้ไขข้อความแจ้งเตือนให้รองรับการกรอก username หรือ email
        $error = "กรุณากรอกชื่อผู้ใช้หรืออีเมล และรหัสผ่าน";
    } else {
        // ตรวจสอบ username/email + password
        // *** ตรรกะการตรวจสอบ Username หรือ Email อยู่ตรงนี้ ***
        // ต้องเพิ่มคอลัมน์ Email ในการ SELECT ด้วย
        $stmt = $conn->prepare("SELECT User_id, Username, role, first_name, last_name, Email
                                FROM User 
                                WHERE (Username = ? OR Email = ?) AND Password = ? 
                                LIMIT 1");
        // ผูกค่าตัวแปร: 'sss' (input, input, password)
        $stmt->bind_param("sss", $input, $input, $pass);
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
            $ins = $conn->prepare("INSERT INTO LoginHistory (User_id, time, success) VALUES (?, NOW(), 1)");
            if ($ins) {
                $ins->bind_param("i", $user_id);
                $ins->execute();
                $ins->close();
            }

            $stmt->close();

            // Redirect ตาม role โดยตรวจสอบ Staff ก่อน Admin
            if ($row['role'] === "staff") {
                header("Location: staffPage.php"); // หน้าสำหรับ Staff
                exit();
            } else if ($row['role'] === "admin") {
                header("Location: Admin-manage.php"); // หน้าสำหรับ Admin
                exit();
            } else { // บทบาทอื่นๆ (Normal User, Guest, ฯลฯ)
                header("Location: Home-PR.php"); // หน้าสำหรับผู้ใช้งานทั่วไป (Normal)
                exit();
            }
        } else {
            $stmt->close();
            $error = "❌ ชื่อผู้ใช้/อีเมล หรือรหัสผ่านไม่ถูกต้อง"; // แก้ไขข้อความ error
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title>เข้าสู่ระบบ</title>
  <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<!-- ✅ เปลี่ยน body เป็น flex container และเพิ่ม min-h-screen -->
<body class="bg-gray-50 font-sans flex flex-col min-h-screen">

  <!-- Header สไตล์ Home-PR -->
  <header class="bg-[#cce4f9] py-5 shadow-xl flex items-center justify-center relative">
    <!-- Logo -->
    <img src="./img/img_psu.png" alt="PSU Logo" class="h-24 w-auto absolute left-6 top-1/2 -translate-y-1/2 hidden md:block">

    <!-- Title -->
    <h1 class="text-2xl md:text-3xl font-extrabold text-blue-800 tracking-wider text-center">
        ระบบค้นหาและจัดการผลงานตีพิมพ์
    </h1>
  </header>

  <!-- Container (Main Content) - ใช้ flex-grow เพื่อให้กินพื้นที่ที่เหลือทั้งหมด -->
  <div class="flex items-center justify-center px-4 py-8 flex-grow">
    <div class="w-full max-w-md bg-white rounded-3xl shadow-2xl p-8 border border-blue-200">
      
      <h2 class="text-3xl font-bold text-center text-blue-600 mb-8">
        <i class="fas fa-sign-in-alt mr-2"></i> เข้าสู่ระบบ
      </h2>

      <!-- Error -->
      <?php if (!empty($error)) : ?>
        <div class="mb-5 p-4 text-sm text-red-700 bg-red-100 border border-red-300 rounded-xl font-medium shadow-inner">
          <?php echo htmlspecialchars($error); ?>
        </div>
      <?php endif; ?>

      <!-- Form -->
      <form class="space-y-6" method="POST" action="">
        <!-- Username/Email Input -->
        <div>
          <label for="username_or_email" class="block text-sm font-semibold text-gray-700 mb-2">
            ชื่อผู้ใช้ หรือ อีเมล
          </label>
          <input type="text" id="username_or_email" name="username_or_email" required
                 placeholder="กรอกชื่อผู้ใช้ หรือ อีเมล"
                 class="w-full px-4 py-3 border border-gray-300 rounded-xl text-base 
                        focus:outline-none focus:ring-4 focus:ring-blue-200 focus:border-blue-500 transition-all shadow-sm">
        </div>

        <!-- Password -->
        <div class="relative">
          <label for="password" class="block text-sm font-semibold text-gray-700 mb-2">
            รหัสผ่าน
          </label>
          <input type="password" id="password" name="password" required
                 placeholder="กรอกรหัสผ่าน"
                 class="w-full px-4 py-3 border border-gray-300 rounded-xl text-base 
                        focus:outline-none focus:ring-4 focus:ring-blue-200 focus:border-blue-500 transition-all shadow-sm">
          <!-- ปุ่มแสดงรหัสผ่าน (Toggle Password Visibility) -->
          <span class="absolute right-4 top-1/2 mt-3 cursor-pointer text-gray-400 hover:text-blue-500 transition-colors"
                onclick="togglePasswordVisibility()">
            <i id="eye-icon" class="fas fa-eye"></i>
          </span>
        </div>

        <!-- Submit -->
        <button type="submit" 
                class="w-full py-3 bg-blue-600 rounded-xl 
                       font-bold text-white text-lg shadow-lg 
                       hover:bg-blue-700 transition-colors duration-200 focus:outline-none focus:ring-4 focus:ring-blue-300">
          <i class="fas fa-lock mr-2"></i> เข้าสู่ระบบ
        </button>
      </form>

      <!-- ปุ่มสำหรับบุคคลทั่วไป -->
      <div class="mt-6 pt-6 border-t border-gray-200">
            <h3 class="text-center text-gray-600 mb-4 font-semibold">หรือเข้าสู่ระบบในฐานะ</h3>
            <a href="HomeallPage-v1.php" 
               class="w-full flex justify-center items-center py-3 px-4 border-2 border-green-500 
                      rounded-xl shadow-md text-lg font-bold text-green-700 bg-green-50 
                      hover:bg-green-100 transition duration-200 ease-in-out">
                <i class="fas fa-globe mr-2"></i> บุคคลทั่วไป (Home)
            </a>
        </div>
        
    </div>
  </div>

  <script>
    function togglePasswordVisibility() {
      const passwordField = document.getElementById('password');
      const eyeIcon = document.getElementById('eye-icon');
      
      if (passwordField.type === 'password') {
        passwordField.type = 'text';
        eyeIcon.classList.remove('fa-eye');
        eyeIcon.classList.add('fa-eye-slash');
      } else {
        passwordField.type = 'password';
        eyeIcon.classList.remove('fa-eye-slash');
        eyeIcon.classList.add('fa-eye');
      }
    }
  </script>

  <!-- ✅ Footer ติดขอบล่างโดยอัตโนมัติด้วย flexbox (mt-auto ถูกลบออกเนื่องจาก body เป็น flex-col แล้ว) -->
  <footer class="p-4 bg-gray-200 text-center text-gray-600 text-sm">
    &copy; <?php echo date("Y"); ?> ระบบค้นหาและจัดการผลงานตีพิมพ์ | มหาวิทยาลัยสงขลานครินทร์ (PSU)
  </footer>
</body>
</html>
<?php $conn->close(); ?>
