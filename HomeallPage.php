<?php
// เชื่อมฐานข้อมูล
$conn = new mysqli("localhost","root","","group10");
if($conn->connect_error) die("Connection failed: ".$conn->connect_error);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบจัดการสิ่งพิมพ์</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="flex min-h-screen font-sans bg-gray-100">

<!-- Sidebar -->
<aside class="w-64 bg-white shadow-lg p-6 flex flex-col items-center sticky top-0 h-screen">
    <h2 class="text-2xl font-bold mb-6">เมนู</h2>
    <nav class="w-full">
        <a href="#" class="block p-3 rounded-lg mb-3 text-gray-700 hover:bg-blue-100 hover:text-blue-700">หน้าหลัก</a>
        <a href="#" class="block p-3 rounded-lg text-gray-700 hover:bg-blue-100 hover:text-blue-700">คู่มือ</a>
    </nav>
</aside>

<main class="flex-1 p-8">
    <header class="flex items-center justify-between mb-8">
        <h1 class="text-4xl font-extrabold text-gray-800">ระบบจัดการการตีพิมพ์</h1>
        <input type="text" id="simple-search" placeholder="ค้นหา..." class="border rounded-full p-2 px-4">
    </header>

    <!-- รายชื่อผู้ใช้ -->
<section class="bg-white p-6 rounded-lg shadow-lg mb-6">
    <h2 class="text-2xl font-semibold mb-4">รายชื่อผู้ใช้</h2>
    <div class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-6 gap-6">
    <?php
// ดึงข้อมูลผู้ใช้พร้อม path รูป
$users = $conn->query("SELECT User_id, first_name, last_name, avatar FROM User");
while($u = $users->fetch_assoc()){
    // กรณีไม่มีรูป ใช้รูป default
    $img = $u['avatar'] ? 'img/'.$u['avatar'] : 'img/default-avatar.png';

    echo '<div class="flex flex-col items-center text-center">
            <div class="w-16 h-16 bg-gray-200 rounded-full flex items-center justify-center mb-2 overflow-hidden">
                <img src="'.htmlspecialchars($img).'" alt="Avatar" style="max-width: 100px; height: auto;">
            </div>
            <span class="text-sm font-medium text-gray-600">'.htmlspecialchars($u['first_name'].' '.$u['last_name']).'</span>
          </div>';
}
?>

    </div>
</section>


    <!-- งานตีพิมพ์ -->
    <section class="bg-white p-6 rounded-lg shadow-lg">
        <h2 class="text-2xl font-semibold mb-4">งานตีพิมพ์</h2>

        <?php
        $pubs = $conn->query("
            SELECT p.Pub_id, p.title, p.publish_year, p.file_path, CONCAT(u.first_name,' ',u.last_name) AS author
            FROM Publication p
            JOIN User u ON p.Author_id = u.User_id
            ORDER BY p.publish_year DESC
        ");
        while($p = $pubs->fetch_assoc()):
        ?>
            <div class="bg-gray-100 p-4 rounded-lg shadow-sm mb-4">
                <div class="flex items-center justify-between">
                    <div>
                        <span class="font-bold"><?php echo $p['Pub_id']; ?></span>
                        <span class="text-lg font-medium"><?php echo htmlspecialchars($p['title']); ?></span>
                    </div>
                    <button class="toggle-details text-gray-500 hover:text-gray-700 transition-colors duration-200">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 transition-transform duration-300" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
                        </svg>
                    </button>
                </div>
                <div class="details mt-4 text-gray-600 space-y-2 hidden">
                    <p class="text-sm font-medium">ผู้จัดทำ: <?php echo htmlspecialchars($p['author']); ?> ปี <?php echo $p['publish_year']; ?></p>
                    <?php if($p['file_path']): ?>
                        <a href="<?php echo $p['file_path']; ?>" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-full hover:bg-blue-700 transition-colors duration-200 mt-2">ดาวน์โหลด</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endwhile; ?>

    </section>
</main>

<script>
document.querySelectorAll('.toggle-details').forEach(btn=>{
    btn.addEventListener('click', ()=>{
        const details = btn.closest('div').querySelector('.details');
        details.classList.toggle('hidden');
        btn.querySelector('svg').classList.toggle('rotate-180');
    });
});
</script>

</body>
</html>
