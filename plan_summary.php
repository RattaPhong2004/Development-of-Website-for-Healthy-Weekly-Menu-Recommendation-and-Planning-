<?php
session_start();
require_once 'includes/db_connect.php';
$page_title = "สรุปแผนอาหารใหม่";
require_once 'includes/header.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    echo '<script>window.location.href = "login.php";</script>';
    exit();
}

$user_id = $_SESSION['user_id'];

// ดึงข้อมูลโปรไฟล์
$sql_profile = "SELECT * FROM user_profiles WHERE user_id = ?";
$stmt_profile = $conn->prepare($sql_profile);
$stmt_profile->bind_param("i", $user_id);
$stmt_profile->execute();
$profile = $stmt_profile->get_result()->fetch_assoc();
$stmt_profile->close();

// ดึงข้อมูลการประเมินล่าสุด
$assessment = $_SESSION['latest_assessment'] ?? null;

// ใช้ค่าที่ปรับแล้วจาก session
$recommended_calories = $_SESSION['adjusted_calories'] ?? $profile['target_calories'];
$bmr = $_SESSION['adjusted_bmr'] ?? $profile['bmr'];


// [IMPROVED] ข้อมูลเหมาะสำหรับ / ไม่เหมาะสำหรับ
$plan_adjustments = [];

// โหลดเหตุผลการปรับจาก session
if (isset($_SESSION['adjustment_reasons']) && !empty($_SESSION['adjustment_reasons'])) {
    $plan_adjustments = $_SESSION['adjustment_reasons'];
} else {
    // Fallback: สร้างข้อความเริ่มต้น
    $plan_adjustments[] = 'ระบบจะปรับแผนให้เหมาะสมกับคุณมากขึ้น';
}

// เพิ่มคำแนะนำเฉพาะตามเป้าหมาย
$goal_specific_advice = [];
if ($profile['goal'] === 'ลดน้ำหนัก') {
    $goal_specific_advice[] = 'เน้นโปรตีนสูง ลดคาร์โบไฮเดรตและไขมัน';
    $goal_specific_advice[] = 'เพิ่มผักใบเขียวและเนื้อปลา';
} elseif ($profile['goal'] === 'เพิ่มน้ำหนัก') {
    $goal_specific_advice[] = 'เน้นคาร์โบไฮเดรตเชิงซ้อน 50-60%';
    $goal_specific_advice[] = 'เพิ่มโปรตีน 1.5-2g ต่อ kg น้ำหนักตัว';
} else {
    $goal_specific_advice[] = 'รักษาสมดุลโภชนาการทั้ง 5 หมู่';
}



// กำหนดตามเป้าหมาย
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
    
    // [NEW] วิเคราะห์การปรับปรุง
    if (isset($_SESSION['adjustment_reasons']) && !empty($_SESSION['adjustment_reasons'])) {
        $plan_adjustments = $_SESSION['adjustment_reasons'];
    } else {
        // Fallback ถ้าไม่มีข้อมูล
        if ($assessment && $assessment['weight_change'] >= 0) {
            $plan_adjustments[] = 'ลดแคลอรี่ลง 200 kcal เพื่อเร่งการลดน้ำหนัก';
        }
        if ($assessment && $assessment['body_feeling'] === 'worse') {
            $plan_adjustments[] = 'เพิ่มแคลอรี่ 100 kcal เพื่อป้องกันความอ่อนล้า';
        }
    }
    if ($assessment && $assessment['body_feeling'] === 'worse') {
        $plan_adjustments[] = 'เพิ่มแคลอรี่ 100 kcal เพื่อป้องกันความอ่อนล้า';
    }
    
} elseif ($profile['goal'] === 'เพิ่มน้ำหนัก') {
    $suitable_for = [
        'ผู้ที่ต้องการเพิ่มมวลกล้ามเนื้อ',
        'นักกีฬาที่ต้องการพลังงานสูง',
        'ผู้ที่มีการเผาผลาญสูง'
    ];
    $not_suitable_for = [
        'ผู้ที่มีปัญหาเรื่องน้ำตาลในเลือด',
        'ผู้ที่มีโรคหัวใจ',
        'ผู้ที่มีปัญหาย่อยอาหาร'
    ];
    $recommendations = [
        'รับประทานอาหารบ่อยครั้ง 5-6 มื้อต่อวัน',
        'เน้นโปรตีนและคาร์โบไฮเดรตคุณภาพดี',
        'ออกกำลังกายเน้นสร้างกล้ามเนื้อ',
        'รับประทาน Snack ระหว่างมื้อ'
    ];
    
    // [NEW] วิเคราะห์การปรับปรุง
    
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



// ข้อควรระวังด้านสุขภาพ (ตาม Disease)
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
            case 'โรคไขมันในเลือดสูง':
                $health_precautions[] = 'หลีกเลี่ยงไขมันอิ่มตัวและไขมันทรานส์';
                $health_precautions[] = 'เพิ่มการรับประทานไขมันดีจากปลา ถั่ว';
                break;
            case 'ความดันโลหิตต่ำ':
                $health_precautions[] = 'ดื่มน้ำเพียงพอและรับประทานเกลือตามปกติ';
                break;
        }
    }
}

