<?php
require_once 'config.php';
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loading Demo - eTranzact</title>
    
    <!-- Tailwind CSS v3.4.17 -->
    <script src="https://cdn.tailwindcss.com?plugins=forms,typography,aspect-ratio"></script>
    <script>
        tailwind.config = {
            darkMode: 'class'
        }
    </script>
    
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
    </style>
</head>
<body class="bg-gray-50 dark:bg-gray-900">
    <!-- Navbar -->
    <?php include 'includes/navbar.php'; ?>
    
    <!-- Loading Overlay -->
    <?php include 'includes/loading.php'; ?>

    <!-- Main Content -->
    <main class="py-8">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Page Header -->
            <div class="mb-8">
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Loading Spinner Demo</h1>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Test all the different loading states and animations</p>
            </div>
            
            <!-- Demo Cards -->
            <div class="space-y-6">
                
                <!-- Automatic Loading -->
                <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-6 shadow-sm">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                        <i class="fas fa-magic text-blue-600 mr-2"></i>
                        Automatic Loading
                    </h2>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                        These actions automatically trigger the loading overlay:
                    </p>
                    <div class="space-y-3">
                        <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                            <span class="text-sm text-gray-700 dark:text-gray-300">
                                <i class="fas fa-link text-blue-500 mr-2"></i>
                                Click any navigation link
                            </span>
                            <a href="index.php" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors">
                                Go to Dashboard
                            </a>
                        </div>
                        
                        <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                            <span class="text-sm text-gray-700 dark:text-gray-300">
                                <i class="fas fa-paper-plane text-green-500 mr-2"></i>
                                Submit any form
                            </span>
                            <form method="POST" class="inline">
                                <button type="submit" class="px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition-colors">
                                    Submit Form
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Manual Loading -->
                <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-6 shadow-sm">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                        <i class="fas fa-hand-pointer text-purple-600 mr-2"></i>
                        Manual Loading Triggers
                    </h2>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                        Click these buttons to manually trigger loading with custom messages:
                    </p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <button onclick="showLoading('Processing...', 'Please wait'); setTimeout(hideLoading, 2000)" 
                                class="px-4 py-3 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors">
                            <i class="fas fa-spinner mr-2"></i>
                            Default Loading (2s)
                        </button>
                        
                        <button onclick="showLoading('Saving data...', 'This may take a moment'); setTimeout(hideLoading, 2000)" 
                                class="px-4 py-3 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition-colors">
                            <i class="fas fa-save mr-2"></i>
                            Saving (2s)
                        </button>
                        
                        <button onclick="showLoading('Uploading files...', 'Do not close this window'); setTimeout(hideLoading, 2000)" 
                                class="px-4 py-3 bg-yellow-600 text-white text-sm font-medium rounded-lg hover:bg-yellow-700 transition-colors">
                            <i class="fas fa-upload mr-2"></i>
                            Uploading (2s)
                        </button>
                        
                        <button onclick="showLoading('Generating report...', 'Analyzing data'); setTimeout(hideLoading, 2000)" 
                                class="px-4 py-3 bg-purple-600 text-white text-sm font-medium rounded-lg hover:bg-purple-700 transition-colors">
                            <i class="fas fa-file-pdf mr-2"></i>
                            Generating PDF (2s)
                        </button>
                        
                        <button onclick="showLoading('Deleting...', 'This action cannot be undone'); setTimeout(hideLoading, 2000)" 
                                class="px-4 py-3 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700 transition-colors">
                            <i class="fas fa-trash mr-2"></i>
                            Deleting (2s)
                        </button>
                        
                        <button onclick="showLoading('Refreshing...', 'Fetching latest data'); setTimeout(hideLoading, 2000)" 
                                class="px-4 py-3 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors">
                            <i class="fas fa-sync mr-2"></i>
                            Refreshing (2s)
                        </button>
                    </div>
                </div>

                <!-- Data-Loading Attribute -->
                <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-6 shadow-sm">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                        <i class="fas fa-code text-orange-600 mr-2"></i>
                        Using data-loading Attribute
                    </h2>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                        Add <code class="px-2 py-1 bg-gray-100 dark:bg-gray-700 rounded text-xs">data-loading="Your message"</code> to any button:
                    </p>
                    <div class="space-y-3">
                        <div class="p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                            <code class="text-xs text-gray-700 dark:text-gray-300">
                                &lt;button data-loading="Exporting data..." onclick="setTimeout(hideLoading, 2000)"&gt;Export&lt;/button&gt;
                            </code>
                        </div>
                        <button data-loading="Exporting data..." 
                                onclick="setTimeout(hideLoading, 2000)"
                                class="px-4 py-2 bg-teal-600 text-white text-sm font-medium rounded-lg hover:bg-teal-700 transition-colors">
                            <i class="fas fa-download mr-2"></i>
                            Try It - Export Data
                        </button>
                    </div>
                </div>

                <!-- Inline Spinner -->
                <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-6 shadow-sm">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                        <i class="fas fa-circle-notch text-pink-600 mr-2"></i>
                        Inline Button Spinner
                    </h2>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                        For button-specific loading states (without overlay):
                    </p>
                    <button id="inline-demo" onclick="demoInlineSpinner()" 
                            class="px-4 py-2 bg-pink-600 text-white text-sm font-medium rounded-lg hover:bg-pink-700 transition-colors">
                        <i class="fas fa-rocket mr-2"></i>
                        Click for Inline Spinner
                    </button>
                </div>

                <!-- Features List -->
                <div class="bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-gray-800 dark:to-gray-700 border border-blue-200 dark:border-gray-600 rounded-xl p-6">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                        <i class="fas fa-star text-yellow-500 mr-2"></i>
                        Features
                    </h2>
                    <ul class="space-y-2 text-sm text-gray-700 dark:text-gray-300">
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mr-2 mt-0.5"></i>
                            <span><strong>Automatic detection</strong> - Works on all navigation links and form submissions</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mr-2 mt-0.5"></i>
                            <span><strong>Smooth animations</strong> - Fade-in/out with scale effect</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mr-2 mt-0.5"></i>
                            <span><strong>Pulse effect</strong> - Animated background pulse for better visibility</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mr-2 mt-0.5"></i>
                            <span><strong>Custom messages</strong> - Show context-specific loading text</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mr-2 mt-0.5"></i>
                            <span><strong>Dark mode support</strong> - Adapts to your theme preference</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mr-2 mt-0.5"></i>
                            <span><strong>Smart exclusions</strong> - Skips export/download links automatically</span>
                        </li>
                    </ul>
                </div>

            </div>
        </div>
    </main>
    
    <script>
        function demoInlineSpinner() {
            const btn = document.getElementById('inline-demo');
            const originalHTML = btn.innerHTML;
            
            // Add spinner
            btn.disabled = true;
            btn.innerHTML = '<div class="btn-spinner"></div> Processing...';
            
            // Reset after 2 seconds
            setTimeout(() => {
                btn.disabled = false;
                btn.innerHTML = originalHTML;
            }, 2000);
        }
    </script>
</body>
</html>
