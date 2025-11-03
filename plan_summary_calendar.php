<?php
session_start();
require_once 'includes/db_connect.php';
$page_title = "สรุปแผนอาหารใหม่";
require_once 'includes/header.php';

if (!isset($_SESSION['user_id'])) {
    echo '<script>window.location.href = "login.php";</script>';
    exit();
}

$user_id = $_SESSION['user_id'];
$plan_id = $_GET['plan_id'] ?? 0;
$plan_type = $_GET['plan_type'] ?? 'ai'; // เพิ่มการรับค่า plan_type

// ดึงข้อมูลโปรไฟล์
$sql_profile = "SELECT * FROM user_profiles WHERE user_id = ?";
$stmt_profile = $conn->prepare($sql_profile);
$stmt_profile->bind_param("i", $user_id);
$stmt_profile->execute();
$profile = $stmt_profile->get_result()->fetch_assoc();
$stmt_profile->close();

// ✅ [FIX] ปรับปรุงการดึงข้อมูลให้รองรับทั้ง 2 แบบ
if ($plan_type === 'ai') {
    $sql_plan = "SELECT plan_data, created_at FROM weekly_plans WHERE id = ? AND user_id = ?";
    $stmt_plan = $conn->prepare($sql_plan);
    $stmt_plan->bind_param("ii", $plan_id, $user_id);
} else {
    $sql_plan = "SELECT plan_data, created_at FROM plan_profiles WHERE id = ? AND user_id = ?";
    $stmt_plan = $conn->prepare($sql_plan);
    $stmt_plan->bind_param("ii", $plan_id, $user_id);
}

if (!$stmt_plan) {
    error_log("❌ SQL Prepare Error: " . $conn->error);
    $_SESSION['error_message'] = 'เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL';
    header("Location: dashboard.php");
    exit();
}

$stmt_plan->execute();
$plan_row = $stmt_plan->get_result()->fetch_assoc();
$stmt_plan->close();

// ✅ [FIX] เพิ่มการตรวจสอบว่ามีข้อมูลหรือไม่
if (!$plan_row) {
    error_log("❌ No plan found - plan_id: $plan_id, plan_type: $plan_type, user_id: $user_id");
    $_SESSION['error_message'] = 'ไม่พบข้อมูลแผนอาหาร กรุณาสร้างแผนใหม่อีกครั้ง';
    header("Location: dashboard.php");
    exit();
}

$weekly_plan = null;
$plan_start_date = null;

if ($plan_row) {
    $weekly_plan = json_decode($plan_row['plan_data'], true);
    $plan_start_date = new DateTime($plan_row['created_at']);
    
    // ✅ Debug: ตรวจสอบว่าข้อมูลถูกต้อง
    error_log("📊 Plan loaded: " . count($weekly_plan ?? []) . " days");
    error_log("📅 Plan start date: " . $plan_start_date->format('Y-m-d'));
}

// ✅ เพิ่มการตรวจสอบกรณีไม่มีข้อมูล
if (!$weekly_plan) {
    $_SESSION['error_message'] = 'ไม่พบข้อมูลแผนอาหาร กรุณาสร้างแผนใหม่';
    header("Location: weekly_plan_dashboard.php");
    exit();
}