if (empty($health_precautions)) {
    $health_precautions[] = 'ไม่มีข้อจำกัดพิเศษ';
}

$conn->close();
?>

<style>
.summary-container {
    max-width: 900px;
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
    transition: transform 0.3s ease;
}

.summary-section:hover {
    transform: translateY(-5px);
}

.summary-section h4 {
    color: #2FC2A0;
    font-weight: 700;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 3px solid #B7D971;
}

.summary-section ul {
    list-style: none;
    padding-left: 0;
}

.summary-section ul li {
    padding: 10px 0;
    padding-left: 35px;
    position: relative;
    font-size: 1.05rem;
}

.summary-section ul li::before {
    content: "✓";
    position: absolute;
    left: 0;
    color: #2FC2A0;
    font-weight: bold;
    font-size: 1.3rem;
}

.summary-section.not-suitable ul li::before {
    content: "✗";
    color: #dc3545;
}

.weight-change-display {
    background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
    padding: 25px;
    border-radius: 15px;
    text-align: center;
    margin-bottom: 20px;
}

.weight-change-display .value {
    font-size: 2.8rem;
    font-weight: 700;
    color: #1976d2;
}

.weight-change-display .label {
    font-size: 1rem;
    color: #666;
    margin-top: 10px;
}

/* [NEW] สำหรับแสดงการปรับปรุงแผน */
.adjustment-card {
    background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
    border-left: 5px solid #ffc107;
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 20px;
}

.adjustment-card h5 {
    color: #856404;
    font-weight: 600;
    margin-bottom: 15px;
}

.adjustment-card ul li {
    color: #856404;
}

.adjustment-card ul li::before {
    content: "⚡";
    color: #ffc107;
}

.calories-comparison {
    display: flex;
    justify-content: space-around;
    align-items: center;
    background: #f8f9fa;
    padding: 20px;
    border-radius: 12px;
    margin-top: 15px;
}

.calorie-box {
    text-align: center;
    padding: 15px;
}

.calorie-box .number {
    font-size: 2rem;
    font-weight: 700;
    color: #2FC2A0;
}

.calorie-box .label {
    font-size: 0.9rem;
    color: #666;
    margin-top: 5px;
}

.arrow-icon {
    font-size: 2rem;
    color: #B7D971;
}

.btn-start-plan {
    background: linear-gradient(135deg, #2FC2A0 0%, #B7D971 100%);
    border: none;
    color: white;
    padding: 15px 40px;
    font-size: 1.2rem;
    font-weight: 600;
    border-radius: 50px;
    box-shadow: 0 8px 20px rgba(47, 170, 168, 0.3);
    transition: all 0.3s ease;
}

.btn-start-plan:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 30px rgba(47, 170, 168, 0.4);
    color: white;
}

