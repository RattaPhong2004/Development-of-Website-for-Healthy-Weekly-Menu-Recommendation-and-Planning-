<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// --- ส่วน PHP ด้านบนสุดของไฟล์ ---
session_start();

require_once 'includes/db_connect.php'; // เรียกใช้ db connect หลัง header

// Global error handler เพื่อป้องกัน fatal error
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("PHP Error [$errno]: $errstr in $errfile on line $errline");
    
    // ถ้าเป็น fatal error ให้ redirect ไป error page
    if (in_array($errno, [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
        $_SESSION['error_message'] = 'เกิดข้อผิดพลาดในระบบ กรุณาลองใหม่อีกครั้ง';
        header('Location: dashboard.php?error=1');
        exit();
    }
    
    return false; // ให้ PHP error handler เดิมทำงานต่อ
});


require_once 'includes/functions.php';

// 1. กำหนด Title ของหน้านี้ (จะถูกนำไปใช้ใน header.php)
$page_title = "แดชบอร์ด  - FitMealWeek"; 

// 2. เรียกใช้ Header (ซึ่งจะรวม <head> และ <body> ให้เรา)
require_once 'includes/header.php'; 
// [ADD] Font Awesome CDN for new icons
echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />';

// [FIX] เพิ่ม SweetAlert2 สำหรับ submitHealthUpdate
echo '<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>';

// [เพิ่ม] ฟังก์ชันสำหรับแปลง Key มื้ออาหารจากอังกฤษเป็นไทย
    function map_meal_keys_to_thai($plan_array) {
        $map = [
            'breakfast' => 'มื้อเช้า',
            'brunch' => 'มื้อว่างเช้า',
            'lunch' => 'มื้อกลางวัน',
            'afternoon_snack' => 'มื้อว่างบ่าย',
            'dinner' => 'มื้อเย็น'
        ];
        
        $new_plan = [];
        
        if (!is_array($plan_array)) {
            return $new_plan;
        }
        
        foreach ($plan_array as $key => $value) {
            // [FIX] จัดการกรณี nested 'plan' key
            if ($key === 'plan' && is_array($value)) {
                // ถ้าเจอ key 'plan' ให้ recursive เข้าไปแปลงข้างใน
                return map_meal_keys_to_thai($value);
            }
            
            // [FIX] จัดการกรณี key เป็น date (2025-07-14)
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $key) && is_array($value)) {
                // ถ้า key เป็น date format ให้ข้าม (ไม่แปล)
                if (isset($value['plan'])) {
                    // แต่ถ้าข้างในมี 'plan' ให้แปลงข้างใน
                    $new_plan[$key] = map_meal_keys_to_thai($value['plan']);
                } else {
                    $new_plan[$key] = map_meal_keys_to_thai($value);
                }
                continue;
            }
            
            // แปลง key ตามปกติ
            if (isset($map[$key])) {
                $new_plan[$map[$key]] = $value;
            } else {
                // เก็บ key ที่ไม่ได้อยู่ใน map (เช่น totals, plan_id)
                $new_plan[$key] = $value;
            }
        }
        
        return $new_plan;
    }


// ตรวจสอบการล็อกอิน (ย้ายมาไว้หลัง header เพื่อให้โครงสร้างชัดเจน)
if (!isset($_SESSION['user_id'])) {
    // ใช้ javascript redirect ถ้า header ถูกส่งไปแล้ว
    echo '<script>window.location.href = "login.php";</script>';
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// --- SECTION 1: ดึงข้อมูลโปรไฟล์ผู้ใช้ ---
$profile = null;
$sql_profile = "SELECT * FROM user_profiles WHERE user_id = ?";
$stmt_profile = $conn->prepare($sql_profile);
$stmt_profile->bind_param("i", $user_id);
if ($stmt_profile->execute()) {
    $result_profile = $stmt_profile->get_result();
    $profile = $result_profile->fetch_assoc();
}
$stmt_profile->close();

// คำนวณ TDEE โดยใช้สูตร Mifflin-St Jeor (แม่นยำกว่า)
$tdee = 0;
$bmr = 0;
if ($profile) {
    $weight = $profile['weight'] ?? 0;
    $height = $profile['height'] ?? 0;
    $age = $profile['age'] ?? 0;
    $gender = $profile['gender'] ?? 'male';
    $activity_level = $profile['activity_level'] ?? 1.2;
    
    // คำนวณ BMR ด้วยสูตร Mifflin-St Jeor
    if ($gender === 'male') {
        $bmr = (10 * $weight) + (6.25 * $height) - (5 * $age) + 5;
    } else {
        $bmr = (10 * $weight) + (6.25 * $height) - (5 * $age) - 161;
    }
    
    // คำนวณ TDEE พื้นฐาน
    $tdee = $bmr * floatval($activity_level);
    
    // ปรับ TDEE ตามเป้าหมาย
    if (isset($profile['goal'])) {
        switch ($profile['goal']) {
            case 'ลดน้ำหนัก':
                $tdee -= 500; // ลด 500 แคลอรี่เพื่อลดน้ำหนักประมาณ 0.5 kg/สัปดาห์
                break;
            case 'เพิ่มน้ำหนัก':
                $tdee += 500; // เพิ่ม 500 แคลอรี่เพื่อเพิ่มน้ำหนักประมาณ 0.5 kg/สัปดาห์
                break;
            case 'รักษาน้ำหนัก':
            default:
                // ไม่ปรับแก้
                break;
        }
    }
}

// --- [REVISED] SECTION 2: ดึงข้อมูลแผนล่าสุดที่กำลังใช้งาน (Active Plan) ---
$active_plan_sql = "
    SELECT 
        pp.plan_date, 
        pp.is_completed,
        pp.plan_id,
        CASE
            WHEN dp.id IS NOT NULL THEN 'daily'
            WHEN wp.id IS NOT NULL THEN 'ai'
            WHEN p.id IS NOT NULL THEN 'custom'
            ELSE 'unknown'
        END as plan_type,
        COALESCE(dp.plan_data, wp.plan_data, p.plan_data) as plan_data,
        p.profile_name,
        wp.created_at as ai_plan_created_at
    FROM 
        plan_progress pp
    LEFT JOIN 
        daily_plans dp ON pp.plan_id = dp.id AND pp.plan_date = dp.plan_date
    LEFT JOIN 
        weekly_plans wp ON pp.plan_id = wp.id AND NOT EXISTS (
            SELECT 1 FROM daily_plans dp2 
            WHERE dp2.id = pp.plan_id AND dp2.plan_date = pp.plan_date
        )
    LEFT JOIN 
        plan_profiles p ON pp.plan_id = p.id AND NOT EXISTS (
            SELECT 1 FROM daily_plans dp3 
            WHERE dp3.id = pp.plan_id AND dp3.plan_date = pp.plan_date
        ) AND NOT EXISTS (
            SELECT 1 FROM weekly_plans wp2 
            WHERE wp2.id = pp.plan_id
        )
    WHERE 
        pp.user_id = ?
    ORDER BY 
        pp.plan_date ASC
";
$stmt_plan = $conn->prepare($active_plan_sql);
$stmt_plan->bind_param("i", $user_id);
$stmt_plan->execute();
$active_plan_result = $stmt_plan->get_result();

$raw_plan_days = [];
while($row = $active_plan_result->fetch_assoc()){
    $raw_plan_days[] = $row;
}
$stmt_plan->close();

// ✅ [DEBUG] ตรวจสอบข้อมูลที่ดึงมา
error_log("📊 Raw plan days count: " . count($raw_plan_days));
foreach($raw_plan_days as $i => $day) {
    error_log("Day $i: {$day['plan_date']} - Type: {$day['plan_type']} - Has data: " . (!empty($day['plan_data']) ? 'YES' : 'NO'));
}


// --- [REVISED] จัดโครงสร้างข้อมูลแผนใหม่ทั้งหมด ---
$plan_days = [];
$active_plan_name = "แผนปัจจุบัน"; 

// ✅ โค้ดใหม่ - เพิ่มการตรวจสอบความปลอดภัย
if (!empty($raw_plan_days)) {
    // Determine plan name from the first day's data
    $first_day = $raw_plan_days[0];
    if ($first_day['plan_type'] === 'custom' && !empty($first_day['profile_name'])) {
        $active_plan_name = $first_day['profile_name'];
    } elseif ($first_day['plan_type'] === 'ai' && !empty($first_day['ai_plan_created_at'])) {
        $active_plan_name = "แผนจาก AI (สร้างเมื่อ " . format_thai_date($first_day['ai_plan_created_at'], false) . ")";
    }

    // ✅ เพิ่มการตรวจสอบก่อน decode JSON
    $plan_data_raw = $raw_plan_days[0]['plan_data'] ?? null;
    
    if (!empty($plan_data_raw)) {
        $full_plan_data_decoded = json_decode($plan_data_raw, true);
        
        // ✅ ตรวจสอบว่า decode สำเร็จหรือไม่
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("❌ JSON decode error: " . json_last_error_msg());
            $full_plan_data_decoded = [];
        }
    } else {
        // ✅ กรณีไม่มี plan_data
        error_log("⚠️ No plan_data found in raw_plan_days");
        $full_plan_data_decoded = [];
    }
    
    // [FIX] Prepare statement to fetch missing nutrient data
    $recipe_details_stmt = $conn->prepare("SELECT sodium_mg, sugar_g, fat, cholesterol_mg FROM recipes WHERE id = ?");

// ✅ [FIX] รองรับทั้ง format เก่า (Day 1) และ format ใหม่ (date)
    foreach ($raw_plan_days as $index => $progress_day) {
        $day_data = [];
        $plan_type = $progress_day['plan_type'];

    // ✅ โค้ดใหม่ - เพิ่มการตรวจสอบ
    if ($plan_type === 'ai') {
        // ✅ ตรวจสอบว่ามี decoded data หรือไม่
        if (!is_array($full_plan_data_decoded) || empty($full_plan_data_decoded)) {
            error_log("⚠️ AI Plan: Empty or invalid plan data");
            $day_data = [];
        } else {
            // ลองหา key แบบ "Day X" ก่อน
            $day_key = 'Day ' . ($index + 1);
            
            if (isset($full_plan_data_decoded[$day_key])) {
                // Format เก่า: { "Day 1": {...}, "Day 2": {...} }
                $day_data = $full_plan_data_decoded[$day_key] ?? [];
                error_log("✅ Day $index: Found Day format ($day_key) - " . count($day_data) . " meals");
            } else {
                // Format ใหม่: { "2025-10-15": {...}, "2025-10-16": {...} }
                $date_key = $progress_day['plan_date'];
                if (isset($full_plan_data_decoded[$date_key])) {
                    $day_data = $full_plan_data_decoded[$date_key] ?? [];
                    error_log("✅ Day $index: Found Date format ($date_key) - " . count($day_data) . " meals");
                } else {
                    error_log("❌ Day $index: No data found! Tried '$day_key' and '$date_key'");
                    error_log("Available keys: " . implode(', ', array_keys($full_plan_data_decoded)));
                    $day_data = [];
                }
            }
        }
            
        // ✅ โค้ดใหม่ที่มีการป้องกัน null
        } elseif ($plan_type === 'custom') {
            // [FIX] สำหรับ Custom Profile - มีโครงสร้างซับซ้อนกว่า
            
            // ✅ เพิ่มการตรวจสอบก่อนใช้ array_keys
            if (is_array($full_plan_data_decoded) && !empty($full_plan_data_decoded)) {
                $sorted_dates = array_keys($full_plan_data_decoded);
                sort($sorted_dates);
                
                if (isset($sorted_dates[$index])) {
                    $date_key = $sorted_dates[$index];
                    $day_entry = $full_plan_data_decoded[$date_key];
                    
                    // ตรวจสอบว่ามี wrapper 'plan' หรือไม่
                    if (isset($day_entry['plan']) && is_array($day_entry['plan'])) {
                        // กรณีมี wrapper (โครงสร้างใหม่จาก custom_plan.php)
                        $day_data = $day_entry['plan'];
                    } elseif (is_array($day_entry)) {
                        // กรณีไม่มี wrapper (โครงสร้างเก่า - backward compatibility)
                        $day_data = $day_entry;
                    }
                } else {
                    // ✅ กรณีไม่มี index ให้ส่ง array ว่าง
                    $day_data = [];
                    error_log("⚠️ Custom plan: Index $index not found in sorted dates");
                }
            } else {
                // ✅ กรณี $full_plan_data_decoded เป็น null หรือ empty
                $day_data = [];
                error_log("⚠️ Custom plan: full_plan_data_decoded is null or empty");
            }
            
        } elseif ($plan_type === 'daily') {
            $day_data = json_decode($progress_day['plan_data'], true);
        }

        // [FIX] แปลง key จากภาษาอังกฤษเป็นไทย
        // ต้องทำก่อนที่จะ enrich เพราะ key ต้องเป็นภาษาไทยเพื่อแสดงผล
        $day_data_thai_keys = map_meal_keys_to_thai($day_data);

        // [FIX] Enrich recipe data with nutrients if they are missing
        $enriched_day_data = [];
        if (is_array($day_data_thai_keys)) {
            foreach ($day_data_thai_keys as $meal_name => $recipes) {
                // Handle both single recipe object and array of recipes
                $is_single_recipe = !(isset($recipes[0]) && is_array($recipes[0]));
                $recipe_items = $is_single_recipe ? [$recipes] : $recipes;
                
                $enriched_recipes = [];
                foreach($recipe_items as $recipe) {
                    if (isset($recipe['id']) && !isset($recipe['sodium_mg'])) {
                        $recipe_details_stmt->bind_param("i", $recipe['id']);
                        $recipe_details_stmt->execute();
                        $result = $recipe_details_stmt->get_result();
                        if ($nutrient_data = $result->fetch_assoc()) {
                            $recipe = array_merge($recipe, $nutrient_data);
                        }
                    }
                    $enriched_recipes[] = $recipe;
                }
                $enriched_day_data[$meal_name] = $is_single_recipe ? ($enriched_recipes[0] ?? []) : $enriched_recipes;
            }
        }
        
        // [REVISED] Calculate totals using the now complete (enriched) data
        $total_cals = 0; $total_sodium = 0; $total_sugar = 0; $total_fat = 0; $total_cholesterol = 0;
        if (is_array($enriched_day_data)) {
            foreach($enriched_day_data as $meal_name => $recipes) {
                $recipe_items = (isset($recipes[0]) && is_array($recipes[0])) ? $recipes : [$recipes];
                 foreach($recipe_items as $recipe) {
                    if (isset($recipe['calories'])) {
                      $total_cals += (float)($recipe['calories'] ?? 0);
                      $total_sodium += (float)($recipe['sodium_mg'] ?? 0);
                      $total_sugar += (float)($recipe['sugar_g'] ?? 0);
                      $total_fat += (float)($recipe['fat'] ?? 0);
                      $total_cholesterol += (float)($recipe['cholesterol_mg'] ?? 0);
                    }
                }
            }
        }

        // Build the final array for the view
        $plan_days[] = [
            'plan_date' => $progress_day['plan_date'],
            'is_completed' => $progress_day['is_completed'],
            'plan' => $enriched_day_data,
            'total_calories_calculated' => $total_cals,
            'total_sodium' => $total_sodium,
            'total_sugar' => $total_sugar,
            'total_fat' => $total_fat,
            'total_cholesterol' => $total_cholesterol
        ];
    }
    $recipe_details_stmt->close();
    }

