<?php
session_start();
$page_title = "กำหนดแผนเอง";

require_once 'includes/db_connect.php';
require_once 'includes/header.php';
require_once 'includes/functions.php';

// Font Awesome CDN
echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />';

// ตรวจสอบการล็อกอิน
if (!isset($_SESSION['user_id'])) {
    echo '<script>window.location.href = "login.php";</script>';
    exit();
}

$user_id = $_SESSION['user_id'];

// ดึงข้อมูล user profile
$profile_sql = "SELECT * FROM user_profiles WHERE user_id = ?";
$profile_stmt = $conn->prepare($profile_sql);
$profile_stmt->bind_param("i", $user_id);
$profile_stmt->execute();
$user_profile = $profile_stmt->get_result()->fetch_assoc();
$profile_stmt->close();
$recommended_calories = 0;
if ($user_profile) {
    $recommended_calories = getRecommendedCalories($user_profile);
}

// ดึงข้อมูลสำหรับ filters
$diseases = [];
$disease_result = $conn->query("SELECT id, name FROM diseases ORDER BY name");
if ($disease_result) {
    $diseases = $disease_result->fetch_all(MYSQLI_ASSOC);
}

$diet_types = [];
$diet_type_result = $conn->query("SELECT id, name FROM diet_types ORDER BY name");
if ($diet_type_result) {
    $diet_types = $diet_type_result->fetch_all(MYSQLI_ASSOC);
}

$all_tags = [];
$tags_result = $conn->query("SELECT id, name FROM tags ORDER BY name");
if ($tags_result) {
    $all_tags = $tags_result->fetch_all(MYSQLI_ASSOC);
}

// ========== จัดการ active plan ==========
$has_active_plan = false;
$plan_info = null;

// ถ้ามีการออกจากแผน
if (isset($_GET['exit']) && $_GET['exit'] == '1') {
    unset($_SESSION['active_plan_id']);
    header('Location: custom_plan.php');
    exit();
}

// ตรวจสอบว่ามี active plan หรือไม่
if (isset($_SESSION['active_plan_id'])) {
    $plan_id = $_SESSION['active_plan_id'];
    
    // ดึงข้อมูลแผนจาก plan_profiles
    $plan_sql = "SELECT * FROM plan_profiles WHERE id = ? AND user_id = ? AND is_draft = 1";
    $plan_stmt = $conn->prepare($plan_sql);
    $plan_stmt->bind_param("ii", $plan_id, $user_id);
    $plan_stmt->execute();
    $plan_info = $plan_stmt->get_result()->fetch_assoc();
    $plan_stmt->close();
    
    if ($plan_info) {
        $has_active_plan = true;
    } else {
        // ถ้าไม่เจอแผน หรือแผนไม่ใช่ draft แล้ว ให้ล้าง session
        unset($_SESSION['active_plan_id']);
    }
}
?>

<div class="main-wrapper">
    <div class="container py-5 main-container-plan">
    
    <?php if (!$has_active_plan): ?>
        <!-- หน้าเลือกรูปแบบการวางแผน -->
        <div id="plan-selection-view">
            <div class="text-center mb-5">
                <h1 class="fw-bold gradient-text">
                    <i class="bi bi-calendar-plus me-2"></i>วางแผนเมนูกำหนดเอง
                </h1>
                <p class="lead">เลือกรูปแบบการวางแผนที่เหมาะกับคุณ</p>
            </div>

            <div class="row g-4 mb-5">
                <!-- แผน 7 วัน -->
<div class="col-md-6 col-lg-3">
    <div class="card h-100 shadow-sm hover-lift plan-card-gradient-blue" onclick="selectPlanDuration(7)">
        <div class="card-body text-center p-4">
            <div class="mb-3">
                <i class="bi bi-calendar-week display-1 text-white"></i>
            </div>
            <h4 class="card-title mb-3 text-white">แผน 7 วัน</h4>
            <p class="card-text text-white-75">เหมาะสำหรับผู้เริ่มต้น<br>หรือต้องการทดลอง</p>
            <button class="btn btn-light btn-gradient-select mt-3">เลือกแผนนี้</button>
        </div>
    </div>
</div>

<!-- แผน 14 วัน -->
<div class="col-md-6 col-lg-3">
    <div class="card h-100 shadow-sm hover-lift plan-card-gradient-green" onclick="selectPlanDuration(14)">
        <div class="card-body text-center p-4">
            <div class="mb-3">
                <i class="bi bi-calendar2-week display-1 text-white"></i>
            </div>
            <h4 class="card-title mb-3 text-white">แผน 14 วัน</h4>
            <p class="card-text text-white-75">เหมาะสำหรับผู้ที่ต้องการ<br>ความหลากหลาย</p>
            <button class="btn btn-light btn-gradient-select mt-3">เลือกแผนนี้</button>
        </div>
    </div>
</div>

<!-- แผน 1 เดือน -->
<div class="col-md-6 col-lg-3">
    <div class="card h-100 shadow-sm hover-lift plan-card-gradient-orange" onclick="selectPlanDuration(30)">
        <div class="card-body text-center p-4">
            <div class="mb-3">
                <i class="bi bi-calendar-month display-1 text-white"></i>
            </div>
            <h4 class="card-title mb-3 text-white">แผน 1 เดือน</h4>
            <p class="card-text text-white-75">เหมาะสำหรับผู้ที่ต้องการ<br>แผนระยะยาว</p>
            <button class="btn btn-light btn-gradient-select mt-3">เลือกแผนนี้</button>
        </div>
    </div>
</div>

<!-- กำหนดเอง -->
<div class="col-md-6 col-lg-3">
    <div class="card h-100 shadow-sm hover-lift plan-card-gradient-purple" onclick="showCustomDurationModal()">
        <div class="card-body text-center p-4">
            <div class="mb-3">
                <i class="bi bi-sliders display-1 text-white"></i>
            </div>
            <h4 class="card-title mb-3 text-white">กำหนดเอง</h4>
            <p class="card-text text-white-75">เลือกจำนวนวัน<br>ตามต้องการ</p>
            <button class="btn btn-light btn-gradient-select mt-3">เลือกแผนนี้</button>
        </div>
    </div>
</div>
     
            <!-- ปุ่มดูแผนที่บันทึกไว้ -->
            <div class="text-center">
                <a href="my_plans.php" class="btn btn-gradient-outline-text btn-lg">
                    <i class="bi bi-bookmark-star me-2"></i>ดูแผนที่บันทึกไว้
                </a>
            </div>
        </div>

    <?php else: ?>
        <!-- หน้าปฏิทินและการจัดการแผน -->
        <div id="plan-management-view">
<div class="text-center mb-4">
    <h1 class="fw-bold plan-title-gradient">
        <i class="bi bi-calendar3 me-2"></i><?php echo htmlspecialchars($plan_info['profile_name']); ?>
    </h1>
    <p class="lead">แผน <?php echo $plan_info['total_days']; ?> วัน</p>
</div>

<!-- Action Buttons -->
<div class="d-flex justify-content-center mb-4">
    <div class="btn-group" role="group">
        <button class="btn btn-gradient-success" onclick="savePlanProfile()">
            <i class="bi bi-bookmark-plus-fill me-2"></i>บันทึกเป็นโปรไฟล์
        </button>
        <button class="btn btn-gradient-danger" onclick="deletePlan()">
            <i class="bi bi-trash3-fill me-2"></i>ลบแผนทั้งหมด
        </button>
        <button class="btn btn-gradient-secondary" onclick="exitPlan()">
            <i class="bi bi-x-circle me-2"></i>ออกจากแผน
        </button>
    </div>
