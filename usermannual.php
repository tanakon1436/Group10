<?php
// usermanual.php
// โค้ดสำหรับตรวจสอบการล็อกอินสามารถเพิ่มได้ที่นี่ หากหน้านี้ต้องการการล็อกอิน
// session_start();
// if (!isset($_SESSION['user_id'])) {
//     header("Location: login-v1.php");
//     exit;
// }
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>คู่มือการใช้งานระบบ</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .psu-logo {
            height: 100px; 
            object-fit: contain;
        }
    </style>
</head>
<body class="flex min-h-screen font-sans bg-gray-100">

<!-- Sidebar: เหลือเพียง Logo, User Info, Back Button, และ Logout -->
<aside class="w-64 bg-white shadow-lg p-6 flex flex-col sticky top-0 h-screen">
    <div class="flex flex-col items-center border-b pb-4 mb-4">
        <img src="./img/img_psu.png" alt="PSU Logo" class="psu-logo">
        <span class="text-xs font-semibold text-gray-600">ระบบจัดการการตีพิมพ์</span>
    </div>

    <!-- NEW: ปุ่มย้อนกลับเพียงปุ่มเดียวตามที่ร้องขอ -->
    <div class="w-full flex-grow pt-4">
        <button id="back-button-sidebar" class="flex items-center justify-center w-full p-3 rounded-lg text-white bg-blue-600 hover:bg-blue-700 transition-colors duration-150 font-semibold text-base shadow-lg ring-2 ring-blue-300">
            <i class="fas fa-arrow-left w-5 h-5 mr-3"></i> กลับไปยังหน้าก่อนหน้า
        </button>
    </div>
    
    <!-- Logout -->
    <div class="px-0 pt-4 border-t border-gray-200">
      <a href="logout.php" class="flex items-center p-3 rounded-lg text-red-500 hover:bg-red-50 transition-colors duration-150">
        <i class="fas fa-sign-out-alt w-5 h-5 mr-3"></i> ออกจากระบบ
      </a>
    </div>
</aside>

<!-- Main Content -->
<main class="flex-1 p-8">
    <header class="flex items-center justify-between mb-8 pb-4 border-b border-gray-300">
        <h1 class="text-3xl font-extrabold text-gray-800">คู่มือการใช้งานระบบ</h1>
    </header>

    <!-- ลบปุ่มกลับหน้าหลักออกจาก Main Content เนื่องจากมีใน Sidebar แล้ว -->

    <section class="bg-white p-6 rounded-xl shadow-2xl">
        <h2 class="text-2xl font-bold text-center mb-6 text-gray-700">คู่มือการใช้งาน (PDF)</h2>

        <!-- PDF Viewer -->
        <div class="h-[70vh] min-h-[500px] w-full">
             <object data="./uploads/G10_Manual.pdf" type="application/pdf" width="100%" height="100%" class="border rounded-lg shadow-inner">
                <div class="p-8 text-center bg-red-50 border border-red-200 rounded-lg">
                    <p class="text-lg text-red-700 font-semibold mb-3">
                        <i class="fas fa-exclamation-triangle mr-2"></i> ไม่สามารถแสดงไฟล์ PDF ได้
                    </p>
                    <p class="text-gray-600">
                        เบราว์เซอร์ของคุณอาจไม่รองรับการแสดงผล PDF โดยตรง กรุณาดาวน์โหลดคู่มือเพื่อดู
                    </p>
                    <a href="./uploads/G10_Manual.pdf" target="_blank" class="mt-4 inline-block bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition duration-150">
                        <i class="fas fa-download mr-2"></i> ดาวน์โหลดคู่มือ (manual.pdf)
                    </a>
                </div>
            </object>
        </div>
    </section>
</main>

<script>
    // เพิ่ม JavaScript สำหรับปุ่มย้อนกลับใน Sidebar
    document.getElementById('back-button-sidebar').addEventListener('click', () => {
        // ใช้ history.back() เพื่อกลับไปยังหน้าล่าสุดที่ผู้ใช้มาจาก
        history.back();
    });
</script>

</body>
</html>
