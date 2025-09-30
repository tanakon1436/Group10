<?php
// เชื่อมฐานข้อมูล
$conn = new mysqli("localhost","root","","group10");
if($conn->connect_error) die("Connection failed: ".$conn->connect_error);

// *** เพิ่ม: Array สำหรับแปลงภาษาอังกฤษเป็นภาษาไทย ***
$type_translation = [
    'Journal' => 'บทความวารสาร',
    'Conference' => 'นำเสนอในงานประชุม',
    'Thesis' => 'วิทยานิพนธ์'
    // หากมีประเภทอื่นๆ ใน DB เพิ่มเติม สามารถเพิ่มตรงนี้ได้
];

// กำหนดพารามิเตอร์การค้นหาและการกรองปี
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$selected_year = isset($_GET['year']) ? (int)$_GET['year'] : 0;
// Note: $selected_type จะยังคงเป็นค่าภาษาอังกฤษ (Journal, Conference, Thesis)
$selected_type = isset($_GET['type']) ? trim($_GET['type']) : ''; 
$like_search = "%" . $search_query . "%";

// ดึงปีที่มีงานตีพิมพ์ที่ approved ทั้งหมดสำหรับ dropdown
$years_sql = "SELECT DISTINCT publish_year FROM Publication WHERE status = 'approved' ORDER BY publish_year DESC";
$years_result = $conn->query($years_sql);
$available_years = [];
if ($years_result) {
    while ($row = $years_result->fetch_assoc()) {
        $available_years[] = (int)$row['publish_year'];
    }
}

// ดึงประเภทงานตีพิมพ์ที่มีงานที่ approved ทั้งหมดสำหรับ dropdown
$types_sql = "SELECT DISTINCT type FROM Publication WHERE status = 'approved' AND type IS NOT NULL AND type != '' ORDER BY type ASC";
$types_result = $conn->query($types_sql);
$available_types = [];
if ($types_result) {
    while ($row = $types_result->fetch_assoc()) {
        $available_types[] = $row['type'];
    }
}