/* Progress Indicator */
.progress-steps {
    display: flex;
    justify-content: center;
    margin-bottom: 30px;
}

.step {
    display: flex;
    align-items: center;
    margin: 0 10px;
}

.step-circle {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #e0e0e0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    color: #666;
}

.step-circle.active {
    background: #2FC2A0;
    color: white;
}

.step-line {
    width: 50px;
    height: 3px;
    background: #e0e0e0;
}

/* Responsive */
@media (max-width: 768px) {
    .summary-header {
        padding: 30px 20px;
    }
    
    .summary-section {
        padding: 20px;
    }
    
    .summary-section ul li {
        font-size: 0.95rem;
    }
    
    .calories-comparison {
        flex-direction: column;
        gap: 15px;
    }
    
    .arrow-icon {
        transform: rotate(90deg);
        margin: 10px 0;
    }
    
    .weight-change-display .value {
        font-size: 2.2rem;
    }
    
    .calorie-box .number {
        font-size: 1.5rem;
    }
}

/* Trophy Animation */
@keyframes trophy-bounce {
    0%, 100% { transform: translateY(0) rotate(0deg); }
    25% { transform: translateY(-20px) rotate(-10deg); }
    50% { transform: translateY(-10px) rotate(10deg); }
    75% { transform: translateY(-15px) rotate(-5deg); }
}

.trophy-animation i {
    display: inline-block;
    animation: trophy-bounce 2s ease-in-out infinite;
    filter: drop-shadow(0 5px 15px rgba(255, 215, 0, 0.5));
}

/* Improvement Card Pulse */
@keyframes pulse-glow {
    0%, 100% { box-shadow: 0 0 10px rgba(255, 193, 7, 0.3); }
    50% { box-shadow: 0 0 25px rgba(255, 193, 7, 0.6); }
}

.adjustment-card {
    animation: pulse-glow 2s ease-in-out infinite;
}

</style>

<div class="summary-container" style="padding-top: 80px;">
    
    <!-- Progress Steps -->
    <div class="progress-steps wow fadeInDown" data-wow-delay="0.1s">
        <div class="step">
            <div class="step-circle active">1</div>
        </div>
        <div class="step-line"></div>
        <div class="step">
            <div class="step-circle active">2</div>
        </div>
        <div class="step-line"></div>
        <div class="step">
            <div class="step-circle active">3</div>
        </div>
    </div>

    <div class="summary-header wow fadeInDown" data-wow-delay="0.2s">
        <div class="trophy-animation mb-3">
            <i class="bi bi-trophy-fill" style="font-size: 3rem;"></i>
        </div>
        <h1 class="mb-3">🎉 ยินดีด้วย!</h1>
        <p class="mb-0" style="font-size: 1.2rem;">เราได้วิเคราะห์ข้อมูลของคุณแล้ว</p>
        <p style="font-size: 1rem; opacity: 0.9;">และสร้างแผนอาหารที่เหมาะสมกับคุณมากยิ่งขึ้น</p>
    </div>

    <!-- [NEW] แสดงการเปลี่ยนแปลงน้ำหนัก -->
    <?php if ($assessment && isset($assessment['weight_change'])): ?>
    <div class="weight-change-display wow fadeInUp" data-wow-delay="0.3s">
        <div class="mb-2">
            <i class="bi bi-graph-<?php echo ($assessment['weight_change'] > 0) ? 'up' : 'down'; ?>-arrow" 
               style="font-size: 2rem; color: <?php echo ($assessment['weight_change'] > 0) ? '#e74c3c' : '#27ae60'; ?>;"></i>
        </div>
        <div class="value">
            <?php echo ($assessment['weight_change'] > 0 ? '+' : '') . number_format($assessment['weight_change'], 1); ?> kg
        </div>
        <div class="label">การเปลี่ยนแปลงน้ำหนัก</div>
        <div class="mt-3">
            <small>จาก <strong><?php echo number_format($assessment['old_weight'], 1); ?> kg</strong> 
            เป็น <strong><?php echo number_format($assessment['new_weight'], 1); ?> kg</strong></small>
        </div>
        
        <!-- แสดงความรู้สึกและพลังงาน -->
        <div class="mt-3 pt-3" style="border-top: 1px solid rgba(0,0,0,0.1);">
            <div class="row text-center">
                <div class="col-6">
                    <small class="text-muted d-block">ความรู้สึกร่างกาย</small>
                    <?php
                    $feeling_icon = [
                        'better' => '<i class="bi bi-emoji-smile-fill text-success"></i> ดีขึ้น',
                        'same' => '<i class="bi bi-emoji-neutral-fill text-secondary"></i> เหมือนเดิม',
                        'worse' => '<i class="bi bi-emoji-frown-fill text-danger"></i> แย่ลง'
                    ];
                    echo $feeling_icon[$assessment['body_feeling']] ?? 'ไม่ระบุ';
                    ?>
                </div>
                <div class="col-6">
                    <small class="text-muted d-block">ระดับพลังงาน</small>
                    <?php
                    $energy_icon = [
                        'high' => '<i class="bi bi-battery-full text-success"></i> สูง',
                        'medium' => '<i class="bi bi-battery-half text-warning"></i> ปานกลาง',
                        'low' => '<i class="bi bi-battery text-danger"></i> ต่ำ'
                    ];
                    echo $energy_icon[$assessment['energy_level']] ?? 'ไม่ระบุ';
                    ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

