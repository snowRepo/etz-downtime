<?php
// Check if the current page is active
function isActive($page)
{
    $current_page = basename($_SERVER['PHP_SELF']);
    return $current_page === $page ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-700 hover:text-blue-600';
}

// Get current user info
$user = Auth::getUser();
$isAdmin = Auth::isAdmin();
?>

<nav class="bg-white shadow-lg">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex items-center justify-between w-full">
                <a href="index.php" class="flex-shrink-0 text-xl font-bold text-blue-600">eTranzact</a>

                <div class="hidden sm:flex space-x-8 absolute left-1/2 transform -translate-x-1/2">
                    <a href="index.php"
                        class="<?php echo isActive('index.php'); ?> px-3 py-2 text-sm font-medium">Dashboard</a>
                    <a href="incidents.php"
                        class="<?php echo isActive('incidents.php'); ?> px-3 py-2 text-sm font-medium">Incidents</a>
                    <a href="sla_report.php"
                        class="<?php echo isActive('sla_report.php'); ?> px-3 py-2 text-sm font-medium">SLA</a>
                    <a href="analytics.php"
                        class="<?php echo isActive('analytics.php'); ?> px-3 py-2 text-sm font-medium">Analytics</a>
                    <?php if ($isAdmin): ?>
                        <a href="users.php"
                            class="<?php echo isActive('users.php'); ?> px-3 py-2 text-sm font-medium">Users</a>
                        <a href="activity_logs.php"
                            class="<?php echo isActive('activity_logs.php'); ?> px-3 py-2 text-sm font-medium">Activity</a>
                    <?php endif; ?>
                </div>
                <div class="flex items-center ml-auto space-x-4">
                    <a href="report.php"
                        class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <i class="fas fa-plus-circle mr-2"></i> Report Incident
                    </a>

                    <?php if ($user): ?>
                        <!-- User Profile Dropdown -->
                        <div class="relative">
                            <button id="user-menu-button"
                                class="flex items-center text-sm rounded-full focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                <div class="h-8 w-8 rounded-full bg-blue-100 flex items-center justify-center">
                                    <span class="text-blue-800 text-sm font-medium">
                                        <?php
                                        $names = explode(' ', $user['full_name']);
                                        $initials = strtoupper(substr($names[0], 0, 1) . (isset($names[1]) ? substr($names[1], 0, 1) : ''));
                                        echo $initials;
                                        ?>
                                    </span>
                                </div>
                                <span class="ml-2 text-sm font-medium text-gray-700 hidden md:inline-block">
                                    <?php echo ucfirst(htmlspecialchars($user['role'])); ?>
                                </span>
                                <i class="ml-1 fas fa-chevron-down text-gray-400 text-xs"></i>
                            </button>

                            <div id="user-dropdown"
                                class="hidden origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 focus:outline-none z-50">
                                <div class="py-1">
                                    <div class="px-4 py-2 text-xs text-gray-500 border-b">
                                        <div><?php echo htmlspecialchars($user['full_name']); ?></div>
                                        <div class="text-gray-400"><?php echo htmlspecialchars($user['email']); ?></div>
                                        <div class="mt-1">
                                            <span
                                                class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-<?php echo $user['role'] === 'admin' ? 'purple' : 'blue'; ?>-100 text-<?php echo $user['role'] === 'admin' ? 'purple' : 'blue'; ?>-800">
                                                <?php echo ucfirst($user['role']); ?>
                                                <?php echo $user['role'] === 'admin' ? ' User' : ''; ?>
                                            </span>
                                        </div>
                                    </div>
                                    <a href="change_password.php"
                                        class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 border-b border-gray-100">
                                        <i class="fas fa-key mr-2 text-gray-400"></i>Change Password
                                    </a>
                                    <a href="logout.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                        <i class="fas fa-sign-out-alt mr-2"></i>Sign out
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Mobile menu, show/hide based on menu state. -->
    <div class="sm:hidden" id="mobile-menu">
        <div class="pt-2 pb-3 space-y-1">
            <a href="index.php"
                class="<?php echo isActive('index.php'); ?> block pl-3 pr-4 py-2 border-l-4 text-base font-medium">Dashboard</a>
            <a href="incidents.php"
                class="<?php echo isActive('incidents.php'); ?> block pl-3 pr-4 py-2 border-l-4 text-base font-medium">Incidents</a>
            <a href="sla_report.php"
                class="<?php echo isActive('sla_report.php'); ?> block pl-3 pr-4 py-2 border-l-4 text-base font-medium">SLA</a>
            <?php if ($isAdmin): ?>
                <a href="users.php"
                    class="<?php echo isActive('users.php'); ?> block pl-3 pr-4 py-2 border-l-4 text-base font-medium">Users</a>
                <a href="activity_logs.php"
                    class="<?php echo isActive('activity_logs.php'); ?> block pl-3 pr-4 py-2 border-l-4 text-base font-medium">Activity</a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<script>
    // User dropdown functionality
    document.addEventListener('DOMContentLoaded', function () {
        const userMenuButton = document.getElementById('user-menu-button');
        const userDropdown = document.getElementById('user-dropdown');

        if (userMenuButton && userDropdown) {
            userMenuButton.addEventListener('click', function (e) {
                e.stopPropagation();
                userDropdown.classList.toggle('hidden');
            });

            // Close dropdown when clicking outside
            document.addEventListener('click', function () {
                userDropdown.classList.add('hidden');
            });

            // Prevent dropdown from closing when clicking inside it
            userDropdown.addEventListener('click', function (e) {
                e.stopPropagation();
            });
        }
    });
</script>