// กำหนดข้อมูลตามเป้าหมาย
if ($profile['goal'] === 'ลดน้ำหนัก') {
    $suitable_for = [
        'ผู้ที่ต้องการลดน้ำหนักอย่างมาก',
        'ผู้ที่ต้องการปรับปรุงสุขภาพเมตาบอลิซึม',
        'ผู้ที่พร้อมสร้างนิสัยใหม่'
    ];
    $not_suitable_for = [
        'ผู้ที่มีความดันโลหิตต่ำ',
        'ผู้ที่กำลังตั้งครรภ์หรือให้นมบุตร',
        'ผู้ที่มี BMI ต่ำกว่า 18.5'
    ];
    $recommendations = [
        'ดื่มน้ำอย่างน้อย 8-10 แก้วต่อวัน',
        'ออกกำลังกายอย่างน้อย 30 นาทีต่อวัน',
        'หลีกเลี่ยงอาหารทอดและของหวาน',
        'นอนหลับให้เพียงพอ 7-8 ชั่วโมงต่อคืน'
    ];
} elseif ($profile['goal'] === 'เพิ่มน้ำหนัก') {
    $suitable_for = [
        'ผู้ที่ต้องการเพิ่มมวลกล้ามเนื้อ',
        'นักกีฬาที่ต้องการพลังงานสูง',
        'ผู้ที่มีการเผาผลาญสูง'
    ];
    $not_suitable_for = [
        'ผู้ที่มีปัญหาเรื่องน้ำตาลในเลือด',
        'ผู้ที่มีโรคหัวใจ',
        'ผู้ที่มีปัญหายากอาหาร'
    ];
    $recommendations = [
        'รับประทานอาหารบ่อยครั้ง 5-6 มื้อต่อวัน',
        'เน้นโปรตีนและคาร์โบไฮเดรตคุณภาพดี',
        'ออกกำลังกายเน้นสร้างกล้ามเนื้อ',
        'รับประทาน Snack ระหว่างมื้อ'
    ];
} else {
    $suitable_for = [
        'ผู้ที่ต้องการรักษาน้ำหนักปัจจุบัน',
        'ผู้ที่ต้องการปรับปรุงคุณภาพการกินอาหาร',
        'ผู้ที่มีน้ำหนักเหมาะสมแล้ว'
    ];
    $not_suitable_for = [
        'ผู้ที่ต้องการลดหรือเพิ่มน้ำหนักอย่างมาก',
        'ผู้ที่มีเป้าหมายพิเศษ (นักกีฬา)'
    ];
    $recommendations = [
        'รับประทานอาหารครบ 5 หมู่',
        'ออกกำลังกายสม่ำเสมอ',
        'ดื่มน้ำเพียงพอ'
    ];
}

$health_precautions = [];
if (!empty($profile['disease']) && $profile['disease'] !== 'ไม่มี') {
    $diseases = explode(',', $profile['disease']);
    foreach ($diseases as $disease) {
        $disease = trim($disease);
        switch ($disease) {
            case 'โรคเบาหวาน':
                $health_precautions[] = 'ควรตรวจระดับน้ำตาลในเลือดสม่ำเสมอ';
                $health_precautions[] = 'หลีกเลี่ยงอาหารที่มีน้ำตาลสูง';
                break;
            case 'โรคความดันโลหิตสูง':
                $health_precautions[] = 'ควรจำกัดโซเดียมไม่เกิน 2000mg/วัน';
                $health_precautions[] = 'เพิ่มการรับประทานผักและผลไม้';
                break;
            case 'โรคไต':
                $health_precautions[] = 'ควรควบคุมโปรตีนและโซเดียม';
                $health_precautions[] = 'ดื่มน้ำตามคำแนะนำของแพทย์';
                break;
        }
    }
}

if (empty($health_precautions)) {
    $health_precautions[] = 'ไม่มีข้อจำกัดพิเศษ';
}

// สร้างข้อมูลปฏิทิน
$today = new DateTime();
$year = $today->format('Y');
$month = $today->format('m');
$month_start = new DateTime("$year-$month-01");
$month_end = (clone $month_start)->modify('last day of this month');

$start_week_day = $month_start->format('w');
$end_week_day = $month_end->format('w');

$calendar_start = (clone $month_start)->modify("-$start_week_day days");
$calendar_end = (clone $month_end)->modify("+" . (6 - $end_week_day) . " days");

$interval = new DateInterval('P1D');
$period = new DatePeriod($calendar_start, $interval, $calendar_end->modify('+1 day'));

$conn->close();
?>