<!-- [IMPROVED] แสดงการปรับปรุงแผน -->
<?php if (!empty($plan_adjustments)): ?>
<div class="adjustment-card wow fadeInUp" data-wow-delay="0.4s">
    <div class="d-flex align-items-center mb-3">
        <i class="bi bi-lightning-charge-fill me-2" style="font-size: 1.5rem;"></i>
        <h5 class="mb-0">การปรับปรุงแผนของคุณ</h5>
    </div>
    <ul class="mb-3">
        <?php foreach ($plan_adjustments as $adjustment): ?>
            <li><?php echo $adjustment; ?></li>
        <?php endforeach; ?>
    </ul>
    
    <!-- คำแนะนำเพิ่มเติมตามเป้าหมาย -->
    <?php if (!empty($goal_specific_advice)): ?>
    <div class="alert alert-info mb-3" style="background-color: rgba(13, 110, 253, 0.1); border: none;">
        <strong><i class="bi bi-info-circle me-2"></i>คำแนะนำเพิ่มเติม:</strong>
        <ul class="mb-0 mt-2">
            <?php foreach ($goal_specific_advice as $advice): ?>
                <li><?php echo $advice; ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>
    
    <!-- เปรียบเทียบแคลอรี่เก่า-ใหม่ -->
    <?php if (isset($_SESSION['adjusted_calories']) && $profile['target_calories'] != $_SESSION['adjusted_calories']): ?>
    <div class="calories-comparison">
        <div class="calorie-box">
            <div class="number"><?php echo number_format($profile['target_calories']); ?></div>
            <div class="label">แคลอรี่เดิม</div>
        </div>
        <div class="arrow-icon">
            <?php 
            $diff = $_SESSION['adjusted_calories'] - $profile['target_calories'];
            if ($diff > 0): ?>
                <i class="bi bi-arrow-up-circle-fill text-success"></i>
                <small class="d-block text-muted">+<?php echo abs($diff); ?> kcal</small>
            <?php elseif ($diff < 0): ?>
                <i class="bi bi-arrow-down-circle-fill text-danger"></i>
                <small class="d-block text-muted">-<?php echo abs($diff); ?> kcal</small>
            <?php else: ?>
                <i class="bi bi-arrow-right-circle-fill"></i>
            <?php endif; ?>
        </div>
        <div class="calorie-box">
            <div class="number"><?php echo number_format($_SESSION['adjusted_calories']); ?></div>
            <div class="label">แคลอรี่ใหม่</div>
        </div>
    </div>
    <?php else: ?>
    <div class="alert alert-success mb-0" style="background-color: rgba(25, 135, 84, 0.1); border: none;">
        <i class="bi bi-check-circle-fill me-2"></i>แคลอรี่เหมาะสมแล้ว ไม่ต้องปรับ (<?php echo number_format($recommended_calories); ?> kcal/วัน)
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

    <!-- เหมาะสำหรับ -->
    <div class="summary-section wow fadeInUp" data-wow-delay="0.5s">
        <h4><i class="bi bi-check-circle-fill me-2"></i>เหมาะสำหรับ</h4>
        <ul>
            <?php foreach ($suitable_for as $item): ?>
                <li><?php echo $item; ?></li>
            <?php endforeach; ?>
        </ul>
    </div>

    <!-- ไม่เหมาะสำหรับ -->
    <div class="summary-section not-suitable wow fadeInUp" data-wow-delay="0.6s">
        <h4><i class="bi bi-x-circle-fill me-2 text-danger"></i>ไม่เหมาะสำหรับ</h4>
        <ul>
            <?php foreach ($not_suitable_for as $item): ?>
                <li><?php echo $item; ?></li>
            <?php endforeach; ?>
        </ul>
    </div>

    <!-- คำแนะนำ -->
    <div class="summary-section wow fadeInUp" data-wow-delay="0.7s">
        <h4><i class="bi bi-lightbulb-fill me-2 text-warning"></i>คำแนะนำที่เป็นประโยชน์</h4>
        <ul>
            <?php foreach ($recommendations as $item): ?>
                <li><?php echo $item; ?></li>
            <?php endforeach; ?>
        </ul>
    </div>

    <!-- ข้อควรระวัง -->
    <div class="summary-section wow fadeInUp" data-wow-delay="0.8s">
        <h4><i class="bi bi-exclamation-triangle-fill me-2 text-danger"></i>ข้อควรระวังเกี่ยวกับสุขภาพ</h4>
        <ul>
            <?php foreach ($health_precautions as $item): ?>
                <li><?php echo $item; ?></li>
            <?php endforeach; ?>
        </ul>
    </div>

    <!-- ปุ่มเริ่มแผน -->
