<?php
require_once 'config.php';
require_once 'auth.php';
session_start();

// Require admin access
Auth::requireLogin();
if (!Auth::isAdmin()) {
    $_SESSION['error'] = 'Access denied. Administrator privileges required.';
    header('Location: index.php');
    exit;
}

$user = Auth::getUser();
$error = $_SESSION['error'] ?? '';
$success = $_SESSION['success'] ?? '';
unset($_SESSION['error'], $_SESSION['success']);

// Handle Status Toggle (Activate/Deactivate)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status'])) {
    $target_user_id = filter_var($_POST['user_id'], FILTER_VALIDATE_INT);
    $new_status = filter_var($_POST['new_status'], FILTER_VALIDATE_INT);

    if ($target_user_id && $target_user_id !== $user['user_id']) {
        try {
            $stmt = $pdo->prepare("UPDATE users SET is_active = ? WHERE user_id = ?");
            $stmt->execute([$new_status, $target_user_id]);

            $action = $new_status ? 'activated' : 'deactivated';
            $success = "User successfully $action.";

            // Log activity
            $auth = new Auth($pdo);
            $auth->logActivity($user['user_id'], "user.$action", "Admin $action user ID: $target_user_id");

            $_SESSION['success'] = $success;
            header("Location: users.php");
            exit;
        } catch (PDOException $e) {
            $error = "Error updating user status: " . $e->getMessage();
            $_SESSION['error'] = $error;
            header("Location: users.php");
            exit;
        }
    } else {
        $error = "Cannot deactivate yourself.";
        $_SESSION['error'] = $error;
        header("Location: users.php");
        exit;
    }
}

// Handle User Creation (from Modal)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = $_POST['role'] ?? 'user';

    if (empty($first_name) || empty($last_name) || empty($username) || empty($email)) {
        $error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } else {
        try {
            // Check if user exists
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            if ($stmt->fetchColumn() > 0) {
                $error = "Username or Email already exists.";
            } else {
                $default_password = 'Etz@1234567890';
                $full_name = $first_name . ' ' . $last_name;

                $stmt = $pdo->prepare("
                    INSERT INTO users (username, email, password_hash, full_name, role, is_active, changed_password) 
                    VALUES (?, ?, ?, ?, ?, 1, 0)
                ");
                $stmt->execute([
                    $username,
                    $email,
                    password_hash($default_password, PASSWORD_DEFAULT),
                    $full_name,
                    $role
                ]);

                $success = "User '$username' created successfully with default password.";

                // Log activity
                $auth = new Auth($pdo);
                $auth->logActivity($user['user_id'], 'user.create', "Admin created user: $username ($role)");

                $_SESSION['success'] = $success;
                header("Location: users.php");
                exit;
            }
        } catch (PDOException $e) {
            $error = "Error creating user: " . $e->getMessage();
            $_SESSION['error'] = $error;
            header("Location: users.php");
            exit;
        }
    }
    $_SESSION['error'] = $error;
    header("Location: users.php");
    exit;
}

// Handle User Update (from Edit Modal)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
    $target_user_id = filter_var($_POST['user_id'], FILTER_VALIDATE_INT);
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = $_POST['role'] ?? 'user';

    if (empty($first_name) || empty($last_name) || empty($email)) {
        $error = "Names and Email are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } else {
        try {
            // Check if email already in use by another user
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND user_id != ?");
            $stmt->execute([$email, $target_user_id]);
            if ($stmt->fetchColumn() > 0) {
                $error = "Email already in use by another user.";
            } else {
                $full_name = $first_name . ' ' . $last_name;
                $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, role = ? WHERE user_id = ?");
                $stmt->execute([$full_name, $email, $role, $target_user_id]);

                $success = "User details updated successfully.";

                // Log activity
                $auth = new Auth($pdo);
                $auth->logActivity($user['user_id'], 'user.edit', "Admin updated user ID: $target_user_id");

                $_SESSION['success'] = $success;
                header("Location: users.php");
                exit;
            }
        } catch (PDOException $e) {
            $error = "Error updating user: " . $e->getMessage();
            $_SESSION['error'] = $error;
            header("Location: users.php");
            exit;
        }
    }
    $_SESSION['error'] = $error;
    header("Location: users.php");
    exit;
}

