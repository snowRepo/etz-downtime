<?php
require_once 'config.php';
session_start();

// Set default date range (last 30 days including today)
$endDate = date('Y-m-d', strtotime('+1 day')); // Include today by going to start of next day
$startDate = date('Y-m-d', strtotime('-30 days'));

// Get filter parameters
$companyId = $_GET['company_id'] ?? null;
$startDate = $_GET['start_date'] ?? $startDate;
$endDate = $_GET['end_date'] ?? $endDate;

// Fetch data for charts
try {
    // Get total incidents by status (grouped by service and root cause)
    $statusQuery = "SELECT 
                        status, 
                        COUNT(DISTINCT CONCAT(service_id, '-', root_cause)) as count 
                    FROM issues_reported 
                    WHERE created_at BETWEEN ? AND ? " . 
                    ($companyId ? "AND company_id = ? " : "") . 
                    "GROUP BY status";
    $stmt = $pdo->prepare($statusQuery);
    $params = [$startDate, $endDate];
    if ($companyId) $params[] = $companyId;
    $stmt->execute($params);
    $incidentsByStatus = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get incidents by company (count distinct by service and root cause)
    $companyQuery = "SELECT 
                        c.company_name, 
                        COUNT(DISTINCT CONCAT(i.service_id, '-', i.root_cause)) as incident_count 
                    FROM issues_reported i
                    JOIN companies c ON i.company_id = c.company_id
                    WHERE i.created_at BETWEEN ? AND ? " . 
                    ($companyId ? "AND i.company_id = ? " : "") . 
                    "GROUP BY i.company_id 
                    ORDER BY incident_count DESC";
    $stmt = $pdo->prepare($companyQuery);
    $stmt->execute($params);
    $incidentsByCompany = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get monthly trend
    $trendQuery = "SELECT 
                    DATE_FORMAT(i.created_at, '%Y-%m') as month,
                    COUNT(DISTINCT CONCAT(i.service_id, '-', i.root_cause)) as incident_count
                   FROM issues_reported i
                   WHERE i.created_at BETWEEN ? AND ? " . 
                   ($companyId ? "AND i.company_id = ? " : "") . 
                   "GROUP BY DATE_FORMAT(i.created_at, '%Y-%m')
                   ORDER BY month";
    $stmt = $pdo->prepare($trendQuery);
    $stmt->execute($params);
    $monthlyTrend = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get impact level distribution
    $impactQuery = "SELECT impact_level, COUNT(*) as count 
                   FROM issues_reported i
                   WHERE i.created_at BETWEEN ? AND ? " . 
                   ($companyId ? "AND i.company_id = ? " : "") . 
                   "GROUP BY impact_level";
    $stmt = $pdo->prepare($impactQuery);
    $stmt->execute($params);
    $impactLevels = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error fetching analytics data: " . $e->getMessage());
}

// Prepare data for charts
$statusLabels = [];
$statusData = [];
$statusColors = [];

// Define status colors
$statusColorMap = [
    'pending' => '#f59e0b',  // yellow
    'resolved' => '#10b981'  // green
];

// Prepare status data with consistent order and colors
$statuses = ['pending', 'resolved'];
$hasData = false;

foreach ($statuses as $status) {
    $found = false;
    foreach ($incidentsByStatus as $statusItem) {
        if (strtolower($statusItem['status']) === $status) {
            $statusLabels[] = ucfirst($status);
            $statusData[] = (int)$statusItem['count'];
            $statusColors[] = $statusColorMap[$status];
            $found = true;
            $hasData = $hasData || $statusItem['count'] > 0;
            break;
        }
    }
    if (!$found) {
        $statusLabels[] = ucfirst($status);
        $statusData[] = 0;
        $statusColors[] = $statusColorMap[$status];
    }
}

// Handle empty state
if (!$hasData) {
    $statusLabels = ['No Data'];
    $statusData = [1];
    $statusColors = ['#6b7280'];  // gray for empty state
}

$companyLabels = [];
$companyData = [];
foreach ($incidentsByCompany as $company) {
    $companyLabels[] = $company['company_name'];
    $companyData[] = (int)$company['incident_count'];
}

$monthlyLabels = [];
$monthlyData = [];
foreach ($monthlyTrend as $month) {
    $monthlyLabels[] = date('M Y', strtotime($month['month'] . '-01'));
    $monthlyData[] = (int)$month['incident_count'];
}