<style>
/* --- General Styles --- */
.summary-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 30px 20px;
}
.summary-header {
    background: linear-gradient(135deg, #2FC2A0 0%, #B7D971 100%);
    color: white;
    padding: 40px 30px;
    border-radius: 20px;
    text-align: center;
    margin-bottom: 30px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}
.summary-section {
    background: white;
    border-radius: 15px;
    padding: 25px;
    margin-bottom: 20px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.08);
}
.summary-section h4 {
    color: #2FC2A0;
    font-weight: 700;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 3px solid #B7D971;
}
.summary-section ul { list-style: none; padding-left: 0; }
.summary-section ul li { padding: 10px 0; padding-left: 35px; position: relative; }
.summary-section ul li::before {
    content: "✓"; position: absolute; left: 0; color: #2FC2A0;
    font-weight: bold; font-size: 1.3rem;
}
.summary-section.not-suitable ul li::before { content: "✗"; color: #dc3545; }
.calendar-preview {
    background: white; border-radius: 15px; padding: 20px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.08);
}
.plan-day-gradient {
    background: linear-gradient(135deg, #bcffc3ff, #b8ff95ff) !important;
}

.btn-gradient2 {
    background-image: linear-gradient(135deg, #2FC2A0 0%, #B7D971 100%);
    border: none;
    color: white;
    border-radius: 50px; /* ปรับความโค้ง (เช่น 8px, 20px หรือ 50px สำหรับแคปซูล) */
    padding: 10px 24px;  /* เพิ่มความสูงและระยะขอบให้สวย */
    font-weight: 500;
    transition: transform 0.2s ease-in-out, box-shadow 0.2s ease;
    display: inline-block;
}

btn-gradient3 {
    background-image: linear-gradient(135deg, #2FC2A0 0%, #B7D971 100%);
}

.th-sun { background-color: #ffebee; color: #c62828; }
.th-mon { background-color: #fff8e1; color: #f57f17; }
.th-tue { background-color: #fce4ec; color: #c2185b; }
.th-wed { background-color: #e8f5e9; color: #388e3c; }
.th-thu { background-color: #fff3e0; color: #e65100; }
.th-fri { background-color: #e3f2fd; color: #1976d2; }
.th-sat { background-color: #f3e5f5; color: #7b1fa2; }
@keyframes trophy-bounce { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-10px); } }
.trophy-animation i { animation: trophy-bounce 1s ease-in-out infinite; }

/* ==========================================================
    ✅ DESKTOP Calendar Styles (จอคอมพิวเตอร์)
   ========================================================== */
.table-bordered {
    width: 100%;
    table-layout: fixed;
}
.table-bordered th, .table-bordered td {
    vertical-align: middle;
    text-align: center;
    height: 90px;
    padding: 5px !important;
}
.table-bordered td > div {
    display: flex;
    flex-direction: column;
    justify-content: flex-start;
    align-items: center;
    height: 100%;
}
.table-bordered td .small {
    margin-top: 8px;
}

/* ⭐⭐⭐ [ปรับแก้] สไตล์ปุ่มและไอคอนสำหรับ Desktop ⭐⭐⭐ */
.table-bordered td .btn {
    border: none; /* เอาเส้นขอบออก */
    background-color: #ffffff; /* สีพื้นหลังเป็นสีขาวทึบ */
    color: #26a69a; /* สีของไอคอน (ตา) */
    width: 32px; /* กำหนดความกว้าง */
    height: 32px; /* กำหนดความสูง */
    border-radius: 40%; /* ทำให้เป็นวงกลม */
    margin-top: 8px;
    padding: 0; /* เอา padding เดิมออก */
    display: inline-flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1); /* เพิ่มเงาจางๆ */
    transition: all 0.2s ease-in-out; /* เพิ่ม animation ตอน hover */
}
.table-bordered td .btn:hover {
    background-color: #15b54bff; /* เปลี่ยนสีพื้นหลังตอนเอาเมาส์ชี้ */
    color: #ffffff; /* เปลี่ยนสีไอคอนเป็นสีขาว */
    transform: translateY(-2px); /* ทำให้ปุ่มลอยขึ้นเล็กน้อย */
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}
.table-bordered td .btn i {
    font-size: 1rem; /* ปรับขนาดไอคอนให้พอดี */
    vertical-align: middle;
}
/* ⭐⭐⭐ สิ้นสุดส่วนปรับแก้ Desktop ⭐⭐⭐ */

/* ==========================================================
    ✅ MOBILE Calendar Styles (จอมือถือ)
   ========================================================== */
@media (max-width: 768px) {
    .summary-container { padding: 20px 5px; }
    .summary-header { padding: 30px 15px; }
    .summary-header h1 { font-size: 1.6rem; }
    .calendar-preview { padding: 5px; }

    .table-bordered th, .table-bordered td {
        height: 70px; /* ปรับความสูงเซลล์ให้เหมาะกับปุ่มใหม่ */
        padding: 2px !important;
        font-size: 0.75rem;
    }
    .table-bordered td > div {
        justify-content: center;
    }
    .table-bordered td div strong {
        font-size: 0.8rem;
    }
    .table-bordered td .small {
        font-size: 0.9rem;
        margin-top: 2px;
    }
    
    /* ⭐⭐⭐ [ปรับแก้] สไตล์ปุ่มและไอคอนสำหรับ Mobile ⭐⭐⭐ */
    .table-bordered td .btn {
        border: none;
        background-color: #f8fffbff;
        color: #15b54bff;
        width: 10px; /* ขนาดเล็กลงสำหรับมือถือ */
        height: 10px;
        border-radius: 40%;
        margin-top: 4px;
        padding: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        /* ไม่ต้องมี hover effect ในมือถือ */
    }
    .table-bordered td .btn i {
        font-size: 0.95rem; /* ขนาดไอคอนสำหรับมือถือ */
        vertical-align: middle;
    }
    /* ⭐⭐⭐ สิ้นสุดส่วนปรับแก้ Mobile ⭐⭐⭐ */
}
</style>

<div class="summary-container" style="padding-top: 80px;">
    
    <div class="summary-header wow fadeInDown">
        <div class="trophy-animation mb-3">
            <i class="bi bi-trophy-fill" style="font-size: 3rem;"></i>
        </div>
        <h1 class="mb-3">🎉 แผนอาหาร 7 วันพร้อมแล้ว!</h1>
        <p class="mb-0" style="font-size: 1.2rem;">เราได้สร้างแผนอาหารที่เหมาะกับคุณมากที่สุด</p>
    </div>

    <!-- เหมาะสำหรับ -->
    <div class="summary-section wow fadeInUp" data-wow-delay="0.2s">
        <h4><i class="bi bi-check-circle-fill me-2"></i>เหมาะสำหรับ</h4>
        <ul>
            <?php foreach ($suitable_for as $item): ?>
                <li><?php echo $item; ?></li>
            <?php endforeach; ?>
        </ul>
    </div>

    <!-- ไม่เหมาะสำหรับ -->
    <div class="summary-section not-suitable wow fadeInUp" data-wow-delay="0.3s">
        <h4><i class="bi bi-x-circle-fill me-2 text-danger"></i>ไม่เหมาะสำหรับ</h4>
        <ul>
            <?php foreach ($not_suitable_for as $item): ?>
                <li><?php echo $item; ?></li>
            <?php endforeach; ?>
        </ul>
    </div>

    <!-- คำแนะนำ -->
    <div class="summary-section wow fadeInUp" data-wow-delay="0.4s">
        <h4><i class="bi bi-lightbulb-fill me-2 text-warning"></i>คำแนะนำที่เป็นประโยชน์</h4>
        <ul>
            <?php foreach ($recommendations as $item): ?>
                <li><?php echo $item; ?></li>
            <?php endforeach; ?>
        </ul>
    </div>

    <!-- ข้อควรระวัง -->
    <div class="summary-section wow fadeInUp" data-wow-delay="0.5s">
        <h4><i class="bi bi-exclamation-triangle-fill me-2 text-danger"></i>ข้อควรระวังเกี่ยวกับสุขภาพ</h4>
        <ul>
            <?php foreach ($health_precautions as $item): ?>
                <li><?php echo $item; ?></li>
            <?php endforeach; ?>
        </ul>
    </div>

    <!-- ปฏิทินแผนอาหาร -->
    <div class="calendar-preview wow fadeInUp" data-wow-delay="0.6s">
        <h4 class="text-center mb-4">
            <i class="bi bi-calendar3 me-2 text-primary"></i>
            ปฏิทินแผนอาหาร - <?php echo $month_start->format('F Y'); ?>
        </h4>
        
        <?php if ($weekly_plan): ?>
            <div class="table-responsive">
                <table class="table table-bordered text-center align-middle">
                    <thead>
                        <tr>
                            <th class="th-sun">อา</th>
                            <th class="th-mon">จ</th>
                            <th class="th-tue">อ</th>
                            <th class="th-wed">พ</th>
                            <th class="th-thu">พฤ</th>
                            <th class="th-fri">ศ</th>
                            <th class="th-sat">ส</th>
                        </tr>
                    </thead>
                   <tbody>
    <?php
    $day_count = 0;
    foreach ($period as $date) {
        if ($day_count % 7 === 0) echo '<tr>';

        $date_str_ymd = $date->format('Y-m-d');
        $display_day = $date->format('j');
        $is_current_month = $date->format('m') === $month;

        $td_classes = [];
        if (!$is_current_month) $td_classes[] = 'text-muted bg-light';

        $has_plan_for_this_date = false;
        $day_offset = 0;
        if (is_array($weekly_plan)) {
            foreach ($weekly_plan as $day_key => $meals) {
                $current_plan_date = clone $plan_start_date;
                $current_plan_date->modify("+$day_offset days");
                if ($current_plan_date->format('Y-m-d') === $date_str_ymd) {
                    $has_plan_for_this_date = true;
                    break;
                }
                $day_offset++;
            }
        }

        if ($has_plan_for_this_date) {
            $td_classes[] = 'plan-day-gradient';
        }
        
        echo '<td class="' . implode(' ', $td_classes) . '">';
        // โค้ดส่วนนี้จะถูกควบคุมด้วย Flexbox ทั้งหมด
        echo '<div><strong>' . $display_day . '</strong>';

        if ($has_plan_for_this_date) {
            echo '<button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modal-' . $date->format('Ymd') . '"><i class="bi bi-eye-fill"></i></button>';
        } else {
            if ($is_current_month) {
                echo '<span class="text-muted small">-</span>';
            }
        }

        echo '</div></td>';

        if ($day_count % 7 === 6) echo '</tr>';
        $day_count++;
    }
    ?>
</tbody>
                </table>
            </div>

            <?php
            $offset = 0;
            foreach ($weekly_plan as $day_key => $daily_plan):
                $date = clone $plan_start_date;
                $date->modify("+$offset days");
                $date_str = $date->format('Ymd');
                $formatted_date = $date->format('d/m/Y');
                $offset++;
            ?>
                <div class="modal fade" id="modal-<?php echo $date_str; ?>" tabindex="-1" aria-labelledby="label-<?php echo $date_str; ?>" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-scrollable">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 id="label-<?php echo $date_str; ?>" class="modal-title">
                                    <i class="bi bi-journal-text me-2 text-primary"></i> แผนอาหารวันที่ <?php echo $formatted_date; ?> (<?php echo htmlspecialchars($day_key); ?>)
                                </h5>
                                <button class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body bg-light">
                                <?php if (is_array($daily_plan) && !empty($daily_plan)): ?>
                                    <div class="row g-3">
                                        <?php foreach ($daily_plan as $meal_name => $recipe_details): ?>
                                            <?php if (is_array($recipe_details) && isset($recipe_details['recipe_name'])): ?>
                                                <div class="col-md-6">
                                                    <div class="card h-100 shadow-sm">
                                                        <div class="row g-0 align-items-center">
                                                            <div class="col-4">
                                                                <?php 
                                                                $image_url = !empty($recipe_details['image_url']) ? htmlspecialchars($recipe_details['image_url']) : 'https://via.placeholder.com/150'; 
                                                                ?>
                                                                <img src="<?php echo $image_url; ?>" class="img-fluid rounded-start recipe-image" style="width:100%; height:150px; object-fit:cover;" alt="<?php echo htmlspecialchars($recipe_details['recipe_name']); ?>">
                                                            </div>
                                                            <div class="col-8">
                                                                <div class="card-body">
                                                                    <h6 class="card-title text-primary"><?php echo htmlspecialchars($meal_name); ?></h6>
                                                                    <p class="card-text mb-1 recipe-name"><?php echo htmlspecialchars($recipe_details['recipe_name']); ?></p>
                                                                    <p class="card-text">
                                                                        <small class="text-muted recipe-calories"><?php echo htmlspecialchars($recipe_details['calories']); ?> Kcal</small>
                                                                    </p>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="text-center text-muted">ไม่พบข้อมูลเมนูสำหรับวันนี้</p>
                                <?php endif; ?>
                            </div>
                            <div class="modal-footer">
                                <button class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="alert alert-warning">ไม่พบข้อมูลแผนอาหาร</div>
        <?php endif; ?>
    </div>

    <!-- ปุ่มนำไปใช้ -->
    <div class="text-center mt-5 mb-4 wow fadeInUp" data-wow-delay="0.7s">
        <button class="btn btn-gradient2 btn-lg px-5 py-3" onclick="openApplyPlanModal()">
            <i class="bi bi-calendar-check-fill me-2"></i>นำแผนไปใช้
        </button>
        <div class="mt-3">
            <a href="dashboard.php" class="text-muted">
                <i class="bi bi-arrow-left me-1"></i>กลับไปหน้าหลัก
            </a>
        </div>
    </div>
</div>

<!-- Modal เลือกวันเริ่มแผน -->
<div class="modal fade" id="applyPlanModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header btn-gradient3 text-white">
                <h5 class=" modal-title">
                    <i class="bi bi-calendar-check me-2"></i>เริ่มแผนใหม่
                </h5>
                <button type="button" class="btn-close btn-close-black" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong>หมายเหตุ:</strong> แผนนี้จะแทนที่แผนปัจจุบันของคุณ
                </div>
                
                <div class="mb-3">
                    <label for="start-date-input" class="form-label fw-bold">
                        เลือกวันเริ่มต้น
                    </label>
                    <input type="date" class="form-control form-control-lg" id="start-date-input" required>
                </div>

                <div class="d-grid gap-2">
                    <button type="button" class="btn btn-gradient2 btn-lg" id="confirm-apply-plan">
                        <i class="bi bi-check-circle me-2"></i>ยืนยันการนำแผนไปใช้
                    </button>
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        ยกเลิก
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (isset($_GET['new_generated']) && $_GET['new_generated'] == '1'): ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    Swal.fire({
        icon: 'success',
        title: 'สร้างแผนใหม่สำเร็จ!',
        html: `
            <p class="mb-2">ระบบได้สร้างแผนอาหารใหม่ให้คุณแล้ว</p>
            <p class="mb-0 text-muted">กรุณาดูรายละเอียดและกด "นำแผนไปใช้" เพื่อเริ่มต้น</p>
        `,
        confirmButtonText: 'เข้าใจแล้ว',
        confirmButtonColor: '#2FC2A0'
    });
});
</script>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function openApplyPlanModal() {
    const planId = <?php echo $plan_id; ?>;
    
    if (!planId || planId === 0) {
        Swal.fire({
            icon: 'error',
            title: 'ไม่พบข้อมูลแผน',
            text: 'กรุณาลองสร้างแผนใหม่อีกครั้ง',
            confirmButtonColor: '#3085d6'
        });
        return;
    }
    
    const modal = new bootstrap.Modal(document.getElementById('applyPlanModal'));
    
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    const startDateInput = document.getElementById('start-date-input');
    if (startDateInput) {
        startDateInput.value = tomorrow.toISOString().split('T')[0];
        startDateInput.min = new Date().toISOString().split('T')[0];
    }
    
    modal.show();
}

document.addEventListener('DOMContentLoaded', function() {
    const confirmBtn = document.getElementById('confirm-apply-plan');
    if (confirmBtn) {
        confirmBtn.addEventListener('click', function() { // ไม่ต้องใช้ async แล้ว
            const startDate = document.getElementById('start-date-input').value;
            const planId = <?php echo $plan_id; ?>;
            
            if (!startDate) {
                Swal.fire({
                    icon: 'warning',
                    title: 'กรุณาเลือกวันเริ่มต้น',
                    confirmButtonColor: '#3085d6'
                });
                return;
            }

            const btn = this;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>กําลังนําไปใช้...';

            // ส่งข้อมูลไปเบื้องหลัง โดยไม่ต้องรอคําตอบ (Fire and forget)
            fetch('process/apply_plan_profile.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    start_date: startDate,
                    plan_id: planId,
                    plan_type: 'regular'
                })
            });

            // แสดงอนิเมชันว่าสําเร็จทันที
            Swal.fire({
                icon: 'success',
                title: 'สําเร็จ!',
                text: 'นําแผนไปใช้เรียบร้อยแล้ว',
                timer: 1500, // ลดเวลาลงเล็กน้อยเพื่อให้เร็วขึ้น
                showConfirmButton: false
            }).then(() => {
                // จากนั้นส่งไปหน้า Dashboard
                window.location.href = 'dashboard.php';
            });
        });
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>