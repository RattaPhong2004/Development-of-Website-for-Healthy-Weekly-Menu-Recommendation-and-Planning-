<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ - FitMealWeek</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;700&display=swap');

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Prompt', sans-serif;
        }

        body {
            background: linear-gradient(135deg, #e8f5e9 0%, #f1f8e9 50%, #e0f2f1 100%);
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            min-height: 100vh;
            padding: 20px;
            overflow: hidden;
        }

        /* เพิ่ม pattern ไอคอนพื้นหลัง */
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: radial-gradient(circle, rgba(183, 217, 113, 0.1) 1px, transparent 1px);
            background-size: 30px 30px;
            z-index: 0;
            pointer-events: none;
        }

        h1 {
            font-size: clamp(24px, 5vw, 38px);
            margin-bottom: 10px;
        }

        .container {
            background-color: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            border-radius: 30px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15), 0 0 0 1px rgba(255, 255, 255, 0.5);
            position: relative;
            overflow: hidden;
            width: 100%;
            max-width: 768px;
            min-height: 580px;
            font-size: clamp(18px, 3.5vw, 26px);
            z-index: 10;
        }

        .container p {
            font-size: clamp(14px, 2.5vw, 16px);
            line-height: 1.6;
            letter-spacing: 0.3px;
            margin: 15px 0;
        }

        .container span {
            font-size: clamp(12px, 2.5vw, 14px);
            display: block;
            margin-bottom: 15px;
        }

        .container a {
            color: #333;
            font-size: clamp(12px, 2.5vw, 14px);
            text-decoration: none;
            margin: 10px 0;
            display: inline-block;
        }

        .container button {
            background: linear-gradient(135deg, #27a184ff 0%, #8ea957ff 100%);
            color: #fff;
            font-size: clamp(14px, 3vw, 17px);
            padding: 14px 30px;
            border: none;
            border-radius: 16px;
            font-weight: 600;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            margin-top: 15px;
            cursor: pointer;
            width: 100%;
            max-width: 250px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 
                0 4px 16px rgba(102, 187, 106, 0.4),
                inset 0 1px 0 rgba(255, 255, 255, 0.3);
            position: relative;
            overflow: hidden;
        }

        .container button::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.5s;
        }

        .container button:hover::before {
            left: 100%;
        }

        .container button:hover {
            background: linear-gradient(135deg, #4caf50 0%, #66bb6a 100%);
            transform: translateY(-3px);
            box-shadow: 
                0 8px 24px rgba(102, 187, 106, 0.5),
                inset 0 1px 0 rgba(255, 255, 255, 0.4);
        }

        .container button:active {
            transform: translateY(-1px);
            box-shadow: 
                0 4px 12px rgba(102, 187, 106, 0.4),
                inset 0 1px 0 rgba(255, 255, 255, 0.3);
        }

        .container button.hidden {
            background-color: transparent;
            border-color: #fff;
        }

        .logo-container {
            text-align: center;
            margin-bottom: 20px;
        }

        .logo-container img {
            width: clamp(20px, 10vw, 50px);
            height: auto;
        }

        .container form {
            background-color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            padding: 30px 20px;
            height: 100%;
        }

        .container input {
            background-color: #eee;
            border: 1px solid transparent;
            margin: 8px 0;
            padding: 10px 15px;
            font-size: clamp(12px, 2.5vw, 14px);
            border-radius: 8px;
            width: 100%;
            outline: none;
            transition: all 0.3s ease;
        }

        .container input:hover {
            border: 2px solid #B7D971;
            box-shadow: 0 0 5px rgba(0, 255, 34, 0.3);
        }

        .container input:focus {
            border-color: #B7D971;
            box-shadow: 0 0 5px rgba(0, 255, 8, 0.5);
        }

        .form-container {
            position: absolute;
            top: 0;
            height: 100%;
            transition: all 0.6s ease-in-out;
            width: 50%;
        }

        .input-icon {
            position: relative;
            margin-bottom: 25px;
            width: 100%;
            max-width: 350px;
            min-height: 45px;
        }

        .input-icon i {
            position: absolute;
            top: 50%;
            left: 15px;
            transform: translateY(-50%);
            color: #00000075;
            font-size: clamp(14px, 3vw, 18px);
        }

        .input-icon input {
            width: 100%;
            padding: 10px 10px 10px 45px;
            border: 1px solid #ccc;
            border-radius: 5px;
            box-sizing: border-box;
        }

        .sign-in {
            left: 0;
            width: 50%;
            z-index: 2;
        }

        .container.active .sign-in {
            transform: translateX(100%);
        }

        .sign-up {
            left: 0;
            width: 50%;
            opacity: 0;
            z-index: 1;
        }

        .container.active .sign-up {
            transform: translateX(100%);
            opacity: 1;
            z-index: 5;
            animation: move 0.6s;
        }

        @keyframes move {
            0%, 49.99% {
                opacity: 0;
                z-index: 1;
            }
            50%, 100% {
                opacity: 1;
                z-index: 5;
            }
        }

        .toggle-container {
            position: absolute;
            top: 0;
            left: 50%;
            width: 50%;
            height: 100%;
            overflow: hidden;
            transition: all 0.6s ease-in-out;
            border-radius: 40px 0 0 40px;
            z-index: 1000;
        }

        .container.active .toggle-container {
            transform: translateX(-100%);
            border-radius: 0 40px 40px 0;
        }

        .toggle {
            background: linear-gradient(135deg, #2FC2A0 0%, #B7D971 100%);
            height: 100%;
            color: #fff;
            position: relative;
            left: -100%;
            width: 200%;
            transform: translateX(0);
            transition: all 0.6s ease-in-out;
        }

        .container.active .toggle {
            transform: translateX(50%);
        }

        .toggle-panel {
            position: absolute;
            width: 50%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            padding: 0 20px;
            text-align: center;
            top: 0;
            transform: translateX(0);
            transition: all 0.6s ease-in-out;
        }

        .toggle-left {
            transform: translateX(-200%);
        }

        .container.active .toggle-left {
            transform: translateX(0);
        }

        .toggle-right {
            right: 0;
            transform: translateX(0);
        }

        .container.active .toggle-right {
            transform: translateX(200%);
        }

        .alert {
            font-size: clamp(14px, 2.5vw, 16px);
            padding: 12px;
            margin-bottom: 15px;
            border-radius: 8px;
            width: 100%;
            max-width: 350px;
            text-align: center;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .error-message {
            position: absolute;
            bottom: -20px;
            left: 0;
            color: #ff3333;
            font-size: 12px;
            text-align: left;
            animation: shake 0.3s;
            width: 100%;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        /* Floating Icons Container */
        .floating-icons {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 0;
            overflow: hidden;
        }

        /* Base Apple Icon Style */
        .apple-icon {
            position: absolute;
            opacity: 0.4;
            animation-timing-function: ease-in-out;
            animation-iteration-count: infinite;
            filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.1));
        }

        .apple-icon svg {
            width: 100%;
            height: 100%;
        }

        /* Individual Apple Animations */
        .apple-1 {
            width: 40px;
            height: 40px;
            top: 10%;
            left: 15%;
            animation: float 6s infinite;
        }

        .apple-2 {
            width: 35px;
            height: 35px;
            top: 20%;
            right: 20%;
            animation: float 7s infinite 1s;
        }

        .apple-3 {
            width: 45px;
            height: 45px;
            bottom: 15%;
            left: 10%;
            animation: float 8s infinite 2s;
        }

        .apple-4 {
            width: 38px;
            height: 38px;
            top: 60%;
            right: 15%;
            animation: float 6.5s infinite 1.5s;
        }

        .apple-5 {
            width: 42px;
            height: 42px;
            bottom: 25%;
            right: 25%;
            animation: float 7.5s infinite 0.5s;
        }

        .apple-6 {
            width: 36px;
            height: 36px;
            top: 40%;
            left: 8%;
            animation: float 8.5s infinite 3s;
        }

        /* Float Animation */
        @keyframes float {
            0%, 100% {
                transform: translateY(0) rotate(0deg);
            }
            25% {
                transform: translateY(-20px) rotate(5deg);
            }
            50% {
                transform: translateY(-40px) rotate(0deg);
            }
            75% {
                transform: translateY(-20px) rotate(-5deg);
            }
        }

        .apple-icon:nth-child(odd) {
            animation-name: floatRotate;
        }

        @keyframes floatRotate {
            0%, 100% {
                transform: translateY(0) rotate(0deg) scale(1);
            }
            33% {
                transform: translateY(-30px) rotate(10deg) scale(1.05);
            }
            66% {
                transform: translateY(-15px) rotate(-10deg) scale(0.95);
            }
        }

        /* Logo Animation */
        .logo-container img {
            animation: logoGlow 3s ease-in-out infinite;
            transition: transform 0.3s ease;
        }

        .logo-container img:hover {
            transform: scale(1.1) rotate(5deg);
        }

        @keyframes logoGlow {
            0%, 100% {
                filter: drop-shadow(0 0 5px rgba(183, 217, 113, 0.3));
            }
            50% {
                filter: drop-shadow(0 0 15px rgba(183, 217, 113, 0.6));
            }
        }

        /* Mobile Responsive */
        @media screen and (max-width: 768px) {
            body {
                padding: 10px;
                min-height: 100vh;
                min-height: -webkit-fill-available;
                overflow-y: auto;
            }

            .container {
                border-radius: 20px;
                min-height: auto;
                max-height: none;
                margin: 20px auto;
            }

            /* ซ่อนปุ่ม Register และ Login ในหน้าจอมือถือ */
            .toggle-container .hidden {
                display: none !important;
            }

            .form-container {
                position: static;
                width: 100%;
                display: block;
                overflow-y: visible; /* ให้เนื้อหาแสดงเต็ม */
            }

            .sign-in, .sign-up {
                position: relative;
                width: 100%;
                left: auto;
                transform: none;
                opacity: 1;
                display: none;
                min-height: 0; /* ยกเลิกข้อจำกัดความสูงขั้นต่ำ */
            }

            .sign-in.active-form,
            .sign-up.active-form {
                display: block;
                animation: fadeIn 0.3s ease-in; /* เพิ่ม animation นุ่มนวล */
            }

            @keyframes fadeIn {
                from {
                    opacity: 0;
                    transform: translateY(-10px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }

            .sign-in.active-form {
                display: block;
            }

            .sign-up.active-form {
                display: block;
            }

            .toggle-container {
                position: static;
                width: 100%;
                height: auto;
                min-height: 150px;
                transform: none;
                border-radius: 0;
                margin-bottom: 20px;
                overflow: visible;
            }

            /* ซ่อนปุ่ม hidden (สร้างบัญชีที่นี่ / เข้าสู่ระบบที่นี่) */
            .toggle-container button.hidden {
                display: none !important;
                visibility: hidden;
            }

            .toggle {
                position: static;
                width: 100%;
                left: auto;
                transform: none;
                padding: 30px 20px;
                border-radius: 20px 20px 0 0;
            }

            .toggle-panel {
                position: static;
                width: 100%;
                transform: none;
                padding: 0;
            }

            .toggle-left,
            .toggle-right {
                transform: none;
                display: none;
            }

            .toggle-left.active-panel,
            .toggle-right.active-panel {
                display: flex;
            }

            .apple-icon {
                opacity: 0.3;
            }
            
            .apple-1 {
                width: 30px;
                height: 30px;
                top: 8%;
                left: 10%;
            }
            
            .apple-2 {
                width: 28px;
                height: 28px;
                top: 15%;
                right: 12%;
            }
            
            .apple-3 {
                width: 32px;
                height: 32px;
                bottom: 12%;
                left: 8%;
            }
            
            .apple-4 {
                width: 30px;
                height: 30px;
                top: 55%;
                right: 10%;
            }
            
            .apple-5 {
                width: 28px;
                height: 28px;
                bottom: 20%;
                right: 15%;
            }
            
            .apple-6 {
                width: 26px;
                height: 26px;
                top: 35%;
                left: 5%;
            }

            .alert {
                max-width: 100%;
                font-size: 13px;
                padding: 10px;
            }
            
            .error-message {
                font-size: 11px;
            }

            .input-icon {
                margin-bottom: 20px;
            }

            /* แสดง mobile toggle links */
            .mobile-toggle {
                display: inline-block !important;
                color: #2FC2A0;
                font-weight: 500;
                margin: 15px 0;
                font-size: 14px;
                text-decoration: underline;
            }

            /* ซ่อนปุ่ม toggle ทั้งหมดในมือถือ */
            #register, #login {
                display: none !important;
            }
        }

        @media screen and (max-width: 480px) {
            .apple-icon {
                opacity: 0.25;
            }
            
            .apple-4,
            .apple-6 {
                display: none;
            }
        }

        html {
            scroll-behavior: smooth; /* เลื่อนหน้านุ่มนวล */
        }

        /* แก้ไข iOS Safari viewport height */
        @supports (-webkit-touch-callout: none) {
            body {
                min-height: -webkit-fill-available;
            }
        }

        @media screen and (max-width: 768px) {
    /* ... โค้ดเดิม ... */
    
    .container form {
        padding: 20px 20px 30px 20px; /* เพิ่ม padding ล่าง */
        min-height: 0;
    }
    
    /* ปรับระยะห่างของ input */
        .input-icon {
            margin-bottom: 20px;
        }
        
        /* ปรับขนาดปุ่ม */
        .container button {
            padding: 12px 20px;
            width: 90%;
            max-width: 300px;
            margin: 15px auto; /* เพิ่มระยะห่าง */
        }
        
        /* ปรับ link toggle */
        .mobile-toggle {
            display: inline-block !important;
            margin: 15px 0;
            font-size: 14px;
        }
        
        /* Alert responsive */
        .alert {
            max-width: 100%;
            font-size: 13px;
            padding: 10px;
            margin: 10px 0 15px 0;
        }
    }
    </style>
</head>

<body>
    <!-- Floating Apple Icons with SVG -->
    <div class="floating-icons">
        <div class="apple-icon apple-1">
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M12 2V4M12 4C10.3431 4 9 5.34315 9 7C9 8.65685 10.3431 10 12 10C13.6569 10 15 8.65685 15 7C15 5.34315 13.6569 4 12 4Z" stroke="#7CB342" stroke-width="2" stroke-linecap="round"/>
                <circle cx="12" cy="15" r="7" fill="#AED581"/>
            </svg>
        </div>
        <div class="apple-icon apple-2">
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle cx="12" cy="15" r="7" fill="#EF5350"/>
                <path d="M12 2V4M12 4C10.3431 4 9 5.34315 9 7C9 8.65685 10.3431 10 12 10C13.6569 10 15 8.65685 15 7C15 5.34315 13.6569 4 12 4Z" stroke="#388E3C" stroke-width="2" stroke-linecap="round"/>
            </svg>
        </div>
        <div class="apple-icon apple-3">
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle cx="12" cy="15" r="7" fill="#9CCC65"/>
                <path d="M12 2V4M12 4C10.3431 4 9 5.34315 9 7C9 8.65685 10.3431 10 12 10C13.6569 10 15 8.65685 15 7C15 5.34315 13.6569 4 12 4Z" stroke="#558B2F" stroke-width="2" stroke-linecap="round"/>
            </svg>
        </div>
        <div class="apple-icon apple-4">
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle cx="12" cy="12" r="10" fill="#66BB6A"/>
                <path d="M8 12H16M12 8V16" stroke="white" stroke-width="2" stroke-linecap="round"/>
            </svg>
        </div>
        <div class="apple-icon apple-5">
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle cx="12" cy="15" r="7" fill="#C5E1A5"/>
                <path d="M12 2V4M12 4C10.3431 4 9 5.34315 9 7C9 8.65685 10.3431 10 12 10C13.6569 10 15 8.65685 15 7C15 5.34315 13.6569 4 12 4Z" stroke="#7CB342" stroke-width="2" stroke-linecap="round"/>
            </svg>
        </div>
        <div class="apple-icon apple-6">
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <ellipse cx="12" cy="13" rx="5" ry="7" fill="#8BC34A"/>
                <circle cx="12" cy="10" r="2" fill="#FDD835"/>
            </svg>
        </div>
    </div>
    
    <div class="container" id="container">
        <div class="form-container sign-up" id="signUpForm">
            <!-- เพิ่ม Alert Messages สำหรับฟอร์มสมัครสมาชิก -->
            <?php if(isset($_GET['error']) && $_GET['error'] == 'username_taken'): ?>
                <div class="alert alert-danger">ชื่อผู้ใช้นี้ถูกใช้งานแล้ว</div>
            <?php endif; ?>

            <form action="process/signup_process.php" method="POST">
                <div class="logo-container">
                    <a href="index.php">
                        <img src="assets/images/logo.png" alt="logo "style>
                    </a>
                </div>
                <h1>สร้างบัญชีของคุณ</h1>
                <span>เริ่มต้นเส้นทางสุขภาพที่ดีกับเราได้เลย</span>

                <div class="input-icon">
                    <i class="fas fa-user"></i>
                    <input type="text" placeholder="ชื่อผู้ใช้" id="username" name="username" required>
                </div>

                <div class="input-icon">
                    <i class="fas fa-envelope"></i>
                    <input type="email" placeholder="อีเมล" id="email" name="email" required>
                </div>

                <div class="input-icon">
                    <i class="fas fa-lock"></i>
                    <input type="password" placeholder="รหัสผ่าน (อย่างน้อย 8 ตัว)" id="password" name="password" required>
                </div>

                <div class="input-icon">
                    <i class="fas fa-lock"></i>
                    <input type="password" placeholder="ยืนยันรหัสผ่าน" id="confirm_password" name="confirm_password" required>
                </div>

                <button type="submit">สร้างบัญชี</button>
                <a href="#" class="mobile-toggle" onclick="toggleMobileForms('signin')">มีบัญชีแล้ว? เข้าสู่ระบบ</a>
            </form>
        </div>

        


        <div class="form-container sign-in active-form" id="signInForm">
            <!-- เพิ่ม Alert Messages สำหรับฟอร์ม Login -->
            <?php if(isset($_GET['success']) && $_GET['success'] == 'registered'): ?>
                <div class="alert alert-success">สมัครสมาชิกสำเร็จ! กรุณาเข้าสู่ระบบ</div>
            <?php endif; ?>
            
            <?php if(isset($_GET['error']) && $_GET['error'] == 'invalid'): ?>
                <div class="alert alert-danger">ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง</div>
            <?php endif; ?>
            
            <?php if(isset($_GET['error']) && $_GET['error'] == 'empty'): ?>
                <div class="alert alert-danger">กรุณากรอกข้อมูลให้ครบถ้วน</div>
            <?php endif; ?>
            
            <?php if(isset($_GET['error']) && $_GET['error'] == 'invalid_format'): ?>
                <div class="alert alert-danger">กรุณากรอกชื่อผู้ใช้ภาษาอังกฤษหรืออีเมลที่ถูกต้อง</div>
            <?php endif; ?>   

            <form action="process/login_process.php" method="POST">
                <div class="logo-container">
                    <a href="index.php">
                         <img src="assets/images/logo.png" alt="logo "style>
                    </a>
                </div>
                <h1>เข้าสู่ระบบ</h1>
                <span>ป้อนชื่อผู้ใช้และรหัสผ่านของคุณ</span>
                
                <div class="input-icon">
                    <i class="fas fa-user"></i>
                    <input type="text" placeholder="ชื่อผู้ใช้" id="username" name="username" required>
                </div>
                
                <div class="input-icon">
                    <i class="fas fa-lock"></i>
                    <input type="password" placeholder="รหัสผ่าน" id="password" name="password" required>
                </div>
                
                <a href="forgot_password.php">ลืมรหัสผ่าน?</a>
                <button type="submit">เข้าสู่ระบบ</button>
                <a href="#" class="mobile-toggle" onclick="toggleMobileForms('signup')">ยังไม่มีบัญชี? สมัครสมาชิก</a>
            </form>
        </div>

        <div class="toggle-container">
            <div class="toggle">
                <div class="toggle-panel toggle-left" id="toggleLeft">
                    <h1 id="welcome-text">ยินดีต้อนรับ!</h1>
                    <p>เข้าสู่ระบบเพื่อใช้งานเว็บไซต์</p>
                    <button class="hidden" id="login">เข้าสู่ระบบที่นี่</button>
                </div>
                <div class="toggle-panel toggle-right active-panel" id="toggleRight">
                    <h1 id="greeting-text">สวัสดี, เพื่อน!</h1>
                    <p>ยินดีตอนรับ เข้าสู่ FitMealWeek</p>
                    <button class="hidden" id="register">สร้างบัญชีที่นี่</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        const container = document.getElementById('container');
        const registerBtn = document.getElementById('register');
        const loginBtn = document.getElementById('login');

        // Desktop toggle
        if (registerBtn) {
            registerBtn.addEventListener('click', () => {
                container.classList.add("active");
                typeWriterEffect("greeting-text", "สวัสดี, เพื่อน!", 100);
            });
        }

        if (loginBtn) {
            loginBtn.addEventListener('click', () => {
                container.classList.remove("active");
                typeWriterEffect("welcome-text", "ยินดีต้อนรับ!", 100);
            });
        }

        // Mobile toggle function
        function toggleMobileForms(formType) {
            const signInForm = document.getElementById('signInForm');
            const signUpForm = document.getElementById('signUpForm');
            const toggleLeft = document.getElementById('toggleLeft');
            const toggleRight = document.getElementById('toggleRight');

            if (window.innerWidth <= 768) {
                if (formType === 'signup') {
                    signInForm.classList.remove('active-form');
                    signUpForm.classList.add('active-form');
                    toggleLeft.classList.add('active-panel');
                    toggleRight.classList.remove('active-panel');
                    
                    // เลื่อนขึ้นด้านบนนุ่มนวล
                    setTimeout(() => {
                        window.scrollTo({
                            top: 0,
                            behavior: 'smooth'
                        });
                    }, 100);
                } else {
                    signInForm.classList.add('active-form');
                    signUpForm.classList.remove('active-form');
                    toggleLeft.classList.remove('active-panel');
                    toggleRight.classList.add('active-panel');
                    
                    // เลื่อนขึ้นด้านบนนุ่มนวล
                    setTimeout(() => {
                        window.scrollTo({
                            top: 0,
                            behavior: 'smooth'
                        });
                    }, 100);
                }
            }
        }

        // Typewriter effect
        let typingTimers = {};

        function typeWriterEffect(elementId, text, speed) {
            const element = document.getElementById(elementId);
            if (!element) return;

            if (typingTimers[elementId]) {
                clearTimeout(typingTimers[elementId]);
            }

            element.innerHTML = "";
            let index = 0;

            function type() {
                if (index < text.length) {
                    element.innerHTML += text.charAt(index);
                    index++;
                    typingTimers[elementId] = setTimeout(type, speed);
                } else {
                    typingTimers[elementId] = null;
                }
            }
            type();
        }

        // Initialize typewriter on load
        window.onload = () => {
            typeWriterEffect("welcome-text", "ยินดีต้อนรับ!", 100);
            typeWriterEffect("greeting-text", "สวัสดี, เพื่อน!", 100);
        };

        // Handle window resize
        let resizeTimer;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(() => {
                if (window.innerWidth > 768) {
                    // Reset to desktop view
                    document.querySelectorAll('.form-container').forEach(el => {
                        el.classList.remove('active-form');
                    });
                    document.querySelectorAll('.toggle-panel').forEach(el => {
                        el.classList.remove('active-panel');
                    });
                } else {
                    // Ensure one form is active on mobile
                    const signInForm = document.getElementById('signInForm');
                    const toggleRight = document.getElementById('toggleRight');
                    if (!document.querySelector('.form-container.active-form')) {
                        signInForm.classList.add('active-form');
                        toggleRight.classList.add('active-panel');
                    }
                }
            }, 250);
        });

        // Check initial screen size
        if (window.innerWidth <= 768) {
            document.getElementById('signInForm').classList.add('active-form');
            document.getElementById('toggleRight').classList.add('active-panel');
        }

        // Style mobile toggle links และซ่อนปุ่ม hidden ในมือถือ
        function updateMobileDisplay() {
            const isMobile = window.innerWidth <= 768;
            
            // แสดง/ซ่อน mobile toggle links
            document.querySelectorAll('.mobile-toggle').forEach(link => {
                link.style.display = isMobile ? 'inline-block' : 'none';
            });
            
            // ซ่อนปุ่ม register/login ในมือถือ
            const registerBtn = document.getElementById('register');
            const loginBtn = document.getElementById('login');
            
            if (registerBtn && loginBtn) {
                registerBtn.style.display = isMobile ? 'none' : 'block';
                loginBtn.style.display = isMobile ? 'none' : 'block';
            }
        }

        // เรียกใช้งานตอน load
        updateMobileDisplay();

        // เรียกใช้งานตอน resize
        window.addEventListener('resize', updateMobileDisplay);

        // ======= Validation Functions =======

// ตรวจสอบชื่อผู้ใช้ (รับเฉพาะภาษาอังกฤษและตัวเลข ไม่มีอักขระพิเศษ)
function validateUsername(username) {
    const regex = /^[a-zA-Z0-9]+$/;
    return regex.test(username);
}

// ตรวจสอบอีเมล
function validateEmail(email) {
    const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return regex.test(email);
}

// ตรวจสอบรหัสผ่าน (อย่างน้อย 8 ตัว รับเฉพาะภาษาอังกฤษและตัวเลข)
function validatePassword(password) {
    const regex = /^[a-zA-Z0-9]{8,}$/;
    return regex.test(password);
}

// แสดง error message
function showError(inputElement, message) {
    // ลบ error เดิม (ถ้ามี)
    const existingError = inputElement.parentElement.querySelector('.error-message');
    if (existingError) {
        existingError.remove();
    }
    
    // สร้าง error message ใหม่
    const errorDiv = document.createElement('div');
    errorDiv.className = 'error-message';
    errorDiv.style.color = '#ff3333';
    errorDiv.style.fontSize = '12px';
    errorDiv.style.marginTop = '5px';
    errorDiv.style.textAlign = 'left';
    errorDiv.textContent = message;
    
    inputElement.parentElement.appendChild(errorDiv);
    inputElement.style.borderColor = '#ff3333';
}

// ลบ error message
function clearError(inputElement) {
    const existingError = inputElement.parentElement.querySelector('.error-message');
    if (existingError) {
        existingError.remove();
    }
    inputElement.style.borderColor = '';
}

    // ======= Sign Up Form Validation =======
    const signUpForm = document.querySelector('#signUpForm form');
    if (signUpForm) {
        signUpForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const username = document.querySelector('#signUpForm #username');
            const email = document.querySelector('#signUpForm #email');
            const password = document.querySelector('#signUpForm #password');
            const confirmPassword = document.querySelector('#signUpForm #confirm_password');
            
            let isValid = true;
            
            // ตรวจสอบชื่อผู้ใช้
            if (!validateUsername(username.value)) {
                showError(username, 'ชื่อผู้ใช้ต้องเป็นภาษาอังกฤษและตัวเลขเท่านั้น (ไม่มีอักขระพิเศษ)');
                isValid = false;
            } else {
                clearError(username);
            }
            
            // ตรวจสอบอีเมล
            if (!validateEmail(email.value)) {
                showError(email, 'รูปแบบอีเมลไม่ถูกต้อง');
                isValid = false;
            } else {
                clearError(email);
            }
            
            // ตรวจสอบรหัสผ่าน
            if (!validatePassword(password.value)) {
                showError(password, 'รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร (ภาษาอังกฤษและตัวเลขเท่านั้น)');
                isValid = false;
            } else {
                clearError(password);
            }
            
            // ตรวจสอบการยืนยันรหัสผ่าน
            if (password.value !== confirmPassword.value) {
                showError(confirmPassword, 'รหัสผ่านไม่ตรงกัน');
                isValid = false;
            } else {
                clearError(confirmPassword);
            }
            
            // ถ้าผ่านทุกเงื่อนไข ให้ submit ฟอร์ม
            if (isValid) {
                this.submit();
            }
        });
    }

    // ======= Sign In Form Validation =======
    const signInForm = document.querySelector('#signInForm form');
    if (signInForm) {
        signInForm.addEventListener('submit', function(e) {
            const username = document.querySelector('#signInForm #username');
            const password = document.querySelector('#signInForm #password');
            
            let isValid = true;
            
            // ตรวจสอบว่าชื่อผู้ใช้เป็นภาษาอังกฤษหรืออีเมล
            const isEmail = validateEmail(username.value);
            const isValidUsername = validateUsername(username.value);
            
            if (!isEmail && !isValidUsername) {
                e.preventDefault();
                showError(username, 'กรุณากรอกชื่อผู้ใช้ (ภาษาอังกฤษ) หรืออีเมลที่ถูกต้อง');
                isValid = false;
            } else {
                clearError(username);
            }
            
            // ตรวจสอบรหัสผ่านว่าไม่เป็นค่าว่าง
            if (password.value.trim() === '') {
                e.preventDefault();
                showError(password, 'กรุณากรอกรหัสผ่าน');
                isValid = false;
            } else {
                clearError(password);
            }
        });
    }

    // Real-time validation
    document.addEventListener('DOMContentLoaded', function() {
        // Validation สำหรับชื่อผู้ใช้ตอนพิมพ์
        const signUpUsername = document.querySelector('#signUpForm #username');
        if (signUpUsername) {
            signUpUsername.addEventListener('input', function() {
                if (this.value && !validateUsername(this.value)) {
                    showError(this, 'ใช้ได้เฉพาะ a-z, A-Z, 0-9 เท่านั้น');
                } else {
                    clearError(this);
                }
            });
        }
        
        // Validation สำหรับรหัสผ่านตอนพิมพ์
        const signUpPassword = document.querySelector('#signUpForm #password');
        if (signUpPassword) {
            signUpPassword.addEventListener('input', function() {
                if (this.value && !validatePassword(this.value)) {
                    showError(this, 'ต้องมีอย่างน้อย 8 ตัว (a-z, A-Z, 0-9)');
                } else {
                    clearError(this);
                }
            });
        }
        
        // Validation สำหรับยืนยันรหัสผ่าน
        const confirmPassword = document.querySelector('#signUpForm #confirm_password');
        if (confirmPassword) {
            confirmPassword.addEventListener('input', function() {
                const password = document.querySelector('#signUpForm #password');
                if (this.value && this.value !== password.value) {
                    showError(this, 'รหัสผ่านไม่ตรงกัน');
                } else {
                    clearError(this);
                }
            });
        }
    });
    </script>
</body>

</html>