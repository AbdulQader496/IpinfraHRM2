<?php
session_start();
require_once 'includes/db.php';

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
    $password = $_POST['password'];
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
            setcookie('remember_token', $token, time() + (86400 * 30), "/", "", false, true);
        }
        
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
        body {
            background: linear-gradient(135deg, #0a2b3e 0%, #0f3b54 50%, #0a2b3e 100%);
            position: relative;
            overflow-x: hidden;
        }
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" opacity="0.05"><path fill="white" d="M20,20 L30,20 L25,30 Z M50,50 L60,50 L55,60 Z M80,30 L90,30 L85,40 Z"/></svg>') repeat;
            pointer-events: none;
        }
        .login-container {
            backdrop-filter: blur(2px);
        }
        .glow-effect {
            box-shadow: 0 20px 40px -15px rgba(0,0,0,0.3), 0 0 0 1px rgba(255,255,255,0.1);
        }
        .input-glow:focus {
            box-shadow: 0 0 0 4px rgba(59,130,246,0.2);
            border-color: #3b82f6;
        }
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .animate-fadeInUp {
            animation: fadeInUp 0.6s ease-out;
        }
        .toggle-password {
            cursor: pointer;
            transition: color 0.2s;
        }
        .toggle-password:hover {
            color: #3b82f6;
        }
        /* Custom Checkbox */
        .checkbox-custom {
            width: 18px;
            height: 18px;
            border-radius: 4px;
            border: 2px solid #cbd5e1;
            transition: all 0.2s;
            cursor: pointer;
        }
        .checkbox-custom:checked {
            background-color: #2563eb;
            border-color: #2563eb;
            position: relative;
        }
        .checkbox-custom:checked::after {
            content: '✓';
            color: white;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4 login-container">
    <div class="w-full max-w-md animate-fadeInUp">
        <!-- Main Card -->
        <div class="bg-white rounded-2xl shadow-2xl overflow-hidden glow-effect">
            
            <!-- Header with Gradient -->
            <div class="bg-gradient-to-r from-blue-900 to-blue-800 px-8 pt-10 pb-8 text-center">
                <div class="flex justify-center mb-4">
                    <div class="w-20 h-20 bg-white/10 rounded-2xl flex items-center justify-center backdrop-blur-sm">
                        <div class="w-14 h-14 bg-white rounded-xl flex items-center justify-center shadow-lg">
                            <span class="text-blue-900 font-black text-2xl tracking-tight">IN</span>
                        </div>
                    </div>
                </div>
                <h1 class="text-white text-2xl font-bold tracking-tight">IPINFRA NETWORKS</h1>
                <p class="text-blue-200 text-sm mt-1">SDN BHD</p>
                <div class="w-16 h-0.5 bg-blue-400 mx-auto mt-4 rounded-full"></div>
            </div>
            
            <!-- Contact Bar -->
            <div class="px-8 pt-6">
                <div class="flex flex-wrap justify-center gap-3 text-xs text-gray-500">
                    <span><i class="fas fa-phone-alt text-blue-600 mr-1"></i> +603-8750 5161</span>
                    <span class="text-gray-300">|</span>
                    <span><i class="fas fa-phone text-blue-600 mr-1"></i> 1700-82-7530</span>
                    <span class="text-gray-300">|</span>
                    <span><i class="fas fa-envelope text-blue-600 mr-1"></i> sales@ipinfra.com.my</span>
                </div>
            </div>
            
            <!-- Login Form -->
            <div class="px-8 pb-6 pt-6">
                <?php if (isset($error)): ?>
                    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-5 text-sm flex items-center gap-2">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-semibold mb-2">Email Address</label>
                        <div class="relative">
                            <i class="fas fa-envelope absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                            <input type="email" name="email" required 
                                class="w-full pl-11 pr-4 py-3 border border-gray-200 rounded-xl focus:outline-none focus:border-blue-500 input-glow transition text-sm"
                                placeholder="admin@ipinfra.com.my">
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-semibold mb-2">Password</label>
                        <div class="relative">
                            <i class="fas fa-lock absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                            <input type="password" name="password" id="password" required 
                                class="w-full pl-11 pr-12 py-3 border border-gray-200 rounded-xl focus:outline-none focus:border-blue-500 input-glow transition text-sm"
                                placeholder="Enter your password">
                            <i class="fas fa-eye-slash toggle-password absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 text-sm" id="togglePassword"></i>
                        </div>
                    </div>
                    
                    <!-- Remember Me Checkbox -->
                    <div class="flex items-center justify-between mb-6">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="remember" id="remember" class="checkbox-custom appearance-none">
                            <span class="text-sm text-gray-600 select-none">Remember Me</span>
                        </label>
                        <a href="#" class="text-sm text-blue-600 hover:text-blue-800 transition">Forgot Password?</a>
                    </div>
                    
                    <button type="submit" name="login" 
                        class="w-full bg-gradient-to-r from-blue-700 to-blue-800 hover:from-blue-800 hover:to-blue-900 text-white font-semibold py-3 rounded-xl transition duration-200 flex items-center justify-center gap-2 shadow-md hover:shadow-lg">
                        <i class="fas fa-arrow-right-to-bracket"></i> Sign In to Dashboard
                    </button>
                </form>
            </div>
            
            <!-- Help Line -->
            <div class="px-8 pb-4 text-center">
                <p class="text-xs text-gray-500">
                    <i class="fas fa-headset text-blue-600 mr-1"></i> Need assistance? 
                    <a href="mailto:support@ipinfra.com.my" class="text-blue-600 hover:underline font-medium">support@ipinfra.com.my</a>
                </p>
            </div>
            
            <!-- Footer -->
            <div class="bg-gray-50 px-8 py-4 border-t border-gray-100 text-center">
                <p class="text-xs text-gray-500">
                    © 2026 IPINFRA Networks SDN BHD. All rights reserved.
                </p>
            </div>
        </div>
        
        <!-- Demo Hint -->
        <div class="text-center mt-6 text-white/50 text-xs">
            <p>Demo: admin@ipinfra.com / password123 | employee@ipinfra.com / password123</p>
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

        // Custom checkbox styling
        const checkbox = document.getElementById('remember');
        checkbox.addEventListener('change', function() {
            if (this.checked) {
                this.classList.add('checked');
            } else {
                this.classList.remove('checked');
            }
        });
    </script>
</body>
</html>