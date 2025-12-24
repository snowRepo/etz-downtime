<?php
require_once 'config.php';
require_once 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

session_start();

// Default values
$companyId = $_GET['company_id'] ?? null;
$startDate = $_GET['start_date'] ?? date('Y-m-01'); // First day of current month
$endDate = $_GET['end_date'] ?? date('Y-m-t');     // Last day of current month

// Get companies for dropdown
try {
    $companies = $pdo->query("SELECT company_id, company_name FROM companies WHERE company_name != 'All' ORDER BY company_name")->fetchAll();
} catch(PDOException $e) {
    die("Error fetching companies: " . $e->getMessage());
}

$reportData = null;

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $companyId) {
    try {
    // Calculate total minutes in the period (24/7)
    $startDateTime = new DateTime($startDate);
    $endDateTime = (new DateTime($endDate))->modify('+1 day');
    $dateInterval = $startDateTime->diff($endDateTime);
    $totalMinutes = $dateInterval->days * 24 * 60;
    
    // Get SLA target for the company
    $slaStmt = $pdo->prepare("SELECT target_uptime FROM sla_targets WHERE company_id = ? LIMIT 1");
    $slaStmt->execute([$companyId]);
    $slaTarget = $slaStmt->fetch(PDO::FETCH_COLUMN) ?: 99.99;
    
    // Get all issues for the company with optional downtime data
    $stmt = $pdo->prepare("
        SELECT 
            ir.*,
            s.service_name,
            s.service_id,
            di.incident_id,
            COALESCE(di.actual_start_time, ir.created_at) as actual_start_time,
            di.actual_end_time,
            di.downtime_minutes,
            di.is_planned,
            di.downtime_category
        FROM issues_reported ir
        LEFT JOIN services s ON ir.service_id = s.service_id
        LEFT JOIN downtime_incidents di ON ir.issue_id = di.issue_id
        WHERE ir.company_id = ? 
        AND (
            (ir.created_at BETWEEN ? AND ?)  -- Created in range
            OR (ir.status = 'resolved' AND ir.updated_at BETWEEN ? AND ?)  -- Resolved in range
            OR (ir.created_at <= ? AND (ir.status = 'pending' OR ir.updated_at >= ?))  -- Ongoing during range
        )
        ORDER BY ir.created_at DESC
    ");
    
    $startDateTime = $startDate . ' 00:00:00';
    $endDateTime = $endDate . ' 23:59:59';
    $stmt->execute([
        $companyId, 
        $startDateTime, $endDateTime,  // Created in range
        $startDateTime, $endDateTime,  // Resolved in range
        $endDateTime, $startDateTime   // Ongoing during range
    ]);
    $incidents = $stmt->fetchAll();
    
    // Calculate total downtime and group by service
    $totalDowntime = 0;
    $downtimeByService = [];
    
    foreach ($incidents as $incident) {
        $downtime = $incident['downtime_minutes'] ?? 0;
        $totalDowntime += $downtime;
        
        // Only group by service if we have a service_id
        if (!empty($incident['service_id'])) {
            if (!isset($downtimeByService[$incident['service_id']])) {
                $downtimeByService[$incident['service_id']] = [
                    'name' => $incident['service_name'] ?? 'Unknown Service',
                    'downtime' => 0,
                    'incidents' => 0
                ];
            }
            $downtimeByService[$incident['service_id']]['downtime'] += $downtime;
            $downtimeByService[$incident['service_id']]['incidents']++;
        }
    }
    
    // Calculate uptime percentage, capped at the SLA target
    $uptimePercentage = $totalMinutes > 0 
        ? min($slaTarget, max(0, 100 - (($totalDowntime / $totalMinutes) * 100)))
        : $slaTarget;
    $isMetSla = $uptimePercentage >= $slaTarget;
    
        // Get company name
        $companyStmt = $pdo->prepare("SELECT company_name FROM companies WHERE company_id = ?");
        $companyStmt->execute([$companyId]);
        $companyName = $companyStmt->fetchColumn();

        $reportData = [
            'totalMinutes' => $totalMinutes,
            'totalDowntime' => $totalDowntime,
            'uptimePercentage' => $uptimePercentage,
            'slaTarget' => $slaTarget,
            'isMetSla' => $isMetSla,
            'incidents' => $incidents,
            'downtimeByService' => $downtimeByService,
            'companyName' => $companyName
        ];
    } catch(PDOException $e) {
        die("Error generating report: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SLA Report - eTranzact</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="//unpkg.com/alpinejs" defer></script>
</head>
<body class="bg-gray-100">
    <!-- Navbar -->
    <?php include 'includes/navbar.php'; ?>

    <!-- Main Content -->
    <main class="pt-4 pb-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Page Header -->
            <div class="md:flex md:items-center md:justify-between mb-6">
                <div class="flex-1 min-w-0">
                    <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:text-3xl sm:truncate">SLA Uptime Report</h2>
                </div>
                <div class="mt-4 flex md:mt-0 md:ml-4">
                    <!-- Export Dropdown -->
                    <div class="relative inline-block text-left" id="export-dropdown">
                        <div>
                            <button type="button" 
                                class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500"
                                onclick="toggleExportDropdown()">
                                <svg class="-ml-1 mr-2 h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                                Export
                                <svg id="export-arrow" class="-mr-1 ml-2 h-5 w-5 transition-transform duration-200" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        </div>

                        <div id="export-menu" 
                             class="hidden origin-top-right absolute right-0 mt-2 w-64 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 focus:outline-none z-50" 
                             role="menu" aria-orientation="vertical" aria-labelledby="menu-button" tabindex="-1">
                            <div class="py-1" role="none">
                                <div class="px-4 py-2 text-xs font-medium text-gray-500 border-b border-gray-100">
                                    Export to Excel
                                </div>
                                <a href="export_sla_report.php?start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>" 
                                   class="text-gray-700 block px-4 py-2 text-sm hover:bg-gray-100" role="menuitem" tabindex="-1">
                                    <span class="flex items-center">
                                        <svg class="mr-2 h-4 w-4 text-green-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                        All Companies
                                    </span>
                                </a>
                                <?php if ($companyId): ?>
                                <a href="export_sla_report.php?company_id=<?= urlencode($companyId) ?>&start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>" 
                                   class="text-gray-700 block px-4 py-2 text-sm hover:bg-gray-100" role="menuitem" tabindex="-1">
                                    <span class="flex items-center">
                                        <svg class="mr-2 h-4 w-4 text-green-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                        Current Filter
                                    </span>
                                </a>
                                <?php endif; ?>
                                
                                <div class="px-4 py-2 text-xs font-medium text-gray-500 border-t border-b border-gray-100 mt-1">
                                    Export to PDF
                                </div>
                                <a href="export_sla_report_pdf.php?start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>" 
                                   class="text-gray-700 block px-4 py-2 text-sm hover:bg-gray-100" role="menuitem" tabindex="-1">
                                    <span class="flex items-center">
                                        <svg class="mr-2 h-4 w-4 text-red-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                        </svg>
                                        All Companies (PDF)
                                    </span>
                                </a>
                                <?php if ($companyId): ?>
                                <a href="export_sla_report_pdf.php?company_id=<?= urlencode($companyId) ?>&start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>" 
                                   class="text-gray-700 block px-4 py-2 text-sm hover:bg-gray-100" role="menuitem" tabindex="-1">
                                    <span class="flex items-center">
                                        <svg class="mr-2 h-4 w-4 text-red-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                        </svg>
                                        Current Filter (PDF)
                                    </span>
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filter Form -->
            <div class="bg-white shadow overflow-visible sm:rounded-lg mb-6" style="position: relative; z-index: 1;">
                <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">
                        Report Filters
                    </h3>
                </div>
                <div class="px-4 py-5 sm:p-6">
                    <form method="get" class="space-y-4">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <div>
                                <label for="company_id" class="block text-sm font-medium text-gray-700">Company</label>
                                <div class="mt-1 relative">
                                    <button type="button" id="company-dropdown-button" class="relative w-full bg-white border border-gray-300 rounded-md shadow-sm pl-3 pr-10 py-2 text-left cursor-default focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                        <span id="company-selected-text" class="block truncate">
                                            <?php 
                                            $selectedText = '-- Select Company --';
                                            foreach ($companies as $company) {
                                                if ($companyId == $company['company_id']) {
                                                    $selectedText = htmlspecialchars($company['company_name']);
                                                    break;
                                                }
                                            }
                                            echo $selectedText;
                                            ?>
                                        </span>
                                        <span class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none">
                                            <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                <path fill-rule="evenodd" d="M10 3a1 1 0 01.707.293l3 3a1 1 0 01-1.414 1.414L10 5.414 7.707 7.707a1 1 0 01-1.414-1.414l3-3A1 1 0 0110 3zm-3.707 9.293a1 1 0 011.414 0L10 14.586l2.293-2.293a1 1 0 011.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd" />
                                            </svg>
                                        </span>
                                    </button>
                                    <input type="hidden" name="company_id" id="company_id" value="<?= htmlspecialchars($companyId) ?>">
                                    <div id="company-dropdown" class="hidden absolute z-10 mt-1 w-full bg-white shadow-lg max-h-60 rounded-md py-1 text-base ring-1 ring-black ring-opacity-5 overflow-auto focus:outline-none sm:text-sm">
                                        <?php foreach ($companies as $company): ?>
                                            <div class="text-gray-900 cursor-default select-none relative py-2 pl-3 pr-9 hover:bg-blue-100" 
                                                data-value="<?= htmlspecialchars($company['company_id']) ?>"
                                                data-display="<?= htmlspecialchars($company['company_name']) ?>">
                                                <span class="font-normal block truncate">
                                                    <?= htmlspecialchars($company['company_name']) ?>
                                                </span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <script>
                                    document.addEventListener('DOMContentLoaded', function() {
                                        const button = document.getElementById('company-dropdown-button');
                                        const dropdown = document.getElementById('company-dropdown');
                                        const hiddenInput = document.getElementById('company_id');
                                        const displayText = document.getElementById('company-selected-text');

                                        button.addEventListener('click', function() {
                                            dropdown.classList.toggle('hidden');
                                        });

                                        dropdown.querySelectorAll('div[data-value]').forEach(item => {
                                            item.addEventListener('click', function() {
                                                const value = this.getAttribute('data-value');
                                                const text = this.getAttribute('data-display');
                                                hiddenInput.value = value;
                                                displayText.textContent = text;
                                                dropdown.classList.add('hidden');
                                            });
                                        });

                                        // Close dropdown when clicking outside
                                        document.addEventListener('click', function(event) {
                                            if (!button.contains(event.target) && !dropdown.contains(event.target)) {
                                                dropdown.classList.add('hidden');
                                            }
                                        });
                                    });
                                </script>
                            </div>
                            <div>
                                <label for="start_date" class="block text-sm font-medium text-gray-700">Start Date</label>
                                <input type="date" id="start_date" name="start_date" 
                                    value="<?= htmlspecialchars($startDate) ?>"
                                    class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                            </div>
                            <div>
                                <label for="end_date" class="block text-sm font-medium text-gray-700">End Date</label>
                                <input type="date" id="end_date" name="end_date" 
                                    value="<?= htmlspecialchars($endDate) ?>"
                                    class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                            </div>
                            <div class="flex items-end space-x-3">
                                <button type="button" 
                                    onclick="window.location.href='sla_report.php'"
                                    class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    Clear
                                </button>
                                <button type="submit" 
                                    class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    <i class="fas fa-chart-line mr-2"></i> Generate Report
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <?php if ($reportData): ?>
                <!-- Summary Cards -->
                <div class="grid grid-cols-1 gap-5 mt-6 sm:grid-cols-3 mb-6">
                    <!-- SLA Target Card -->
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 bg-indigo-500 rounded-md p-3">
                                    <i class="fas fa-bullseye text-white text-xl"></i>
                                </div>
                                <div class="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt class="text-sm font-medium text-gray-500 truncate">
                                            SLA Target
                                        </dt>
                                        <dd class="flex items-baseline">
                                            <div class="text-2xl font-semibold text-gray-900">
                                                <?= number_format($reportData['slaTarget'], 2) ?>%
                                            </div>
                                        </dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Downtime Card -->
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 bg-red-500 rounded-md p-3">
                                    <i class="fas fa-exclamation-triangle text-white text-xl"></i>
                                </div>
                                <div class="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt class="text-sm font-medium text-gray-500 truncate">
                                            Total Downtime
                                        </dt>
                                        <dd class="flex items-baseline">
                                            <div class="text-2xl font-semibold text-gray-900">
                                                <?= number_format($reportData['totalDowntime'], 2) ?> minutes
                                            </div>
                                            <div class="ml-2 flex items-baseline text-sm font-semibold text-gray-500">
                                                (<?= number_format(($reportData['totalDowntime'] / 60), 2) ?> hours)
                                            </div>
                                        </dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Uptime Card -->
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 bg-green-500 rounded-md p-3">
                                    <i class="fas fa-chart-line text-white text-xl"></i>
                                </div>
                                <div class="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt class="text-sm font-medium text-gray-500 truncate">
                                            Actual Uptime
                                        </dt>
                                        <dd class="flex items-baseline">
                                            <div class="text-2xl font-semibold text-gray-900">
                                                <?= number_format($reportData['uptimePercentage'], 2) ?>%
                                            </div>
                                            <div class="ml-2 flex items-baseline text-sm font-semibold <?= $reportData['isMetSla'] ? 'text-green-600' : 'text-red-600' ?>">
                                                <?= $reportData['isMetSla'] ? '✓ Met' : '✗ Below Target' ?>
                                            </div>
                                        </dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Downtime by Service -->
                <div class="bg-white shadow overflow-hidden sm:rounded-lg mb-6">
                    <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                        <h3 class="text-lg leading-6 font-medium text-gray-900">
                            Downtime by Service
                        </h3>
                        <p class="mt-1 max-w-2xl text-sm text-gray-500">
                            Breakdown of downtime incidents by service
                        </p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Service</th>
                                    <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Incidents</th>
                                    <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Total Downtime</th>
                                    <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">% of Total</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (empty($reportData['downtimeByService'])): ?>
                                    <tr>
                                        <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500">No downtime data available</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($reportData['downtimeByService'] as $service): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 text-center">
                                                <?= htmlspecialchars($service['name']) ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-center text-gray-500">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                                    <?= $service['incidents'] ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-center text-gray-500">
                                                <?= number_format($service['downtime']) ?> mins
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-center text-gray-500">
                                                <?= $reportData['totalDowntime'] > 0 ? number_format(($service['downtime'] / $reportData['totalDowntime']) * 100, 2) : '0' ?>%
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Recent Incidents -->
                <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                    <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                        <h3 class="text-lg leading-6 font-medium text-gray-900">
                            Recent Incidents
                        </h3>
                        <p class="mt-1 max-w-2xl text-sm text-gray-500">
                            List of all downtime incidents for the selected period
                        </p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Service</th>
                                    <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Start Time</th>
                                    <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">End Time</th>
                                    <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Duration</th>
                                    <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Root Cause</th>
                                    <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Impact</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (empty($reportData['incidents'])): ?>
                                    <tr>
                                        <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">No incidents found for the selected period</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($reportData['incidents'] as $incident): 
                                        $impactClass = [
                                            'Low' => 'bg-blue-100 text-blue-800',
                                            'Medium' => 'bg-yellow-100 text-yellow-800',
                                            'High' => 'bg-orange-100 text-orange-800',
                                            'Critical' => 'bg-red-100 text-red-800'
                                        ][$incident['impact_level'] ?? 'Low'] ?? 'bg-gray-100 text-gray-800';
                                    ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 text-center">
                                                <?= htmlspecialchars($incident['service_name']) ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">
                                                <?= !empty($incident['actual_start_time']) && $incident['actual_start_time'] != '0000-00-00 00:00:00' 
                                                    ? date('M j, Y H:i', strtotime($incident['actual_start_time'])) 
                                                    : 'N/A' ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">
                                                <?php 
                                                if (empty($incident['actual_end_time']) || $incident['actual_end_time'] == '0000-00-00 00:00:00') {
                                                    if (($incident['status'] ?? '') === 'resolved') {
                                                        // If status is resolved but no end time, show status
                                                        echo 'Resolved (No end time)';
                                                    } else {
                                                        // If status is pending and no end time, show Ongoing
                                                        echo '<span class="text-red-600">Ongoing</span>';
                                                    }
                                                } else {
                                                    // Show the actual end time if it exists
                                                    echo date('M j, Y H:i', strtotime($incident['actual_end_time']));
                                                }
                                                ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-center text-gray-500">
                                                <?= $incident['downtime_minutes'] ?? 'N/A' ?> mins
                                            </td>
                                            <td class="px-6 py-4 text-sm text-gray-500 text-center">
                                                <?= htmlspecialchars($incident['root_cause']) ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $impactClass ?>">
                                                    <?= htmlspecialchars($incident['impact_level']) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET['company_id'])): ?>
                <div class="rounded-md bg-yellow-50 p-4 mb-6">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-yellow-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-yellow-800">No data found</h3>
                            <div class="mt-2 text-sm text-yellow-700">
                                <p>No incidents were found for the selected company and date range.</p>
                            </div>
                        </div>
                    </div>
                </div>
            <?php elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['company_id'])): ?>
                <div class="rounded-md bg-yellow-50 p-4 mb-6">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-yellow-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-yellow-800">No company selected</h3>
                            <div class="mt-2 text-sm text-yellow-700">
                                <p>Please select a company to generate the report.</p>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        // Toggle export dropdown
        function toggleExportDropdown() {
            const menu = document.getElementById('export-menu');
            const arrow = document.getElementById('export-arrow');
            
            // Toggle menu visibility
            menu.classList.toggle('hidden');
            
            // Rotate arrow
            arrow.classList.toggle('rotate-180');
            
            // Close when clicking outside
            if (!menu.classList.contains('hidden')) {
                document.addEventListener('click', closeExportDropdownOnClickOutside);
            } else {
                document.removeEventListener('click', closeExportDropdownOnClickOutside);
            }
        }
        
        // Close dropdown when clicking outside
        function closeExportDropdownOnClickOutside(event) {
            const dropdown = document.getElementById('export-dropdown');
            if (!dropdown.contains(event.target)) {
                const menu = document.getElementById('export-menu');
                const arrow = document.getElementById('export-arrow');
                
                menu.classList.add('hidden');
                arrow.classList.remove('rotate-180');
                document.removeEventListener('click', closeExportDropdownOnClickOutside);
            }
        }
        
        // Close dropdown when clicking on a menu item
        document.addEventListener('DOMContentLoaded', function() {
            const menuItems = document.querySelectorAll('#export-menu a');
            menuItems.forEach(item => {
                item.addEventListener('click', function() {
                    const menu = document.getElementById('export-menu');
                    const arrow = document.getElementById('export-arrow');
                    
                    menu.classList.add('hidden');
                    arrow.classList.remove('rotate-180');
                    document.removeEventListener('click', closeExportDropdownOnClickOutside);
                });
            });
        });
    </script>
</body>
</html>
