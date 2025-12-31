<?php
// Check if the current page is active
function isActive($page) {
    $current_page = basename($_SERVER['PHP_SELF']);
    return $current_page === $page ? 'text-blue-600 dark:text-blue-400 border-b-2 border-blue-600 dark:border-blue-400' : 'text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white';
}

function isMobileActive($page) {
    $current_page = basename($_SERVER['PHP_SELF']);
    return $current_page === $page ? 'bg-blue-50 dark:bg-blue-900/30 border-blue-600 dark:border-blue-400 text-blue-700 dark:text-blue-300' : 'border-transparent text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 hover:text-gray-900 dark:hover:text-white';
}
?>

<nav class="bg-white dark:bg-gray-800 sticky top-0 z-50 border-b border-gray-200 dark:border-gray-700 transition-colors duration-200" style="box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05);" x-data="{ mobileMenuOpen: false, darkMode: false }" x-init="
        // Initialize theme from localStorage or system preference
        darkMode = localStorage.getItem('theme') === 'dark' || (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches);
        if (darkMode) {
            document.documentElement.classList.add('dark');
        }
    " @theme-toggle.window="darkMode = !darkMode; localStorage.setItem('theme', darkMode ? 'dark' : 'light'); darkMode ? document.documentElement.classList.add('dark') : document.documentElement.classList.remove('dark')">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex items-center justify-between w-full">
                <!-- Logo -->
                <div class="flex items-center">
                    <a href="index.php" class="flex items-center space-x-2.5 group">
                            <img src="includes/logo1.png" alt="eTranzact Logo" class="h-24 w-auto object-contain">
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
                    <!-- Dark Mode Toggle -->
                    <button @click="$dispatch('theme-toggle')" type="button" class="inline-flex items-center justify-center p-2 rounded-lg text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-blue-500 dark:focus:ring-blue-400 transition-all duration-150" title="Toggle dark mode">
                        <span class="sr-only">Toggle dark mode</span>
                        <!-- Sun icon (visible in dark mode) -->
                        <svg x-show="darkMode" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display: none;">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path>
                        </svg>
                        <!-- Moon icon (visible in light mode) -->
                        <svg x-show="!darkMode" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path>
                        </svg>
                    </button>
                    
                    <!-- Report Button (Desktop) -->
                    <a href="report.php" class="hidden md:inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600 rounded-lg transition-colors duration-150 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:focus:ring-offset-gray-800">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        Report Incident
                    </a>
                    
                    
                    
                    <!-- Mobile menu button -->
                    <button @click="mobileMenuOpen = !mobileMenuOpen" type="button" class="md:hidden inline-flex items-center justify-center p-2 rounded-lg text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-blue-500 dark:focus:ring-blue-400 transition-colors duration-150">
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
         class="md:hidden border-t border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800"
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
            <a href="report.php" class="block mx-3 my-2 px-4 py-2.5 text-center text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600 rounded-lg transition-colors duration-150">
                Report Incident
            </a>
        </div>
    </div>
</nav>