// [ADD] คำนวณจำนวนมื้ออาหารทั้งหมดในแผน
    $total_meals_in_plan = 0;
    if (!empty($plan_days)) {
        foreach ($plan_days as $day) {
            if (isset($day['plan']) && is_array($day['plan'])) {
                // นับจำนวน key ที่ไม่ใช่ค่าว่างใน 'plan' array
                foreach ($day['plan'] as $meal_key => $meal_data) {
                    // ✅ [FIX] ตรวจสอบว่ามีข้อมูล recipe จริงๆ
                    if (!empty($meal_data)) {
                        if (is_array($meal_data) && isset($meal_data[0])) {
                            // Array of recipes
                            foreach ($meal_data as $recipe) {
                                if (isset($recipe['id']) || isset($recipe['recipe_name']) || isset($recipe['name'])) {
                                    $total_meals_in_plan++;
                                }
                            }
                        } elseif (is_array($meal_data) && (isset($meal_data['id']) || isset($meal_data['recipe_name']) || isset($meal_data['name']))) {
                            // Single recipe object
                            $total_meals_in_plan++;
                        }
                    }
                }
            }
        }
    }
    
    error_log("🍽️ Total meals calculated: $total_meals_in_plan");

// --- SECTION 3: ดึงข้อมูลเมนูแนะนำ (สำหรับส่วนล่างของหน้า) ---
$recommended_breakfasts = [];
$sql_breakfast = "SELECT id, name, description, image_url FROM recipes WHERE id IN (SELECT recipe_id FROM recipe_categories WHERE category_id = (SELECT id FROM categories WHERE name = 'มื้อเช้า')) ORDER BY RAND() LIMIT 8";
if ($result_breakfast = $conn->query($sql_breakfast)) {
    while($row = $result_breakfast->fetch_assoc()) {
        $recommended_breakfasts[] = $row;
    }
}
$recommended_lunches = [];
$sql_lunch = "SELECT id, name, description, image_url FROM recipes WHERE id IN (SELECT recipe_id FROM recipe_categories WHERE category_id = (SELECT id FROM categories WHERE name = 'มื้อกลางวัน')) ORDER BY RAND() LIMIT 8";
if ($result_lunch = $conn->query($sql_lunch)) {
    while($row = $result_lunch->fetch_assoc()) {
        $recommended_lunches[] = $row;
    }
}
$recommended_dinners = [];
$sql_dinner = "SELECT id, name, description, image_url FROM recipes WHERE id IN (SELECT recipe_id FROM recipe_categories WHERE category_id = (SELECT id FROM categories WHERE name = 'มื้อเย็น')) ORDER BY RAND() LIMIT 8";
if ($result_dinner = $conn->query($sql_dinner)) {
    while($row = $result_dinner->fetch_assoc()) {
        $recommended_dinners[] = $row;
    }
}
$conn->close();

// --- ตรรกะสำหรับกำหนดมุมมองเริ่มต้น ---
$has_active_plan = !empty($plan_days);
$show_progress_initially = $has_active_plan || isset($_GET['plan_activated']);
?>