<div class="text-center mt-5 mb-4 wow fadeInUp" data-wow-delay="0.9s">
    <div class="d-grid gap-2 d-md-flex justify-content-md-center">
        <button class="btn btn-success btn-lg" onclick="showPlanDetails()">
            <i class="bi bi-eye-fill me-2"></i>ดูรายละเอียดแผน
        </button>
        <button class="btn btn-start-plan btn-lg" onclick="openApplyPlanModal()">
            <i class="bi bi-calendar-check-fill me-2"></i>นำแผนไปใช้
        </button>
    </div>
    <div class="mt-3">
        <a href="dashboard.php" class="text-muted">
            <i class="bi bi-arrow-left me-1"></i>กลับไปหน้าหลัก
        </a>
    </div>
</div>

</div>

<!-- Modal สำหรับเลือกวันเริ่มแผน -->
<div class="modal fade" id="applyNewPlanModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="bi bi-calendar-check me-2"></i>เริ่มแผนใหม่
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
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
                    <button type="button" class="btn btn-primary btn-lg" id="confirm-apply-new-plan">
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

<!-- Modal แสดงรายละเอียดแผน -->
<div class="modal fade" id="planDetailsModal" tabindex="-1" aria-labelledby="planDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="planDetailsModalLabel">
                    <i class="bi bi-calendar3 me-2"></i>รายละเอียดแผนอาหาร 7 วัน
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="plan-details-content">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">กำลังโหลด...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
            </div>
        </div>
    </div>
