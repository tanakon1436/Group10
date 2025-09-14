<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title>ระบบจัดการการตีพิมพ์ผลงานของอาจารย์</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-blue-50 font-sans">

  <!-- 🔹 Sidebar (ซ่อน) -->
  <div id="sidebar" class="fixed top-0 left-0 h-full w-64 bg-white shadow transform -translate-x-full transition-transform duration-300 z-50">
    <div class="p-4 border-b">
      <h2 class="text-lg font-semibold">เมนู</h2>
    </div>
    <nav class="flex flex-col p-4 space-y-2">
      <a href="#" class="px-3 py-2 rounded hover:bg-blue-100">หน้าหลัก</a>
      <a href="#" class="px-3 py-2 rounded hover:bg-blue-100">ประวัติการแก้ไข</a>
      <a href="#" class="px-3 py-2 rounded hover:bg-blue-100">คู่มือการใช้งาน</a>
      <a href="#" class="px-3 py-2 rounded hover:bg-blue-100">ช่องทางติดต่อ</a>
    </nav>
  </div>


  <!-- 🔹 Navbar -->
  <header class="flex items-center justify-between bg-blue-100 px-4 py-2 shadow">
    <!-- Hamburger Menu -->
    <button id="menu-btn" class="text-gray-700">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none"
           viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M4 6h16M4 12h16M4 18h16" />
      </svg>
    </button>

    <!-- Title -->
    <h1 class="text-center text-lg font-medium text-black">
      ระบบจัดการการตีพิมพ์ผลงานของอาจารย์
    </h1>

    <!-- Right icons -->
    <div class="flex items-center gap-4">
      <!-- Notification Bell -->
      <button class="text-gray-700">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none"
             viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 
                   0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 
                   .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 
                   11-6 0v-1m6 0H9" />
        </svg>
      </button>

    <!-- User icon -->
    <div class="w-8 h-8 flex items-center justify-center text-xl">
      👤
    </div>
    </div>
  </header>

  <!-- 🔹 Form Content -->
  <main class="p-6">
    <div class="max-w-3xl mx-auto bg-blue-100 p-6 rounded-lg shadow">
      <!-- Back Button + Title -->
      <div class="flex items-center mb-4">
        <button class="mr-2 text-gray-700">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none"
               viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M15 19l-7-7 7-7" />
          </svg>
        </button>
        <h2 class="text-xl font-semibold flex-grow text-center">เพิ่มผลงานตีพิมพ์</h2>
      </div>

      <!-- Form -->
      <form action="#" method="POST" class="space-y-4">
        <!-- ชื่อผลงาน -->
        <div>
          <label class="block font-medium mb-1">
            ชื่อผลงาน <span class="text-red-500">*</span>
          </label>
          <input type="text" name="title" required
                 class="w-full border rounded px-3 py-2 focus:outline-none focus:ring focus:ring-blue-300">
        </div>

        <!-- ชื่อผู้ตีพิมพ์ผลงาน -->
        <div>
          <label class="block font-medium mb-1">
            ชื่อผู้ตีพิมพ์ผลงาน <span class="text-red-500">*</span>
          </label>
          <input type="text" name="author" required
                 class="w-full border rounded px-3 py-2 focus:outline-none focus:ring focus:ring-blue-300">
        </div>

        <!-- ชื่อผู้ร่วมตีพิมพ์ -->
        <div>
          <label class="block font-medium mb-1">ชื่อผู้ร่วมตีพิมพ์ผลงาน</label>
          <input type="text" name="coauthor"
                 class="w-full border rounded px-3 py-2 focus:outline-none focus:ring focus:ring-blue-300">
        </div>

        <!-- รายละเอียด -->
        <div>
          <label class="block font-medium mb-1">
            รายละเอียด <span class="text-red-500">*</span>
          </label>
          <textarea name="details" required rows="4"
                    class="w-full border rounded px-3 py-2 focus:outline-none focus:ring focus:ring-blue-300"></textarea>
        </div>

        <!-- แนบไฟล์ -->
        <div>
          <label class="block font-medium mb-1">แนบไฟล์ผลงาน</label>
          <input type="file" name="file"
                 class="w-full border rounded px-3 py-2 focus:outline-none focus:ring focus:ring-blue-300">
        </div>

        <!-- ปุ่ม -->
        <div class="flex justify-center gap-3">
          <button type="reset" class="px-6 py-2 bg-gray-300 rounded hover:bg-gray-400">ยกเลิก</button>
          <button type="submit" class="px-6 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">บันทึก</button>
        </div>
      </form>
    </div>
  </main>

  <!-- 🔹 Script สำหรับ Hamburger Menu -->
  <script>
    const menuBtn = document.getElementById('menu-btn');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');

    function openSidebar() {
      sidebar.classList.remove('-translate-x-full');
      overlay.classList.remove('hidden');
    }

    function closeSidebar() {
      sidebar.classList.add('-translate-x-full');
      overlay.classList.add('hidden');
    }

    menuBtn.addEventListener('click', openSidebar);
    overlay.addEventListener('click', closeSidebar);
  </script>

</body>
</html>
