<?php
require_once 'config.php';
require_once 'auth.php';
session_start();

// Require authentication for all pages
Auth::requireLogin();

// Get statistics and recent incidents
try {
    // Get total incident count
    $total_query = $pdo->query("
        SELECT COUNT(*) as total_incidents FROM incidents
    ");
    $total = $total_query->fetchColumn();
    
    // Resolved incidents
    $resolved_query = $pdo->query("
        SELECT COUNT(*) as resolved_incidents 
        FROM incidents 
        WHERE status = 'resolved'
    ");
    $resolved = $resolved_query->fetchColumn();
    
    // Pending incidents
    $pending_query = $pdo->query("
        SELECT COUNT(*) as pending_incidents 
        FROM incidents 
        WHERE status = 'pending'
    ");
    $pending = $pending_query->fetchColumn();
    
    // Get recent incidents with affected companies
    $recent_incidents = $pdo->query("
        SELECT 
            i.incident_id,
            s.service_name,
            s.service_id,
            GROUP_CONCAT(DISTINCT c.company_name ORDER BY c.company_name) as company_names,
            COUNT(DISTINCT iac.company_id) as company_count,
            i.created_at as date_reported,
            i.resolved_at as date_resolved,
            i.status,
            i.impact_level,
            i.root_cause
        FROM incidents i
        JOIN services s ON i.service_id = s.service_id
        JOIN incident_affected_companies iac ON i.incident_id = iac.incident_id
        JOIN companies c ON iac.company_id = c.company_id
        GROUP BY i.incident_id, s.service_name, s.service_id, i.created_at, i.resolved_at, i.status, i.impact_level, i.root_cause
        ORDER BY i.created_at DESC
        LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    die("ERROR: Could not fetch data. " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>eTranzact - Downtime Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        .stat-card {
            transition: transform 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
    </style>
</head>
<body class="bg-gray-100">
    <!-- Navbar -->
    <?php include 'includes/navbar.php'; ?>

    <!-- Main Content -->
    <main class="pt-4 pb-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Stats Cards -->
            <div class="grid grid-cols-1 gap-5 mt-6 sm:grid-cols-2 lg:grid-cols-3">
                <!-- Total Incidents Card -->
                <div class="stat-card bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-7">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-blue-500 rounded-md p-4">
                                <i class="fas fa-exclamation-triangle text-white text-2xl"></i>
                            </div>
                            <div class="ml-6 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">
                                        Total Incidents
                                    </dt>
                                    <dd class="flex items-baseline">
                                        <div class="text-3xl font-semibold text-gray-900">
                                            <?php echo $total; ?>
                                        </div>
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Resolved Incidents Card -->
                <div class="stat-card bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-7">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-green-500 rounded-md p-4">
                                <i class="fas fa-check-circle text-white text-2xl"></i>
                            </div>
                            <div class="ml-6 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">
                                        Resolved
                                    </dt>
                                    <dd class="flex items-baseline">
                                        <div class="text-3xl font-semibold text-gray-900">
                                            <?php echo $resolved; ?>
                                        </div>
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pending Incidents Card -->
                <div class="stat-card bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-7">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-yellow-500 rounded-md p-4">
                                <i class="fas fa-clock text-white text-2xl"></i>
                            </div>
                            <div class="ml-6 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">
                                        Pending
                                    </dt>
                                    <dd class="flex items-baseline">
                                        <div class="text-3xl font-semibold text-gray-900">
                                            <?php echo $pending; ?>
                                        </div>
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Incidents Table -->
            <div class="mt-8 bg-white shadow overflow-hidden sm:rounded-lg">
                <div class="px-4 py-5 sm:px-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">
                        Recent Incidents
                    </h3>
                    <p class="mt-1 text-sm text-gray-500">
                        A list of recent downtime incidents.
                    </p>
                </div>
                <div class="border-t border-gray-200">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Service</th>
                                    <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Affected Companies</th>
                                    <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Date Reported</th>
                                    <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Date Resolved</th>
                                    <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (empty($recent_incidents)): ?>
                                    <tr>
                                        <td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500">No incidents found</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recent_incidents as $incident): 
                                        $statusClass = [
                                            'pending' => 'bg-yellow-100 text-yellow-800',
                                            'resolved' => 'bg-green-100 text-green-800'
                                        ][$incident['status']] ?? 'bg-gray-100 text-gray-800';
                                    ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 text-center">
                                                <?php echo htmlspecialchars($incident['service_name']); ?>
                                            </td>
                                            <td class="px-6 py-4 text-sm text-gray-500 text-center">
                                                <?php 
                                                    $companies = !empty($incident['company_names']) ? explode(',', $incident['company_names']) : [];
                                                    $total_companies = count($companies);
                                                    $display_limit = 3;
                                                    
                                                    if ($total_companies > 0) {
                                                        echo '<div class="flex flex-wrap justify-center gap-1">';
                                                        
                                                        // Display up to the limit or total, whichever is smaller
                                                        $display_count = min($display_limit, $total_companies);
                                                        for ($i = 0; $i < $display_count; $i++) {
                                                            echo '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">' . 
                                                                 htmlspecialchars(trim($companies[$i])) . 
                                                                 '</span>';
                                                        }
                                                        
                                                        // Add ellipsis if there are more companies
                                                        if ($total_companies > $display_limit) {
                                                            $remaining = $total_companies - $display_limit;
                                                            echo '<span class="inline-flex items-center px-2 py-0.5 text-xs font-medium text-gray-500" title="' . 
                                                                 htmlspecialchars('And ' . $remaining . ' more...') . '">
                                                                    +' . $remaining . '...
                                                                  </span>';
                                                        }
                                                        
                                                        echo '</div>';
                                                    } else {
                                                        echo '<span class="text-gray-400">No companies affected</span>';
                                                    }
                                                ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">
                                                <?php echo !empty($incident['date_reported']) ? date('M j, Y g:i A', strtotime($incident['date_reported'])) : 'N/A'; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">
                                                <?php echo !empty($incident['date_resolved']) ? date('M j, Y g:i A', strtotime($incident['date_resolved'])) : 'Not Available'; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                                <?php 
                                                    $statusClass = [
                                                        'pending' => 'bg-yellow-100 text-yellow-800',
                                                        'resolved' => 'bg-green-100 text-green-800'
                                                    ][$incident['status']] ?? 'bg-gray-100 text-gray-800';
                                                    
                                                    echo '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full ' . $statusClass . '">' . 
                                                         ucfirst($incident['status']) . 
                                                         '</span>';
                                                ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Add any JavaScript functionality here if needed
    </script>
</body>
</html>

<?php
// Close connection
closeConnection();
?>