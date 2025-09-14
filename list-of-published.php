<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title>ระบบ จัดการการตีพิมพ์ผลงานของอาจารย์</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    function confirmDelete(el) {
      if (confirm("คุณแน่ใจหรือไม่ว่าต้องการลบผลงานนี้?")) {
        el.parentElement.parentElement.remove();
      }
    }

    function toggleSidebar() {
      document.getElementById("sidebar").classList.toggle("-translate-x-full");
    }

    // โหลดชื่อผู้ใช้จาก localStorage
    window.onload = function() {
      const username = localStorage.getItem("username") || "ผศ.XXX XXXXX";
      document.getElementById("username").innerText = username;
    }
  </script>
</head>
<body class="bg-white font-sans">

  <!-- Header -->
  <header class="flex items-center justify-between bg-blue-100 p-4 shadow fixed top-0 left-0 w-full z-40">
    <div class="flex items-center gap-3">
      <!-- ปุ่มสามขีดตลอดเวลา -->
      <button class="text-2xl" onclick="toggleSidebar()">&#9776;</button>
      <h1 class="text-lg font-semibold">ระบบ จัดการการตีพิมพ์ผลงานของอาจารย์</h1>
    </div>
    <div class="flex gap-4 text-xl">
      <button>🔔</button>
      <button>👤</button>
    </div>
  </header>

  <!-- Sidebar -->
  <aside id="sidebar" class="fixed top-0 left-0 w-64 h-full bg-white shadow transform -translate-x-full transition-transform duration-300 z-30">
    <div class="p-4 border-b">
      <div class="flex items-center gap-2">
        <span class="text-2xl">👤</span>
        <span id="username" class="font-medium">กำลังโหลด...</span>
      </div>
    </div>
    <nav class="p-4 space-y-4">
      <a href="list-of-published.php" class="flex items-center gap-2 hover:text-blue-600"><span>🏠</span> หน้าหลัก</a>
      <a href="#" class="flex items-center gap-2 hover:text-blue-600"><span>⏳</span> ประวัติการแก้ไข</a>
      <a href="#" class="flex items-center gap-2 hover:text-blue-600"><span>📘</span> คู่มือการใช้งาน</a>
      <a href="#" class="flex items-center gap-2 hover:text-blue-600"><span>📞</span> ช่องทางติดต่อ</a>
    </nav>
    <div class="absolute bottom-0 w-full border-t p-4">
      <a href="login.php" class="flex items-center gap-2 text-red-600 hover:text-red-800"><span>↩️</span> ออกจากระบบ</a>
    </div>
  </aside>

  <!-- เนื้อหาหน้าเว็บ -->
  <main class="p-6 pt-20">
    <div class="flex items-center justify-center mb-6">
      <h2 class="text-xl font-semibold">รายการผลงานตีพิมพ์</h2>
    </div>

    <!-- งานที่ 1 -->
    <div class="bg-blue-100 p-4 rounded-xl mb-4 flex justify-between items-center">
      <div>
        <p class="font-semibold">ผลงานที่ 1</p>
        <p>ชื่อผลงานวิจัย โปรแกรมการวิเคราะห์คำและนับจำนวนเซลล์</p>
      </div>
      <div class="flex items-center gap-3">
        <span class="bg-green-500 text-white px-3 py-1 rounded-lg">อนุมัติแล้ว</span>
        <button onclick="confirmDelete(this)" class="bg-gray-200 p-2 rounded-lg">➖</button>
        <a href="edit-of-published.php" class="bg-gray-200 p-2 rounded-lg">✏️</a>
      </div>
    </div>

    <!-- งานที่ 2 -->
    <div class="bg-blue-100 p-4 rounded-xl mb-4 flex justify-between items-center">
      <div>
        <p class="font-semibold">ผลงานที่ 2</p>
        <p>ชื่อผลงานวิจัย xxxxxxxxxxxxxxxxxxxxxxxx</p>
      </div>
      <div class="flex items-center gap-3">
        <span class="bg-yellow-500 text-white px-3 py-1 rounded-lg">รออนุมัติ</span>
        <button onclick="confirmDelete(this)" class="bg-gray-200 p-2 rounded-lg">➖</button>
        <a href="edit-of-published.php" class="bg-gray-200 p-2 rounded-lg">✏️</a>
      </div>
    </div>

    <!-- งานที่ 3 -->
    <div class="bg-blue-100 p-4 rounded-xl mb-4 flex justify-between items-center">
      <div>
        <p class="font-semibold">ผลงานที่ 3</p>
        <p>ชื่อผลงานวิจัย xxxxxxxxxxxxxxxxxxxxxxxx</p>
      </div>
      <div class="flex items-center gap-3">
        <span class="bg-red-500 text-white px-3 py-1 rounded-lg">ปฏิเสธ</span>
        <button onclick="confirmDelete(this)" class="bg-gray-200 p-2 rounded-lg">➖</button>
        <a href="edit-of-published.php" class="bg-gray-200 p-2 rounded-lg">✏️</a>
      </div>
    </div>
  </main>

</body>
</html>
