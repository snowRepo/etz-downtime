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

// Pagination
$limit = 50;
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$offset = ($page - 1) * $limit;

try {
    // Get total count for pagination
    $countStmt = $pdo->query("SELECT COUNT(*) FROM activity_logs");
    $totalLogs = $countStmt->fetchColumn();
    $totalPages = ceil($totalLogs / $limit);

    // Fetch logs with user info
    $stmt = $pdo->prepare("
        SELECT l.*, u.full_name, u.username 
        FROM activity_logs l 
        LEFT JOIN users u ON l.user_id = u.user_id 
        ORDER BY l.created_at DESC 
        LIMIT ? OFFSET ?
    ");
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>eTranzact - Recent Activity</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
</head>

<body class="bg-gray-100">
    <?php include 'includes/navbar.php'; ?>

    <header class="bg-white shadow">
        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
            <h1 class="text-3xl font-bold text-gray-900">Recent Activity</h1>
        </div>
    </header>

    <main class="pt-4 pb-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Time</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                User</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Action</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Description</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                IP Address</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($logs as $log): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo date('M j, Y H:i:s', strtotime($log['created_at'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <div class="font-medium">
                                        <?php echo htmlspecialchars($log['full_name'] ?? 'System'); ?>
                                    </div>
                                    <div class="text-xs text-gray-400">@
                                        <?php echo htmlspecialchars($log['username'] ?? 'system'); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?php
                                        echo strpos($log['action'], 'create') !== false ? 'bg-green-100 text-green-800' :
                                            (strpos($log['action'], 'delete') !== false || strpos($log['action'], 'deactivate') !== false ? 'bg-red-100 text-red-800' :
                                                (strpos($log['action'], 'login') !== false ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800'));
                                        ?>">
                                        <?php echo htmlspecialchars($log['action']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    <?php echo htmlspecialchars($log['description']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-400">
                                    <?php echo htmlspecialchars($log['ip_address'] ?? 'N/A'); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="5" class="px-6 py-10 text-center text-gray-500 italic">No activity logs found.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="bg-gray-50 px-4 py-3 border-t border-gray-200 sm:px-6">
                        <div class="flex justify-between items-center">
                            <div class="text-sm text-gray-700">
                                Showing <span class="font-medium">
                                    <?php echo $offset + 1; ?>
                                </span> to <span class="font-medium">
                                    <?php echo min($offset + $limit, $totalLogs); ?>
                                </span> of <span class="font-medium">
                                    <?php echo $totalLogs; ?>
                                </span> logs
                            </div>
                            <div class="flex space-x-2">
                                <?php if ($page > 1): ?>
                                    <a href="?page=<?php echo $page - 1; ?>"
                                        class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">Previous</a>
                                <?php endif; ?>

                                <?php if ($page < $totalPages): ?>
                                    <a href="?page=<?php echo $page + 1; ?>"
                                        class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">Next</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</body>

</html>