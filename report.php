<?php
require_once 'config.php';
session_start();

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Simple rate limiting (5 requests per minute)
$rateLimitKey = 'rate_limit_' . md5($_SERVER['REMOTE_ADDR']);
$currentTime = time();
$rateWindow = 60; // 1 minute
$maxRequests = 5;

if (isset($_SESSION[$rateLimitKey])) {
    list($count, $timestamp) = explode('|', $_SESSION[$rateLimitKey]);
    
    if (($currentTime - $timestamp) < $rateWindow) {
        if ($count >= $maxRequests) {
            die("Too many requests. Please try again later.");
        }
        $count++;
    } else {
        $count = 1;
    }
} else {
    $count = 1;
}

$_SESSION[$rateLimitKey] = "$count|$currentTime";

// Helper function to sanitize output
function sanitize($data) {
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

// Fetch services and companies for dropdowns
try {
    $services = $pdo->query("SELECT * FROM services ORDER BY service_name")->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch all companies and sort them with 'All' first, then alphabetically
    $allCompanies = $pdo->query("SELECT * FROM companies")->fetchAll(PDO::FETCH_ASSOC);
    
    // Separate 'All' from other companies
    $allOption = [];
    $otherCompanies = [];
    
    foreach ($allCompanies as $company) {
        if (strtolower($company['company_name']) === 'all') {
            $allOption[] = $company;
        } else {
            $otherCompanies[] = $company;
        }
    }
    
    // Sort other companies alphabetically
    usort($otherCompanies, function($a, $b) {
        return strcasecmp($a['company_name'], $b['company_name']);
    });
    
    // Combine 'All' with the rest of the companies
    $companies = array_merge($allOption, $otherCompanies);
} catch(PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    die("An error occurred. Please try again later.");
}

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Invalid request");
    }

    // Sanitize and validate inputs
    $user_name = trim(filter_var($_POST['user_name'] ?? '', FILTER_SANITIZE_STRING));
    $service_id = filter_var($_POST['service_id'] ?? null, FILTER_VALIDATE_INT);
    $impact_level = in_array($_POST['impact_level'] ?? '', ['Low', 'Medium', 'High', 'Critical']) ? $_POST['impact_level'] : 'Low';
    $root_cause = trim(filter_var($_POST['root_cause'] ?? '', FILTER_SANITIZE_STRING));
    $status = 'pending'; // Default status
    
    // Validate and deduplicate company IDs
    $company_ids = [];
    if (!empty($_POST['company_ids']) && is_array($_POST['company_ids'])) {
        $temp_ids = [];
        foreach ($_POST['company_ids'] as $company_id) {
            $company_id = filter_var($company_id, FILTER_VALIDATE_INT);
            if ($company_id && !in_array($company_id, $temp_ids)) {
                $temp_ids[] = $company_id;
            }
        }
        $company_ids = array_values(array_unique($temp_ids));
    }
    
    // Validation
    $errors = [];
    if (empty($user_name) || strlen($user_name) > 100) {
        $errors[] = "Please enter a valid name (max 100 characters).";
    }
    if (empty($service_id)) {
        $errors[] = "Please select a service.";
    }
    if (empty($company_ids)) {
        $errors[] = "Please select at least one company.";
    }
    if (strlen($root_cause) > 1000) {
        $errors[] = "Root cause is too long (max 1000 characters).";
    }
    
    if (!empty($errors)) {
        $error = implode(" ", $errors);
    } else {
        // Start transaction
        $pdo->beginTransaction();
        $success = false;
        
        try {
            // Create an incident for each selected company
            $inserted = [];
            foreach ($company_ids as $company_id) {
                // Check if this exact combination already exists to prevent duplicates
                $check_sql = "SELECT COUNT(*) as count FROM issues_reported 
                             WHERE user_name = :user_name 
                             AND service_id = :service_id 
                             AND company_id = :company_id 
                             AND root_cause = :root_cause 
                             AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)";
                
                $check_stmt = $pdo->prepare($check_sql);
                $check_stmt->execute([
                    ':user_name' => $user_name,
                    ':service_id' => $service_id,
                    ':company_id' => $company_id,
                    ':root_cause' => $root_cause
                ]);
                
                $exists = $check_stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;
                
                if (!$exists) {
                    $sql = "INSERT INTO issues_reported 
                            (user_name, service_id, company_id, root_cause, impact_level, status) 
                            VALUES (:user_name, :service_id, :company_id, :root_cause, :impact_level, :status)";
                    
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        ':user_name' => $user_name,
                        ':service_id' => $service_id,
                        ':company_id' => $company_id,
                        ':root_cause' => $root_cause,
                        ':impact_level' => $impact_level,
                        ':status' => $status
                    ]);
                    $issue_id = $pdo->lastInsertId();
                    
                    // Insert into downtime_incidents table
                    $downtime_sql = "INSERT INTO downtime_incidents 
                                    (issue_id, actual_start_time, is_planned, downtime_category)
                                    VALUES (:issue_id, NOW(), 0, 'Other')";
                    $downtime_stmt = $pdo->prepare($downtime_sql);
                    $downtime_stmt->execute([
                        ':issue_id' => $issue_id
                    ]);
                    
                    $inserted[] = $company_id;
                }
            }
            
            if (empty($inserted)) {
                throw new Exception("No new incidents were created. These incidents may have been recently reported.");
            }
            
            $pdo->commit();
            $success = true;
            
        } catch (Exception $e) {
            // Rollback on error
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = "Error: " . $e->getMessage();
        }
        
        if ($success) {
            $_SESSION['success'] = "Incident(s) reported successfully!";
            header("Location: report.php");
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Incident - eTranzact</title>
    
    <!-- Tailwind CSS v3.4.17 -->
    <script src="https://cdn.tailwindcss.com?plugins=forms,typography,aspect-ratio"></script>
    
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
        
        .form-input:focus {
            transition: all 0.15s ease;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Navbar -->
    <?php include 'includes/navbar.php'; ?>

    <!-- Main Content -->
    <main class="py-8">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white border border-gray-200 overflow-hidden rounded-xl" style="box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05), 0 1px 2px 0 rgba(0, 0, 0, 0.03);">
                <div class="px-6 py-5 sm:px-8 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">
                        Report New Incident
                    </h3>
                    <p class="text-sm text-gray-600 mt-1">
                        Fill in the details below to report a new downtime incident
                    </p>
                </div>
                
                <?php if (!empty($error)): ?>
                    <div class="bg-red-50 border-l-4 border-red-500 p-4 mx-6 mt-6 rounded-r-lg animate-slide-in">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-exclamation-circle text-red-500 text-xl"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium text-red-800"><?php echo htmlspecialchars($error); ?></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="bg-green-50 border-l-4 border-green-500 p-4 mx-6 mt-6 rounded-r-lg animate-slide-in">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-check-circle text-green-500 text-xl"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium text-green-800"><?php echo htmlspecialchars($_SESSION['success']); ?></p>
                            </div>
                        </div>
                    </div>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>

                <form action="report.php" method="POST" class="px-6 pb-8 pt-6 sm:px-8 space-y-6">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    
                    <!-- Reporter Name -->
                    <div>
                        <label for="user_name" class="block text-sm font-medium text-gray-700 mb-2">
                            Your Name <span class="text-red-600">*</span>
                        </label>
                        <input type="text" 
                               name="user_name" 
                               id="user_name" 
                               required
                               class="form-input block w-full border-gray-300 rounded-lg shadow-sm py-2.5 px-3.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-150"
                               placeholder="Enter your full name"
                               value="<?php echo isset($_POST['user_name']) ? sanitize($_POST['user_name']) : ''; ?>">
                    </div>

                    <!-- Service Selection -->
                    <div>
                        <label for="service_id" class="block text-sm font-medium text-gray-700">
                            Service Affected <span class="text-red-500">*</span>
                        </label>
                        <div class="mt-1 relative">
                            <button type="button" id="service-dropdown-button" class="relative w-full bg-white border border-gray-300 rounded-md shadow-sm pl-3 pr-10 py-2 text-left cursor-default focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                <span id="service-selected-text" class="block truncate">Select service...</span>
                                <span class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none">
                                    <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M10 3a1 1 0 01.707.293l3 3a1 1 0 01-1.414 1.414L10 5.414 7.707 7.707a1 1 0 01-1.414-1.414l3-3A1 1 0 0110 3zm-3.707 9.293a1 1 0 011.414 0L10 14.586l2.293-2.293a1 1 0 011.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd" />
                                    </svg>
                                </span>
                            </button>
                            <div id="service-dropdown" class="hidden absolute z-10 mt-1 w-full bg-white shadow-lg max-h-60 rounded-md py-1 text-base ring-1 ring-black ring-opacity-5 overflow-auto focus:outline-none sm:text-sm">
                                <?php foreach ($services as $service): ?>
                                    <div class="flex items-center px-4 py-2 hover:bg-gray-100">
                                        <input type="radio" 
                                               id="service-<?php echo $service['service_id']; ?>" 
                                               name="service_id" 
                                               value="<?php echo $service['service_id']; ?>"
                                               class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300"
                                               <?php echo (isset($_POST['service_id']) && $_POST['service_id'] == $service['service_id']) ? 'checked' : ''; ?>>
                                        <label for="service-<?php echo $service['service_id']; ?>" class="ml-3 block text-sm font-medium text-gray-700">
                                            <?php echo htmlspecialchars($service['service_name']); ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div id="selected-service" class="mt-2 flex flex-wrap gap-2">
                                <?php if (isset($_POST['service_id'])): 
                                    $selected_id = $_POST['service_id'];
                                    $selected_service = array_filter($services, function($service) use ($selected_id) {
                                        return $service['service_id'] == $selected_id;
                                    });
                                    $selected_service = reset($selected_service);
                                    if ($selected_service): ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                            <?php echo htmlspecialchars($selected_service['service_name']); ?>
                                            <input type="hidden" name="service_id" value="<?php echo $selected_service['service_id']; ?>">
                                        </span>
                                    <?php endif; 
                                endif; ?>
                            </div>
                        </div>
                        <p class="mt-1 text-sm text-gray-500">Select the affected service</p>
                    </div>

                    <!-- Companies Affected -->
                    <div>
                        <label for="company_ids" class="block text-sm font-medium text-gray-700">
                            Companies Affected <span class="text-red-500">*</span>
                        </label>
                        <div class="mt-1 relative">
                            <button type="button" id="company-dropdown-button" class="relative w-full bg-white border border-gray-300 rounded-md shadow-sm pl-3 pr-10 py-2 text-left cursor-default focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                <span id="company-selected-text" class="block truncate">Select companies...</span>
                                <span class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none">
                                    <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M10 3a1 1 0 01.707.293l3 3a1 1 0 01-1.414 1.414L10 5.414 7.707 7.707a1 1 0 01-1.414-1.414l3-3A1 1 0 0110 3zm-3.707 9.293a1 1 0 011.414 0L10 14.586l2.293-2.293a1 1 0 011.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd" />
                                    </svg>
                                </span>
                            </button>
                            <div id="company-dropdown" class="hidden absolute z-10 mt-1 w-full bg-white shadow-lg max-h-60 rounded-md py-1 text-base ring-1 ring-black ring-opacity-5 overflow-auto focus:outline-none sm:text-sm">
                                <?php foreach ($companies as $company): ?>
                                    <div class="flex items-center px-4 py-2 hover:bg-gray-100">
                                    <input type="checkbox" id="company-<?php echo strtolower($company['company_name']) === 'all' ? 'all' : $company['company_id']; ?>" 
                                           name="company_ids[]" 
                                           value="<?php echo strtolower($company['company_name']) === 'all' ? 'all' : $company['company_id']; ?>"
                                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                                           <?php 
                                           $isChecked = false;
                                           if (isset($_POST['company_ids'])) {
                                               if (strtolower($company['company_name']) === 'all') {
                                                   $isChecked = in_array('all', $_POST['company_ids']);
                                               } else {
                                                   $isChecked = in_array($company['company_id'], $_POST['company_ids']);
                                               }
                                           }
                                           echo $isChecked ? 'checked' : ''; 
                                           ?>>
                                    <label for="company-<?php echo strtolower($company['company_name']) === 'all' ? 'all' : $company['company_id']; ?>" class="ml-3 block text-sm font-medium text-gray-700">
                                        <?php echo htmlspecialchars($company['company_name']); ?>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <div id="selected-companies" class="mt-2 flex flex-wrap gap-2">
                                <?php if (isset($_POST['company_ids'])): ?>
                                    <?php foreach ($_POST['company_ids'] as $selected_id): 
                                        $selected_company = array_filter($companies, function($company) use ($selected_id) {
                                            return $company['company_id'] == $selected_id;
                                        });
                                        $selected_company = reset($selected_company);
                                        if ($selected_company): ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                <?php echo htmlspecialchars($selected_company['company_name']); ?>
                                                <input type="hidden" name="company_ids[]" value="<?php echo $selected_company['company_id']; ?>">
                                            </span>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <p class="mt-1 text-sm text-gray-500">Click to select multiple companies</p>
                    </div>

                    <!-- Impact Level -->
                    <div>
                        <span class="block text-sm font-medium text-gray-700">Impact Level <span class="text-red-500">*</span></span>
                        <div class="mt-2 space-y-2">
                            <div class="flex items-center">
                                <input id="impact-low" name="impact_level" type="radio" value="Low" 
                                    class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300"
                                    <?php echo (!isset($_POST['impact_level']) || (isset($_POST['impact_level']) && $_POST['impact_level'] === 'Low')) ? 'checked' : ''; ?>>
                                <label for="impact-low" class="ml-3 block text-sm font-medium text-gray-700">
                                    Low - Minor impact, workaround available
                                </label>
                            </div>
                            <div class="flex items-center">
                                <input id="impact-medium" name="impact_level" type="radio" value="Medium" 
                                    class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300"
                                    <?php echo (isset($_POST['impact_level']) && $_POST['impact_level'] === 'Medium') ? 'checked' : ''; ?>>
                                <label for="impact-medium" class="ml-3 block text-sm font-medium text-gray-700">
                                    Medium - Significant impact, workaround may exist
                                </label>
                            </div>
                            <div class="flex items-center">
                                <input id="impact-high" name="impact_level" type="radio" value="High" 
                                    class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300"
                                    <?php echo (isset($_POST['impact_level']) && $_POST['impact_level'] === 'High') ? 'checked' : ''; ?>>
                                <label for="impact-high" class="ml-3 block text-sm font-medium text-gray-700">
                                    High - Critical impact, no workaround available
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Root Cause -->
                    <div>
                        <label for="root_cause" class="block text-sm font-medium text-gray-700">
                            Root Cause (Optional)
                        </label>
                        <div class="mt-1">
                            <textarea id="root_cause" name="root_cause" rows="3" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border border-gray-300 rounded-md p-3"><?php echo isset($_POST['root_cause']) ? sanitize($_POST['root_cause']) : ''; ?></textarea>
                        </div>
                        <p class="mt-1 text-sm text-gray-500">Briefly describe what caused the issue (if known)</p>
                    </div>

                    <div class="pt-5">
                        <div class="flex justify-end">
                            <a href="index.php" class="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                Cancel
                            </a>
                            <button type="submit" class="ml-3 inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                <i class="fas fa-paper-plane mr-2"></i> Report Incident
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const dropdownButton = document.getElementById('company-dropdown-button');
            const dropdown = document.getElementById('company-dropdown');
            const selectedCompanies = document.getElementById('selected-companies');
            const companyCheckboxes = document.querySelectorAll('input[name="company_ids[]"]');
            
            // Toggle dropdown
            dropdownButton.addEventListener('click', function() {
                dropdown.classList.toggle('hidden');
            });
            
            // Close dropdown when clicking outside
            document.addEventListener('click', function(event) {
                if (!dropdownButton.contains(event.target) && !dropdown.contains(event.target)) {
                    dropdown.classList.add('hidden');
                }
            });
            
            // Handle checkbox changes
            companyCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    if (this.value === 'all') {
                        // When "All" is checked, check all other checkboxes
                        // When unchecked, uncheck all other checkboxes
                        const isChecked = this.checked;
                        companyCheckboxes.forEach(cb => {
                            if (cb !== this) {
                                cb.checked = isChecked;
                            }
                        });
                    } else {
                        // For other checkboxes, uncheck "All" if any is unchecked
                        // Or check "All" if all others are checked
                        const allCheckbox = document.querySelector('input[value="all"]');
                        if (allCheckbox) {
                            if (!this.checked) {
                                allCheckbox.checked = false;
                            } else {
                                // Check if all other checkboxes are checked
                                const allChecked = Array.from(companyCheckboxes)
                                    .filter(cb => cb.value !== 'all')
                                    .every(cb => cb.checked);
                                allCheckbox.checked = allChecked;
                            }
                        }
                    }
                    updateSelectedCompanies();
                });
            });
            
            function updateSelectedCompanies() {
                const selected = [];
                const selectedNames = [];
                
                document.querySelectorAll('input[name="company_ids[]"]:checked').forEach(checkbox => {
                    selected.push({
                        id: checkbox.value,
                        name: checkbox.nextElementSibling.textContent.trim()
                    });
                    selectedNames.push(checkbox.nextElementSibling.textContent.trim());
                });
                
                // Update the selected text
                const selectedText = selectedNames.length > 0 
                    ? selectedNames.join(', ') 
                    : 'Select companies...';
                document.getElementById('company-selected-text').textContent = selectedText;
                
                // Update the hidden inputs in the selected-companies container
                selectedCompanies.innerHTML = selected.map(company => `
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                        ${company.name}
                        <input type="hidden" name="company_ids[]" value="${company.id}">
                    </span>
                `).join('');
            }
            
            // Initialize with any pre-selected companies
            updateSelectedCompanies();
            
            // Handle 'All' checkbox on page load
            const allCheckbox = document.querySelector('input[value="all"]');
            if (allCheckbox && allCheckbox.checked) {
                // Check all checkboxes when 'All' is checked on page load
                companyCheckboxes.forEach(checkbox => {
                    checkbox.checked = true;
                });
                updateSelectedCompanies();
            }
            
            // Service dropdown functionality
            const serviceDropdownButton = document.getElementById('service-dropdown-button');
            const serviceDropdown = document.getElementById('service-dropdown');
            const serviceRadios = document.querySelectorAll('input[name="service_id"]');
            
            serviceDropdownButton.addEventListener('click', function() {
                serviceDropdown.classList.toggle('hidden');
            });
            
            // Close dropdown when clicking outside
            document.addEventListener('click', function(event) {
                if (!serviceDropdownButton.contains(event.target) && !serviceDropdown.contains(event.target)) {
                    serviceDropdown.classList.add('hidden');
                }
            });
            
            // Update selected service when radio changes
            document.addEventListener('change', function(event) {
                if (event.target.matches('input[name="service_id"]')) {
                    updateSelectedService();
                    serviceDropdown.classList.add('hidden');
                }
            });
            
            // Initialize selected service on page load
            updateSelectedService();
            
            // Update selected service display
            function updateSelectedService() {
                const selectedServiceDiv = document.getElementById('selected-service');
                const selectedRadio = document.querySelector('input[name="service_id"]:checked');
                
                if (selectedRadio) {
                    const serviceName = selectedRadio.nextElementSibling.textContent.trim();
                    document.getElementById('service-selected-text').textContent = serviceName;
                    
                    // Update the selected service display
                    selectedServiceDiv.innerHTML = `
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                            ${serviceName}
                            <input type="hidden" name="service_id" value="${selectedRadio.value}">
                        </span>
                    `;
                } else {
                    document.getElementById('service-selected-text').textContent = 'Select service...';
                    selectedServiceDiv.innerHTML = '';
                }
            }
        });
    </script>
</body>
</html>