<style>
    .hero-header { position: relative; }
    .view-toggle-btn {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        z-index: 10;
        background-color: rgba(255, 255, 255, 0.2);
        border: 1px solid rgba(255, 255, 255, 0.7);
        width: 40px; height: 40px;
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
    }
    .view-toggle-btn:hover { background-color: rgba(255, 255, 255, 0.4); }
    #show-progress-btn { right: 25px; }
    #show-banner-btn { left: 25px; }

    .plan-progress-container { 
        background-color: rgba(255,255,255,0.95); 
        border-radius: 15px; 
        padding: 25px; 
        box-shadow: 0 4px 12px rgba(0,0,0,0.08); 
        color: #333; 
    }
    
    /* ปรับ Timeline ให้เลื่อนดีขึ้น */
    .plan-timeline { 
        display: flex; 
        overflow-x: auto; 
        padding-bottom: 20px; 
        scrollbar-width: thin; 
        scrollbar-color: #2FAAA8 #f1f1f1;
        -webkit-overflow-scrolling: touch; /* เพิ่มสำหรับ iOS */
    }
    .plan-timeline::-webkit-scrollbar { height: 8px; }
    .plan-timeline::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 10px; }
    .plan-timeline::-webkit-scrollbar-thumb { background: #2FAAA8; border-radius: 10px; }
    
    /* ปรับ Card ให้ยืดหยุ่นตามหน้าจอ */
    .plan-day-card { 
        flex: 0 0 280px; 
        margin-right: 20px; 
        border: 1px solid #e0e0e0; 
        border-radius: 10px; 
        padding: 15px; 
        background-color: #fff; 
    }
    .plan-day-card.completed { background-color: #e8f5e9; border-color: #a5d6a7; }
    .meal-item { font-size: 0.9rem; padding: 4px 0; border-bottom: 1px dashed #ddd; }
    .meal-item:last-child { border-bottom: none; }
    .completion-checkbox .form-check-input:checked { background-color: #28a745; border-color: #28a745; }
    
    /* === MOBILE RESPONSIVE RULES === */
    @media (max-width: 768px) {
        .view-toggle-btn { 
            width: 35px !important; 
            height: 35px !important; 
        }
        #show-progress-btn { right: 10px !important; }
        #show-banner-btn { left: 10px !important; }
        
        .plan-progress-container { 
            padding: 15px !important; 
        }
        
        /* Card ให้กว้างขึ้นเล็กน้อยบนมือถือ */
        .plan-day-card { 
            flex: 0 0 220px !important; 
            margin-right: 12px !important;
            padding: 12px !important;
        }
    }
    
    @media (max-width: 480px) {
        /* Card เล็กลงอีกสำหรับหน้าจอเล็กมาก */
        .plan-day-card { 
            flex: 0 0 190px !important; 
            margin-right: 10px !important;
            padding: 10px !important;
        }
    }
</style>

<main class="container-fluid content-area">

<div class="container" style="padding-top: 20px;">
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['success_message']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['error_message']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>
    
 <?php 
// ✅ แสดง success message ตามแหล่งที่มา
if (isset($_GET['plan_activated']) && $_GET['plan_activated'] == '1'): 
    if (isset($_GET['from_generation']) && $_GET['from_generation'] == '1'): 
?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>
            <strong>🎉 สร้างแผนสำเร็จ!</strong> แผนอาหาร AI 7 วันของคุณพร้อมใช้งานแล้ว เลื่อนลงเพื่อดูความคืบหน้า
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
<?php 
    elseif (isset($_GET['from_my_plans']) && $_GET['from_my_plans'] == '1'): 
?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-calendar-check me-2"></i>
            <strong>✅ เปลี่ยนแผนสำเร็จ!</strong> เริ่มต้นใช้งานแผนที่คุณเลือกได้เลย
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
<?php 
    else: 
?>
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            <i class="bi bi-info-circle me-2"></i>
            แผนอาหารของคุณพร้อมใช้งาน
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
<?php 
    endif;
endif; 
?>

    <div class="container-fluid hero-header mb-5" style="padding-top: 130px;">
        <button id="show-progress-btn" class="btn btn-light view-toggle-btn" style="<?php echo ($show_progress_initially || !$has_active_plan) ? 'display: none;' : 'display: flex;'; ?>"><i class="bi bi-caret-right-fill"></i></button>
        <button id="show-banner-btn" class="btn btn-light view-toggle-btn" style="<?php echo !$show_progress_initially ? 'display: none;' : 'display: flex;'; ?>"><i class="bi bi-caret-left-fill"></i></button>
        
        <div id="default-banner-view" style="<?php echo $show_progress_initially ? 'display: none;' : 'display: block;'; ?>">
            <div class="row align-items-center g-5 px-5">
                <div class="col-lg-6 text-center text-lg-start">
                    <h3 style="color: #B7D971;" class="animated slideInUp">สวัสดี, <?php echo htmlspecialchars($username); ?>!</h3>
                    <h4 style="color: #2FAAA8;" class="mb-4 animated slideInUp">ยินดีต้อนรับสู่แดชบอร์ดสุขภาพของคุณ</h4>
                    <h1 class="typewriter-container text-white">
                        <div id="typewriter-line-0" class="type-line"></div>
                        <div id="typewriter-line-1" class="type-line"></div>
                        <div id="typewriter-line-2" class="type-line"></div>
                    </h1>
                    <p class="text-white animated slideInLeft mb-4 pb-2 small-text">FitMealWeek คือเว็บไซต์สำหรับผู้ที่ใส่ใจสุขภาพ ที่จะช่วยคุณ วางแผนเมนูประจำสัปดาห์ อย่างชาญฉลาด ไม่ว่าคุณจะต้องการควบคุมน้ำหนัก, ดูแลโรคประจำตัว (เช่น เบาหวาน ความดัน ไขมันในเลือด), หรือเพียงแค่อยากกินดีในทุกมื้อ</p>
                    <a href="about.php" class="btn btn-gradient2 py-sm-3 px-sm-4 me-3 animated slideInLeft" style="font-size: 16px;">เรียนรู้เพิ่มเติม</a>
                </div>
               <div class="col-lg-6 text-center text-lg-end overflow-hidden">
                    <img src="assets/images/banner/hero.png" alt="Hero Image" class="rotating-hero-image">
                </div>
            </div>
        </div>
        
        <div id="progress-tracker-view" class="container-fluid" style="<?php echo !$show_progress_initially ? 'display: none;' : 'display: block;'; ?>">
    <style>
    .progress-view-container {
        background-color: rgba(255, 255, 255, 0.9);
        border-radius: 15px;
        padding: 25px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        color: #333;
    }

    /* === Progress Header Improvements === */
        .progress-header-section {
        position: relative;
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        border-radius: 12px;
        padding: 15px 15px 15px 25px; /* เผื่อที่ด้านซ้าย */
        overflow: hidden;
        }

        /* ทำเส้นขอบซ้ายเป็น gradient */
        .progress-header-section::before {
        content: "";
        position: absolute;
        top: 0;
        left: 0;
        width: 6px;
        height: 100%;
        border-radius: 12px 0 0 12px;
        background: linear-gradient(135deg, #2FC2A0 0%, #B7D971 100%);
        }

        .plan-title-wrapper .plan-title {
            font-size: 1.1rem;
            color: #212529;
            font-weight: 600;
            display: flex;
            align-items: center;
        }

        .plan-title-wrapper .plan-subtitle {
            font-size: 0.85rem;
            color: #6c757d;
            padding-left: 32px;
        }

        .plan-action-buttons {
            display: flex;
            gap: 8px;
            margin-top: 12px;
        }

        .btn-action {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 8px 12px;
            font-size: 0.875rem;
            white-space: nowrap;
        }

        /* Update Button Styles */
        .btn-warning.btn-action {
            background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);
            color: white;
            border: none;
            transition: all 0.3s ease;
        }

        .btn-warning.btn-action:hover {
            background: linear-gradient(135deg, #ff9800 0%, #ffc107 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(255, 193, 7, 0.4);
            transform: translateY(-2px);
        }

        /* Animation for new button */
        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        #header-update-btn {
            animation: slideInRight 0.5s ease-out;
        }

        .btn-action i {
            font-size: 1rem;
        }
        
    /* === [NEW] Gradient buttons === */
    .btn-gradient-blue {
        background-image: linear-gradient(to right, #3498db 0%, #2980b9 100%);
        color: white;
        border: none;
        transition: all 0.3s ease;
    }
    .btn-gradient-blue:hover {
        background-image: linear-gradient(to right, #2980b9 0%, #3498db 100%);
        color: white;
        box-shadow: 0 4px 8px rgba(0,0,0,0.2);
    }
    .btn-gradient-red {
        background-image: linear-gradient(to right, #e74c3c 0%, #c0392b 100%);
        color: white;
        border: none;
        transition: all 0.3s ease;
    }
    .btn-gradient-red:hover {
        background-image: linear-gradient(to right, #c0392b 0%, #e74c3c 100%);
        color: white;
        box-shadow: 0 4px 8px rgba(0,0,0,0.2);
    }

        /* === Progress Bar Styling === */
        .progress-section {
            background-color: #fff;
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.05);
        }

        .progress-label {
            display: flex;
            align-items: center;
            font-size: 0.95rem;
        }

        .progress-percentage .badge {
            font-size: 0.9rem;
            padding: 6px 12px;
        }

        .progress-modern {
            border-radius: 12px;
            overflow: hidden;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.1);
        }

        .progress-modern .progress-bar {
            transition: width 0.6s ease;
        }

        .progress-text {
            font-size: 0.8rem;
            font-weight: 600;
            line-height: 24px;
        }

    .plan-day-timeline {
        display: flex;
        overflow-x: auto;
        padding-bottom: 20px;
        scrollbar-width: thin;
        scrollbar-color: #0d6efd #f1f1f1;
        -webkit-overflow-scrolling: touch;
    }

    .plan-day-timeline::-webkit-scrollbar { height: 8px; }
    .plan-day-timeline::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 10px; }
    .plan-day-timeline::-webkit-scrollbar-thumb { background: #0d6efd; border-radius: 10px; }

    .progress-day-card {
        flex: 0 0 220px;
        margin-right: 15px;
        border: 2px solid #e0e0e0;
        border-radius: 15px;
        padding: 10px;
        background-color: #fff;
        text-align: center;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        transition: all 0.3s ease-in-out;
    }
    .progress-day-card.active-day {
        border-color: var(--bs-primary);
        box-shadow: 0 0 15px rgba(13, 110, 253, 0.3);
    }
    .progress-day-card.completed {
        background-color: #d1e7dd;
        border-color: #0f5132;
    }
    
    .meal-status-display {
        background-color: #f8f9fa;
        border-radius: 10px;
        padding: 10px;
        position: relative;
        flex-grow: 1;
        display: flex;
        flex-direction: column;
    }
    
    .meal-icons {
        display: flex;
        justify-content: space-around;
        margin-bottom: 10px;
        align-items: center;
        flex-wrap: wrap; /* เพิ่มเพื่อให้ไอคอนขึ้นบรรทัดใหม่ถ้าพื้นที่ไม่พอ */
        gap: 4px; /* เพิ่มระยะห่าง */
    }

    .meal-icons i {
        width: 32px;
        height: 32px;
        line-height: 32px;
        text-align: center;
        border-radius: 50%;
        background-color: #e9ecef;
        color: #6c757d;
        transition: all 0.3s ease-in-out;
        font-size: 1rem;
    }
    
    .meal-icons i.active-breakfast { background-color: #2FACAA; color: white; }
    .meal-icons i.active-brunch { background-color: #B7D971; color: white; }
    .meal-icons i.active-lunch { background-color: #FFB405; color: white; }
    .meal-icons i.active-snack { background-color: #E3812B; color: white; }
    .meal-icons i.active-dinner { background-color: #7E72DA; color: white; }

    .meal-content {
        position: relative;
        text-align: center;
        flex-grow: 1;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }

    .meal-content img {
        width: 100%;
        height: 120px;
        object-fit: cover;
        border-radius: 5px;
        margin-bottom: 10px;
        transform: none !important; 
        animation: none !important;
    }

    .meal-content .meal-checkbox {
        position: absolute;
        top: 10px;
        right: 10px;
        width: 25px;
        height: 25px;
        cursor: pointer;
        border: 2px solid black;
        background-color: rgba(255, 255, 255, 0.7);
        border-radius: 5px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.3);
    }
    
    .meal-info {
        margin-top: auto;
    }
    .meal-info h6 {
         margin-bottom: 2px;
         font-size: 0.9rem;
    }
    .meal-info .calories {
         font-size: 0.8rem;
         color: #6c757d;
    }

    .day-completed-message {
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        height: 100%;
        flex-grow: 1;
        color: #198754;
    }
    .day-completed-message i {
        font-size: 3rem;
    }

    .chart-card {
        background-color: #fff;
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.07);
        margin-top: 1.5rem;
    }
    
    /* === MOBILE OPTIMIZATIONS === */
        @media (max-width: 768px) {
            .progress-view-container {
                padding: 10px !important;
                margin: 0 -15px !important; /* ขยายออกนอก container */
                border-radius: 0 !important; /* ลบมุมมน */
                width: calc(100% + 30px) !important;
            }
        
        .progress-day-card {
            flex: 0 0 180px !important;
            margin-right: 10px !important;
            padding: 8px !important;
        }
        
        /* === Mobile Header Adjustments === */
        .progress-header-section {
            padding: 12px !important;
        }

        .plan-title-wrapper .plan-title {
            font-size: 0.95rem !important;
        }

        .plan-title-wrapper .plan-title i {
            font-size: 1.1rem !important;
        }

        .plan-title-wrapper .plan-subtitle {
            font-size: 0.75rem !important;
            padding-left: 28px !important;
        }

        .plan-action-buttons {
            gap: 6px !important;
        }

        .btn-action {
            padding: 6px 10px !important;
            font-size: 0.75rem !important;
        }

        .btn-action span {
            display: none; /* ซ่อนข้อความบนมือถือ แสดงแค่ icon */
        }

        .btn-action i {
            font-size: 0.9rem !important;
            margin: 0 !important;
        }

        /* === Progress Bar Mobile === */
        .progress-section {
            padding: 10px !important;
        }

        .progress-label {
            font-size: 0.85rem !important;
        }

        .progress-label i {
            font-size: 1rem !important;
        }

        .progress-percentage .badge {
            font-size: 0.8rem !important;
            padding: 4px 8px !important;
        }

        .progress-modern {
            height: 20px !important;
        }

        .meal-icons {
            gap: 3px !important;
        }
        
        .meal-icons i {
            width: 28px !important;
            height: 28px !important;
            line-height: 28px !important;
            font-size: 0.85rem !important;
        }
        
        .meal-content img {
            height: 100px !important;
        }
        
        .meal-info h6 {
            font-size: 0.8rem !important;
        }
        
        .meal-info .calories {
            font-size: 0.7rem !important;
        }
        
        .chart-card {
            padding: 15px !important;
            margin-top: 1rem !important;
        }
        
        .chart-card h5 {
            font-size: 0.95rem !important;
            margin-bottom: 10px !important;
        }
        
        /* ปรับ canvas ให้แสดงผลดีขึ้น */
        .chart-card canvas {
            max-height: 250px !important;
        }
    }
    
    @media (max-width: 480px) {
        .progress-day-card {
            flex: 0 0 160px !important;
        }
        
        .meal-icons i {
            width: 24px !important;
            height: 24px !important;
            line-height: 24px !important;
            font-size: 0.75rem !important;
        }
        
        .meal-content img {
            height: 80px !important;
        }
        
        .chart-card canvas {
            max-height: 200px !important;
        }
    }

        /* === ปุ่มลัดด้านขวา (ดูปฏิทินและแก้ไขข้อมูล) === */
    .floating-action-buttons {
        position: fixed;
        right: 25px;
        bottom: 80px;
        z-index: 1000;
        display: flex;
        flex-direction: column;
        gap: 15px;
    }
    
    .floating-btn {
        width: 56px;
        height: 56px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        border: none;
        cursor: pointer;
        transition: all 0.3s ease;
        color: white;
        font-size: 1.5rem;
    }
    
    .floating-btn:hover {
        transform: scale(1.1);
        box-shadow: 0 6px 16px rgba(0, 0, 0, 0.25);
    }
    
    .floating-btn:active {
        transform: scale(0.95);
    }
    
    /* ปุ่มแก้ไขข้อมูล - Gradient สีเขียว */
    .btn-edit-profile {
        background: linear-gradient(135deg, #2FC2A0 0%, #B7D971 100%);
    }
    
    /* ปุ่มดูปฏิทิน - Gradient สีน้ำเงิน */
    .btn-calendar-view {
        background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    }
    
    /* Responsive สำหรับมือถือ */
    @media (max-width: 768px) {
        .floating-action-buttons {
            right: 15px;
            bottom: 70px;
            gap: 12px;
        }
        
        .floating-btn {
            width: 50px;
            height: 50px;
            font-size: 1.3rem;
        }
    }
    
    @media (max-width: 480px) {
        .floating-action-buttons {
            right: 10px;
            bottom: 60px;
        }
        
        .floating-btn {
            width: 48px;
            height: 48px;
            font-size: 1.2rem;
        }
    }

    /* === [NEW] Responsive Modal Styles === */
    @media (max-width: 768px) {
        #planCompletedModal .modal-dialog {
            margin: 1rem;
            max-width: calc(100% - 2rem);
        }
        
        #planCompletedModal .modal-content {
            border-radius: 15px;
        }
        
        #planCompletedModal .modal-body {
            padding: 1.5rem 1rem;
        }
        
        #planCompletedModal .trophy-animation i {
            font-size: 3rem;
        }
        
        #planCompletedModal h4 {
            font-size: 1.3rem;
        }
        
        #planCompletedModal p {
            font-size: 0.85rem;
        }
        
        #planCompletedModal .btn-lg {
            padding: 0.75rem 1.5rem;
            font-size: 0.95rem;
        }
    }

/* ✅ แก้ไขปัญหา Modal Backdrop */
    .modal-backdrop {
        background-color: rgba(0, 0, 0, 0.5) !important;
        z-index: 1040 !important;
    }

    .modal-backdrop.show {
        opacity: 0.5 !important;
    }

    .modal {
        z-index: 1050 !important;
    }

    /* ✅ ป้องกัน backdrop ซ้อนกัน */
    .modal-open {
        overflow: hidden !important;
        padding-right: 0 !important;
    }

    /* ✅ Animation สำหรับปิด Modal */
    .modal.fade .modal-dialog {
        transition: transform 0.3s ease-out;
    }

    .modal-backdrop.fade {
        transition: opacity 0.15s linear;
    }

    /* === Update Button Styles === */
    .btn-warning.btn-action {
        background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);
        color: white;
        border: none;
        transition: all 0.3s ease;
    }

    .btn-warning.btn-action:hover {
        background: linear-gradient(135deg, #ff9800 0%, #ffc107 100%);
        color: white;
        box-shadow: 0 4px 12px rgba(255, 193, 7, 0.4);
        transform: translateY(-2px);
    }
</style>

    <div class="row justify-content-center">
        <div class="col-12 px-0">
            
            <?php if ($has_active_plan && $profile): ?>
                <div class="progress-view-container wow fadeInUp" data-wow-delay="0.1s">
                   <div class="progress-header-section mb-3">
                        <div class="plan-title-wrapper mb-2">
                            <h4 class="plan-title mb-1">
                                <i class="bi bi-clipboard-check text-primary me-2"></i>
                                <?php echo htmlspecialchars($active_plan_name); ?>
                            </h4>
                            <p class="plan-subtitle mb-0">
                                <i class="bi bi-info-circle me-1"></i>
                                ทำเครื่องหมายเมื่อทานอาหารตามแผน
                            </p>
                        </div>
                        <div class="plan-action-buttons">
                            <a href="my_plans.php" class="btn btn-gradient-blue btn-action">
                                <i class="bi bi-pencil-square"></i>
                                <span>จัดการแผน</span>
                            </a>
                            
                            <?php
                            // ✅ [NEW] ตรวจสอบว่าแผนเสร็จหรือยัง
                            $total_days = count($plan_days);
                            $completed_days = 0;
                            
                            foreach ($plan_days as $day) {
                                if ($day['is_completed'] == 1) {
                                    $completed_days++;
                                }
                            }
                            
                            $is_plan_completed = ($total_days > 0 && $completed_days >= $total_days);
                            
                            // 🆕 [FIX] แสดงปุ่มอัพเดทถ้าแผนเสร็จแล้ว
                            if ($is_plan_completed):
                            ?>
                            <button id="header-update-btn-permanent" class="btn btn-warning btn-action" onclick="openHealthUpdateModal()">
                                <i class="bi bi-clipboard2-pulse"></i>
                                <span>อัพเดทข้อมูล</span>
                            </button>
                            <?php endif; ?>
                            
                            <button id="exit-plan-btn" class="btn btn-gradient-red btn-action">
                                <i class="bi bi-box-arrow-right"></i>
                                <span>ออกจากแผน</span>
                            </button>
                        </div>
                    </div>

                    <div class="progress-section mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div class="progress-label">
                                <i class="bi bi-trophy-fill text-warning me-2"></i>
                                <span class="fw-semibold">ความสำเร็จ</span>
                            </div>
                            <div class="progress-percentage">
                                <span id="plan-progress-percentage" class="badge bg-primary">0%</span>
                            </div>
                        </div>
                        <div class="progress progress-modern" style="height: 24px;">
                            <div id="plan-progress-bar" 
                                class="progress-bar progress-bar-striped progress-bar-animated" 
                                role="progressbar" 
                                style="width: 0%; background: linear-gradient(90deg, #B7D971 0%, #2FAAA8 100%);" 
                                aria-valuenow="0" 
                                aria-valuemin="0" 
                                aria-valuemax="100">
                                <span class="progress-text"></span>
                            </div>
                        </div>
                    </div>

                    <div class="mobile-day-navigator d-md-none">
                        <div class="day-nav-header">
                            <button id="prev-day-btn" class="nav-arrow-btn">
                                <i class="bi bi-chevron-left"></i>
                            </button>
                            
                            <div class="current-day-info">
                                <h4 id="current-day-title">วันที่ 1</h4>
                                <small id="current-day-date">5 ต.ค. 2568</small>
                            </div>
                            
                            <button id="next-day-btn" class="nav-arrow-btn">
                                <i class="bi bi-chevron-right"></i>
                            </button>
                        </div>

                        <div class="current-day-display" id="mobile-day-content">
                            </div>
                    </div>

                    <div class="plan-day-timeline-desktop d-none d-md-flex">
                       <?php foreach($plan_days as $index => $day): 
                            $date = new DateTime($day['plan_date']);
                            $plan_data_json = json_encode($day['plan'] ?? []); 
                            $day_plan = $day['plan'] ?? [];
                        ?>
                            <div class="progress-day-card <?php echo $day['is_completed'] ? 'day-completed' : ''; ?>" 
                                id="progress-card-<?php echo $date->format('Y-m-d'); ?>"
                                data-date="<?php echo $date->format('Y-m-d'); ?>"
                                data-day-index="<?php echo $index; ?>"
                                data-plan='<?php echo htmlspecialchars($plan_data_json, ENT_QUOTES, 'UTF-8'); ?>'>
                                
                                <div class="meal-status-display">
                                    <div class="meal-icons">
                                        <?php 
                                        if (!empty($day_plan['มื้อเช้า'])): ?>
                                            <i id="icon-breakfast-<?php echo $date->format('Y-m-d'); ?>" class="fas fa-coffee" title="มื้อเช้า"></i> 
                                        <?php endif; ?>
                                        <?php if (!empty($day_plan['มื้อว่างเช้า'])): ?>
                                            <i id="icon-brunch-<?php echo $date->format('Y-m-d'); ?>" class="fa-solid fa-bread-slice" title="มื้อว่างเช้า"></i> 
                                        <?php endif; ?>
                                        <?php if (!empty($day_plan['มื้อกลางวัน'])): ?>
                                            <i id="icon-lunch-<?php echo $date->format('Y-m-d'); ?>" class="fa-solid fa-burger" title="มื้อกลางวัน"></i> 
                                        <?php endif; ?>
                                        <?php if (!empty($day_plan['มื้อว่างบ่าย'])): ?>
                                            <i id="icon-snack-<?php echo $date->format('Y-m-d'); ?>" class="fa-solid fa-cookie-bite" title="มื้อว่างบ่าย"></i> 
                                        <?php endif; ?>
                                        <?php if (!empty($day_plan['มื้อเย็น'])): ?>
                                            <i id="icon-dinner-<?php echo $date->format('Y-m-d'); ?>" class="fa-solid fa-utensils" title="มื้อเย็น"></i> 
                                        <?php endif; ?>
                                    </div>
                                    <div class="meal-content"></div>
                                </div>

                                <div class="mt-2 text-center">
                                    <small class="fw-bold">วันที่ <?php echo $index + 1; ?></small><br>
                                    <small class="text-muted"><?php echo format_thai_date($day['plan_date'], false); ?></small>
                                </div>

                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php
                        // This progress bar is for weight goal, not plan completion.
                        $start_weight = $profile['initial_weight'] ?? $profile['weight'];
                        $goal_weight = $profile['goal_weight'] ?? $start_weight;
                        $current_weight = $profile['weight'];
                        $plan_start_date = $plan_days[0]['plan_date'];
                        $plan_end_date = end($plan_days)['plan_date'];
                        
                        $weight_diff_total = $goal_weight - $start_weight;
                        $weight_diff_current = $current_weight - $start_weight;
                        $progress_percent = 0;
                        if ($weight_diff_total != 0) {
                            $progress_percent = max(0, min(100, ($weight_diff_current / $weight_diff_total) * 100));
                        } else if ($current_weight == $goal_weight) {
                            $progress_percent = 100;
                        }
                    ?>

                    <div class="row flex-lg-wrap flex-nowrap overflow-auto" style="padding-bottom: 15px;">
                        <div class="col-lg-6 col-11 mb-4">
                            <div class="chart-card h-100">
                                <h5 class="text-center mb-3">แคลอรี่ที่ได้รับ</h5>
                                <canvas id="caloriesReceivedChart"></canvas>
                            </div>
                        </div>
                        <div class="col-lg-6 col-11 mb-4">
                             <div class="chart-card h-100">
                                <h5 class="text-center mb-3">น้ำหนัก</h5>
                                <canvas id="weightLogChart"></canvas>
                            </div>
                        </div>
                        <div class="col-lg-6 col-11 mb-4">
                            <div class="chart-card h-100">
                                <h5 class="text-center mb-3">โซเดียม (mg)</h5>
                                <canvas id="sodiumChart"></canvas>
                            </div>
                        </div>
                        <div class="col-lg-6 col-11 mb-4">
                            <div class="chart-card h-100">
                                <h5 class="text-center mb-3">น้ำตาล (g)</h5>
                                <canvas id="sugarChart"></canvas>
                            </div>
                        </div>
                        <div class="col-lg-6 col-11 mb-4">
                            <div class="chart-card h-100">
                                <h5 class="text-center mb-3">ไขมัน (g)</h5>
                                <canvas id="fatChart"></canvas>
                            </div>
                        </div>
                        <div class="col-lg-6 col-11 mb-4">
                            <div class="chart-card h-100">
                                <h5 class="text-center mb-3">คอเลสเตอรอล (mg)</h5>
                                <canvas id="cholesterolChart"></canvas>
                            </div>
                        </div>
                    </div>

                </div>

            <?php else: ?>
                <div class="text-center p-5 bg-white rounded shadow-sm text-dark">
                    <i class="bi bi-calendar-x fs-1 text-muted"></i>
                    <h4 class="mt-3">ไม่มีแผนที่กำลังใช้งาน</h4>
                    <p class="text-muted">ไปที่หน้า "โปรไฟล์แผนของฉัน" เพื่อเริ่มนำแผนมาใช้งาน</p>
                    <a href="my_plans.php" class="btn btn-primary mt-2">เลือกแผนอาหาร</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
<!-- ปุ่มลัดด้านขวา -->
    <div class="floating-action-buttons">
        <!-- ปุ่มแก้ไขข้อมูลสุขภาพ (อยู่บนสุด) -->
        <a href="profile.php" class="floating-btn btn-edit-profile" title="แก้ไขข้อมูลสุขภาพ">
            <i class="bi bi-person-gear"></i>
        </a>
        
        <!-- ปุ่มดูปฏิทิน (อยู่ด้านล่าง) -->
        <button id="calendar-view-btn" class="floating-btn btn-calendar-view" title="ดูปฏิทินแผน">
            <i class="bi bi-calendar3"></i>
        </button>
    </div>

    </div>

    <section class="mb-4 wow fadeInUp" style="padding-top: 30px;">
    <?php if ($profile): ?>
        <div class="row g-3">
            <div class="col-4 col-md-4">
                <div class="card health-card-gradient shadow rounded text-center p-3 h-100 gradient-bmi">
                    <a href="#" class="info-icon" data-bs-toggle="modal" data-bs-target="#healthInfoModal" data-title="ค่า BMI คืออะไร?" data-content="<strong>BMI (Body Mass Index)</strong> คือ ดัชนีมวลกาย เป็นค่าที่ใช้ประเมินภาวะน้ำหนักตัว ว่าอยู่ในเกณฑ์ปกติ, ต่ำกว่าเกณฑ์, หรือเกินเกณฑ์ (อ้วน) โดยคำนวณจากน้ำหนักและส่วนสูง ใช้เป็นตัวชี้วัดความเสี่ยงต่อโรคต่างๆ ที่เกี่ยวข้องกับน้ำหนักได้">
                        <i class="fas fa-info-circle"></i>
                    </a>
                    <h5 class="health-card-title">ค่า BMI</h5>
                    <h2 class="health-card-value"><?php echo htmlspecialchars($profile['bmi']); ?></h2>
                </div>
            </div>

            <div class="col-4 col-md-4">
                <div class="card health-card-gradient shadow rounded text-center p-3 h-100 gradient-bmr">
                    <a href="#" class="info-icon" data-bs-toggle="modal" data-bs-target="#healthInfoModal" data-title="ค่า BMR คืออะไร?" data-content="<strong>BMR (Basal Metabolic Rate)</strong> คือ อัตราการเผาผลาญพลังงานพื้นฐานของร่างกายในแต่ละวัน หมายถึงจำนวนแคลอรี่ขั้นต่ำที่ร่างกายต้องการเพื่อใช้ในการทำงานของอวัยวะต่างๆ ขณะพัก เช่น การหายใจ, การเต้นของหัวใจ, การรักษาระดับอุณหภูมิร่างกาย เป็นต้น">
                        <i class="fas fa-info-circle"></i>
                    </a>
                    <h5 class="health-card-title">BMR</h5>
                    <h2 class="health-card-value"><?php echo round($profile['bmr']); ?></h2>
                </div>
            </div>

            <div class="col-4 col-md-4">
                <div class="card health-card-gradient shadow rounded text-center p-3 h-100 gradient-tdee">
                    <a href="#" class="info-icon" data-bs-toggle="modal" data-bs-target="#healthInfoModal" data-title="ค่า TDEE คืออะไร?" data-content="<strong>TDEE (Total Daily Energy Expenditure)</strong> คือ ปริมาณการใช้พลังงานทั้งหมดในแต่ละวัน ซึ่งจะรวมค่า BMR (พลังงานที่ใช้ตอนพัก) เข้ากับพลังงานที่ใช้ทำกิจกรรมต่างๆ เช่น เดิน, ทำงาน, และออกกำลังกาย ค่า TDEE จะบอกว่าในหนึ่งวันคุณควรได้รับแคลอรี่เท่าไหร่เพื่อรักษาน้ำหนักตัวให้คงที่ หากต้องการลดน้ำหนัก ก็ควรทานให้น้อยกว่าค่านี้">
                        <i class="fas fa-info-circle"></i>
                    </a>
                    <h5 class="health-card-title">TDEE</h5>
                    <h2 class="health-card-value"><?php echo round($tdee); ?></h2>
                </div>
            </div>

            <div class="col-3 col-md-3">
                <div class="card health-card-gradient shadow rounded text-center p-2 p-md-4 h-100 gradient-details">
                    <h5>น้ำหนัก (kg)</h5>
                    <h2><?php echo round($profile['weight']); ?></h2>
                </div>
            </div>
            <div class="col-3 col-md-3">
                <div class="card health-card-gradient shadow rounded text-center p-2 p-md-4 h-100 gradient-details">
                    <h5>ส่วนสูง (cm)</h5>
                    <h2><?php echo round($profile['height']); ?></h2>
                </div>
            </div>
            <div class="col-3 col-md-3">
                <div class="card health-card-gradient shadow rounded text-center p-2 p-md-4 h-100 gradient-profile">
                    <h5>อายุ (ปี)</h5>
                    <h2><?php echo round($profile['age']); ?></h2>
                </div>
            </div>
            <div class="col-3 col-md-3">
                <div class="card health-card-gradient shadow rounded text-center p-2 p-md-4 h-100 gradient-profile">
                    <h5>เพศ</h5>
                    <h2>
                        <?php echo (isset($profile['gender']) && strtolower($profile['gender']) == 'female') ? 'หญิง' : 'ชาย'; ?>
                    </h2>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-warning text-center">
            <h4>ยังไม่มีข้อมูลสุขภาพของคุณ</h4>
            <a href="profile.php" class="btn btn-primary">กรอกข้อมูลเพื่อเริ่มต้นใช้งาน</a>
        </div>
    <?php endif; ?>



        <div style="padding-top: 15px;">
            <?php if ($profile) : ?>
                <?php
                    $adviceClass = 'alert-info '; $adviceIcon = '<i class="bi bi-info-circle-fill me-3"></i>'; $adviceTitle = "คำแนะนำสำหรับคุณ"; $adviceMessage = 'กรุณากรอกข้อมูล BMI เพื่อรับคำแนะนำที่แม่นยำ'; $diseaseAdvice = '';
                    if (isset($profile['bmi'])) {
                        $bmi = $profile['bmi'];

                        // [FIX] Replicate calorie calculation from profile.php to ensure consistency
                        $recommended_calories = 0;
                        if (isset($profile['bmr'], $profile['activity_level'], $profile['goal'])) {
                            // Calculate TDEE from stored BMR and activity level
                            $goal_tdee = $profile['bmr'] * (float)$profile['activity_level'];

                            // Adjust TDEE based on the user's goal
                            switch ($profile['goal']) {
                                case 'ลดน้ำหนัก':
                                    $goal_tdee -= 500;
                                    break;
                                case 'เพิ่มน้ำหนัก':
                                    $goal_tdee += 500;
                                    break;
                                // For 'รักษาน้ำหนัก' (Maintain weight), no adjustment is needed.
                            }
                            $recommended_calories = round($goal_tdee);
                        } else {
                             // Fallback to the TDEE calculated at the top of the file if goal is not set
                            $recommended_calories = round($tdee);
                        }


                        if ($bmi < 18.5) { $adviceClass = 'alert-custom-1'; $adviceIcon = '<i class="bi bi-arrow-up-circle-fill me-3"></i>'; $adviceTitle = "น้ำหนักน้อยกว่าเกณฑ์"; $adviceMessage = 'ควรได้รับพลังงานประมาณ <strong>' . $recommended_calories . ' kcal/วัน</strong><br>เน้นโปรตีนดี, ไขมันดี, และคาร์โบไฮเดรตเชิงซ้อน'; } 
                        elseif ($bmi < 23) { $adviceClass = 'alert-custom-2'; $adviceIcon = '<i class="bi bi-check-circle-fill me-3"></i>'; $adviceTitle = "สุขภาพดี น้ำหนักสมส่วน"; $adviceMessage = 'เยี่ยมมาก! รักษาน้ำหนักโดยรับพลังงานประมาณ <strong>' . $recommended_calories . ' kcal/วัน</strong><br>ทานอาหารให้สมดุลครบ 5 หมู่'; } 
                        elseif ($bmi < 25) { $adviceClass = 'alert-custom-3'; $adviceIcon = '<i class="bi bi-exclamation-triangle-fill me-3"></i>'; $adviceTitle = "น้ำหนักเริ่มเกิน (ท้วม)"; $adviceMessage = 'ควรควบคุมพลังงานที่ <strong>' . $recommended_calories . ' kcal/วัน</strong><br>เน้นผักใบเขียว, โปรตีนไขมันต่ำ และลดของมันของทอด'; } 
                        else { $adviceClass = 'alert-custom-4'; $adviceIcon = '<i class="bi bi-x-octagon-fill me-3"></i>'; $adviceTitle = "น้ำหนักเกินเกณฑ์ (โรคอ้วน)"; $adviceMessage = 'ควรจำกัดพลังงานที่ <strong>' . $recommended_calories . ' kcal/วัน</strong><br>หลีกเลี่ยงอาหารแปรรูป, น้ำหวาน, และไขมันทรานส์'; }
                    }
                    if (!empty($profile['disease']) && $profile['disease'] !== 'ไม่มี') {
                        // [FIX] Handle multiple diseases
                        $diseases = explode(',', $profile['disease']);
                        $diseaseAdvice = '';
                        foreach ($diseases as $disease) {
                            $diseaseName = htmlspecialchars(trim($disease));
                            if (empty($diseaseName)) continue;
                            
                            $specificAdvice = '';
                            switch ($diseaseName) {
                                case 'โรคอ้วน':
                                    $specificAdvice = 'ควรใส่ใจการควบคุมแคลอรี่เป็นพิเศษ หลีกเลี่ยงอาหารที่ให้พลังงานสูงแต่มีสารอาหารต่ำ เช่น ขนมหวาน, น้ำอัดลม, <br>และอาหารฟาสต์ฟู้ด';
                                    break;
                                case 'โรคเบาหวาน':
                                    $specificAdvice = 'ควรควบคุมปริมาณ <strong>คาร์โบไฮเดรต (แป้งและน้ำตาล)</strong> ในแต่ละมื้อ, หลีกเลี่ยงน้ำหวานและผลไม้รสจัด, <br>และเลือกทานธัญพืชไม่ขัดสี เช่น ข้าวกล้อง ขนมปังโฮลวีท';
                                    break;
                                case 'โรคไต':
                                    $specificAdvice = 'ควรจำกัดอาหารที่มี <strong>โซเดียม</strong> สูง (เค็มจัด, ของหมักดอง), <strong>ฟอสฟอรัส</strong> สูง (นม, ถั่ว, น้ำอัดลมสีเข้ม), และ <strong>โพแทสเซียม</strong> สูง (ผลไม้บางชนิด เช่น กล้วย, ทุเรียน) <strong>*คำแนะนำสำหรับโรคไตควรอยู่ภายใต้การดูแลของแพทย์อย่างใกล้ชิด*</strong>';
                                    break;
                                case 'โรคไขมันในเลือดสูง':
                                    $specificAdvice = 'ควรหลีกเลี่ยงอาหารที่มี <strong>ไขมันอิ่มตัว</strong> และ <strong>ไขมันทรานส์</strong> สูง เช่น ของทอด, เบเกอรี่, เนื้อสัตว์ติดมัน, และน้ำมันปาล์ม ควรเน้นไขมันดีจากปลาทะเล, น้ำมันมะกอก, และถั่วต่างๆ';
                                    break;
                                case 'ความดันโลหิตสูง':
                                    $specificAdvice = 'ควรลดการบริโภคอาหารที่มี<strong>โซเดียม</strong>สูง (เค็มจัด) จำกัดปริมาณโซเดียมไม่เกิน 2,000 มิลลิกรัมต่อวัน และเน้นการทานผัก ผลไม้ และธัญพืชให้มากขึ้น';
                                    break;
                            }
                            if (!empty($specificAdvice)) {
                                $diseaseAdvice .= '<div class="alert btn-gradient3 mt-3 d-flex align-items-center shadow wow fadeInUp" role="alert" data-wow-delay="0.2s">
                                    <div style="font-size: 2.5rem; line-height: 1;"><i class="bi bi-heart-pulse-fill text-danger me-3"></i></div>
                                    <div class="ms-2">
                                        <h4 class="alert-heading">คำแนะนำสำหรับ: ' . $diseaseName . '</h4>
                                        <p class="mb-0">' . $specificAdvice . '</p>
                                    </div>
                                </div>';
                            }
                        }
                    }
                ?>
                <div class="alert <?php echo $adviceClass; ?> d-flex align-items-center shadow wow fadeInUp" role="alert" data-wow-delay="0.2s">
                    <div style="font-size: 2.5rem; line-height: 1;"><?php echo $adviceIcon; ?></div>
                    <div class="ms-2"><h4 class="alert-heading"><?php echo $adviceTitle; ?></h4><p class="mb-0"><?php echo $adviceMessage; ?></p></div>
                </div>



                <?php echo $diseaseAdvice; ?>
            <?php endif; ?>
        </div> 
    </section>
    </div>
    
    <div class="container mb-5">
        <section id="features">
            <h3 class="text-center mb-4 wow fadeInUp">ฟีเจอร์แนะนำ</h3>
            <div class="row g-4 flex-nowrap overflow-auto d-sm-flex flex-sm-wrap justify-content-sm-center" style="padding-bottom: 15px;">
                <a href="weekly_plan_dashboard.php" class="col-9 col-sm-6 col-lg-3 wow fadeInUp" data-wow-delay="0.1s"><div class="service-item rounded pt-3"><div class="service-item1 p-4 text-center shadow-sm border border-dark rounded h-100"> <i class="bi bi-robot mb-4" style="font-size: 4rem ; color: #2FAAA8"></i><h5>AI แนะนำแผนเมนู<br>ประจำสัปดาห์</h5><p style="color: #494949">แนะนำเมนูหลากหลาย <br>สุขภาพดี พร้อมคำแนะนำใน<br>การรับประทานอาหาร</p></div></div></a>
                <a href="custom_plan.php" class="col-9 col-sm-6 col-lg-3 wow fadeInUp" data-wow-delay="0.3s"><div class="service-item rounded pt-3"><div class="service-item2 p-4 text-center shadow-sm border border-dark rounded h-100"> <i class="bi bi-pencil-square mb-4" style="font-size: 4rem ; color: #ffb406"></i><h5>กำหนดแผนสุขภาพ<br>ของคุณเอง</h5><p style="color: #494949">ปรับแต่งแผนเมนูให้<br>ตรงเป้าหมายของคุณ<br>พร้อมดูสรุปโภชนาการทันที</p></div></div></a>
                <a href="recipes.php" class="col-9 col-sm-6 col-lg-3 wow fadeInUp" data-wow-delay="0.5s"><div class="service-item rounded pt-3"><div class="service-item3 p-4 text-center shadow-sm border border-dark rounded h-100"> <i class="bi bi-book-half mb-4" style="font-size: 4rem ; color: #B7D971"></i><h5>ค้นหาสูตร<br>อาหารทั้งหมด</h5><p style="color: #494949">รายการเมนูอาหารทั้งหมด<br>ในคลังของเราพร้อม<br>วิธีทำอย่างละเอียด</p></div></div></a>
                <a href="my_plans.php" class="col-9 col-sm-6 col-lg-3 wow fadeInUp" data-wow-delay="0.7s"><div class="service-item rounded pt-3 "><div class="service-item4 p-4 text-center shadow-sm border border-dark rounded h-100"> <i class="bi bi-clock-history mb-4" style="font-size: 4rem ; color: #7d71d9"></i><h5>ประวัติและ<br>แผนของฉัน</h5><p style="color: #494949">ดูแผนทั้งหมดและติดตาม<br>ความสำเร็จในการทำตาม<br>เป้าหมายของคุณ</p></div></div></a>
            </div>
        </section>
    </div>

    <div class="container mb-5 pb-5">
        <div class="text-center wow fadeInUp" data-wow-delay="0.1s">
            <h5 class="section-title ff-secondary text-center fw-normal" style="color: #2FAAA8;">เมนูอาหาร</h5>
            <h2 class="mb-5">เมนูแนะนำประจำวัน</h2>
        </div>
        <div class="tab-class text-center wow fadeInUp" data-wow-delay="0.1s">
            <ul class="nav nav-pills d-inline-flex justify-content-center border-bottom mb-5" role="tablist">
                <li class="nav-item"><a class="nav-link d-flex align-items-center text-start mx-3 ms-0 pb-3 active" data-bs-toggle="pill" href="#tab-1"><i class="fa fa-coffee fa-2x" style="color: #2FAAA8"></i><div class="ps-3"><small class="text-body">ช่วงเวลาเร่งรีบ</small><h6 class="mt-n1 mb-0">มื้อเช้า</h6></div></a></li>
                <li class="nav-item"><a class="nav-link d-flex align-items-center text-start mx-3 pb-3" data-bs-toggle="pill" href="#tab-2"><i class="fa fa-hamburger fa-2x" style="color: #ffb406"></i><div class="ps-3"><small class="text-body">ช่วงเวลาแสนพิเศษ</small><h6 class="mt-n1 mb-0">มื้อเที่ยง</h6></div></a></li>
                <li class="nav-item"><a class="nav-link d-flex align-items-center text-start mx-3 me-0 pb-3" data-bs-toggle="pill" href="#tab-3"><i class="fa fa-utensils fa-2x" style="color: #7d71d9"></i><div class="ps-3"><small class="text-body">ช่วงเวลาแห่งความรัก</small><h6 class="mt-n1 mb-0">มื้อเย็น</h6></div></a></li>
            </ul>
            <div class="tab-content">
                <div id="tab-1" class="tab-pane fade show active p-0"><div class="row g-4"><?php if (!empty($recommended_breakfasts)): foreach ($recommended_breakfasts as $menu): ?><div class="col-lg-6"><div class="d-flex align-items-center menu-item-hover p-3"><img class="flex-shrink-0 img-fluid rounded" src="<?php echo htmlspecialchars($menu['image_url']); ?>" alt="<?php echo htmlspecialchars($menu['name']); ?>" style="width: 80px; height: 80px; object-fit: cover;"><div class="w-100 d-flex flex-column text-start ps-4"><h5 class="d-flex justify-content-between border-bottom pb-2"><span><?php echo htmlspecialchars($menu['name']); ?></span><a href="recipe_detail.php?id=<?php echo $menu['id']; ?>" class="how-to btn-gradient2">ดูวิธีทำ</a></h5><small class="fst-italic"><?php echo htmlspecialchars($menu['description']); ?></small></div></div></div><?php endforeach; else: ?><p class="text-center text-muted">ไม่พบเมนูแนะนำสำหรับมื้อเช้า</p><?php endif; ?></div></div>
                <div id="tab-2" class="tab-pane fade p-0"><div class="row g-4"><?php if (!empty($recommended_lunches)): foreach ($recommended_lunches as $menu): ?><div class="col-lg-6"><div class="d-flex align-items-center menu-item-hover p-3"><img class="flex-shrink-0 img-fluid rounded" src="<?php echo htmlspecialchars($menu['image_url']); ?>" alt="<?php echo htmlspecialchars($menu['name']); ?>" style="width: 80px; height: 80px; object-fit: cover;"><div class="w-100 d-flex flex-column text-start ps-4"><h5 class="d-flex justify-content-between border-bottom pb-2"><span><?php echo htmlspecialchars($menu['name']); ?></span><a href="recipe_detail.php?id=<?php echo $menu['id']; ?>" class="how-to btn-gradient2">ดูวิธีทำ</a></h5><small class="fst-italic"><?php echo htmlspecialchars($menu['description']); ?></small></div></div></div><?php endforeach; else: ?><p class="text-center text-muted">ไม่พบเมนูแนะนำสำหรับมื้อกลางวัน</p><?php endif; ?></div></div>
                <div id="tab-3" class="tab-pane fade p-0"><div class="row g-4"><?php if (!empty($recommended_dinners)): foreach ($recommended_dinners as $menu): ?><div class="col-lg-6"><div class="d-flex align-items-center menu-item-hover p-3"><img class="flex-shrink-0 img-fluid rounded" src="<?php echo htmlspecialchars($menu['image_url']); ?>" alt="<?php echo htmlspecialchars($menu['name']); ?>" style="width: 80px; height: 80px; object-fit: cover;"><div class="w-100 d-flex flex-column text-start ps-4"><h5 class="d-flex justify-content-between border-bottom pb-2"><span><?php echo htmlspecialchars($menu['name']); ?></span><a href="recipe_detail.php?id=<?php echo $menu['id']; ?>" class="how-to btn-gradient2">ดูวิธีทำ</a></h5><small class="fst-italic"><?php echo htmlspecialchars($menu['description']); ?></small></div></div></div><?php endforeach; else: ?><p class="text-center text-muted">ไม่พบเมนูแนะนำสำหรับมื้อเย็น</p><?php endif; ?></div></div>
            </div>
        </div>
    </div>

    <div class="modal fade mini-calendar-modal" id="miniCalendarModal" tabindex="-1" aria-labelledby="miniCalendarModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="miniCalendarModalLabel">
                        <i class="bi bi-calendar-week me-2"></i>ภาพรวมแผนอาหาร
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mini-calendar-container" id="mini-calendar-content">
                        </div>
                    <div class="mt-3">
                        <div class="d-flex gap-3 justify-content-center flex-wrap">
                            <span><i class="bi bi-circle-fill" style="color: #d1e7dd;"></i> มีแผน</span>
                            <span><i class="bi bi-circle-fill" style="color: #cfe2ff;"></i> ทำเสร็จแล้ว</span>
                            <span><i class="bi bi-circle-fill" style="color: #fff3cd;"></i> วันปัจจุบัน</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="healthInfoModal" tabindex="-1" aria-labelledby="modalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle"><i class="fas fa-info-circle me-2"></i>หัวข้อข้อมูล</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="modalBody" style="line-height: 1.8;">
                </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
            </div>
            </div>
        </div>
    </div>

    

</main>

<!-- ✨ Modal แผนเสร็จสิ้น -->
<div class="modal fade" id="planCompletedModal" tabindex="-1" aria-labelledby="planCompletedModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="planCompletedModalLabel">
                    <i class="bi bi-trophy-fill me-2"></i>🎉 ยินดีด้วย!
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center py-4">
                <div class="trophy-animation mb-4">
                    <i class="bi bi-trophy-fill text-warning" style="font-size: 5rem;"></i>
                </div>
                
                <h4 class="mb-3">คุณทำแผนครบ 7 วันแล้ว! 🎊</h4>
                <p class="text-muted mb-4">
                    ระบบต้องการอัพเดทข้อมูลสุขภาพของคุณ<br>
                    เพื่อสร้างแผนใหม่ที่เหมาะสมยิ่งขึ้น
                </p>

                <div class="alert alert-info mb-0">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong>ขั้นตอนต่อไป:</strong><br>
                    1. กรอกข้อมูลสุขภาพปัจจุบัน<br>
                    2. ระบบจะสร้างแผนใหม่อัตโนมัติ<br>
                    3. คุณจะได้ดูรายละเอียดและเลือกนำไปใช้
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-1"></i>ปิด
                </button>
                <button type="button" class="btn btn-success btn-lg" id="goto-health-update-btn">
                    <i class="bi bi-clipboard2-pulse-fill me-2"></i>อัพเดทข้อมูลสุขภาพ
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="healthUpdateModal" tabindex="-1" aria-labelledby="healthUpdateModalLabel" aria-hidden="true" data-bs-backdrop="true" data-bs-keyboard="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="healthUpdateModalLabel">
                    <i class="bi bi-clipboard2-pulse-fill me-2"></i>อัพเดทข้อมูลสุขภาพ
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
<div class="modal-body">
    <form id="health-update-form" onsubmit="return false;">
        <div class="mb-4">
            <label for="current_weight" class="form-label fw-bold">
                <i class="bi bi-speedometer2 text-primary me-2"></i>น้ำหนักปัจจุบัน (kg) <span class="text-danger">*</span>
            </label>
            <input type="number" step="0.1" class="form-control form-control-lg" id="current_weight" name="current_weight" required>
            <div class="form-text">น้ำหนักเดิม: <strong><?php echo $profile['weight'] ?? 0; ?> kg</strong></div>
        </div>

        <div class="mb-4">
            <label class="form-label fw-bold">
                <i class="bi bi-heart-pulse text-danger me-2"></i>รู้สึกกับร่างกายอย่างไร?
            </label>
            <div class="btn-group w-100" role="group">
                <input type="radio" class="btn-check" name="body_feeling" id="feeling_better" value="better">
                <label class="btn btn-outline-success" for="feeling_better">
                    <i class="bi bi-emoji-smile"></i> ดีขึ้น
                </label>

                <input type="radio" class="btn-check" name="body_feeling" id="feeling_same" value="same" checked>
                <label class="btn btn-outline-secondary" for="feeling_same">
                    <i class="bi bi-emoji-neutral"></i> เหมือนเดิม
                </label>

                <input type="radio" class="btn-check" name="body_feeling" id="feeling_worse" value="worse">
                <label class="btn btn-outline-danger" for="feeling_worse">
                    <i class="bi bi-emoji-frown"></i> แย่ลง
                </label>
            </div>
        </div>

        <div class="mb-4">
            <label class="form-label fw-bold">
                <i class="bi bi-lightning-charge text-warning me-2"></i>ระดับพลังงาน
            </label>
            <div class="btn-group w-100" role="group">
                <input type="radio" class="btn-check" name="energy_level" id="energy_high" value="high">
                <label class="btn btn-outline-success" for="energy_high">
                    <i class="bi bi-battery-full"></i> สูง
                </label>

                <input type="radio" class="btn-check" name="energy_level" id="energy_medium" value="medium" checked>
                <label class="btn btn-outline-warning" for="energy_medium">
                    <i class="bi bi-battery-half"></i> ปานกลาง
                </label>

                <input type="radio" class="btn-check" name="energy_level" id="energy_low" value="low">
                <label class="btn btn-outline-danger" for="energy_low">
                    <i class="bi bi-battery"></i> ต่ำ
                </label>
            </div>
        </div>

        <div class="mb-4">
            <label for="plan_satisfaction" class="form-label fw-bold">
                <i class="bi bi-star-fill text-warning me-2"></i>ความพึงพอใจต่อแผน (1-5)
            </label>
            <input type="range" class="form-range" min="1" max="5" step="1" id="plan_satisfaction" name="plan_satisfaction" value="3">
            <div class="d-flex justify-content-between">
                <span class="text-muted">1 (น้อย)</span>
                <span id="satisfaction-value" class="fw-bold text-primary">3</span>
                <span class="text-muted">5 (มาก)</span>
            </div>
        </div>

        <div class="mb-3">
            <label for="additional_notes" class="form-label fw-bold">
                <i class="bi bi-chat-left-text text-info me-2"></i>บันทึกเพิ่มเติม (ถ้ามี)
            </label>
            <textarea class="form-control" id="additional_notes" name="additional_notes" rows="3" placeholder="เช่น อาการที่รู้สึก, ปัญหาที่พบ..."></textarea>
        </div>
    </form>
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" onclick="window.forceCleanModals()">
        <i class="bi bi-x-circle me-1"></i>ยกเลิก
    </button>
    <button type="button" class="btn btn-primary" id="submit-health-update-btn" onclick="submitHealthUpdate()">
        <i class="bi bi-check-circle me-1"></i>บันทึกและสร้างแผนใหม่
    </button>
</div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // ✅ [NEW] เชื่อม Modal "แผนเสร็จสิ้น" กับ Modal "อัพเดทข้อมูล"
    const gotoHealthUpdateBtn = document.getElementById('goto-health-update-btn');
    
    if (gotoHealthUpdateBtn) {
        gotoHealthUpdateBtn.addEventListener('click', function() {
            // ปิด Modal แผนเสร็จสิ้น
            const completionModal = bootstrap.Modal.getInstance(document.getElementById('planCompletedModal'));
            if (completionModal) {
                completionModal.hide();
            }
            
            // เปิด Modal อัพเดทข้อมูล
            setTimeout(() => {
                const healthModal = new bootstrap.Modal(document.getElementById('healthUpdateModal'));
                healthModal.show();
            }, 300);
        });
    }
        
    // ✅ เพิ่ม cleanup เมื่อปิด Health Update Modal
    const healthModal = document.getElementById('healthUpdateModal');
    if (healthModal) {
        healthModal.addEventListener('hidden.bs.modal', function () {
            setTimeout(() => {
                document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
                document.body.classList.remove('modal-open');
                document.body.style.overflow = '';
                document.body.style.paddingRight = '';
            }, 100);
        });
    }
    
    if (gotoHealthUpdateBtn) {
        gotoHealthUpdateBtn.addEventListener('click', function() {
            const completionModal = bootstrap.Modal.getInstance(document.getElementById('planCompletedModal'));
            if (completionModal) {
                completionModal.hide();
            }
            
            setTimeout(() => {
                const healthModal = new bootstrap.Modal(document.getElementById('healthUpdateModal'));
                healthModal.show();
            }, 300);
        });
    }
    
    // ✅ [REVISED] แสดง Modal แผนเสร็จสิ้นแทนปุ่ม
        function showPlanCompletionModal() {
            // ตรวจสอบว่าเคยแสดงแล้วหรือยัง
            const hasShownModal = localStorage.getItem(`plan_completed_modal_${storageKey}`);
            
            if (!hasShownModal) {
                console.log('🎉 Opening plan completion modal...');
                
                // บันทึกว่าแสดงแล้ว
                localStorage.setItem(`plan_completed_modal_${storageKey}`, 'true');
                
                // แสดง Modal
                const modal = new bootstrap.Modal(document.getElementById('planCompletedModal'), {
                    backdrop: 'static',
                    keyboard: false
                });
                modal.show();
            }
        }
        
        // ✅ [LEGACY] เก็บฟังก์ชันเดิมไว้สำหรับกรณีพิเศษ
        function showUpdateButton() {
            // ตรวจสอบว่ามีปุ่มถาวรอยู่แล้วหรือยัง
            if (document.getElementById('header-update-btn-permanent')) {
                console.log('✅ Permanent update button already exists');
                return;
            }
            
            // ตรวจสอบว่ามีปุ่มชั่วคราวแล้วหรือยัง
            if (document.getElementById('header-update-btn')) {
                console.log('✅ Temporary update button already exists');
                return;
            }
            
            const actionButtons = document.querySelector('.plan-action-buttons');
            if (actionButtons) {
                const updateBtn = document.createElement('button');
                updateBtn.id = 'header-update-btn';
                updateBtn.className = 'btn btn-warning btn-action';
                updateBtn.innerHTML = '<i class="bi bi-clipboard2-pulse"></i><span>อัพเดทข้อมูล</span>';
                updateBtn.onclick = function() {
                    openHealthUpdateModal();
                };
                
                const exitBtn = document.getElementById('exit-plan-btn');
                if (exitBtn) {
                    actionButtons.insertBefore(updateBtn, exitBtn);
                } else {
                    actionButtons.appendChild(updateBtn);
                }
                
                console.log('✅ Temporary update button created');
            }
        }
});
</script>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // === Typewriter Effect ===
    const lines = ["วางแผนมื้ออาหาร", "เพื่อสุขภาพของคุณ", "อย่างง่ายดาย"];
    let lineIndex = 0, charIndex = 0; 
    const speed = 80, hold = 4000;
    
    function typeLine() {
        const typewriterContainer = document.querySelector('.typewriter-container');
        if (!typewriterContainer || lineIndex >= lines.length) return;
        
        const lineEl = document.getElementById(`typewriter-line-${lineIndex}`);
        if (!lineEl) return;
        
        lineEl.classList.add('visible');
        const text = lines[lineIndex];
        
        if (charIndex < text.length) {
            lineEl.textContent += text.charAt(charIndex);
            charIndex++;
            setTimeout(typeLine, speed);
        } else {
            lineIndex++;
            charIndex = 0;
            if (lineIndex < lines.length) {
                setTimeout(typeLine, speed);
            } else {
                setTimeout(resetTyping, hold);
            }
        }
    }

    function resetTyping() {
        const typewriterContainer = document.querySelector('.typewriter-container');
        if (!typewriterContainer) return;
        
        for (let i = 0; i < lines.length; i++) {
            const el = document.getElementById(`typewriter-line-${i}`);
            if(el) el.textContent = '';
        }
        lineIndex = 0;
        charIndex = 0;
        setTimeout(typeLine, speed);
    }

    // === View Toggling ===
    const hasActivePlan = <?php echo json_encode($has_active_plan); ?>;
    const defaultBannerView = document.getElementById('default-banner-view');
    const progressTrackerView = document.getElementById('progress-tracker-view');
    const showProgressBtn = document.getElementById('show-progress-btn');
    const showBannerBtn = document.getElementById('show-banner-btn');

    if(showProgressBtn) {
        showProgressBtn.addEventListener('click', () => {
            defaultBannerView.style.display = 'none';
            progressTrackerView.style.display = 'block';
            showProgressBtn.style.display = 'none';
            showBannerBtn.style.display = 'flex';
        });
    }
    
    if(showBannerBtn) {
        showBannerBtn.addEventListener('click', () => {
            progressTrackerView.style.display = 'none';
            defaultBannerView.style.display = 'block';
            showBannerBtn.style.display = 'none';
            if(hasActivePlan) {
                showProgressBtn.style.display = 'flex';
            }
        });
    }

    // === Active Plan Logic ===
    if (hasActivePlan) {
        const storageKey = `planProgress_<?php echo $user_id; ?>_<?php echo $plan_days[0]['plan_date'] ?? ''; ?>`;

        // ✅ FIX: ตรวจสอบว่ามีแผนใหม่จาก URL หรือไม่
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('new_plan') === '1') {
            console.log('🆕 New plan detected - clearing all old localStorage');
            
            // ล้าง localStorage key เก่าทั้งหมด
            const userId = <?php echo $user_id; ?>;
            Object.keys(localStorage).forEach(key => {
                if (key.startsWith(`planProgress_${userId}_`)) {
                    localStorage.removeItem(key);
                    console.log('🗑️ Cleared old key:', key);
                }
            });
            
            // ลบ parameter ออกจาก URL
            window.history.replaceState({}, document.title, window.location.pathname + '?plan_activated=1');
        }
        const totalMealsInPlan = <?php echo $total_meals_in_plan; ?>;
        let planState = {};
        let completedMeals = 0;
        let completedRecipes = 0;
        let currentDayIndex = 0;

        const progressBar = document.getElementById('plan-progress-bar');
        const progressPercentageText = document.getElementById('plan-progress-percentage');

        // === Helper Functions ===
        function saveProgress() {
            const progressToSave = {
                planState: planState,
                completedMeals: completedMeals,
                completedRecipes: completedRecipes,
                currentDayIndex: currentDayIndex
            };
            localStorage.setItem(storageKey, JSON.stringify(progressToSave));
        }

        function loadProgress() {
            const savedProgress = localStorage.getItem(storageKey);
            if (savedProgress) {
                try {
                    const progressData = JSON.parse(savedProgress);
                    planState = progressData.planState || {};
                    completedMeals = progressData.completedMeals || 0;
                    completedRecipes = progressData.completedRecipes || 0;
                    currentDayIndex = progressData.currentDayIndex || 0;
                    
                    console.log('✅ Progress loaded:', {
                        completedMeals,
                        completedRecipes,
                        currentDayIndex,
                        planState
                    });
                    
                    if (Object.keys(planState).length === 0) {
                        console.warn('⚠️ planState is empty, initializing...');
                        initializePlanState();
                    }
                } catch (error) {
                    console.error('❌ Error loading progress:', error);
                    initializePlanState();
                }
            } else {
                console.log('ℹ️ No saved progress found, initializing...');
                initializePlanState();
            }
        }

        function initializePlanState() {
            planState = {};
            planDaysArray.forEach(day => {
                planState[day.date] = { recipeIndex: 0 };
            });
            completedMeals = 0;
            completedRecipes = 0;
            saveProgress();
        }

        function updatePlanProgress() {
            if (totalMealsInPlan === 0) return;
            const percentage = Math.min(100, Math.round((completedMeals / totalMealsInPlan) * 100));
            progressBar.style.width = percentage + '%';
            progressBar.setAttribute('aria-valuenow', percentage);
            progressPercentageText.textContent = percentage + '%';
            
            const progressText = progressBar.querySelector('.progress-text');
            if (progressText) {
                progressText.textContent = percentage >= 15 ? `${completedMeals}/${totalMealsInPlan} มื้อ` : '';
            }
        }

        function countRecipesInDay(planData) {
            if (!planData || typeof planData !== 'object') return 0;
            let count = 0;
            const mealOrder = ['มื้อเช้า', 'มื้อว่างเช้า', 'มื้อกลางวัน', 'มื้อว่างบ่าย', 'มื้อเย็น'];
            
            for (const key of mealOrder) {
                if (planData[key]) {
                    if (Array.isArray(planData[key])) {
                        count += planData[key].length;
                    } else if (planData[key].id || planData[key].recipe_name || planData[key].name) {
                        count += 1;
                    }
                }
            }
            return count;
        }

        function getRecipeAtIndex(planData, globalRecipeIndex) {
            if (!planData || typeof planData !== 'object') return null;
            
            const mealOrder = ['มื้อเช้า', 'มื้อว่างเช้า', 'มื้อกลางวัน', 'มื้อว่างบ่าย', 'มื้อเย็น'];
            let currentIndex = 0;
            
            for (const mealKey of mealOrder) {
                if (!planData[mealKey]) continue;
                
                const recipes = Array.isArray(planData[mealKey]) ? planData[mealKey] : [planData[mealKey]];
                
                for (let i = 0; i < recipes.length; i++) {
                    if (currentIndex === globalRecipeIndex) {
                        return {
                            mealKey: mealKey,
                            recipe: recipes[i],
                            recipeIndexInMeal: i,
                            totalInMeal: recipes.length
                        };
                    }
                    currentIndex++;
                }
            }
            return null;
        }

        // === Exit Plan ===
        const exitPlanBtn = document.getElementById('exit-plan-btn');
        if (exitPlanBtn) {
            exitPlanBtn.addEventListener('click', function() {
                if (confirm('คุณแน่ใจหรือไม่ว่าต้องการออกจากแผนปัจจุบัน? การกระทำนี้ไม่สามารถย้อนกลับได้')) {
                    exitPlanBtn.disabled = true;
                    exitPlanBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> กำลังลบ...';
                    
                    fetch('process/exit_plan.php', { method: 'POST' })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                localStorage.removeItem(storageKey);
                                alert(data.message);
                                window.location.reload();
                            } else { 
                                throw new Error(data.message); 
                            }
                        })
                        .catch(error => {
                            alert('เกิดข้อผิดพลาด: ' + error.message);
                            exitPlanBtn.disabled = false;
                            exitPlanBtn.innerHTML = '<i class="bi bi-box-arrow-right"></i> ออกจากแผน';
                        });
                }
            });
        }

       
