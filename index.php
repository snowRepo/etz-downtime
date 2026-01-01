<?php
require_once 'config.php';
session_start();

// Get statistics and recent incidents
try {
    // Get distinct issue counts by service
    $total_query = $pdo->query("
        SELECT COUNT(DISTINCT CONCAT(service_id, '-', root_cause, '-', status)) 
        FROM issues_reported
    ");
    $total = $total_query->fetchColumn();
    
    // Resolved incidents (distinct by service and root cause)
    $resolved_query = $pdo->query("
        SELECT COUNT(DISTINCT CONCAT(service_id, '-', root_cause)) 
        FROM issues_reported 
        WHERE status = 'resolved'
    ");
    $resolved = $resolved_query->fetchColumn();
    
    // Pending incidents (distinct by service and root cause)
    $pending_query = $pdo->query("
        SELECT COUNT(DISTINCT CONCAT(service_id, '-', root_cause)) 
        FROM issues_reported 
        WHERE status = 'pending'
    ");
    $pending = $pending_query->fetchColumn();
    
    // Get services with reported issues, grouped by service and status
    $recent_incidents = $pdo->query("
        SELECT 
            s.service_name,
            s.service_id,
            i.status,
            GROUP_CONCAT(DISTINCT c.company_name ORDER BY c.company_name) as company_names,
            COUNT(DISTINCT i.issue_id) as incident_count,
            MAX(i.created_at) as date_reported,
            MAX(i.resolved_at) as date_resolved
        FROM issues_reported i
        JOIN services s ON i.service_id = s.service_id
        JOIN companies c ON i.company_id = c.company_id
        GROUP BY s.service_id, s.service_name, i.status
        ORDER BY date_reported DESC, s.service_name
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
    
    <!-- Tailwind CSS v3.4.17 -->
    <script src="https://cdn.tailwindcss.com?plugins=forms,typography,aspect-ratio"></script>
    <script>
        tailwind.config = {
            darkMode: 'class'
        }
    </script>
    
    <!-- Alpine.js v3.x -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    
    <!-- Font Awesome 6.5.1 -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        * {
            font-family: 'Inter', sans-serif;
        }
        
        .stat-card {
            transition: box-shadow 0.15s ease;
        }
        
        .stat-card:hover {
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.08), 0 2px 4px -1px rgba(0, 0, 0, 0.04);
        }
        
        .table-row-hover {
            transition: background-color 0.15s ease;
        }
        
        .table-row-hover:hover {
            background-color: #f9fafb;
        }
        
        .dark .table-row-hover:hover {
            background-color: #374151;
        }
    </style>
</head>
<body class="bg-gray-50 dark:bg-gray-900">
    <!-- Navbar -->
    <?php include 'includes/navbar.php'; ?>
    
    <!-- Loading Overlay -->
    <?php include 'includes/loading.php'; ?>

    <!-- Main Content -->
    <main class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Page Header -->
            <div class="mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Dashboard</h1>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Monitor and manage downtime incidents across all services</p>
                </div>
                <div class="mt-4 sm:mt-0 flex items-center space-x-3">
                    <span class="text-xs text-gray-500 dark:text-gray-400" id="last-updated">
                        Last updated: <?php echo date('g:i A'); ?>
                    </span>
                    <button onclick="refreshDashboard()" class="inline-flex items-center px-3 py-2 border border-gray-300 dark:border-gray-600 shadow-sm text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-150">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                        Refresh
                    </button>
                </div>
            </div>
            
            <!-- Stats Cards -->
            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3 mb-8">
                
                <!-- Total Incidents Card -->
                <div class="stat-card bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 overflow-hidden rounded-xl" style="box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05), 0 1px 2px 0 rgba(0, 0, 0, 0.03);">
                    <div class="p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600 dark:text-gray-400 uppercase tracking-wide">
                                    Total Incidents
                                </p>
                                <p class="mt-2 text-3xl font-semibold text-gray-900 dark:text-white">
                                    <?php echo $total; ?>
                                </p>
                            </div>
                            <div class="flex-shrink-0">
                                <div class="w-12 h-12 bg-blue-50 dark:bg-blue-900/30 rounded-lg flex items-center justify-center">
                                    <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                    </svg>
                                </div>
                            </div>
                        </div>
                        <div class="mt-4">
                            <p class="text-xs text-gray-500 dark:text-gray-400">All reported issues</p>
                        </div>
                    </div>
                </div>

                <!-- Resolved Incidents Card -->
                <div class="stat-card bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 overflow-hidden rounded-xl" style="box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05), 0 1px 2px 0 rgba(0, 0, 0, 0.03);">
                    <div class="p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600 dark:text-gray-400 uppercase tracking-wide">
                                    Resolved
                                </p>
                                <p class="mt-2 text-3xl font-semibold text-gray-900 dark:text-white">
                                    <?php echo $resolved; ?>
                                </p>
                            </div>
                            <div class="flex-shrink-0">
                                <div class="w-12 h-12 bg-green-50 dark:bg-green-900/30 rounded-lg flex items-center justify-center">
                                    <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                            </div>
                        </div>
                        <div class="mt-4">
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                <span class="text-green-600 dark:text-green-400 font-medium">
                                    <?php echo $total > 0 ? round(($resolved / $total) * 100, 1) : 0; ?>%
                                </span>
                                resolution rate
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Pending Incidents Card -->
                <div class="stat-card bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 overflow-hidden rounded-xl" style="box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05), 0 1px 2px 0 rgba(0, 0, 0, 0.03);">
                    <div class="p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600 dark:text-gray-400 uppercase tracking-wide">
                                    Pending
                                </p>
                                <p class="mt-2 text-3xl font-semibold text-gray-900 dark:text-white">
                                    <?php echo $pending; ?>
                                </p>
                            </div>
                            <div class="flex-shrink-0">
                                <div class="w-12 h-12 bg-yellow-50 dark:bg-yellow-900/30 rounded-lg flex items-center justify-center">
                                    <svg class="w-6 h-6 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                            </div>
                        </div>
                        <div class="mt-4">
                            <p class="text-xs text-gray-500 dark:text-gray-400">Awaiting resolution</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Incidents Table -->
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 overflow-hidden rounded-xl" style="box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05), 0 1px 2px 0 rgba(0, 0, 0, 0.03);">
                <div class="px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                        Recent Incidents
                    </h3>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        Latest downtime incidents across all services
                    </p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900/50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-600 dark:text-gray-400 uppercase tracking-wider">
                                    Service
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-600 dark:text-gray-400 uppercase tracking-wider">
                                    Affected Companies
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-600 dark:text-gray-400 uppercase tracking-wider">
                                    Date Reported
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-600 dark:text-gray-400 uppercase tracking-wider">
                                    Date Resolved
                                </th>
                                <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-600 dark:text-gray-400 uppercase tracking-wider">
                                    Status
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            <?php if (empty($recent_incidents)): ?>
                                <tr>
                                    <td colspan="5" class="px-6 py-12 text-center">
                                        <div class="flex flex-col items-center justify-center">
                                            <i class="fas fa-inbox text-gray-300 text-5xl mb-4"></i>
                                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">No incidents found</p>
                                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">All systems operational</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recent_incidents as $incident): 
                                    // Get the status for this row
                                    $mainStatus = $incident['status'];
                                    
                                    $statusClass = [
                                        'pending' => 'bg-yellow-100 text-yellow-800',
                                        'resolved' => 'bg-green-100 text-green-800',
                                        'investigating' => 'bg-blue-100 text-blue-800'
                                    ][$mainStatus] ?? 'bg-gray-100 text-gray-800';
                                ?>
                                    <tr class="table-row-hover">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="flex-shrink-0 h-9 w-9 bg-blue-50 rounded-lg flex items-center justify-center">
                                                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"></path>
                                                    </svg>
                                                </div>
                                                <div class="ml-3">
                                                    <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                        <?php echo htmlspecialchars($incident['service_name']); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <?php 
                                                $companies = !empty($incident['company_names']) ? explode(',', $incident['company_names']) : [];
                                                $total_companies = count($companies);
                                                $display_limit = 3;
                                                
                                                if ($total_companies > 0) {
                                                    echo '<div class="flex flex-wrap gap-1.5">';
                                                    
                                                    // Display up to the limit or total, whichever is smaller
                                                    $display_count = min($display_limit, $total_companies);
                                                    for ($i = 0; $i < $display_count; $i++) {
                                                        echo '<span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-medium bg-blue-50 text-blue-700 border border-blue-200">' . 
                                                             htmlspecialchars(trim($companies[$i])) . 
                                                             '</span>';
                                                    }
                                                    
                                                    // Add ellipsis if there are more companies
                                                    if ($total_companies > $display_limit) {
                                                        $remaining = $total_companies - $display_limit;
                                                        echo '<span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-medium bg-gray-100 text-gray-600" title="' . 
                                                             htmlspecialchars('And ' . $remaining . ' more...') . '">
                                                                +' . $remaining . ' more
                                                              </span>';
                                                    }
                                                    
                                                    echo '</div>';
                                                } else {
                                                    echo '<span class="text-sm text-gray-400 italic">No companies affected</span>';
                                                }
                                            ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900 dark:text-white font-medium">
                                                <?php echo !empty($incident['date_reported']) ? date('M j, Y', strtotime($incident['date_reported'])) : 'N/A'; ?>
                                            </div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                                <?php echo !empty($incident['date_reported']) ? date('g:i A', strtotime($incident['date_reported'])) : ''; ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php if (!empty($incident['date_resolved'])): ?>
                                                <div class="text-sm text-gray-900 dark:text-white font-medium">
                                                    <?php echo date('M j, Y', strtotime($incident['date_resolved'])); ?>
                                                </div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                                    <?php echo date('g:i A', strtotime($incident['date_resolved'])); ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-sm text-gray-400 dark:text-gray-500 italic">Not resolved</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center">
                                            <?php 
                                                // Use the status field from the query
                                                $currentStatus = $incident['status'];
                                                
                                                $statusClass = [
                                                    'pending' => 'bg-yellow-100 text-yellow-800 border-yellow-200',
                                                    'resolved' => 'bg-green-100 text-green-800 border-green-200',
                                                    'investigating' => 'bg-blue-100 text-blue-800 border-blue-200'
                                                ][$currentStatus] ?? 'bg-gray-100 text-gray-800 border-gray-200';
                                                
                                                $statusIcon = [
                                                    'pending' => 'fa-clock',
                                                    'resolved' => 'fa-check-circle',
                                                    'investigating' => 'fa-search'
                                                ][$currentStatus] ?? 'fa-question-circle';
                                                
                                                echo '<span class="px-3 py-1.5 inline-flex items-center text-xs leading-5 font-semibold rounded-full border ' . $statusClass . '">' . 
                                                     '<i class="fas ' . $statusIcon . ' mr-1.5"></i>' .
                                                     ucfirst($currentStatus) . 
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
    </main>
    
    <script>
        // Refresh dashboard function
        function refreshDashboard() {
            const btn = event.target.closest('button');
            const originalContent = btn.innerHTML;
            
            // Show loading state
            btn.disabled = true;
            btn.innerHTML = '<div class="btn-spinner"></div> Refreshing...';
            
            // Reload the page
            setTimeout(() => {
                location.reload();
            }, 300);
        }
        
        // Update last updated time
        function updateLastUpdatedTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
            const lastUpdated = document.getElementById('last-updated');
            if (lastUpdated) {
                lastUpdated.textContent = 'Last updated: ' + timeString;
            }
        }
    </script>
</body>
</html>