# eTranzact Downtime Tracking System

## 📋 Table of Contents

- [Overview](#overview)
- [Features](#features)
- [System Requirements](#system-requirements)
- [Installation](#installation)
- [Database Schema](#database-schema)
- [Application Structure](#application-structure)
- [User Guide](#user-guide)
- [Developer Guide](#developer-guide)
- [Security Features](#security-features)
- [Troubleshooting](#troubleshooting)

---

## 🎯 Overview

The **eTranzact Downtime Tracking System** is a comprehensive web application designed to monitor, track, and analyze service downtime incidents across multiple companies and services. Built with PHP and MySQL, it provides real-time incident management, detailed analytics, and SLA compliance reporting.

### Key Capabilities

- **Real-time Incident Tracking**: Monitor service outages as they occur
- **Multi-Company Support**: Track incidents across 17+ partner companies
- **Service Coverage**: Monitor 8 critical services including Mobile Money, Fundgate, GHQR, and more
- **Advanced Analytics**: Visualize trends with interactive Chart.js dashboards
- **SLA Reporting**: Generate comprehensive uptime and compliance reports
- **PDF Export**: Export analytics and SLA reports in professional PDF format

---

## ✨ Features

### 1. **Dashboard** (`index.php`)

- **Quick Statistics**: View total, resolved, and pending incidents at a glance
- **Recent Incidents Table**: See the latest 10 incidents with service, affected companies, dates, and status
- **Real-time Updates**: Refresh button to get the latest data
- **Status Badges**: Color-coded badges (Pending, Resolved, Investigating)
- **Dark Mode Support**: Toggle between light and dark themes

### 2. **Incident Reporting** (`report.php`)

- **Multi-Company Selection**: Report incidents affecting multiple companies simultaneously
- **Service Selection**: Choose from 8 predefined services
- **Impact Level Classification**: Low, Medium, High, Critical
- **Root Cause Documentation**: Detailed text field for incident analysis
- **Duplicate Prevention**: Automatic detection of existing incidents
- **CSRF Protection**: Secure form submission with token validation
- **Rate Limiting**: Prevents spam submissions (5 requests per minute)

### 3. **Incident Management** (`incidents.php`)

- **Comprehensive Incident View**: See all incidents grouped by service
- **Status Management**: Update incident status (Pending → Resolved)
- **Update Timeline**: Add and view chronological updates for each incident
- **Company Grouping**: View all affected companies per incident
- **Bulk Updates**: Resolve all incidents for a specific service at once
- **Impact Level Display**: Visual indicators for incident severity

### 4. **Analytics Dashboard** (`analytics.php`)

- **Interactive Charts**:
  - Status Distribution (Doughnut Chart)
  - Incidents by Company (Bar Chart)
  - Monthly Trend Analysis (Line Chart)
  - Impact Level Distribution (Pie Chart)
- **Date Range Filtering**: Analyze data for custom time periods
- **Company Filtering**: Focus on specific company performance
- **PDF Export**: Generate professional analytics reports
- **Responsive Design**: Charts adapt to screen size

### 5. **SLA Reporting** (`sla_report.php`)

- **Uptime Calculation**: Precise uptime percentage tracking
- **Business Hours Support**: Configure business hours per company/service
- **Downtime Analysis**: Detailed breakdown of downtime incidents
- **Target Compliance**: Compare actual vs. target uptime (default 99.99%)
- **Multi-Service Reports**: View SLA compliance across all services
- **Excel Export**: Download SLA data in spreadsheet format
- **PDF Export**: Professional SLA compliance reports

---

## 💻 System Requirements

### Server Requirements

- **Web Server**: Apache 2.4+ (with mod_rewrite)
- **PHP**: 7.4 or higher (8.0+ recommended)
- **Database**: MySQL 5.7+ or MariaDB 10.3+
- **Extensions**:
  - PDO
  - PDO_MySQL
  - mbstring
  - GD (for PDF generation)
  - OpenSSL

### Client Requirements

- Modern web browser (Chrome 90+, Firefox 88+, Safari 14+, Edge 90+)
- JavaScript enabled
- Minimum screen resolution: 1024x768

### Development Tools (Optional)

- Composer (for dependency management)
- Git (for version control)

---

## 🚀 Installation

### Step 1: Clone/Download the Repository

```bash
git clone <repository-url>
cd etz-downtime
```

### Step 2: Install Dependencies

```bash
composer install
```

### Step 3: Database Setup

1. **Create the Database**:

   ```bash
   mysql -u root -p < downtimedb.sql
   ```

2. **Verify Tables Created**:
   - `companies` - Partner companies
   - `services` - Monitored services
   - `issues_reported` - Incident records
   - `incident_updates` - Update timeline
   - `downtime_incidents` - Detailed downtime tracking
   - `sla_targets` - SLA configuration

### Step 4: Configure Database Connection

1. **Copy the example config**:

   ```bash
   copy config.php.example config.php
   ```

2. **Edit `config.php`** with your database credentials:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'your_username');
   define('DB_PASS', 'your_password');
   define('DB_NAME', 'downtimedb');
   define('APP_ENV', 'production'); // or 'development'
   ```

### Step 5: Set Permissions

```bash
# Linux/Mac
chmod 644 config.php
chmod 755 includes/

# Windows - Ensure IIS_IUSRS or IUSR has read permissions
```

### Step 6: Access the Application

Navigate to: `http://localhost/etz-downtime/`

---

## 🗄️ Database Schema

### Core Tables

#### `companies`

Stores partner company information.

```sql
company_id (PK)    - Auto-increment ID
company_name       - Company name (e.g., "MTN", "AirtelTigo")
category           - Optional categorization
created_at         - Timestamp
updated_at         - Auto-updated timestamp
```

#### `services`

Defines monitored services.

```sql
service_id (PK)    - Auto-increment ID
service_name       - Service name (e.g., "Mobile Money")
created_at         - Timestamp
updated_at         - Auto-updated timestamp
```

#### `issues_reported`

Main incident tracking table.

```sql
issue_id (PK)      - Auto-increment ID
user_name          - Reporter name
service_id (FK)    - References services
company_id (FK)    - References companies
root_cause         - Incident description
status             - ENUM('pending', 'resolved')
impact_level       - ENUM('Low', 'Medium', 'High', 'Critical')
resolved_by        - User who resolved the issue
resolved_at        - Resolution timestamp
created_at         - Report timestamp
updated_at         - Auto-updated timestamp
```

#### `incident_updates`

Timeline of incident updates.

```sql
update_id (PK)     - Auto-increment ID
issue_id (FK)      - References issues_reported
user_name          - Update author
update_text        - Update content
created_at         - Update timestamp
updated_at         - Auto-updated timestamp
```

#### `downtime_incidents`

Detailed downtime tracking for SLA calculations.

```sql
incident_id (PK)       - Auto-increment ID
issue_id (FK)          - References issues_reported
actual_start_time      - Downtime start
actual_end_time        - Downtime end
downtime_minutes       - Auto-calculated duration
is_planned             - Boolean flag
downtime_category      - ENUM('Network', 'Server', 'Maintenance', etc.)
created_at             - Timestamp
updated_at             - Auto-updated timestamp
```

#### `sla_targets`

SLA configuration per company/service.

```sql
target_id (PK)         - Auto-increment ID
company_id (FK)        - References companies (nullable)
service_id (FK)        - References services (nullable)
target_uptime          - Decimal (default 99.99%)
business_hours_start   - Time (default 09:00:00)
business_hours_end     - Time (default 17:00:00)
business_days          - SET('Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun')
created_at             - Timestamp
updated_at             - Auto-updated timestamp
```

### Database Triggers

**`calculate_downtime_minutes`**: Automatically calculates downtime duration when `actual_end_time` is updated.

---

## 📁 Application Structure

```
etz-downtime/
├── index.php                      # Dashboard homepage
├── report.php                     # Incident reporting form
├── incidents.php                  # Incident management page
├── analytics.php                  # Analytics dashboard
├── sla_report.php                 # SLA compliance reporting
├── export_analytics_pdf.php       # PDF export for analytics
├── export_sla_report.php          # Excel export for SLA
├── export_sla_report_pdf.php      # PDF export for SLA
├── config.php                     # Database configuration (gitignored)
├── config.php.example             # Configuration template
├── downtimedb.sql                 # Database schema and seed data
├── composer.json                  # PHP dependencies
├── composer.lock                  # Locked dependency versions
├── .gitignore                     # Git ignore rules
│
├── includes/                      # Shared components
│   ├── navbar.php                 # Navigation bar with dark mode
│   ├── loading.php                # Loading overlay component
│   ├── pdf_config.php             # PDF generation configuration
│   └── logo1.png                  # Application logo
│
└── vendor/                        # Composer dependencies (gitignored)
    └── ...
```

---

## 📖 User Guide

### Reporting a New Incident

1. Navigate to **Report Incident** from the navbar
2. Fill in the form:
   - **Your Name**: Enter your full name
   - **Service**: Select the affected service
   - **Companies**: Check all affected companies
   - **Impact Level**: Choose severity (Low/Medium/High/Critical)
   - **Root Cause**: Describe the issue in detail
3. Click **Submit Report**
4. Confirmation message appears on success

### Managing Incidents

1. Go to **Incidents** page
2. View all incidents grouped by service
3. **To add an update**:
   - Click "Add Update" on an incident card
   - Enter your name and update text
   - Submit
4. **To resolve an incident**:
   - Click "Resolve Incident"
   - Enter your name
   - Confirm resolution
   - All incidents for that service are marked as resolved

### Viewing Analytics

1. Navigate to **Analytics**
2. **Filter data**:
   - Select company (or "All Companies")
   - Choose date range
   - Click "Apply Filters"
3. **View charts**:
   - Status Distribution
   - Incidents by Company
   - Monthly Trends
   - Impact Levels
4. **Export**: Click "Export PDF" for a report

### Generating SLA Reports

1. Go to **SLA Report**
2. **Configure filters**:
   - Select company
   - Select service
   - Choose date range
3. Click **Generate Report**
4. View:
   - Uptime percentage
   - Total downtime
   - Incident count
   - SLA compliance status
5. **Export options**:
   - Excel: Click "Export to Excel"
   - PDF: Click "Export to PDF"

### Using Dark Mode

1. Click the **moon icon** (🌙) in the navbar
2. Toggle between light and dark themes
3. Preference is saved in browser localStorage

---

## 🛠️ Developer Guide

### Adding a New Service

1. **Database**:

   ```sql
   INSERT INTO services (service_name) VALUES ('New Service Name');
   ```

2. **Update SLA Targets**:
   ```sql
   INSERT INTO sla_targets (company_id, service_id, target_uptime)
   SELECT c.company_id, LAST_INSERT_ID(), 99.99
   FROM companies c WHERE c.company_name != 'All';
   ```

### Adding a New Company

1. **Database**:

   ```sql
   INSERT INTO companies (company_name, category) VALUES ('Company Name', 'Category');
   ```

2. **Update SLA Targets**:
   ```sql
   INSERT INTO sla_targets (company_id, service_id, target_uptime)
   SELECT LAST_INSERT_ID(), s.service_id, 99.99
   FROM services s;
   ```

### Customizing SLA Targets

Edit the `sla_targets` table:

```sql
UPDATE sla_targets
SET target_uptime = 99.95,
    business_hours_start = '08:00:00',
    business_hours_end = '18:00:00',
    business_days = 'Mon,Tue,Wed,Thu,Fri,Sat'
WHERE company_id = 1 AND service_id = 2;
```

### Modifying Chart Colors

Edit the color arrays in `analytics.php`:

```php
$statusColorMap = [
    'pending' => '#f59e0b',    // Yellow
    'resolved' => '#10b981',   // Green
    'investigating' => '#3b82f6' // Blue
];
```

### Adding New Impact Levels

1. **Modify database ENUM**:

   ```sql
   ALTER TABLE issues_reported
   MODIFY impact_level ENUM('Low','Medium','High','Critical','Severe');
   ```

2. **Update PHP code** in `report.php` and `incidents.php` to handle the new level

### Customizing PDF Exports

Edit `includes/pdf_config.php` to modify:

- Page size and orientation
- Fonts and colors
- Header/footer content
- Logo placement

---

## 🔒 Security Features

### Implemented Security Measures

1. **CSRF Protection**

   - Token generation and validation on all forms
   - Prevents cross-site request forgery attacks

2. **SQL Injection Prevention**

   - Prepared statements with PDO
   - Parameter binding for all queries

3. **XSS Protection**

   - `htmlspecialchars()` on all output
   - Content Security Policy headers (recommended to add)

4. **Rate Limiting**

   - Session-based rate limiting (5 requests/minute)
   - Prevents spam and DoS attacks

5. **Input Validation**

   - Server-side validation for all inputs
   - Length limits and type checking
   - Sanitization of user data

6. **Error Handling**

   - Production mode hides detailed errors
   - Development mode shows full error details
   - Error logging for debugging

7. **Session Security**
   - Session-based authentication (can be extended)
   - Secure session configuration recommended

### Recommended Additional Security

1. **HTTPS**: Always use SSL/TLS in production
2. **Database User**: Create a dedicated MySQL user with minimal privileges
3. **File Permissions**: Restrict write access to necessary directories only
4. **Regular Updates**: Keep PHP, MySQL, and dependencies updated
5. **Backup Strategy**: Regular automated database backups

---

## 🐛 Troubleshooting

### Common Issues

#### 1. **Database Connection Failed**

**Error**: "Could not connect to database"

**Solutions**:

- Verify database credentials in `config.php`
- Ensure MySQL service is running
- Check database exists: `SHOW DATABASES;`
- Verify user permissions: `GRANT ALL ON downtimedb.* TO 'user'@'localhost';`

#### 2. **Charts Not Displaying**

**Error**: Blank chart areas on analytics page

**Solutions**:

- Check browser console for JavaScript errors
- Verify Chart.js CDN is accessible
- Ensure data is being returned from database queries
- Clear browser cache

#### 3. **PDF Export Not Working**

**Error**: "Failed to generate PDF"

**Solutions**:

- Verify Composer dependencies are installed: `composer install`
- Check PHP GD extension is enabled: `php -m | grep gd`
- Ensure write permissions on temp directory
- Check error logs for specific TCPDF errors

#### 4. **Dark Mode Not Persisting**

**Error**: Theme resets on page reload

**Solutions**:

- Enable localStorage in browser
- Check browser console for JavaScript errors
- Clear browser cache and cookies
- Verify Alpine.js is loading correctly

#### 5. **Duplicate Incident Prevention Not Working**

**Error**: Same incident can be reported multiple times

**Solutions**:

- Check database constraints are in place
- Verify the duplicate check query in `report.php`
- Ensure transaction rollback is working
- Check for race conditions with concurrent submissions

#### 6. **SLA Calculations Incorrect**

**Error**: Uptime percentages don't match expectations

**Solutions**:

- Verify `downtime_incidents` table has correct data
- Check trigger `calculate_downtime_minutes` is active
- Ensure timezone settings are correct
- Review business hours configuration in `sla_targets`

### Debug Mode

Enable detailed error reporting in `config.php`:

```php
define('APP_ENV', 'development');
```

This will display:

- PHP errors and warnings
- SQL query errors
- Stack traces

**⚠️ Never use development mode in production!**

### Logging

Check server error logs:

```bash
# Apache
tail -f /var/log/apache2/error.log

# XAMPP Windows
C:\xampp\apache\logs\error.log

# PHP-FPM
tail -f /var/log/php-fpm/error.log
```

---

## 📞 Support

For issues, questions, or feature requests:

1. Check this documentation
2. Review the troubleshooting section
3. Check application error logs
4. Contact the development team

---

## 📄 License

This application is proprietary software developed for eTranzact. All rights reserved.

---

## 🙏 Credits

**Developed by**: eTranzact Development Team  
**Framework**: Tailwind CSS v3.4.17  
**Charts**: Chart.js v4.4.1  
**Icons**: Font Awesome 6.5.1  
**Fonts**: Inter (Google Fonts)  
**PDF Library**: TCPDF (via Composer)  
**JavaScript**: Alpine.js v3.x

---

**Last Updated**: January 2026  
**Version**: 1.0.0
