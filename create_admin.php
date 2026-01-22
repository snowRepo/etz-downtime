<?php
/**
 * Admin User Creation Page - Simplified Version
 * Direct form for creating admin users (for testing purposes)
 */

session_start();
require_once 'config.php';
require_once 'auth.php';

$error = '';
$success = '';

// Check if users already exist
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    $userCount = $stmt->fetchColumn();
    
    if ($userCount > 0) {
        $error = 'Admin users already exist in the system.';
    }
} catch (PDOException $e) {
    $error = 'Database connection error: ' . $e->getMessage();
}

// Handle admin user creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_admin'])) {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validation
    if (empty($first_name) || empty($last_name) || empty($username) || empty($email) || empty($password)) {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        try {
            $auth = new Auth($pdo);
            
            // Check if username or email already exists
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
            $checkStmt->execute([$username, $email]);
            
            if ($checkStmt->fetchColumn() > 0) {
                $error = 'Username or email already exists.';
            } else {
                // Create admin user directly with changed_password = 1
                $full_name = $first_name . ' ' . $last_name;
                
                $stmt = $pdo->prepare("
                    INSERT INTO users (username, email, password_hash, full_name, role, is_active, changed_password) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                
                $result = $stmt->execute([
                    $username,
                    $email,
                    password_hash($password, PASSWORD_DEFAULT),
                    $full_name,
                    'admin',
                    1,
                    1  // Set changed_password to 1 for admins
                ]);
                
                $userId = $result ? $pdo->lastInsertId() : false;
                
                if ($userId) {
                    $success = "Admin user '$username' created successfully!";
                    // Log the creation
                    $auth->logActivity($userId, 'admin.create', "Admin user created: $full_name");
                    
                    // Clear form data
                    $_POST = [];
                } else {
                    $error = 'Failed to create admin user. Please try again.';
                }
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Admin User - eTranzact</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <!-- Header -->
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-gray-800">eTranzact</h1>
            <p class="text-gray-600 mt-2">Admin User Creation</p>
        </div>
        
        <?php if ($error && strpos($error, 'already exist') !== false): ?>
            <!-- Users Exist Message -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="p-8 text-center">
                    <div class="mx-auto bg-green-100 text-green-600 w-16 h-16 rounded-full flex items-center justify-center mb-6">
                        <i class="fas fa-check text-2xl"></i>
                    </div>
                    
                    <h2 class="text-xl font-bold text-gray-800 mb-4">Admin Users Already Exist</h2>
                    <p class="text-gray-600 mb-6">
                        <?php echo htmlspecialchars($error); ?>
                    </p>
                    
                    <a href="login.php" 
                       class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <i class="fas fa-sign-in-alt mr-2"></i>
                        Go to Login
                    </a>
                </div>
            </div>
        <?php else: ?>
            <!-- Admin User Creation Form -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="p-8">
                    <h2 class="text-xl font-bold text-gray-800 text-center mb-2">Create Admin User</h2>
                    <p class="text-gray-600 text-center mb-6">
                        Create your admin account for system access.
                    </p>
                    
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
                    
                    <?php if ($success): ?>
                        <div class="mb-6 bg-green-50 border-l-4 border-green-400 p-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-check-circle text-green-400"></i>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-green-700"><?php echo htmlspecialchars($success); ?></p>
                                    <p class="text-xs text-green-600 mt-1">You can create another admin user or <a href="login.php" class="underline">go to login</a>.</p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="create_admin" value="1">
                        
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="first_name" class="block text-sm font-medium text-gray-700 mb-1">
                                    First Name *
                                </label>
                                <input
                                    id="first_name"
                                    name="first_name"
                                    type="text"
                                    required
                                    value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>"
                                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                    placeholder="John"
                                >
                            </div>
                            
                            <div>
                                <label for="last_name" class="block text-sm font-medium text-gray-700 mb-1">
                                    Last Name *
                                </label>
                                <input
                                    id="last_name"
                                    name="last_name"
                                    type="text"
                                    required
                                    value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>"
                                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                    placeholder="Doe"
                                >
                            </div>
                        </div>
                        
                        <div>
                            <label for="username" class="block text-sm font-medium text-gray-700 mb-1">
                                Username *
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-user text-gray-400"></i>
                                </div>
                                <input
                                    id="username"
                                    name="username"
                                    type="text"
                                    required
                                    value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                                    class="block w-full pl-10 px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                    placeholder="johndoe"
                                >
                            </div>
                        </div>
                        
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 mb-1">
                                Email Address *
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-envelope text-gray-400"></i>
                                </div>
                                <input
                                    id="email"
                                    name="email"
                                    type="email"
                                    required
                                    value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                    class="block w-full pl-10 px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                    placeholder="john.doe@company.com"
                                >
                            </div>
                        </div>
                        
                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-700 mb-1">
                                Password *
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-lock text-gray-400"></i>
                                </div>
                                <input
                                    id="password"
                                    name="password"
                                    type="password"
                                    required
                                    minlength="8"
                                    class="block w-full pl-10 pr-10 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                    placeholder="Minimum 8 characters"
                                >
                                <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                    <button
                                        type="button"
                                        id="togglePassword"
                                        class="text-gray-400 hover:text-gray-600 focus:outline-none"
                                    >
                                        <i class="fas fa-eye" id="eyeIcon"></i>
                                    </button>
                                </div>
                            </div>
                            <p class="mt-1 text-xs text-gray-500">
                                Must be at least 8 characters long
                            </p>
                        </div>
                        
                        <div>
                            <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-1">
                                Confirm Password *
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-lock text-gray-400"></i>
                                </div>
                                <input
                                    id="confirm_password"
                                    name="confirm_password"
                                    type="password"
                                    required
                                    class="block w-full pl-10 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                    placeholder="Re-enter your password"
                                >
                            </div>
                        </div>
                        
                        <div class="pt-4">
                            <button
                                type="submit"
                                class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500"
                            >
                                <i class="fas fa-user-plus mr-2"></i>
                                Create Admin Account
                            </button>
                        </div>
                    </form>
                </div>
                
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Password visibility toggle
        document.addEventListener('DOMContentLoaded', function() {
            // Main password toggle
            const toggle = document.getElementById('togglePassword');
            const password = document.getElementById('password');
            const eyeIcon = document.getElementById('eyeIcon');
            
            if (toggle && password && eyeIcon) {
                toggle.addEventListener('click', function() {
                    if (password.type === 'password') {
                        password.type = 'text';
                        eyeIcon.classList.remove('fa-eye');
                        eyeIcon.classList.add('fa-eye-slash');
                    } else {
                        password.type = 'password';
                        eyeIcon.classList.remove('fa-eye-slash');
                        eyeIcon.classList.add('fa-eye');
                    }
                });
            }
            
            // Form validation
            const form = document.querySelector('form[method="POST"]');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const password = document.getElementById('password').value;
                    const confirmPassword = document.getElementById('confirm_password').value;
                    
                    if (password !== confirmPassword) {
                        e.preventDefault();
                        alert('Passwords do not match!');
                        return false;
                    }
                });
            }
        });
    </script>
</body>
</html>