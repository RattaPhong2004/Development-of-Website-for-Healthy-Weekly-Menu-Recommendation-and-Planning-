<?php
session_start();
$page_title = "ลืมรหัสผ่าน";
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'FitMealWeek'; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/stylelogin.css">
</head>

<style>
    /* Override styles for forgot password page */
    .container {
        max-width: 500px;
        margin-top: 50px;
        min-height: auto;
        padding: 40px 30px;
    }

    .form-container {
        position: static !important;
        width: 100% !important;
        opacity: 1 !important;
    }

    .input-icon {
        position: relative;
        margin-bottom: 20px;
        width: 100%;
    }

    .input-icon i {
        position: absolute;
        top: 50%;
        left: 18px;
        transform: translateY(-50%);
        color: #aaa;
        font-size: 16px;
        z-index: 1;
    }

    .input-icon input {
        width: 100%;
        padding: 14px 18px 14px 50px;
        border: 1px solid #ddd;
        border-radius: 10px;
        background-color: #f9f9f9;
        font-size: 15px;
        outline: none;
        transition: all 0.3s ease;
    }

    .input-icon input:focus {
        background-color: #fff;
        border-color: #2FC2A0;
        box-shadow: 0 0 8px rgba(47, 194, 160, 0.3);
    }

    .container button {
        background: linear-gradient(135deg, #2FC2A0 0%, #B7D971 100%);
        padding: 14px 45px;
        border-radius: 10px;
        transition: all 0.3s ease;
    }

    .container button:hover {
        background: linear-gradient(135deg, #28a889 0%, #a3c065 100%);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(47, 194, 160, 0.4);
    }

    /* Mobile Responsive */
    @media screen and (max-width: 768px) {
        .container {
            margin-top: 20px;
            padding: 30px 20px;
            border-radius: 20px;
        }

        h1 {
            font-size: 24px;
        }

        .container span {
            font-size: 13px;
        }

        .input-icon input {
            padding: 12px 15px 12px 45px;
            font-size: 14px;
        }

        .input-icon i {
            left: 15px;
            font-size: 14px;
        }

        .container button {
            padding: 12px 30px;
            font-size: 16px;
        }
    }

    @media screen and (max-width: 480px) {
        .container {
            padding: 25px 15px;
        }

        h1 {
            font-size: 20px;
        }

        .logo-container img {
            width: 60px;
        }
    }
</style>

<body>
    <div class="container" id="container" style="max-width: 500px; margin-top: 50px;">
        <div class="form-container" style="text-align: center;">
            <form action="process/process_forgot_password.php" method="POST">
                <div class="logo-container">
                    <a href="index.php">
                        <img src="assets/images/logo.png" alt="logo">
                    </a>
                </div>
                <h1>ลืมรหัสผ่าน</h1>
                <span>กรุณากรอกอีเมลของคุณเพื่อรับลิงก์สำหรับตั้งรหัสผ่านใหม่</span>

                <?php if (isset($_GET['error'])): ?>
                    <div class="alert alert-danger" style="font-size: 16px;"><?php echo htmlspecialchars($_GET['error']); ?></div>
                <?php endif; ?>
                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success" style="font-size: 16px;"><?php echo htmlspecialchars($_GET['success']); ?></div>
                <?php endif; ?>

                <div class="input-icon">
                    <i class="fas fa-envelope"></i>
                    <input type="email" placeholder="อีเมล" id="email" name="email" required>
                </div>

                <button type="submit" style="margin-top: 15px;">ส่งลิงก์ตั้งรหัสผ่านใหม่</button>
                <div style="margin-top: 20px;">
                    <a href="login.php">กลับไปหน้าเข้าสู่ระบบ</a>
                </div>
            </form>
        </div>
    </div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/js/all.min.js"></script>
</body>

</html>