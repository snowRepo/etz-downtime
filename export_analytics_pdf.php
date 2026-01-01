<?php
/**
 * Analytics PDF Export
 * Generates comprehensive analytics report with premium fintech styling
 */

require_once 'config.php';
require_once 'vendor/autoload.php';
require_once 'includes/pdf_config.php';

// Include TCPDF library
require_once('vendor/tecnickcom/tcpdf/tcpdf.php');

// Error reporting for debugging (disable in production)
if (APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

try {
    // Get and validate filter parameters
    $endDate = $_GET['end_date'] ?? date('Y-m-d');
    $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
    $companyId = $_GET['company_id'] ?? null;
    
    // Validate inputs
    validateDateRange($startDate, $endDate);
    $companyId = validateCompanyId($pdo, $companyId);
    
    // Prepare query parameters
    $params = [$startDate, $endDate];
    $companyFilter = '';
    if ($companyId) {
        $companyFilter = "AND i.company_id = ? ";
        $params[] = $companyId;
    }
    
    // Fetch summary statistics (optimized single query)
    $summaryQuery = "
        SELECT 
            COUNT(DISTINCT CONCAT(i.service_id, '-', i.root_cause)) as total_incidents,
            SUM(CASE WHEN i.status = 'pending' THEN 1 ELSE 0 END) as pending_count,
            SUM(CASE WHEN i.status = 'resolved' THEN 1 ELSE 0 END) as resolved_count
        FROM issues_reported i
        WHERE i.created_at BETWEEN ? AND ? {$companyFilter}
    ";
    $stmt = $pdo->prepare($summaryQuery);
    $stmt->execute($params);
    $summary = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $totalIncidents = (int)($summary['total_incidents'] ?? 0);
    $openIncidents = (int)($summary['pending_count'] ?? 0);
    $resolvedIncidents = (int)($summary['resolved_count'] ?? 0);
    
    // Fetch incidents by status
    $statusQuery = "
        SELECT status, COUNT(DISTINCT CONCAT(service_id, '-', root_cause)) as count 
        FROM issues_reported 
        WHERE created_at BETWEEN ? AND ? {$companyFilter}
        GROUP BY status
    ";
    $stmt = $pdo->prepare($statusQuery);
    $stmt->execute($params);
    $incidentsByStatus = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch incidents by company (top 15 only)
    $companyQuery = "
        SELECT c.company_name, COUNT(DISTINCT CONCAT(i.service_id, '-', i.root_cause)) as incident_count 
        FROM issues_reported i
        JOIN companies c ON i.company_id = c.company_id
        WHERE i.created_at BETWEEN ? AND ? {$companyFilter}
        GROUP BY i.company_id 
        ORDER BY incident_count DESC
        LIMIT " . MAX_COMPANIES_IN_CHART . "
    ";
    $stmt = $pdo->prepare($companyQuery);
    $stmt->execute($params);
    $incidentsByCompany = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch monthly trend
    $trendQuery = "
        SELECT 
            DATE_FORMAT(i.created_at, '%Y-%m') as month,
            COUNT(DISTINCT CONCAT(i.service_id, '-', i.root_cause)) as incident_count
        FROM issues_reported i
        WHERE i.created_at BETWEEN ? AND ? {$companyFilter}
        GROUP BY DATE_FORMAT(i.created_at, '%Y-%m')
        ORDER BY month
    ";
    $stmt = $pdo->prepare($trendQuery);
    $stmt->execute($params);
    $monthlyTrend = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch impact level distribution
    $impactQuery = "
        SELECT impact_level, COUNT(*) as count 
        FROM issues_reported i
        WHERE i.created_at BETWEEN ? AND ? {$companyFilter}
        GROUP BY impact_level
    ";
    $stmt = $pdo->prepare($impactQuery);
    $stmt->execute($params);
    $impactLevels = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate average resolution time
    $resolutionQuery = "
        SELECT AVG(TIMESTAMPDIFF(HOUR, d.actual_start_time, COALESCE(d.actual_end_time, NOW()))) as avg_hours 
        FROM issues_reported i
        JOIN downtime_incidents d ON i.issue_id = d.issue_id
        WHERE d.actual_start_time IS NOT NULL
        AND i.created_at BETWEEN ? AND ? {$companyFilter}
    ";
    $stmt = $pdo->prepare($resolutionQuery);
    $stmt->execute($params);
    $avgResolution = $stmt->fetch(PDO::FETCH_ASSOC);
    $avgResolutionTime = formatDuration($avgResolution['avg_hours'] ?? null);

    // Get company name for title
    $companyName = 'All Companies';
    if ($companyId) {
        $companyStmt = $pdo->prepare("SELECT company_name FROM companies WHERE company_id = ?");
        $companyStmt->execute([$companyId]);
        $companyName = $companyStmt->fetchColumn() ?: 'Unknown Company';
    }
    
    // Create new PDF document with premium fintech styling
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator('eTranzact Analytics System');
    $pdf->SetAuthor('eTranzact');
    $pdf->SetTitle("Analytics Report - $companyName - $startDate to $endDate");
    $pdf->SetSubject('Downtime Analytics Report');
    $pdf->SetKeywords(implode(', ', ['Analytics', 'Downtime', 'Report', $companyName, date('Y-m-d')]));
    
    // Set header data - professional fintech style
    $headerText = "Period: $startDate to $endDate | Generated: " . date('M j, Y');
    $pdf->SetHeaderData('', 0, 'eTranzact Analytics Report', $headerText);
    
    // Set header and footer fonts - clean and professional
    $pdf->setHeaderFont(Array('helvetica', '', 9));
    $pdf->setFooterFont(Array('helvetica', '', 8));
    
    // Set default monospaced font
    $pdf->SetDefaultMonospacedFont('courier');
    
    // Set margins - professional spacing
    $pdf->SetMargins(ETZ_PDF_MARGIN_LEFT, ETZ_PDF_MARGIN_TOP, ETZ_PDF_MARGIN_RIGHT);
    $pdf->SetHeaderMargin(ETZ_PDF_HEADER_MARGIN);
    $pdf->SetFooterMargin(ETZ_PDF_FOOTER_MARGIN);
    
    // Set auto page breaks
    $pdf->SetAutoPageBreak(TRUE, ETZ_PDF_AUTO_PAGE_BREAK);
    
    // Apply fintech styling
    applyFintechStyling($pdf);
    
    // Add first page
    $pdf->AddPage();
    
    // === EXECUTIVE SUMMARY PAGE ===
    
    // Main title - professional fintech style
    $pdf->SetFont('helvetica', 'B', 20);
    $pdf->SetTextColor(17, 24, 39); // gray-900
    $pdf->Cell(0, 15, 'Analytics Report', 0, 1, 'C');
    
    // Subtitle
    $pdf->SetFont('helvetica', '', 11);
    $pdf->SetTextColor(75, 85, 99); // gray-600
    $pdf->Cell(0, 8, "Period: $startDate to $endDate", 0, 1, 'C');
    $pdf->Cell(0, 8, $companyName, 0, 1, 'C');
    $pdf->Ln(8);
    
    // Summary section header
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->SetTextColor(17, 24, 39); // gray-900
    $pdf->Cell(0, 10, 'Executive Summary', 0, 1);
    $pdf->Ln(2);
    
    // Summary table - premium fintech styling
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetFillColor(249, 250, 251); // gray-50
    $pdf->SetTextColor(75, 85, 99); // gray-600
    $pdf->SetDrawColor(229, 231, 235); // gray-200
    
    // Table header
    $pdf->Cell(60, TABLE_HEADER_HEIGHT, 'METRIC', 1, 0, 'L', 1);
    $pdf->Cell(60, TABLE_HEADER_HEIGHT, 'VALUE', 1, 0, 'C', 1);
    $pdf->Cell(60, TABLE_HEADER_HEIGHT, 'DETAILS', 1, 1, 'C', 1);
    
    // Table data - clean styling
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetTextColor(17, 24, 39); // gray-900
    
    // Report period
    $pdf->Cell(60, TABLE_ROW_HEIGHT, 'Report Period', 1, 0, 'L');
    $pdf->Cell(60, TABLE_ROW_HEIGHT, "$startDate to $endDate", 1, 0, 'C');
    $diff = (new DateTime($endDate))->diff(new DateTime($startDate));
    $pdf->Cell(60, TABLE_ROW_HEIGHT, $diff->days . ' days', 1, 1, 'C');
    
    // Company (if filtered)
    if ($companyId) {
        $pdf->Cell(60, TABLE_ROW_HEIGHT, 'Company', 1, 0, 'L');
        $pdf->Cell(60, TABLE_ROW_HEIGHT, truncateText($companyName, 25), 1, 0, 'C');
        $pdf->Cell(60, TABLE_ROW_HEIGHT, count($incidentsByCompany) . ' incidents', 1, 1, 'C');
    }
    
    // Total incidents
    $pdf->Cell(60, TABLE_ROW_HEIGHT, 'Total Incidents', 1, 0, 'L');
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(60, TABLE_ROW_HEIGHT, number_format($totalIncidents), 1, 0, 'C');
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(60, TABLE_ROW_HEIGHT, 'All reported issues', 1, 1, 'C');
    
    // Pending incidents
    $pdf->Cell(60, TABLE_ROW_HEIGHT, 'Pending Incidents', 1, 0, 'L');
    $pdf->SetTextColor(202, 138, 4); // yellow-600
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(60, TABLE_ROW_HEIGHT, number_format($openIncidents), 1, 0, 'C');
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetTextColor(17, 24, 39); // gray-900
    $pdf->Cell(60, TABLE_ROW_HEIGHT, 'Awaiting resolution', 1, 1, 'C');
    
    // Resolved incidents
    $pdf->Cell(60, TABLE_ROW_HEIGHT, 'Resolved Incidents', 1, 0, 'L');
    $pdf->SetTextColor(22, 163, 74); // green-600
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(60, TABLE_ROW_HEIGHT, number_format($resolvedIncidents), 1, 0, 'C');
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetTextColor(17, 24, 39); // gray-900
    $pdf->Cell(60, TABLE_ROW_HEIGHT, 'Successfully closed', 1, 1, 'C');
    
    // Resolution rate
    $resolutionRate = formatPercentage($resolvedIncidents, $totalIncidents);
    $pdf->Cell(60, TABLE_ROW_HEIGHT, 'Resolution Rate', 1, 0, 'L');
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(60, TABLE_ROW_HEIGHT, $resolutionRate, 1, 0, 'C');
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(60, TABLE_ROW_HEIGHT, 'Closed vs total', 1, 1, 'C');
    
    // Average resolution time
    $pdf->Cell(60, TABLE_ROW_HEIGHT, 'Avg. Resolution Time', 1, 0, 'L');
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(60, TABLE_ROW_HEIGHT, $avgResolutionTime, 1, 0, 'C');
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(60, TABLE_ROW_HEIGHT, 'Time to resolve', 1, 1, 'C');
    
    $pdf->Ln(10);

    // === INCIDENTS BY STATUS (PIE CHART) ===
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->SetTextColor(17, 24, 39); // gray-900
    $pdf->Cell(0, 10, 'Incidents by Status', 0, 1, 'L');
    $pdf->Ln(2);

    // Prepare data for status pie chart
    $statusLabels = [];
    $statusData = [];
    $statuses = ['pending', 'resolved'];

    foreach ($statuses as $status) {
        $found = false;
        foreach ($incidentsByStatus as $statusItem) {
            if (strtolower($statusItem['status']) === $status) {
                $statusLabels[] = ucfirst($status);
                $statusData[] = (int)$statusItem['count'];
                $found = true;
                break;
            }
        }
        if (!$found) {
            $statusLabels[] = ucfirst($status);
            $statusData[] = 0;
        }
    }

    // Calculate chart position for better centering
    $pageWidth = $pdf->getPageWidth();
    $margins = ETZ_PDF_MARGIN_LEFT + ETZ_PDF_MARGIN_RIGHT;
    $availableWidth = $pageWidth - $margins;
    $radius = CHART_PIE_RADIUS;
    
    // Center the chart horizontally
    $chartX = ETZ_PDF_MARGIN_LEFT + ($availableWidth / 2) - $radius - 30; // Offset for legend
    $chartY = $pdf->GetY() + 10;
    $centerX = $chartX + $radius;
    $centerY = $chartY + $radius;

    $pdf->SetDrawColor(229, 231, 235); // gray-200
    $pdf->SetLineWidth(0.1);
    
    drawPieChart($pdf, $statusData, $statusLabels, STATUS_COLORS, $centerX, $centerY, $radius);

    // Add legend to the right of the chart
    $legendX = $centerX + $radius + 15;
    $legendY = $chartY + 15;
    addChartLegend($pdf, $statusLabels, $statusData, STATUS_COLORS, $legendX, $legendY);

    $pdf->Ln(90); // Add space after the chart

    // Monthly Trend Bar Chart
    $pdf->AddPage();
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->SetTextColor(17, 24, 39); // gray-900
    $pdf->Cell(0, 10, 'Monthly Incident Trend', 0, 1, 'L');
    $pdf->Ln(2);

    // Prepare data for monthly trend chart
    $monthlyLabels = [];
    $monthlyData = [];
    foreach ($monthlyTrend as $month) {
        $monthlyLabels[] = date('M Y', strtotime($month['month'] . '-01'));
        $monthlyData[] = (int)$month['incident_count'];
    }

    // Calculate chart dimensions respecting margins
    $pageWidth = $pdf->getPageWidth();
    $margins = ETZ_PDF_MARGIN_LEFT + ETZ_PDF_MARGIN_RIGHT;
    $availableWidth = $pageWidth - $margins - 20; // Extra space for labels
    
    $chartX = ETZ_PDF_MARGIN_LEFT + 10; // Space for Y-axis labels
    $chartY = $pdf->GetY() + 10;
    $chartWidth = $availableWidth - 10;
    $chartHeight = 90;
    $maxData = !empty($monthlyData) ? max($monthlyData) : 10;
    $maxData = max($maxData, 1);

    // Set professional styling
    $pdf->SetDrawColor(229, 231, 235); // gray-200
    $pdf->SetLineWidth(0.1);
    $pdf->SetTextColor(75, 85, 99); // gray-600
    $pdf->SetFont('helvetica', '', 8);

    // Draw axes
    $pdf->Line($chartX, $chartY, $chartX, $chartY + $chartHeight); // Y-axis
    $pdf->Line($chartX, $chartY + $chartHeight, $chartX + $chartWidth, $chartY + $chartHeight); // X-axis

    // Draw grid lines and labels
    $yStep = $chartHeight / 5;
    for ($i = 0; $i <= 5; $i++) {
        $y = $chartY + $chartHeight - ($i * $yStep);
        $pdf->Line($chartX, $y, $chartX + $chartWidth, $y, array('dash' => '1,1'));
        $pdf->Text($chartX - 8, $y - 1, number_format(($maxData / 5) * $i, 0));
    }

    // Draw bars with fintech blue
    $barCount = count($monthlyLabels);
    if ($barCount > 0) {
        $barWidth = min(($chartWidth / $barCount) - 4, CHART_BAR_MAX_WIDTH);
        
        foreach ($monthlyLabels as $index => $label) {
            $barHeight = ($monthlyData[$index] / $maxData) * $chartHeight;
            $x = $chartX + ($index * ($chartWidth / $barCount)) + 2;
            $y = $chartY + $chartHeight - $barHeight;
            
            // Use fintech blue
            $pdf->SetFillColor(37, 99, 235); // blue-600
            $pdf->Rect($x, $y, $barWidth, $barHeight, 'F');
            
            // Add month label (rotated)
            $pdf->StartTransform();
            $pdf->Rotate(45, $x + $barWidth/2, $chartY + $chartHeight + 3);
            $pdf->Text($x, $chartY + $chartHeight + 3, $label);
            $pdf->StopTransform();
        }
    }
    
    $pdf->Ln(10);

    // Incidents by Company Bar Chart
    $pdf->AddPage();
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->SetTextColor(17, 24, 39); // gray-900
    $pdf->Cell(0, 10, 'Incidents by Company', 0, 1, 'L');
    $pdf->Ln(2);

    // Prepare data for company chart
    $companyLabels = [];
    $companyData = [];
    foreach ($incidentsByCompany as $company) {
        $companyLabels[] = $company['company_name'];
        $companyData[] = (int)$company['incident_count'];
    }

    // Calculate chart dimensions respecting margins
    $pageWidth = $pdf->getPageWidth();
    $margins = ETZ_PDF_MARGIN_LEFT + ETZ_PDF_MARGIN_RIGHT;
    $availableWidth = $pageWidth - $margins - 60; // Space for company names
    
    $chartX = ETZ_PDF_MARGIN_LEFT;
    $chartY = $pdf->GetY() + 10;
    $chartWidth = $availableWidth - 10;
    $chartHeight = max(60, count($companyLabels) * 10);
    $maxData = !empty($companyData) ? max($companyData) : 10;
    $maxData = max($maxData, 1);

    // Set professional styling
    $pdf->SetDrawColor(229, 231, 235); // gray-200
    $pdf->SetLineWidth(0.1);
    $pdf->SetTextColor(75, 85, 99); // gray-600
    $pdf->SetFont('helvetica', '', 8);

    // Draw axes
    $pdf->Line($chartX, $chartY, $chartX, $chartY + $chartHeight); // Y-axis
    $pdf->Line($chartX, $chartY + $chartHeight, $chartX + $chartWidth, $chartY + $chartHeight); // X-axis

    // Draw grid lines and labels
    $xStep = $chartWidth / 5;
    for ($i = 0; $i <= 5; $i++) {
        $x = $chartX + ($i * $xStep);
        $pdf->Line($x, $chartY, $x, $chartY + $chartHeight, array('dash' => '1,1'));
        $pdf->Text($x - 3, $chartY + $chartHeight + 4, number_format(($maxData / 5) * $i, 0));
    }

    // Draw horizontal bars
    $barCount = count($companyLabels);
    if ($barCount > 0) {
        $barHeight = min(($chartHeight - 10) / $barCount, CHART_HORIZONTAL_BAR_HEIGHT);
        
        foreach ($companyLabels as $index => $label) {
            $barWidth = ($companyData[$index] / $maxData) * $chartWidth;
            $x = $chartX;
            $y = $chartY + 5 + ($index * ($chartHeight - 10) / $barCount);
            
            // Use fintech blue
            $pdf->SetFillColor(37, 99, 235); // blue-600
            $pdf->Rect($x, $y, $barWidth, $barHeight, 'F');
            
            // Add company label (truncated to fit) - removed value label
            $pdf->SetTextColor(17, 24, 39); // gray-900
            $pdf->Text($chartX + $chartWidth + 3, $y + $barHeight/2 + 1, truncateText($label, 25));
        }
    }
    
    // Move below the chart
    $pdf->SetY($chartY + $chartHeight + 15);
    
    // Add summary table below the chart
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->SetTextColor(17, 24, 39); // gray-900
    $pdf->Cell(0, 8, 'Incident Count by Company', 0, 1, 'L');
    $pdf->Ln(2);
    
    // Table header
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetFillColor(249, 250, 251); // gray-50
    $pdf->SetTextColor(75, 85, 99); // gray-600
    $pdf->SetDrawColor(229, 231, 235); // gray-200
    
    $colWidth1 = 120; // Company name
    $colWidth2 = 60;  // Incident count
    
    $pdf->Cell($colWidth1, TABLE_HEADER_HEIGHT, 'COMPANY', 1, 0, 'L', 1);
    $pdf->Cell($colWidth2, TABLE_HEADER_HEIGHT, 'INCIDENTS', 1, 1, 'C', 1);
    
    // Table data
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetTextColor(17, 24, 39); // gray-900
    
    foreach ($companyLabels as $index => $label) {
        $pdf->Cell($colWidth1, TABLE_ROW_HEIGHT, $label, 1, 0, 'L');
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell($colWidth2, TABLE_ROW_HEIGHT, number_format($companyData[$index]), 1, 1, 'C');
        $pdf->SetFont('helvetica', '', 9);
    }
    
    $pdf->Ln(10);


    // Impact Level Distribution Pie Chart
    $pdf->AddPage();
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->SetTextColor(17, 24, 39); // gray-900
    $pdf->Cell(0, 10, 'Impact Level Distribution', 0, 1, 'L');
    $pdf->Ln(2);

    // Prepare data for impact level chart
    $impactLabels = [];
    $impactData = [];

    foreach ($impactLevels as $impact) {
        $impactLabels[] = $impact['impact_level'];
        $impactData[] = (int)$impact['count'];
    }

    // Calculate chart position for better centering
    $pageWidth = $pdf->getPageWidth();
    $margins = ETZ_PDF_MARGIN_LEFT + ETZ_PDF_MARGIN_RIGHT;
    $availableWidth = $pageWidth - $margins;
    $radius = CHART_PIE_RADIUS;
    
    // Center the chart horizontally
    $chartX = ETZ_PDF_MARGIN_LEFT + ($availableWidth / 2) - $radius - 30;
    $chartY = $pdf->GetY() + 10;
    $centerX = $chartX + $radius;
    $centerY = $chartY + $radius;

    $pdf->SetDrawColor(229, 231, 235); // gray-200
    $pdf->SetLineWidth(0.1);
    
    drawPieChart($pdf, $impactData, $impactLabels, IMPACT_COLORS, $centerX, $centerY, $radius);

    // Add legend to the right of the chart
    $legendX = $centerX + $radius + 15;
    $legendY = $chartY + 15;
    addChartLegend($pdf, $impactLabels, $impactData, IMPACT_COLORS, $legendX, $legendY);

    $pdf->Ln(90);

    // Detailed Incidents Table
    $pdf->AddPage();
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'Detailed Incidents Summary', 0, 1, 'L');
    
    // Build query to get detailed incidents data
    $detailedQuery = "SELECT 
                        ir.issue_id,
                        ir.status,
                        ir.impact_level,
                        ir.created_at,
                        ir.resolved_at,
                        c.company_name,
                        s.service_name
                      FROM issues_reported ir
                      JOIN companies c ON ir.company_id = c.company_id
                      JOIN services s ON ir.service_id = s.service_id
                      WHERE ir.created_at BETWEEN ? AND ? ";
    
    $detailedParams = [$startDate, $endDate];
    
    if ($companyId) {
        $detailedQuery .= " AND ir.company_id = ? ";
        $detailedParams[] = $companyId;
    }
    
    $detailedQuery .= " ORDER BY ir.created_at DESC
                      LIMIT 50"; // Limit to prevent too many records
    
    $stmt = $pdo->prepare($detailedQuery);
    $stmt->execute($detailedParams);
    $detailedIncidents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($detailedIncidents)) {
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 10, 'No incidents found for the selected period.', 0, 1, 'C');
    } else {
        $pdf->SetFont('helvetica', '', 10);
        
        // Table header
        $header = ['ID', 'Company', 'Service', 'Impact', 'Status', 'Created', 'Resolved'];
        $w = [15, 35, 40, 25, 20, 30, 30];
        
        // Set fill color for header
        $pdf->SetFillColor(220, 220, 220);
        $pdf->SetFont('helvetica', 'B', 10);
        
        // Header
        for($i = 0; $i < count($header); $i++) {
            $pdf->Cell($w[$i], 7, $header[$i], 1, 0, 'C', 1);
        }
        $pdf->Ln();
        
        // Data
        $pdf->SetFont('helvetica', '', 9);
        $fill = false;
        
        foreach ($detailedIncidents as $incident) {
            // Check if we need a new page
            if ($pdf->GetY() > 200) {
                $pdf->AddPage();
                // Add table header on new page
                $pdf->SetFont('helvetica', 'B', 10);
                for($i = 0; $i < count($header); $i++) {
                    $pdf->Cell($w[$i], 7, $header[$i], 1, 0, 'C', 1);
                }
                $pdf->Ln();
                $pdf->SetFont('helvetica', '', 9);
            }
            
            // Format dates
            $created = date('Y-m-d', strtotime($incident['created_at']));
            $resolved = $incident['resolved_at'] ? date('Y-m-d', strtotime($incident['resolved_at'])) : 'N/A';
            
            // Set fill color for alternate rows
            $pdf->SetFillColor($fill ? 240 : 255, $fill ? 240 : 255, $fill ? 240 : 255);
            
            // Draw cells
            $pdf->Cell($w[0], 6, $incident['issue_id'], 'LR', 0, 'C', $fill);
            $pdf->Cell($w[1], 6, substr($incident['company_name'], 0, 15), 'LR', 0, 'L', $fill);
            $pdf->Cell($w[2], 6, substr($incident['service_name'], 0, 18), 'LR', 0, 'L', $fill);
            $pdf->Cell($w[3], 6, $incident['impact_level'], 'LR', 0, 'C', $fill);
            
            // Status with color coding
            $status = $incident['status'] ?? 'open';
            $statusColor = $status === 'resolved' ? [50, 205, 50] : [255, 165, 0]; // Green for resolved, orange for others
            $pdf->SetFillColor($statusColor[0], $statusColor[1], $statusColor[2]);
            $pdf->Cell($w[4], 6, ucfirst($status), 'LR', 0, 'C', 1);
            
            $pdf->SetFillColor($fill ? 240 : 255, $fill ? 240 : 255, $fill ? 240 : 255);
            $pdf->Cell($w[5], 6, $created, 'LR', 0, 'C', $fill);
            $pdf->Cell($w[6], 6, $resolved, 'LR', 1, 'C', $fill);
            
            $fill = !$fill;
        }
        
        // Close the table
        $pdf->Cell(array_sum($w), 0, '', 'T');
    }

    // Set filename
    $filename = 'Analytics_Report_' . 
               ($companyId ? preg_replace('/[^a-zA-Z0-9]/', '_', $companyName) . '_' : '') . 
               $startDate . '_to_' . $endDate . '.pdf';

    // Output PDF to browser
    $pdf->Output($filename, 'D');
    exit;

} catch (InvalidArgumentException $e) {
    // Handle validation errors
    http_response_code(400);
    error_log('PDF Export Validation Error: ' . $e->getMessage());
    
    if (APP_ENV === 'development') {
        die('Validation Error: ' . $e->getMessage());
    } else {
        die('Invalid request parameters. Please check your date range and company selection.');
    }
    
} catch (PDOException $e) {
    // Handle database errors
    http_response_code(500);
    error_log('PDF Export Database Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
    
    if (APP_ENV === 'development') {
        die('Database Error: ' . $e->getMessage());
    } else {
        die('Unable to retrieve analytics data. Please try again later.');
    }
    
} catch (Exception $e) {
    // Handle all other errors
    http_response_code(500);
    error_log('PDF Export Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
    
    if (APP_ENV === 'development') {
        die('Error generating PDF: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
    } else {
        die('Unable to generate report. Please try again later.');
    }
}