// Handle Password Reset (from Edit Modal)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $target_user_id = filter_var($_POST['user_id'], FILTER_VALIDATE_INT);
    try {
        $default_password = 'Etz@1234567890';
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, changed_password = 0 WHERE user_id = ?");
        $stmt->execute([password_hash($default_password, PASSWORD_DEFAULT), $target_user_id]);

        $success = "Password reset successfully. User will be forced to change it on next login.";

        // Log activity
        $auth = new Auth($pdo);
        $auth->logActivity($user['user_id'], 'user.password_reset', "Admin reset password for user ID: $target_user_id");

        $_SESSION['success'] = $success;
        header("Location: users.php");
        exit;
    } catch (PDOException $e) {
        $error = "Error resetting password: " . $e->getMessage();
        $_SESSION['error'] = $error;
        header("Location: users.php");
        exit;
    }
}

// Fetch all users
try {
    $stmt = $pdo->query("SELECT user_id, full_name, email, role, is_active FROM users ORDER BY full_name ASC");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>eTranzact - User Management</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        .modal {
            transition: opacity 0.25s ease;
        }

        body.modal-active {
            overflow-x: hidden;
            overflow-y: visible !important;
        }
    </style>
</head>

<body class="bg-gray-100">
    <?php include 'includes/navbar.php'; ?>

    <header class="bg-white shadow">
        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8 flex justify-between items-center">
            <h1 class="text-3xl font-bold text-gray-900">User Management</h1>
            <button onclick="toggleModal()"
                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                <i class="fas fa-user-plus mr-2"></i> Add User
            </button>
        </div>
    </header>

    <main class="pt-4 pb-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <?php if ($error): ?>
                <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Full Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Email</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Role</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Status</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <?php echo htmlspecialchars($u['full_name']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo htmlspecialchars($u['email']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <span
                                        class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-<?php echo $u['role'] === 'admin' ? 'purple' : 'blue'; ?>-100 text-<?php echo $u['role'] === 'admin' ? 'purple' : 'blue'; ?>-800">
                                        <?php echo ucfirst($u['role']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php if ($u['is_active']): ?>
                                        <span
                                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Active</span>
                                    <?php else: ?>
                                        <span
                                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Deactivated</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                                    <button onclick='openEditModal(<?php echo json_encode($u); ?>)'
                                        class="text-blue-600 hover:text-blue-900">Edit</button>

                                    <?php if ($u['user_id'] !== $user['user_id']): ?>
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="user_id" value="<?php echo $u['user_id']; ?>">
                                            <input type="hidden" name="new_status"
                                                value="<?php echo $u['is_active'] ? '0' : '1'; ?>">
                                            <button type="submit" name="toggle_status"
                                                class="<?php echo $u['is_active'] ? 'text-red-600 hover:text-red-900' : 'text-green-600 hover:text-green-900'; ?>">
                                                <?php echo $u['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Modal -->
    <div id="addUserModal"
        class="modal opacity-0 pointer-events-none fixed w-full h-full top-0 left-0 flex items-center justify-center z-50">
        <div class="modal-overlay absolute w-full h-full bg-gray-900 opacity-50"></div>

        <div class="modal-container bg-white w-11/12 md:max-w-md mx-auto rounded shadow-lg z-50 overflow-y-auto">
            <div class="modal-content py-4 text-left px-6">
                <!-- Title -->
                <div class="flex justify-between items-center pb-3 border-b">
                    <p class="text-2xl font-bold text-gray-800">Add New User</p>
                    <div onclick="toggleModal()" class="modal-close cursor-pointer z-50">
                        <i class="fas fa-times text-gray-600"></i>
                    </div>
                </div>

                <!-- Form -->
                <form method="POST" class="mt-4 space-y-4">
                    <input type="hidden" name="add_user" value="1">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">First Name</label>
                            <input type="text" name="first_name" required
                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Last Name</label>
                            <input type="text" name="last_name" required
                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Username</label>
                        <input type="text" name="username" required
                            class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Email</label>
                        <input type="email" name="email" required
                            class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Role</label>
                        <select name="role"
                            class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            <option value="user">User</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>

                    <p class="text-xs text-gray-500 italic mt-2">Default password will be: Etz@1234567890</p>

                    <!-- Footer -->
                    <div class="flex justify-end pt-4 space-x-3">
                        <button type="button" onclick="toggleModal()"
                            class="px-4 bg-gray-200 p-2 rounded-lg text-gray-800 hover:bg-gray-300">Cancel</button>
                        <button type="submit"
                            class="px-4 bg-blue-600 p-2 rounded-lg text-white hover:bg-blue-700">Create User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div id="editUserModal"
        class="modal opacity-0 pointer-events-none fixed w-full h-full top-0 left-0 flex items-center justify-center z-50">
        <div class="modal-overlay absolute w-full h-full bg-gray-900 opacity-50" onclick="toggleEditModal()"></div>

        <div class="modal-container bg-white w-11/12 md:max-w-md mx-auto rounded shadow-lg z-50 overflow-y-auto">
            <div class="modal-content py-4 text-left px-6">
                <!-- Title -->
                <div class="flex justify-between items-center pb-3 border-b">
                    <p class="text-2xl font-bold text-gray-800">Edit User</p>
                    <div onclick="toggleEditModal()" class="modal-close cursor-pointer z-50">
                        <i class="fas fa-times text-gray-600"></i>
                    </div>
                </div>

                <!-- Form -->
                <form method="POST" class="mt-4 space-y-4">
                    <input type="hidden" name="update_user" value="1">
                    <input type="hidden" name="user_id" id="edit_user_id">

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">First Name</label>
                            <input type="text" name="first_name" id="edit_first_name" required
                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Last Name</label>
                            <input type="text" name="last_name" id="edit_last_name" required
                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Email</label>
                        <input type="email" name="email" id="edit_email" required
                            class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Role</label>
                        <select name="role" id="edit_role"
                            class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            <option value="user">User</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>

                    <!-- Footer -->
                    <div class="flex justify-between pt-4 border-t">
                        <button type="submit" name="reset_password"
                            onclick="return confirm('Are you sure you want to reset the password for this user?');"
                            class="px-4 py-2 border border-transparent text-sm font-medium rounded-md text-red-700 bg-red-100 hover:bg-red-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                            Reset Password
                        </button>
                        <div class="flex space-x-3">
                            <button type="button" onclick="toggleEditModal()"
                                class="px-4 bg-gray-200 p-2 rounded-lg text-gray-800 hover:bg-gray-300">Cancel</button>
                            <button type="submit"
                                class="px-4 bg-blue-600 p-2 rounded-lg text-white hover:bg-blue-700">Save
                                Changes</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function toggleModal() {
            const body = document.querySelector('body');
            const modal = document.querySelector('#addUserModal');
            modal.classList.toggle('opacity-0');
            modal.classList.toggle('pointer-events-none');
            body.classList.toggle('modal-active');
        }

        function toggleEditModal() {
            const body = document.querySelector('body');
            const modal = document.querySelector('#editUserModal');
            modal.classList.toggle('opacity-0');
            modal.classList.toggle('pointer-events-none');
            body.classList.toggle('modal-active');
        }

        function openEditModal(user) {
            document.getElementById('edit_user_id').value = user.user_id;

            // Split full name into first and last
            const names = user.full_name.split(' ');
            document.getElementById('edit_first_name').value = names[0] || '';
            document.getElementById('edit_last_name').value = names.slice(1).join(' ') || '';

            document.getElementById('edit_email').value = user.email;
            document.getElementById('edit_role').value = user.role;
            toggleEditModal();
        }
    </script>
</body>

</html>