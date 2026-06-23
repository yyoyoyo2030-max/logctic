<?php
/**
 * نظام إدارة اللوجستيك
 * صفحة تسجيل الدخول
 */

header('Content-Type: text/html; charset=utf-8');
require_once '../../config/config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $username = clean_input($_POST['username']);
        $password = $_POST['password'];
        
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['branch_id'] = $user['branch_id'];
            
            // إعادة توجيه حسب الدور
            if ($user['role'] == 'driver') {
                redirect('views/drivers/driver_transfers.php');
            } else {
                redirect('views/dashboard/dashboard.php');
            }
        } else {
            $error = 'اسم المستخدم أو كلمة المرور غير صحيحة';
        }
    } catch(Exception $e) {
        // تسجيل الخطأ في وضع الإنتاج
        if (PRODUCTION_MODE) {
            error_log("Login Error: " . $e->getMessage());
            $error = 'حدث خطأ أثناء تسجيل الدخول. يرجى المحاولة لاحقاً.';
        } else {
            $error = 'خطأ: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول - Logistic Pro</title>
    <link rel="icon" type="image/svg+xml" href="../../assets/images/favicon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-box">
            <div class="login-header">
                <img src="<?php echo SITE_URL; ?>/assets/images/logo.png" alt="أسواق الرشيد" class="login-logo" style="width: 120px; height: auto; margin-bottom: 20px;">
                <h1>أسواق الرشيد</h1>
                <p>نظام إدارة اللوجستيات</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label><i class="fas fa-user"></i> اسم المستخدم</label>
                    <input type="text" name="username" required autofocus placeholder="أدخل اسم المستخدم">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-lock"></i> كلمة المرور</label>
                    <input type="password" name="password" required placeholder="أدخل كلمة المرور">
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fas fa-sign-in-alt"></i> دخول
                </button>
            </form>
        </div>
    </div>
</body>
</html>