</div>

            <!-- Progress Bar -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fw-bold">ความคืบหน้า:</span>
                        <span id="progress-text" class="text-muted">0/<?php echo $plan_info['total_days']; ?> วัน</span>
                    </div>
                    <div class="progress" style="height: 25px;">
                        <div id="progress-bar" class="progress-bar progress-bar-striped progress-bar-animated bg-success" 
                             role="progressbar" style="width: 0%">
                            0%
                        </div>
                    </div>
                </div>
            </div>

            <!-- Calendar Navigation -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <button class="btn btn-outline-secondary" id="prev-page-btn" onclick="changePage(-1)">
                    <i class="bi bi-chevron-left"></i> ก่อนหน้า
                </button>
                <span class="fw-bold" id="page-indicator">หน้า 1</span>
                <button class="btn btn-outline-secondary" id="next-page-btn" onclick="changePage(1)">
                    ถัดไป <i class="bi bi-chevron-right"></i>
                </button>
            </div>

            <!-- Calendar Grid -->
            <div id="calendar-container" class="mb-4">
                <!-- จะถูก generate ด้วย JavaScript -->
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Modal: กำหนดจำนวนวันเอง -->
<div class="modal fade" id="customDurationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">กำหนดจำนวนวันเอง</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <label for="custom-days-input" class="form-label">จำนวนวัน (1-90)</label>
                <input type="number" class="form-control" id="custom-days-input" 
                       min="1" max="90" value="21" placeholder="ระบุจำนวนวัน">
                <div class="form-text">แนะนำ: 21 วัน หรือ 28 วัน สำหรับแผนที่มีประสิทธิภาพ</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                <button type="button" class="btn btn-primary" onclick="confirmCustomDuration()">ยืนยัน</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: วางแผนเมนูประจำวัน -->
