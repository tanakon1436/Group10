<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ระบบจัดการการตีพิมพ์ผลงานอาจารย์</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-blue-50 font-sans h-screen flex flex-col">

  <!-- Navbar -->
  <header class="flex items-center justify-between bg-blue-100 px-4 py-2 shadow">
    <!-- ปุ่มเมนู -->
    <button id="menu-btn" class="text-gray-700 focus:outline-none">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none"
        viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M4 6h16M4 12h16M4 18h16" />
      </svg>
    </button>

    <!-- ชื่อระบบ -->
    <h1 class="text-lg font-semibold text-gray-800">
      ระบบ จัดการการตีพิมพ์ผลงานของอาจารย์
    </h1>
<!-- User icon -->
    <div class="w-8 h-8 flex items-center justify-center text-xl">
      👤
    </div>
  </header>

  <!-- ส่วนเนื้อหา -->
  <div class="flex flex-1 overflow-hidden">
    <!-- Sidebar (โชว์ตลอด) -->
    <aside id="sidebar" class="bg-white w-64 p-4 border-r">
      <ul class="space-y-4">
        <li class="flex items-center space-x-2 hover:bg-gray-100 p-2 rounded cursor-pointer">

          <span>ค้นหาผลงานตีพิมพ์</span>
        </li>
        <li class="flex items-center space-x-2 hover:bg-gray-100 p-2 rounded cursor-pointer">
          <span>แก้ไขข้อมูลอาจารย์</span>
        </li>
        <li class="flex items-center space-x-2 hover:bg-gray-100 p-2 rounded cursor-pointer">
          
          <span>เพิ่มข้อมูลอาจารย์/เจ้าหน้าที่</span>
        </li>
        <li class="flex items-center space-x-2 hover:bg-gray-100 p-2 rounded cursor-pointer">
          
          <span>ตั้งการแจ้งเตือน</span>
        </li>
        <li class="flex items-center space-x-2 hover:bg-gray-100 p-2 rounded cursor-pointer">
          
          <span>รายงานผล / ดาวน์โหลด PDF</span>
        </li>
      </ul>
    </aside>

    <!-- Main content -->
    <main class="flex-1 p-6 overflow-y-auto">
      <!-- ช่องค้นหา -->
      <div class="flex items-center space-x-2 mb-6">
        <input type="text" id="search" placeholder="ค้นหา"
          class="w-full px-4 py-2 border rounded shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        <button id="search-btn" class="p-2 bg-gray-200 rounded hover:bg-gray-300">
          🔍
        </button>
      </div>

      <!-- กล่องข้อมูล -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <!-- ชื่อผลงาน -->
        <div class="bg-white p-4 rounded shadow">
          <h2 class="font-bold mb-2">ชื่อผลงาน</h2>
          <ul class="text-sm text-gray-700 space-y-1" id="work-list">
            <li>การวิจัยเพื่อวัดผลต่อความสำเร็จ...</li>
            <li>การบูรณาการโครงการกับ IT (IP Camera)</li>
            <li>โปรแกรมวิเคราะห์ประสิทธิภาพพบ...</li>
            <li>Complete research report impact...</li>
          </ul>
        </div>

        <!-- ชื่ออาจารย์ -->
        <div class="bg-white p-4 rounded shadow">
          <h2 class="font-bold mb-2">ชื่ออาจารย์</h2>
          <ul class="text-sm text-gray-700 space-y-1" id="teacher-list">
            <li>จรวย สายบัว</li>
            <li>สุทธรรมณ์ กานต์ศิลป์</li>
            <li>มณฑา เหล่าทิม</li>
            <li>ธานินท์ สมฤดี</li>
          </ul>
        </div>

        <!-- ปีที่พิมพ์ -->
        <div class="bg-white p-4 rounded shadow">
          <h2 class="font-bold mb-2">ปีที่พิมพ์</h2>
          <ul class="text-sm text-gray-700 space-y-1">
            <li>2500-2567 <span class="float-right">12748</span></li>
            <li>2000-2099 <span class="float-right">3727</span></li>
            <li>1953-1999 <span class="float-right">141</span></li>
          </ul>
        </div>
      </div>
    </main>
  </div>

  <!-- Script ค้นหา -->
  <script>
    const searchInput = document.getElementById("search");
    const searchBtn = document.getElementById("search-btn");
    const workList = document.getElementById("work-list").getElementsByTagName("li");
    const teacherList = document.getElementById("teacher-list").getElementsByTagName("li");

    function searchItems() {
      const query = searchInput.value.toLowerCase();

      // ค้นหาในชื่อผลงาน
      for (let item of workList) {
        item.style.display = item.textContent.toLowerCase().includes(query) ? "" : "none";
      }

      // ค้นหาในชื่ออาจารย์
      for (let item of teacherList) {
        item.style.display = item.textContent.toLowerCase().includes(query) ? "" : "none";
      }
    }

    searchBtn.addEventListener("click", searchItems);
    searchInput.addEventListener("keyup", searchItems);
  </script>
</body>
</html>
