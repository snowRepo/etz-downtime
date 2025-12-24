<?php
// Check if the current page is active
function isActive($page) {
    $current_page = basename($_SERVER['PHP_SELF']);
    return $current_page === $page ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-700 hover:text-blue-600';
}
?>

<nav class="bg-white shadow-lg">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex items-center justify-between w-full">
                <a href="index.php" class="flex-shrink-0 text-xl font-bold text-blue-600">eTranzact</a>
                
                <div class="hidden sm:flex space-x-8 absolute left-1/2 transform -translate-x-1/2">
                    <a href="index.php" class="<?php echo isActive('index.php'); ?> px-3 py-2 text-sm font-medium">Dashboard</a>
                    <a href="incidents.php" class="<?php echo isActive('incidents.php'); ?> px-3 py-2 text-sm font-medium">Incidents</a>
                    <a href="sla_report.php" class="<?php echo isActive('sla_report.php'); ?> px-3 py-2 text-sm font-medium">SLA</a>
                    <a href="analytics.php" class="<?php echo isActive('analytics.php'); ?> px-3 py-2 text-sm font-medium">Analytics</a>
                </div>
                <div class="flex items-center ml-auto">
                    <a href="report.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <i class="fas fa-plus-circle mr-2"></i> Report Incident
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Mobile menu, show/hide based on menu state. -->
    <div class="sm:hidden" id="mobile-menu">
        <div class="pt-2 pb-3 space-y-1">
            <a href="index.php" class="<?php echo isActive('index.php'); ?> block pl-3 pr-4 py-2 border-l-4 text-base font-medium">Dashboard</a>
            <a href="incidents.php" class="<?php echo isActive('incidents.php'); ?> block pl-3 pr-4 py-2 border-l-4 text-base font-medium">Incidents</a>
            <a href="sla_report.php" class="<?php echo isActive('sla_report.php'); ?> block pl-3 pr-4 py-2 border-l-4 text-base font-medium">SLA</a>
        </div>
    </div>
</nav>