// *** Function เพื่อแปลงค่า type จากอังกฤษเป็นไทย (ใช้ในการแสดงผล) ***
function get_thai_type($english_type, $translation_map) {
    return isset($translation_map[$english_type]) ? $translation_map[$english_type] : $english_type;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>หน้าหลัก | ระบบค้นหาผลงานตีพิมพ์</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
   
    <style>
        /* กำหนดสีหลักของธีม */
        .text-theme { color: #1d4ed8; } /* blue-700 */
        .bg-theme-light { background-color: #eff6ff; } /* blue-50 */
        .hover-bg-theme { background-color: #dbeafe; } /* blue-100 */

        .psu-logo { height: 100px; object-fit: contain; }
    </style>
</head>
<body class="flex min-h-screen font-sans bg-gray-100">

<aside class="w-64 bg-white shadow-lg p-6 flex flex-col sticky top-0 h-screen">
    <div class="flex flex-col items-center border-b pb-4 mb-4">
        <img src="img/img_psu.png" onerror="this.onerror=null;this.src='https://placehold.co/100x100/eeeeee/333333?text=PSU';" alt="PSU Logo" class="psu-logo">
        <span class="text-xs font-semibold text-gray-600">ระบบค้นหาและจัดการผลงานตีพิมพ์</span>
    </div>

    <nav class="w-full flex-grow">
        <a href="HomeallPage-v1.php" class="flex items-center p-3 rounded-lg mb-3 text-theme bg-blue-100 hover:bg-blue-200 hover:text-blue-900 font-semibold transition-colors duration-150">
            <i class="fas fa-home w-5 h-5 mr-3"></i> หน้าหลัก
        </a>
        <a href="usermannual.php" class="flex items-center p-3 rounded-lg text-gray-700 hover:bg-blue-100 hover:text-blue-700 transition-colors duration-150">
            <i class="fas fa-book w-5 h-5 mr-3"></i> คู่มือการใช้งาน
        </a>
    </nav>
   
    <div class="px-0 pt-4 border-t border-gray-200">
      <a href="login-v1.php" class="flex items-center p-3 rounded-lg text-blue-500 hover:bg-blue-50 transition-colors duration-150">
        <i class="fas fa-sign-in-alt w-5 h-5 mr-3"></i> เข้าสู่ระบบ (เจ้าหน้าที่)
      </a>
    </div>
</aside>

<main class="flex-1 p-8">
    <header class="flex flex-col md:flex-row items-center justify-between mb-8 pb-4 border-b border-gray-300">
        <h1 class="text-3xl font-bold text-gray-800 mb-4 md:mb-0">ระบบค้นหาผลงานตีพิมพ์</h1>
       
        <form method="GET" action="HomeallPage-v1.php" class="w-full md:w-auto">
            <div class="relative">
                <input type="text" name="search" id="simple-search" placeholder="ค้นหางานตีพิมพ์ หรือชื่อผู้จัดทำ..."
                       value="<?= htmlspecialchars($search_query); ?>"
                       class="border rounded-full p-2 pl-10 pr-4 w-full md:w-96 focus:ring-blue-500 focus:border-blue-500 transition duration-150">
                <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
            </div>
            <?php if ($selected_year > 0): ?>
                <input type="hidden" name="year" value="<?= $selected_year; ?>">
            <?php endif; ?>
            <?php if (!empty($selected_type)): ?>
                <input type="hidden" name="type" value="<?= htmlspecialchars($selected_type); ?>">
            <?php endif; ?>
            <button type="submit" class="hidden">Search</button>
        </form>
    </header>

    <section class="bg-white p-6 rounded-xl shadow-xl mb-8">
        <h2 class="text-2xl font-bold text-blue-700 mb-6 border-b pb-2">รายชื่ออาจารย์</h2>
       
        <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 xl:grid-cols-8 gap-6">
        <?php
        // ------------------------------------------------------------------
        // NEW LOGIC: ดึงข้อมูลผู้ใช้ (เฉพาะอาจารย์) โดยกรองด้วยคำค้นหา
        // ------------------------------------------------------------------
        $user_search_condition = "";
        $user_bind_params = [];
        $user_bind_types = "";
       
        if (!empty($search_query)) {
            // กรองด้วยชื่ออาจารย์: (first_name LIKE %query% OR last_name LIKE %query% OR CONCAT(first_name,' ',last_name) LIKE %query%)
            $user_search_condition = " AND (first_name LIKE ? OR last_name LIKE ? OR CONCAT(first_name,' ',last_name) LIKE ?)";
            $user_bind_params = [$like_search, $like_search, $like_search];
            $user_bind_types = "sss";
        }

        $users_sql = "SELECT User_id, first_name, last_name, avatar
                      FROM User
                      WHERE role = 'normal'
                      ".$user_search_condition."
                      ORDER BY first_name LIMIT 16";
       
        $stmt_users = $conn->prepare($users_sql);
       
        if ($stmt_users) {
            if (!empty($user_bind_params)) {
                $stmt_users->bind_param($user_bind_types, ...$user_bind_params);
            }
            $stmt_users->execute();
            $users = $stmt_users->get_result();

            if ($users->num_rows > 0) {
                while($u = $users->fetch_assoc()){
                    $avatar_filename = htmlspecialchars($u['avatar']);
                    $avatar_path = !empty($avatar_filename) ? "img/" . $avatar_filename : 'img/default-avatar.png';
                    $full_name = htmlspecialchars($u['first_name'].' '.$u['last_name']);
   
                    echo '<a href="G1.php?user_id='.$u['User_id'].'" class="flex flex-col items-center text-center group hover:bg-gray-50 p-2 rounded-lg transition duration-150">
                            <div class="w-20 h-20 bg-gray-200 rounded-full flex items-center justify-center mb-2 overflow-hidden border-4 border-gray-300 group-hover:border-blue-500 transition-colors duration-150">
                                <img src="'.$avatar_path.'" onerror="this.onerror=null;this.src=\'img/default-avatar.png\';" alt="Avatar" class="w-full h-full object-cover">
                            </div>
                            <span class="text-sm font-medium text-gray-700 group-hover:text-blue-700 line-clamp-2">'.$full_name.'</span>
                          </a>';
                }
            } else {
                echo '<p class="col-span-full text-center text-gray-500 py-4">ไม่พบรายชื่ออาจารย์ที่ตรงกับคำค้นหา "'.htmlspecialchars($search_query).'"</p>';
            }
            $stmt_users->close();
        } else {
             echo '<p class="col-span-full text-red-500 text-center py-4">เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL สำหรับอาจารย์</p>';
        }
       
        ?>
        </div>
    </section>

---

    <section class="bg-white p-6 rounded-xl shadow-xl">
       
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 border-b pb-2">
            <h2 class="text-2xl font-bold text-blue-700 mb-3 sm:mb-0">งานตีพิมพ์ทั้งหมด</h2>
           
            <form method="GET" action="HomeallPage-v1.php" class="flex flex-col space-y-2 sm:space-y-0 sm:flex-row sm:space-x-4 w-full sm:w-auto">
                
                <?php if (!empty($search_query)): ?>
                    <input type="hidden" name="search" value="<?= htmlspecialchars($search_query); ?>">
                <?php endif; ?>
               
                <div class="flex items-center space-x-2">
                    <label for="year-filter" class="text-sm font-medium text-gray-700">กรองตามปี:</label>
                    <select name="year" id="year-filter" onchange="this.form.submit()"
                            class="border rounded-lg p-2 text-sm focus:ring-blue-500 focus:border-blue-500 transition duration-150">
                        <option value="0">--- ทุกปี ---</option>
                        <?php foreach ($available_years as $year): ?>
                            <option value="<?= $year; ?>" <?= ($selected_year == $year) ? 'selected' : ''; ?>>
                                <?= $year; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="flex items-center space-x-2">
                    <label for="type-filter" class="text-sm font-medium text-gray-700">กรองตามประเภท:</label>
                    <select name="type" id="type-filter" onchange="this.form.submit()"
                            class="border rounded-lg p-2 text-sm focus:ring-blue-500 focus:border-blue-500 transition duration-150">
                        <option value="">--- ทุกประเภท ---</option>
                        <?php foreach ($available_types as $type): 
                            // *** ใช้ get_thai_type เพื่อแสดงชื่อประเภทเป็นภาษาไทย ***
                            $thai_type = get_thai_type($type, $type_translation);
                        ?>
                            <option value="<?= htmlspecialchars($type); ?>" <?= ($selected_type === $type) ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($thai_type); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>


        <?php
        // ----------------------------------------------------
        // 1. สร้างเงื่อนไขสำหรับ SQL (Publication)
        // ----------------------------------------------------
        $year_condition = "";
        if ($selected_year > 0) {
            $year_condition = " AND p.publish_year = ?";
        }
       
        $type_condition = "";
        if (!empty($selected_type)) {
            $type_condition = " AND p.type = ?";
        }
       
        // เงื่อนไขการค้นหา: title LIKE %?% หรือ author LIKE %?%
        $pub_search_condition = "";
        if (!empty($search_query)) {
            $pub_search_condition = " AND (p.title LIKE ? OR CONCAT(u.first_name,' ',u.last_name) LIKE ?)";
        }
       
        // ----------------------------------------------------
        // 2. สร้าง SQL Query (Publication)
        // ----------------------------------------------------
        $pubs_sql = "
            SELECT p.Pub_id, p.title, p.publish_year, p.file_path, CONCAT(u.first_name,' ',u.last_name) AS author, p.type
            FROM Publication p
            JOIN User u ON p.Author_id = u.User_id
            WHERE p.status = 'approved'
            ".$year_condition."
            ".$type_condition."
            ".$pub_search_condition."
            ORDER BY p.publish_year DESC, p.Pub_id DESC
            LIMIT 20
        ";

        $stmt_pubs = $conn->prepare($pubs_sql);
       
        if ($stmt_pubs) {
            // ----------------------------------------------------
            // 3. จัดการการผูกพารามิเตอร์ (Binding Parameters) (Publication)
            // ----------------------------------------------------
            $bind_types = "";
            $bind_params = [];
           
            // 1. Year
            if ($selected_year > 0) {
                $bind_types .= "i";
                $bind_params[] = $selected_year;
            }
           
            // 2. Type
            if (!empty($selected_type)) {
                $bind_types .= "s";
                $bind_params[] = $selected_type;
            }
           
            // 3. Search Query (Title AND Author)
            if (!empty($search_query)) {
                $bind_types .= "ss"; // สองตัวสำหรับ title และ author
                $bind_params[] = $like_search;
                $bind_params[] = $like_search;
            }

            if (!empty($bind_params)) {
                // ต้องใช้วิธี dynamic binding เนื่องจากจำนวนพารามิเตอร์ไม่คงที่
                $stmt_pubs->bind_param($bind_types, ...$bind_params);
            }
           
            // ----------------------------------------------------
            // 4. Execute และแสดงผล (Publication)
            // ----------------------------------------------------
            $stmt_pubs->execute();
            $pubs = $stmt_pubs->get_result();

            if ($pubs->num_rows > 0):
                $count = 0;
                while($p = $pubs->fetch_assoc()):
                $count++;
        ?>
            <div class="publication-item bg-gray-50 p-4 rounded-lg shadow-sm mb-3 border border-gray-200 transition duration-200 hover:shadow-md"
                 data-pub-id="<?= $p['Pub_id']; ?>">
                <div class="flex items-start justify-between">
                    <div class="flex-1 min-w-0 pr-4">
                        <span class="text-sm text-blue-600 font-semibold mr-2"><?= $count; ?>.</span>
                        <span class="text-lg font-bold text-gray-800 leading-snug block md:inline"><?php echo htmlspecialchars($p['title']); ?></span>
                        <p class="text-sm text-gray-500 mt-1 md:mt-0 md:inline block"> (ปี <?php echo $p['publish_year']; ?>)</p>
                        <p class="text-sm text-gray-600 mt-1">ผู้จัดทำ: <?php echo htmlspecialchars($p['author']); ?></p>
                        <span class="text-xs font-medium bg-indigo-100 text-indigo-700 px-2 py-1 rounded-full mt-1 inline-block">
                            <?php echo htmlspecialchars(get_thai_type($p['type'], $type_translation)); ?>
                        </span>
                    </div>
                    <button class="toggle-details text-gray-500 hover:text-blue-700 transition-colors duration-200 p-2 rounded-full flex-shrink-0"
                            data-pub-id="<?= $p['Pub_id']; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 transition-transform duration-300" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
                        </svg>
                    </button>
                </div>
               
                <div id="details-<?= $p['Pub_id']; ?>" class="details mt-4 text-gray-700 space-y-3 hidden border-t border-gray-300 pt-4">
                    <p class="text-base font-medium text-gray-900">รายละเอียดเพิ่มเติม:</p>
                    <p class="text-sm">รหัสสิ่งพิมพ์: **<?php echo $p['Pub_id']; ?>**</p>

                    <?php if(!empty($p['file_path'])): ?>
                        <a href="<?php echo htmlspecialchars($p['file_path']); ?>" target="_blank"
                           class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-full shadow-md
                                  hover:bg-green-700 transition-colors duration-200 text-sm font-medium">
                            <i class="fas fa-file-pdf mr-2"></i> ดูเอกสาร (PDF Viewer)
                        </a>
                    <?php else: ?>
                        <p class="text-red-500 text-sm"><i class="fas fa-exclamation-triangle mr-2"></i> ไม่พบไฟล์เอกสาร</p>
                    <?php endif; ?>
                </div>
            </div>
        <?php
                endwhile;
            else:
                $filter_info = "";
                if ($selected_year > 0) $filter_info .= " (ปี: ".$selected_year.")";
                // ใช้การแปลงภาษาไทยสำหรับแสดงผล
                if (!empty($selected_type)) $filter_info .= " (ประเภท: ".htmlspecialchars(get_thai_type($selected_type, $type_translation)).")";
                if (!empty($search_query)) $filter_info .= " (คำค้นหา: ".htmlspecialchars($search_query).")";

                echo '<p class="text-center text-gray-500 py-6">ไม่พบงานตีพิมพ์ที่ตรงกับเงื่อนไขการกรอง'.$filter_info.'</p>';
            endif;
            $stmt_pubs->close();
        } else {
             echo '<p class="text-red-500 text-center py-6">เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL</p>';
        }
        ?>
    </section>
</main>

<script>
document.querySelectorAll('.toggle-details').forEach(btn=>{
    btn.addEventListener('click', ()=>{
        const pubId = btn.getAttribute('data-pub-id');
        const details = document.getElementById(`details-${pubId}`);
        const svg = btn.querySelector('svg');
       
        details.classList.toggle('hidden');
        svg.classList.toggle('rotate-180');
    });
});
</script>

</body>
</html>
<?php $conn->close(); ?>