// === [FIX] Initialize Plan Days with Validation ===
    const mealOrder = ['มื้อเช้า', 'มื้อว่างเช้า', 'มื้อกลางวัน', 'มื้อว่างบ่าย', 'มื้อเย็น'];
    const dayCards = document.querySelectorAll('.progress-day-card');
    
    const planDaysArray = Array.from(dayCards).map((card, cardIndex) => {
        let planData = {};
        try {
            const rawPlan = card.dataset.plan;
            if (rawPlan && rawPlan !== 'null' && rawPlan !== '[]') {
                planData = JSON.parse(rawPlan);
                const mealCount = Object.keys(planData).length;
                console.log(`✅ Card ${cardIndex + 1} (${card.dataset.date}): ${mealCount} meals`, planData);
            } else {
                console.warn(`⚠️ Card ${cardIndex + 1} (${card.dataset.date}): Empty plan data`);
            }
        } catch (e) {
            console.error(`❌ Card ${cardIndex + 1} (${card.dataset.date}): Parse error`, e);
        }
        
        return {
            date: card.dataset.date,
            dayIndex: parseInt(card.dataset.dayIndex) || 0,
            plan: planData,
            card: card
        };
    });

    if (planDaysArray.length === 0) {
        console.error('❌ No plan days found!');
    } else {
        console.log(`✅ Loaded ${planDaysArray.length} plan days`);
        
        // ✅ [DEBUG] แสดงข้อมูลแต่ละวัน
        planDaysArray.forEach((day, i) => {
            const meals = Object.keys(day.plan || {});
            console.log(`Day ${i + 1}: ${day.date} - ${meals.length} meals [${meals.join(', ')}]`);
        });
    }

        // โหลด Progress
        loadProgress();
        updatePlanProgress();

        // === Mobile vs Desktop Setup ===
        const isMobile = window.innerWidth <= 768;
        
        if (isMobile) {
            // Mobile: Load saved day or find first incomplete
            let savedIndex = currentDayIndex;
            let isValidIndex = false;
            
            if (savedIndex >= 0 && savedIndex < planDaysArray.length) {
                const savedDate = planDaysArray[savedIndex].date;
                if (!planState[savedDate]) {
                    planState[savedDate] = { recipeIndex: 0 };
                }
                const planData = planDaysArray[savedIndex].plan;
                const totalRecipes = countRecipesInDay(planData);
                const completedCount = planState[savedDate].recipeIndex || 0;
                
                if (completedCount < totalRecipes) {
                    currentDayIndex = savedIndex;
                    isValidIndex = true;
                }
            }
            
            if (!isValidIndex) {
                let foundIncomplete = false;
                for (let i = 0; i < planDaysArray.length; i++) {
                    const date = planDaysArray[i].date;
                    if (!planState[date]) {
                        planState[date] = { recipeIndex: 0 };
                    }
                    const planData = planDaysArray[i].plan;
                    const totalRecipes = countRecipesInDay(planData);
                    const completedCount = planState[date].recipeIndex || 0;
                    
                    if (completedCount < totalRecipes) {
                        currentDayIndex = i;
                        foundIncomplete = true;
                        break;
                    }
                }
                if (!foundIncomplete && planDaysArray.length > 0) {
                    currentDayIndex = planDaysArray.length - 1;
                }
            }
            
            saveProgress();
            updateMobileDayView();
            setupMobileNavigation();
            
        } else {
            // Desktop: Update all cards
            dayCards.forEach((card, index) => {
                const date = card.dataset.date;
                if (!planState[date]) {
                    planState[date] = { recipeIndex: 0 };
                }
                updateDayCard(date);
            });

            // Find and scroll to active day
            let activeCardIndex = -1;
            for (let i = 0; i < planDaysArray.length; i++) {
                const date = planDaysArray[i].date;
                const planData = planDaysArray[i].plan;
                const totalRecipes = countRecipesInDay(planData);
                const completedCount = planState[date]?.recipeIndex || 0;
                
                if (completedCount < totalRecipes) {
                    activeCardIndex = i;
                    break;
                }
            }
            
            if (activeCardIndex === -1 && planDaysArray.length > 0) {
                activeCardIndex = planDaysArray.length - 1;
            }
            
            if (activeCardIndex >= 0 && planDaysArray[activeCardIndex]) {
                const activeCard = planDaysArray[activeCardIndex].card;
                activeCard.classList.add('active-day');
                
                setTimeout(() => {
                    activeCard.scrollIntoView({ 
                        behavior: 'smooth', 
                        block: 'nearest', 
                        inline: 'center' 
                    });
                }, 300);
            }
        }

        // === Mobile Functions ===
        function updateMobileDayView() {
            if (currentDayIndex < 0 || currentDayIndex >= planDaysArray.length) {
                console.error('Invalid day index:', currentDayIndex);
                return;
            }
            
            const currentDay = planDaysArray[currentDayIndex];
            const date = currentDay.date;
            const dateObj = new Date(date + 'T00:00:00');
            
            const titleEl = document.getElementById('current-day-title');
            const dateEl = document.getElementById('current-day-date');
            
            if (titleEl) titleEl.textContent = `วันที่ ${currentDayIndex + 1}`;
            if (dateEl) {
                dateEl.textContent = dateObj.toLocaleDateString('th-TH', { 
                    day: 'numeric', 
                    month: 'short', 
                    year: 'numeric' 
                });
            }

            const prevBtn = document.getElementById('prev-day-btn');
            const nextBtn = document.getElementById('next-day-btn');
            
            if (prevBtn) prevBtn.disabled = (currentDayIndex === 0);
            if (nextBtn) nextBtn.disabled = (currentDayIndex === planDaysArray.length - 1);

            renderMobileDayContent(date, currentDay.plan);
        }

        function renderMobileDayContent(date, planData) {
            const container = document.getElementById('mobile-day-content');
            if (!container) {
                console.error('Mobile day content container not found');
                return;
            }
            container.innerHTML = '';

            if (!planData || typeof planData !== 'object' || Object.keys(planData).length === 0) {
                container.innerHTML = `
                    <div class="text-center py-4">
                        <i class="bi bi-slash-circle" style="font-size: 3rem; color: #6c757d;"></i>
                        <p class="mt-2 text-muted">ไม่มีแผนสำหรับวันนี้</p>
                    </div>`;
                return;
            }

            if (!planState[date]) {
                planState[date] = { recipeIndex: 0 };
            }

            const totalRecipes = countRecipesInDay(planData);
            const completedCount = planState[date].recipeIndex || 0;

            if (completedCount >= totalRecipes) {
                const isLastDay = currentDayIndex === planDaysArray.length - 1;
                container.innerHTML = `
                    <div class="text-center py-4">
                        <div class="success-animation mb-3">
                            <i class="bi bi-check-circle-fill text-success" style="font-size: 4rem;"></i>
                        </div>
                        <h4 class="fw-bold text-success mb-3">🎉 ยินดีด้วย!</h4>
                        <p class="text-muted mb-3">คุณทำครบทุกเมนูในวันนี้แล้ว</p>
                        
                        <div class="stats-summary mb-4">
                            <div class="stat-item">
                                <i class="bi bi-calendar-check text-primary"></i>
                                <span>${totalRecipes}/${totalRecipes} เมนู</span>
                            </div>
                        </div>

                        ${!isLastDay ? `
                            <button class="btn btn-primary btn-lg w-100" onclick="goToNextDay()">
                                <i class="bi bi-arrow-right-circle me-2"></i>ไปวันถัดไป
                            </button>
                        ` : `
                            <div class="alert alert-success">
                                <i class="bi bi-trophy-fill me-2"></i>
                                คุณทำสำเร็จทุกวันแล้ว!
                            </div>
                        `}
                    </div>
                `;
                return;
            }

            const currentRecipeData = getRecipeAtIndex(planData, completedCount);
            if (!currentRecipeData) {
                console.error('Could not find recipe at index:', completedCount);
                return;
            }

            planState[date].recipeIndex = completedCount;

            let progressHTML = '<div class="meal-progress-container mb-3">';
            progressHTML += '<div class="d-flex justify-content-between align-items-center mb-2">';
            progressHTML += `<small class="text-muted">ความคืบหน้าวันนี้</small>`;
            progressHTML += `<small class="fw-bold text-primary">${completedCount}/${totalRecipes} เมนู</small>`;
            progressHTML += '</div>';
            progressHTML += '<div class="meal-dots">';
            
            for (let i = 0; i < totalRecipes; i++) {
                const isDone = i < completedCount;
                const isCurrent = i === completedCount;
                progressHTML += `<div class="meal-dot ${isDone ? 'done' : ''} ${isCurrent ? 'current' : ''}"></div>`;
            }
            progressHTML += '</div></div>';

            const recipe = currentRecipeData.recipe;
            const mealKey = currentRecipeData.mealKey;
            const recipeNum = currentRecipeData.recipeIndexInMeal + 1;
            const totalInMeal = currentRecipeData.totalInMeal;

            container.innerHTML = progressHTML + `
                <div class="current-meal-section">
                    <div class="meal-header mb-3">
                        <h5 class="mb-1">${mealKey}</h5>
                        <small class="text-muted">เมนูที่ ${recipeNum}/${totalInMeal} ในมื้อนี้ (เมนูที่ ${completedCount + 1}/${totalRecipes} ของวัน)</small>
                    </div>

                    ${recipe && (recipe.recipe_name || recipe.name) ? `
                        <div class="meal-card">
                            <div style="text-align: center; background-color: #f8f9fa;">
                                <img src="${recipe.image_url}" alt="${recipe.recipe_name || recipe.name}" 
                                    class="meal-image"
                                    style="margin: 0 auto; display: block;"
                                    onerror="this.src='https://placehold.co/400x300/e2e8f0/64748b?text=No+Image';">
                            </div>
                            
                            <div class="meal-info-section">
                                <h6 class="meal-name">${recipe.recipe_name || recipe.name}</h6>
                                <div class="meal-calories">
                                    <i class="bi bi-fire text-danger me-1"></i>
                                    ${recipe.calories} kcal
                                </div>
                            </div>

                            <button class="check-meal-btn" onclick="markRecipeAsDone('${date}')">
                                <div class="check-circle">
                                    <i class="bi bi-check"></i>
                                </div>
                                <span>ทานแล้ว</span>
                            </button>
                        </div>
                    ` : `
                        <div class="meal-card no-recipe">
                            <p class="text-muted mb-3">ไม่มีข้อมูลเมนูสำหรับรายการนี้</p>
                            <button class="check-meal-btn secondary" onclick="markRecipeAsDone('${date}')">
                                <div class="check-circle">
                                    <i class="bi bi-check"></i>
                                </div>
                                <span>ข้าม</span>
                            </button>
                        </div>
                    `}
                </div>
            `;
        }

        function setupMobileNavigation() {
            const prevBtn = document.getElementById('prev-day-btn');
            const nextBtn = document.getElementById('next-day-btn');
            
            if (prevBtn) {
                prevBtn.addEventListener('click', function() {
                    if (currentDayIndex > 0) {
                        currentDayIndex--;
                        updateMobileDayView();
                    }
                });
            }
            
            if (nextBtn) {
                nextBtn.addEventListener('click', function() {
                    if (currentDayIndex < planDaysArray.length - 1) {
                        currentDayIndex++;
                        updateMobileDayView();
                    }
                });
            }
        }

        // === Desktop Functions ===
        function updateDayCard(date) {
            const card = document.getElementById(`progress-card-${date}`);
            if (!card) return;
            
            const planData = JSON.parse(card.dataset.plan);
            const mealContentDiv = card.querySelector('.meal-content');
            mealContentDiv.innerHTML = '';

            card.querySelectorAll('.meal-icons i').forEach(icon => {
                icon.classList.remove('active-breakfast', 'active-brunch', 'active-lunch', 'active-snack', 'active-dinner');
            });

            if (!planData || typeof planData !== 'object' || Object.keys(planData).length === 0) {
                mealContentDiv.innerHTML = `<div class="day-completed-message"><i class="bi bi-slash-circle" style="font-size: 3rem; color: #6c757d;"></i><span class="mt-2 fw-bold text-muted">ไม่มีแผน</span></div>`;
                return; 
            }

            if (!planState[date]) {
                planState[date] = { recipeIndex: 0 };
            }

            const totalRecipes = countRecipesInDay(planData);
            const completedCount = planState[date].recipeIndex || 0;

            if (completedCount >= totalRecipes) {
                mealContentDiv.innerHTML = `<div class="day-completed-message"><i class="bi bi-check-circle-fill"></i><span class="mt-2 fw-bold">สำเร็จแล้ว!</span></div>`;
                card.classList.add('completed');
                setTimeout(checkPlanCompletion, 300);
                return;
            }

            const currentRecipeData = getRecipeAtIndex(planData, completedCount);
            if (!currentRecipeData) return;

            planState[date].recipeIndex = completedCount;
            
            const currentMealKey = currentRecipeData.mealKey;
            const iconMap = { 
                'มื้อเช้า': 'breakfast', 
                'มื้อว่างเช้า': 'brunch', 
                'มื้อกลางวัน': 'lunch', 
                'มื้อว่างบ่าย': 'snack', 
                'มื้อเย็น': 'dinner'
            };
            
            if (iconMap[currentMealKey]) {
                const mealKey = iconMap[currentMealKey];
                const iconId = `icon-${mealKey}-${date}`;
                const activeIcon = document.getElementById(iconId);
                if(activeIcon) {
                    activeIcon.classList.add(`active-${mealKey}`);
                }
            }

            const recipe = currentRecipeData.recipe;
            const recipeNum = currentRecipeData.recipeIndexInMeal + 1;
            const totalInMeal = currentRecipeData.totalInMeal;
            
            if (recipe && (recipe.recipe_name || recipe.name)) {
                mealContentDiv.innerHTML = `
                    <img src="${recipe.image_url}" alt="${recipe.recipe_name || recipe.name}" onerror="this.src='https://placehold.co/400x300/e2e8f0/e2e8f0?text=Image';">
                    <input class="form-check-input meal-checkbox" type="checkbox" onchange="markRecipeAsDone('${date}')">
                    <div class="meal-info">
                        <h6>${recipe.recipe_name || recipe.name}</h6>
                        <span class="calories">${recipe.calories} kcal</span>
                        ${totalInMeal > 1 ? `<small class="text-muted d-block">${recipeNum}/${totalInMeal} ในมื้อนี้</small>` : ''}
                    </div>`;
            } else {
                mealContentDiv.innerHTML = `<div class="day-completed-message"><span class="text-muted">ไม่มีเมนูสำหรับรายการนี้</span><button class="btn btn-sm btn-light mt-2" onclick="markRecipeAsDone('${date}')">ข้าม</button></div>`;
            }
        }

        // === Mark Recipe as Done ===
        window.markRecipeAsDone = function(date) {
            if (!planState[date]) {
                console.error('Plan state not found for date:', date);
                return;
            }
            
            const oldRecipeIndex = planState[date].recipeIndex || 0;
            completedRecipes++;
            planState[date].recipeIndex++;
            
            const currentDay = planDaysArray.find(d => d.date === date);
            if (currentDay) {
                const planData = currentDay.plan;
                const currentRecipeData = getRecipeAtIndex(planData, oldRecipeIndex);
                
                if (currentRecipeData) {
                    const nextRecipeData = getRecipeAtIndex(planData, planState[date].recipeIndex);
                    if (!nextRecipeData || nextRecipeData.mealKey !== currentRecipeData.mealKey) {
                        completedMeals++;
                        console.log(`✅ Completed meal: ${currentRecipeData.mealKey} (Total: ${completedMeals}/${totalMealsInPlan})`);
                    }
                }
            }
            
            updatePlanProgress();
            console.log(`📊 Progress: ${completedMeals}/${totalMealsInPlan} meals, ${completedRecipes} recipes`);
            
            const isMobileView = window.innerWidth <= 768;
            
            if (isMobileView) {
                const currentDay = planDaysArray[currentDayIndex];
                if (!currentDay) return;
                
                const planData = currentDay.plan;
                const container = document.getElementById('mobile-day-content');
                
                if (container) {
                    container.style.opacity = '0.5';
                    setTimeout(() => {
                        container.style.opacity = '1';
                        renderMobileDayContent(date, planData);
                    }, 300);
                } else {
                    renderMobileDayContent(date, planData);
                }
            } else {
                updateDayCard(date);
                
                const card = document.getElementById(`progress-card-${date}`);
                if (card && card.classList.contains('completed')) {
                    card.classList.remove('active-day');
                    const nextCard = card.nextElementSibling;
                    if (nextCard && nextCard.classList.contains('progress-day-card')) {
                        nextCard.classList.add('active-day');
                        nextCard.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
                    }
                }
            }
            
            saveProgress();
            setTimeout(() => {
                console.log('🔍 Initial plan completion check...');
                checkPlanCompletion();
            }, 1000);
        };

        window.goToNextDay = function() {
            if (currentDayIndex < planDaysArray.length - 1) {
                currentDayIndex++;
                saveProgress();
                updateMobileDayView();
                
                window.scrollTo({ top: 0, behavior: 'smooth' });
                
                const container = document.getElementById('mobile-day-content');
                if (container) {
                    container.style.opacity = '0';
                    setTimeout(() => {
                        container.style.opacity = '1';
                    }, 300);
                }
            }
        };

        // === Calendar View ===
        const calendarViewBtn = document.getElementById('calendar-view-btn');
        if (calendarViewBtn) {
            calendarViewBtn.addEventListener('click', showMiniCalendar);
        }

        function showMiniCalendar() {
            const modal = new bootstrap.Modal(document.getElementById('miniCalendarModal'));
            renderMiniCalendar();
            modal.show();
        }

        function renderMiniCalendar() {
            const container = document.getElementById('mini-calendar-content');
            
            if (planDaysArray.length === 0) {
                container.innerHTML = '<p class="text-center text-muted">ไม่มีข้อมูลแผน</p>';
                return;
            }

            const firstDate = new Date(planDaysArray[0].date + 'T00:00:00');
            const lastDate = new Date(planDaysArray[planDaysArray.length - 1].date + 'T00:00:00');
            
            const startOfMonth = new Date(firstDate.getFullYear(), firstDate.getMonth(), 1);
            const endOfMonth = new Date(lastDate.getFullYear(), lastDate.getMonth() + 1, 0);
            
            const startDay = startOfMonth.getDay();
            
            let calendarHTML = `
                <table class="mini-calendar-table">
                    <thead>
                        <tr>
                            <th>อา</th><th>จ</th><th>อ</th><th>พ</th><th>พฤ</th><th>ศ</th><th>ส</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            let currentDate = new Date(startOfMonth);
            currentDate.setDate(currentDate.getDate() - startDay);
            
            while (currentDate <= endOfMonth) {
                calendarHTML += '<tr>';
                for (let i = 0; i < 7; i++) {
                    const dateStr = currentDate.toISOString().split('T')[0];
                    const dayData = planDaysArray.find(d => d.date === dateStr);
                    const isInMonth = currentDate.getMonth() === firstDate.getMonth();
                    
                    let classes = ['mini-calendar-day'];
                    let status = '';
                    
                    if (dayData && isInMonth) {
                        classes.push('has-plan');
                        
                        const totalRecipes = countRecipesInDay(dayData.plan);
                        const completedCount = planState[dateStr]?.recipeIndex || 0;
                        
                        if (completedCount >= totalRecipes) {
                            classes.push('completed');
                            status = '✓';
                        }
                        
                        if (dateStr === planDaysArray[currentDayIndex].date) {
                            classes.push('active-day');
                        }
                    } else if (!isInMonth) {
                        classes.push('disabled');
                    }
                    
                    calendarHTML += `
                        <td>
                            <div class="${classes.join(' ')}" 
                                 ${dayData ? `onclick="jumpToDay('${dateStr}')"` : ''}>
                                <div class="day-number">${currentDate.getDate()}</div>
                                ${status ? `<div class="day-status">${status}</div>` : ''}
                            </div>
                        </td>
                    `;
                    
                    currentDate.setDate(currentDate.getDate() + 1);
                }
                calendarHTML += '</tr>';
            }
            
            calendarHTML += '</tbody></table>';
            container.innerHTML = calendarHTML;
        }

        window.jumpToDay = function(dateStr) {
            const dayIndex = planDaysArray.findIndex(d => d.date === dateStr);
            if (dayIndex !== -1) {
                if (isMobile) {
                    currentDayIndex = dayIndex;
                    saveProgress();
                    updateMobileDayView();
                } else {
                    dayCards.forEach(card => card.classList.remove('active-day'));
                    const targetCard = document.getElementById(`progress-card-${dateStr}`);
                    if (targetCard) {
                        targetCard.classList.add('active-day');
                        targetCard.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
                    }
                }
                
                bootstrap.Modal.getInstance(document.getElementById('miniCalendarModal')).hide();
            }
        }

        // === Charts ===
        const planDaysData = <?php echo json_encode($plan_days); ?>;
        const labels = planDaysData.map(day => new Date(day.plan_date).toLocaleDateString('th-TH', { day: 'numeric', month: 'short' }));

        const caloriesReceivedData = planDaysData.map(day => day.total_calories_calculated); 
        const weightLogData = Array(labels.length).fill(<?php echo $profile['weight'] ?? 0; ?>); 
        const sodiumData = planDaysData.map(day => day.total_sodium);
        const sugarData = planDaysData.map(day => day.total_sugar);
        const fatData = planDaysData.map(day => day.total_fat);
        const cholesterolData = planDaysData.map(day => day.total_cholesterol);

        if (window.myCaloriesChart) window.myCaloriesChart.destroy();
        if (window.myWeightChart) window.myWeightChart.destroy();
        if (window.mySodiumChart) window.mySodiumChart.destroy();
        if (window.mySugarChart) window.mySugarChart.destroy();
        if (window.myFatChart) window.myFatChart.destroy();
        if (window.myCholesterolChart) window.myCholesterolChart.destroy();

        const chartOptions = {
            responsive: true,
            maintainAspectRatio: true,
            aspectRatio: isMobile ? 1.5 : 2,
            plugins: {
                legend: {
                    display: !isMobile,
                    position: 'top',
                    labels: { 
                        boxWidth: isMobile ? 10 : 12,
                        font: { size: isMobile ? 10 : 12 }
                    }
                },
                tooltip: {
                    enabled: true,
                    titleFont: { size: isMobile ? 11 : 13 },
                    bodyFont: { size: isMobile ? 10 : 12 }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { font: { size: isMobile ? 9 : 11 } }
                },
                x: {
                    ticks: {
                        font: { size: isMobile ? 9 : 11 },
                        maxRotation: isMobile ? 45 : 0,
                        minRotation: isMobile ? 45 : 0
                    }
                }
            }
        };

        const ctxCalories = document.getElementById('caloriesReceivedChart');
        if (ctxCalories) {
            window.myCaloriesChart = new Chart(ctxCalories.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'แคลอรี่ (kcal)',
                        data: caloriesReceivedData,
                        backgroundColor: 'rgba(54, 162, 235, 0.5)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1
                    }]
                },
                options: chartOptions
            });
        }

        const ctxWeight = document.getElementById('weightLogChart');
        if (ctxWeight) {
            window.myWeightChart = new Chart(ctxWeight.getContext('2d'), {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'น้ำหนัก (ก.)',
                        data: weightLogData,
                        fill: false,
                        borderColor: 'rgb(255, 99, 132)',
                        tension: 0.1
                    }]
                },
                options: chartOptions
            });
        }

        function createNutrientChart(canvasId, label, data, limit, lineColor, limitLabel) {
            const ctx = document.getElementById(canvasId);
            if (ctx) {
                return new Chart(ctx.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [
                            {
                                label: label,
                                data: data,
                                borderColor: lineColor,
                                tension: 0.1,
                                fill: false,
                                order: 2
                            },
                            {
                                label: limitLabel,
                                data: Array(labels.length).fill(limit),
                                type: 'line',
                                borderColor: 'rgba(255, 99, 132, 1)',
                                borderWidth: 2,
                                borderDash: [5, 5],
                                fill: false,
                                pointRadius: 0,
                                order: 1
                            }
                        ]
                    },
                    options: chartOptions
                });
            }
        }

        window.mySodiumChart = createNutrientChart('sodiumChart', 'โซเดียม (mg)', sodiumData, 2000, 'rgba(255, 159, 64, 1)', 'เป้าหมายไม่เกิน 2000mg');
        window.mySugarChart = createNutrientChart('sugarChart', 'น้ำตาล (g)', sugarData, 30, 'rgba(153, 102, 255, 1)', 'เป้าหมายไม่เกิน 30g');
        window.myFatChart = createNutrientChart('fatChart', 'ไขมัน (g)', fatData, 65, 'rgba(255, 206, 86, 1)', 'เป้าหมายไม่เกิน 65g');
        window.myCholesterolChart = createNutrientChart('cholesterolChart', 'คอเลสเตอรอล (mg)', cholesterolData, 300, 'rgba(75, 192, 192, 1)', 'เป้าหมายไม่เกิน 300mg');

        // === [FIXED] Plan Completion Check - ปรับปรุงใหม่ ===
        function checkPlanCompletion() {
            const totalDays = planDaysArray.length;
            let completedDaysCount = 0;
            
            console.log('🔍 Checking plan completion...');
            
            planDaysArray.forEach(day => {
                const date = day.date;
                const planData = day.plan;
                const totalRecipesInDay = countRecipesInDay(planData);
                const completedRecipesInDay = planState[date]?.recipeIndex || 0;
                
                if (totalRecipesInDay > 0 && completedRecipesInDay >= totalRecipesInDay) {
                    completedDaysCount++;
                    console.log(`✅ Day ${date} completed: ${completedRecipesInDay}/${totalRecipesInDay}`);
                } else {
                    console.log(`⏳ Day ${date} in progress: ${completedRecipesInDay}/${totalRecipesInDay}`);
                }
            });

            console.log(`📅 Plan completion: ${completedDaysCount}/${totalDays} days`);

            const isPlanCompleted = (totalDays > 0 && completedDaysCount >= totalDays);
            
            if (isPlanCompleted) {
                console.log("🎉 ALL DAYS COMPLETED!");
                
                // แสดง Modal เฉพาะถ้ายังไม่เคยแสดง
                const userId = <?php echo $user_id; ?>;
                const modalShownKey = `plan_completed_shown_${userId}`;
                const hasShownModal = localStorage.getItem(modalShownKey);
                
                if (!hasShownModal) {
                    console.log('🎉 Opening plan completion modal...');
                    localStorage.setItem(modalShownKey, 'true');
                    
                    const modal = new bootstrap.Modal(document.getElementById('planCompletedModal'), {
                        backdrop: 'static',
                        keyboard: false
                    });
                    modal.show();
                }
                
                // 🆕 [FIX] แสดงปุ่มอัพเดทข้อมูลเสมอเมื่อแผนเสร็จ
                showUpdateButton();
            }
        }
        
        // 🆕 [FIX] ฟังก์ชันแสดงปุ่มอัพเดทข้อมูล
        function showUpdateButton() {
            // ตรวจสอบว่ามีปุ่มถาวรอยู่แล้วหรือยัง
            if (document.getElementById('header-update-btn-permanent')) {
                console.log('✅ Permanent update button already exists');
                return;
            }
            
            // ตรวจสอบว่ามีปุ่มชั่วคราวแล้วหรือยัง
            if (document.getElementById('header-update-btn')) {
                console.log('✅ Temporary update button already exists');
                return;
            }
            
            const actionButtons = document.querySelector('.plan-action-buttons');
            if (actionButtons) {
                const updateBtn = document.createElement('button');
                updateBtn.id = 'header-update-btn';
                updateBtn.className = 'btn btn-warning btn-action';
                updateBtn.innerHTML = '<i class="bi bi-clipboard2-pulse"></i><span>อัพเดทข้อมูล</span>';
                updateBtn.onclick = function() {
                    openHealthUpdateModal();
                };
                
                // แทรกก่อนปุ่ม "ออกจากแผน"
                const exitBtn = document.getElementById('exit-plan-btn');
                if (exitBtn) {
                    actionButtons.insertBefore(updateBtn, exitBtn);
                } else {
                    actionButtons.appendChild(updateBtn);
                }
                
                console.log('✅ Update button created and added');
            }
        }

        // ฟังก์ชันแสดง Toast Notification
        function showCompletionToast() {
            // สร้าง Toast Element
            const toastHTML = `
                <div class="position-fixed top-0 end-0 p-3" style="z-index: 9999;">
                    <div id="completionToast" class="toast align-items-center text-white bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true" data-bs-autohide="true" data-bs-delay="8000">
                        <div class="d-flex">
                            <div class="toast-body">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-trophy-fill fs-3 me-3" style="color: #FFD700;"></i>
                                    <div>
                                        <strong class="fs-5">🎉 ยินดีด้วย!</strong>
                                        <p class="mb-0">คุณทำแผนครบ 7 วันแล้ว!<br>กดปุ่ม "อัพเดทข้อมูล" เพื่อปรับแผนใหม่</p>
                                    </div>
                                </div>
                            </div>
                            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                        </div>
                    </div>
                </div>
            `;
            
            // เพิ่ม Toast เข้าไปใน body
            const toastContainer = document.createElement('div');
            toastContainer.innerHTML = toastHTML;
            document.body.appendChild(toastContainer);
            
            // แสดง Toast
            const toastElement = document.getElementById('completionToast');
            const toast = new bootstrap.Toast(toastElement);
            toast.show();
            
            // ลบ Toast หลังจากปิด
            toastElement.addEventListener('hidden.bs.toast', function () {
                toastContainer.remove();
            });
        }

        // Setup MutationObserver - เพื่อตรวจสอบเมื่อมีการเปลี่ยนแปลง
        if (dayCards.length > 0 && typeof MutationObserver !== 'undefined') {
            const observer = new MutationObserver(function(mutations) {
                let hasCompletedClass = false;
                
                mutations.forEach(mutation => {
                    if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
                        if (mutation.target.classList.contains('completed')) {
                            hasCompletedClass = true;
                        }
                    }
                });
                
                if (hasCompletedClass) {
                    console.log('🔔 Card completed, checking plan...');
                    setTimeout(checkPlanCompletion, 500);
                }
            });

            dayCards.forEach(card => {
                observer.observe(card, { 
                    attributes: true,
                    attributeFilter: ['class']
                });
            });
            
            console.log(`👁️ Observer watching ${dayCards.length} cards`);
        }

// Initial check
setTimeout(checkPlanCompletion, 500);

        // Initial check
        setTimeout(checkPlanCompletion, 500);
        

    } else {
        // If no active plan, run the typewriter effect
        typeLine(); 
    }

    // === Responsive Chart Resize Handler ===
    let resizeTimeout;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(function() {
            const newIsMobile = window.innerWidth <= 768;
            
            if (window.myCaloriesChart && newIsMobile !== isMobile) {
                const charts = [
                    'myCaloriesChart', 'myWeightChart', 'mySodiumChart',
                    'mySugarChart', 'myFatChart', 'myCholesterolChart'
                ];
                
                charts.forEach(chartName => {
                    if (window[chartName]) {
                        window[chartName].destroy();
                    }
                });
                
                location.reload();
            }
        }, 500);
    });

    // === Health Info Modal Setup ===
    const healthInfoModal = document.getElementById('healthInfoModal');
    if (healthInfoModal) {
        healthInfoModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const title = button.getAttribute('data-title');
            const content = button.getAttribute('data-content');
            const modalTitle = healthInfoModal.querySelector('.modal-title');
            const modalBody = healthInfoModal.querySelector('.modal-body');
            modalTitle.innerHTML = `<i class="fas fa-info-circle me-2"></i> ${title}`;
            modalBody.innerHTML = content;
        });
    }

    // === Satisfaction Slider ===
    const satisfactionSlider = document.getElementById('plan_satisfaction');
    const satisfactionValue = document.getElementById('satisfaction-value');
    if (satisfactionSlider && satisfactionValue) {
        satisfactionSlider.addEventListener('input', function(e) {
            satisfactionValue.textContent = e.target.value;
        });
    }



    // === Mobile Touch Enhancements ===
    if ('ontouchstart' in window) {
        let touchStartX = 0;
        let touchEndX = 0;
        
        const timeline = document.querySelector('.plan-day-timeline');
        if (timeline) {
            timeline.addEventListener('touchstart', (e) => {
                touchStartX = e.changedTouches[0].screenX;
            });
            
            timeline.addEventListener('touchend', (e) => {
                touchEndX = e.changedTouches[0].screenX;
                handleSwipe();
            });
            
            function handleSwipe() {
                if (touchEndX < touchStartX - 50) {
                    timeline.scrollLeft += 200;
                }
                if (touchEndX > touchStartX + 50) {
                    timeline.scrollLeft -= 200;
                }
            }
        }
        
        if (window.innerWidth <= 768) {
            const chartOptions = {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                }
            };
            
            if (window.myCaloriesChart) {
                window.myCaloriesChart.options = {...window.myCaloriesChart.options, ...chartOptions};
                window.myCaloriesChart.update();
            }
        }
    }

    // === Responsive Table Wrapper ===
    if (window.innerWidth <= 768) {
        document.querySelectorAll('table').forEach(table => {
            if (!table.closest('.table-responsive')) {
                const wrapper = document.createElement('div');
                wrapper.className = 'table-responsive';
                table.parentNode.insertBefore(wrapper, table);
                wrapper.appendChild(table);
            }
        });
    }

    // ✅ [NEW] ฟังก์ชันเปิด Modal อัพเดทข้อมูล
    function openHealthUpdateModal() {
        const healthModal = new bootstrap.Modal(document.getElementById('healthUpdateModal'));
        healthModal.show();
    }

    // === [FIX] ฟังก์ชันบันทึกข้อมูลสุขภาพแบบใหม่ ===
        window.submitHealthUpdate = function() {
            const form = document.getElementById('health-update-form');
            const formData = new FormData(form);

            // Validate
            const weight = formData.get('current_weight');
            if (!weight || parseFloat(weight) <= 0) {
                Swal.fire({ 
                    icon: 'error', 
                    title: 'ข้อมูลไม่ถูกต้อง', 
                    text: 'กรุณากรอกน้ำหนักปัจจุบันของคุณ',
                    confirmButtonColor: '#3085d6'
                });
                return;
            }

            const submitBtn = document.getElementById('submit-health-update-btn');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>กำลังบันทึก...';

            fetch('process/update_health_data.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('Response status:', response.status);
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                console.log('Server response:', data);
                
                if (data.success) {
                    // ปิด Modal Health Update
                    const healthModal = bootstrap.Modal.getInstance(document.getElementById('healthUpdateModal'));
                    if (healthModal) {
                        healthModal.hide();
                    }
                    
                    // ปิด Modal Plan Completed ด้วย
                    const completionModal = bootstrap.Modal.getInstance(document.getElementById('planCompletedModal'));
                    if (completionModal) {
                        completionModal.hide();
                    }

                    // ลบ backdrop ทั้งหมด
                    setTimeout(() => {
                        document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
                        document.body.classList.remove('modal-open');
                        document.body.style.overflow = '';
                        document.body.style.paddingRight = '';
                    }, 300);

                    // ✅ [NEW] ลบ completion flag เพื่อให้สามารถแสดง Modal ได้อีกครั้ง
                    const userId = <?php echo $user_id; ?>;
                    localStorage.removeItem(`plan_completed_shown_${userId}`);
                    
                    // ✅ [NEW] ลบ plan progress เก่าทั้งหมด
                    Object.keys(localStorage).forEach(key => {
                        if (key.startsWith(`planProgress_${userId}_`)) {
                            localStorage.removeItem(key);
                            console.log('🗑️ Cleared old progress:', key);
                        }
                    });

                    // แสดง Loading และ Redirect
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
                        // Redirect ไปหน้าสร้างแผน
                        window.location.href = 'process/generate_improved_plan.php';
                    });
                } else {
                    throw new Error(data.message || 'เกิดข้อผิดพลาด');
                }
            })

            .catch(error => {
                console.error('Error:', error);
                
                // ลบ backdrop กรณี error
                document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
                document.body.classList.remove('modal-open');
                document.body.style.overflow = '';

                Swal.fire({
                    icon: 'error',
                    title: 'เกิดข้อผิดพลาด',
                    text: error.message,
                    confirmButtonColor: '#d33'
                });

                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="bi bi-check-circle me-1"></i>บันทึกและสร้างแผนใหม่';
            });
        };

    // === [FIX] Improved Modal Cleanup Function ===
    window.forceCleanModals = function() {
        console.log('🧹 Force cleaning all modals...');
        
        // ปิด modal instance ทั้งหมด
        document.querySelectorAll('.modal').forEach(modalEl => {
            const instance = bootstrap.Modal.getInstance(modalEl);
            if (instance) {
                instance.hide();
            }
            // Reset modal state
            modalEl.classList.remove('show');
            modalEl.style.display = 'none';
            modalEl.setAttribute('aria-hidden', 'true');
            modalEl.removeAttribute('aria-modal');
            modalEl.removeAttribute('role');
        });
        
        // ลบ backdrop ทั้งหมดแบบค่อยๆ fade
        setTimeout(() => {
            const backdrops = document.querySelectorAll('.modal-backdrop');
            backdrops.forEach((el, index) => {
                el.style.opacity = '0';
                setTimeout(() => {
                    el.remove();
                    console.log(`🗑️ Removed backdrop ${index + 1}/${backdrops.length}`);
                }, 150);
            });
            
            // รีเซ็ต body
            document.body.classList.remove('modal-open');
            document.body.style.overflow = '';
            document.body.style.paddingRight = '';
            
            console.log('✅ Modal cleanup completed');
        }, 100);
    };

    // เพิ่ม event listener สำหรับ cleanup modal เมื่อปิด
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('hidden.bs.modal', function() {
            setTimeout(() => {
                // ตรวจสอบว่ายังมี backdrop ค้างอยู่หรือไม่
                const remainingBackdrops = document.querySelectorAll('.modal-backdrop');
                if (remainingBackdrops.length > 0) {
                    console.warn('⚠️ Detected remaining backdrop, cleaning...');
                    remainingBackdrops.forEach(el => el.remove());
                    document.body.classList.remove('modal-open');
                    document.body.style.overflow = '';
                }
            }, 100);
        });
    });

    // Safety net - ตรวจสอบ backdrop ที่ค้างทุก 5 วินาที
    setInterval(() => {
        const openModals = document.querySelectorAll('.modal.show').length;
        const backdrops = document.querySelectorAll('.modal-backdrop').length;
        
        if (backdrops > openModals) {
            console.warn(`⚠️ Backdrop leak detected: ${backdrops} backdrops for ${openModals} modals`);
            window.forceCleanModals();
        }
    }, 5000);

}); // End of DOMContentLoaded

    // === [NEW] Debug Helper Functions ===
    window.debugDashboard = function() {
        console.log('=== Dashboard Debug Info ===');
        console.log('Has Active Plan:', <?php echo json_encode($has_active_plan); ?>);
        console.log('Total Plan Days:', <?php echo count($plan_days); ?>);
        console.log('Plan Days Data:', <?php echo json_encode($plan_days); ?>);
        console.log('Progress Bar Element:', document.getElementById('plan-progress-bar'));
        console.log('Day Cards:', document.querySelectorAll('.progress-day-card').length);
        console.log('Modal Backdrops:', document.querySelectorAll('.modal-backdrop').length);
        console.log('Open Modals:', document.querySelectorAll('.modal.show').length);
        console.log('========================');
    };

    // เรียกใช้ debug function เมื่อหน้าโหลดเสร็จ
    window.addEventListener('load', function() {
        setTimeout(() => {
            window.debugDashboard();
        }, 1000);
    });

// ✅ ทำความสะอาด URL parameters หลังแสดง notification
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    
    // ถ้ามี parameters เหล่านี้ ให้ลบออกหลัง 3 วินาที
    if (urlParams.has('from_generation') || urlParams.has('from_my_plans')) {
        setTimeout(() => {
            // สร้าง clean URL (เก็บแค่ plan_activated)
            const cleanUrl = window.location.pathname + '?plan_activated=1';
            window.history.replaceState({}, document.title, cleanUrl);
            
            console.log('✅ Cleaned URL parameters');
        }, 3000);
    }
});

</script>

<?php 
require_once 'includes/footer.php'; 
?>