</div>

<style>
.plan-preview-card {
    background: white;
    border-radius: 15px;
    padding: 20px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    transition: transform 0.3s ease;
}

.plan-preview-card:hover {
    transform: translateY(-5px);
}

.day-preview {
    border-left: 4px solid #2FC2A0;
    padding: 15px;
    margin-bottom: 15px;
    background: #f8f9fa;
    border-radius: 8px;
}

.meal-preview {
    display: flex;
    align-items: center;
    padding: 10px;
    margin: 5px 0;
    background: white;
    border-radius: 8px;
}

.meal-preview img {
    width: 60px;
    height: 60px;
    object-fit: cover;
    border-radius: 8px;
    margin-right: 15px;
}

.meal-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 10px;
    font-size: 1.2rem;
}

.icon-breakfast { background: #2FACAA; color: white; }
.icon-brunch { background: #B7D971; color: white; }
.icon-lunch { background: #FFB405; color: white; }
.icon-snack { background: #E3812B; color: white; }
.icon-dinner { background: #7E72DA; color: white; }
</style>


<?php
// ✅ [NEW] แจ้งเตือนว่าแผนถูกสร้างใหม่
if (isset($_GET['new_generated']) && $_GET['new_generated'] == '1'):
?>
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

<script>
// 🔹 ฟังก์ชันเปิด Modal แสดงรายละเอียดแผน
function showPlanDetails() {
    const urlParams = new URLSearchParams(window.location.search);
    const planId = urlParams.get('plan_id');
    
    console.log('🔍 Opening plan details for plan_id:', planId);
    
    // ✅ ตรวจสอบ plan_id ให้ชัดเจน
    if (!planId || planId === '' || planId === 'null' || planId === '0') {
        console.error('❌ Invalid plan_id:', planId);
        Swal.fire({
            icon: 'error',
            title: 'ไม่พบข้อมูลแผน',
            html: `
                <p>ไม่สามารถโหลดรายละเอียดแผนได้</p>
                <p class="text-muted small">plan_id: ${planId || 'ไม่ระบุ'}</p>
            `,
            confirmButtonColor: '#3085d6',
            confirmButtonText: 'ตกลง'
        });
        return;
    }
    
    const modalEl = document.getElementById('planDetailsModal');
    if (!modalEl) {
        console.error('❌ Modal element not found!');
        return;
    }
    
    const modal = new bootstrap.Modal(modalEl);
    const contentEl = document.getElementById('plan-details-content');
    
    // แสดง loading
    contentEl.innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">กำลังโหลด...</span>
            </div>
            <p class="mt-2">กำลังโหลดข้อมูลแผน...</p>
        </div>`;
    
    modal.show();
    
    // ✅ เพิ่ม error handling ที่ชัดเจน
    fetch(`process/get_plan_details.php?plan_id=${planId}&type=ai`)
        .then(response => {
            console.log('Response status:', response.status);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('✅ Plan data loaded:', data);
            
            if (data.success && data.plan) {
                renderPlanDetails(data.plan);
            } else {
                throw new Error(data.message || 'ไม่สามารถโหลดข้อมูลแผนได้');
            }
        })
        .catch(error => {
            console.error('❌ Error loading plan:', error);
            contentEl.innerHTML = `
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    เกิดข้อผิดพลาด: ${error.message}
                    <hr>
                    <small class="text-muted">plan_id: ${planId}</small>
                </div>`;
        });
}

// 🔹 ฟังก์ชัน Render รายละเอียดแผน
function renderPlanDetails(planData) {
    const container = document.getElementById('plan-details-content');
    
    if (!planData || Object.keys(planData).length === 0) {
        container.innerHTML = '<div class="alert alert-warning">ไม่พบข้อมูลแผน</div>';
        return;
    }
    
    let html = '';
    
    const mealIcons = {
        'มื้อเช้า': 'fa-coffee',
        'มื้อว่างเช้า': 'fa-bread-slice',
        'มื้อกลางวัน': 'fa-burger',
        'มื้อว่างบ่าย': 'fa-cookie-bite',
        'มื้อเย็น': 'fa-utensils'
    };
    
    const mealColors = {
        'มื้อเช้า': 'breakfast',
        'มื้อว่างเช้า': 'brunch',
        'มื้อกลางวัน': 'lunch',
        'มื้อว่างบ่าย': 'snack',
        'มื้อเย็น': 'dinner'
    };
    
    // เรียงลำดับวัน
    const sortedDays = Object.keys(planData).sort();
    
    sortedDays.forEach((dayKey, index) => {
        const dayData = planData[dayKey];
        
        html += `
            <div class="day-preview mb-4">
                <h5 class="mb-3">
                    <i class="bi bi-calendar-day text-primary me-2"></i>
                    ${dayKey}
                </h5>`;
        
        // แสดงแต่ละมื้อ
        Object.keys(dayData).forEach(mealKey => {
            if (dayData[mealKey]) {
                const recipes = Array.isArray(dayData[mealKey]) ? dayData[mealKey] : [dayData[mealKey]];
                const icon = mealIcons[mealKey] || 'fa-utensils';
                const colorClass = mealColors[mealKey] || 'lunch';
                
                html += `<h6 class="mt-3 mb-2"><i class="fas ${icon} me-2"></i>${mealKey}</h6>`;
                
                recipes.forEach(recipe => {
                    if (recipe && (recipe.recipe_name || recipe.name)) {
                        html += `
                            <div class="meal-preview">
                                <div class="meal-icon icon-${colorClass}">
                                    <i class="fas ${icon}"></i>
                                </div>
                                <img src="${recipe.image_url || 'https://placehold.co/60'}" 
                                     alt="${recipe.recipe_name || recipe.name}"
                                     onerror="this.src='https://placehold.co/60'">
                                <div class="flex-grow-1">
                                    <strong>${recipe.recipe_name || recipe.name}</strong><br>
                                    <small class="text-muted">${recipe.calories || 0} kcal</small>
                                </div>
                            </div>`;
                    }
                });
            }
        });
        
        html += `</div>`;
    });

    container.innerHTML = html;
}

// 🔹 ฟังก์ชันเปิด Modal เลือกวันเริ่มใช้แผน
function openApplyPlanModal() {
    const urlParams = new URLSearchParams(window.location.search);
    const planId = urlParams.get('plan_id');
    
    console.log('🔍 Opening apply modal for plan_id:', planId);
    
    if (!planId || planId === '' || planId === 'null') {
        console.error('❌ Invalid plan_id:', planId);
        Swal.fire({
            icon: 'error',
            title: 'ไม่พบข้อมูลแผน',
            text: 'กรุณาลองสร้างแผนใหม่อีกครั้ง',
            confirmButtonColor: '#3085d6'
        });
        return;
    }
    
    const modal = new bootstrap.Modal(document.getElementById('applyNewPlanModal'));
    
    // ตั้งค่าวันเริ่มต้นเป็นวันพรุ่งนี้
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    const startDateInput = document.getElementById('start-date-input');
    if (startDateInput) {
        startDateInput.value = tomorrow.toISOString().split('T')[0];
        startDateInput.min = new Date().toISOString().split('T')[0];
    }
    
    modal.show();
}

// ===== Event Listeners =====

document.addEventListener('DOMContentLoaded', function() {
    console.log('✅ plan_summary.php loaded');
    
    // ตรวจสอบว่ามี SweetAlert2 หรือไม่
    if (typeof Swal === 'undefined') {
        console.error('❌ SweetAlert2 not loaded!');
    }
    
    // ตรวจสอบ plan_id
    const urlParams = new URLSearchParams(window.location.search);
    const planId = urlParams.get('plan_id');
    const fromGenerate = urlParams.get('from_generate');
    const newGenerated = urlParams.get('new_generated');
    
    console.log('📊 URL Parameters:', {
        plan_id: planId,
        from_generate: fromGenerate,
        new_generated: newGenerated
    });
    
    // แสดง Success Message ถ้าเพิ่งสร้างแผน
    if (newGenerated === '1' && typeof Swal !== 'undefined') {
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
    }
    
    // ตั้งค่าวันเริ่มต้น
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    const startDateInput = document.getElementById('start-date-input');
    if (startDateInput) {
        startDateInput.value = tomorrow.toISOString().split('T')[0];
        startDateInput.min = new Date().toISOString().split('T')[0];
    }

    // Confirm Apply Plan
    const confirmBtn = document.getElementById('confirm-apply-new-plan');
    if (confirmBtn) {
        confirmBtn.addEventListener('click', async function() {
            const startDate = document.getElementById('start-date-input').value;
            
            if (!startDate) {
                Swal.fire({
                    icon: 'warning',
                    title: 'กรุณาเลือกวันเริ่มต้น',
                    confirmButtonColor: '#3085d6'
                });
                return;
            }
            
            if (!planId || planId === '' || planId === 'null') {
                Swal.fire({
                    icon: 'error',
                    title: 'ไม่พบข้อมูลแผน',
                    text: 'กรุณาลองสร้างแผนใหม่อีกครั้ง',
                    confirmButtonColor: '#3085d6'
                });
                return;
            }

            const btn = this;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>กำลังนำไปใช้...';

            try {
                const response = await fetch('process/apply_plan_profile.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        start_date: startDate,
                        plan_id: planId,
                        plan_type: 'ai'
                    })
                });

                const result = await response.json();
                
                console.log('Apply plan result:', result);

if (result.success) {
    // ลบ localStorage
    if (result.clear_storage_key) {
        const userId = <?php echo $user_id; ?>;
        const keysToRemove = [];
        
        Object.keys(localStorage).forEach(key => {
            if (key.startsWith(result.clear_storage_key)) {
                keysToRemove.push(key);
            }
        });
        
        keysToRemove.forEach(key => {
            localStorage.removeItem(key);
            console.log('🗑️ Cleared progress:', key);
        });
        
        localStorage.removeItem(`plan_completed_shown_${userId}`);
        console.log('🗑️ Cleared completion flag');
    }

    // ✅ [FIX] ตรวจสอบว่าต้อง redirect ไปสร้างแผนหรือไม่
    if (result.redirect_to_generate) {
        Swal.fire({
            icon: 'success',
            title: 'บันทึกข้อมูลสำเร็จ!',
            html: `
                <div class="mb-3">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
                <p class="mb-0">กำลังสร้างแผนอาหารใหม่ให้คุณ...</p>
            `,
            showConfirmButton: false,
            allowOutsideClick: false,
            allowEscapeKey: false,
            timer: 2000
        }).then(() => {
            // ✅ ใช้ URL ที่ server ส่งมา หรือ default
            const redirectUrl = result.redirect_url || 'process/generate_improved_plan.php';
            console.log('🔄 Redirecting to:', redirectUrl);
            window.location.href = redirectUrl;
        });
    } else {
        // กรณีไม่ต้องสร้างแผนใหม่ (ใช้แผนเดิม)
        Swal.fire({
            icon: 'success',
            title: 'สำเร็จ!',
            text: 'นำแผนไปใช้เรียบร้อยแล้ว',
            timer: 2000,
            showConfirmButton: false
        }).then(() => {
            window.location.href = 'dashboard.php?plan_activated=1';
        });
    }
} else {
                    throw new Error(result.message || 'เกิดข้อผิดพลาด');
                }
            } catch (error) {
                console.error('Apply plan error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'เกิดข้อผิดพลาด',
                    text: error.message,
                    confirmButtonColor: '#d33'
                });
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-check-circle me-2"></i>ยืนยันการนำไปใช้';
            }
        });
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>