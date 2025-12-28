<?php
// Check if the current page is active
function isActive($page) {
    $current_page = basename($_SERVER['PHP_SELF']);
    return $current_page === $page ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-600 hover:text-gray-900';
}

function isMobileActive($page) {
    $current_page = basename($_SERVER['PHP_SELF']);
    return $current_page === $page ? 'bg-blue-50 border-blue-600 text-blue-700' : 'border-transparent text-gray-600 hover:bg-gray-50 hover:text-gray-900';
}
?>

<nav class="bg-white sticky top-0 z-50 border-b border-gray-200" style="box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05);" x-data="{ mobileMenuOpen: false }">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex items-center justify-between w-full">
                <!-- Logo -->
                <div class="flex items-center">
                    <a href="index.php" class="flex items-center space-x-2.5 group">
                        <div class="w-9 h-9 bg-blue-600 rounded-lg flex items-center justify-center transition-all duration-200 group-hover:bg-blue-700">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                            </svg>
                        </div>
                        <span class="text-lg font-semibold text-gray-900">eTranzact</span>
                    </a>
                </div>
                
                <!-- Desktop Navigation -->
                <div class="hidden md:flex space-x-1 absolute left-1/2 transform -translate-x-1/2">
                    <a href="index.php" class="<?php echo isActive('index.php'); ?> px-4 py-2 text-sm font-medium transition-colors duration-150">
                        Dashboard
                    </a>
                    <a href="incidents.php" class="<?php echo isActive('incidents.php'); ?> px-4 py-2 text-sm font-medium transition-colors duration-150">
                        Incidents
                    </a>
                    <a href="sla_report.php" class="<?php echo isActive('sla_report.php'); ?> px-4 py-2 text-sm font-medium transition-colors duration-150">
                        SLA
                    </a>
                    <a href="analytics.php" class="<?php echo isActive('analytics.php'); ?> px-4 py-2 text-sm font-medium transition-colors duration-150">
                        Analytics
                    </a>
                </div>
                
                <!-- Right Side Actions -->
                <div class="flex items-center space-x-3">
                    <!-- Report Button (Desktop) -->
                    <a href="report.php" class="hidden md:inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors duration-150 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        Report Incident
                    </a>
                    
                    <!-- Mobile menu button -->
                    <button @click="mobileMenuOpen = !mobileMenuOpen" type="button" class="md:hidden inline-flex items-center justify-center p-2 rounded-lg text-gray-600 hover:text-gray-900 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-blue-500 transition-colors duration-150">
                        <span class="sr-only">Open main menu</span>
                        <svg x-show="!mobileMenuOpen" class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                        <svg x-show="mobileMenuOpen" class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display: none;">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Mobile menu -->
    <div x-show="mobileMenuOpen" 
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 -translate-y-1"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 -translate-y-1"
         class="md:hidden border-t border-gray-200 bg-white"
         style="display: none;">
        <div class="px-2 pt-2 pb-3 space-y-1">
            <a href="index.php" class="<?php echo isMobileActive('index.php'); ?> block pl-3 pr-4 py-2.5 border-l-4 text-sm font-medium transition-colors duration-150">
                Dashboard
            </a>
            <a href="incidents.php" class="<?php echo isMobileActive('incidents.php'); ?> block pl-3 pr-4 py-2.5 border-l-4 text-sm font-medium transition-colors duration-150">
                Incidents
            </a>
            <a href="sla_report.php" class="<?php echo isMobileActive('sla_report.php'); ?> block pl-3 pr-4 py-2.5 border-l-4 text-sm font-medium transition-colors duration-150">
                SLA
            </a>
            <a href="analytics.php" class="<?php echo isMobileActive('analytics.php'); ?> block pl-3 pr-4 py-2.5 border-l-4 text-sm font-medium transition-colors duration-150">
                Analytics
            </a>
            <a href="report.php" class="block mx-3 my-2 px-4 py-2.5 text-center text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors duration-150">
                Report Incident
            </a>
        </div>
    </div>
</nav>
