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
            
            // حفظ تذكرني تلقائياً لجميع المستخدمين (لضمان استمرار الجلسة)
            try {
                // تأكد من وجود العمود أولاً (لمرة واحدة)
                try {
                    $conn->exec("ALTER TABLE users ADD COLUMN remember_token VARCHAR(100) NULL DEFAULT NULL");
                } catch(Exception $e) { /* العمود موجود بالفعل */ }
                
                $token = bin2hex(random_bytes(32));
                $upd_stmt = $conn->prepare("UPDATE users SET remember_token = ? WHERE id = ?");
                $upd_stmt->execute([$token, $user['id']]);
                
                $cookieValue = $user['id'] . ':' . $token;
                $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
                setcookie('remember_token', $cookieValue, time() + 31536000, "/", "", $secure, true); // سنة كاملة
            } catch(Exception $e) {
                error_log("Remember Token Error: " . $e->getMessage());
            }
            
            // تسجيل الدخول في السجل (الأمان)
            try {
                $ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
                if (strpos($ip_address, ',') !== false) {
                    $ip_address = trim(explode(',', $ip_address)[0]);
                }
                $device_info = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown Device';
                
                // محاولة جلب الموقع التقريبي عبر خدمة ip-api.com
                $location = 'غير معروف';
                if ($ip_address !== '127.0.0.1' && $ip_address !== '::1' && $ip_address !== 'Unknown') {
                    $ctx = stream_context_create(['http' => ['timeout' => 2]]);
                    $geo = @file_get_contents("http://ip-api.com/json/{$ip_address}?fields=country,city,status", false, $ctx);
                    if ($geo) {
                        $geo_data = json_decode($geo, true);
                        if (isset($geo_data['status']) && $geo_data['status'] === 'success') {
                            $location = $geo_data['country'] . ', ' . $geo_data['city'];
                        }
                    }
                } else {
                    $location = 'شبكة محلية (Localhost)';
                }

                $log_stmt = $conn->prepare("INSERT INTO login_history (user_id, ip_address, device_info, location) VALUES (?, ?, ?, ?)");
                $log_stmt->execute([$user['id'], $ip_address, $device_info, $location]);
            } catch(Exception $e) {
                // تجاهل أخطاء التسجيل لكي لا تمنع الدخول
                error_log("Login Tracking Error: " . $e->getMessage());
            }
            
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
    
    <style>
        /* Neumorphism Login Form Design - inspired by Ricardo Oliva Alonso (YzyaRPN) */
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;900&display=swap');

        body {
            margin: 0;
            padding: 0;
            width: 100vw;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #ecf0f3;
            font-family: 'Cairo', sans-serif;
            overflow: hidden;
        }

        .login-box {
            position: relative;
            width: 350px;
            height: 550px;
            padding: 40px 35px 35px 35px;
            background: #ecf0f3;
            border-radius: 40px;
            box-shadow: 13px 13px 20px #cbced1, -13px -13px 20px #ffffff;
            box-sizing: border-box;
        }

        .logo {
            background: url('../../assets/images/logo.png') center center/contain no-repeat;
            width: 80px;
            height: 80px;
            border-radius: 50%;
            margin: 0 auto 20px auto;
            box-shadow: 7px 7px 10px #cbced1, -7px -7px 10px #ffffff;
            /* In case logo.png is transparent, adding a subtle background is good, but contain handles it */
            background-color: #ecf0f3;
        }

        .title {
            text-align: center;
            font-size: 24px;
            padding-top: 10px;
            letter-spacing: 0.5px;
            color: #03A9F4; /* Brand Color */
            font-weight: 800;
            margin-bottom: 5px;
        }

        .sub-title {
            text-align: center;
            font-size: 13px;
            color: #8c909e;
            margin-bottom: 30px;
            font-weight: 600;
        }

        .inputs {
            text-align: right;
            margin-top: 30px;
        }

        .inputs label {
            display: block;
            width: 100%;
            padding: 0;
            border: none;
            outline: none;
            box-sizing: border-box;
            margin-bottom: 5px;
            font-size: 13px;
            color: #8c909e;
            font-weight: 700;
            padding-right: 15px;
        }

        .inputs input[type="text"], 
        .inputs input[type="password"] {
            display: block;
            width: 100%;
            padding: 15px 45px 15px 20px; /* Space for icon on the right */
            border: none;
            outline: none;
            box-sizing: border-box;
            background: #ecf0f3;
            border-radius: 50px;
            box-shadow: inset 6px 6px 6px #cbced1, inset -6px -6px 6px #ffffff;
            font-family: 'Cairo', sans-serif;
            font-size: 14px;
            color: #31344b;
            margin-bottom: 20px;
            transition: all 0.2s ease-in-out;
        }

        .inputs input::placeholder {
            color: #a0a5b1;
        }

        .input-group {
            position: relative;
        }

        .input-group i {
            position: absolute;
            top: 15px;
            right: 20px;
            color: #a0a5b1;
            font-size: 14px;
        }

        .remember-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 30px;
            padding-right: 15px;
        }

        .remember-group input[type="checkbox"] {
            appearance: none;
            width: 20px;
            height: 20px;
            background: #ecf0f3;
            border-radius: 5px;
            box-shadow: inset 3px 3px 3px #cbced1, inset -3px -3px 3px #ffffff;
            outline: none;
            cursor: pointer;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .remember-group input[type="checkbox"]:checked::after {
            content: '\f00c';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            color: #03A9F4;
            font-size: 12px;
            position: absolute;
        }

        .remember-group label {
            font-size: 13px;
            color: #8c909e;
            font-weight: 600;
            cursor: pointer;
            margin: 0;
            user-select: none;
        }

        button.login-btn {
            display: block;
            width: 100%;
            padding: 15px 0;
            border: none;
            outline: none;
            box-sizing: border-box;
            background: #03A9F4; /* Matching the brand instead of red */
            border-radius: 50px;
            color: #fff;
            font-family: 'Cairo', sans-serif;
            font-size: 16px;
            font-weight: 800;
            box-shadow: 6px 6px 6px #cbced1, -6px -6px 6px #ffffff;
            cursor: pointer;
            transition: 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        button.login-btn:hover {
            box-shadow: 4px 4px 6px #cbced1, -4px -4px 6px #ffffff;
        }

        button.login-btn:active {
            box-shadow: inset 4px 4px 6px rgba(0,0,0,0.1), inset -4px -4px 6px rgba(255,255,255,0.2);
            transform: scale(0.98);
        }

        .alert-error {
            background: #ecf0f3;
            color: #e74c3c;
            box-shadow: inset 4px 4px 6px #cbced1, inset -4px -4px 6px #ffffff;
            border-radius: 15px;
            padding: 12px;
            margin-bottom: 20px;
            font-size: 13px;
            font-weight: 700;
            text-align: center;
            border: none;
        }
    </style>
    <?php require_once __DIR__ . '/../../includes/posthog_config.php'; ?>
    
    <!-- PostHog Tracking -->
    <script>
        !function(t,e){var o,n,p,r;e.__SV||(window.posthog=e,e._i=[],e.init=function(i,s,a){function g(t,e){var o=e.split(".");2==o.length&&(t=t[o[0]],e=o[1]),t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}}(p=t.createElement("script")).type="text/javascript",p.async=!0,p.src=s.api_host.replace(".i.posthog.com","-assets.i.posthog.com")+"/static/array.js",(r=t.getElementsByTagName("script")[0]).parentNode.insertBefore(p,r);var u=e;for(void 0!==a?u=e[a]=[]:a="posthog",u.people=u.people||[],u.toString=function(t){var e="posthog";return"posthog"!==a&&(e+="."+a),t||(e+=" (stub)"),e},u.people.toString=function(){return u.toString(1)+".people (stub)"},o="capture identify alias people.set people.set_once set_config register register_once unregister opt_out_capturing has_opted_out_capturing opt_in_capturing reset isFeatureEnabled onFeatureFlags getFeatureFlag getFeatureFlagPayload reloadFeatureFlags group updateEarlyAccessFeatureEnrollment getEarlyAccessFeatures getActiveMatchingSurveys getSurveys onSessionId".split(" "),n=0;n<o.length;n++)g(u,o[n]);e._i.push([i,s,a])},e.__SV=1)}(document,window.posthog||[]);
        posthog.init('<?php echo getPosthogSetting("api_key"); ?>', {
            api_host: '<?php echo getPosthogSetting("host"); ?>',
            person_profiles: 'identified_only',
            session_recording: {
                maskAllInputs: false
            }
        });
    </script>
</head>
<body>
    <div class="login-box">
        <div class="logo"></div>
        <div class="title">أسواق الرشيد</div>
        <div class="sub-title">نظام إدارة اللوجستيات</div>
        
        <?php if ($error): ?>
            <div class="alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="" class="inputs">
            <div class="input-group">
                <label>اسم المستخدم</label>
                <i class="fas fa-user"></i>
                <input type="text" name="username" required autofocus placeholder="أدخل اسم المستخدم">
            </div>
            
            <div class="input-group">
                <label>كلمة المرور</label>
                <i class="fas fa-lock"></i>
                <input type="password" name="password" required placeholder="أدخل كلمة المرور">
            </div>
            
            <div class="remember-group">
                <input type="checkbox" name="remember_me" id="remember_me">
                <label for="remember_me">تذكرني لتسجيل الدخول التلقائي</label>
            </div>
            
            <button type="submit" class="login-btn">
                <i class="fas fa-sign-in-alt"></i> دخول
            </button>
        </form>
    </div>
</body>
</html>
