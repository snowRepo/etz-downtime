<?php
require_once 'config.php';
require_once 'vendor/autoload.php';

// Include TCPDF library
require_once('vendor/tecnickcom/tcpdf/tcpdf.php');

// Set error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set default date range (last 30 days including today)
$endDate = date('Y-m-d', strtotime('+1 day')); // Include today by going to start of next day
$startDate = date('Y-m-d', strtotime('-30 days'));

// Get filter parameters
$companyId = !empty($_GET['company_id']) ? $_GET['company_id'] : null;
$startDate = $_GET['start_date'] ?? $startDate;
$endDate = $_GET['end_date'] ?? $endDate;

try {
    // Fetch data for charts - same as in analytics.php
    // Get total incidents by status (grouped by service and root cause)
    $statusQuery = "SELECT 
                        status, 
                        COUNT(DISTINCT CONCAT(service_id, '-', root_cause)) as count 
                    FROM issues_reported 
                    WHERE created_at BETWEEN ? AND ? " . 
                    ($companyId ? "AND company_id = ? " : "") . 
                    "GROUP BY status";
    $stmt = $pdo->prepare($statusQuery);
    $params = [$startDate, $endDate];
    if ($companyId) $params[] = $companyId;
    $stmt->execute($params);
    $incidentsByStatus = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get incidents by company (count distinct by service and root cause)
    $companyQuery = "SELECT 
                        c.company_name, 
                        COUNT(DISTINCT CONCAT(i.service_id, '-', i.root_cause)) as incident_count 
                    FROM issues_reported i
                    JOIN companies c ON i.company_id = c.company_id
                    WHERE i.created_at BETWEEN ? AND ? " . 
                    ($companyId ? "AND i.company_id = ? " : "") . 
                    "GROUP BY i.company_id 
                    ORDER BY incident_count DESC";
    $stmt = $pdo->prepare($companyQuery);
    $stmt->execute($params);
    $incidentsByCompany = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get monthly trend
    $trendQuery = "SELECT 
                    DATE_FORMAT(i.created_at, '%Y-%m') as month,
                    COUNT(DISTINCT CONCAT(i.service_id, '-', i.root_cause)) as incident_count
                   FROM issues_reported i
                   WHERE i.created_at BETWEEN ? AND ? " . 
                   ($companyId ? "AND i.company_id = ? " : "") . 
                   "GROUP BY DATE_FORMAT(i.created_at, '%Y-%m')
                   ORDER BY month";
    $stmt = $pdo->prepare($trendQuery);
    $stmt->execute($params);
    $monthlyTrend = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get impact level distribution
    $impactQuery = "SELECT impact_level, COUNT(*) as count 
                   FROM issues_reported i
                   WHERE i.created_at BETWEEN ? AND ? " . 
                   ($companyId ? "AND i.company_id = ? " : "") . 
                   "GROUP BY impact_level";
    $stmt = $pdo->prepare($impactQuery);
    $stmt->execute($params);
    $impactLevels = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate summary statistics
    $totalIncidents = 0;
    $openIncidents = 0;
    $resolvedIncidents = 0;
    
    foreach ($incidentsByStatus as $status) {
        $totalIncidents += (int)$status['count'];
        if ($status['status'] === 'pending') {
            $openIncidents = (int)$status['count'];
        }
        if ($status['status'] === 'resolved') {
            $resolvedIncidents = (int)$status['count'];
        }
    }

    // Calculate average resolution time (in hours)
    $resolutionQuery = "SELECT AVG(TIMESTAMPDIFF(HOUR, d.actual_start_time, COALESCE(d.actual_end_time, NOW()))) as avg_hours 
                      FROM issues_reported i
                      JOIN downtime_incidents d ON i.issue_id = d.issue_id
                      WHERE d.actual_start_time IS NOT NULL
                      AND i.created_at BETWEEN ? AND ? " . 
                      ($companyId ? "AND i.company_id = ? " : "");
    $stmt = $pdo->prepare($resolutionQuery);
    $stmt->execute($params);
    $avgResolution = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $avgResolutionTime = 'N/A';
    if ($avgResolution && $avgResolution['avg_hours'] !== null) {
        $avgHours = round($avgResolution['avg_hours'], 1);
        $avgResolutionTime = $avgHours < 24 
            ? $avgHours . ' hours' 
            : round($avgHours / 24, 1) . ' days';
    }

    // Create new PDF document
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);

    // Set document information
    $pdf->SetCreator('eTranzact Analytics Report');
    $pdf->SetAuthor('eTranzact');
    
    // Get company name for title if company is selected
    $companyName = 'All Companies';
    if ($companyId) {
        $companyStmt = $pdo->prepare("SELECT company_name FROM companies WHERE company_id = ?");
        $companyStmt->execute([$companyId]);
        $companyName = $companyStmt->fetchColumn() ?: 'Unknown Company';
    }
    
    $pdf->SetTitle('Analytics Report - ' . $companyName . ' - ' . $startDate . ' to ' . $endDate);
    $pdf->SetSubject('Analytics Report');
    $pdf->SetKeywords('Analytics, Report, eTranzact');

    // Set default header data
    $pdf->SetHeaderData('', 0, 'Analytics Report', 'Period: ' . $startDate . ' to ' . $endDate . '\nGenerated on: ' . date('Y-m-d H:i:s'));

    // Set header and footer fonts
    $pdf->setHeaderFont(Array('helvetica', '', 10));
    $pdf->setFooterFont(Array('helvetica', '', 8));

    // Set default monospaced font
    $pdf->SetDefaultMonospacedFont('helvetica');

    // Set margins
    $pdf->SetMargins(15, 25, 15);
    $pdf->SetHeaderMargin(10);
    $pdf->SetFooterMargin(10);

    // Set auto page breaks
    $pdf->SetAutoPageBreak(TRUE, 25);

    // Add a page
    $pdf->AddPage();

    // Set font
    $pdf->SetFont('helvetica', '', 10);

    // Add title
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 15, 'Analytics Report', 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 10, 'Period: ' . $startDate . ' to ' . $endDate, 0, 1, 'C');
    $pdf->Ln(5);

    // Add summary information
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'Summary', 0, 1);
    $pdf->SetFont('helvetica', '', 10);

    // Summary table
    $pdf->SetFillColor(240, 240, 240);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(60, 7, 'Metric', 1, 0, 'C', 1);
    $pdf->Cell(60, 7, 'Value', 1, 0, 'C', 1);
    $pdf->Cell(60, 7, 'Details', 1, 1, 'C', 1);

    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(60, 7, 'Report Period', 1, 0, 'L');
    $pdf->Cell(60, 7, $startDate . ' to ' . $endDate, 1, 0, 'C');
    $diff = (new DateTime($endDate))->diff(new DateTime($startDate));
    $pdf->Cell(60, 7, $diff->days . ' days', 1, 1, 'C');

    if ($companyId) {
        $stmt = $pdo->prepare("SELECT company_name FROM companies WHERE company_id = ?");
        $stmt->execute([$companyId]);
        $companyName = $stmt->fetchColumn();
        $pdf->Cell(60, 7, 'Company', 1, 0, 'L');
        $pdf->Cell(60, 7, $companyName ?: 'Unknown Company', 1, 0, 'C');
        $pdf->Cell(60, 7, count($incidentsByCompany ?? []) . ' incidents', 1, 1, 'C');
    }

    $pdf->Cell(60, 7, 'Total Incidents', 1, 0, 'L');
    $pdf->Cell(60, 7, number_format($totalIncidents), 1, 0, 'C');
    $pdf->Cell(60, 7, 'All incidents reported', 1, 1, 'C');

    $pdf->Cell(60, 7, 'Pending Incidents', 1, 0, 'L');
    $pdf->Cell(60, 7, number_format($openIncidents), 1, 0, 'C');
    $pdf->Cell(60, 7, 'Still open', 1, 1, 'C');

    $pdf->Cell(60, 7, 'Resolved Incidents', 1, 0, 'L');
    $pdf->Cell(60, 7, number_format($resolvedIncidents), 1, 0, 'C');
    $pdf->Cell(60, 7, 'Successfully resolved', 1, 1, 'C');

    $pdf->Cell(60, 7, 'Avg. Resolution Time', 1, 0, 'L');
    $pdf->Cell(60, 7, $avgResolutionTime, 1, 0, 'C');
    $pdf->Cell(60, 7, 'Average time to resolve', 1, 1, 'C');

    $pdf->Ln(10);

    // Incidents by Status Pie Chart
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'Incidents by Status', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 10);

    // Prepare data for status pie chart
    $statusLabels = [];
    $statusData = [];
    $statusColors = [
        'pending' => [245, 158, 11],  // yellow
        'resolved' => [16, 185, 129]  // green
    ];

    // Prepare status data with consistent order and colors
    $statuses = ['pending', 'resolved'];
    $hasStatusData = false;

    foreach ($statuses as $status) {
        $found = false;
        foreach ($incidentsByStatus as $statusItem) {
            if (strtolower($statusItem['status']) === $status) {
                $statusLabels[] = ucfirst($status);
                $statusData[] = (int)$statusItem['count'];
                $found = true;
                $hasStatusData = $hasStatusData || $statusItem['count'] > 0;
                break;
            }
        }
        if (!$found) {
            $statusLabels[] = ucfirst($status);
            $statusData[] = 0;
        }
    }

    // Draw pie chart for status
    $pdf->SetDrawColor(200, 200, 200);
    $pdf->SetLineWidth(0.2);

    $chartX = 80;
    $chartY = $pdf->GetY() + 20;
    $radius = 40;
    $centerX = $chartX + $radius;
    $centerY = $chartY + $radius;

    // Check if we have data for the chart
    $hasData = array_sum($statusData) > 0;
    if (!$hasData) {
        // For no data, just show a gray circle with text
        $pdf->SetFillColor(200, 200, 200);
        $pdf->PieSector($centerX, $centerY, $radius, 0, 360, 'F', false, 0, 2);

        // Add "No Data" text in the center
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->Text($centerX - 20, $centerY - 5, 'No Data');
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('helvetica', '', 10);
    } else {
        // Draw the pie chart with actual data
        $total = array_sum($statusData);
        $startAngle = 0;
        $i = 0;

        foreach ($statusData as $index => $value) {
            if ($total > 0) {
                $angle = ($value / $total) * 360;
                $color = $statusColors[array_keys($statusLabels)[$index]] ?? [200, 200, 200];
                $pdf->SetFillColor($color[0], $color[1], $color[2]);
                $pdf->PieSector($centerX, $centerY, $radius, $startAngle, $startAngle + $angle, 'F', false, 0, 2);
                $startAngle += $angle;
            }
            $i++;
        }
    }

    // Add legend for status chart
    $legendX = $centerX + $radius + 20;
    $legendY = $chartY + 10;
    $boxSize = 4;

    $i = 0;
    foreach ($statusLabels as $index => $label) {
        if ($i * 6 + $legendY > 200) {
            $legendX += 60;
            $i = 0;
        }

        $color = $statusColors[strtolower($label)] ?? [200, 200, 200];
        $pdf->SetFillColor($color[0], $color[1], $color[2]);
        $pdf->Rect($legendX, $legendY + $i * 6, $boxSize, $boxSize, 'F');
        
        $value = $statusData[$index] ?? 0;
        $percentage = $total > 0 ? number_format(($value / $total) * 100, 1) : 0;
        $pdf->Text($legendX + $boxSize + 2, $legendY + $i * 6 + $boxSize - 1, 
                  $label . ': ' . $value . ' (' . $percentage . '%)');
        $i++;
    }

    $pdf->Ln(100); // Add space after the chart

    // Monthly Trend Bar Chart
    $pdf->AddPage();
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'Monthly Incident Trend', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 10);

    // Prepare data for monthly trend chart
    $monthlyLabels = [];
    $monthlyData = [];
    foreach ($monthlyTrend as $month) {
        $monthlyLabels[] = date('M Y', strtotime($month['month'] . '-01'));
        $monthlyData[] = (int)$month['incident_count'];
    }

    // Draw bar chart for monthly trend
    $chartX = 25;
    $chartY = $pdf->GetY() + 5;
    $chartWidth = 160;
    $chartHeight = 100;
    $maxData = !empty($monthlyData) ? max($monthlyData) : 10;
    $maxData = max($maxData, 1); // Ensure we have a minimum value

    // Draw axes
    $pdf->Line($chartX, $chartY, $chartX, $chartY + $chartHeight); // Y-axis
    $pdf->Line($chartX, $chartY + $chartHeight, $chartX + $chartWidth, $chartY + $chartHeight); // X-axis

    // Draw grid lines and labels
    $yStep = $chartHeight / 5;
    for ($i = 0; $i <= 5; $i++) {
        $y = $chartY + $chartHeight - ($i * $yStep);
        $pdf->Line($chartX, $y, $chartX + $chartWidth, $y, array('dash' => '1,1'));
        $pdf->Text($chartX - 15, $y - 3, number_format(($maxData / 5) * (5 - $i), 0));
    }

    // Draw bars
    $barColors = [
        [65, 105, 225],  // Royal Blue
        [50, 205, 50],   // Lime Green
        [255, 165, 0],   // Orange
        [220, 20, 60],   // Crimson
        [147, 112, 219], // Medium Purple
        [0, 191, 255],   // Deep Sky Blue
        [255, 192, 203], // Pink
        [255, 215, 0]    // Gold
    ];

    $barCount = count($monthlyLabels);
    $barWidth = $chartWidth / max($barCount, 1);
    $barWidth = min($barWidth - 4, 15); // Max width for bars

    foreach ($monthlyLabels as $index => $label) {
        $barHeight = ($monthlyData[$index] / $maxData) * $chartHeight;
        $x = $chartX + ($index * ($chartWidth / max($barCount, 1))) + 2;
        $y = $chartY + $chartHeight - $barHeight;
        $width = $barWidth;

        $color = $barColors[$index % count($barColors)];
        $pdf->SetFillColor($color[0], $color[1], $color[2]);
        $pdf->Rect($x, $y, $width, $barHeight, 'F');

        // Add month label (rotated)
        $pdf->StartTransform();
        $pdf->Rotate(45, $x + $width/2, $chartY + $chartHeight + 5);
        $pdf->Text($x, $chartY + $chartHeight + 5, $label);
        $pdf->StopTransform();
    }

    // Incidents by Company Bar Chart
    $pdf->AddPage();
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'Incidents by Company', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 10);

    // Prepare data for company chart
    $companyLabels = [];
    $companyData = [];
    foreach ($incidentsByCompany as $company) {
        $companyLabels[] = $company['company_name'];
        $companyData[] = (int)$company['incident_count'];
    }

    // Draw horizontal bar chart for companies
    $chartX = 25;
    $chartY = $pdf->GetY() + 5;
    $chartWidth = 100;
    $chartHeight = max(60, count($companyLabels) * 10); // Adjust height based on number of companies
    $maxData = !empty($companyData) ? max($companyData) : 10;
    $maxData = max($maxData, 1); // Ensure we have a minimum value

    // Draw axes
    $pdf->Line($chartX, $chartY, $chartX, $chartY + $chartHeight); // Y-axis
    $pdf->Line($chartX, $chartY + $chartHeight, $chartX + $chartWidth, $chartY + $chartHeight); // X-axis

    // Draw grid lines and labels
    $xStep = $chartWidth / 5;
    for ($i = 0; $i <= 5; $i++) {
        $x = $chartX + ($i * $xStep);
        $pdf->Line($x, $chartY, $x, $chartY + $chartHeight, array('dash' => '1,1'));
        $pdf->Text($x - 5, $chartY + $chartHeight + 5, number_format(($maxData / 5) * $i, 0));
    }

    // Draw horizontal bars
    $barColors = [
        [65, 105, 225],  // Royal Blue
        [50, 205, 50],   // Lime Green
        [255, 165, 0],   // Orange
        [220, 20, 60],   // Crimson
        [147, 112, 219], // Medium Purple
        [0, 191, 255],   // Deep Sky Blue
        [255, 192, 203], // Pink
        [255, 215, 0]    // Gold
    ];

    $barHeight = count($companyLabels) > 0 ? ($chartHeight - 10) / count($companyLabels) : 10;
    $barHeight = max(5, min($barHeight, 15)); // Ensure bars are visible but not too large

    foreach ($companyLabels as $index => $label) {
        $barWidth = ($companyData[$index] / $maxData) * $chartWidth;
        $x = $chartX;
        $y = $chartY + 5 + ($index * ($chartHeight - 10) / max(count($companyLabels), 1));
        
        $color = $barColors[$index % count($barColors)];
        $pdf->SetFillColor($color[0], $color[1], $color[2]);
        $pdf->Rect($x, $y, $barWidth, $barHeight, 'F');
        
        // Add company label
        $pdf->Text($chartX + $chartWidth + 5, $y + $barHeight/2 + 2, substr($label, 0, 30));
        
        // Add value label
        $pdf->Text($x + $barWidth + 2, $y + $barHeight/2 + 2, $companyData[$index]);
    }

    // Impact Level Distribution Pie Chart
    $pdf->AddPage();
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'Impact Level Distribution', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 10);

    // Prepare data for impact level chart
    $impactLabels = [];
    $impactData = [];
    $impactColors = [
        'Critical' => [220, 38, 38],    // Dark Red
        'High' => [239, 68, 68],        // Red
        'Medium' => [245, 158, 11],     // Amber
        'Low' => [16, 185, 129]         // Green
    ];

    foreach ($impactLevels as $impact) {
        $impactLabels[] = $impact['impact_level'];
        $impactData[] = (int)$impact['count'];
    }

    // Draw pie chart for impact levels
    $pdf->SetDrawColor(200, 200, 200);
    $pdf->SetLineWidth(0.2);

    $chartX = 80;
    $chartY = $pdf->GetY() + 20;
    $radius = 40;
    $centerX = $chartX + $radius;
    $centerY = $chartY + $radius;

    // Check if we have data for the chart
    $hasImpactData = array_sum($impactData) > 0;
    if (!$hasImpactData) {
        // For no data, just show a gray circle with text
        $pdf->SetFillColor(200, 200, 200);
        $pdf->PieSector($centerX, $centerY, $radius, 0, 360, 'F', false, 0, 2);

        // Add "No Data" text in the center
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->Text($centerX - 20, $centerY - 5, 'No Data');
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('helvetica', '', 10);
    } else {
        // Draw the pie chart with actual data
        $total = array_sum($impactData);
        $startAngle = 0;
        $i = 0;

        foreach ($impactData as $index => $value) {
            if ($total > 0) {
                $angle = ($value / $total) * 360;
                $label = $impactLabels[$index];
                $color = $impactColors[$label] ?? [200, 200, 200];
                $pdf->SetFillColor($color[0], $color[1], $color[2]);
                $pdf->PieSector($centerX, $centerY, $radius, $startAngle, $startAngle + $angle, 'F', false, 0, 2);
                $startAngle += $angle;
            }
            $i++;
        }
    }

    // Add legend for impact level chart
    $legendX = $centerX + $radius + 20;
    $legendY = $chartY + 10;
    $boxSize = 4;

    $i = 0;
    foreach ($impactLabels as $index => $label) {
        if ($i * 6 + $legendY > 200) {
            $legendX += 60;
            $i = 0;
        }

        $color = $impactColors[$label] ?? [200, 200, 200];
        $pdf->SetFillColor($color[0], $color[1], $color[2]);
        $pdf->Rect($legendX, $legendY + $i * 6, $boxSize, $boxSize, 'F');
        
        $value = $impactData[$index] ?? 0;
        $percentage = $total > 0 ? number_format(($value / $total) * 100, 1) : 0;
        $pdf->Text($legendX + $boxSize + 2, $legendY + $i * 6 + $boxSize - 1, 
                  $label . ': ' . $value . ' (' . $percentage . '%)');
        $i++;
    }

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

} catch (Exception $e) {
    die('Error generating PDF: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
}