$impactLabels = [];
$impactData = [];
$impactColors = [
    'Low' => '#10b981',
    'Medium' => '#f59e0b',
    'High' => '#ef4444',
    'Critical' => '#7c3aed'
];

foreach ($impactLevels as $impact) {
    $impactLabels[] = $impact['impact_level'];
    $impactData[] = (int)$impact['count'];
}

// Get companies for filter dropdown
$companies = $pdo->query("SELECT company_id, company_name FROM companies ORDER BY company_name")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics - eTranzact Downtime Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .chart-container {
            position: relative;
            height: 350px;
            width: 100%;
        }
        .stat-card {
            transition: transform 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Navbar -->
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container mx-auto px-4 py-8">
        <!-- Header with title and export button -->
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-2xl font-bold text-gray-800">Analytics</h1>
            
            <!-- PDF Export Button -->
            <a href="export_analytics_pdf.php?company_id=<?= $companyId ? htmlspecialchars($companyId) : '' ?>&start_date=<?= htmlspecialchars($startDate) ?>&end_date=<?= htmlspecialchars($endDate) ?>" 
               class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                <svg class="-ml-1 mr-2 h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd" />
                </svg>
                Export PDF
            </a>
        </div>
        
        <!-- Date Range Picker and Filters -->
        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200 mb-8">
            <form method="GET" class="flex flex-wrap items-end gap-4">
                <div class="flex items-center space-x-2">
                    <label class="text-sm font-medium text-gray-700">Company:</label>
                    <div class="relative">
                        <button type="button" id="company-dropdown-button" class="relative w-48 bg-white border border-gray-300 rounded-md shadow-sm pl-3 pr-10 py-1.5 text-left cursor-default focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-sm">
                            <span id="company-selected-text" class="block truncate">
                                <?php 
                                $selectedCompanyName = 'All Companies';
                                if ($companyId) {
                                    foreach ($companies as $company) {
                                        if ($company['company_id'] == $companyId) {
                                            $selectedCompanyName = $company['company_name'];
                                            break;
                                        }
                                    }
                                }
                                echo htmlspecialchars($selectedCompanyName);
                                ?>
                            </span>
                            <span class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none">
                                <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M10 3a1 1 0 01.707.293l3 3a1 1 0 01-1.414 1.414L10 5.414 7.707 7.707a1 1 0 01-1.414-1.414l3-3A1 1 0 0110 3zm-3.707 9.293a1 1 0 011.414 0L10 14.586l2.293-2.293a1 1 0 011.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </span>
                        </button>
                        <div id="company-dropdown" class="hidden absolute z-10 mt-1 w-full bg-white shadow-lg max-h-60 rounded-md py-1 text-sm ring-1 ring-black ring-opacity-5 overflow-auto focus:outline-none">
                            <div class="flex items-center px-4 py-2 hover:bg-gray-100">
                                <input type="radio" id="company-all" name="company_id" value="" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300" <?= empty($companyId) ? 'checked' : '' ?>>
                                <label for="company-all" class="ml-3 block text-sm font-medium text-gray-700">All Companies</label>
                            </div>
                            <?php foreach ($companies as $company): ?>
                                <div class="flex items-center px-4 py-2 hover:bg-gray-100">
                                    <input type="radio" id="company-<?= $company['company_id'] ?>" name="company_id" value="<?= $company['company_id'] ?>" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300" <?= $companyId == $company['company_id'] ? 'checked' : '' ?>>
                                    <label for="company-<?= $company['company_id'] ?>" class="ml-3 block text-sm font-medium text-gray-700">
                                        <?= htmlspecialchars($company['company_name']) ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <input type="hidden" name="company_id" id="selected-company-id" value="<?= $companyId ?>">
                    </div>
                </div>
                <div class="flex items-center space-x-2">
                    <label class="text-sm font-medium text-gray-700">From:</label>
                    <input type="date" name="start_date" value="<?= $startDate ?>" class="border rounded px-3 py-1 text-sm">
                </div>
                <div class="flex items-center space-x-2">
                    <label class="text-sm font-medium text-gray-700">To:</label>
                    <input type="date" name="end_date" value="<?= $endDate ?>" class="border rounded px-3 py-1 text-sm">
                </div>
                <button type="submit" name="apply_filters" value="1" class="bg-blue-600 text-white px-4 py-1 rounded text-sm hover:bg-blue-700">
                    Apply
                </button>
                <?php if (isset($_GET['apply_filters'])): ?>
                    <a href="analytics.php" class="bg-white border border-gray-300 text-gray-700 px-4 py-1 rounded text-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Clear
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <?php
            $totalIncidents = array_sum($statusData);
            $openIncidents = 0;
            $resolvedIncidents = 0;
            $avgResolutionTime = 'N/A';
            
            foreach ($incidentsByStatus as $status) {
                if ($status['status'] === 'pending') {
                    $openIncidents = $status['count'];
                }
                if ($status['status'] === 'resolved') {
                    $resolvedIncidents = $status['count'];
                }
            }
            
            // Calculate average resolution time (in hours)
            $resolutionQuery = "SELECT AVG(TIMESTAMPDIFF(HOUR, d.actual_start_time, COALESCE(d.actual_end_time, NOW()))) as avg_hours 
                              FROM issues_reported i
                              JOIN downtime_incidents d ON i.issue_id = d.issue_id
                              WHERE d.actual_start_time IS NOT NULL
                              AND i.created_at BETWEEN ? AND ? " . 
                              ($companyId ? "AND i.company_id = ? " : "");
            $stmt = $pdo->prepare($resolutionQuery);
            $stmt->execute($params);
            $avgResolution = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($avgResolution && $avgResolution['avg_hours'] !== null) {
                $avgHours = round($avgResolution['avg_hours'], 1);
                $avgResolutionTime = $avgHours < 24 
                    ? $avgHours . ' hours' 
                    : round($avgHours / 24, 1) . ' days';
            }
            ?>
            
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="w-14 h-14 rounded-full bg-blue-100 flex items-center justify-center flex-shrink-0 mr-4">
                        <i class="fas fa-exclamation-triangle text-blue-600 text-2xl"></i>
                    </div>
                    <div>
                        <p class="text-gray-500 text-sm font-medium">Total <br> Incidents</p>
                        <h3 class="text-2xl font-bold text-gray-800"><?= number_format($totalIncidents) ?></h3>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="w-14 h-14 rounded-full bg-yellow-100 flex items-center justify-center flex-shrink-0 mr-4">
                        <i class="fas fa-clock text-yellow-600 text-2xl"></i>
                    </div>
                    <div>
                        <p class="text-gray-500 text-sm font-medium">Pending <br> Incidents</p>
                        <h3 class="text-2xl font-bold text-gray-800"><?= number_format($openIncidents) ?></h3>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="w-14 h-14 rounded-full bg-green-100 flex items-center justify-center flex-shrink-0 mr-4">
                        <i class="fas fa-check-circle text-green-600 text-2xl"></i>
                    </div>
                    <div>
                        <p class="text-gray-500 text-sm font-medium">Resolved Incidents</p>
                        <h3 class="text-2xl font-bold text-gray-800"><?= number_format($resolvedIncidents) ?></h3>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="w-14 h-14 rounded-full bg-purple-100 flex items-center justify-center flex-shrink-0 mr-4">
                        <i class="fas fa-stopwatch text-purple-600 text-2xl"></i>
                    </div>
                    <div>
                        <p class="text-gray-500 text-sm font-medium">Avg. Resolution Time</p>
                        <h3 class="text-2xl font-bold text-gray-800"><?= $avgResolutionTime ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row 1 -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            <!-- Incidents by Status -->
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Incidents by Status</h3>
                <div class="chart-container">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>
            
            <!-- Monthly Trend -->
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Monthly Incident Trend</h3>
                <div class="chart-container">
                    <canvas id="trendChart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Charts Row 2 -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            <!-- Incidents by Company -->
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Incidents by Company</h3>
                <div class="chart-container">
                    <canvas id="companyChart"></canvas>
                </div>
            </div>
            
            <!-- Impact Level Distribution -->
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Impact Level Distribution</h3>
                <div class="chart-container">
                    <canvas id="impactChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Company dropdown functionality
        document.addEventListener('DOMContentLoaded', function() {
            const dropdownButton = document.getElementById('company-dropdown-button');
            const dropdown = document.getElementById('company-dropdown');
            const selectedText = document.getElementById('company-selected-text');
            const selectedIdInput = document.getElementById('selected-company-id');
            const companyRadios = document.querySelectorAll('input[name="company_id"]');
            
            // Toggle dropdown
            dropdownButton.addEventListener('click', function(e) {
                e.stopPropagation();
                dropdown.classList.toggle('hidden');
            });
            
            // Close dropdown when clicking outside
            document.addEventListener('click', function() {
                dropdown.classList.add('hidden');
            });
            
            // Prevent dropdown from closing when clicking inside it
            dropdown.addEventListener('click', function(e) {
                e.stopPropagation();
            });
            
            // Handle radio button changes
            companyRadios.forEach(radio => {
                radio.addEventListener('change', function() {
                    if (this.checked) {
                        const label = this.nextElementSibling.textContent.trim();
                        selectedText.textContent = label;
                        selectedIdInput.value = this.value;
                        dropdown.classList.add('hidden');
                        
                        // Submit the form when a company is selected
                        const form = this.closest('form');
                        if (form) {
                            form.submit();
                        }
                    }
                });
            });
        });
        // Incidents by Status (Doughnut Chart)
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($statusLabels) ?>,
                datasets: [{
                    data: <?= json_encode($statusData) ?>,
                    backgroundColor: <?= json_encode($statusColors) ?>,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                    }
                }
            }
        });

        // Monthly Trend (Bar Chart)
        const trendCtx = document.getElementById('trendChart').getContext('2d');
        new Chart(trendCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($monthlyLabels) ?>,
                datasets: [{
                    label: 'Number of Incidents',
                    data: <?= json_encode($monthlyData) ?>,
                    backgroundColor: [
                        '#3b82f6', '#60a5fa', '#93c5fd', '#3b82f6', '#60a5fa', 
                        '#93c5fd', '#3b82f6', '#60a5fa', '#93c5fd', '#3b82f6',
                        '#60a5fa', '#93c5fd', '#3b82f6'
                    ],
                    borderWidth: 0,
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });

        // Incidents by Company (Bar Chart)
        const companyCtx = document.getElementById('companyChart').getContext('2d');
        new Chart(companyCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($companyLabels) ?>,
                datasets: [{
                    label: 'Number of Incidents',
                    data: <?= json_encode($companyData) ?>,
                    backgroundColor: [
                        '#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899',
                        '#14b8a6', '#f97316', '#6366f1', '#f43f5e', '#06b6d4'
                    ],
                    borderWidth: 0,
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });

        // Impact Level Distribution (Pie Chart)
        const impactCtx = document.getElementById('impactChart').getContext('2d');
        
        // Prepare data for the chart
        let impactLabels = <?= json_encode($impactLabels) ?>;
        let impactData = <?= json_encode($impactData) ?>;
        
        // Define impact level colors with clear severity indicators
        const impactColorMap = {
            'Critical': '#dc2626',  // Dark Red - Immediate attention required
            'High': '#ef4444',      // Red - High severity
            'Medium': '#f59e0b',    // Amber - Medium severity
            'Low': '#10b981'        // Green - Low severity
        };
        // Color Guide:
        // - Critical (Dark Red): Complete system outage or critical business impact
        // - High (Red): Major impact on business operations
        // - Medium (Amber): Moderate impact, some business functions affected
        // - Low (Green): Minor impact, minimal business disruption
        
        // Sort impact levels in order of severity for consistent color mapping
        const severityOrder = ['Critical', 'High', 'Medium', 'Low'];
        const sortedData = [];
        const sortedLabels = [];
        const sortedColors = [];
        
        // If no data, show empty state
        if (impactData.length === 0 || impactData.every(val => val === 0)) {
            impactLabels = ['No Data'];
            impactData = [1];
            sortedColors.push('#e5e7eb');
        } else {
            // Sort the data by severity
            severityOrder.forEach(level => {
                const index = impactLabels.findIndex(label => 
                    label.toLowerCase() === level.toLowerCase()
                );
                if (index !== -1) {
                    sortedLabels.push(impactLabels[index]);
                    sortedData.push(impactData[index]);
                    sortedColors.push(impactColorMap[level]);
                }
            });
            
            // Update the original arrays with sorted data
            impactLabels = sortedLabels;
            impactData = sortedData;
        }
        
        new Chart(impactCtx, {
            type: 'pie',
            data: {
                labels: impactLabels,
                datasets: [{
                    data: impactData,
                    backgroundColor: sortedColors.length ? sortedColors : impactColors,
                    borderWidth: 1,
                    borderColor: '#fff',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            padding: 20,
                            usePointStyle: true,
                            pointStyle: 'circle',
                            font: {
                                size: 12
                            }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                if (context.label === 'No Data') {
                                    return 'No impact data available';
                                }
                                const label = context.label || '';
                                const value = context.raw || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = Math.round((value / total) * 100);
                                return `${label}: ${value} (${percentage}%)`;
                            }
                        },
                        displayColors: true,
                        usePointStyle: true
                    }
                }
            }
        });
    </script>

</body>
</html>
