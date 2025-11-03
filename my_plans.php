<?php
session_start();
$page_title = "โปรไฟล์แผนของฉัน";
require_once 'includes/header.php';
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    echo '<script>window.location.href = "login.php";</script>';
    exit();
}
$user_id = $_SESSION['user_id'];

// 1. ดึงโปรไฟล์แผนที่ผู้ใช้สร้างเอง (พร้อม Tags)
$custom_profiles = [];
$sql_custom = "SELECT p.id, p.profile_name, p.description, p.plan_data, p.created_at, 
                      GROUP_CONCAT(t.name) as tags
               FROM plan_profiles p
               LEFT JOIN plan_profile_tags pt ON p.id = pt.plan_profile_id
               LEFT JOIN tags t ON pt.tag_id = t.id
               WHERE p.user_id = ?
               GROUP BY p.id
               ORDER BY p.created_at DESC";
$stmt_custom = $conn->prepare($sql_custom);
$stmt_custom->bind_param("i", $user_id);
$stmt_custom->execute();
$result_custom = $stmt_custom->get_result();
while ($row = $result_custom->fetch_assoc()) {
    $custom_profiles[] = $row;
}
$stmt_custom->close();

// 2. ดึงแผนทั้งหมดที่สร้างโดย AI
$ai_plans = [];
$sql_ai = "SELECT id, plan_data, created_at FROM weekly_plans WHERE user_id = ? ORDER BY created_at DESC";
$stmt_ai = $conn->prepare($sql_ai);
$stmt_ai->bind_param("i", $user_id);
$stmt_ai->execute();
$result_ai = $stmt_ai->get_result();
while ($row = $result_ai->fetch_assoc()) {
    $ai_plans[] = $row;
}
$stmt_ai->close();

$conn->close();
?>

