<?php
require_once 'config.php';
session_start();

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_update' && !empty($_POST['update_text']) && !empty($_POST['user_name'])) {
        // Add new update
        $stmt = $pdo->prepare("
            INSERT INTO incident_updates (issue_id, user_name, update_text) 
            VALUES (:issue_id, :user_name, :update_text)
        ");
        $stmt->execute([
            ':issue_id' => $_POST['issue_id'],
            ':user_name' => trim($_POST['user_name']),
            ':update_text' => trim($_POST['update_text'])
        ]);
    } 
    elseif ($_POST['action'] === 'update_status' && isset($_POST['issue_id'], $_POST['status'], $_POST['user_name'])) {
        // First get the service_id for this issue
        $stmt = $pdo->prepare("SELECT service_id FROM issues_reported WHERE issue_id = ?");
        $stmt->execute([$_POST['issue_id']]);
        $service = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($service) {
            // Update all issues with the same service_id
            $updateData = [
                ':status' => $_POST['status'],
                ':service_id' => $service['service_id'],
                ':user_name' => trim($_POST['user_name'])
            ];
            
            // Prepare the SQL based on status
            $sql = "UPDATE issues_reported 
                    SET status = :status, 
                        updated_at = NOW()";
            
            // Add resolved_by and resolved_at for resolved status
            if ($_POST['status'] === 'resolved') {
                $sql .= ", resolved_by = :user_name, resolved_at = NOW()";
            } else {
                $sql .= ", resolved_by = NULL, resolved_at = NULL";
            }
            
            $sql .= " WHERE service_id = :service_id";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($updateData);
            
            // Get all affected issue IDs for the update log
            $stmt = $pdo->prepare("SELECT issue_id FROM issues_reported WHERE service_id = ?");
            $stmt->execute([$service['service_id']]);
            $affectedIssues = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Add system update to each affected issue
            $statusText = $_POST['status'] === 'resolved' ? 'resolved' : 'reopened';
            $updateText = "All issues for this service have been marked as {$statusText} by " . trim($_POST['user_name']);
            
            // Set success message
            $_SESSION['success'] = "Incident(s) updated successfully!";
            
            $stmt = $pdo->prepare("
                INSERT INTO incident_updates (issue_id, user_name, update_text) 
                VALUES (:issue_id, :user_name, :update_text)
            
            ");
            
            foreach ($affectedIssues as $issueId) {
                $stmt->execute([
                    ':issue_id' => $issueId,
                    ':user_name' => 'System',
                    ':update_text' => $updateText
                ]);
            }
        }
    }
    
    // Redirect to prevent form resubmission
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Get all incidents with their updates
try {
    // First, get distinct service issues with affected companies
    $incidents = $pdo->query("
        SELECT 
            MIN(i.issue_id) as issue_id,
            i.service_id,
            i.root_cause,
            i.status,
            i.impact_level,
            i.user_name,
            i.created_at,
            MAX(i.resolved_by) as resolved_by,
            MAX(i.resolved_at) as resolved_at,
            MAX(i.updated_at) as updated_at,
            s.service_name,
            GROUP_CONCAT(DISTINCT c.company_name ORDER BY c.company_name SEPARATOR ', ') as affected_companies,
            COUNT(DISTINCT c.company_id) as company_count,
            (SELECT COUNT(*) FROM incident_updates iu WHERE iu.issue_id = MIN(i.issue_id)) as update_count
        FROM issues_reported i
        JOIN services s ON i.service_id = s.service_id
        JOIN companies c ON i.company_id = c.company_id
        GROUP BY i.service_id, i.root_cause, i.status, i.impact_level, i.user_name, i.created_at, s.service_name
        ORDER BY 
            FIELD(i.status, 'pending', 'resolved'),
            MAX(i.updated_at) DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Get updates for each incident
    foreach ($incidents as &$incident) {
        $stmt = $pdo->prepare("
            SELECT * FROM incident_updates 
            WHERE issue_id = ? 
            ORDER BY created_at DESC
        ");
        $stmt->execute([$incident['issue_id']]);
        $incident['updates'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    unset($incident); // Break the reference
    
} catch(PDOException $e) {
    die("ERROR: Could not fetch incidents. " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Incidents - ETZ Downtime</title>
    
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        * {
            font-family: 'Inter', sans-serif;
        }
        
        .line-clamp-3 {
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .incident-card {
            transition: box-shadow 0.15s ease;
        }
        
        .incident-card:hover {
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.08), 0 2px 4px -1px rgba(0, 0, 0, 0.04);
        }
        
        .status-badge {
            @apply inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium border;
        }
        
        /* Impact level badges */
        .impact-high, .impact-critical {
            @apply bg-red-50 text-red-700 border-red-200;
        }
        .impact-medium {
            @apply bg-yellow-50 text-yellow-700 border-yellow-200;
        }
        .impact-low {
            @apply bg-green-50 text-green-700 border-green-200;
        }
    </style>
</head>
<body class="bg-gray-50 dark:bg-gray-900">
    <!-- Navbar -->
    <?php include 'includes/navbar.php'; ?>
    
    <!-- Loading Overlay -->
    <?php include 'includes/loading.php'; ?>

    <!-- Main Content -->
    <main class="py-6">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 mb-6">
                <div class="bg-green-50 border-l-4 border-green-400 p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-check-circle text-green-400"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-green-700"><?php echo htmlspecialchars($_SESSION['success']); ?></p>
                        </div>
                    </div>
                </div>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Page Header -->
            <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-2xl font-bold leading-7 text-gray-900 dark:text-white sm:text-3xl sm:truncate">
                        Incident Management
                    </h2>
                </div>
                <div class="mt-4 sm:mt-0 flex items-center space-x-3">
                    <span class="text-xs text-gray-500 dark:text-gray-400" id="last-updated">
                        Last updated: <?php echo date('g:i A'); ?>
                    </span>
                    <button onclick="refreshIncidents()" class="inline-flex items-center px-3 py-2 border border-gray-300 dark:border-gray-600 shadow-sm text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-150">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                        Refresh
                    </button>
                </div>
            </div>
            
            <!-- Search and Filter Bar -->
            <div class="mb-6 flex flex-col sm:flex-row gap-4">
                <div class="flex-1">
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </div>
                        <input type="text" id="incident-search" placeholder="Search incidents by service, company, or root cause..." class="block w-full pl-10 pr-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent sm:text-sm" onkeyup="filterIncidents()">
                    </div>
                </div>
                <div class="mt-4 flex md:mt-0 md:ml-6">
                    <div class="inline-flex rounded-lg shadow-sm" role="group">
                        <button type="button" 
                                data-status="all"
                                class="status-toggle px-4 py-2 text-sm font-medium rounded-l-lg border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 transition-colors duration-200 ease-in-out focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-blue-500">
                            <span class="flex items-center">
                                <i class="fas fa-list-ul mr-2 text-gray-500"></i>
                                <span>All</span>
                            </span>
                        </button>
                        <button type="button" 
                                data-status="pending"
                                class="status-toggle px-4 py-2 text-sm font-medium border-t border-b border-gray-200 bg-white text-gray-700 hover:bg-yellow-50 transition-colors duration-200 ease-in-out focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-yellow-500">
                            <span class="flex items-center">
                                <i class="fas fa-clock mr-2 text-yellow-500"></i>
                                <span>Pending</span>
                            </span>
                        </button>
                        <button type="button" 
                                data-status="resolved"
                                class="status-toggle px-4 py-2 text-sm font-medium rounded-r-lg border border-gray-200 bg-white text-gray-700 hover:bg-green-50 transition-colors duration-200 ease-in-out focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-green-500">
                            <span class="flex items-center">
                                <i class="fas fa-check-circle mr-2 text-green-500"></i>
                                <span>Resolved</span>
                            </span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Incidents List -->
            <div class="space-y-4">
                <!-- Empty State for No Results -->
                <div id="no-results" class="hidden text-center py-12">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No incidents found</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Try selecting a different filter.</p>
                </div>
                
                <?php if (empty($incidents)): ?>
                    <div class="text-center py-12">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No incidents reported</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Get started by reporting a new incident.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($incidents as $incident): 
                        $statusClass = $incident['status'] === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800';
                        
                        // Impact level colors
                        $impactColors = [
                            'critical' => 'bg-red-100 text-red-800',
                            'high' => 'bg-orange-100 text-orange-800',
                            'medium' => 'bg-yellow-100 text-yellow-800',
                            'low' => 'bg-blue-100 text-blue-800'
                        ];
                        $impactClass = $impactColors[strtolower($incident['impact_level'])] ?? 'bg-gray-100 text-gray-800';
                    ?>
                        <div class="incident-card bg-white dark:bg-gray-800 shadow overflow-hidden sm:rounded-lg mb-6 border dark:border-gray-700" data-status="<?php echo $incident['status']; ?>">
                            <div class="px-4 py-5 sm:px-6">
                                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                                                <?php echo htmlspecialchars($incident['service_name']); ?>
                                            </h3>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $impactClass; ?>">
                                                <?php echo ucfirst($incident['impact_level']); ?>
                                            </span>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $statusClass; ?>">
                                                <?php echo ucfirst($incident['status']); ?>
                                            </span>
                                        </div>
                                        <p class="mt-1 max-w-2xl text-sm text-gray-500 dark:text-gray-400">
                                            Reported by <?php echo htmlspecialchars($incident['user_name']); ?> on 
                                            <?php echo date('M j, Y g:i A', strtotime($incident['created_at'])); ?>
                                        </p>
                                    </div>
                                    <?php if ($incident['status'] === 'pending'): ?>
                                        <button type="button" 
                                                onclick="showResolveModal(<?php echo $incident['issue_id']; ?>, '<?php echo addslashes(htmlspecialchars($incident['service_name'])); ?>')" 
                                                class="mt-2 sm:mt-0 inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                            <i class="fas fa-check mr-1"></i> Mark as Resolved
                                        </button>
                                    <?php else: ?>
                                        <span class="text-sm text-green-600 font-medium">
                                            Resolved by <?php echo htmlspecialchars($incident['resolved_by'] ?? 'System'); ?> on <?php echo $incident['resolved_at'] ? date('M j, Y g:i A', strtotime($incident['resolved_at'])) : 'Unknown'; ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="border-t border-gray-200 dark:border-gray-700 px-4 py-5 sm:p-6">
                                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                    <!-- LEFT COLUMN: Details -->
                                    <div class="space-y-4">
                                        <div>
                                            <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Affected Companies</h4>
                                            <p class="mt-1 text-sm text-gray-900 dark:text-white"><?php echo htmlspecialchars($incident['affected_companies']); ?></p>
                                        </div>
                                        
                                        <div>
                                            <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Root Cause</h4>
                                            <?php if (!empty($incident['root_cause'])): ?>
                                                <p class="mt-1 text-sm text-gray-900 dark:text-white"><?php echo htmlspecialchars($incident['root_cause']); ?></p>
                                            <?php else: ?>
                                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400 italic">Not specified</p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <!-- RIGHT COLUMN: Updates -->
                                    <div class="lg:border-l lg:border-gray-200 dark:lg:border-gray-700 lg:pl-6">
                                        <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-3">Updates (<?php echo $incident['update_count']; ?>)</h4>
                                        
                                        <?php if (empty($incident['updates'])): ?>
                                            <p class="text-sm text-gray-500 dark:text-gray-400 italic">No updates available.</p>
                                        <?php else: ?>
                                            <div class="space-y-3 mb-4">
                                                <?php foreach ($incident['updates'] as $update): ?>
                                                    <div class="text-sm">
                                                        <div class="flex items-baseline gap-2">
                                                            <span class="font-medium text-gray-900 dark:text-white"><?php echo htmlspecialchars($update['user_name']); ?></span>
                                                            <span class="text-xs text-gray-500 dark:text-gray-400">• <?php echo date('M j, g:i A', strtotime($update['created_at'])); ?></span>
                                                        </div>
                                                        <p class="mt-0.5 text-gray-700 dark:text-gray-300"><?php echo nl2br(htmlspecialchars($update['update_text'])); ?></p>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <!-- Add Update Form - Inline -->
                                        <form method="POST" class="mt-3">
                                            <input type="hidden" name="action" value="add_update">
                                            <input type="hidden" name="issue_id" value="<?php echo $incident['issue_id']; ?>">
                                            <div class="flex gap-2">
                                                <input type="text" 
                                                       name="user_name" 
                                                       placeholder="Your Name" 
                                                       required
                                                       class="w-32 text-sm border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 py-1.5 px-3">
                                                <input type="text" 
                                                       name="update_text" 
                                                       placeholder="Add an update..." 
                                                       required
                                                       class="flex-1 text-sm border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 py-1.5 px-3">
                                                <button type="submit" 
                                                        class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md text-white bg-gray-900 hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                                                    Post
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Resolve Issue Modal -->
    <div id="resolveModal" class="hidden fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50 p-4 transition-opacity duration-300">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full transform transition-all duration-300 scale-95 opacity-0" id="modalContent">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-1">Resolve Issue</h3>
                <p class="text-sm text-gray-500 mb-4" id="modalServiceName"></p>
                
                <form id="resolveForm" method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="issue_id" id="modal_issue_id" value="">
                    <input type="hidden" name="status" value="resolved">
                    
                    <div>
                        <label for="resolve_name" class="block text-sm font-medium text-gray-700">Your Name</label>
                        <input type="text" 
                               id="resolve_name" 
                               name="user_name" 
                               class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                               required
                               autocomplete="off">
                    </div>
                    
                    <div class="flex justify-end space-x-3 pt-2">
                        <button type="button" 
                                onclick="hideResolveModal()"
                                class="inline-flex justify-center py-2 px-4 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="inline-flex justify-center items-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                            <i class="fas fa-check mr-2"></i> Mark as Resolved
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    // Status toggle functionality
    document.addEventListener('DOMContentLoaded', function() {
        const statusToggles = document.querySelectorAll('.status-toggle');
        
        statusToggles.forEach(button => {
            button.addEventListener('click', function() {
                const status = this.getAttribute('data-status');
                
                // Update active state
                statusToggles.forEach(btn => {
                    btn.classList.remove(
                        'bg-blue-50', 'text-blue-700', 'border-blue-200',
                        'bg-yellow-50', 'text-yellow-700', 'border-yellow-200',
                        'bg-green-50', 'text-green-700', 'border-green-200'
                    );
                    btn.classList.add('bg-white', 'text-gray-700', 'border-gray-200');
                    
                    // Reset icon colors
                    const icon = btn.querySelector('i');
                    if (icon) {
                        icon.classList.remove('text-blue-500', 'text-yellow-500', 'text-green-500');
                        if (btn.getAttribute('data-status') === 'pending') {
                            icon.classList.add('text-yellow-500');
                        } else if (btn.getAttribute('data-status') === 'resolved') {
                            icon.classList.add('text-green-500');
                        } else {
                            icon.classList.add('text-gray-500');
                        }
                    }
                });
                
                // Set active button styles
                if (status === 'pending') {
                    this.classList.add('bg-yellow-50', 'text-yellow-700', 'border-yellow-200');
                    this.querySelector('i').classList.add('text-yellow-600');
                } else if (status === 'resolved') {
                    this.classList.add('bg-green-50', 'text-green-700', 'border-green-200');
                    this.querySelector('i').classList.add('text-green-600');
                } else {
                    this.classList.add('bg-blue-50', 'text-blue-700', 'border-blue-200');
                    this.querySelector('i').classList.add('text-blue-600');
                }
                
                // Filter incidents
                const incidents = document.querySelectorAll('.incident-card');
                const noResults = document.getElementById('no-results');
                let visibleCount = 0;
                
                incidents.forEach(incident => {
                    const incidentStatus = incident.getAttribute('data-status');
                    if (status === 'all' || incidentStatus === status) {
                        incident.classList.remove('hidden');
                        visibleCount++;
                    } else {
                        incident.classList.add('hidden');
                    }
                });
                
                // Show/hide empty state
                if (visibleCount === 0) {
                    noResults.classList.remove('hidden');
                } else {
                    noResults.classList.add('hidden');
                }
                
                // Update URL without page reload
                const url = new URL(window.location);
                if (status === 'all') {
                    url.searchParams.delete('status');
                } else {
                    url.searchParams.set('status', status);
                }
                window.history.pushState({}, '', url);
            });
        });
        
        // Set initial active state from URL
        const urlParams = new URLSearchParams(window.location.search);
        const statusParam = urlParams.get('status');
        if (statusParam) {
            const activeButton = document.querySelector(`.status-toggle[data-status="${statusParam}"]`);
            if (activeButton) activeButton.click();
        } else {
            // Default to 'all' if no status in URL
            document.querySelector('.status-toggle[data-status="all"]').click();
        }
    });
    
    // Resolve Modal Functions
    function showResolveModal(issueId, serviceName) {
        const modal = document.getElementById('resolveModal');
        const modalContent = document.getElementById('modalContent');
        
        // Set the issue ID and service name
        document.getElementById('modal_issue_id').value = issueId;
        document.getElementById('modalServiceName').textContent = `Service: ${serviceName}`;
        
        // Show modal with animation
        modal.classList.remove('hidden');
        setTimeout(() => {
            modalContent.classList.remove('opacity-0', 'scale-95');
            modalContent.classList.add('opacity-100', 'scale-100');
            document.getElementById('resolve_name').focus();
        }, 10);
    }
    
    function hideResolveModal() {
        const modal = document.getElementById('resolveModal');
        const modalContent = document.getElementById('modalContent');
        
        // Hide with animation
        modalContent.classList.remove('opacity-100', 'scale-100');
        modalContent.classList.add('opacity-0', 'scale-95');
        
        // Hide modal after animation
        setTimeout(() => {
            modal.classList.add('hidden');
            // Reset form
            document.getElementById('resolveForm').reset();
        }, 200);
    }
    
    // Close modal when clicking outside
    document.getElementById('resolveModal').addEventListener('click', function(e) {
        if (e.target === this) {
            hideResolveModal();
        }
    });
    
    // Handle form submission
    document.getElementById('resolveForm').addEventListener('submit', function(e) {
        const nameInput = document.getElementById('resolve_name');
        if (!nameInput.value.trim()) {
            e.preventDefault();
            nameInput.focus();
            // Add error class
            nameInput.classList.add('border-red-500');
            // Remove error class after animation
            setTimeout(() => nameInput.classList.remove('border-red-500'), 1000);
        }
    });
    
    // Close modal with ESC key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && !document.getElementById('resolveModal').classList.contains('hidden')) {
            hideResolveModal();
        }
    });
    </script>
    
    <script>
        function toggleRootCause(issueId) {
            const rootCause = document.getElementById(`root-cause-${issueId}`);
            const readMoreBtn = rootCause.nextElementSibling;
            const readMoreText = readMoreBtn.querySelector('.read-more');
            const readLessText = readMoreBtn.querySelector('.read-less');
            
            if (rootCause.classList.contains('line-clamp-3')) {
                rootCause.classList.remove('line-clamp-3');
                readMoreText.classList.add('hidden');
                readLessText.classList.remove('hidden');
            } else {
                rootCause.classList.add('line-clamp-3');
                readMoreText.classList.remove('hidden');
                readLessText.classList.add('hidden');
                
                // Scroll the element into view if needed
                rootCause.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        }

        // Auto-resize textareas
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('textarea').forEach(textarea => {
                textarea.addEventListener('input', function() {
                    this.style.height = 'auto';
                    this.style.height = (this.scrollHeight) + 'px';
                });
            });
        });
    </script>
    
    <script>
        // Search/Filter incidents
        function filterIncidents() {
            const searchTerm = document.getElementById('incident-search').value.toLowerCase();
            const cards = document.querySelectorAll('.incident-card');
            let visibleCount = 0;
            
            cards.forEach(card => {
                const text = card.textContent.toLowerCase();
                if (text.includes(searchTerm)) {
                    card.style.display = '';
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });
        }
        
        // Refresh incidents function
        function refreshIncidents() {
            const btn = event.target.closest('button');
            
            // Show loading state
            btn.disabled = true;
            btn.innerHTML = '<div class="btn-spinner"></div> Refreshing...';
            
            // Reload the page
            setTimeout(() => {
                location.reload();
            }, 300);
        }
    </script>
</body>
</html>