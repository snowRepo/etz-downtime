# Technical Documentation - eTranzact Downtime System

## 📚 Table of Contents

- [Architecture Overview](#architecture-overview)
- [Code Structure](#code-structure)
- [Database Queries Reference](#database-queries-reference)
- [Frontend Components](#frontend-components)
- [Backend Logic](#backend-logic)
- [Chart.js Implementation](#chartjs-implementation)
- [PDF Generation](#pdf-generation)
- [Session Management](#session-management)
- [Performance Optimization](#performance-optimization)

---

## 🏗️ Architecture Overview

### Technology Stack

```
┌─────────────────────────────────────────┐
│           Frontend Layer                │
│  - HTML5 + Tailwind CSS v3.4.17       │
│  - Alpine.js v3.x (Reactivity)         │
│  - Chart.js v4.4.1 (Visualizations)    │
│  - Font Awesome 6.5.1 (Icons)          │
└─────────────────────────────────────────┘
                    ↓
┌─────────────────────────────────────────┐
│        Application Layer (PHP)          │
│  - PHP 7.4+ / 8.0+                     │
│  - PDO for Database Access             │
│  - Session-based State Management      │
│  - TCPDF for PDF Generation            │
└─────────────────────────────────────────┘
                    ↓
┌─────────────────────────────────────────┐
│          Data Layer (MySQL)             │
│  - MySQL 5.7+ / MariaDB 10.3+          │
│  - InnoDB Engine                       │
│  - Foreign Key Constraints             │
│  - Triggers for Auto-calculations      │
└─────────────────────────────────────────┘
```

### Design Patterns

1. **MVC-Inspired Structure**

   - Views: Embedded PHP templates
   - Controllers: Page-level PHP scripts
   - Models: Direct PDO queries (can be abstracted)

2. **Component-Based UI**

   - Reusable includes (`navbar.php`, `loading.php`)
   - Consistent styling via Tailwind utility classes
   - Dark mode support via CSS classes

3. **Database-First Approach**
   - Schema defines business logic
   - Triggers handle calculations
   - Foreign keys ensure referential integrity

---

## 📂 Code Structure

### File Organization

```
etz-downtime/
│
├── Core Pages (Entry Points)
│   ├── index.php              → Dashboard (Read-only)
│   ├── report.php             → Incident Creation (Write)
│   ├── incidents.php          → Incident Management (Read/Write)
│   ├── analytics.php          → Data Visualization (Read-only)
│   └── sla_report.php         → SLA Compliance (Read-only)
│
├── Export Utilities
│   ├── export_analytics_pdf.php      → Analytics PDF
│   ├── export_sla_report.php         → SLA Excel
│   └── export_sla_report_pdf.php     → SLA PDF
│
├── Configuration
│   ├── config.php             → Database connection
│   └── includes/pdf_config.php → PDF settings
│
├── Shared Components
│   ├── includes/navbar.php    → Navigation + Dark Mode
│   └── includes/loading.php   → Loading overlay
│
└── Database
    └── downtimedb.sql         → Schema + Seed Data
```

### Page Lifecycle

```php
// Standard page structure
<?php
require_once 'config.php';  // 1. Load database connection
session_start();            // 2. Initialize session

// 3. Handle POST requests (if applicable)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Process form submission
    // Validate input
    // Execute database operations
    // Redirect or set session messages
}

// 4. Fetch data for display
try {
    $stmt = $pdo->prepare("SELECT ...");
    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Handle errors
}

// 5. Render HTML
?>
<!DOCTYPE html>
<html>
<!-- Template with embedded PHP -->
</html>
```

---

## 🗃️ Database Queries Reference

### Common Query Patterns

#### 1. **Dashboard - Recent Incidents**

```sql
SELECT
    s.service_name,
    s.service_id,
    i.status,
    GROUP_CONCAT(DISTINCT c.company_name ORDER BY c.company_name) as company_names,
    COUNT(DISTINCT i.issue_id) as incident_count,
    MAX(i.created_at) as date_reported,
    MAX(i.resolved_at) as date_resolved
FROM issues_reported i
JOIN services s ON i.service_id = s.service_id
JOIN companies c ON i.company_id = c.company_id
GROUP BY s.service_id, s.service_name, i.status
ORDER BY date_reported DESC, s.service_name
LIMIT 10
```

**Purpose**: Groups incidents by service and status, showing affected companies  
**Performance**: Indexed on `service_id`, `company_id`, `created_at`

#### 2. **Analytics - Status Distribution**

```sql
SELECT
    status,
    COUNT(DISTINCT CONCAT(service_id, '-', root_cause)) as count
FROM issues_reported
WHERE created_at >= ? AND created_at < ?
GROUP BY status
```

**Purpose**: Counts unique incidents by status for pie chart  
**Note**: Uses CONCAT to prevent duplicate counting of same issue across companies

#### 3. **SLA Report - Uptime Calculation**

```sql
SELECT
    SUM(downtime_minutes) as total_downtime_minutes,
    COUNT(*) as incident_count
FROM downtime_incidents di
JOIN issues_reported ir ON di.issue_id = ir.issue_id
WHERE ir.service_id = ?
  AND ir.company_id = ?
  AND di.actual_start_time >= ?
  AND di.actual_start_time < ?
```

**Purpose**: Calculates total downtime for SLA percentage  
**Formula**: `Uptime % = ((Total Minutes - Downtime Minutes) / Total Minutes) * 100`

#### 4. **Incident Management - Service Grouping**

```sql
SELECT
    MIN(i.issue_id) as issue_id,
    i.service_id,
    i.root_cause,
    i.status,
    s.service_name,
    i.impact_level,
    GROUP_CONCAT(DISTINCT c.company_name ORDER BY c.company_name) as company_names,
    COUNT(DISTINCT i.company_id) as company_count,
    MAX(i.created_at) as created_at,
    MAX(i.updated_at) as updated_at,
    MAX(i.resolved_at) as resolved_at,
    MAX(i.resolved_by) as resolved_by
FROM issues_reported i
JOIN services s ON i.service_id = s.service_id
JOIN companies c ON i.company_id = c.company_id
GROUP BY i.service_id, i.root_cause, i.status, s.service_name, i.impact_level
ORDER BY
    FIELD(i.status, 'pending', 'resolved'),
    MAX(i.updated_at) DESC
```

**Purpose**: Groups incidents by service for management view  
**Key**: Uses `MIN(issue_id)` to get a representative ID for updates

#### 5. **Report Submission - Duplicate Check**

```sql
SELECT COUNT(*) as count
FROM issues_reported
WHERE user_name = ?
  AND service_id = ?
  AND company_id = ?
  AND root_cause = ?
  AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
```

**Purpose**: Prevents duplicate submissions within 5 minutes  
**Logic**: Same user, service, company, and root cause

---

## 🎨 Frontend Components

### Navbar Component (`includes/navbar.php`)

**Features**:

- Responsive mobile menu
- Active page highlighting
- Dark mode toggle with localStorage persistence
- Notification bell (placeholder)

**Dark Mode Implementation**:

```javascript
// Alpine.js component
{
    darkMode: localStorage.getItem('darkMode') === 'true',

    toggleDarkMode() {
        this.darkMode = !this.darkMode;
        localStorage.setItem('darkMode', this.darkMode);

        if (this.darkMode) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    },

    init() {
        if (this.darkMode) {
            document.documentElement.classList.add('dark');
        }
    }
}
```

### Loading Overlay (`includes/loading.php`)

**Purpose**: Provides visual feedback during page transitions

**Usage**:

```javascript
// Show loading
window.showLoading();

// Hide loading (auto-hides after 500ms)
window.addEventListener("load", () => {
  setTimeout(() => window.hideLoading(), 500);
});
```

### Status Badges

**Color Coding**:

```php
$statusClass = [
    'pending' => 'bg-yellow-100 text-yellow-800 border-yellow-200',
    'resolved' => 'bg-green-100 text-green-800 border-green-200',
    'investigating' => 'bg-blue-100 text-blue-800 border-blue-200'
][$status] ?? 'bg-gray-100 text-gray-800 border-gray-200';
```

**Dark Mode Support**:

```css
.dark .bg-yellow-100 {
  background-color: rgba(251, 191, 36, 0.2);
}
.dark .text-yellow-800 {
  color: #fbbf24;
}
```

### Impact Level Badges

```php
$impactClass = [
    'Critical' => 'bg-red-50 text-red-700 border-red-200',
    'High' => 'bg-orange-50 text-orange-700 border-orange-200',
    'Medium' => 'bg-yellow-50 text-yellow-700 border-yellow-200',
    'Low' => 'bg-green-50 text-green-700 border-green-200'
][$impact] ?? 'bg-gray-50 text-gray-700 border-gray-200';
```

---

## ⚙️ Backend Logic

### Form Processing Pattern

```php
// 1. CSRF Token Validation
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    die("Invalid request");
}

// 2. Input Sanitization
$user_name = trim(filter_var($_POST['user_name'], FILTER_SANITIZE_STRING));
$service_id = filter_var($_POST['service_id'], FILTER_VALIDATE_INT);

// 3. Validation
$errors = [];
if (empty($user_name)) {
    $errors[] = "Name is required.";
}
if (!$service_id) {
    $errors[] = "Invalid service.";
}

// 4. Database Transaction
if (empty($errors)) {
    $pdo->beginTransaction();
    try {
        // Execute queries
        $pdo->commit();
        $_SESSION['success'] = "Operation successful!";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}
```

### Rate Limiting Implementation

```php
$rateLimitKey = 'rate_limit_' . $_SERVER['REMOTE_ADDR'];
$maxRequests = 5;
$timeWindow = 60; // seconds

if (isset($_SESSION[$rateLimitKey])) {
    list($count, $timestamp) = explode('|', $_SESSION[$rateLimitKey]);
    $currentTime = time();

    if ($currentTime - $timestamp < $timeWindow) {
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

$_SESSION[$rateLimitKey] = "$count|" . time();
```

### Bulk Status Update Logic

```php
// Get all issues with the same service_id
$stmt = $pdo->prepare("
    SELECT issue_id
    FROM issues_reported
    WHERE service_id = ?
");
$stmt->execute([$service_id]);
$affectedIssues = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Update all at once
$placeholders = implode(',', array_fill(0, count($affectedIssues), '?'));
$stmt = $pdo->prepare("
    UPDATE issues_reported
    SET status = ?,
        resolved_by = ?,
        resolved_at = NOW()
    WHERE issue_id IN ($placeholders)
");
$stmt->execute(array_merge([$status, $user_name], $affectedIssues));

// Add system update to each
foreach ($affectedIssues as $issueId) {
    $stmt = $pdo->prepare("
        INSERT INTO incident_updates (issue_id, user_name, update_text)
        VALUES (?, 'System', ?)
    ");
    $stmt->execute([$issueId, "Marked as $status by $user_name"]);
}
```

---

## 📊 Chart.js Implementation

### Chart Configuration Template

```javascript
const chartConfig = {
    type: 'doughnut', // or 'bar', 'line', 'pie'
    data: {
        labels: <?php echo json_encode($labels); ?>,
        datasets: [{
            label: 'Dataset Label',
            data: <?php echo json_encode($data); ?>,
            backgroundColor: <?php echo json_encode($colors); ?>,
            borderColor: '#ffffff',
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    padding: 15,
                    font: { size: 12 },
                    usePointStyle: true
                }
            },
            tooltip: {
                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                padding: 12,
                titleFont: { size: 14 },
                bodyFont: { size: 13 }
            }
        }
    }
};

new Chart(document.getElementById('chartId'), chartConfig);
```

### Status Distribution Chart (Doughnut)

```javascript
const statusChart = new Chart(document.getElementById("statusChart"), {
  type: "doughnut",
  data: {
    labels: ["Pending", "Resolved", "Investigating"],
    datasets: [
      {
        data: [15, 45, 5],
        backgroundColor: ["#f59e0b", "#10b981", "#3b82f6"],
        borderWidth: 2,
        borderColor: "#ffffff",
      },
    ],
  },
  options: {
    cutout: "60%", // Creates donut hole
    plugins: {
      legend: { position: "bottom" },
    },
  },
});
```

### Monthly Trend Chart (Line)

```javascript
const trendChart = new Chart(document.getElementById("trendChart"), {
  type: "line",
  data: {
    labels: ["Jan 2026", "Feb 2026", "Mar 2026"],
    datasets: [
      {
        label: "Incidents",
        data: [12, 19, 8],
        borderColor: "#3b82f6",
        backgroundColor: "rgba(59, 130, 246, 0.1)",
        tension: 0.4, // Smooth curves
        fill: true,
        pointRadius: 4,
        pointHoverRadius: 6,
      },
    ],
  },
  options: {
    scales: {
      y: {
        beginAtZero: true,
        ticks: { stepSize: 5 },
      },
    },
  },
});
```

---

## 📄 PDF Generation

### TCPDF Configuration (`includes/pdf_config.php`)

```php
require_once __DIR__ . '/../vendor/autoload.php';

class CustomPDF extends TCPDF {
    public function Header() {
        // Logo
        $this->Image('includes/logo1.png', 15, 10, 30);

        // Title
        $this->SetFont('helvetica', 'B', 16);
        $this->Cell(0, 15, 'eTranzact Downtime Report', 0, false, 'C');

        // Line
        $this->Line(15, 30, 195, 30);
    }

    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'Page ' . $this->getAliasNumPage() . ' of ' . $this->getAliasNbPages(), 0, false, 'C');
    }
}
```

### Analytics PDF Export (`export_analytics_pdf.php`)

```php
$pdf = new CustomPDF('P', 'mm', 'A4', true, 'UTF-8');
$pdf->SetCreator('eTranzact Downtime System');
$pdf->SetAuthor('eTranzact');
$pdf->SetTitle('Analytics Report');

$pdf->AddPage();

// Add chart images (convert canvas to image via JavaScript)
$pdf->Image($chartImagePath, 15, 40, 180);

// Add data table
$html = '<table border="1" cellpadding="5">
    <thead>
        <tr style="background-color: #3b82f6; color: white;">
            <th>Status</th>
            <th>Count</th>
        </tr>
    </thead>
    <tbody>';

foreach ($data as $row) {
    $html .= '<tr>
        <td>' . htmlspecialchars($row['status']) . '</td>
        <td>' . htmlspecialchars($row['count']) . '</td>
    </tr>';
}

$html .= '</tbody></table>';

$pdf->writeHTML($html, true, false, true, false, '');
$pdf->Output('analytics_report.pdf', 'D'); // D = Download
```

### SLA Excel Export (`export_sla_report.php`)

```php
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="sla_report.xls"');

echo '<table border="1">
    <thead>
        <tr>
            <th>Service</th>
            <th>Company</th>
            <th>Uptime %</th>
            <th>Downtime (min)</th>
            <th>Incidents</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>';

foreach ($slaData as $row) {
    echo '<tr>
        <td>' . htmlspecialchars($row['service']) . '</td>
        <td>' . htmlspecialchars($row['company']) . '</td>
        <td>' . number_format($row['uptime'], 2) . '%</td>
        <td>' . $row['downtime'] . '</td>
        <td>' . $row['incidents'] . '</td>
        <td>' . ($row['uptime'] >= $row['target'] ? 'Met' : 'Missed') . '</td>
    </tr>';
}

echo '</tbody></table>';
```

---

## 🔐 Session Management

### Session Initialization

```php
// config.php or page top
session_start();

// Regenerate session ID periodically (security)
if (!isset($_SESSION['created'])) {
    $_SESSION['created'] = time();
} elseif (time() - $_SESSION['created'] > 1800) {
    session_regenerate_id(true);
    $_SESSION['created'] = time();
}
```

### Flash Messages

```php
// Set message
$_SESSION['success'] = "Operation completed successfully!";
$_SESSION['error'] = "An error occurred.";

// Display and clear
if (isset($_SESSION['success'])) {
    echo '<div class="alert-success">' . htmlspecialchars($_SESSION['success']) . '</div>';
    unset($_SESSION['success']);
}
```

### CSRF Token Management

```php
// Generate token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Include in form
<input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

// Validate on submission
if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    die("Invalid CSRF token");
}
```

---

## ⚡ Performance Optimization

### Database Indexing

```sql
-- Essential indexes
CREATE INDEX idx_service_id ON issues_reported(service_id);
CREATE INDEX idx_company_id ON issues_reported(company_id);
CREATE INDEX idx_status ON issues_reported(status);
CREATE INDEX idx_created_at ON issues_reported(created_at);
CREATE INDEX idx_issue_id ON incident_updates(issue_id);

-- Composite indexes for common queries
CREATE INDEX idx_service_status ON issues_reported(service_id, status);
CREATE INDEX idx_date_range ON issues_reported(created_at, service_id);
```

### Query Optimization Tips

1. **Use LIMIT**: Always limit results on dashboard queries
2. **Avoid SELECT \***: Specify only needed columns
3. **Use EXPLAIN**: Analyze slow queries
   ```sql
   EXPLAIN SELECT ... FROM issues_reported WHERE ...;
   ```
4. **Leverage Prepared Statements**: Reuse execution plans

### Frontend Optimization

1. **Lazy Load Charts**: Initialize charts only when visible
2. **Debounce Filters**: Delay filter application on user input
3. **Cache Static Assets**: Set proper cache headers
4. **Minify CSS/JS**: Use production builds in live environment

### Caching Strategy (Future Enhancement)

```php
// Simple query result caching
$cacheKey = 'dashboard_stats_' . date('Y-m-d-H');
$cached = $_SESSION[$cacheKey] ?? null;

if ($cached && time() - $cached['time'] < 300) { // 5 min cache
    $stats = $cached['data'];
} else {
    $stats = $pdo->query("SELECT ...")->fetchAll();
    $_SESSION[$cacheKey] = ['data' => $stats, 'time' => time()];
}
```

---

## 🧪 Testing Checklist

### Manual Testing

- [ ] **Dashboard**: Verify stats accuracy, recent incidents display
- [ ] **Report Form**: Test all validation rules, duplicate prevention
- [ ] **Incidents Page**: Test status updates, timeline updates
- [ ] **Analytics**: Verify charts render, filters work, PDF exports
- [ ] **SLA Report**: Check uptime calculations, Excel/PDF exports
- [ ] **Dark Mode**: Toggle across all pages, verify persistence
- [ ] **Responsive**: Test on mobile, tablet, desktop
- [ ] **Cross-browser**: Chrome, Firefox, Safari, Edge

### Database Testing

```sql
-- Test duplicate prevention
INSERT INTO issues_reported (user_name, service_id, company_id, root_cause)
VALUES ('Test', 1, 1, 'Test cause');
-- Run again immediately - should be caught by application logic

-- Test trigger
INSERT INTO downtime_incidents (issue_id, actual_start_time, actual_end_time)
VALUES (1, '2026-01-01 10:00:00', '2026-01-01 11:30:00');
-- Check downtime_minutes is auto-calculated to 90

-- Test SLA query
SELECT * FROM sla_targets WHERE company_id = 1 AND service_id = 1;
```

---

## 🚀 Deployment Checklist

### Pre-Deployment

- [ ] Set `APP_ENV` to `'production'` in `config.php`
- [ ] Remove or secure `phpinfo.php`, `test_connection.php`
- [ ] Ensure `config.php` is in `.gitignore`
- [ ] Run `composer install --no-dev` for production dependencies
- [ ] Set proper file permissions (644 for files, 755 for directories)
- [ ] Configure HTTPS/SSL certificate
- [ ] Set up database backups (daily recommended)

### Post-Deployment

- [ ] Test all critical paths (report, resolve, export)
- [ ] Verify error logging is working
- [ ] Check database connection pooling
- [ ] Monitor server resources (CPU, memory, disk)
- [ ] Set up monitoring/alerting for downtime

---

## 📞 API Integration (Future)

### Potential REST API Endpoints

```
GET    /api/incidents              - List all incidents
POST   /api/incidents              - Create new incident
GET    /api/incidents/{id}         - Get incident details
PUT    /api/incidents/{id}         - Update incident
DELETE /api/incidents/{id}         - Delete incident

GET    /api/analytics              - Get analytics data
GET    /api/sla                    - Get SLA report data

POST   /api/auth/login             - Authenticate user
POST   /api/auth/logout            - Logout user
```

### Example API Response

```json
{
  "success": true,
  "data": {
    "issue_id": 123,
    "service_name": "Mobile Money",
    "status": "pending",
    "companies": ["MTN", "AirtelTigo"],
    "created_at": "2026-01-01T10:30:00Z"
  },
  "meta": {
    "timestamp": "2026-01-01T10:30:05Z",
    "version": "1.0.0"
  }
}
```

---

**Document Version**: 1.0.0  
**Last Updated**: January 2026  
**Maintained By**: eTranzact Development Team