<div class="modal fade" id="dayPlanModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-pencil-square"></i> วางแผนวันที่ <span id="modal-day-number"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="container-fluid">
                    <input type="hidden" id="current-day-index" value="">
                    <div class="row g-4">
                        <!-- คอลัมน์ซ้าย: ค้นหาเมนู -->
                        <div class="col-lg-4">
                            <div class="planner-section h-100 d-flex flex-column">
                                <h4 class="planner-section-header">
                                    <i class="bi bi-search me-2"></i>ค้นหาเมนูอาหาร
                                </h4>
                                
                                <!-- ฟิลเตอร์ -->
                                <div class="row g-2 mb-3">
                                    <div class="col-12">
                                        <input type="text" id="search-input" class="form-control form-control-sm" 
                                               placeholder="ค้นหาตามชื่อ...">
                                    </div>
                                    <div class="col-md-6">
                                        <select id="category-filter" class="form-select form-select-sm">
                                            <option value="">ทุกประเภท</option>
                                            <option value="มื้อเช้า">มื้อเช้า</option>
                                            <option value="มื้อกลางวัน">มื้อกลางวัน</option>
                                            <option value="มื้อเย็น">มื้อเย็น</option>
                                            <option value="ของว่าง">ของว่าง</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <select id="diet-type-filter" class="form-select form-select-sm">
                                            <option value="">ประเภทการกิน</option>
                                            <?php foreach ($diet_types as $diet): ?>
                                                <option value="<?php echo $diet['id']; ?>">
                                                    <?php echo htmlspecialchars($diet['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <select id="disease-filter" class="form-select form-select-sm">
                                            <option value="">เหมาะสำหรับโรค</option>
                                            <?php foreach ($diseases as $disease): ?>
                                                <option value="<?php echo $disease['id']; ?>">
                                                    <?php echo htmlspecialchars($disease['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <button class="btn btn-primary btn-sm w-100" onclick="randomizeAllMeals()">
                                            <i class="bi bi-shuffle me-1"></i>สุ่มเมนูทั้งหมด
                                        </button>
                                    </div>
                                </div>

                                <!-- ผลการค้นหา -->
                                <div id="search-results" style="flex-grow: 1; min-height: 300px; overflow-y: auto;">
                                    <!-- Recipe items จะถูกแสดงที่นี่ -->
                                </div>
                            </div>
                        </div>

                        <!-- คอลัมน์ขวา: แผนอาหารประจำวัน -->
                        <div class="col-lg-8">
                            <div class="planner-section h-100">
                                <h4 class="planner-section-header">
                                    <i class="bi bi-calendar-check me-2"></i>แผนอาหารประจำวัน
                                </h4>

                                <!-- 5 มื้อ -->
                                <div class="row g-2 mb-4">
                                    <?php 
                                    $meals = [
                                        ['breakfast', 'มื้อเช้า', '#2FACAA', 'fa-coffee'],
                                        ['brunch', 'มื้อสาย', '#B7D971', 'fa-bread-slice'],
                                        ['lunch', 'มื้อเที่ยง', '#FFB405', 'fa-burger'],
                                        ['afternoon_snack', 'มื้อบ่าย', '#E3812B', 'fa-cookie-bite'],
                                        ['dinner', 'มื้อเย็น', '#7E72DA', 'fa-utensils']
                                    ];
                                    foreach ($meals as $meal): 
                                    ?>
                                        <div class="col-12 col-lg">
                                            <div class="card h-100">
                                                <div class="card-header fw-bold text-white d-flex justify-content-between align-items-center" 
                                                     style="background-color: <?php echo $meal[2]; ?>;">
                                                    <span>
                                                        <?php echo $meal[1]; ?> 
                                                        <i class="fas <?php echo $meal[3]; ?>"></i>
                                                    </span>
                                                    <button class="btn btn-sm btn-light py-0 px-2" 
                                                            onclick="randomizeMeal('<?php echo $meal[0]; ?>')"
                                                            title="สุ่มเมนู">
                                                        <i class="bi bi-shuffle"></i>
                                                    </button>
                                                </div>
                                                <ul id="meal-<?php echo $meal[0]; ?>" class="list-group list-group-flush">
                                                    <!-- Recipe items -->
                                                </ul>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <!-- สรุปโภชนาการ -->
                                <h5 class="mb-3"><i class="bi bi-clipboard-data me-2"></i>สรุปโภชนาการรวม</h5>
                                <div class="nutrition-summary-grid">
                                    <!-- พลังงานและมาโคร -->
                                    <div class="nutrition-stat-card calories">
                                        <div class="label"><i class="bi bi-lightning-fill"></i> พลังงาน</div>
                                        <div><span id="summary-calories" class="value">0</span> <span class="unit">Kcal</span></div>
                                    </div>
                                    <div class="nutrition-stat-card protein">
                                        <div class="label"><i class="bi bi-egg-fill"></i> โปรตีน</div>
                                        <div><span id="summary-protein" class="value">0.0</span> <span class="unit">g</span></div>
                                    </div>
                                    <div class="nutrition-stat-card carbs">
                                        <div class="label"><i class="bi bi-grain"></i> คาร์โบไฮเดรต</div>
                                        <div><span id="summary-carbs" class="value">0.0</span> <span class="unit">g</span></div>
                                    </div>
                                    <div class="nutrition-stat-card fat">
                                        <div class="label"><i class="bi bi-droplet-fill"></i> ไขมัน</div>
                                        <div><span id="summary-fat" class="value">0.0</span> <span class="unit">g</span></div>
                                    </div>
                                    
                                    <!-- ไขมันและคอเลสเตอรอล -->
                                    <div class="nutrition-stat-card">
                                        <div class="label">ไขมันอ่ิมตัว</div>
                                        <div><span id="summary-saturated_fat" class="value">0.0</span> <span class="unit">g</span></div>
                                    </div>
                                    <div class="nutrition-stat-card">
                                        <div class="label">ไขมันทรานส์</div>
                                        <div><span id="summary-trans_fat" class="value">0.0</span> <span class="unit">g</span></div>
                                    </div>
                                    <div class="nutrition-stat-card">
                                        <div class="label">โอเมก้า 3</div>
                                        <div><span id="summary-omega_3" class="value">0.0</span> <span class="unit">g</span></div>
                                    </div>
                                    <div class="nutrition-stat-card">
                                        <div class="label">คอเลสเตอรอล</div>
                                        <div><span id="summary-cholesterol" class="value">0</span> <span class="unit">mg</span></div>
                                    </div>
                                    
                                    <!-- เกลือและน้ำตาล -->
                                    <div class="nutrition-stat-card">
                                        <div class="label">โซเดียม</div>
                                        <div><span id="summary-sodium" class="value">0</span> <span class="unit">mg</span></div>
                                    </div>
                                    <div class="nutrition-stat-card">
                                        <div class="label">น้ำตาล</div>
                                        <div><span id="summary-sugar" class="value">0.0</span> <span class="unit">g</span></div>
                                    </div>
                                    <div class="nutrition-stat-card">
                                        <div class="label">ใยอาหาร</div>
                                        <div><span id="summary-fiber" class="value">0.0</span> <span class="unit">g</span></div>
                                    </div>
                                    
                                    <!-- วิตามิน -->
                                    <div class="nutrition-stat-card vitamin">
                                        <div class="label">วิตามิน A</div>
                                        <div><span id="summary-vitamin_a" class="value">0</span> <span class="unit">µg</span></div>
                                    </div>
                                    <div class="nutrition-stat-card vitamin">
                                        <div class="label">วิตามิน B</div>
                                        <div><span id="summary-vitamin_b" class="value">0.0</span> <span class="unit">mg</span></div>
                                    </div>
                                    <div class="nutrition-stat-card vitamin">
                                        <div class="label">วิตามิน C</div>
                                        <div><span id="summary-vitamin_c" class="value">0.0</span> <span class="unit">mg</span></div>
                                    </div>
                                    <div class="nutrition-stat-card vitamin">
                                        <div class="label">วิตามิน D</div>
                                        <div><span id="summary-vitamin_d" class="value">0</span> <span class="unit">µg</span></div>
                                    </div>
                                    <div class="nutrition-stat-card vitamin">
                                        <div class="label">วิตามิน E</div>
                                        <div><span id="summary-vitamin_e" class="value">0.0</span> <span class="unit">mg</span></div>
                                    </div>
                                    <div class="nutrition-stat-card vitamin">
                                        <div class="label">วิตามิน K</div>
                                        <div><span id="summary-vitamin_k" class="value">0</span> <span class="unit">µg</span></div>
                                    </div>
                                    
                                    <!-- แร่ธาตุ -->
                                    <div class="nutrition-stat-card mineral">
                                        <div class="label">แคลเซียม</div>
                                        <div><span id="summary-calcium" class="value">0</span> <span class="unit">mg</span></div>
                                    </div>
                                    <div class="nutrition-stat-card mineral">
                                        <div class="label">เหล็ก</div>
                                        <div><span id="summary-iron" class="value">0.0</span> <span class="unit">mg</span></div>
                                    </div>
                                    <div class="nutrition-stat-card mineral">
                                        <div class="label">โพแทสเซียม</div>
                                        <div><span id="summary-potassium" class="value">0</span> <span class="unit">mg</span></div>
                                    </div>
                                    <div class="nutrition-stat-card mineral">
                                        <div class="label">แมกนีเซียม</div>
                                        <div><span id="summary-magnesium" class="value">0</span> <span class="unit">mg</span></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                <button type="button" class="btn btn-primary" onclick="saveDayPlan()">
                    <i class="bi bi-save"></i> บันทึกแผนวันนี้
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: คัดลอกแผน -->
<div class="modal fade" id="copyDayModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">คัดลอกแผนไปยังวันอื่น</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>เลือกวันที่ต้องการคัดลอกแผนไป:</p>
                <div id="copy-days-list" class="d-flex flex-wrap gap-2">
                    <!-- Checkboxes จะถูก generate ที่นี่ -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                <button type="button" class="btn btn-primary" onclick="confirmCopyPlan()">
                    <i class="bi bi-clipboard-check"></i> ยืนยันการคัดลอก
                </button>
            </div>
        </div>
    </div>
</div>
</div><!-- Close main-wrapper -->

<!-- Modal: บันทึกเป็นโปรไฟล์ -->
<div class="modal fade" id="saveProfileModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">บันทึกเป็นโปรไฟล์แผนอาหาร</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="save-profile-form">
                    <div class="mb-3">
                        <label class="form-label">ชื่อโปรไฟล์ <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="profile-name" 
                               value="<?php echo htmlspecialchars($plan_info['profile_name'] ?? ''); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">คำอธิบาย</label>
                        <textarea class="form-control" id="profile-description" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">แท็ก</label>
                        <div id="selected-tags" class="mb-2"></div>
                        <input type="text" class="form-control" id="tag-input" 
                               placeholder="พิมพ์แท็กแล้วกด Enter">
                        <div id="existing-tags" class="mt-2 d-flex flex-wrap gap-2">
                            <?php foreach ($all_tags as $tag): ?>
                                <span class="badge bg-secondary tag-option" 
                                      onclick="addTag('<?php echo htmlspecialchars($tag['name']); ?>')">
                                    <?php echo htmlspecialchars($tag['name']); ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                <button type="button" class="btn btn-primary" onclick="confirmSaveProfile()">
                    <i class="bi bi-bookmark-check"></i> บันทึกโปรไฟล์
                </button>
            </div>
        </div>
    </div>
</div>

<style>
/* ========== Sticky Footer Fix ========== */
html, body {
    height: 100%;
    margin: 0;
    padding: 0;
}

.main-wrapper {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
}

.main-container-plan {
    flex: 1;
    padding-top: 100px !important;
}

/* ตรวจสอบว่า footer มี class อะไร และเพิ่ม */
footer {
    margin-top: auto;
    width: 100%;
}

/* ========== กำหนดค่าเริ่มต้นสำหรับคอมพิวเตอร์ ========== */

.hover-lift {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    cursor: pointer;
}
.hover-lift:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.15) !important;
}
.planner-section {
    background-color: #fff;
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}
.planner-section-header {
    color: #2FAAA8;
    border-bottom: 2px solid #2FAAA8;
    padding-bottom: 0.5rem;
    margin-bottom: 1rem;
}

/* ========== ปรับเฉพาะมือถือ - ลดพื้นที่ส่วนบน ========== */
@media (max-width: 576px) {
    .main-wrapper {
        min-height: 100vh;
        display: flex;
        flex-direction: column;
    }
    
    .main-container-plan {
        flex: 1;
        padding-top: 40px !important;
        padding-bottom: 2rem !important;
    }
    
    footer {
        margin-top: auto;
    }
    
    /* ลด margin ของ container */
    .main-container-plan.py-5 {
        padding-top: 40px !important;
        padding-bottom: 2rem !important;
    }
    
    /* ลดขนาดหัวข้อหลัก */
    .main-container-plan h1 {
        font-size: 1.3rem;
        margin-bottom: 0.75rem !important;
    }
    
    .main-container-plan .lead {
        font-size: 0.95rem;
        margin-bottom: 1rem !important;
    }
    
    /* ลดระยะห่างของ text-center mb-5 */
    .main-container-plan .text-center.mb-5 {
        margin-bottom: 1.5rem !important;
    }
    
    /* ลดระยะห่างของ row g-4 */
    .main-container-plan .row.g-4 {
        --bs-gutter-y: 1rem;
    }
    
    /* ลด padding ของการ์ดเลือกแผน */
    .main-container-plan .card-body.p-4 {
        padding: 1rem !important;
    }
    
    /* ลดขนาด icon */
    .main-container-plan .card-body i.display-1 {
        font-size: 2.5rem;
    }
    
    /* ลดขนาดหัวข้อการ์ด */
    .main-container-plan .card-title {
        font-size: 1.1rem;
        margin-bottom: 0.5rem !important;
    }
    
    /* ลดขนาดข้อความในการ์ด */
    .main-container-plan .card-text {
        font-size: 0.85rem;
    }
    
    /* ลดระยะห่างปุ่ม */
    .main-container-plan .btn.mt-3 {
        margin-top: 0.75rem !important;
    }
    
    /* ลดระยะห่าง Action Buttons */
    .main-container-plan .text-center.mb-4 {
        margin-bottom: 1rem !important;
    }
    
    /* ลด padding ของ card mb-4 (Progress Bar) */
    .main-container-plan .card.mb-4 {
        margin-bottom: 1rem !important;
    }
    
    .main-container-plan .card-body {
        padding: 0.75rem !important;
    }
    
    /* ลดขนาดปุ่ม Navigation Calendar */
    .main-container-plan .btn-outline-secondary {
        font-size: 0.85rem;
        padding: 0.4rem 0.75rem;
    }
    
    /* ลดขนาด day-card */
    .day-card {
        padding: 0.75rem !important;
    }
    
    .day-card h5 {
        font-size: 1rem;
        margin-bottom: 0.5rem !important;
    }
    
    .day-card .badge {
        font-size: 0.75rem;
        padding: 0.25rem 0.5rem;
    }
    
    .day-card .btn {
        font-size: 0.8rem;
        padding: 0.4rem 0.5rem;
    }
}

/* ========== ปรับ Modal ในมือถือ ========== */
@media (max-width: 576px) {
    /* ลด padding ของ modal-body */
    .modal-body {
        padding: 1rem !important;
    }
    
    .modal-header h5 {
        font-size: 1.1rem;
    }
    
    /* ลดขนาด planner-section */
    .planner-section {
        padding: 1rem !important;
    }
    
    .planner-section-header {
        font-size: 1.1rem;
    }
    
    /* ลดขนาด form control */
    .modal-body .form-control,
    .modal-body .form-select {
        font-size: 0.85rem;
        padding: 0.5rem 0.75rem;
    }
    
    /* ลดขนาด label */
    .modal-body .form-label {
        font-size: 0.85rem;
        margin-bottom: 0.25rem;
    }
    
    /* ปรับ nutrition summary grid */
    .nutrition-summary-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 0.5rem;
    }
    
    .nutrition-stat-card {
        padding: 0.5rem;
    }
    
    .nutrition-stat-card .label {
        font-size: 0.7rem;
    }
    
    .nutrition-stat-card .value {
        font-size: 1.1rem;
    }
    
    .nutrition-stat-card .unit {
        font-size: 0.75rem;
    }
}

/* ========== Gradient Plan Cards (4 กล่องเลือกแผน) ========== */
.plan-card-gradient-blue {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
}

.plan-card-gradient-green {
    background: linear-gradient(135deg, #2FC2A0 0%, #B7D971 100%);
    border: none;
}

.plan-card-gradient-orange {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    border: none;
}

.plan-card-gradient-purple {
    background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    border: none;
}

.plan-card-gradient-blue:hover,
.plan-card-gradient-green:hover,
.plan-card-gradient-orange:hover,
.plan-card-gradient-purple:hover {
    transform: translateY(-8px);
    box-shadow: 0 15px 35px rgba(0,0,0,0.25) !important;
}

/* ปรับสีข้อความในกล่องให้เป็นสีขาว */
.text-white-75 {
    color: rgba(255, 255, 255, 0.85);
}

/* ปุ่มในกล่องแผน (เลือกแผนนี้) */
.btn-gradient-select {
    background: white;
    color: #333;
    font-weight: 600;
    border: none;
    transition: all 0.3s ease;
}

.btn-gradient-select:hover {
    background: rgba(255, 255, 255, 0.9);
    transform: scale(1.05);
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
}

/* ========== ปุ่ม "ดูแผนที่บันทึกไว้" ========== */
.btn-gradient-outline-text {
    background: transparent;
    border: 2px solid transparent;
    background-image: linear-gradient(white, white), 
                      linear-gradient(135deg, #2FC2A0 0%, #B7D971 100%);
    background-origin: border-box;
    background-clip: padding-box, border-box;
    position: relative;
    overflow: hidden;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-gradient-outline-text:before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(135deg, #2FC2A0 0%, #B7D971 100%);
    opacity: 0;
    transition: opacity 0.3s ease;
    z-index: -1;
}

.btn-gradient-outline-text:hover:before {
    opacity: 1;
}

.btn-gradient-outline-text:hover {
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(47, 194, 160, 0.3);
}

/* ข้อความไล่เฉด */
.btn-gradient-outline-text {
    background: linear-gradient(135deg, #2FC2A0 0%, #B7D971 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.btn-gradient-outline-text:hover {
    -webkit-text-fill-color: white;
    color: white;
}

/* ========== หัวข้อแผน (แผน X วัน) ========== */
.plan-title-gradient {
    background: linear-gradient(135deg, #2FC2A0 0%, #B7D971 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    color: transparent;
    display: inline-block;
}

/* ========== ปุ่ม Action (บันทึก, ลบ, ออก) ========== */
.btn-gradient-success {
    background: linear-gradient(135deg, #2FC2A0 0%, #B7D971 100%);
    border: none;
    color: white;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-gradient-success:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(47, 194, 160, 0.4);
    background: linear-gradient(135deg, #28a88f 0%, #a3c55f 100%);
}

.btn-gradient-danger {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    border: none;
    color: white;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-gradient-danger:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(245, 87, 108, 0.4);
    background: linear-gradient(135deg, #d67ee3 0%, #db4e5f 100%);
}

.btn-gradient-secondary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    color: white;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-gradient-secondary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(118, 75, 162, 0.4);
    background: linear-gradient(135deg, #5a6fd1 0%, #663e8f 100%);
}

/* จัดปุ่ม Action ให้อยู่ตรงกลาง */
.d-flex.justify-content-center .btn-group {
    display: inline-flex;
}

</style>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
// ============= GLOBAL VARIABLES =============
const RECOMMENDED_CALORIES = <?php echo $recommended_calories; ?>;
const HAS_ACTIVE_PLAN = <?php echo $has_active_plan ? 'true' : 'false'; ?>;
let PLAN_ID = <?php echo isset($_SESSION['active_plan_id']) ? $_SESSION['active_plan_id'] : 'null'; ?>;
let TOTAL_DAYS = <?php echo $has_active_plan ? $plan_info['total_days'] : 0; ?>;
let currentPage = 0;
const DAYS_PER_PAGE = 28;

let planData = {};
let currentDayIndex = null;
let selectedTags = new Set();

let dayPlanModal, copyDayModal, saveProfileModal, customDurationModal;

// ============= INITIALIZATION =============
document.addEventListener('DOMContentLoaded', async function() {
    // Initialize modals
    const dayPlanModalEl = document.getElementById('dayPlanModal');
    const copyDayModalEl = document.getElementById('copyDayModal');
    const saveProfileModalEl = document.getElementById('saveProfileModal');
    const customDurationModalEl = document.getElementById('customDurationModal');
    
    if (dayPlanModalEl) dayPlanModal = new bootstrap.Modal(dayPlanModalEl);
    if (copyDayModalEl) copyDayModal = new bootstrap.Modal(copyDayModalEl);
    if (saveProfileModalEl) saveProfileModal = new bootstrap.Modal(saveProfileModalEl);
    if (customDurationModalEl) customDurationModal = new bootstrap.Modal(customDurationModalEl);

    if (HAS_ACTIVE_PLAN) {
        // รอให้ loadPlanData() เสร็จก่อน แล้วค่อย render
        const loaded = await loadPlanData();
        if (loaded !== false) {
            renderCalendar();
            updateProgress();
        }
    }

    // Search and filter listeners
    setupSearchListeners();
    
    // Tag input listener
    const tagInput = document.getElementById('tag-input');
    if (tagInput) {
        tagInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                addTag(this.value.trim());
                this.value = '';
            }
        });
    }
});

// ============= PLAN SELECTION =============
async function selectPlanDuration(days) {
    if (!confirm(`คุณต้องการสร้างแผน ${days} วันใช่หรือไม่?`)) return;
    
    try {
        const response = await fetch('process/create_meal_plan.php', {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ 
                days: days,
                profile_name: `แผน ${days} วัน`
            })
        });
        
        // ตรวจสอบ content-type
        const contentType = response.headers.get("content-type");
        
        if (!contentType || !contentType.includes("application/json")) {
            const text = await response.text();
            console.error("Response is not JSON:", text);
            alert('เกิดข้อผิดพลาด: ระบบตอบกลับไม่ถูกต้อง');
            return;
        }
        
        const result = await response.json();
        
        if (result.success) {
            alert('สร้างแผนสำเร็จ!');
            window.location.reload();
        } else {
            alert('เกิดข้อผิดพลาด: ' + result.message);
        }
    } catch (error) {
        console.error('Error:', error);
        alert('เกิดข้อผิดพลาด: ' + error.message);
    }
}

function showCustomDurationModal() {
    if (customDurationModal) {
        customDurationModal.show();
    }
}

function confirmCustomDuration() {
    const days = parseInt(document.getElementById('custom-days-input').value);
    if (days < 1 || days > 90) {
        alert('กรุณาระบุจำนวนวันระหว่าง 1-90 วัน');
        return;
    }
    if (customDurationModal) {
        customDurationModal.hide();
    }
    selectPlanDuration(days);
}

// ============= CALENDAR RENDERING (แก้ไข) =============
async function loadPlanData() {
    // แสดง loading
    const container = document.getElementById('calendar-container');
    if (container) {
        container.innerHTML = `
            <div class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">กำลังโหลด...</span>
                </div>
                <p class="mt-3 text-muted">กำลังโหลดข้อมูลแผน...</p>
            </div>
        `;
    }
    
    try {
        const response = await fetch(`api/get_meal_plan_data.php?plan_id=${PLAN_ID}`);
        
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        
        const result = await response.json();
        
        if (result.success) {
            // ตรวจสอบว่า data เป็น object หรือไม่
            if (result.data && typeof result.data === 'object') {
                planData = result.data;
                console.log('Loaded plan data:', planData); // Debug
                console.log('Number of days with plans:', Object.keys(planData).length); // Debug
                return true;
            } else {
                console.error('Invalid data format:', result.data);
                planData = {};
                return false;
            }
        } else {
            console.error('Load failed:', result.message);
            planData = {};
            return false;
        }
    } catch (error) {
        console.error('Error loading plan data:', error);
        planData = {};
        
        // แสดง error message
        if (container) {
            container.innerHTML = `
                <div class="alert alert-danger text-center" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    ไม่สามารถโหลดข้อมูลแผนได้ กรุณาลองใหม่อีกครั้ง
                    <button class="btn btn-sm btn-primary mt-2" onclick="window.location.reload()">
                        <i class="bi bi-arrow-clockwise"></i> รีโหลด
                    </button>
                </div>
            `;
        }
        
        return false;
    }
}

function renderCalendar() {
    const container = document.getElementById('calendar-container');
    if (!container) return;
    
    const startIdx = currentPage * DAYS_PER_PAGE;
    const endIdx = Math.min(startIdx + DAYS_PER_PAGE, TOTAL_DAYS);
    
    let html = '<div class="row g-3">';
    
    for (let i = startIdx; i < endIdx; i++) {
        const dayNum = i + 1;
        const hasPlan = planData[i] && Object.values(planData[i]).some(meal => meal && meal.length > 0);
        const cardClass = hasPlan ? 'day-card has-plan' : 'day-card';
        
        html += `
        <div class="col-6 col-sm-4 col-md-3 col-lg-auto">
            <div class="${cardClass}" id="day-${i}">
                <h5 class="mb-2">วันที่ ${dayNum}</h5>
                ${hasPlan ? `
                    <div class="badge bg-success mb-2">
                        <i class="bi bi-check-circle"></i> มีแผน
                    </div>
                ` : `
                    <div class="badge bg-secondary mb-2">
                        <i class="bi bi-circle"></i> ยังไม่มีแผน
                    </div>
                `}
                <div class="d-grid gap-2">
                    <button class="btn btn-sm btn-primary" onclick="openDayPlan(${i})">
                        <i class="bi bi-pencil"></i> ${hasPlan ? 'แก้ไข' : 'เพิ่ม'}
                    </button>
                    ${hasPlan ? `
                        <div class="btn-group" role="group">
                            <button class="btn btn-sm btn-outline-info" onclick="openCopyModal(${i})" title="คัดลอก">
                                <i class="bi bi-clipboard"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger" onclick="deleteDayPlan(${i})" title="ลบ">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    ` : ''}
                </div>
            </div>
        </div>`;
        
        if ((i - startIdx + 1) % 7 === 0 && i < endIdx - 1) {
            html += '</div><div class="row g-3 mt-0">';
        }
    }
    
    html += '</div>';
    container.innerHTML = html;
    
    // Update pagination
    const totalPages = Math.ceil(TOTAL_DAYS / DAYS_PER_PAGE);
    document.getElementById('page-indicator').textContent = `หน้า ${currentPage + 1} จาก ${totalPages}`;
    document.getElementById('prev-page-btn').disabled = currentPage === 0;
    document.getElementById('next-page-btn').disabled = currentPage >= totalPages - 1;
}

function changePage(direction) {
    const totalPages = Math.ceil(TOTAL_DAYS / DAYS_PER_PAGE);
    currentPage += direction;
    if (currentPage < 0) currentPage = 0;
    if (currentPage >= totalPages) currentPage = totalPages - 1;
    renderCalendar();
}

function updateProgress() {
    const completedDays = Object.keys(planData).filter(dayIdx => {
        const day = planData[dayIdx];
        return day && Object.values(day).some(meal => meal && meal.length > 0);
    }).length;
    
    const percentage = Math.round((completedDays / TOTAL_DAYS) * 100);
    document.getElementById('progress-text').textContent = `${completedDays}/${TOTAL_DAYS} วัน`;
    document.getElementById('progress-bar').style.width = percentage + '%';
    document.getElementById('progress-bar').textContent = percentage + '%';
}

// ============= EXIT PLAN (แก้ไข) =============
async function exitPlan() {
    if (!confirm('คุณต้องการออกจากแผนนี้ใช่หรือไม่?')) {
        return;
    }
    
    try {
        // เรียก API เพื่อ clear session
        const response = await fetch('process/exit_plan_custom.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' }
        });
        
        const result = await response.json();
        
        if (result.success) {
            window.location.href = 'custom_plan.php';
        } else {
            // ถ้า API ล้มเหลว ให้ลองใช้วิธีเดิม
            window.location.href = 'custom_plan.php?exit=1';
        }
    } catch (error) {
        // ถ้าเกิด error ให้ลองใช้วิธีเดิม
        window.location.href = 'custom_plan.php?exit=1';
    }
}

// ============= DELETE PLAN =============
async function deletePlan() {
    if (!confirm('คุณต้องการลบแผนทั้งหมดใช่หรือไม่? การกระทำนี้ไม่สามารถย้อนกลับได้')) return;
    
    try {
        const response = await fetch('process/delete_meal_plan.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ plan_id: PLAN_ID })
        });
        
        const result = await response.json();
        if (result.success) {
            alert('ลบแผนสำเร็จ!');
            window.location.reload();
        } else {
            alert('เกิดข้อผิดพลาด: ' + result.message);
        }
    } catch (error) {
        alert('เกิดข้อผิดพลาด: ' + error.message);
    }
}

// ============= DAY PLANNING (แก้ไขส่วนโหลดข้อมูล) =============
async function openDayPlan(dayIndex) {
    currentDayIndex = dayIndex;
    document.getElementById('current-day-index').value = dayIndex;
    document.getElementById('modal-day-number').textContent = dayIndex + 1;
    
    // Load existing plan or create empty
    let existingPlan = planData[dayIndex];
    
    // ตรวจสอบว่ามีข้อมูลหรือไม่
    if (!existingPlan || typeof existingPlan !== 'object') {
        existingPlan = {
            breakfast: [],
            brunch: [],
            lunch: [],
            afternoon_snack: [],
            dinner: []
        };
    }
    
    // ตรวจสอบว่าแต่ละมื้อเป็น array หรือไม่
    const mealTypes = ['breakfast', 'brunch', 'lunch', 'afternoon_snack', 'dinner'];
    mealTypes.forEach(mealType => {
        if (!Array.isArray(existingPlan[mealType])) {
            existingPlan[mealType] = [];
        }
    });
    
    // Populate meal containers
    for (const mealType in existingPlan) {
        const container = document.getElementById(`meal-${mealType}`);
        if (container) {
            container.innerHTML = '';
            if (Array.isArray(existingPlan[mealType])) {
                existingPlan[mealType].forEach((recipe, index) => {
                    addRecipeToUI(mealType, recipe, index);
                });
            }
        }
    }
    
    updateNutritionSummary();
    searchRecipes('', '', '', '');
    
    if (dayPlanModal) {
        dayPlanModal.show();
    }
}

// ============= เพิ่มฟังก์ชัน Error Handler =============
function handleAjaxError(error, context) {
    console.error(`Error in ${context}:`, error);
    
    // แสดงข้อความที่เป็นมิตรกับผู้ใช้
    let userMessage = 'เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง';
    
    if (error.message) {
        userMessage += '\n' + error.message;
    }
    
    alert(userMessage);
}

// ============= ปรับปรุง saveDayPlan เพื่อจัดการ error ดีขึ้น =============
async function saveDayPlan() {
    const dayIndex = parseInt(document.getElementById('current-day-index').value);
    
    // Collect all meals
    const dayPlan = {
        breakfast: [],
        brunch: [],
        lunch: [],
        afternoon_snack: [],
        dinner: []
    };
    
    for (const mealType in dayPlan) {
        const container = document.getElementById(`meal-${mealType}`);
        if (container) {
            const items = container.querySelectorAll('.list-group-item');
            items.forEach(item => {
                try {
                    const recipeData = JSON.parse(item.dataset.recipe);
                    dayPlan[mealType].push(recipeData);
                } catch (e) {
                    console.error('Error parsing recipe data:', e);
                }
            });
        }
    }
    
    // Check if at least one meal is added
    const hasMeals = Object.values(dayPlan).some(meal => meal.length > 0);
    if (!hasMeals) {
        alert('กรุณาเพิ่มอาหารอย่างน้อย 1 รายการ');
        return;
    }
    
    // แสดง loading
    const modalFooter = document.querySelector('#dayPlanModal .modal-footer');
    const saveBtn = modalFooter.querySelector('button.btn-primary');
    const originalText = saveBtn.innerHTML;
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>กำลังบันทึก...';
    
    try {
        console.log('Sending data:', {
            plan_id: PLAN_ID,
            day_index: dayIndex,
            plan: dayPlan
        });
        
        const response = await fetch('process/save_day_plan.php', {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                plan_id: PLAN_ID,
                day_index: dayIndex,
                plan: dayPlan
            })
        });
        
        // ตรวจสอบ Content-Type ของ response
        const contentType = response.headers.get("content-type");
        
        if (!contentType || !contentType.includes("application/json")) {
            const text = await response.text();
            console.error("Response is not JSON:", text);
            throw new Error('เซิร์ฟเวอร์ตอบกลับผิดพลาด (ไม่ใช่ JSON)');
        }
        
        const result = await response.json();
        console.log('Response:', result);
        
        if (result.success) {
            // อัปเดต local data
            planData[dayIndex] = dayPlan;
            
            if (dayPlanModal) dayPlanModal.hide();
            
            // รีเฟรชข้อมูล
            await loadPlanData();
            renderCalendar();
            updateProgress();
            
            alert('บันทึกแผนสำเร็จ!');
        } else {
            throw new Error(result.message || 'ไม่สามารถบันทึกแผนได้');
        }
    } catch (error) {
        console.error('Error in saveDayPlan:', error);
        alert('เกิดข้อผิดพลาด: ' + error.message);
    } finally {
        // คืนค่าปุ่มให้เป็นปกติ
        saveBtn.disabled = false;
        saveBtn.innerHTML = originalText;
    }
}

function addRecipeToUI(mealType, recipe, index) {
    const container = document.getElementById(`meal-${mealType}`);
    if (!container) return;
    
    const li = document.createElement('li');
    li.className = 'list-group-item d-flex justify-content-between align-items-center small p-2';
    li.dataset.recipe = JSON.stringify(recipe);
    li.innerHTML = `
        <div>
            <span class="fw-bold">${recipe.name}</span>
            <br>
            <small class="text-muted">${Math.round(recipe.calories)} Kcal</small>
        </div>
        <button class="btn btn-sm btn-outline-danger py-0 px-1" onclick="removeFromMeal('${mealType}', this)">
            <i class="bi bi-x"></i>
        </button>
    `;
    container.appendChild(li);
    updateNutritionSummary();
}

function removeFromMeal(mealType, button) {
    button.closest('.list-group-item').remove();
    updateNutritionSummary();
}

// ============= UPDATE NUTRITION SUMMARY (แก้ไขให้รองรับสารอาหารทั้งหมด) =============
function updateNutritionSummary() {
    const totals = {
        calories: 0,
        protein: 0,
        carbs: 0,
        fat: 0,
        saturated_fat: 0,
        trans_fat: 0,
        cholesterol: 0,
        sodium: 0,
        sugar: 0,
        fiber: 0,
        vitamin_a: 0,
        vitamin_b: 0,
        vitamin_c: 0,
        vitamin_d: 0,
        vitamin_e: 0,
        vitamin_k: 0,
        calcium: 0,
        iron: 0,
        potassium: 0,
        magnesium: 0,
        omega_3: 0
    };
    
    const mealTypes = ['breakfast', 'brunch', 'lunch', 'afternoon_snack', 'dinner'];
    mealTypes.forEach(mealType => {
        const container = document.getElementById(`meal-${mealType}`);
        if (container) {
            const items = container.querySelectorAll('.list-group-item');
            items.forEach(item => {
                try {
                    const recipe = JSON.parse(item.dataset.recipe);
                    totals.calories += parseFloat(recipe.calories || 0);
                    totals.protein += parseFloat(recipe.protein || 0);
                    totals.carbs += parseFloat(recipe.carbs || 0);
                    totals.fat += parseFloat(recipe.fat || 0);
                    totals.saturated_fat += parseFloat(recipe.saturated_fat_g || 0);
                    totals.trans_fat += parseFloat(recipe.trans_fat_g || 0);
                    totals.cholesterol += parseFloat(recipe.cholesterol_mg || 0);
                    totals.sodium += parseFloat(recipe.sodium_mg || 0);
                    totals.sugar += parseFloat(recipe.sugar_g || 0);
                    totals.fiber += parseFloat(recipe.fiber_g || 0);
                    totals.vitamin_a += parseFloat(recipe.vitamin_a_ug || 0);
                    totals.vitamin_b += parseFloat(recipe.vitamin_b_mg || 0);
                    totals.vitamin_c += parseFloat(recipe.vitamin_c_mg || 0);
                    totals.vitamin_d += parseFloat(recipe.vitamin_d_ug || 0);
                    totals.vitamin_e += parseFloat(recipe.vitamin_e_mg || 0);
                    totals.vitamin_k += parseFloat(recipe.vitamin_k_ug || 0);
                    totals.calcium += parseFloat(recipe.calcium_mg || 0);
                    totals.iron += parseFloat(recipe.iron_mg || 0);
                    totals.potassium += parseFloat(recipe.potassium_mg || 0);
                    totals.magnesium += parseFloat(recipe.magnesium_mg || 0);
                    totals.omega_3 += parseFloat(recipe.omega_3_g || 0);
                } catch (e) {
                    console.error('Error parsing recipe:', e);
                }
            });
        }
    });
    
    // อัปเดตค่าแสดงผลทั้งหมด
    const updateElement = (id, value, decimals = 0) => {
        const element = document.getElementById(id);
        if (element) {
            element.textContent = decimals > 0 ? value.toFixed(decimals) : Math.round(value);
        }
    };
    
    // มาโครนิวเทรียนต์หลัก
    updateElement('summary-calories', totals.calories, 0);
    updateElement('summary-protein', totals.protein, 1);
    updateElement('summary-carbs', totals.carbs, 1);
    updateElement('summary-fat', totals.fat, 1);
    
    // ไขมันและคอเลสเตอรอล
    updateElement('summary-saturated_fat', totals.saturated_fat, 1);
    updateElement('summary-trans_fat', totals.trans_fat, 1);
    updateElement('summary-omega_3', totals.omega_3, 1);
    updateElement('summary-cholesterol', totals.cholesterol, 0);
    
    // เกลือและน้ำตาล
    updateElement('summary-sodium', totals.sodium, 0);
    updateElement('summary-sugar', totals.sugar, 1);
    updateElement('summary-fiber', totals.fiber, 1);
    
    // วิตามิน
    updateElement('summary-vitamin_a', totals.vitamin_a, 0);
    updateElement('summary-vitamin_b', totals.vitamin_b, 1);
    updateElement('summary-vitamin_c', totals.vitamin_c, 1);
    updateElement('summary-vitamin_d', totals.vitamin_d, 0);
    updateElement('summary-vitamin_e', totals.vitamin_e, 1);
    updateElement('summary-vitamin_k', totals.vitamin_k, 0);
    
    // แร่ธาตุ
    updateElement('summary-calcium', totals.calcium, 0);
    updateElement('summary-iron', totals.iron, 1);
    updateElement('summary-potassium', totals.potassium, 0);
    updateElement('summary-magnesium', totals.magnesium, 0);
}

// ============= RECIPE SEARCH =============
function setupSearchListeners() {
    let debounceTimeout;
    const searchAndFilter = () => {
        clearTimeout(debounceTimeout);
        debounceTimeout = setTimeout(() => {
            const query = document.getElementById('search-input')?.value || '';
            const category = document.getElementById('category-filter')?.value || '';
            const dietType = document.getElementById('diet-type-filter')?.value || '';
            const disease = document.getElementById('disease-filter')?.value || '';
            searchRecipes(query, category, dietType, disease);
        }, 300);
    };
    
    ['search-input', 'category-filter', 'diet-type-filter', 'disease-filter'].forEach(id => {
        const element = document.getElementById(id);
        if (element) {
            element.addEventListener('input', searchAndFilter);
        }
    });
}

async function searchRecipes(query, category, dietTypeId, diseaseId) {
    const container = document.getElementById('search-results');
    if (!container) return;
    
    container.innerHTML = '<div class="text-center p-3"><span class="spinner-border spinner-border-sm"></span></div>';
    
    const params = new URLSearchParams({
        search: query,
        category: category,
        diet_type_id: dietTypeId,
        disease_id: diseaseId
    });
    
    try {
        const response = await fetch(`api/recipe_api.php?${params.toString()}`);
        const result = await response.json();
        displaySearchResults(result.data || []);
    } catch (error) {
        container.innerHTML = '<p class="text-danger text-center">ไม่สามารถโหลดข้อมูลได้</p>';
    }
}

function displaySearchResults(recipes) {
    const container = document.getElementById('search-results');
    if (!container) return;
    
    if (!recipes || recipes.length === 0) {
        container.innerHTML = '<p class="text-muted text-center mt-3">ไม่พบเมนูที่ค้นหา</p>';
        return;
    }
    
    container.innerHTML = '';
    recipes.forEach(recipe => {
        const div = document.createElement('div');
        div.className = 'card mb-2 shadow-sm';
        
        // Escape quotes in recipe object for onclick
        const recipeJson = JSON.stringify(recipe).replace(/"/g, '&quot;');
        
        div.innerHTML = `
            <div class="card-body p-2 d-flex align-items-center">
                <img src="${recipe.image_url || 'https://via.placeholder.com/60'}" 
                     class="rounded me-2" style="width:60px;height:60px;object-fit:cover;" 
                     alt="${recipe.name}">
                <div class="flex-grow-1">
                    <h6 class="mb-1 small">${recipe.name}</h6>
                    <small class="text-muted">${Math.round(recipe.calories)} Kcal</small>
                    <div class="mt-2 btn-group btn-group-sm" role="group">
                        <button class="btn btn-outline-primary" onclick='addRecipeToMealFromSearch(${recipeJson}, "breakfast")' title="มื้อเช้า">
                            <i class="fas fa-coffee"></i>
                        </button>
                        <button class="btn btn-outline-success" onclick='addRecipeToMealFromSearch(${recipeJson}, "brunch")' title="มื้อสาย">
                            <i class="fa-solid fa-bread-slice"></i>
                        </button>
                        <button class="btn btn-outline-warning" onclick='addRecipeToMealFromSearch(${recipeJson}, "lunch")' title="มื้อเที่ยง">
                            <i class="fa-solid fa-burger"></i>
                        </button>
                        <button class="btn btn-outline-danger" onclick='addRecipeToMealFromSearch(${recipeJson}, "afternoon_snack")' title="มื้อบ่าย">
                            <i class="fa-solid fa-cookie-bite"></i>
                        </button>
                        <button class="btn btn-outline-info" onclick='addRecipeToMealFromSearch(${recipeJson}, "dinner")' title="มื้อเย็น">
                            <i class="fa-solid fa-utensils"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
        container.appendChild(div);
    });
}

function addRecipeToMealFromSearch(recipe, mealType) {
    const container = document.getElementById(`meal-${mealType}`);
    if (!container) return;
    
    const existingItems = container.querySelectorAll('.list-group-item');
    const index = existingItems.length;
    addRecipeToUI(mealType, recipe, index);
}

// ============= RANDOM MEAL GENERATION =============
async function randomizeAllMeals() {
    if (!confirm('คุณต้องการสุ่มเมนูทั้งหมดใช่หรือไม่? (จะลบเมนูเดิมทั้งหมด)')) return;
    
    const mealTypes = ['breakfast', 'brunch', 'lunch', 'afternoon_snack', 'dinner'];
    for (const mealType of mealTypes) {
        await randomizeMeal(mealType, true);
    }
}

async function randomizeMeal(mealType, skipConfirm = false) {
    if (!skipConfirm && !confirm(`คุณต้องการสุ่มเมนูสำหรับมื้อนี้ใช่หรือไม่?`)) return;
    
    try {
        const response = await fetch('api/get_random_recipe.php');
        const result = await response.json();
        
        if (result.success && result.recipe) {
            const container = document.getElementById(`meal-${mealType}`);
            if (container) {
                container.innerHTML = ''; // Clear existing
                addRecipeToUI(mealType, result.recipe, 0);
            }
        } else {
            alert('ไม่สามารถสุ่มเมนูได้');
        }
    } catch (error) {
        alert('เกิดข้อผิดพลาด: ' + error.message);
    }
}

// ============= COPY PLAN =============
function openCopyModal(sourceDayIndex) {
    const container = document.getElementById('copy-days-list');
    if (!container) return;
    
    container.innerHTML = '';
    
    for (let i = 0; i < TOTAL_DAYS; i++) {
        if (i === sourceDayIndex) continue;
        
        const div = document.createElement('div');
        div.className = 'form-check';
        div.innerHTML = `
            <input class="form-check-input" type="checkbox" value="${i}" id="copy-day-${i}">
            <label class="form-check-label" for="copy-day-${i}">
                วันที่ ${i + 1}
            </label>
        `;
        container.appendChild(div);
    }
    
    if (copyDayModal) {
        copyDayModal._element.dataset.sourceDay = sourceDayIndex;
        copyDayModal.show();
    }
}

async function confirmCopyPlan() {
    const modalEl = document.getElementById('copyDayModal');
    const sourceDayIndex = parseInt(modalEl.dataset.sourceDay);
    const checkboxes = document.querySelectorAll('#copy-days-list input:checked');
    const targetDays = Array.from(checkboxes).map(cb => parseInt(cb.value));
    
    if (targetDays.length === 0) {
        alert('กรุณาเลือกวันที่ต้องการคัดลอก');
        return;
    }
    
    const sourcePlan = planData[sourceDayIndex];
    if (!sourcePlan) {
        alert('ไม่พบข้อมูลแผนต้นทาง');
        return;
    }
    
    try {
        const response = await fetch('process/copy_day_plan.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                plan_id: PLAN_ID,
                source_day: sourceDayIndex,
                target_days: targetDays,
                plan: sourcePlan
            })
        });
        
        const result = await response.json();
        if (result.success) {
            // Update local data
            targetDays.forEach(dayIdx => {
                planData[dayIdx] = JSON.parse(JSON.stringify(sourcePlan));
            });
            if (copyDayModal) copyDayModal.hide();
            renderCalendar();
            updateProgress();
            alert(`คัดลอกแผนไปยัง ${targetDays.length} วันสำเร็จ!`);
        } else {
            alert('เกิดข้อผิดพลาด: ' + result.message);
        }
    } catch (error) {
        alert('เกิดข้อผิดพลาด: ' + error.message);
    }
}