<style>
    .profile-card { 
        transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out; 
        border: none !important;
        overflow: hidden;
        position: relative;
    }
    .profile-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 5px;
        background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
    }
    .profile-card:hover { 
        transform: translateY(-8px); 
        box-shadow: 0 1rem 2rem rgba(102, 126, 234, 0.3) !important; 
    }
    .profile-card-icon { font-size: 1.5rem; }
    #viewProfileModal .modal-body { background-color: #f8f9fa; }

        /* ปุ่มนำไปใช้ - สีเขียวไล่ระดับ */
    .btn-success {
        background: linear-gradient(135deg, #2FC2A0 0%, #B7D971 100%) !important;
        border: none !important;
        color: white !important;
        font-weight: 600;
        transition: all 0.3s ease;
    }
    .btn-success:hover {
        transform: scale(1.05);
        box-shadow: 0 4px 15px rgba(47, 194, 160, 0.4);
    }
    
    /* ปุ่มดูแผน - สีน้ำเงินไล่ระดับ */
    .btn-outline-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
        border: none !important;
        color: white !important;
        transition: all 0.3s ease;
    }
    .btn-outline-primary:hover {
        transform: scale(1.05);
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
    }
    
    /* ปุ่มลบ - สีแดงไล่ระดับ */
    .btn-outline-danger {
        background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%) !important;
        border: none !important;
        color: white !important;
        transition: all 0.3s ease;
    }
    .btn-outline-danger:hover {
        transform: scale(1.05);
        box-shadow: 0 4px 15px rgba(245, 87, 108, 0.4);
    }
    
    /* ปรับขนาดปุ่มในมือถือ */
    @media (max-width: 768px) {
        .btn-group {
            display: flex;
            gap: 0.25rem;
        }
        .btn-group .btn {
            padding: 0.5rem 0.75rem;
            font-size: 0.875rem;
            flex: 1;
            min-width: auto;
        }
        .btn-group .btn i {
            font-size: 1.1rem;
        }
    }

    /* ปุ่มกรองประเภทแผน - สีเขียวอ่อนไล่ระดับ */
    .btn-gradient-green {
        background: linear-gradient(135deg, #2FC2A0 0%, #B7D971 100%);
        border: none;
        color: white;
        font-weight: 500;
        transition: all 0.3s ease;
    }
    .btn-gradient-green:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(47, 194, 160, 0.4);
    }
    
    .btn-outline-green {
        background: white;
        border: 2px solid #2FC2A0;
        color: #2FC2A0;
        font-weight: 500;
        transition: all 0.3s ease;
    }
    .btn-outline-green:hover,
    .btn-outline-green.active {
        background: linear-gradient(135deg, #2FC2A0 0%, #B7D971 100%);
        border-color: transparent;
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(47, 194, 160, 0.4);
    }
    
    /* ปุ่มกรองตามแท็ก - สีฟ้าอ่อนไล่ระดับ */
    .btn-gradient-blue {
        background: linear-gradient(135deg, #56CCF2 0%, #2F80ED 100%);
        border: none;
        color: white;
        font-weight: 500;
        transition: all 0.3s ease;
    }
    .btn-gradient-blue:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(86, 204, 242, 0.4);
    }
    
    .btn-outline-blue {
        background: white;
        border: 2px solid #56CCF2;
        color: #2F80ED;
        font-weight: 500;
        transition: all 0.3s ease;
    }
    .btn-outline-blue:hover,
    .btn-outline-blue.active {
        background: linear-gradient(135deg, #56CCF2 0%, #2F80ED 100%);
        border-color: transparent;
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(86, 204, 242, 0.4);
    }
    
    /* ปรับ Responsive สำหรับปุ่มกรอง */
    @media (max-width: 768px) {
        #source-filter-container,
        #tag-filter-container {
            text-align: left;
            padding: 0 0.5rem;
        }
        #source-filter-container strong,
        #tag-filter-container strong {
            font-size: 0.9rem;
        }
        #source-buttons {
            display: flex;
            justify-content: flex-start;
            flex-wrap: wrap;
        }
        #source-buttons .btn {
            font-size: 0.85rem;
            padding: 0.4rem 0.8rem;
        }
    }
    
    /* Dropdown แท็กสวยงาม */
    #tag-select {
        border: 2px solid #56CCF2;
        border-radius: 8px;
        padding: 0.5rem 1rem;
        font-weight: 500;
        color: #2F80ED;
        background: white;
        transition: all 0.3s ease;
        cursor: pointer;
        min-width: 200px;
        max-width: 300px;
    }
    
    #tag-select:focus {
        border-color: #2F80ED;
        box-shadow: 0 0 0 0.2rem rgba(86, 204, 242, 0.25);
        outline: none;
    }
    
    #tag-select:hover {
        background: linear-gradient(135deg, rgba(86, 204, 242, 0.1) 0%, rgba(47, 128, 237, 0.1) 100%);
        border-color: #2F80ED;
    }
    
    /* Style สำหรับ option */
    /* Style สำหรับ option */
    #tag-select option {
        padding: 0.5rem;
        font-weight: 500;
        font-size: inherit; /* ใช้ขนาดเดียวกับ select */
    }
    
    #tag-select option:checked {
        background: linear-gradient(135deg, #56CCF2 0%, #2F80ED 100%);
        color: white;
    }

    /* ไอคอนค้นหาใน dropdown */
    #tag-select.ps-5 {
        padding-left: 2.5rem !important;
    }
    
    /* Responsive สำหรับมือถือ */
    @media (max-width: 768px) {
        #tag-select {
            width: calc(100vw - 2rem) !important;
            max-width: calc(100vw - 2rem) !important;
            min-width: auto !important;
            font-size: 0.875rem !important;
            padding: 0.5rem 0.75rem !important;
            box-sizing: border-box !important;
            margin: 0 !important;
        }
        
        #tag-select option {
            font-size: 0.875rem !important;
            padding: 0.4rem 0.5rem !important;
            max-width: 100% !important;
        }
        
        #tag-select.ps-5 {
            padding-left: 2.25rem !important;
            padding-right: 0.75rem !important;
            width: calc(100vw - 2rem) !important;
        }
        
        #tag-filter-container .position-relative {
            width: 100% !important;
            max-width: 100% !important;
            overflow: hidden;
        }
        
        #tag-filter-container .bi-search {
            font-size: 0.875rem;
            left: 10px !important;
            z-index: 10;
        }
    }

        /* ควบคุมความกว้างของ dropdown menu เมื่อเปิด */
    @media (max-width: 768px) {
        /* บังคับให้ dropdown menu ไม่กว้างเกินจอ */
        #tag-select {
            max-width: 100vw !important;
            box-sizing: border-box;
        }
        
        /* ปรับ container ของ select ให้ไม่ล้น */
        #tag-filter-container .position-relative {
            max-width: 100%;
            overflow: hidden;
        }
        
        #tag-filter-container .col-12 {
            max-width: 100%;
            overflow: hidden;
        }
    }

    /* ปรับ Container ของ Tag Filter */
    #tag-filter-container {
        width: 100%;
    }
    
    #tag-filter-container .row {
        margin-left: 0;
        margin-right: 0;
        width: 100%;
    }
    
    @media (max-width: 768px) {
        #tag-filter-container {
            padding-left: 1rem !important;
            padding-right: 1rem !important;
            margin-left: 0 !important;
            margin-right: 0 !important;
            width: 100% !important;
            max-width: 100% !important;
            box-sizing: border-box;
        }
        
        #tag-filter-container .row {
            width: 100% !important;
            margin: 0 !important;
        }
        
        #tag-filter-container .col-12 {
            padding-left: 0 !important;
            padding-right: 0 !important;
            width: 100% !important;
            max-width: 100% !important;
        }
        
        #tag-filter-container strong {
            font-size: 0.9rem;
            display: block;
            margin-bottom: 0.5rem;
        }
        
        #tag-filter-container .position-relative {
            width: 100% !important;
            max-width: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
        }
    }

    #plan-day-nav .list-group-item { border-radius: 0.5rem; margin-bottom: 0.5rem; }
    #plan-day-nav .list-group-item.active {
        background-color: var(--bs-primary);
        border-color: var(--bs-primary);
    }
    .day-detail-pane h5 { color: var(--bs-primary); }

    .calendar-table { width: 100%; text-align: center; border-collapse: collapse; }
    .calendar-table th { padding: 0.5rem; font-weight: bold; color: #6c757d; }
    .calendar-table td {
        border: 1px solid #dee2e6;
        vertical-align: top;
        text-align: left;
        padding: 8px;
        width: calc(100% / 7);
        aspect-ratio: 1 / 1;
    }
    .calendar-table td.day-with-plan {
        background-color: #e2f5ea;
        font-weight: bold;
        color: #155724;
    }
    .calendar-table td.clickable-day { cursor: pointer; }
    .calendar-table td.clickable-day:hover { background-color: #c8e6c9; }
    .calendar-table td .day-number { font-size: 1.1rem; }
    .calendar-table td.empty-day { background-color: #f8f9fa; }
    .calendar-table td.other-month-day {
        opacity: 0.7;
    }


        /* Responsive Card Grid */
    @media (max-width: 576px) {
        .row.g-4 {
            gap: 1rem !important;
        }
        .profile-card .card-body {
            padding: 1rem;
        }
        .profile-card-icon {
            font-size: 1.25rem;
        }
        .card-title {
            font-size: 1rem;
        }
    }

        /* Responsive Heading */
    @media (max-width: 768px) {
        .container h1 {
            font-size: 1.5rem;
        }
        .container p.text-muted {
            font-size: 0.875rem;
        }
    }

        /* แก้ปัญหา iOS Safari ที่ขยาย dropdown */
    @supports (-webkit-touch-callout: none) {
        @media (max-width: 768px) {
            #tag-select {
                -webkit-appearance: none;
                -moz-appearance: none;
                appearance: none;
                background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%232F80ED' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
                background-repeat: no-repeat;
                background-position: right 0.75rem center;
                background-size: 12px;
                padding-right: 2.5rem;
            }
            
            #tag-select option {
                background-color: white;
                color: #333;
                font-size: 0.875rem !important;
            }
        }
    }

        /* Tag Filter Wrapper */
    .tag-filter-wrapper {
        width: 100%;
        display: flex;
        align-items: center;
        gap: 1rem;
    }
    
    .tag-filter-label {
        flex-shrink: 0;
    }
    
    .tag-filter-select {
        flex: 1;
        min-width: 0;
    }
    
    @media (max-width: 768px) {
        .tag-filter-wrapper {
            flex-direction: column;
            align-items: flex-start;
            gap: 0.5rem;
        }
        
        .tag-filter-label {
            width: 100%;
        }
        
        .tag-filter-select {
            width: 100%;
        }
        
        .tag-filter-select .position-relative {
            width: 100%;
        }
        
        .tag-filter-select select {
            width: 100% !important;
        }
    }

        /* 🔥 FIX: แก้ Dropdown ล้นในมือถือ - สมบูรณ์ */
    @media (max-width: 768px) {
        /* ป้องกัน container ล้น */
        body {
            overflow-x: hidden !important;
        }
        
        .container {
            max-width: 100vw !important;
            overflow-x: hidden !important;
        }
        
        /* ล็อค tag filter ไม่ให้ล้น */
        #tag-filter-container {
            width: calc(100vw - 2rem) !important;
            max-width: calc(100vw - 2rem) !important;
            margin: 0 auto 1rem auto !important;
            padding: 0 !important;
            box-sizing: border-box !important;
        }
        
        #tag-filter-container * {
            box-sizing: border-box !important;
        }
        
        #tag-filter-container .row,
        #tag-filter-container [class*="col-"] {
            width: 100% !important;
            max-width: 100% !important;
            margin: 0 !important;
            padding-left: 0 !important;
            padding-right: 0 !important;
        }
        
        /* ล็อค select ไม่ให้ล้น */
        #tag-select {
            width: 100% !important;
            max-width: 100% !important;
            font-size: 0.875rem !important;
            box-sizing: border-box !important;
        }
        
        #tag-select.ps-5 {
            width: 100% !important;
        }
        
        /* ล็อค position relative wrapper */
        #tag-filter-container .position-relative {
            width: 100% !important;
            max-width: 100% !important;
        }
    }
