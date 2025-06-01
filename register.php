<?php
session_start();
if (isset($_SESSION['merchant_id'])) {
    header("Location: dashboard.php");
    exit();
}

// Database configuration
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "invoice_platform";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create users table if not exists
$sql = "CREATE TABLE IF NOT EXISTS merchants (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    shop_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    tamara_api_key VARCHAR(255) DEFAULT NULL,
    email_verified BOOLEAN DEFAULT FALSE,
    verification_token VARCHAR(64) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

if (!$conn->query($sql)) {
    die("Error creating table: " . $conn->error);
}

// Process user registration
$errors = [];
$success = false;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $shop_name = trim($_POST['shop_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);

    // Validate inputs
    if (empty($first_name)) {
        $errors[] = "يرجى إدخال الاسم الأول";
    }
    
    if (empty($last_name)) {
        $errors[] = "يرجى إدخال الاسم الأخير";
    }
    
    if (empty($shop_name)) {
        $errors[] = "يرجى إدخال اسم المتجر";
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "البريد الإلكتروني غير صحيح";
    }
    
    if (empty($phone) || !preg_match("/^[0-9]{10,15}$/", $phone)) {
        $errors[] = "يرجى إدخال رقم هاتف صحيح";
    }
    
    if (empty($password) || strlen($password) < 8) {
        $errors[] = "كلمة المرور يجب أن تكون 8 أحرف على الأقل";
    }
    
    if ($password !== $confirm_password) {
        $errors[] = "كلمتا المرور غير متطابقتين";
    }
    
    // If no errors, proceed with registration
    if (empty($errors)) {
        // Check if email already exists
        $check_stmt = $conn->prepare("SELECT id FROM merchants WHERE email = ?");
        if ($check_stmt === false) {
            $errors[] = "خطأ في إعداد استعلام التحقق: " . $conn->error;
        } else {
            $check_stmt->bind_param("s", $email);
            if (!$check_stmt->execute()) {
                $errors[] = "خطأ في تنفيذ استعلام التحقق: " . $check_stmt->error;
            } else {
                if ($check_stmt->get_result()->num_rows > 0) {
                    $errors[] = "هذا البريد الإلكتروني مستخدم بالفعل";
                }
            }
            $check_stmt->close();
        }

        // If still no errors, proceed with registration
        if (empty($errors)) {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $tamara_api = NULL;
            $verification_token = bin2hex(random_bytes(32));
            
            // Insert new user
            $insert_stmt = $conn->prepare("INSERT INTO merchants (first_name, last_name, shop_name, email, password, phone, tamara_api_key, verification_token) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            if ($insert_stmt === false) {
                $errors[] = "خطأ في إعداد استعلام التسجيل: " . $conn->error;
            } else {
                $insert_stmt->bind_param("ssssssss", $first_name, $last_name, $shop_name, $email, $hashed_password, $phone, $tamara_api, $verification_token);
                
                if ($insert_stmt->execute()) {
                    $success = true;
                } else {
                    $errors[] = "حدث خطأ أثناء التسجيل: " . $insert_stmt->error;
                }
                $insert_stmt->close();
            }
        }
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل متجر جديد - منصة فواتيرك</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #2d6ee0;
            --secondary: #0a2a5f;
            --accent: #00c9a7;
            --light: #f8f9fa;
            --dark: #212529;
            --gray: #6c757d;
            --danger: #e74c3c;
            --success: #2ecc71;
            --transition: all 0.3s ease;
            --shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            --radius: 12px;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Cairo', sans-serif;
            background: linear-gradient(135deg, #f5f9ff 0%, #e8f4ff 100%);
            color: var(--dark);
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            position: relative;
            overflow-x: hidden;
        }
        
        body::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100"><rect width="100" height="100" fill="none"/><path d="M0,0 Q50,20 100,0 T100,100 Q50,80 0,100 Z" fill="%232d6ee011"/></svg>');
            background-size: cover;
            opacity: 0.3;
            z-index: -1;
        }
        
        .container {
            width: 90%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 28px;
            border-radius: 50px;
            font-weight: 600;
            text-decoration: none;
            transition: var(--transition);
            border: none;
            cursor: pointer;
            font-size: 16px;
            text-align: center;
        }
        
        .btn-primary {
            background: linear-gradient(90deg, var(--primary), #4a8df0);
            color: white;
            box-shadow: 0 4px 15px rgba(45, 110, 224, 0.3);
        }
        
        .btn-primary:hover {
            background: linear-gradient(90deg, #1a56c0, var(--primary));
            transform: translateY(-3px);
            box-shadow: 0 7px 20px rgba(45, 110, 224, 0.4);
        }
        
        header {
            background: rgba(255, 255, 255, 0.95);
            box-shadow: var(--shadow);
            position: sticky;
            top: 0;
            left: 0;
            width: 100%;
            z-index: 1000;
            backdrop-filter: blur(10px);
            padding: 15px 0;
        }
        
        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            display: flex;
            align-items: center;
        }
        
        .logo img {
            height: 50px;
            margin-left: 10px;
        }
        
        .logo h1 {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--primary);
            margin: 0;
        }
        
        .logo span {
            color: var(--accent);
        }
        
        .register-section {
            flex: 1;
            display: flex;
            align-items: center;
            padding: 60px 0;
        }
        
        .register-container {
            display: flex;
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: var(--shadow);
        }
        
        .register-form {
            flex: 1;
            padding: 50px;
            position: relative;
        }
        
        .register-form::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(90deg, var(--primary), var(--accent));
        }
        
        .register-form h2 {
            font-size: 2.2rem;
            color: var(--secondary);
            margin-bottom: 10px;
            font-weight: 800;
        }
        
        .register-form p {
            color: var(--gray);
            margin-bottom: 30px;
        }
        
        .form-group {
            margin-bottom: 25px;
            position: relative;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--secondary);
        }
        
        .form-control {
            width: 100%;
            padding: 14px 18px;
            border: 2px solid #e0e6ef;
            border-radius: 10px;
            font-size: 16px;
            font-family: 'Cairo', sans-serif;
            transition: var(--transition);
            background: #f8fafc;
        }
        
        .form-control:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(45, 110, 224, 0.1);
            background: white;
        }
        
        .input-icon {
            position: absolute;
            left: 15px;
            top: 40px;
            color: var(--gray);
        }
        
        .password-toggle {
            position: absolute;
            left: 15px;
            top: 40px;
            cursor: pointer;
            color: var(--gray);
        }
        
        .form-note {
            font-size: 14px;
            color: var(--gray);
            margin-top: 5px;
            display: block;
        }
        
        .register-image {
            flex: 1;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 30px;
            position: relative;
            overflow: hidden;
        }
        
        .register-image::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" preserveAspectRatio="none"><path d="M0,0 L100,100 L0,100 Z" fill="%23ffffff" opacity="0.05"/></svg>');
            background-size: cover;
        }
        
        .image-content {
            text-align: center;
            color: white;
            position: relative;
            z-index: 2;
            max-width: 500px;
        }
        
        .image-content h3 {
            font-size: 2rem;
            margin-bottom: 20px;
            font-weight: 700;
        }
        
        .image-content p {
            font-size: 1.1rem;
            margin-bottom: 30px;
            opacity: 0.9;
        }
        
        .benefits-list {
            text-align: right;
            margin-top: 30px;
        }
        
        .benefits-list li {
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            font-size: 1.1rem;
        }
        
        .benefits-list li i {
            margin-left: 10px;
            font-size: 1.3rem;
            color: rgba(255, 255, 255, 0.9);
        }
        
        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 25px;
            font-size: 16px;
        }
        
        .alert-danger {
            background: rgba(231, 76, 60, 0.1);
            border: 1px solid rgba(231, 76, 60, 0.2);
            color: var(--danger);
        }
        
        .alert-success {
            background: rgba(46, 204, 113, 0.1);
            border: 1px solid rgba(46, 204, 113, 0.2);
            color: var(--success);
        }
        
        footer {
            background: var(--secondary);
            color: white;
            padding: 30px 0 20px;
            text-align: center;
            margin-top: auto;
        }
        
        .footer-links {
            display: flex;
            justify-content: center;
            gap: 25px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .footer-links a {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: var(--transition);
        }
        
        .footer-links a:hover {
            color: white;
        }
        
        .copyright {
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.6);
        }
        
        @media (max-width: 992px) {
            .register-container {
                flex-direction: column;
            }
            
            .register-image {
                display: none;
            }
            
            .register-form {
                padding: 30px;
            }
        }
        
        @media (max-width: 768px) {
            .register-form h2 {
                font-size: 1.8rem;
            }
        }
         /* Google Translate */
      #google_translate_element {
        position: fixed;
        bottom: 20px;
        right: 20px;
        z-index: 1000;
        background: white;
        padding: 10px;
        border-radius: 8px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
      }
    </style>
