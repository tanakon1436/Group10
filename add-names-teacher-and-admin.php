<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>เพิ่มรายชื่ออาจารย์และเจ้าหน้าที่</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-blue-50 font-sans">

  <!-- Header -->
  <header class="flex items-center justify-between bg-blue-100 px-6 py-4 shadow relative font-sans">
    <!-- Hamburger menu -->
    <button id="menuBtn" class="space-y-1 cursor-pointer z-50">
      <div class="w-6 h-0.5 bg-black"></div>
      <div class="w-6 h-0.5 bg-black"></div>
      <div class="w-6 h-0.5 bg-black"></div>
    </button>

    <!-- Title -->
    <h1 class="absolute left-1/2 -translate-x-1/2 text-lg font-semibold">
      ระบบ เพิ่มรายชื่ออาจารย์และเจ้าหน้าที่
    </h1>

    <!-- User icon -->
    <div class="w-8 h-8 flex items-center justify-center text-xl">
      👤
    </div>
  </header>

  <div class="flex">
    <!-- Sidebar -->
    <aside id="sidebar"
           class="fixed top-0 left-0 w-64 bg-blue-50 min-h-screen border-r transform -translate-x-full transition-transform duration-300 z-40">
      <nav class="flex flex-col text-sm pt-16">
        <a href="#" class="px-6 py-3 flex items-center gap-3 hover:bg-blue-100">
          <span>🔍</span> ค้นหาผลงานตีพิมพ์
        </a>
        <a href="#" class="px-6 py-3 flex items-center gap-3 hover:bg-blue-100">
          <span>✏️</span> แก้ไขข้อมูลอาจารย์
        </a>
        <a href="#" class="px-6 py-3 flex items-center gap-3 bg-gray-200 font-semibold">
          <span>➕</span> เพิ่มข้อมูลอาจารย์ / เจ้าหน้าที่
        </a>
        <a href="#" class="px-6 py-3 flex items-center gap-3 hover:bg-blue-100">
          <span>🔔</span> ตั้งการแจ้งเตือน
        </a>
        <a href="#" class="px-6 py-3 flex items-center gap-3 hover:bg-blue-100">
          <span>📄</span> รายงานผล / ดาวน์โหลด PDF
        </a>
      </nav>
    </aside>

    <!-- Overlay -->
    <div id="overlay" class="fixed inset-0 bg-black bg-opacity-40 hidden z-30"></div>

    <!-- Main content -->
    <main class="flex-1 p-8">
      <div class="bg-blue-100 rounded-lg shadow p-6 max-w-3xl mx-auto">
        <form class="grid grid-cols-2 gap-6">
          <!-- Row 1 -->
          <div>
            <label class="block text-sm font-medium">ชื่อจริง <span class="text-red-500">*</span></label>
            <input type="text" class="w-full mt-1 border rounded-md px-3 py-2 focus:ring focus:ring-blue-300" placeholder="กรอกชื่อจริง">
          </div>
          <div>
            <label class="block text-sm font-medium">นามสกุล <span class="text-red-500">*</span></label>
            <input type="text" class="w-full mt-1 border rounded-md px-3 py-2" placeholder="กรอกนามสกุล">
          </div>

          <!-- Row 2 -->
          <div>
            <label class="block text-sm font-medium">เบอร์โทรศัพท์ <span class="text-red-500">*</span></label>
            <input type="text" class="w-full mt-1 border rounded-md px-3 py-2" placeholder="กรอกเบอร์โทรศัพท์">
          </div>
          <div>
            <label class="block text-sm font-medium">ที่อยู่ <span class="text-red-500">*</span></label>
            <input type="text" class="w-full mt-1 border rounded-md px-3 py-2" placeholder="กรอกที่อยู่">
          </div>

          <!-- Row 3 -->
          <div>
            <label class="block text-sm font-medium">อีเมล <span class="text-red-500">*</span></label>
            <input type="email" class="w-full mt-1 border rounded-md px-3 py-2" placeholder="กรอกอีเมล">
          </div>
          <div>
            <label class="block text-sm font-medium">ตำแหน่ง</label>
            <select class="w-full mt-1 border rounded-md px-3 py-2">
              <option>อาจารย์</option>
              <option>เจ้าหน้าที่</option>
            </select>
          </div>

          <!-- Row 4 -->
          <div>
            <label class="block text-sm font-medium">บัญชีผู้ใช้ <span class="text-red-500">*</span></label>
            <input type="text" class="w-full mt-1 border rounded-md px-3 py-2" placeholder="กรอกชื่อบัญชีผู้ใช้">
          </div>
          <div></div>

          <!-- Row 5 -->
          <div>
            <label class="block text-sm font-medium">รหัสผ่าน <span class="text-red-500">*</span></label>
            <input type="password" class="w-full mt-1 border rounded-md px-3 py-2" placeholder="กรอกรหัสผ่าน">
          </div>
          <div>
            <label class="block text-sm font-medium">ยืนยันรหัสผ่าน <span class="text-red-500">*</span></label>
            <input type="password" class="w-full mt-1 border rounded-md px-3 py-2" placeholder="ยืนยันรหัสผ่าน">
          </div>
        </form>

        <!-- Button -->
        <div class="flex justify-center gap-4 pt-4">
          <a href="index.php" class="bg-gray-300 px-6 py-2 rounded-lg hover:bg-gray-400">ยกเลิก</a>
          <button type="submit" class="bg-blue-500 text-white px-6 py-2 rounded-lg hover:bg-blue-600">เพิ่มข้อมูล</button>
        </div>
      </div>
    </main>
  </div>

  <!-- JS -->
  <script>
    const menuBtn = document.getElementById("menuBtn");
    const sidebar = document.getElementById("sidebar");
    const overlay = document.getElementById("overlay");

    // toggle เปิด/ปิด
    menuBtn.addEventListener("click", () => {
      sidebar.classList.toggle("-translate-x-full");
      overlay.classList.toggle("hidden");
    });

    overlay.addEventListener("click", () => {
      sidebar.classList.add("-translate-x-full");
      overlay.classList.add("hidden");
    });
  </script>

</body>
</html>