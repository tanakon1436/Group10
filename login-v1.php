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
    Log in
  </header>

  <div class="max-w-md mx-auto mt-12 p-8 bg-[#e6f2ff] rounded-2xl text-center">
    <h2 class="text-xl font-semibold mb-6">ยินดีต้อนรับ</h2>

    <form class="space-y-6">
      <div class="text-left">
        <label for="username" class="font-bold block mb-1">บัญชีผู้ใช้ *</label>
        <input type="text" id="username" placeholder="กรอกชื่อบัญชีผู้ใช้"
               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-400">
      </div>

      <div class="text-left relative">
        <label for="password" class="font-bold block mb-1">รหัสผ่าน *</label>
        <input type="password" id="password" placeholder="กรอกรหัสผ่าน"
               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-400">
        <span class="absolute right-3 top-1/2 -translate-y-1/2 cursor-pointer text-gray-600">
          <i class="fas fa-eye"></i>
        </span>
      </div>

      <div class="flex items-center justify-between text-sm">
        <label class="flex items-center">
          <input type="checkbox" id="remember" checked class="mr-2">
          จดจำรหัสผ่าน
        </label>
        <a href="#" class="text-blue-600 underline">ลืมรหัสผ่าน ?</a>
      </div>

      <button type="submit" 
              class="w-full py-3 bg-white border border-gray-300 rounded-lg font-bold text-lg hover:bg-gray-100">
        เข้าสู่ระบบ
      </button>
    </form>

    <div class="border-t border-gray-400 my-6"></div>

    <p class="text-sm">
      เข้าสู่ระบบแบบ 
      <a href="#" class="text-blue-600 underline font-bold cursor-pointer" title="คลิกเพื่อเข้าสู่ระบบแบบผู้เยี่ยมชม">
        ผู้เยี่ยมชม
      </a>
    </p>
  </div>

</body>
</html>