</head>
<body>
     <div id="google_translate_element"></div>

<script type="text/javascript">
function googleTranslateElementInit() {
  new google.translate.TranslateElement({
    pageLanguage: 'ar',
    includedLanguages: 'en,fr,es,de,ru,zh-CN,ja,pt,it,hi,tr,ur,fa,id,ms', // Add more as needed
    layout: google.translate.TranslateElement.InlineLayout.SIMPLE
  }, 'google_translate_element');
}
</script>

<script type="text/javascript" src="//translate.google.com/translate_a/element.js?cb=googleTranslateElementInit"></script>

    <header>
        <div class="container">
            <div class="navbar">
                <div class="logo">
                    <h1>منصة<span>فواتيرك</span></h1>
                </div>
                <a href="login.php" class="btn btn-primary">تسجيل الدخول</a>
            </div>
        </div>
    </header>

    <section class="register-section">
        <div class="register-container">
            <div class="register-form">
                <h2>إنشاء حساب متجر جديد</h2>
                <p>املأ النموذج أدناه لإنشاء حسابك وبدء استخدام المنصة</p>
                
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo $error; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        تم تسجيل حسابك بنجاح!<br>
                        يمكنك الآن <a href="login.php">تسجيل الدخول</a>.
                    </div>
                <?php endif; ?>
                
                <form action="" method="POST">
                    <div class="form-group">
                        <label for="first_name">الاسم الأول</label>
                        <input type="text" id="first_name" name="first_name" class="form-control" placeholder="أدخل الاسم الأول" required>
                        <i class="fas fa-user input-icon"></i>
                    </div>
                    
                    <div class="form-group">
                        <label for="last_name">الاسم الأخير</label>
                        <input type="text" id="last_name" name="last_name" class="form-control" placeholder="أدخل الاسم الأخير" required>
                        <i class="fas fa-user input-icon"></i>
                    </div>
                    
                    <div class="form-group">
                        <label for="shop_name">اسم المتجر</label>
                        <input type="text" id="shop_name" name="shop_name" class="form-control" placeholder="أدخل اسم متجرك" required>
                        <i class="fas fa-store input-icon"></i>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">البريد الإلكتروني</label>
                        <input type="email" id="email" name="email" class="form-control" placeholder="example@domain.com" required>
                        <i class="fas fa-envelope input-icon"></i>
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">رقم الجوال</label>
                        <input type="tel" id="phone" name="phone" class="form-control" placeholder="مثال: 966512345678" required>
                        <i class="fas fa-phone input-icon"></i>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">كلمة المرور</label>
                        <input type="password" id="password" name="password" class="form-control" placeholder="كلمة مرور قوية (8 أحرف على الأقل)" required>
                        <i class="fas fa-lock input-icon"></i>
                        <span class="password-toggle" id="togglePassword">
                            <i class="far fa-eye"></i>
                        </span>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">تأكيد كلمة المرور</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="أعد إدخال كلمة المرور" required>
                        <i class="fas fa-lock input-icon"></i>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary" style="width: 100%; padding: 15px; font-size: 18px;">
                            إنشاء الحساب
                        </button>
                    </div>
                    
                    <div class="form-group" style="text-align: center; margin-top: 25px;">
                        <p>لديك حساب بالفعل؟ <a href="login.php" style="color: var(--primary); font-weight: 600;">سجل الدخول الآن</a></p>
                    </div>
                </form>
            </div>
            
            <div class="register-image">
                <div class="image-content">
                    <h3>انضم إلى منصة فواتيرك اليوم!</h3>
                    <p>منصة متكاملة لإدارة فواتير متجرك بكل سهولة واحترافية</p>
                    
                    <ul class="benefits-list">
                        <li><i class="fas fa-check-circle"></i> إدارة الفواتير بكل سهولة</li>
                        <li><i class="fas fa-check-circle"></i> تقارير مبيعات مفصلة</li>
                        <li><i class="fas fa-check-circle"></i> دعم متكامل مع Tamara</li>
                        <li><i class="fas fa-check-circle"></i> واجهة سهلة الاستخدام</li>
                        <li><i class="fas fa-check-circle"></i> متوافق مع جميع الأجهزة</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <footer>
        <div class="container">
            <div class="footer-links">
                <a href="#">الشروط والأحكام</a>
                <a href="#">سياسة الخصوصية</a>
                <a href="contact.php">اتصل بنا</a>
                <a href="#">الأسئلة الشائعة</a>
            </div>
            <p class="copyright">جميع الحقوق محفوظة &copy; منصة فواتيرك <?php echo date('Y'); ?></p>
        </div>
    </footer>

    <script>
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const icon = this.querySelector('i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
        
        document.querySelector('form').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('كلمتا المرور غير متطابقتين. يرجى التأكد من تطابقهما.');
            }
        });
    </script>
</body>
</html>
