<?php
session_start();
// 1. เรียก db_connect.php ก่อนเสมอ เพื่อให้ BASE_URL พร้อมใช้งาน
require_once 'includes/db_connect.php';

$page_title = "คลังสูตรอาหาร";
require_once 'includes/header.php';


if (!isset($_SESSION['user_id'])) {
    echo '<script>window.location.href = "login.php";</script>';
    exit();
}

// --- 1. กำหนดค่าสำหรับ Pagination ---
$items_per_page = 12; // กำหนดจำนวนเมนูที่จะแสดงต่อหนึ่งหน้า (ปรับได้ตามต้องการ)
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $items_per_page;

// --- 2. ดึงข้อมูลสำหรับสร้างตัวเลือกใน Filter (เหมือนเดิม) ---
$all_categories = $conn->query("SELECT * FROM categories ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);
$all_goals = $conn->query("SELECT * FROM goals ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);
$all_diseases = $conn->query("SELECT * FROM diseases ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);
$all_diet_types = $conn->query("SELECT * FROM diet_types ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);

// --- 3. รับค่าที่ผู้ใช้เลือกมาจาก Filter (เหมือนเดิม) ---
$search_term = $_GET['search'] ?? '';
$category_id = $_GET['category_id'] ?? '';
$goal_id = $_GET['goal_id'] ?? '';
$disease_id = $_GET['disease_id'] ?? '';
$diet_type_id = $_GET['diet_type_id'] ?? '';


// --- 4. สร้าง SQL Query แบบไดนามิก (ส่วนนี้มีการเปลี่ยนแปลง) ---
$sql_base = "SELECT DISTINCT r.id FROM recipes r"; // เปลี่ยนเป็นนับ id ก่อน
$joins = [];
$wheres = [];
$params_values = [];
$param_types = "";

// สร้างเงื่อนไข Join และ Where (เหมือนเดิม)
if (!empty($search_term)) { $wheres[] = "r.name LIKE ?"; $param_types .= "s"; $params_values[] = "%" . $search_term . "%"; }
if (!empty($category_id)) { $joins['recipe_categories'] = " JOIN recipe_categories rc ON r.id = rc.recipe_id "; $wheres[] = "rc.category_id = ?"; $param_types .= "i"; $params_values[] = $category_id; }
if (!empty($goal_id)) { $joins['recipe_goals'] = " JOIN recipe_goals rg ON r.id = rg.recipe_id "; $wheres[] = "rg.goal_id = ?"; $wheres[] = "rg.suitability = 'good'"; $param_types .= "i"; $params_values[] = $goal_id; }
if (!empty($disease_id)) { $joins['recipe_diseases'] = " JOIN recipe_diseases rd ON r.id = rd.recipe_id "; $wheres[] = "rd.disease_id = ?"; $wheres[] = "rd.suitability = 'good'"; $param_types .= "i"; $params_values[] = $disease_id; }
if (!empty($diet_type_id)) { $joins['recipe_diet_types'] = " JOIN recipe_diet_types rdt ON r.id = rdt.recipe_id "; $wheres[] = "rdt.diet_type_id = ?"; $wheres[] = "rdt.suitability = 'good'"; $param_types .= "i"; $params_values[] = $diet_type_id; }

// --- 5. สร้าง SQL เพื่อ 'นับจำนวน' ผลลัพธ์ทั้งหมด (เวอร์ชันแก้ไข) ---
$sql_count_base = "SELECT COUNT(DISTINCT r.id) FROM recipes r";
$sql_count = $sql_count_base . implode(" ", $joins);
if (!empty($wheres)) {
    $sql_count .= " WHERE " . implode(" AND ", $wheres);
}

$stmt_count = $conn->prepare($sql_count);
if (!empty($param_types)) { $stmt_count->bind_param($param_types, ...$params_values); }
$stmt_count->execute();
$total_items = $stmt_count->get_result()->fetch_row()[0];
$stmt_count->close();
$total_pages = ceil($total_items / $items_per_page);


// --- 6. สร้าง SQL Query เพื่อ 'ดึงข้อมูล' มาแสดงผลในหน้าปัจจุบัน ---
$sql_fetch = "SELECT r.* FROM recipes r" . implode(" ", $joins);
if (!empty($wheres)) { $sql_fetch .= " WHERE " . implode(" AND ", $wheres); }
$sql_fetch .= " ORDER BY r.name ASC LIMIT ? OFFSET ?";
$param_types .= "ii"; // เพิ่ม type integer 2 ตัวสำหรับ LIMIT และ OFFSET
$params_values[] = $items_per_page;
$params_values[] = $offset;

$stmt_fetch = $conn->prepare($sql_fetch);
if (!empty($param_types)) { $stmt_fetch->bind_param($param_types, ...$params_values); }
$stmt_fetch->execute();
$result = $stmt_fetch->get_result();

?>
<style>
/* คำอธิบายเมนู - จำกัด 2 บรรทัด */
.description-text {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
    line-height: 1.4;
    min-height: 2.8em;
    font-size: 0.85rem;
}

/* ปรับขนาด Badge โภชนาการ */
.nutri-badge {
    font-size: 0.7rem;
    padding: 0.35rem 0.5rem;
    margin: 0.15rem;
    display: inline-block;
    color: white;
    font-weight: 600;
}

/* ปรับการ์ดให้สวยงามในทุกหน้าจอ */
.recipe-card {
    transition: transform 0.2s, box-shadow 0.2s;
}

.recipe-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.15) !important;
}

.recipe-card .card-img-top {
    height: 200px;
    object-fit: cover;
}

/* ปรับการ์ดในมือถือ (จอเล็กกว่า 576px) */
@media (max-width: 576px) {
    /* ปรับรูปภาพ */
    .recipe-card .card-img-top {
        height: 160px;
        object-fit: cover;
    }
    
    /* ปรับชื่อเมนู */
    .recipe-card .card-title {
        font-size: 0.85rem;
        margin-bottom: 0.4rem;
        line-height: 1.3;
    }
    
    /* ปรับ padding ของ card-body */
    .recipe-card .card-body {
        padding: 0.6rem !important;
    }
    
    /* ปรับคำอธิบาย */
    .description-text {
        font-size: 0.7rem;
        min-height: 2.4em;
        line-height: 1.3;
        margin-bottom: 0.4rem !important;
    }
    
    /* ปรับส่วนข้อมูลโภชนาการ */
    .nutri-info {
        padding: 0.4rem !important;
        margin-top: 0.5rem !important;
    }
    
    .nutri-badge {
        font-size: 0.55rem;
        padding: 0.2rem 0.35rem;
        margin: 0.08rem;
        white-space: nowrap;
    }
    
    /* ปรับ card-footer */
    .card-footer {
        padding: 0.5rem !important;
    }
    
    .card-footer .btn {
        font-size: 0.7rem !important;
        padding: 0.45rem 0.3rem !important;
        min-width: 100% !important;
    }
    
    .card-footer .btn span {
        text-align: center;
        display: block;
        line-height: 1.2;
    }
}

/* ปรับ Dropdown ในมือถือ */
@media (max-width: 576px) {
    .form-select-sm {
        font-size: 0.8rem;
        padding: 0.4rem 0.6rem;
    }
    
    .form-label {
        font-size: 0.75rem;
        margin-bottom: 0.25rem;
    }
    
    .search-header h1 {
        font-size: 1.3rem;
    }
}

/* Pagination */
.pagination .page-item.active .page-link {
    background: linear-gradient(135deg, #2FC2A0 0%, #B7D971 100%);
    border-color: #2FC2A0;
    color: white;
}

.pagination .page-link {
    color: #2FC2A0;
}

.pagination .page-link:hover {
    background: linear-gradient(135deg, #2FC2A0 0%, #B7D971 100%);
    color: white;
}

/* ปรับระยะห่างการ์ดในมือถือ */
@media (max-width: 576px) {
    .row.g-3 {
        --bs-gutter-x: 0.5rem;
        --bs-gutter-y: 0.75rem;
    }
    
    .row.g-3 > * {
        padding-left: calc(var(--bs-gutter-x) * 0.5);
        padding-right: calc(var(--bs-gutter-x) * 0.5);
    }
}

/* ปรับ container ในมือถือ */
@media (max-width: 576px) {
    .container {
        padding-left: 0.75rem;
        padding-right: 0.75rem;
    }
}

/* ลดพื้นที่ส่วนบนในมือถือเท่านั้น */
@media (max-width: 576px) {
    /* ลด padding-top ของ container หลัก */
    .container.my-5 {
        padding-top: 20px !important;
        margin-top: 1rem !important;
        margin-bottom: 2rem !important;
    }
    
    /* ลดขนาดหัวข้อหลัก */
    .search-header h1 {
        font-size: 1.2rem;
        margin-bottom: 1rem !important;
    }
    
    .search-header h1 i {
        font-size: 1rem;
    }
    
    /* ลดระยะห่าง Form */
    .search-header form {
        margin-top: 0.5rem;
    }
    
    /* ลดระยะห่างระหว่างบรรทัดของ Form */
    .search-header .row.mb-2 {
        margin-bottom: 0.5rem !important;
    }
    
    .search-header .row.g-2 {
        --bs-gutter-y: 0.5rem;
    }
    
    /* ลด padding ของ input และ select */
    .search-header .form-control,
    .search-header .form-select {
        padding: 0.5rem 0.75rem;
    }
    
    /* ลดระยะห่างระหว่าง label และ input */
    .search-header .form-label {
        margin-bottom: 0.2rem !important;
    }
    
    /* ลด padding ของปุ่มกรอง */
    .search-header .btn-gradient {
        padding: 0.6rem 1rem !important;
    }
    
    /* ลดระยะห่างระหว่าง Form กับการ์ด */
    .row.row-cols-2 {
        margin-top: 1rem !important;
    }
}

/* กำหนดค่าเริ่มต้นสำหรับคอมพิวเตอร์ */
.main-container {
    padding-top: 50px;
}

/* ปรับเฉพาะมือถือ */
@media (max-width: 576px) {
    .main-container {
        padding-top: 70px !important;
    }
}

/* กำหนดค่าเริ่มต้นสำหรับคอมพิวเตอร์ */
.main-container {
    padding-top: 50px;
}

/* ปรับเฉพาะมือถือ - ลดพื้นที่ส่วนบน */
@media (max-width: 576px) {
    /* ปรับ padding-top */
    .main-container {
        padding-top: 70px !important;
    }
    
    /* ลด margin ของ container */
    .container.my-5 {
        margin-top: 1rem !important;
        margin-bottom: 2rem !important;
    }
    
    /* ลดขนาดหัวข้อหลัก */
    .search-header h1 {
        font-size: 1.2rem;
        margin-bottom: 1rem !important;
        padding-top: 0;
    }
    
    .search-header h1 i {
        font-size: 1rem;
    }
    
    /* ลดระยะห่าง Form */
    .search-header form {
        margin-top: 0.5rem;
    }
    
    /* ลดระยะห่างระหว่างบรรทัดของ Form */
    .search-header .row.mb-2 {
        margin-bottom: 0.5rem !important;
    }
    
    .search-header .row.g-2 {
        --bs-gutter-y: 0.5rem;
    }
    
    /* ลด padding ของ input และ select */
    .search-header .form-control,
    .search-header .form-select {
        padding: 0.5rem 0.75rem;
        font-size: 0.85rem;
    }
    
    /* ลดระยะห่างระหว่าง label และ input */
    .search-header .form-label {
        margin-bottom: 0.2rem !important;
        font-size: 0.75rem;
    }
    
    /* ลด padding ของปุ่มกรอง */
    .search-header .btn-gradient {
        padding: 0.6rem 1rem !important;
        font-size: 0.9rem;
    }
    
    /* ลดระยะห่างระหว่าง Form กับการ์ด */
    .row.row-cols-2 {
        margin-top: 1rem !important;
    }
}

</style>

    <div class="container my-5 main-container">
        <div class="search-header">
            <h1 class="text-center mb-3 gradient-text"><i class="bi bi-funnel-fill text-primary me-2"></i>ค้นหาเมนูอาหารอย่างละเอียด</h1>
            <form action="recipes.php" method="GET">
<!-- บรรทัดที่ 1: ชื่อเมนู -->
<div class="row g-2 mb-2">
    <div class="col-12">
        <label for="search" class="form-label small fw-bold">ชื่อเมนู</label>
        <input type="text" name="search" id="search" class="form-control" placeholder="เช่น 'ไก่', 'ปลา', 'ผัด'..." value="<?php echo htmlspecialchars($search_term); ?>">
    </div>
</div>

<!-- บรรทัดที่ 2: ประเภทมื้ออาหาร + เป้าหมายสุขภาพ -->
<div class="row g-2 mb-2">
    <div class="col-md-6 col-6">
        <label for="category_id" class="form-label small fw-bold text-primary">ประเภทมื้ออาหาร</label>
        <select name="category_id" id="category_id" class="form-select form-select-sm border-primary">
            <option value="">ทั้งหมด</option>
            <?php foreach($all_categories as $cat): ?>
                <option value="<?php echo $cat['id']; ?>" <?php if($category_id == $cat['id']) echo 'selected'; ?>>
                    <?php echo htmlspecialchars($cat['name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-6 col-6">
        <label for="goal_id" class="form-label small fw-bold text-success">เป้าหมายสุขภาพ</label>
        <select name="goal_id" id="goal_id" class="form-select form-select-sm border-success">
            <option value="">ทั้งหมด</option>
            <?php foreach($all_goals as $goal): ?>
                <option value="<?php echo $goal['id']; ?>" <?php if($goal_id == $goal['id']) echo 'selected'; ?>>
                    <?php echo htmlspecialchars($goal['name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
</div>

<!-- บรรทัดที่ 3: เหมาะสำหรับโรค + ประเภทการกิน -->
<div class="row g-2 mb-2">
    <div class="col-md-6 col-6">
        <label for="disease_id" class="form-label small fw-bold text-danger">เหมาะสำหรับโรค</label>
        <select name="disease_id" id="disease_id" class="form-select form-select-sm border-danger">
            <option value="">ทั้งหมด</option>
            <?php foreach($all_diseases as $disease): ?>
                <option value="<?php echo $disease['id']; ?>" <?php if($disease_id == $disease['id']) echo 'selected'; ?>>
                    <?php echo htmlspecialchars($disease['name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-6 col-6">
        <label for="diet_type_id" class="form-label small fw-bold text-info">ประเภทการกิน</label>
        <select name="diet_type_id" id="diet_type_id" class="form-select form-select-sm border-info">
            <option value="">ทั้งหมด</option>
            <?php foreach($all_diet_types as $diet): ?>
                <option value="<?php echo $diet['id']; ?>" <?php if($diet_type_id == $diet['id']) echo 'selected'; ?>>
                    <?php echo htmlspecialchars($diet['name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
</div>

<!-- บรรทัดที่ 4: ปุ่มกรอง -->
<div class="row g-2">
    <div class="col-12 d-grid">
        <button class="btn btn-gradient py-2" type="submit" style="background: linear-gradient(135deg, #2FC2A0 0%, #B7D971 100%); color: white; font-weight: bold; border: none;">
            <i class="bi bi-search me-1"></i>กรอง
        </button>
    </div>
</div>
            </form>
        </div>
        <div class="row row-cols-2 row-cols-md-2 row-cols-lg-3 g-3 mt-4">
            <?php if ($result->num_rows > 0): ?>
                <?php while($recipe = $result->fetch_assoc()): ?>
<div class="col ">
    <div class="recipe-card card shadow-sm border border border-dark rounded-4 overflow-hidden h-100 ">
        <img src="<?php echo htmlspecialchars($recipe['image_url']); ?>" 
             class="card-img-top" 
             alt="<?php echo htmlspecialchars($recipe['name']); ?>">

        <div class="card-body d-flex flex-column  p-4">
            <h5 class="card-title fw-bold text-center text-dark mb-1">
                <?php echo htmlspecialchars($recipe['name']); ?>
            </h5>
                <p class="text-muted small mb-2 description-text">
                    <?php echo htmlspecialchars($recipe['description']); ?>
                </p>

            <!-- Nutri info ล็อกไว้ล่าง -->
                <div class="nutri-info mt-auto p-2 rounded-3" style="background: linear-gradient(135deg, #E8F5E9 0%, #FFF9C4 50%, #FFEBEE 100%);">
                    <!-- บรรทัดที่ 1 -->
                    <div class="mb-1 text-center">
                        <span class="badge nutri-badge" style="background: linear-gradient(135deg, #2FC2A0 0%, #4CAF50 100%);">
                            แคลอรี่: <?php echo $recipe['calories']; ?> kcal
                        </span>
                        <span class="badge nutri-badge" style="background: linear-gradient(135deg, #B7D971 0%, #8BC34A 100%);">
                            โปรตีน: <?php echo $recipe['protein']; ?>g
                        </span>
                    </div>
                    <!-- บรรทัดที่ 2 -->
                    <div class="mb-1 text-center">
                        <span class="badge nutri-badge" style="background: linear-gradient(135deg, #FFB74D 0%, #FFA726 100%);">
                            ไขมัน: <?php echo $recipe['fat']; ?>g
                        </span>
                        <span class="badge nutri-badge" style="background: linear-gradient(135deg, #64B5F6 0%, #42A5F5 100%);">
                            คาร์บ: <?php echo $recipe['carbs']; ?>g
                        </span>
                    </div>
                    <!-- บรรทัดที่ 3 -->
                    <div class="text-center">
                        <span class="badge nutri-badge" style="background: linear-gradient(135deg, #E57373 0%, #EF5350 100%);">
                            โซเดียม: <?php echo $recipe['sodium_mg']; ?>mg
                        </span>
                    </div>
                </div>
        </div>

        <div class="card-footer text-center bg-white border-0 pb-3 pt-2">
            <a href="recipe_detail.php?id=<?php echo $recipe['id']; ?>" 
            class="btn btn-sm rounded-pill px-3 py-2 fw-bold d-inline-flex align-items-center justify-content-center" 
            style="background: linear-gradient(135deg, #2FC2A0 0%, #B7D971 100%); color: white; border: none; min-width: 90%;">
            <span>ดูวิธีทำและรายละเอียด</span>
            </a>
        </div>
    </div>
</div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="alert alert-warning text-center">ไม่พบเมนูอาหารที่ตรงกับเงื่อนไขที่คุณเลือก</div>
                </div>
            <?php endif; ?>
        </div>
            <nav aria-label="Page navigation" class="mt-5">
                <ul class="pagination pagination-sm justify-content-center flex-wrap">
                    <?php if ($total_pages > 1): ?>
                        <?php
                            $query_params = $_GET;
                            unset($query_params['page']);
                        ?>

                        <li class="page-item <?php if($current_page <= 1) echo 'disabled'; ?>">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($query_params, ['page' => $current_page - 1])); ?>">
                                <i class="bi bi-chevron-left"></i>
                            </a>
                        </li>

                        <?php 
                        // แสดงเฉพาะหน้าใกล้เคียงในมือถือ
                        $start = max(1, $current_page - 2);
                        $end = min($total_pages, $current_page + 2);
                        
                        if ($start > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($query_params, ['page' => 1])); ?>">1</a>
                            </li>
                            <?php if ($start > 2): ?>
                                <li class="page-item disabled"><span class="page-link">...</span></li>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php for ($i = $start; $i <= $end; $i++): ?>
                            <li class="page-item <?php if ($i == $current_page) echo 'active'; ?>">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($query_params, ['page' => $i])); ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>

                        <?php if ($end < $total_pages): ?>
                            <?php if ($end < $total_pages - 1): ?>
                                <li class="page-item disabled"><span class="page-link">...</span></li>
                            <?php endif; ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($query_params, ['page' => $total_pages])); ?>"><?php echo $total_pages; ?></a>
                            </li>
                        <?php endif; ?>

                        <li class="page-item <?php if($current_page >= $total_pages) echo 'disabled'; ?>">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($query_params, ['page' => $current_page + 1])); ?>">
                                <i class="bi bi-chevron-right"></i>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
    </div>
<?php require_once 'includes/footer.php'; ?>