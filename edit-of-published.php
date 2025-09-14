<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title>แก้ไขผลงานตีพิมพ์</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-white font-sans">

  <!-- Header -->
  <header class="flex items-center justify-between bg-blue-100 p-4 shadow">
    <div class="flex items-center gap-3">
      <!-- ปุ่มสามขีด (อยู่ตลอด) -->
      <button id="menuBtn" class="text-2xl">&#9776;</button>
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

  <!-- ======================= หน้า แก้ไขผลงาน ======================= -->
  <main class="p-6">
    <!-- หัวข้ออยู่ตรงกลาง -->
    <h2 class="text-xl font-semibold text-center mb-6">แก้ไขผลงานตีพิมพ์</h2>

    <form id="editForm" class="bg-blue-100 p-4 rounded-xl space-y-4">
      <!-- ชื่อผลงาน -->
      <div>
        <label class="block mb-1 font-medium">ชื่อผลงาน</label>
        <div class="flex gap-2">
          <input type="text" class="flex-1 p-2 rounded border border-gray-300"
            value="โปรแกรมการวิเคราะห์คำและนับจำนวนเซลล์">
          <!-- ปุ่มแนบไฟล์ -->
          <label class="bg-yellow-200 px-3 py-1 rounded border cursor-pointer">
            + แนบไฟล์
            <input type="file" id="fileInput" name="files[]" multiple class="hidden">
          </label>
        </div>
        <div id="fileList" class="text-sm text-gray-600 mt-1"></div>
      </div>

      <!-- ชื่อผู้พิมพ์ผลงาน -->
      <div>
        <label class="block mb-1 font-medium">ชื่อผู้พิมพ์ผลงาน</label>
        <input type="text" class="w-full p-2 rounded border border-gray-300"
          value="ผศ. ดร. พรชัย พฤกษ์ภิรมย์">
      </div>

      <!-- ชื่อผู้ร่วมตีพิมพ์ผลงาน -->
      <div>
        <label class="block mb-1 font-medium">ชื่อผู้ร่วมตีพิมพ์ผลงาน</label>
        <input type="text" class="w-full p-2 rounded border border-gray-300"
          value="รศ. ปณิธิดา บุญชัยพัฒน์">
      </div>

      <!-- รายละเอียดบทความ -->
      <div>
        <label class="block mb-1 font-medium">รายละเอียดบทความ</label>
        <textarea rows="4" class="w-full p-2 rounded border border-gray-300">โปรแกรมการวิเคราะห์คำและนับจำนวนเซลล์นี้ จัดทำขึ้นจากความร่วมมือระหว่างคณะแพทยศาสตร์ และ คณะวิศวกรรมศาสตร์ เมื่อ พ.ศ. 2548 โดยเป็นทีมพยาบาลที่จะต้องผลิตเครื่องมือใช้เอง</textarea>
      </div>

      <!-- ปุ่ม -->
      <div class="flex justify-center gap-4 pt-4">
        <a href="list-of-published.php" class="bg-gray-300 px-6 py-2 rounded-lg">ย้อนกลับ</a>
        <button type="submit" class="bg-blue-500 text-white px-6 py-2 rounded-lg">บันทึก</button>
      </div>
    </form>
  </main>

  <!-- ======================= Script ======================= -->
  <script>
    // Toggle Sidebar
    const menuBtn = document.getElementById("menuBtn");
    const sidebar = document.getElementById("sidebar");

    menuBtn.addEventListener("click", () => {
      sidebar.classList.toggle("-translate-x-full");
    });

    // แสดงชื่อไฟล์ที่เลือก
    document.getElementById('fileInput').addEventListener('change', function() {
      let fileList = document.getElementById('fileList');
      fileList.innerHTML = "";
      for (let file of this.files) {
        fileList.innerHTML += "<div>📄 " + file.name + "</div>";
      }
    });

    // ปุ่มบันทึก
    document.getElementById("editForm").addEventListener("submit", function(e) {
      e.preventDefault();
      if (confirm("คุณต้องการบันทึกการแก้ไขใช่หรือไม่?")) {
        alert("บันทึกเรียบร้อยแล้ว!");
        window.location.href = "list-of-published.php";
      }
    });
  </script>

</body>
</html>
