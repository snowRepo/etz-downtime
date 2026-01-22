<?php
require_once 'config.php';
require_once 'auth.php';
session_start();

// Redirect if already logged in
if (Auth::isLoggedIn()) {
    $redirect = $_SESSION['login_redirect'] ?? 'index.php';
    unset($_SESSION['login_redirect']);
    header("Location: $redirect");
    exit;
}

$error = '';
$success = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Please enter both username/email and password.';
    } else {
        $user = $auth->login($username, $password);

        if ($user) {
            // Set session variables
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['changed_password'] = $user['changed_password'];
            $_SESSION['last_activity'] = time();

            // Set success message
            $_SESSION['success'] = 'Welcome back, ' . htmlspecialchars($user['full_name']) . '!';

            // Redirect to change password if required
            if ($user['changed_password'] == 0) {
                $_SESSION['success'] = 'Please change your temporary password to continue.';
                header("Location: change_password.php");
                exit;
            }

            // Redirect to intended page or dashboard
            $redirect = $_SESSION['login_redirect'] ?? 'index.php';
            unset($_SESSION['login_redirect']);
            header("Location: $redirect");
            exit;
        } else {
            $error = 'Invalid username/email or password.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - eTranzact Downtime Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
</head>

<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <!-- Logo/Header -->
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-gray-800">eTranzact</h1>
            <p class="text-gray-600 mt-2">Downtime Incident Management</p>
        </div>

        <!-- Login Card -->
        <div class="bg-white rounded-xl shadow-lg overflow-hidden">
            <div class="p-8">
                <h2 class="text-2xl font-bold text-gray-800 text-center mb-6">Sign in to your account</h2>

                <!-- Error Message -->
                <?php if ($error): ?>
                    <div class="mb-6 bg-red-50 border-l-4 border-red-400 p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-exclamation-circle text-red-400"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-red-700"><?php echo htmlspecialchars($error); ?></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Success Message -->
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="mb-6 bg-green-50 border-l-4 border-green-400 p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-check-circle text-green-400"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-green-700"><?php echo htmlspecialchars($_SESSION['success']); ?></p>
                            </div>
                        </div>
                    </div>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>

                <!-- Login Form -->
                <form method="POST" class="space-y-6">
                    <div>
                        <label for="username" class="block text-sm font-medium text-gray-700 mb-1">
                            Username or Email
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-user text-gray-400"></i>
                            </div>
                            <input id="username" name="username" type="text" required
                                value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                                class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                placeholder="Enter your username or email" autocomplete="username">
                        </div>
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-1">
                            Password
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-lock text-gray-400"></i>
                            </div>
                            <input id="password" name="password" type="password" required
                                class="block w-full pl-10 pr-10 py-3 border border-gray-300 rounded-lg shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                placeholder="Enter your password" autocomplete="current-password">
                            <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                <button type="button" id="togglePassword"
                                    class="text-gray-400 hover:text-gray-600 focus:outline-none">
                                    <i class="fas fa-eye" id="eyeIcon"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <input id="remember-me" name="remember-me" type="checkbox"
                                class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                            <label for="remember-me" class="ml-2 block text-sm text-gray-700">
                                Remember me
                            </label>
                        </div>

                        <div class="text-sm">
                            <a href="#" class="font-medium text-blue-600 hover:text-blue-500">
                                Forgot password?
                            </a>
                        </div>
                    </div>

                    <div>
                        <button type="submit"
                            class="w-full flex justify-center py-3 px-4 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition duration-150 ease-in-out">
                            <i class="fas fa-sign-in-alt mr-2"></i>
                            Sign in
                        </button>
                    </div>
                </form>
            </div>

            <!-- Footer -->
            <div class="bg-gray-50 px-8 py-6 text-center">
                <p class="text-xs text-gray-500">
                    © <?php echo date('Y'); ?> eTranzact. All rights reserved.
                </p>
                <p class="text-xs text-gray-400 mt-1">
                    Secure incident management system
                </p>
            </div>
        </div>


    </div>

    <script>
        // Password visibility toggle
        document.getElementById('togglePassword').addEventListener('click', function () {
            const passwordInput = document.getElementById('password');
            const eyeIcon = document.getElementById('eyeIcon');

            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.classList.remove('fa-eye');
                eyeIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                eyeIcon.classList.remove('fa-eye-slash');
                eyeIcon.classList.add('fa-eye');
            }
        });

        // Focus first input on load
        document.addEventListener('DOMContentLoaded', function () {
            document.getElementById('username').focus();
        });

        // Form validation
        document.querySelector('form').addEventListener('submit', function (e) {
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;

            if (!username) {
                e.preventDefault();
                alert('Please enter your username or email');
                document.getElementById('username').focus();
                return;
            }

            if (!password) {
                e.preventDefault();
                alert('Please enter your password');
                document.getElementById('password').focus();
                return;
            }
        });
    </script>
</body>

</html>