</style>

<div class="container my-5" style="padding-top: 50px;">
    <style>
        @media (max-width: 768px) {
            .container.my-5 { 
                padding-top: 20px !important; 
                margin-top: 1rem !important;
                padding-left: 1rem !important;
                padding-right: 1rem !important;
                max-width: 100vw !important;
                overflow-x: hidden !important;
            }
        }
    </style>
    <div class="">
        <h1 class="text-center gradient-text"><i class="bi bi-bookmarks-fill text-primary"></i> โปรไฟล์แผนของฉัน</h1>
    </div>

    <p class="text-muted mb-4 text-center">
        เลือกโปรไฟล์แผนที่คุณต้องการ แล้วกด "นำไปใช้" เพื่อเริ่มแผนในวันที่คุณต้องการ
    </p>
        <div class="mb-3" id="source-filter-container">
            <strong class="me-2 d-block d-md-inline mb-2 mb-md-0">ประเภทแผน:</strong>
            <div class="btn-group flex-wrap gap-2" role="group" id="source-buttons">
                <button type="button" class="btn btn-sm btn-gradient-green active" data-source="all">ทั้งหมด</button>
                <button type="button" class="btn btn-sm btn-outline-green" data-source="custom">แผนกำหนดเอง</button>
                <button type="button" class="btn btn-sm btn-outline-green" data-source="ai">แผนจาก AI</button>
            </div>
        </div>
        <div class="mb-4" id="tag-filter-container" style="display: none;">
            <div class="tag-filter-wrapper">
                <div class="tag-filter-label mb-2">
                    <strong><i class="bi bi-funnel-fill text-primary me-2"></i>กรองตามแท็ก:</strong>
                </div>
                <div class="tag-filter-select">
                    <div class="position-relative">
                        <i class="bi bi-search position-absolute" style="left: 12px; top: 50%; transform: translateY(-50%); color: #2F80ED; pointer-events: none; z-index: 10;"></i>
                        <select class="form-select form-select-sm ps-5" id="tag-select">
                            <option value="all" selected>🏷️ ทั้งหมด</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

    <div class="row g-4">
        <?php foreach ($custom_profiles as $profile): ?>
            <div class="col-md-6 col-lg-4" data-type="custom">
                <div class="card h-100 profile-card" id="profile-card-custom-<?php echo $profile['id']; ?>" data-tags="<?php echo htmlspecialchars($profile['tags']); ?>">
                    <div class="card-body">
                        <div class="d-flex align-items-start">
                            <i class="bi bi-person-badge-fill text-success profile-card-icon me-3"></i>
                            <div>
                                <h5 class="card-title"><?php echo htmlspecialchars($profile['profile_name']); ?></h5>
                                <p class="card-text small text-muted"><?php echo !empty($profile['description']) ? htmlspecialchars($profile['description']) : '<i>ไม่มีคำอธิบาย</i>'; ?></p>
                            </div>
                        </div>
                            <?php if (!empty($profile['tags'])): ?>
                                <div class="mt-2">
                                    <?php
                                    $tags_array = explode(',', $profile['tags']);
                                    foreach ($tags_array as $tag):
                                    ?>
                                        <span class="badge bg-secondary text-white"><?php echo htmlspecialchars($tag); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                    </div>
                    <div class="card-footer bg-transparent border-0 d-flex justify-content-between align-items-center">
                        <span class="text-muted small">สร้างเมื่อ: <?php echo format_thai_date($profile['created_at']); ?></span>
                        <div class="btn-group">
                            <button class="btn btn-sm btn-success" onclick="openApplyModal(<?php echo $profile['id']; ?>, 'custom')">
                                <i class="bi bi-calendar-plus-fill"></i> 
                                <span class="d-none d-md-inline">นำไปใช้</span>
                            </button>
                            <button class="btn btn-sm btn-outline-primary" onclick="viewProfileFromButton(<?php echo $profile['id']; ?>, 'custom', '<?php echo htmlspecialchars(addslashes($profile['profile_name'])); ?>')"><i class="bi bi-eye-fill"></i></button>
                            <button class="btn btn-sm btn-outline-danger" onclick="deleteProfile(<?php echo $profile['id']; ?>, 'custom')"><i class="bi bi-trash3-fill"></i></button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <?php foreach ($ai_plans as $plan): ?>
            <div class="col-md-6 col-lg-4" data-type="ai">
                <div class="card h-100 profile-card" id="profile-card-ai-<?php echo $plan['id']; ?>">
                       <div class="card-body">
                        <div class="d-flex align-items-start">
                        <i class="bi bi-robot text-info profile-card-icon me-3"></i>
                        <div>
                            <h5 class="card-title">แผนจาก AI</h5>
                            <p class="card-text small text-muted">
                                แผนอาหาร 7 วัน (อัปเดตล่าสุด)
                            </p>
                        </div>
                    </div>
                    </div>
                    <div class="card-footer bg-transparent border-0 d-flex justify-content-between align-items-center">
                        <span class="text-muted small">สร้างเมื่อ: <?php echo format_thai_date($plan['created_at']); ?></span>
                        <div class="btn-group">
                            <button class="btn btn-sm btn-success" onclick="openApplyModal(<?php echo $plan['id']; ?>, 'ai')">
                                <i class="bi bi-calendar-plus-fill"></i> 
                                <span class="d-none d-md-inline">นำไปใช้</span>
                            </button>
                            <button class="btn btn-sm btn-outline-primary" onclick="viewProfileFromButton(<?php echo $plan['id']; ?>, 'ai', 'แผนจาก AI (<?php echo htmlspecialchars(addslashes(format_thai_date($plan['created_at']))); ?>)', '<?php echo $plan['created_at']; ?>')"><i class="bi bi-eye-fill"></i></button>
                            <button class="btn btn-sm btn-outline-danger" onclick="deleteProfile(<?php echo $plan['id']; ?>, 'ai')"><i class="bi bi-trash3-fill"></i></button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if (empty($custom_profiles) && empty($ai_plans)): ?>
            <div class="col-12">
                <div class="text-center p-5 border rounded bg-light">
                    <i class="bi bi-journal-x fs-1 text-muted"></i>
                    <h3 class="mt-3">ไม่พบโปรไฟล์แผนอาหาร</h3>
                    <p class="text-muted">คุณยังไม่เคยบันทึกแผนอาหารของตัวเอง หรือยังไม่เคยให้ AI สร้างแผนให้</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="modal fade" id="viewProfileModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div id="viewProfileModalHeader" class="d-flex justify-content-between align-items-center w-100">
                    <h5 class="modal-title" id="viewProfileModalLabel">รายละเอียดโปรไฟล์</h5>
                    </div>
                <button type="button" class="btn-close ms-2" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="viewProfileModalBody"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="applyPlanModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="applyPlanModalLabel">นำโปรไฟล์แผนไปใช้</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>เลือกวันที่คุณต้องการเริ่มต้นแผนนี้</p>
                <div class="mb-3">
                    <label for="start-date-input" class="form-label"><strong>เลือกวันเริ่มต้น</strong></label>
                    <input type="date" class="form-control" id="start-date-input">
                </div>
                <div class="form-text text-danger">
                    ข้อควรระวัง: หากในวันที่เลือกมีแผนอยู่แล้ว แผนเดิมจะถูกเขียนทับด้วยแผนใหม่นี้
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                <button type="button" id="confirm-apply-btn" class="btn btn-primary">ยืนยันการนำไปใช้</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // ---- Part 1: Prepare Data from PHP ----
    const allPlanData = {
        custom: {
            <?php
            $custom_items = [];
            foreach ($custom_profiles as $profile) {
                $plan_json = json_decode($profile['plan_data']) ? $profile['plan_data'] : '{}';
                $custom_items[] = "'{$profile['id']}': " . $plan_json;
            }
            echo implode(',', $custom_items);
            ?>
        },
        ai: {
            <?php
            $ai_items = [];
            foreach ($ai_plans as $plan) {
                 $plan_json = json_decode($plan['plan_data']) ? $plan['plan_data'] : '{}';
                $ai_items[] = "'{$plan['id']}': " . $plan_json;
            }
            echo implode(',', $ai_items);
            ?>
        }
    };

    // ---- Part 2: Prepare Tools and Variables ----
    const viewProfileModalEl = document.getElementById('viewProfileModal');
    const applyPlanModalEl = document.getElementById('applyPlanModal');

    if (!viewProfileModalEl || !applyPlanModalEl) {
        console.error('Modal elements not found!');
        return;
    }

    const viewProfileModal = new bootstrap.Modal(viewProfileModalEl);
    const applyPlanModal = new bootstrap.Modal(applyPlanModalEl);
    let currentPlanToApply = null;

    const mealNameMapping = {
        breakfast: 'มื้อเช้า', brunch: 'มื้อสาย', lunch: 'มื้อกลางวัน',
        afternoon_snack: 'มื้อบ่าย', dinner: 'มื้อเย็น'
    };
    
    // ---- Part 3: Make Functions Globally Accessible ----
    window.viewProfileFromButton = function(planId, planType, profileName, createdAt = null) {
        const planData = allPlanData[planType][planId];
        renderProfileModal(profileName, planData, planType, createdAt);
    };

    window.openApplyModal = function(planId, planType) {
        // [FIX] Store the ID and type, NOT the full plan data
        currentPlanToApply = { plan_id: planId, plan_type: planType }; 
        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        const startDateInput = document.getElementById('start-date-input');
        if(startDateInput) {
            startDateInput.value = tomorrow.toISOString().split('T')[0];
            startDateInput.min = new Date().toISOString().split('T')[0]; // Prevent selecting past dates
        }
        applyPlanModal.show();
    };

    window.deleteProfile = async function(profileId, planType) {
        if (!confirm(`คุณแน่ใจหรือไม่ว่าต้องการลบโปรไฟล์แผนนี้อย่างถาวร?`)) return;
        try {
            const response = await fetch('process/delete_plan_profile.php', {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ profile_id: profileId, plan_type: planType })
            });
            const result = await response.json();
            if (result.success) {
                alert('ลบโปรไฟล์สำเร็จ');
                const cardElement = document.getElementById(`profile-card-${planType}-${profileId}`);
                if (cardElement) {
                    cardElement.style.transition = 'opacity 0.5s ease';
                    cardElement.style.opacity = '0';
                    setTimeout(() => cardElement.parentElement.remove(), 500);
                }
            } else { throw new Error(result.message || 'เกิดข้อผิดพลาดในการลบ'); }
        } catch (error) {
            console.error('Delete error:', error);
            alert(error.message);
        }
    };

    // ---- Part 4: Helper Functions for Modal Views ----
    
    function generateSingleCalendarHtml(year, month, planData) {
        const firstDayOfMonth = new Date(year, month, 1);
        const lastDayOfMonth = new Date(year, month + 1, 0);
        let html = '<table class="calendar-table"><thead><tr><th>อา</th><th>จ</th><th>อ</th><th>พ</th><th>พฤ</th><th>ศ</th><th>ส</th></tr></thead><tbody>';
        
        let currentDay = new Date(firstDayOfMonth);
        currentDay.setDate(currentDay.getDate() - currentDay.getDay());

        let lastDayInGrid = new Date(lastDayOfMonth);
        if (lastDayInGrid.getDay() !== 6) {
            lastDayInGrid.setDate(lastDayInGrid.getDate() + (6 - lastDayInGrid.getDay()));
        }

        while (currentDay <= lastDayInGrid) {
            if (currentDay.getDay() === 0) html += '<tr>';

            const dateStr = `${currentDay.getFullYear()}-${String(currentDay.getMonth() + 1).padStart(2, '0')}-${String(currentDay.getDate()).padStart(2, '0')}`;
            const dayPlanData = planData[dateStr];
            const hasPlan = dayPlanData && dayPlanData.plan && Object.values(dayPlanData.plan).some(meal => meal && meal.length > 0);
            const isCurrentMonth = currentDay.getMonth() === month;

            let cellClasses = [];
            let cellAttributes = '';
            let cellContent = '';

            if (isCurrentMonth || hasPlan) {
                cellContent = `<strong class="day-number">${currentDay.getDate()}</strong>`;
                if (hasPlan) {
                    cellClasses.push('day-with-plan', 'clickable-day');
                    cellAttributes = `data-date="${dateStr}"`;
                    const totalCalories = dayPlanData.totals ? Math.round(dayPlanData.totals.calories) : 0;
                    cellContent += `<div class="small text-success fw-bold mt-1">${totalCalories} Kcal</div>`;
                }
                if (!isCurrentMonth && hasPlan) {
                    cellClasses.push('other-month-day');
                }
            } else {
                cellClasses.push('empty-day');
                cellContent = '&nbsp;';
            }

            html += `<td class="${cellClasses.join(' ')}" ${cellAttributes}>${cellContent}</td>`;

            if (currentDay.getDay() === 6) html += '</tr>';
            currentDay.setDate(currentDay.getDate() + 1);
        }

        html += '</tbody></table>';
        return html;
    }
    
    function renderProfileModal(profileName, planData, planType, createdAt = null) {
        const modalHeader = document.getElementById('viewProfileModalHeader');
        const modalBodyEl = document.getElementById('viewProfileModalBody');

        modalHeader.innerHTML = `
            <h5 class="modal-title flex-grow-1"><i class="bi bi-journal-text me-2"></i> ${profileName}</h5>
            <button id="toggle-view-btn" class="btn btn-outline-secondary btn-sm" type="button" style="display: none;">
                <i class="bi bi-calendar3"></i> ดูปฏิทิน
            </button>`;
        
        const planEntries = Object.keys(planData).sort();

        if (planEntries.length === 0) {
            modalBodyEl.innerHTML = '<p class="text-center text-muted p-5">โปรไฟล์นี้ไม่มีข้อมูลแผนอาหาร</p>';
            viewProfileModal.show();
            return;
        }

        modalBodyEl.innerHTML = `
            <div id="detail-view-container">
                <div class="row">
                    <div class="col-12 col-md-4 border-end pe-3">
                        <div class="list-group list-group-flush" id="plan-day-nav"></div>
                    </div>
                    <div class="col-12 col-md-8 mt-3 mt-md-0 ps-md-4">
                        <div id="plan-day-details"></div>
                    </div>
                </div>
            </div>
            <div id="calendar-view-container" style="display: none;"></div>`;
        
        const navContainer = modalBodyEl.querySelector("#plan-day-nav");
        const detailsContainer = modalBodyEl.querySelector("#plan-day-details");
        const calendarContainer = modalBodyEl.querySelector("#calendar-view-container");
        const toggleBtn = modalHeader.querySelector("#toggle-view-btn");

        let navHtml = '';
        let detailsHtml = '';

        const startDate = createdAt ? new Date(createdAt) : null;

        planEntries.forEach((key, index) => {
            const dayData = planData[key];
            const isActive = index === 0;
            let navText;

            if (planType === 'custom') {
                navText = `วันที่ ${index + 1} <span class="small text-muted">(${new Date(key + 'T00:00:00').toLocaleDateString('th-TH', { day: 'numeric', month: 'short', year: '2-digit'})})</span>`;
            } else { // This is for AI Plan
                let formattedDate = '';
                if(startDate) {
                    const currentDate = new Date(startDate);
                    currentDate.setDate(currentDate.getDate() + index);
                    formattedDate = `(${currentDate.toLocaleDateString('th-TH', { day: 'numeric', month: 'short', year: '2-digit'})})`;
                }
                navText = `${key} <span class="small text-muted">${formattedDate}</span>`;
            }
            
            navHtml += `<a href="#" class="list-group-item list-group-item-action ${isActive ? 'active' : ''}" data-target-pane="pane-${index}" data-date-key="${key}">${navText}</a>`;
            detailsHtml += `<div class="day-detail-pane" id="pane-${index}" style="${isActive ? '' : 'display: none;'}">`;
            const mealData = planType === 'custom' ? dayData.plan : dayData;
            
            let mealCount = 0;
            if (mealData) {
                const mealKeys = planType === 'ai' ? Object.keys(mealData) : Object.keys(mealNameMapping);
                mealKeys.forEach(mealKey => {
                    const mealItems = mealData[mealKey];
                    const recipes = Array.isArray(mealItems) ? mealItems : (mealItems ? [mealItems] : []);
                    const mealTitle = mealNameMapping[mealKey] || mealKey;
                    if (recipes.length > 0) {
                         mealCount++;
                         detailsHtml += `<h5 class="mt-3"><strong>${mealTitle}</strong></h5><ul class="list-group list-group-flush">`;
                         recipes.forEach(recipe => {
                             if(recipe && (recipe.name || recipe.recipe_name)) {
                                const recipeName = recipe.name || recipe.recipe_name;
                                detailsHtml += `<li class="list-group-item d-flex align-items-center bg-transparent px-0"><img src="${recipe.image_url || 'https://via.placeholder.com/60'}" alt="${recipeName}" class="me-3" style="width:60px; height:60px; object-fit:cover; border-radius:8px;"><div>${recipeName}<br><small class="text-muted">${recipe.calories || 0} kcal</small></div></li>`;
                             }
                        });
                        detailsHtml += `</ul>`;
                    }
                });
            }

            if (mealCount === 0) {
                detailsHtml += '<div class="text-center text-muted p-4"><em>ไม่มีเมนูสำหรับวันนี้</em></div>';
            }
            detailsHtml += '</div>';
        });

        navContainer.innerHTML = navHtml;
        detailsContainer.innerHTML = detailsHtml;

        if (planType === 'custom') {
            toggleBtn.style.display = 'block';
            const months = {};
            planEntries.forEach(dateStr => { months[dateStr.substring(0, 7)] = true; });
            let calendarHtml = '';
            Object.keys(months).sort().forEach(monthKey => {
                const [year, month] = monthKey.split('-').map(Number);
                const monthName = new Date(year, month - 1, 1).toLocaleDateString('th-TH', { month: 'long', year: 'numeric' });
                calendarHtml += `<h5 class="text-center mb-3 mt-4">${monthName}</h5>`;
                calendarHtml += generateSingleCalendarHtml(year, month - 1, planData);
            });
            calendarContainer.innerHTML = calendarHtml;
        }

        toggleBtn.addEventListener('click', () => {
            const isCalendarVisible = calendarContainer.style.display !== 'none';
            calendarContainer.style.display = isCalendarVisible ? 'none' : 'block';
            modalBodyEl.querySelector("#detail-view-container").style.display = isCalendarVisible ? 'block' : 'none';
            toggleBtn.innerHTML = isCalendarVisible ? '<i class="bi bi-calendar3"></i> ดูปฏิทิน' : '<i class="bi bi-list-ul"></i> กลับไปที่รายการ';
        });

        navContainer.addEventListener('click', (e) => {
            e.preventDefault();
            const clickedLink = e.target.closest('a.list-group-item-action');
            if (!clickedLink) return;
            navContainer.querySelector('a.active')?.classList.remove('active');
            clickedLink.classList.add('active');
            const targetPaneId = clickedLink.dataset.targetPane;
            detailsContainer.querySelectorAll('.day-detail-pane').forEach(pane => pane.style.display = 'none');
            document.getElementById(targetPaneId).style.display = 'block';
        });
        
        calendarContainer.addEventListener('click', (e) => {
            const clickedDay = e.target.closest('.clickable-day');
            if (!clickedDay) return;
            const dateStr = clickedDay.dataset.date;
            const targetNavLink = navContainer.querySelector(`a[data-date-key="${dateStr}"]`);
            if (targetNavLink) {
                targetNavLink.click();
                toggleBtn.click();
            }
        });

        viewProfileModal.show();
    }
    
    // [FIXED] This function now sends the correct data to the new backend script.
    async function confirmApplyPlan() {
        const startDate = document.getElementById('start-date-input').value;
        if (!startDate) {
            alert('กรุณาเลือกวันเริ่มต้น');
            return;
        }
        if (!currentPlanToApply) {
            alert('เกิดข้อผิดพลาด: ไม่พบข้อมูลแผน');
            return;
        }

        const confirmBtn = document.getElementById('confirm-apply-btn');
        confirmBtn.disabled = true;
        confirmBtn.innerHTML = `<span class="spinner-border spinner-border-sm"></span> กำลังนำไปใช้...`;

        try {
            const response = await fetch('process/apply_plan_profile.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    start_date: startDate,
                    plan_id: currentPlanToApply.plan_id,
                    plan_type: currentPlanToApply.plan_type
                })
            });
            const result = await response.json();
