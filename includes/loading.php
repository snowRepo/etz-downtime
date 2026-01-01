<!-- Loading Spinner Component -->
<div id="loading-overlay" class="fixed inset-0 bg-gray-900 bg-opacity-50 dark:bg-opacity-70 hidden items-center justify-center z-50 transition-opacity duration-200">
    <div class="bg-white dark:bg-gray-800 rounded-lg p-6 shadow-xl">
        <div class="flex flex-col items-center">
            <div class="loading-spinner"></div>
            <p class="mt-4 text-sm font-medium text-gray-700 dark:text-gray-300" id="loading-text">Loading...</p>
        </div>
    </div>
</div>

<style>
/* Loading Spinner */
.loading-spinner {
    width: 40px;
    height: 40px;
    border: 4px solid #e5e7eb;
    border-top-color: #3b82f6;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}

.dark .loading-spinner {
    border-color: #374151;
    border-top-color: #60a5fa;
}

@keyframes spin {
    to { transform: rotate(360deg); }
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
</style>

<script>
// Loading overlay functions
function showLoading(message = 'Loading...') {
    const overlay = document.getElementById('loading-overlay');
    const text = document.getElementById('loading-text');
    if (overlay && text) {
        text.textContent = message;
        overlay.classList.remove('hidden');
        overlay.classList.add('flex');
    }
}

function hideLoading() {
    const overlay = document.getElementById('loading-overlay');
    if (overlay) {
        overlay.classList.add('hidden');
        overlay.classList.remove('flex');
    }
}

// Auto-hide loading on page load
window.addEventListener('load', function() {
    hideLoading();
});
</script>
