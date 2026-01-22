<?php
require_once 'config.php';
require_once 'auth.php';
session_start();

// Require authentication for all pages
Auth::requireLogin();

$user = Auth::getUser();
$error = $_SESSION['error'] ?? '';
$success_message = $_SESSION['success'] ?? '';
unset($_SESSION['error'], $_SESSION['success']);

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
function sanitize($data)
{
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

// Fetch services and companies for dropdowns
try {
    $services = $pdo->query("SELECT * FROM services ORDER BY service_name")->fetchAll(PDO::FETCH_ASSOC);

    // Fetch all companies and sort them with 'All' first, then alphabetically
    $allCompanies = $pdo->query("SELECT * FROM companies")->fetchAll(PDO::FETCH_ASSOC);

    $allOption = [];
    $otherCompanies = [];

    foreach ($allCompanies as $company) {
        if (strtolower($company['company_name']) === 'all') {
            $allOption[] = $company;
        } else {
            $otherCompanies[] = $company;
        }
    }

    usort($otherCompanies, function ($a, $b) {
        return strcasecmp($a['company_name'], $b['company_name']);
    });

    $companies = array_merge($allOption, $otherCompanies);
} catch (PDOException $e) {
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
    $service_id = filter_var($_POST['service_id'] ?? null, FILTER_VALIDATE_INT);
    $component_id = filter_var($_POST['component_id'] ?? null, FILTER_VALIDATE_INT);
    $incident_type_id = filter_var($_POST['incident_type_id'] ?? null, FILTER_VALIDATE_INT);
    $impact_level = in_array($_POST['impact_level'] ?? '', ['Low', 'Medium', 'High', 'Critical']) ? $_POST['impact_level'] : 'Low';
    $root_cause = trim(filter_var($_POST['root_cause'] ?? '', FILTER_SANITIZE_STRING));
    $status = 'pending';
    $reported_by = $user['user_id'];
    $attachment_path = null;

    // Handle file upload
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['attachment']['tmp_name'];
        $fileName = $_FILES['attachment']['name'];
        $fileSize = $_FILES['attachment']['size'];
        $fileType = $_FILES['attachment']['type'];
        $fileNameCmps = explode(".", $fileName);
        $fileExtension = strtolower(end($fileNameCmps));

        // Sanitize file name
        $newFileName = md5(time() . $fileName) . '.' . $fileExtension;

        // Allowed extensions
        $allowedfileExtensions = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx', 'txt'];

        if (in_array($fileExtension, $allowedfileExtensions)) {
            // Directory where uploaded file will be saved
            $uploadFileDir = './uploads/';
            $dest_path = $uploadFileDir . $newFileName;

            if (move_uploaded_file($fileTmpPath, $dest_path)) {
                $attachment_path = $dest_path;
            } else {
                $errors[] = "There was an error moving the uploaded file.";
            }
        } else {
            $errors[] = "Upload failed. Allowed types: " . implode(',', $allowedfileExtensions);
        }
    }

    // Validate and deduplicate company IDs
    $company_ids = [];
    if (!empty($_POST['company_ids']) && is_array($_POST['company_ids'])) {
        $temp_ids = [];
        foreach ($_POST['company_ids'] as $company_id) {
            if ($company_id === 'all') {
                $comp_stmt = $pdo->query("SELECT company_id FROM companies WHERE LOWER(company_name) != 'all'");
                $temp_ids = $comp_stmt->fetchAll(PDO::FETCH_COLUMN);
                break;
            }
            $cid = filter_var($company_id, FILTER_VALIDATE_INT);
            if ($cid && !in_array($cid, $temp_ids)) {
                $temp_ids[] = $cid;
            }
        }
        $company_ids = $temp_ids;
    }

    // Validation
    $errors = [];
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
        $pdo->beginTransaction();
        try {
            // 1. Create the main incident
            $sql = "INSERT INTO incidents 
                    (service_id, component_id, incident_type_id, impact_level, root_cause, attachment_path, status, reported_by) 
                    VALUES (:service_id, :component_id, :incident_type_id, :impact_level, :root_cause, :attachment_path, :status, :reported_by)";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':service_id' => $service_id,
                ':component_id' => $component_id ?: null,
                ':incident_type_id' => $incident_type_id ?: null,
                ':impact_level' => $impact_level,
                ':root_cause' => $root_cause,
                ':attachment_path' => $attachment_path,
                ':status' => $status,
                ':reported_by' => $reported_by
            ]);

            $incident_id = $pdo->lastInsertId();

            // 2. Link affected companies
            $link_sql = "INSERT INTO incident_affected_companies (incident_id, company_id) VALUES (?, ?)";
            $link_stmt = $pdo->prepare($link_sql);
            foreach ($company_ids as $cid) {
                $link_stmt->execute([$incident_id, $cid]);
            }

            // 3. Create record in downtime_incidents
            $downtime_sql = "INSERT INTO downtime_incidents (incident_id, actual_start_time) VALUES (?, NOW())";
            $pdo->prepare($downtime_sql)->execute([$incident_id]);

            $pdo->commit();

            $auth = new Auth($pdo);
            $auth->logActivity($user['user_id'], 'incident.report', "User reported incident ID: $incident_id");

            $_SESSION['success'] = "Incident reported successfully!";
            header("Location: incidents.php");
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Incident reporting error: " . $e->getMessage());
            $error = "An error occurred while reporting the incident: " . $e->getMessage();
        }
    }
    $_SESSION['error'] = $error;
    header("Location: report.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>eTranzact - Report Incident</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
        }
    </style>