// ============= DELETE DAY PLAN =============
async function deleteDayPlan(dayIndex) {
    if (!confirm(`คุณต้องการลบแผนวันที่ ${dayIndex + 1} ใช่หรือไม่?`)) return;
    
    try {
        const response = await fetch('process/delete_day_plan.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                plan_id: PLAN_ID,
                day_index: dayIndex
            })
        });
        
        const result = await response.json();
        if (result.success) {
            delete planData[dayIndex];
            renderCalendar();
            updateProgress();
            alert('ลบแผนสำเร็จ!');
        } else {
            alert('เกิดข้อผิดพลาด: ' + result.message);
        }
    } catch (error) {
        alert('เกิดข้อผิดพลาด: ' + error.message);
    }
}

// ============= PROFILE MANAGEMENT =============
function savePlanProfile() {
    // Check if plan is complete
    const completedDays = Object.keys(planData).filter(dayIdx => {
        const day = planData[dayIdx];
        return day && Object.values(day).some(meal => meal && meal.length > 0);
    }).length;
    
    if (completedDays === 0) {
        alert('กรุณาวางแผนอย่างน้อย 1 วันก่อนบันทึกเป็นโปรไฟล์');
        return;
    }
    
    if (saveProfileModal) {
        saveProfileModal.show();
    }
}

