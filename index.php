<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Check if user has remember me cookie
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
    $token = mysqli_real_escape_string($conn, $_COOKIE['remember_token']);
    $query = "SELECT * FROM employees WHERE remember_token = '$token' AND status = 'active'";
    $result = mysqli_query($conn, $query);
    
    if (mysqli_num_rows($result) == 1) {
        $user = mysqli_fetch_assoc($result);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['employee_id'] = $user['employee_id'];
        
        if ($user['role'] == 'admin') {
            header('Location: admin/dashboard.php');
        } else {
            header('Location: employee/dashboard.php');
        }
        exit();
    }
}

if (isset($_POST['login'])) {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = mysqli_real_escape_string($conn, $_POST['password']);
    $remember = isset($_POST['remember']) ? true : false;

    $query = "SELECT * FROM employees WHERE email = '$email' AND password = '$password' AND status = 'active'";
    $result = mysqli_query($conn, $query);
    
    if (mysqli_num_rows($result) == 1) {
        $user = mysqli_fetch_assoc($result);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['employee_id'] = $user['employee_id'];
        
        // Set remember me cookie (30 days)
        if ($remember) {
            $token = bin2hex(random_bytes(32));
            mysqli_query($conn, "UPDATE employees SET remember_token = '$token' WHERE id = {$user['id']}");
            $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
            setcookie('remember_token', $token, time() + (86400 * 30), "/", "", $secure, true);
        }
        
        logAction('login', 'User logged in: ' . $user['name'] . ' (' . $user['email'] . ')', $user['id'], 'employee');
        if ($user['role'] == 'admin') {
            header('Location: admin/dashboard.php');
        } else {
            header('Location: employee/dashboard.php');
        }
        exit();
    } else {
        $error = "Invalid email or password!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IPINFRA Networks | HRM System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            font-family: 'Inter', sans-serif;
        }

        /* ── Layout ─────────────────────────────────────────────── */
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            background: #f1f5f9;
            overflow-x: hidden;
        }

        /* ── Left panel — branding ───────────────────────────────── */
        .brand-panel {
            flex: 1;
            min-height: 100vh;
            background: linear-gradient(145deg, #071e2e 0%, #0a2b3e 40%, #0f3b54 70%, #0a2b3e 100%);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 3rem 2.5rem;
            position: relative;
            overflow: hidden;
        }

        /* Animated geometric shapes */
        .brand-panel::before,
        .brand-panel::after {
            content: '';
            position: absolute;
            border-radius: 50%;
            pointer-events: none;
        }
        .brand-panel::before {
            width: 420px;
            height: 420px;
            top: -100px;
            right: -120px;
            border: 1.5px solid rgba(255,255,255,0.06);
            animation: rotateRing 28s linear infinite;
        }
        .brand-panel::after {
            width: 260px;
            height: 260px;
            bottom: -60px;
            left: -80px;
            border: 1.5px solid rgba(255,255,255,0.08);
            animation: rotateRing 18s linear infinite reverse;
        }

        @keyframes rotateRing {
            from { transform: rotate(0deg); }
            to   { transform: rotate(360deg); }
        }

        /* Extra decorative shapes injected via CSS */
        .geo-shape {
            position: absolute;
            pointer-events: none;
            opacity: 0.07;
        }
        .geo-shape-1 {
            width: 0; height: 0;
            border-left: 55px solid transparent;
            border-right: 55px solid transparent;
            border-bottom: 95px solid #ffffff;
            top: 18%;
            left: 10%;
            animation: floatUp 9s ease-in-out infinite;
        }
        .geo-shape-2 {
            width: 70px; height: 70px;
            background: rgba(255,255,255,1);
            transform: rotate(45deg);
            bottom: 22%;
            right: 8%;
            animation: floatUp 12s ease-in-out infinite 2s;
        }
        .geo-shape-3 {
            width: 0; height: 0;
            border-left: 35px solid transparent;
            border-right: 35px solid transparent;
            border-bottom: 60px solid #ffffff;
            top: 62%;
            left: 6%;
            animation: floatUp 7s ease-in-out infinite 1s;
        }
        .geo-shape-4 {
            width: 50px; height: 50px;
            background: rgba(255,255,255,1);
            transform: rotate(45deg);
            top: 12%;
            right: 18%;
            animation: floatUp 10s ease-in-out infinite 3s;
        }

        @keyframes floatUp {
            0%,100% { transform: translateY(0)   rotate(var(--rot,0deg)); }
            50%      { transform: translateY(-14px) rotate(var(--rot,0deg)); }
        }
        .geo-shape-2 { --rot: 45deg; }
        .geo-shape-4 { --rot: 45deg; }

        .brand-content {
            position: relative;
            z-index: 1;
            text-align: center;
            max-width: 380px;
        }

        .brand-logo-wrap {
            width: 96px;
            height: 96px;
            background: rgba(255,255,255,0.1);
            border-radius: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            backdrop-filter: blur(4px);
            box-shadow: 0 8px 32px rgba(0,0,0,0.25);
        }

        .brand-logo-inner {
            width: 68px;
            height: 68px;
            background: #ffffff;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .brand-logo-inner span {
            color: #0a2b3e;
            font-size: 1.75rem;
            font-weight: 900;
            letter-spacing: -0.05em;
        }

        .brand-name {
            color: #ffffff;
            font-size: 1.6rem;
            font-weight: 800;
            letter-spacing: -0.03em;
            margin-bottom: 0.25rem;
        }

        .brand-sub {
            color: rgba(147,197,253,0.9);
            font-size: 0.8rem;
            font-weight: 500;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            margin-bottom: 2rem;
        }

        .brand-divider {
            width: 48px;
            height: 3px;
            background: linear-gradient(90deg, #3b82f6, #60a5fa);
            border-radius: 99px;
            margin: 0 auto 1.75rem;
        }

        .brand-tagline {
            color: rgba(255,255,255,0.75);
            font-size: 1.05rem;
            font-weight: 400;
            line-height: 1.6;
            margin-bottom: 2.5rem;
        }

        .brand-tagline strong {
            color: #ffffff;
            font-weight: 600;
        }

        .brand-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 0.6rem;
            justify-content: center;
        }

        .brand-badge {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.15);
            color: rgba(255,255,255,0.8);
            font-size: 0.72rem;
            font-weight: 500;
            padding: 0.35rem 0.8rem;
            border-radius: 99px;
            backdrop-filter: blur(2px);
        }

        .brand-footer {
            position: absolute;
            bottom: 1.5rem;
            left: 0; right: 0;
            text-align: center;
            color: rgba(255,255,255,0.3);
            font-size: 0.7rem;
        }

        /* ── Right panel — form ──────────────────────────────────── */
        .form-panel {
            width: 480px;
            min-width: 320px;
            min-height: 100vh;
            background: #ffffff;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 3rem 3rem 2rem;
            position: relative;
            box-shadow: -8px 0 48px rgba(0,0,0,0.08);
        }

        .form-panel-inner {
            max-width: 380px;
            margin: 0 auto;
            width: 100%;
        }

        .form-heading {
            font-size: 1.5rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 0.35rem;
        }

        .form-subheading {
            font-size: 0.875rem;
            color: #64748b;
            margin-bottom: 2rem;
        }

        .form-panel-footer {
            position: absolute;
            bottom: 1.25rem;
            left: 0; right: 0;
            text-align: center;
            font-size: 0.7rem;
            color: #94a3b8;
        }

        /* ── Inputs ──────────────────────────────────────────────── */
        .input-field {
            width: 100%;
            padding: 0.78rem 1rem 0.78rem 2.75rem;
            border: 1.5px solid #e2e8f0;
            border-radius: 12px;
            font-size: 0.875rem;
            color: #0f172a;
            background: #f8fafc;
            transition: border-color 0.18s, box-shadow 0.18s, background 0.18s;
            outline: none;
            box-sizing: border-box;
        }
        .input-field:focus {
            border-color: #3b82f6;
            background: #ffffff;
            box-shadow: 0 0 0 4px rgba(59,130,246,0.12);
        }
        .input-wrapper {
            position: relative;
        }
        .input-icon {
            position: absolute;
            left: 0.9rem;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 0.85rem;
            pointer-events: none;
        }
        .input-action {
            position: absolute;
            right: 0.9rem;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 0.85rem;
            cursor: pointer;
            transition: color 0.2s;
        }
        .input-action:hover { color: #3b82f6; }

        /* ── Submit button ───────────────────────────────────────── */
        .btn-login {
            width: 100%;
            padding: 0.85rem 1rem;
            background: linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%);
            color: #ffffff;
            font-size: 0.9rem;
            font-weight: 600;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            transition: filter 0.2s, box-shadow 0.2s, transform 0.2s;
            box-shadow: 0 4px 14px rgba(29,78,216,0.35);
        }
        .btn-login:hover {
            filter: brightness(1.08);
            box-shadow: 0 6px 20px rgba(29,78,216,0.45);
        }

        /* ── Checkbox ────────────────────────────────────────────── */
        .checkbox-custom {
            width: 18px;
            height: 18px;
            border-radius: 5px;
            border: 2px solid #cbd5e1;
            transition: all 0.2s;
            cursor: pointer;
            accent-color: #2563eb;
        }

        /* ── Error alert ─────────────────────────────────────────── */
        .login-error {
            background: #fef2f2;
            border: 1.5px solid #fecaca;
            color: #b91c1c;
            padding: 0.75rem 1rem;
            border-radius: 10px;
            font-size: 0.84rem;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* ── Responsive: mobile stacked layout ───────────────────── */
        @media (max-width: 768px) {
            body {
                flex-direction: column;
            }
            .brand-panel {
                min-height: auto;
                padding: 2.5rem 1.5rem;
            }
            .brand-tagline { display: none; }
            .brand-badges  { display: none; }
            .brand-footer  { position: static; margin-top: 1.25rem; }
            .form-panel {
                width: 100%;
                min-width: 0;
                min-height: auto;
                padding: 2rem 1.5rem 3.5rem;
                box-shadow: none;
            }
            .form-panel-footer { position: fixed; }
        }

        @keyframes slideInRight {
            from { opacity: 0; transform: translateX(24px); }
            to   { opacity: 1; transform: translateX(0); }
        }
        .form-panel {
            animation: slideInRight 0.45s cubic-bezier(0.22,1,0.36,1) both;
        }
        @keyframes slideInLeft {
            from { opacity: 0; transform: translateX(-24px); }
            to   { opacity: 1; transform: translateX(0); }
        }
        .brand-panel .brand-content {
            animation: slideInLeft 0.45s cubic-bezier(0.22,1,0.36,1) both;
        }

        .toggle-password {
            cursor: pointer;
            transition: color 0.2s;
        }
        .toggle-password:hover { color: #3b82f6; }
    </style>
</head>
<body>

    <!-- ═══════════════ LEFT — Branding panel ═══════════════ -->
    <div class="brand-panel">
        <!-- Decorative geometric shapes -->
        <div class="geo-shape geo-shape-1"></div>
        <div class="geo-shape geo-shape-2"></div>
        <div class="geo-shape geo-shape-3"></div>
        <div class="geo-shape geo-shape-4"></div>

        <div class="brand-content">
            <!-- Logo -->
            <div class="brand-logo-wrap">
                <div class="brand-logo-inner">
                    <img src="uploads/1775551018_4xzREYTcMvK7ReGODviudjeDBIofOQ78mr5DsN9g.jpg" alt="IPINFRA" style="width:28px;height:28px;object-fit:contain;border-radius:4px;background:#fff;">
                </div>
            </div>

            <!-- Company name -->
            <div class="brand-name">IPINFRA NETWORKS</div>
            <div class="brand-sub">SDN BHD</div>
            <div class="brand-divider"></div>

            <!-- Tagline -->
            <p class="brand-tagline">
                Your <strong>Human Resource</strong> partner —<br>
                streamlining attendance, leave &amp; claims<br>
                for a connected workforce.
            </p>

            <!-- Feature badges -->
            <div class="brand-badges">
                <span class="brand-badge"><i class="fas fa-clock" style="margin-right:5px;opacity:.7"></i>Attendance</span>
                <span class="brand-badge"><i class="fas fa-calendar-check" style="margin-right:5px;opacity:.7"></i>Leave</span>
                <span class="brand-badge"><i class="fas fa-receipt" style="margin-right:5px;opacity:.7"></i>Claims</span>
                <span class="brand-badge"><i class="fas fa-users" style="margin-right:5px;opacity:.7"></i>People</span>
            </div>
        </div>

        <!-- Brand panel footer -->
        <div class="brand-footer">
            <i class="fas fa-phone-alt" style="margin-right:4px"></i>+603-8750 5161 &nbsp;|&nbsp;
            <i class="fas fa-phone" style="margin-right:4px"></i>1700-82-7530 &nbsp;|&nbsp;
            <i class="fas fa-envelope" style="margin-right:4px"></i>sales@ipinfra.com.my
        </div>
    </div>

    <!-- ═══════════════ RIGHT — Form panel ═══════════════════ -->
    <div class="form-panel">
        <div class="form-panel-inner">

            <!-- Heading -->
            <div style="margin-bottom:2rem;">
                <h1 class="form-heading">Welcome to IPINFRA Networks Sdn Bhd</h1>
                <p class="form-subheading">HR Management System — Sign in to your account to continue.</p>
            </div>

            <!-- Error message -->
            <?php if (isset($error)): ?>
                <div class="login-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <!-- Login form -->
            <form method="POST" action="">

                <!-- Email -->
                <div style="margin-bottom:1.1rem;">
                    <label style="display:block;font-size:.82rem;font-weight:600;color:#374151;margin-bottom:.45rem;">
                        Email Address
                    </label>
                    <div class="input-wrapper">
                        <i class="fas fa-envelope input-icon"></i>
                        <input type="email" name="email" required
                               class="input-field"
                               placeholder="you@ipinfra.com.my">
                    </div>
                </div>

                <!-- Password -->
                <div style="margin-bottom:1.1rem;">
                    <label style="display:block;font-size:.82rem;font-weight:600;color:#374151;margin-bottom:.45rem;">
                        Password
                    </label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" name="password" id="password" required
                               class="input-field"
                               style="padding-right:2.75rem;"
                               placeholder="Enter your password">
                        <i class="fas fa-eye-slash input-action toggle-password" id="togglePassword"></i>
                    </div>
                </div>

                <!-- Remember me + forgot password -->
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;">
                    <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;">
                        <input type="checkbox" name="remember" id="remember" class="checkbox-custom">
                        <span style="font-size:.84rem;color:#4b5563;user-select:none;">Remember me</span>
                    </label>
                    <a href="mailto:support@ipinfra.com.my?subject=Password%20Reset%20Request" style="font-size:.84rem;color:#2563eb;text-decoration:none;font-weight:500;">Forgot password?</a>
                </div>

                <!-- Submit -->
                <button type="submit" name="login" class="btn-login">
                    <i class="fas fa-arrow-right-to-bracket"></i>
                    Sign In to Dashboard
                </button>

            </form>

            <!-- Help line -->
            <div style="text-align:center;margin-top:1.5rem;">
                <p style="font-size:.78rem;color:#94a3b8;">
                    <i class="fas fa-headset" style="color:#3b82f6;margin-right:4px;"></i>
                    Need help?
                    <a href="mailto:support@ipinfra.com.my" style="color:#2563eb;font-weight:500;">support@ipinfra.com.my</a>
                </p>
            </div>
        </div>

        <!-- Powered-by footer -->
        <div class="form-panel-footer">
            Powered by IPINFRA Networks &nbsp;&mdash;&nbsp; &copy; 2026 All rights reserved.
        </div>
    </div>

    <script>
        // Toggle password visibility
        const togglePassword = document.getElementById('togglePassword');
        const password = document.getElementById('password');

        togglePassword.addEventListener('click', function() {
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });
    </script>
</body>
</html>