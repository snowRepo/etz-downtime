<?php
require_once 'config.php';
require_once 'auth.php';
session_start();

// Require authentication for all pages
Auth::requireLogin();

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_update' && !empty($_POST['update_text'])) {
        // Add new update
        $stmt = $pdo->prepare("
            INSERT INTO incident_updates (incident_id, user_id, user_name, update_text) 
            VALUES (:incident_id, :user_id, :user_name, :update_text)
        ");
        $stmt->execute([
            ':incident_id' => $_POST['incident_id'],
            ':user_id' => $_SESSION['user_id'],
            ':user_name' => $user['full_name'],
            ':update_text' => trim($_POST['update_text'])
        ]);

        // Log activity
        $auth = new Auth($pdo);
        $auth->logActivity($_SESSION['user_id'], 'incident.update', "User added update to incident ID: " . $_POST['incident_id']);
    } elseif ($_POST['action'] === 'update_status' && isset($_POST['incident_id'], $_POST['status'])) {
        // First get the service_id for this incident
        $stmt = $pdo->prepare("SELECT service_id FROM incidents WHERE incident_id = ?");
        $stmt->execute([$_POST['incident_id']]);
        $service = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($service) {
            // Update all incidents with the same service_id
            $updateData = [
                ':status' => $_POST['status'],
                ':service_id' => $service['service_id'],
                ':user_name' => trim($_POST['user_name'])
            ];

            // Prepare the SQL based on status
            $sql = "UPDATE incidents 
                    SET status = :status, 
                        updated_at = NOW()";

            // Add resolved_by and resolved_at for resolved status
            if ($_POST['status'] === 'resolved') {
                $sql .= ", resolved_by = :resolved_by, resolved_at = NOW()";
                $updateData[':resolved_by'] = $user['full_name'];
            } else {
                $sql .= ", resolved_by = NULL, resolved_at = NULL";
            }

            $sql .= " WHERE service_id = :service_id";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($updateData);

            // Get all affected incident IDs for the update log
            $stmt = $pdo->prepare("SELECT incident_id FROM incidents WHERE service_id = ?");
            $stmt->execute([$service['service_id']]);
            $affectedIncidents = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // Add system update to each affected incident
            $statusText = $_POST['status'] === 'resolved' ? 'resolved' : 'reopened';
            $updateText = "All incidents for this service have been marked as {$statusText} by " . trim($_POST['user_name']);

            // Set success message
            $_SESSION['success'] = "Incident(s) updated successfully!";

            $stmt = $pdo->prepare("
                INSERT INTO incident_updates (incident_id, user_id, user_name, update_text) 
                VALUES (:incident_id, :user_id, :user_name, :update_text)
            ");

            foreach ($affectedIncidents as $incidentId) {
                $stmt->execute([
                    ':incident_id' => $incidentId,
                    ':user_id' => null, // System update
                    ':user_name' => 'System',
                    ':update_text' => $updateText
                ]);
            }

            // Log activity
            $auth = new Auth($pdo);
            $auth->logActivity($_SESSION['user_id'], 'incident.status_change', "User changed status to $statusText for service ID: " . $service['service_id']);
        }
    }

    // Redirect to prevent form resubmission
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Get all incidents with their updates
try {
    // Get incidents with affected companies
    $incidents = $pdo->query("
        SELECT 
            i.incident_id,
            i.service_id,
            i.root_cause,
            i.status,
            i.impact_level,
            i.attachment_path,
            i.created_at,
            i.resolved_by,
            i.resolved_at,
            i.updated_at,
            s.service_name,
            sc.name as component_name,
            it.name as incident_type_name,
            u.full_name as reported_by_name,
            GROUP_CONCAT(DISTINCT c.company_name ORDER BY c.company_name SEPARATOR ', ') as affected_companies,
            COUNT(DISTINCT iac.company_id) as company_count,
            (SELECT COUNT(*) FROM incident_updates iu WHERE iu.incident_id = i.incident_id) as update_count
        FROM incidents i
        JOIN services s ON i.service_id = s.service_id
        LEFT JOIN service_components sc ON i.component_id = sc.component_id
        LEFT JOIN incident_types it ON i.incident_type_id = it.type_id
        LEFT JOIN users u ON i.reported_by = u.user_id
        JOIN incident_affected_companies iac ON i.incident_id = iac.incident_id
        JOIN companies c ON iac.company_id = c.company_id
        GROUP BY i.incident_id, i.service_id, i.root_cause, i.status, i.impact_level, i.attachment_path, i.created_at, i.resolved_by, i.resolved_at, i.updated_at, s.service_name, sc.name, it.name, u.full_name
        ORDER BY 
            FIELD(i.status, 'pending', 'resolved'),
            i.updated_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Get updates for each incident
    foreach ($incidents as &$incident) {
        $stmt = $pdo->prepare("
            SELECT * FROM incident_updates 
            WHERE incident_id = ? 
            ORDER BY created_at DESC
        ");
        $stmt->execute([$incident['incident_id']]);
        $incident['updates'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    unset($incident); // Break the reference

} catch (PDOException $e) {
    die("ERROR: Could not fetch incidents. " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Incidents - ETZ Downtime</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .line-clamp-3 {
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .incident-card {
            transition: all 0.3s ease;
        }

        .incident-card:hover {
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

        .status-badge {
            @apply inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium;
        }

        /* Impact level badges */
        .impact-high,
        .impact-critical {
            @apply bg-red-100 text-red-800;
        }

        .impact-medium {
            @apply bg-yellow-100 text-yellow-800;
        }

        .impact-low {
            @apply bg-green-100 text-green-800;
        }
    </style>
</head>

<body class="bg-gray-100">
    <!-- Navbar -->
    <?php include 'includes/navbar.php'; ?>

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
            <div class="md:flex md:items-center md:justify-between mb-6">
                <div class="flex-1 min-w-0">
                    <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:text-3xl sm:truncate">
                        All Incidents
                    </h2>
                </div>
                <div class="mt-4 flex md:mt-0 md:ml-6">
                    <div class="inline-flex rounded-lg shadow-sm" role="group">
                        <button type="button" data-status="all"
                            class="status-toggle px-4 py-2 text-sm font-medium rounded-l-lg border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 transition-colors duration-200 ease-in-out focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-blue-500">
                            <span class="flex items-center">
                                <i class="fas fa-list-ul mr-2 text-gray-500"></i>
                                <span>All</span>
                            </span>
                        </button>
                        <button type="button" data-status="pending"
                            class="status-toggle px-4 py-2 text-sm font-medium border-t border-b border-gray-200 bg-white text-gray-700 hover:bg-yellow-50 transition-colors duration-200 ease-in-out focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-yellow-500">
                            <span class="flex items-center">
                                <i class="fas fa-clock mr-2 text-yellow-500"></i>
                                <span>Pending</span>
                            </span>
                        </button>
                        <button type="button" data-status="resolved"
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
                <?php if (empty($incidents)): ?>
                    <div class="text-center py-12">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900">No incidents reported</h3>
                        <p class="mt-1 text-sm text-gray-500">Get started by reporting a new incident.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($incidents as $incident):
                        $statusClass = $incident['status'] === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800';
                        $impactClass = 'impact-' . strtolower($incident['impact_level']);
                        ?>
                        <div class="incident-card bg-white shadow overflow-hidden sm:rounded-lg mb-6"
                            data-status="<?php echo $incident['status']; ?>">
                            <div class="px-4 py-5 sm:px-6">
                                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                                    <div class="flex-1">
                                        <div class="flex items-center">
                                            <h3 class="text-lg font-medium text-gray-900">
                                                <?php echo htmlspecialchars($incident['service_name']); ?>
                                            </h3>
                                            <span
                                                class="ml-2 px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $statusClass; ?>">
                                                <?php echo ucfirst($incident['status']); ?>
                                            </span>
                                        </div>
                                        <p class="mt-1 max-w-2xl text-sm text-gray-500">
                                            Reported by <span
                                                class="font-medium text-gray-700"><?php echo htmlspecialchars($incident['reported_by_name'] ?? 'Unknown'); ?></span>
                                            on
                                            <?php echo date('M j, Y g:i A', strtotime($incident['created_at'])); ?>
                                        </p>
                                        <?php if (!empty($incident['component_name']) || !empty($incident['incident_type_name'])): ?>
                                            <div class="mt-2 flex space-x-2">
                                                <?php if (!empty($incident['component_name'])): ?>
                                                    <span
                                                        class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800">
                                                        <i class="fas fa-cube mr-1 text-gray-400"></i>
                                                        <?php echo htmlspecialchars($incident['component_name']); ?>
                                                    </span>
                                                <?php endif; ?>
                                                <?php if (!empty($incident['incident_type_name'])): ?>
                                                    <span
                                                        class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-50 text-blue-700 border border-blue-100">
                                                        <i class="fas fa-tag mr-1 text-blue-400"></i>
                                                        <?php echo htmlspecialchars($incident['incident_type_name']); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php if (!empty($incident['attachment_path'])): ?>
                                                        <div class="mt-2 text-sm">
                                                            <a href="<?php echo htmlspecialchars($incident['attachment_path']); ?>" target="_blank" class="inline-flex items-center text-blue-600 hover:text-blue-800 font-medium">
                                                                <i class="fas fa-paperclip mr-1.5 opacity-70"></i>
                                                                View Attachment
                                                            </a>
                                                        </div>
                                                <?php endif; ?>
                                            </div>
                                            <?php if ($incident['status'] === 'pending'): ?>
                                                    <button type="button"
                                                        onclick="showResolveModal(<?php echo $incident['incident_id']; ?>, '<?php echo addslashes(htmlspecialchars($incident['service_name'])); ?>')"
                                                        class="mt-2 sm:mt-0 inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                                        <i class="fas fa-check mr-1"></i> Mark as Resolved
                                                    </button>
                                            <?php else: ?>
                                                    <span class="text-sm text-green-600 font-medium">
                                                        Resolved by <?php echo htmlspecialchars($incident['resolved_by'] ?? 'System'); ?> on
                                                        <?php echo date('M j, Y g:i A', strtotime($incident['resolved_at'] ?? $incident['updated_at'])); ?>
                                                    </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="border-t border-gray-200 px-4 py-5 sm:p-6">
                                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                            <div class="sm:col-span-1 text-center">
                                                <h4 class="text-sm font-medium text-gray-500">Impact Level</h4>
                                                <div class="flex justify-center">
                                                    <span
                                                        class="mt-1 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $impactClass; ?>">
                                                        <?php echo $incident['impact_level']; ?> Impact
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="sm:col-span-1 text-center">
                                                <h4 class="text-sm font-medium text-gray-500">Affected Companies
                                                    (<?php echo $incident['company_count']; ?>)</h4>
                                                <p class="mt-1 text-sm text-gray-900">
                                                    <?php echo htmlspecialchars($incident['affected_companies']); ?>
                                                </p>
                                            </div>
                                            <div class="sm:col-span-1 text-center">
                                                <h4 class="text-sm font-medium text-gray-500">Root Cause</h4>
                                                <div class="mt-1 px-2">
                                                    <?php if (!empty($incident['root_cause'])): ?>
                                                            <div class="relative">
                                                                <p id="root-cause-<?php echo $incident['incident_id']; ?>"
                                                                    class="text-sm text-gray-900 line-clamp-3">
                                                                    <?php echo htmlspecialchars($incident['root_cause']); ?>
                                                                </p>
                                                                <?php if (strlen($incident['root_cause']) > 100): ?>
                                                                        <button type="button"
                                                                            onclick="toggleRootCause(<?php echo $incident['incident_id']; ?>)"
                                                                            class="mt-1 text-xs text-blue-600 hover:text-blue-800 focus:outline-none">
                                                                            <span class="read-more">Read more</span>
                                                                            <span class="read-less hidden">Show less</span>
                                                                        </button>
                                                                <?php endif; ?>
                                                            </div>
                                                    <?php else: ?>
                                                            <p class="text-sm text-gray-500 italic">Not specified</p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Updates Section -->
                                        <div class="mt-6">
                                            <h4 class="text-sm font-medium text-gray-500 mb-3">Updates
                                                (<?php echo $incident['update_count']; ?>)</h4>
                                            <?php if (empty($incident['updates'])): ?>
                                                    <p class="text-sm text-gray-500 italic">No updates yet.</p>
                                            <?php else: ?>
                                                    <div class="space-y-4">
                                                        <?php foreach ($incident['updates'] as $update): ?>
                                                                <div class="flex">
                                                                    <div class="flex-shrink-0 mr-3">
                                                                        <div class="h-8 w-8 rounded-full bg-gray-200 flex items-center justify-center">
                                                                            <span class="text-gray-500 text-sm font-medium">
                                                                                <?php echo strtoupper(substr($update['user_name'], 0, 2)); ?>
                                                                            </span>
                                                                        </div>
                                                                    </div>
                                                                    <div class="flex-1 bg-gray-50 rounded-lg px-4 py-2">
                                                                        <div class="flex items-center justify-between">
                                                                            <span
                                                                                class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($update['user_name']); ?></span>
                                                                            <span
                                                                                class="text-xs text-gray-500"><?php echo date('M j, g:i A', strtotime($update['created_at'])); ?></span>
                                                                        </div>
                                                                        <p class="mt-1 text-sm text-gray-700">
                                                                            <?php echo nl2br(htmlspecialchars($update['update_text'])); ?>
                                                                        </p>
                                                                    </div>
                                                                </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                            <?php endif; ?>

                                            <!-- Add Update Form -->
                                            <form method="POST" class="mt-4">
                                                <input type="hidden" name="action" value="add_update">
                                                <input type="hidden" name="incident_id" value="<?php echo $incident['incident_id']; ?>">
                                                <div class="mt-1">
                                                    <label class="block text-sm font-medium text-gray-700">Posting as: <span
                                                            class="text-blue-600"><?php echo sanitize($user['full_name']); ?></span></label>
                                                </div>
                                                <div class="mt-2">
                                                    <label for="update_text_<?php echo $incident['incident_id']; ?>"
                                                        class="block text-sm font-medium text-gray-700">Add Update</label>
                                                    <textarea id="update_text_<?php echo $incident['incident_id']; ?>"
                                                        name="update_text" rows="2"
                                                        class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                                        placeholder="Add an update about this incident..." required></textarea>
                                                </div>
                                                <div class="mt-2 flex justify-end">
                                                    <button type="submit"
                                                        class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                                        <i class="fas fa-paper-plane mr-1"></i> Post Update
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                        <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Resolve Issue Modal -->
    <div id="resolveModal"
        class="hidden fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50 p-4 transition-opacity duration-300">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full transform transition-all duration-300 scale-95 opacity-0"
            id="modalContent">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-1">Resolve Issue</h3>
                <p class="text-sm text-gray-500 mb-4" id="modalServiceName"></p>

                <form id="resolveForm" method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="incident_id" id="modal_incident_id" value="">
                    <input type="hidden" name="status" value="resolved">

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Resolving as: <span
                                class="text-green-600"><?php echo sanitize($user['full_name']); ?></span></label>
                    </div>

                    <div class="flex justify-end space-x-3 pt-2">
                        <button type="button" onclick="hideResolveModal()"
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
        document.addEventListener('DOMContentLoaded', function () {
            const statusToggles = document.querySelectorAll('.status-toggle');

            statusToggles.forEach(button => {
                button.addEventListener('click', function () {
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
                    incidents.forEach(incident => {
                        const incidentStatus = incident.getAttribute('data-status');
                        if (status === 'all' || incidentStatus === status) {
                            incident.classList.remove('hidden');
                        } else {
                            incident.classList.add('hidden');
                        }
                    });

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
        function showResolveModal(incidentId, serviceName) {
            const modal = document.getElementById('resolveModal');
            const modalContent = document.getElementById('modalContent');

            // Set the incident ID and service name
            document.getElementById('modal_incident_id').value = incidentId;
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
        document.getElementById('resolveModal').addEventListener('click', function (e) {
            if (e.target === this) {
                hideResolveModal();
            }
        });

        // Handle form submission
        document.getElementById('resolveForm').addEventListener('submit', function (e) {
            // Automated user capture, no validation needed for name
        });

        // Close modal with ESC key
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !document.getElementById('resolveModal').classList.contains('hidden')) {
                hideResolveModal();
            }
        });
    </script>

    <script>
        function toggleRootCause(incidentId) {
            const rootCause = document.getElementById(`root-cause-${incidentId}`);
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
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('textarea').forEach(textarea => {
                textarea.addEventListener('input', function () {
                    this.style.height = 'auto';
                    this.style.height = (this.scrollHeight) + 'px';
                });
            });
        });
    </script>
</body>

</html>