async function confirmSaveProfile() {
    const profileName = document.getElementById('profile-name')?.value.trim();
    if (!profileName) {
        alert('กรุณาระบุชื่อโปรไฟล์');
        return;
    }
    
    const description = document.getElementById('profile-description')?.value.trim() || '';
    const tags = Array.from(selectedTags);
    
    // แสดง loading
    const saveBtn = event.target;
    const originalText = saveBtn.innerHTML;
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>กำลังบันทึก...';
    
    try {
        const response = await fetch('process/finalize_plan_profile.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                plan_id: PLAN_ID,
                profile_name: profileName,
                description: description,
                tags: tags
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('บันทึกโปรไฟล์สำเร็จ!');
            if (saveProfileModal) saveProfileModal.hide();
            
            // Redirect ไปหน้า my_plans.php
            window.location.href = 'my_plans.php';
        } else {
            alert('เกิดข้อผิดพลาด: ' + result.message);
            saveBtn.disabled = false;
            saveBtn.innerHTML = originalText;
        }
    } catch (error) {
        console.error('Error:', error);
        alert('เกิดข้อผิดพลาด: ' + error.message);
        saveBtn.disabled = false;
        saveBtn.innerHTML = originalText;
    }
}

function addTag(tagName) {
    const cleanTag = tagName.trim();
    if (cleanTag && !selectedTags.has(cleanTag)) {
        selectedTags.add(cleanTag);
        renderTags();
    }
}