if (result.success) {
    // ✅ [FIX] Clear localStorage for plan progress
    if (result.clear_storage_key) {
        const userId = <?php echo $user_id; ?>;
        const keysToRemove = [];
        
        Object.keys(localStorage).forEach(key => {
            if (key.startsWith(result.clear_storage_key)) {
                keysToRemove.push(key);
            }
        });
    }
    
    // ✅ ใช้ SweetAlert2 แทน alert ธรรมดา (ดูสวยกว่า)
    Swal.fire({
        icon: 'success',
        title: 'สำเร็จ!',
        text: 'นำแผนไปใช้เรียบร้อยแล้ว',
        timer: 1500,
        showConfirmButton: false
    }).then(() => {
        // ✅ Redirect ไป dashboard พร้อม flag เดียวกัน
        window.location.href = 'dashboard.php?plan_activated=1&new_plan=1&from_my_plans=1';
    });
} else {
    throw new Error(result.message || 'เกิดข้อผิดพลาด');
}
        } catch (error) {
            alert(`เกิดข้อผิดพลาด: ${error.message}`);
            confirmBtn.disabled = false;
            confirmBtn.innerHTML = 'ยืนยันการนำไปใช้';
        }
    }
    
    // ---- Part 5: Attach Event Listeners ----
    document.getElementById('confirm-apply-btn').addEventListener('click', confirmApplyPlan);

    // ---- Part 6: Filtering System ----
    let currentSourceFilter = 'all';
    let currentTagFilter = 'all';
    const sourceButtonsContainer = document.getElementById('source-buttons');
    const tagFilterContainer = document.getElementById('tag-filter-container');
    const allProfileCards = document.querySelectorAll('.row.g-4 > div');

    function populateTagFilters() {
        const tags = new Set();
        document.querySelectorAll('div[data-type="custom"] .card').forEach(card => {
            const cardTags = card.dataset.tags;
            if (cardTags) {
                cardTags.split(',').forEach(tag => {
                    if(tag) tags.add(tag.trim());
                });
            }
        });

        if (tags.size > 0) {
            tagFilterContainer.style.display = 'block';
            const tagSelect = document.getElementById('tag-select');
            const sortedTags = Array.from(tags).sort();
            
            // ล้าง option เก่าออก (เว้น "ทั้งหมด")
            tagSelect.innerHTML = '<option value="all" selected>🏷️ ทั้งหมด</option>';
            
            // เพิ่ม option ใหม่
            sortedTags.forEach(tag => {
                const option = document.createElement('option');
                option.value = tag;
                option.textContent = `📌 ${tag}`;
                tagSelect.appendChild(option);
            });
        }
    }

    function applyFilters() {
        allProfileCards.forEach(cardContainer => {
            const card = cardContainer.querySelector('.card');
            const cardType = cardContainer.dataset.type;
            const cardTags = card.dataset.tags || '';
            const sourceMatch = (currentSourceFilter === 'all') || (cardType === currentSourceFilter);
            const tagMatch = (currentTagFilter === 'all') || (cardType !== 'custom') || (cardTags.split(',').includes(currentTagFilter));

            if (sourceMatch && tagMatch) {
                cardContainer.style.display = '';
            } else {
                cardContainer.style.display = 'none';
            }
        });
    }

    sourceButtonsContainer.addEventListener('click', (e) => {
        const button = e.target.closest('button');
        if (!button) return;
        
        sourceButtonsContainer.querySelectorAll('button').forEach(btn => {
            btn.classList.remove('active', 'btn-gradient-green');
            btn.classList.add('btn-outline-green');
        });
        button.classList.add('active', 'btn-gradient-green');
        button.classList.remove('btn-outline-green');

        currentSourceFilter = button.dataset.source;
        
        const tagSelect = document.getElementById('tag-select');
        const tagsExist = tagSelect && tagSelect.options.length > 1;
        tagFilterContainer.style.display = (currentSourceFilter === 'ai' || !tagsExist) ? 'none' : 'block';
        
        // Reset tag filter เมื่อเปลี่ยน source
        if (tagSelect) {
            tagSelect.value = 'all';
            currentTagFilter = 'all';
        }
        
        applyFilters();
    });

    // Event listener สำหรับ Dropdown แท็ก
    const tagSelect = document.getElementById('tag-select');
    if (tagSelect) {
        tagSelect.addEventListener('change', (e) => {
            currentTagFilter = e.target.value;
            applyFilters();
        });
    }

    // ✅ เรียกใช้งานฟังก์ชันเริ่มต้น
    populateTagFilters();
    applyFilters();
});
</script>

<?php require_once 'includes/footer.php'; ?>
