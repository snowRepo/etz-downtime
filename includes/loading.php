<!-- Enhanced Loading Spinner Component -->
<div id="loading-overlay" class="fixed inset-0 bg-gray-900 bg-opacity-50 dark:bg-opacity-70 flex items-center justify-center z-50 transition-opacity duration-300" style="opacity: 1;">
    <div class="bg-white dark:bg-gray-800 rounded-xl p-8 shadow-2xl transform transition-all duration-300 scale-95" id="loading-card">
        <div class="flex flex-col items-center">
            <!-- Enhanced Spinner with Pulse Effect -->
            <div class="relative">
                <div class="loading-spinner"></div>
                <div class="loading-pulse"></div>
            </div>
            <p class="mt-5 text-sm font-semibold text-gray-700 dark:text-gray-300" id="loading-text">Loading...</p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400" id="loading-subtext">Please wait</p>
        </div>
    </div>
</div>

<style>
/* Enhanced Loading Spinner with Gradient */
.loading-spinner {
    width: 48px;
    height: 48px;
    border: 4px solid transparent;
    border-top-color: #3b82f6;
    border-right-color: #60a5fa;
    border-radius: 50%;
    animation: spin 0.8s cubic-bezier(0.68, -0.55, 0.265, 1.55) infinite;
    position: relative;
    z-index: 2;
}

.dark .loading-spinner {
    border-top-color: #60a5fa;
    border-right-color: #93c5fd;
}

/* Pulse Effect Behind Spinner */
.loading-pulse {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: rgba(59, 130, 246, 0.1);
    animation: pulse 1.5s ease-in-out infinite;
    z-index: 1;
}

.dark .loading-pulse {
    background: rgba(96, 165, 250, 0.15);
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

@keyframes pulse {
    0%, 100% {
        transform: translate(-50%, -50%) scale(1);
        opacity: 0.5;
    }
    50% {
        transform: translate(-50%, -50%) scale(1.5);
        opacity: 0;
    }
}

/* Fade-in animation for overlay */
#loading-overlay.show {
    opacity: 1 !important;
}

#loading-overlay.show #loading-card {
    transform: scale(1) !important;
}

/* Inline spinner for buttons */
.btn-spinner {
    display: inline-block;
    width: 16px;
    height: 16px;
    border: 2px solid rgba(255, 255, 255, 0.3);
    border-top-color: white;
    border-radius: 50%;
    animation: spin 0.6s linear infinite;
    margin-right: 8px;
    vertical-align: middle;
}

/* Table skeleton loader */
.skeleton {
    background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
    background-size: 200% 100%;
    animation: loading 1.5s ease-in-out infinite;
}

.dark .skeleton {
    background: linear-gradient(90deg, #374151 25%, #4b5563 50%, #374151 75%);
    background-size: 200% 100%;
}

@keyframes loading {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}

.skeleton-text {
    height: 12px;
    border-radius: 4px;
}

.skeleton-title {
    height: 20px;
    border-radius: 4px;
}

/* Button loading state */
button.loading {
    position: relative;
    pointer-events: none;
    opacity: 0.7;
}

button.loading::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 16px;
    height: 16px;
    border: 2px solid rgba(255, 255, 255, 0.3);
    border-top-color: white;
    border-radius: 50%;
    animation: spin 0.6s linear infinite;
}
</style>

<script>
// Enhanced Loading overlay functions
let loadingTimeout = null;

function showLoading(message = 'Loading...', subtext = 'Please wait') {
    const overlay = document.getElementById('loading-overlay');
    const text = document.getElementById('loading-text');
    const subtextEl = document.getElementById('loading-subtext');
    
    if (overlay && text) {
        text.textContent = message;
        if (subtextEl) {
            subtextEl.textContent = subtext;
        }
        
        overlay.classList.remove('hidden');
        overlay.classList.add('flex');
        overlay.style.opacity = '1';
    }
}

function hideLoading() {
    const overlay = document.getElementById('loading-overlay');
    if (overlay) {
        // Fade out
        overlay.style.opacity = '0';
        
        setTimeout(() => {
            overlay.classList.add('hidden');
            overlay.classList.remove('flex');
        }, 300); // Match transition duration
    }
}

// Auto-hide loading on page load
window.addEventListener('load', function() {
    setTimeout(() => hideLoading(), 1500); // Show for 1500ms minimum
});

// Show loading on page unload (navigation)
window.addEventListener('beforeunload', function() {
    showLoading('Loading page...', 'Navigating');
});

// Simplified approach - attach to document immediately
(function() {
    // Function to attach navbar link listeners
    function attachNavbarListeners() {
        const navbarLinks = document.querySelectorAll('nav a[href*=".php"]');
        console.log('Found navbar links:', navbarLinks.length);
        
        navbarLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                const currentPage = window.location.pathname;
                const targetPage = new URL(this.href).pathname;
                
                // Check if it's an export/download link
                const isExportLink = this.href.includes('export_') || 
                                    this.hasAttribute('download') ||
                                    this.download;
                
                console.log('Link clicked! Current:', currentPage, 'Target:', targetPage, 'Is Export:', isExportLink);
                
                // Only show loading if going to a different page and NOT an export
                if (currentPage !== targetPage && !isExportLink) {
                    showLoading('Loading page...', 'Please wait');
                    console.log('Loading triggered!');
                } else if (isExportLink) {
                    console.log('Export link - skipping loading screen');
                }
            });
        });
    }
    
    // Try to attach immediately if DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', attachNavbarListeners);
    } else {
        // DOM is already ready
        attachNavbarListeners();
    }
    
    // Also use event delegation as a backup
    document.addEventListener('click', function(e) {
        const link = e.target.closest('a');
        
        if (link && link.href && !link.target && !link.href.startsWith('#')) {
            try {
                const url = new URL(link.href);
                const currentUrl = new URL(window.location.href);
                
                // Check if it's an export/download link
                const isExportLink = link.hasAttribute('download') || 
                                    link.download ||
                                    link.href.includes('export_');
                
                // Only show loading if navigating to a different page and NOT an export
                if (url.origin === currentUrl.origin) {
                    if (!isExportLink && url.pathname !== currentUrl.pathname) {
                        showLoading('Loading page...', 'Please wait');
                        console.log('Loading triggered via delegation for:', link.href);
                    } else if (isExportLink) {
                        console.log('Export/download link detected - skipping loading');
                    }
                }
            } catch (err) {
                console.error('Error processing link:', err);
            }
        }
    }, true);
    
    // Intercept form submissions
    document.addEventListener('submit', function(e) {
        const form = e.target;
        
        if (!form.hasAttribute('data-no-loading')) {
            const submitBtn = form.querySelector('button[type="submit"]');
            const actionText = submitBtn ? submitBtn.textContent.trim() : 'Submitting';
            
            showLoading(actionText + '...', 'Processing your request');
        }
    });
    
    // Add loading state to buttons with data-loading attribute
    function attachButtonListeners() {
        document.querySelectorAll('button[data-loading]').forEach(button => {
            button.addEventListener('click', function() {
                const message = this.getAttribute('data-loading') || 'Processing...';
                showLoading(message, 'Please wait');
            });
        });
    }
    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', attachButtonListeners);
    } else {
        attachButtonListeners();
    }
})();

// Expose functions globally
window.showLoading = showLoading;
window.hideLoading = hideLoading;
</script>