function removeTag(tagName) {
    selectedTags.delete(tagName);
    renderTags();
}

function renderTags() {
    const container = document.getElementById('selected-tags');
    if (!container) return;
    
    container.innerHTML = Array.from(selectedTags).map(tag => `
        <span class="badge bg-primary me-1">
            ${tag} 
            <i class="bi bi-x-circle" onclick="removeTag('${tag}')" style="cursor:pointer;"></i>
        </span>
    `).join('');
}
</script>

<style>
.day-card {
    border: 2px solid #e9ecef;
    border-radius: 10px;
    padding: 1rem;
    text-align: center;
    transition: all 0.3s ease;
    background-color: #fff;
    cursor: pointer;
}
.day-card.has-plan {
    border-color: #2FAAA8;
    background-color: #e7f5f4;
}
.day-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}
.nutrition-summary-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
}
.nutrition-stat-card {
    background-color: #f8f9fa;
    padding: 1rem;
    border-radius: 8px;
    text-align: center;
}
.nutrition-stat-card .label {
    font-size: 0.85rem;
    color: #6c757d;
    margin-bottom: 0.5rem;
}
.nutrition-stat-card .value {
    font-size: 1.5rem;
    font-weight: bold;
    color: #2FAAA8;
}
.tag-option {
    cursor: pointer;
    transition: all 0.2s ease;
}
.tag-option:hover {
    transform: scale(1.05);
    opacity: 0.8;
}
@media (max-width: 768px) {
    .nutrition-summary-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>

<?php require_once 'includes/footer.php'; ?>