</head>

<body class="bg-gray-100 min-h-screen">
    <?php include 'includes/navbar.php'; ?>

    <header class="bg-white shadow">
        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
            <h1 class="text-3xl font-bold text-gray-900">Report Incident</h1>
        </div>
    </header>

    <main class="py-10">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white shadow-xl rounded-lg overflow-hidden">
                <div class="bg-blue-600 px-6 py-4">
                    <h2 class="text-xl font-semibold text-white">Incident Details</h2>
                    <p class="text-blue-100 text-sm">Please provide information about the service interruption.</p>
                </div>

                <?php if ($error): ?>
                    <div class="bg-red-50 border-l-4 border-red-400 p-4 mx-6 mt-6">
                        <div class="flex">
                            <div class="flex-shrink-0"><i class="fas fa-exclamation-circle text-red-400"></i></div>
                            <div class="ml-3">
                                <p class="text-sm text-red-700"><?php echo sanitize($error); ?></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($success_message): ?>
                    <div class="bg-green-50 border-l-4 border-green-400 p-4 mx-6 mt-6">
                        <div class="flex">
                            <div class="flex-shrink-0"><i class="fas fa-check-circle text-green-400"></i></div>
                            <div class="ml-3">
                                <p class="text-sm text-green-700"><?php echo sanitize($success_message); ?></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <form action="report.php" method="POST" enctype="multipart/form-data" class="p-6 space-y-6">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Reported By (Auto-captured) -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Reported By</label>
                            <div class="mt-1 relative rounded-md shadow-sm">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-user-circle text-gray-400"></i>
                                </div>
                                <input type="text" value="<?php echo sanitize($user['full_name']); ?>" disabled
                                    class="block w-full pl-10 bg-gray-50 border border-gray-300 rounded-md shadow-sm py-2 px-3 text-gray-500 sm:text-sm cursor-not-allowed">
                            </div>
                        </div>

                        <!-- Affected Service -->
                        <div class="relative">
                            <label class="block text-sm font-medium text-gray-700">Affected Service <span
                                    class="text-red-500">*</span></label>
                            <button type="button" id="service-dropdown-button"
                                class="mt-1 relative w-full bg-white border border-gray-300 rounded-md shadow-sm pl-3 pr-10 py-2 text-left cursor-default focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                <span id="service-selected-text" class="block truncate">Select service...</span>
                                <span class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none">
                                    <i class="fas fa-chevron-down text-gray-400"></i>
                                </span>
                            </button>
                            <div id="service-dropdown"
                                class="hidden absolute z-10 mt-1 w-full bg-white shadow-lg max-h-60 rounded-md py-1 text-base ring-1 ring-black ring-opacity-5 overflow-auto focus:outline-none sm:text-sm">
                                <?php foreach ($services as $service): ?>
                                    <div class="px-4 py-2 hover:bg-gray-100 cursor-pointer"
                                        onclick="selectService(<?php echo $service['service_id']; ?>, '<?php echo sanitize($service['service_name']); ?>')">
                                        <input type="radio" name="service_id" value="<?php echo $service['service_id']; ?>"
                                            class="hidden">
                                        <span><?php echo sanitize($service['service_name']); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Dynamic Fields -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 hidden" id="dynamic-fields">
                        <div>
                            <label for="component_id" class="block text-sm font-medium text-gray-700">Service
                                Component</label>
                            <select name="component_id" id="component_id"
                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                <option value="">Select component...</option>
                            </select>
                        </div>
                        <div>
                            <label for="incident_type_id" class="block text-sm font-medium text-gray-700">Incident
                                Type</label>
                            <select name="incident_type_id" id="incident_type_id"
                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                <option value="">Select type...</option>
                            </select>
                        </div>
                    </div>

                    <!-- Companies Afffected -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Companies Affected <span
                                class="text-red-500">*</span></label>
                        <div class="mt-1 relative">
                            <button type="button" id="company-dropdown-button"
                                class="relative w-full bg-white border border-gray-300 rounded-md shadow-sm pl-3 pr-10 py-2 text-left cursor-default focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                <span id="company-selected-text" class="block truncate text-gray-400">Select
                                    companies...</span>
                                <span class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none"><i
                                        class="fas fa-chevron-down text-gray-400"></i></span>
                            </button>
                            <div id="company-dropdown"
                                class="hidden absolute z-10 mt-1 w-full bg-white shadow-lg max-h-60 rounded-md py-1 text-base ring-1 ring-black ring-opacity-5 overflow-auto focus:outline-none sm:text-sm">
                                <?php foreach ($companies as $company): ?>
                                    <div class="flex items-center px-4 py-2 hover:bg-gray-100">
                                        <input type="checkbox" id="company-<?php echo $company['company_id']; ?>"
                                            name="company_ids[]"
                                            value="<?php echo strtolower($company['company_name']) === 'all' ? 'all' : $company['company_id']; ?>"
                                            class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                        <label for="company-<?php echo $company['company_id']; ?>"
                                            class="ml-3 block text-sm font-medium text-gray-700"><?php echo sanitize($company['company_name']); ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div id="selected-companies-tags" class="mt-2 flex flex-wrap gap-2"></div>
                    </div>

                    <!-- File Attachment -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Attachment (Optional)</label>
                        <div id="drop-zone"
                            class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md hover:border-blue-400 transition-colors cursor-pointer">
                            <div class="space-y-1 text-center">
                                <i class="fas fa-paperclip text-gray-400 text-3xl mb-3"></i>
                                <div class="flex text-sm text-gray-600">
                                    <label for="attachment"
                                        class="relative cursor-pointer bg-white rounded-md font-medium text-blue-600 hover:text-blue-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-blue-500">
                                        <span>Upload a file</span>
                                        <input id="attachment" name="attachment" type="file" class="sr-only">
                                    </label>
                                    <p class="pl-1">or drag and drop</p>
                                </div>
                                <p class="text-xs text-gray-500">PNG, JPG, PDF, DOC, TXT up to 5MB</p>
                            </div>
                        </div>
                        <div id="file-preview" class="mt-2 hidden">
                            <div class="flex items-center p-2 bg-blue-50 rounded-md border border-blue-100">
                                <div id="preview-icon" class="flex-shrink-0 mr-3 text-blue-500">
                                    <i class="fas fa-file"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p id="preview-name" class="text-sm font-medium text-blue-900 truncate"></p>
                                    <p id="preview-size" class="text-xs text-blue-500"></p>
                                </div>
                                <button type="button" id="remove-file" class="ml-3 text-red-400 hover:text-red-500">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Impact Level -->
                    <div>
                        <span class="block text-sm font-medium text-gray-700 mb-2">Impact Level <span
                                class="text-red-500">*</span></span>
                        <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                            <?php foreach (['Low', 'Medium', 'High', 'Critical'] as $level): ?>
                                <label
                                    class="relative flex items-center p-3 rounded-lg border border-gray-200 cursor-pointer hover:bg-gray-50 focus-within:ring-2 focus-within:ring-blue-500">
                                    <input type="radio" name="impact_level" value="<?php echo $level; ?>"
                                        class="h-4 w-4 text-blue-600 border-gray-300" <?php echo ($level === 'Low') ? 'checked' : ''; ?>>
                                    <span class="ml-3 text-sm font-medium text-gray-700"><?php echo $level; ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Root Cause -->
                    <div>
                        <label for="root_cause" class="block text-sm font-medium text-gray-700">Root Cause
                            (Optional)</label>
                        <textarea id="root_cause" name="root_cause" rows="4"
                            class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                            placeholder="Briefly describe what caused the issue..."></textarea>
                    </div>

                    <div class="flex justify-end pt-4 space-x-3">
                        <a href="incidents.php"
                            class="bg-gray-100 py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-200">Cancel</a>
                        <button type="submit"
                            class="bg-blue-600 py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <i class="fas fa-paper-plane mr-2"></i> Submit Incident
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // File Upload Preview and Drag-and-Drop
            const dropZone = document.getElementById('drop-zone');
            const fileInput = document.getElementById('attachment');
            const filePreview = document.getElementById('file-preview');
            const previewName = document.getElementById('preview-name');
            const previewSize = document.getElementById('preview-size');
            const previewIcon = document.getElementById('preview-icon');
            const removeBtn = document.getElementById('remove-file');

            dropZone.addEventListener('click', () => fileInput.click());

            dropZone.addEventListener('dragover', (e) => {
                e.preventDefault();
                dropZone.classList.add('border-blue-400', 'bg-blue-50');
            });

            ['dragleave', 'drop'].forEach(eventName => {
                dropZone.addEventListener(eventName, () => {
                    dropZone.classList.remove('border-blue-400', 'bg-blue-50');
                });
            });

            dropZone.addEventListener('drop', (e) => {
                e.preventDefault();
                const files = e.dataTransfer.files;
                if (files.length) {
                    fileInput.files = files;
                    updatePreview(files[0]);
                }
            });

            fileInput.addEventListener('change', () => {
                if (fileInput.files.length) {
                    updatePreview(fileInput.files[0]);
                }
            });

            removeBtn.addEventListener('click', () => {
                fileInput.value = '';
                filePreview.classList.add('hidden');
                dropZone.classList.remove('hidden');
            });

            function updatePreview(file) {
                previewName.textContent = file.name;
                previewSize.textContent = (file.size / 1024 / 1024).toFixed(2) + ' MB';

                // Update icon based on type
                if (file.type.startsWith('image/')) {
                    previewIcon.innerHTML = '<i class="fas fa-image"></i>';
                } else if (file.type === 'application/pdf') {
                    previewIcon.innerHTML = '<i class="fas fa-file-pdf"></i>';
                } else if (file.type.includes('word') || file.type.includes('officedocument')) {
                    previewIcon.innerHTML = '<i class="fas fa-file-word"></i>';
                } else {
                    previewIcon.innerHTML = '<i class="fas fa-file-alt"></i>';
                }

                filePreview.classList.remove('hidden');
                dropZone.classList.add('hidden');
            }

            // Service Dropdown
            const serviceBtn = document.getElementById('service-dropdown-button');
            const serviceMenu = document.getElementById('service-dropdown');

            serviceBtn.addEventListener('click', () => serviceMenu.classList.toggle('hidden'));

            window.selectService = function (id, name) {
                document.getElementById('service-selected-text').textContent = name;
                document.querySelector(`input[name="service_id"][value="${id}"]`).checked = true;
                serviceMenu.classList.add('hidden');
                document.getElementById('dynamic-fields').classList.remove('hidden');
                fetchServiceData(id);
            };

            function fetchServiceData(id) {
                const compSel = document.getElementById('component_id');
                const typeSel = document.getElementById('incident_type_id');
                compSel.innerHTML = '<option value="">Loading...</option>';
                typeSel.innerHTML = '<option value="">Loading...</option>';

                fetch(`api/get_service_data.php?service_id=${id}`)
                    .then(r => r.json())
                    .then(data => {
                        compSel.innerHTML = '<option value="">Select component...</option>';
                        data.components.forEach(c => compSel.innerHTML += `<option value="${c.component_id}">${c.name}</option>`);
                        typeSel.innerHTML = '<option value="">Select type...</option>';
                        data.types.forEach(t => typeSel.innerHTML += `<option value="${t.type_id}">${t.name}</option>`);
                    });
            }

            // Company Dropdown
            const companyBtn = document.getElementById('company-dropdown-button');
            const companyMenu = document.getElementById('company-dropdown');
            const companyTags = document.getElementById('selected-companies-tags');

            companyBtn.addEventListener('click', () => companyMenu.classList.toggle('hidden'));

            document.querySelectorAll('input[name="company_ids[]"]').forEach(cb => {
                cb.addEventListener('change', function () {
                    if (this.value === 'all' && this.checked) {
                        document.querySelectorAll('input[name="company_ids[]"]').forEach(c => { if (c.value !== 'all') c.checked = true; });
                    } else if (this.value === 'all' && !this.checked) {
                        document.querySelectorAll('input[name="company_ids[]"]').forEach(c => c.checked = false);
                    }
                    updateTags();
                });
            });

            function updateTags() {
                const checked = Array.from(document.querySelectorAll('input[name="company_ids[]"]:checked'));
                const names = checked.map(c => c.nextElementSibling.textContent.trim());
                document.getElementById('company-selected-text').textContent = names.length ? (names.length + ' companies selected') : 'Select companies...';
                companyTags.innerHTML = checked.map(c => `
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                        ${c.nextElementSibling.textContent.trim()}
                    </span>
                `).join('');
            }

            // Close dropdowns
            document.addEventListener('click', (e) => {
                if (!serviceBtn.contains(e.target) && !serviceMenu.contains(e.target)) serviceMenu.classList.add('hidden');
                if (!companyBtn.contains(e.target) && !companyMenu.contains(e.target)) companyMenu.classList.add('hidden');
            });
        });
    </script>
</body>

</html>