# Loading Spinner Implementation Guide

## 🎯 Overview

The enhanced loading spinner system provides automatic and manual loading indicators throughout the application with smooth animations and dark mode support.

---

## ✨ Features

### 1. **Automatic Loading Detection**

- ✅ Navigation links (internal pages)
- ✅ Form submissions
- ✅ Page transitions
- ❌ External links (skipped)
- ❌ Download/export links (skipped)
- ❌ Anchor links (skipped)

### 2. **Visual Enhancements**

- Gradient spinner with dual-color animation
- Pulsing background effect
- Smooth fade-in/fade-out transitions
- Scale animation on card
- Dark mode support

### 3. **Customizable Messages**

- Primary message (main text)
- Secondary message (subtext)
- Context-aware defaults

---

## 🚀 Usage

### Automatic (No Code Required)

The loading spinner automatically appears for:

**Navigation Links:**

```html
<a href="index.php">Dashboard</a>
<!-- Automatically shows "Loading page..." -->
```

**Form Submissions:**

```html
<form method="POST" action="report.php">
  <button type="submit">Submit Report</button>
</form>
<!-- Automatically shows "Submit Report..." -->
```

---

### Manual Triggers

#### Basic Usage

```javascript
// Show loading
showLoading();

// Hide loading
hideLoading();
```

#### With Custom Message

```javascript
showLoading("Saving data...", "Please wait");
```

#### With Timeout

```javascript
showLoading("Processing...", "This may take a moment");

setTimeout(() => {
  hideLoading();
}, 2000);
```

---

### Using data-loading Attribute

Add the `data-loading` attribute to any button for automatic loading on click:

```html
<button data-loading="Exporting report...">Export PDF</button>
```

The button will automatically trigger the loading overlay with your custom message when clicked.

---

### Disable Automatic Loading

#### For Specific Forms

```html
<form method="POST" data-no-loading>
  <!-- This form won't trigger loading automatically -->
</form>
```

#### For Specific Links

```html
<a href="export.php" download> Download File </a>
<!-- Download links are automatically excluded -->
```

---

## 🎨 Customization

### Change Loading Messages

```javascript
// Different contexts
showLoading("Uploading files...", "Do not close this window");
showLoading("Generating report...", "Analyzing data");
showLoading("Deleting...", "This action cannot be undone");
showLoading("Refreshing...", "Fetching latest data");
```

### Inline Button Spinner

For button-specific loading without the overlay:

```html
<button id="myButton" onclick="processAction()">Process</button>

<script>
  function processAction() {
    const btn = document.getElementById("myButton");
    const originalHTML = btn.innerHTML;

    // Show inline spinner
    btn.disabled = true;
    btn.innerHTML = '<div class="btn-spinner"></div> Processing...';

    // Your async operation here
    setTimeout(() => {
      btn.disabled = false;
      btn.innerHTML = originalHTML;
    }, 2000);
  }
</script>
```

### Add Loading Class to Button

```html
<button class="loading">
  <!-- Button text becomes invisible, spinner appears -->
</button>
```

---

## 🎯 Common Patterns

### 1. AJAX Request

```javascript
function fetchData() {
  showLoading("Fetching data...", "Please wait");

  fetch("/api/data")
    .then((response) => response.json())
    .then((data) => {
      // Process data
      hideLoading();
    })
    .catch((error) => {
      hideLoading();
      alert("Error: " + error);
    });
}
```

### 2. Form Submission with Validation

```javascript
function submitForm(e) {
  e.preventDefault();

  // Validate first
  if (!validateForm()) {
    return;
  }

  showLoading("Submitting...", "Processing your request");

  // Submit form
  e.target.submit();
}
```

### 3. Multi-Step Process

```javascript
async function multiStepProcess() {
  showLoading("Step 1 of 3...", "Initializing");
  await step1();

  showLoading("Step 2 of 3...", "Processing data");
  await step2();

  showLoading("Step 3 of 3...", "Finalizing");
  await step3();

  hideLoading();
}
```

### 4. Refresh Button

```javascript
function refreshDashboard() {
  showLoading("Refreshing dashboard...", "Fetching latest data");
  location.reload();
}
```

---

## 🎨 Styling

### Spinner Colors

Edit `includes/loading.php`:

```css
/* Light mode */
.loading-spinner {
  border-top-color: #3b82f6; /* Primary color */
  border-right-color: #60a5fa; /* Secondary color */
}

/* Dark mode */
.dark .loading-spinner {
  border-top-color: #60a5fa;
  border-right-color: #93c5fd;
}
```

### Pulse Effect Color

```css
.loading-pulse {
  background: rgba(59, 130, 246, 0.1); /* Light mode */
}

.dark .loading-pulse {
  background: rgba(96, 165, 250, 0.15); /* Dark mode */
}
```

### Animation Speed

```css
.loading-spinner {
  animation: spin 0.8s cubic-bezier(0.68, -0.55, 0.265, 1.55) infinite;
}

.loading-pulse {
  animation: pulse 1.5s ease-in-out infinite;
}
```

