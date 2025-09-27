<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ระบบจัดการการตีพิมพ์ผลงานอาจารย์</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-blue-50 font-sans min-h-screen flex flex-col">

  <!-- Navbar -->
  <header class="flex items-center justify-between bg-blue-100 px-4 py-4 shadow-md">
    <!-- ปุ่มเมนูมือถือ -->
    <button id="menu-btn" class="md:hidden text-gray-700 focus:outline-none">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" fill="none"
           viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M4 6h16M4 12h16M4 18h16" />
      </svg>
    </button>

    <!-- ชื่อระบบ -->
    <h1 class="text-xl md:text-2xl font-semibold text-gray-800">
      ระบบจัดการการตีพิมพ์ผลงานอาจารย์
    </h1>

    <!-- User icon -->
    <div class="w-10 h-10 flex items-center justify-center bg-white text-gray-700 font-bold rounded-full shadow">
      👤
    </div>
  </header>

  <div class="flex flex-1 overflow-hidden">

    <!-- Sidebar -->
    <aside id="sidebar" class="bg-white w-64 p-4 border-r hidden md:block">
      <ul class="space-y-2">
        <li>
          <a href="search_work.html"
             class="flex items-center space-x-3 p-2 rounded-lg hover:bg-blue-50 transition">
            🔍 <span class="font-medium text-gray-700">ค้นหาผลงานตีพิมพ์</span>
          </a>
        </li>
        <li>
          <a href="edit_teacher.html"
             class="flex items-center space-x-3 p-2 rounded-lg hover:bg-blue-50 transition">
            ✏️ <span class="font-medium text-gray-700">แก้ไขข้อมูลอาจารย์</span>
          </a>
        </li>
        <li>
          <a href="add_teacher.html"
             class="flex items-center space-x-3 p-2 rounded-lg hover:bg-blue-50 transition">
            ➕ <span class="font-medium text-gray-700">เพิ่มข้อมูลอาจารย์/เจ้าหน้าที่</span>
          </a>
        </li>
        <li>
          <a href="notifications.html"
             class="flex items-center space-x-3 p-2 rounded-lg hover:bg-blue-50 transition">
            🔔 <span class="font-medium text-gray-700">ตั้งการแจ้งเตือน</span>
          </a>
        </li>
        <li>
          <a href="report.html"
             class="flex items-center space-x-3 p-2 rounded-lg hover:bg-blue-50 transition">
            📄 <span class="font-medium text-gray-700">รายงานผล / ดาวน์โหลด PDF</span>
          </a>
        </li>
      </ul>
    </aside>

    <!-- Main content -->
    <main class="flex-1 p-6 overflow-y-auto">
      <!-- ช่องค้นหา -->
      <div class="flex items-center mb-8">
        <div class="flex w-full rounded-lg shadow bg-white overflow-hidden">
          <input type="text" id="search" placeholder="🔎 พิมพ์เพื่อค้นหา..."
                 class="w-full px-5 py-3 focus:outline-none text-gray-700">
          <button id="search-btn"
                  class="bg-blue-200 px-5 text-gray-800 font-medium hover:bg-blue-300 transition">
            ค้นหา
          </button>
        </div>
      </div>

      <!-- กล่องข้อมูล -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- ชื่อผลงาน -->
        <div class="bg-white p-6 rounded-lg shadow hover:shadow-md transition">
          <h2 class="font-bold mb-4 text-gray-800 text-lg border-b pb-2">📑 ชื่อผลงาน</h2>
          <ul class="text-sm text-gray-700 space-y-2" id="work-list">
            <li><a href="work_detail.html?id=1" class="hover:text-blue-600">การวิจัยเพื่อวัดผลต่อความสำเร็จ...</a></li>
            <li><a href="work_detail.html?id=2" class="hover:text-blue-600">การบูรณาการโครงการกับ IT (IP Camera)</a></li>
            <li><a href="work_detail.html?id=3" class="hover:text-blue-600">โปรแกรมวิเคราะห์ประสิทธิภาพพบ...</a></li>
            <li><a href="work_detail.html?id=4" class="hover:text-blue-600">Complete research report impact...</a></li>
          </ul>
        </div>

        <!-- ชื่ออาจารย์ -->
        <div class="bg-white p-6 rounded-lg shadow hover:shadow-md transition">
          <h2 class="font-bold mb-4 text-gray-800 text-lg border-b pb-2">👩‍🏫 ชื่ออาจารย์</h2>
          <ul class="text-sm text-gray-700 space-y-2" id="teacher-list">
            <li><a href="teacher_detail.html?id=1" class="hover:text-blue-600">จรวย สายบัว</a></li>
            <li><a href="teacher_detail.html?id=2" class="hover:text-blue-600">สุทธรรมณ์ กานต์ศิลป์</a></li>
            <li><a href="teacher_detail.html?id=3" class="hover:text-blue-600">มณฑา เหล่าทิม</a></li>
            <li><a href="teacher_detail.html?id=4" class="hover:text-blue-600">ธานินท์ สมฤดี</a></li>
          </ul>
        </div>

        <!-- ปีที่พิมพ์ -->
        <div class="bg-white p-6 rounded-lg shadow hover:shadow-md transition">
          <h2 class="font-bold mb-4 text-gray-800 text-lg border-b pb-2">📆 ปีที่พิมพ์</h2>
          <ul class="text-sm text-gray-700 space-y-2">
            <li class="flex justify-between"><a href="year_detail.html?year=2500-2567" class="hover:text-blue-600">2500-2567</a> <span class="font-semibold">12748</span></li>
            <li class="flex justify-between"><a href="year_detail.html?year=2000-2099" class="hover:text-blue-600">2000-2099</a> <span class="font-semibold">3727</span></li>
            <li class="flex justify-between"><a href="year_detail.html?year=1953-1999" class="hover:text-blue-600">1953-1999</a> <span class="font-semibold">141</span></li>
          </ul>
        </div>
      </div>
    </main>
  </div>

  <!-- Script ค้นหา + Toggle Sidebar -->
  <script>
    const searchInput = document.getElementById("search");
    const searchBtn   = document.getElementById("search-btn");
    const workList    = document.getElementById("work-list").getElementsByTagName("li");
    const teacherList = document.getElementById("teacher-list").getElementsByTagName("li");
    const menuBtn     = document.getElementById("menu-btn");
    const sidebar     = document.getElementById("sidebar");

    function searchItems() {
      const query = searchInput.value.toLowerCase();
      for (let item of workList)
        item.style.display = item.textContent.toLowerCase().includes(query) ? "" : "none";
      for (let item of teacherList)
        item.style.display = item.textContent.toLowerCase().includes(query) ? "" : "none";
    }

    searchBtn.addEventListener("click", searchItems);
    searchInput.addEventListener("keyup", searchItems);

    menuBtn.addEventListener("click", () => sidebar.classList.toggle("hidden"));
  </script>

</body>
</html>
