<?php
// manual.php
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>คู่มือการใช้งานระบบ</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-200">
    <!-- Navbar -->
    <div class="bg-blue-200 p-4 flex justify-between items-center">
        <h1 class="text-lg font-bold">คู่มือการใช้งานระบบ</h1>
        <div class="flex items-center space-x-2">
            <span class="material-icons">account_circle</span>
        </div>
    </div>

    <div class="flex">
        <!-- Sidebar -->
        <aside class="w-1/5 bg-blue-100 p-4 min-h-screen">
            <button onclick="history.back()" class="flex items-center mb-4">
                <span class="material-icons">arrow_back</span>
                <span class="ml-2">ย้อนกลับ</span>
            </button>

            <nav>
                <ul>
                    <li class="mb-2">
                        <a href="#" class="flex items-center space-x-2 text-black font-medium hover:text-blue-500">
                            <span class="material-icons">home</span>
                            <span>หน้าหลัก</span>
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="manual.php" class="flex items-center space-x-2 text-black font-medium hover:text-blue-500">
                            <span class="material-icons">menu_book</span>
                            <span>คู่มือ</span>
                        </a>
                    </li>
                </ul>
            </nav>
        </aside>

        <!-- Content -->
        <main class="flex-1 p-6">
            <div class="bg-white p-6 rounded shadow">
                <h2 class="text-xl font-bold text-center mb-6">คู่มือการใช้งานระบบ</h2>

                <div class="space-y-6">
                    <!-- Step 1 -->
                    <div>
                        <p class="font-semibold mb-2">1.</p>
                        <div class="flex items-center space-x-4">
                            <div class="w-40 h-40 bg-gray-300 flex items-center justify-center">ภาพ</div>
                            <div>
                                <p>การใช้งานเบื้องต้น 1</p>
                                <p>การใช้งานเบื้องต้น 2</p>
                            </div>
                        </div>
                    </div>

                    <!-- Step 2 -->
                    <div>
                        <p class="font-semibold mb-2">2.</p>
                        <div class="flex items-center space-x-4">
                            <div class="w-40 h-40 bg-gray-300 flex items-center justify-center">ภาพ</div>
                            <span class="material-icons">arrow_forward</span>
                            <div class="w-40 h-40 bg-gray-300 flex items-center justify-center">ภาพ</div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
