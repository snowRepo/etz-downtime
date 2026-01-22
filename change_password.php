<?php
require_once 'config.php';
require_once 'auth.php';
session_start();

// Require authentication
Auth::requireLogin();

$user = Auth::getUser();
$error = $_SESSION['error'] ?? '';
$success = $_SESSION['success'] ?? '';
unset($_SESSION['error'], $_SESSION['success']);

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Invalid request");
    }

    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validation
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = "All fields are required.";
    } elseif ($new_password !== $confirm_password) {
        $error = "New passwords do not match.";
    } elseif (strlen($new_password) < 8) {
        $error = "New password must be at least 8 characters long.";
    } else {
        try {
            // Check current password
            $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE user_id = ?");
            $stmt->execute([$user['user_id']]);
            $userData = $stmt->fetch();

            if ($userData && password_verify($current_password, $userData['password_hash'])) {
                // Update password
                $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $updateStmt = $pdo->prepare("
                    UPDATE users 
                    SET password_hash = ?, changed_password = 1 
                    WHERE user_id = ?
                ");
                $updateStmt->execute([$new_hash, $user['user_id']]);

                // Update session
                $_SESSION['changed_password'] = 1;

                // Log activity
                $auth = new Auth($pdo);
                $auth->logActivity($user['user_id'], 'user.password_change', 'User changed their password');

                $_SESSION['success'] = "Password changed successfully!";
                header("Location: change_password.php");
                exit;
            } else {
                $error = "Current password is incorrect.";
            }
        } catch (PDOException $e) {
            error_log("Password change error: " . $e->getMessage());
            $error = "An error occurred. Please try again later.";
        }
    }

    $_SESSION['error'] = $error;
    header("Location: change_password.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>eTranzact - Change Password</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
        }
    </style>
</head>

<body class="bg-gray-100">
    <?php include 'includes/navbar.php'; ?>

    <header class="bg-white shadow">
        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
            <h1 class="text-3xl font-bold text-gray-900">Change Password</h1>
        </div>
    </header>

    <main class="pt-4 pb-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="mt-8 max-w-md mx-auto">
                <?php if ($error): ?>
                    <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                        <span class="block sm:inline">
                            <?php echo htmlspecialchars($error); ?>
                        </span>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative"
                        role="alert">
                        <span class="block sm:inline">
                            <?php echo htmlspecialchars($success); ?>
                        </span>
                    </div>
                <?php endif; ?>

                <div class="bg-white shadow px-4 py-5 sm:rounded-lg sm:p-6">
                    <form action="change_password.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                        <div class="space-y-6">
                            <div>
                                <label for="current_password" class="block text-sm font-medium text-gray-700">Current
                                    Password</label>
                                <div class="mt-1">
                                    <input type="password" name="current_password" id="current_password" required
                                        class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                </div>
                            </div>

                            <div>
                                <label for="new_password" class="block text-sm font-medium text-gray-700">New
                                    Password</label>
                                <div class="mt-1">
                                    <input type="password" name="new_password" id="new_password" required
                                        class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                </div>
                                <p class="mt-2 text-xs text-gray-500 italic">Must be at least 8 characters long.</p>
                            </div>

                            <div>
                                <label for="confirm_password" class="block text-sm font-medium text-gray-700">Confirm
                                    New Password</label>
                                <div class="mt-1">
                                    <input type="password" name="confirm_password" id="confirm_password" required
                                        class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                </div>
                            </div>

                            <div class="flex items-center justify-end pt-4">
                                <button type="submit"
                                    class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    Update Password
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>
</body>

</html>