---

## 📱 Responsive Behavior

The loading overlay is fully responsive:

- Centers on all screen sizes
- Adapts to mobile viewports
- Touch-friendly (prevents interaction during loading)

---

## ♿ Accessibility

### Best Practices

1. **Always provide meaningful messages**

   ```javascript
   // Good
   showLoading("Saving your changes...", "Please wait");

   // Avoid
   showLoading();
   ```

2. **Don't show loading for too long**

   - Maximum recommended: 5 seconds
   - For longer operations, consider progress indicators

3. **Always hide loading on error**
   ```javascript
   try {
     showLoading("Processing...");
     await processData();
   } catch (error) {
     hideLoading(); // Important!
     showError(error);
   }
   ```

---

## 🐛 Troubleshooting

### Loading Doesn't Hide

**Problem**: Loading overlay stays visible

**Solutions**:

1. Ensure `hideLoading()` is called
2. Check for JavaScript errors in console
3. Verify the overlay element exists

```javascript
// Debug
console.log(document.getElementById("loading-overlay"));
```

### Loading Doesn't Show

**Problem**: No loading indicator appears

**Solutions**:

1. Verify `includes/loading.php` is included
2. Check if `data-no-loading` attribute is present
3. Ensure JavaScript is enabled

### Multiple Overlays

**Problem**: Loading shows multiple times

**Solution**: Clear any existing timeouts before showing new loading

```javascript
let loadingTimeout;

function safeShowLoading(message, subtext) {
  clearTimeout(loadingTimeout);
  showLoading(message, subtext);
}
```

---

## 📊 Performance

### Best Practices

1. **Minimum display time**: 300ms (prevents flashing)
2. **Maximum display time**: 5 seconds (use progress bar for longer)
3. **Debounce rapid calls**: Prevent multiple shows/hides

```javascript
let lastLoadingTime = 0;

function throttledShowLoading(message, subtext) {
  const now = Date.now();
  if (now - lastLoadingTime > 300) {
    showLoading(message, subtext);
    lastLoadingTime = now;
  }
}
```

---

## 🎓 Examples

### Example 1: Export PDF

```javascript
function exportPDF() {
  showLoading("Generating PDF...", "This may take a moment");

  // Simulate PDF generation
  setTimeout(() => {
    window.location.href = "export_analytics_pdf.php";
    hideLoading();
  }, 1000);
}
```

### Example 2: Delete Confirmation

```javascript
function deleteIncident(id) {
  if (confirm("Are you sure?")) {
    showLoading("Deleting incident...", "This cannot be undone");

    fetch(`/api/incidents/${id}`, { method: "DELETE" })
      .then(() => {
        location.reload();
      })
      .catch((error) => {
        hideLoading();
        alert("Error: " + error);
      });
  }
}
```

### Example 3: Search with Debounce

```javascript
let searchTimeout;

function searchIncidents(query) {
  clearTimeout(searchTimeout);

  searchTimeout = setTimeout(() => {
    showLoading("Searching...", "Finding matches");

    fetch(`/api/search?q=${query}`)
      .then((response) => response.json())
      .then((results) => {
        displayResults(results);
        hideLoading();
      });
  }, 500); // Wait 500ms after user stops typing
}
```

---

## 📚 API Reference

### Functions

#### `showLoading(message, subtext)`

Shows the loading overlay with optional custom messages.

**Parameters:**

- `message` (string, optional): Main loading text. Default: "Loading..."
- `subtext` (string, optional): Secondary text. Default: "Please wait"

**Returns:** void

**Example:**

```javascript
showLoading("Saving...", "Do not close this window");
```

---

#### `hideLoading()`

Hides the loading overlay with fade-out animation.

**Parameters:** None

**Returns:** void

**Example:**

```javascript
hideLoading();
```

---

### CSS Classes

#### `.btn-spinner`

Inline spinner for buttons (16px, white)

#### `.loading-spinner`

Main spinner (48px, gradient blue)

#### `.loading-pulse`

Pulsing background effect

#### `.skeleton`

Skeleton loader for content placeholders

#### `button.loading`

Adds loading state to button with centered spinner

---

## 🔗 Related Files

- `includes/loading.php` - Main loading component
- `loading_demo.php` - Interactive demo page
- All pages include the component via PHP include

---

## 📝 Changelog

### Version 2.0 (Current)

- ✨ Automatic detection for links and forms
- ✨ Enhanced animations with pulse effect
- ✨ Dual-message support (main + subtext)
- ✨ Smart exclusions for downloads/exports
- ✨ data-loading attribute support
- 🎨 Improved dark mode styling
- 🐛 Fixed fade-in/out transitions

### Version 1.0

- Basic spinner overlay
- Manual show/hide functions
- Dark mode support

---

**Last Updated**: January 2026  
**Maintained By**: eTranzact Development Team
