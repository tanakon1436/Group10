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
<body class="bg-gray-100">

    <!-- Header -->
    <header class="bg-blue-100 relative flex justify-between items-center px-4 py-3 shadow">
        <h1 class="absolute left-1/2 transform -translate-x-1/2 text-lg font-semibold">
            คู่มือการใช้งานระบบ
        </h1>
        <div class="flex items-center space-x-3">
            <button class="text-xl">👤</button>
        </div>
    </header>

    <div class="flex">
        <!-- Sidebar -->
        <aside class="bg-white w-56 min-h-screen shadow-md flex flex-col justify-between">
            <div>

                <nav class="mt-2 flex flex-col">
                    <a href="HomeallPage-v1.php" class="block p-3 rounded-lg text-gray-700 hover:bg-blue-100 hover:text-blue-700">
                        <span class="text-xl mr-3">🏠</span> หน้าหลัก
                    </a>
                    <a href="manual.php" class="block p-3 rounded-lg mb-3 text-gray-700 bg-blue-100 text-blue-700 font-medium">
                        <span class="text-xl mr-3">📖</span> คู่มือ
                    </a>
                </nav>
            </div>

            <div class="px-4 py-4 border-t">
                <a href="logout.php" class="flex items-center text-red-500 hover:underline">
                    <span class="text-xl mr-3">⏻</span> ออกจากระบบ
                </a>
            </div>
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
