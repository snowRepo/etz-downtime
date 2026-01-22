<?php
require_once 'config.php';
require_once 'auth.php';
require_once 'vendor/autoload.php';

session_start();
Auth::requireLogin();

// Include TCPDF library
require_once('vendor/tecnickcom/tcpdf/tcpdf.php');

// Set error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Get filter parameters
$companyId = $_GET['company_id'] ?? null;
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-t');

try {
    // Calculate total minutes in the period (24/7)
    $startDateTime = new DateTime($startDate);
    $endDateTime = (new DateTime($endDate))->modify('+1 day'); // Include end date
    $dateInterval = $startDateTime->diff($endDateTime);
    $totalMinutesInPeriod = $dateInterval->days * 24 * 60;

    // Get SLA target for the company if filtered
    $slaTarget = 99.99; // Default SLA target
    if ($companyId) {
        $slaStmt = $pdo->prepare("SELECT target_uptime FROM sla_targets WHERE company_id = ? LIMIT 1");
        $slaStmt->execute([$companyId]);
        $slaTarget = $slaStmt->fetch(PDO::FETCH_COLUMN) ?: $slaTarget;
    }

    // Build the query to get report data
    $query = "SELECT 
                c.company_id,
                c.company_name,
                s.service_name,
                s.service_id,
                ir.issue_id,
                ir.created_at as incident_date,
                ir.resolved_at as resolved_date,
                ir.status,
                TIMESTAMPDIFF(MINUTE, 
                    GREATEST(ir.created_at, :period_start), 
                    LEAST(IFNULL(ir.resolved_at, NOW()), :period_end)
                ) as downtime_minutes,
                ir.impact_level,
                ir.root_cause
              FROM issues_reported ir
              JOIN companies c ON ir.company_id = c.company_id
              JOIN services s ON ir.service_id = s.service_id
              WHERE ir.created_at < :period_end 
              AND (ir.resolved_at IS NULL OR ir.resolved_at > :period_start)
              AND ir.status != 'deleted'";

    $params = [
        'period_start' => $startDate . ' 00:00:00',
        'period_end' => $endDate . ' 23:59:59'
    ];

    if ($companyId) {
        $query .= " AND ir.company_id = :company_id";
        $params['company_id'] = $companyId;
    }

    $query .= " ORDER BY c.company_name, s.service_name, ir.created_at DESC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $incidents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate total downtime
    $totalDowntimeMinutes = 0;
    foreach ($incidents as $incident) {
        $downtimeMinutes = max(0, (int) $incident['downtime_minutes']);
        $totalDowntimeMinutes += $downtimeMinutes;
    }

    // Calculate overall uptime percentage, capped at the SLA target (99.99%)
    $overallUptime = $totalMinutesInPeriod > 0
        ? max(0, min($slaTarget, 100 - (($totalDowntimeMinutes / $totalMinutesInPeriod) * 100)))
        : $slaTarget;

    // Create new PDF document
    $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);

    // Set document information
    $pdf->SetCreator('eTranzact Downtime Report');
    $pdf->SetAuthor('eTranzact');
    $pdf->SetTitle('SLA Uptime Report - ' . ($companyId && !empty($incidents) ? $incidents[0]['company_name'] : 'All Companies') . ' - ' . $startDate . ' to ' . $endDate);
    $pdf->SetSubject('SLA Uptime Report');
    $pdf->SetKeywords('SLA, Uptime, Report, eTranzact');

    // Set default header data
    $pdf->SetHeaderData('', 0, 'SLA Uptime Report', 'Period: ' . $startDate . ' to ' . $endDate . '\nGenerated on: ' . date('Y-m-d H:i:s'));

    // Set header and footer fonts
    $pdf->setHeaderFont(array('helvetica', '', 10));
    $pdf->setFooterFont(array('helvetica', '', 8));

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
    $pdf->Cell(0, 15, 'SLA Uptime Report', 0, 1, 'C');
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
    $pdf->Cell(60, 7, $dateInterval->days . ' days', 1, 1, 'C');

    if ($companyId && !empty($incidents)) {
        $pdf->Cell(60, 7, 'Company', 1, 0, 'L');
        $pdf->Cell(60, 7, $incidents[0]['company_name'], 1, 0, 'C');
        $pdf->Cell(60, 7, count($incidents) . ' incidents', 1, 1, 'C');
    }

    $pdf->Cell(60, 7, 'Total Downtime', 1, 0, 'L');
    $pdf->Cell(60, 7, number_format($totalDowntimeMinutes, 2) . ' minutes', 1, 0, 'C');
    $pdf->Cell(60, 7, number_format($totalDowntimeMinutes / 60, 2) . ' hours', 1, 1, 'C');

    $pdf->Cell(60, 7, 'Uptime Percentage', 1, 0, 'L');
    $pdf->Cell(60, 7, number_format(round($overallUptime, 2), 2) . '%', 1, 0, 'C');
    $pdf->Cell(60, 7, 'Target: ' . number_format($slaTarget, 2) . '%', 1, 1, 'C');

    $pdf->SetFillColor(240, 240, 240);

    // 1. Pie Chart - Downtime Distribution by Service
    $pdf->AddPage();
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'Downtime Distribution by Service', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 10);

    // Prepare data for pie chart
    $serviceDowntime = [];
    if (!empty($incidents)) {
        foreach ($incidents as $incident) {
            $service = $incident['service_name'];
            $downtime = (float) $incident['downtime_minutes'];
            if (!isset($serviceDowntime[$service])) {
                $serviceDowntime[$service] = 0;
            }
            $serviceDowntime[$service] += $downtime;
        }
    }

    // Always show the pie chart, even with no data
    $hasData = !empty($serviceDowntime) && array_sum($serviceDowntime) > 0;
    if (!$hasData) {
        $serviceDowntime = ['No Data' => 1];
    }

    // Sort by downtime (descending)
    arsort($serviceDowntime);

    // Draw pie chart
    $pdf->SetDrawColor(200, 200, 200);
    $pdf->SetLineWidth(0.2);

    $chartX = 80;  // Moved more to the right
    $chartY = $pdf->GetY() + 20;  // Added more space from the top
    $radius = 50;  // Increased radius for better visibility
    $centerX = $chartX + $radius;
    $centerY = $chartY + $radius;

    // Draw the pie chart with gray background for no data
    if (!$hasData) {
        // For no data, just show a gray circle with text
        $pdf->SetFillColor(200, 200, 200);
        $pdf->PieSector($centerX, $centerY, $radius, 0, 360, 'F', false, 0);

        // Add "No Data" text in the center
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->Text($centerX - 20, $centerY - 5, 'No Data');
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('helvetica', '', 10);
    }
    $colors = [
        [65, 105, 225],  // Royal Blue
        [220, 20, 60],   // Crimson
        [50, 205, 50],   // Lime Green
        [255, 165, 0],   // Orange
        [147, 112, 219], // Medium Purple
        [255, 192, 203], // Pink
        [0, 191, 255],   // Deep Sky Blue
        [255, 215, 0]    // Gold
    ];

    $total = array_sum($serviceDowntime);
    $startAngle = 0;
    $i = 0;

    if ($hasData) {
        foreach ($serviceDowntime as $service => $downtime) {
            if ($total > 0) {
                $angle = ($downtime / $total) * 360;
                $pdf->SetFillColor($colors[$i % count($colors)][0], $colors[$i % count($colors)][1], $colors[$i % count($colors)][2]);
                $pdf->PieSector($centerX, $centerY, $radius, $startAngle, $startAngle + $angle, 'F', false, 0);
                $startAngle += $angle;
                $i++;
            }
        }
    }

    // Skip legend for no data
    if ($hasData) {
        // Add legend
        $legendX = $centerX + $radius + 30;  // Moved legend further right
        $legendY = $chartY + 10;  // Adjusted Y position of legend
        $legendWidth = 80;
        $boxSize = 4;

        $i = 0;
        foreach ($serviceDowntime as $service => $downtime) {
            if ($i * 5 + $legendY > 200) { // Increased the threshold for new column
                $legendX += 100;  // Increased column width
                $i = 0;
            }

            $pdf->SetFillColor($colors[$i % count($colors)][0], $colors[$i % count($colors)][1], $colors[$i % count($colors)][2]);
            $pdf->Rect($legendX, $legendY + $i * 5, $boxSize, $boxSize, 'F');
            $percentage = $total > 0 ? number_format(($downtime / $total) * 100, 1) : 0;
            $pdf->Text(
                $legendX + $boxSize + 2,
                $legendY + $i * 5 + $boxSize - 1,
                substr($service, 0, 20) . ' (' . $percentage . '%)'
            );
            $i++;
        }
    }
    // End of pie chart section

    // 2. Bar Chart - Monthly Downtime Trend
    $pdf->AddPage();
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'Monthly Downtime Trend', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 10);

    // Initialize default empty data
    $months = [];
    $downtimes = [];
    $maxDowntime = 100; // Default max if no data

    // Only process if we have incidents
    if (!empty($incidents)) {
        // Group incidents by month
        $monthlyData = [];
        foreach ($incidents as $incident) {
            $month = date('Y-m', strtotime($incident['incident_date']));
            if (!isset($monthlyData[$month])) {
                $monthlyData[$month] = 0;
            }
            $monthlyData[$month] += (float) $incident['downtime_minutes'];
        }

        // Sort by month if we have data
        if (!empty($monthlyData)) {
            ksort($monthlyData);
            $months = array_keys($monthlyData);
            $downtimes = array_values($monthlyData);
            $maxDowntime = max($downtimes) * 1.2; // Add 20% padding
        }
    }

    // If no months, create a default month for display
    if (empty($months)) {
        $months = [date('Y-m')];
        $downtimes = [0];
        $maxDowntime = 100; // Default max
    }

    // Draw bar chart
    $chartX = 25;
    $chartY = $pdf->GetY() + 5;
    $chartWidth = 240;
    $chartHeight = 100;
    $barWidth = $chartWidth / max(count($months), 1);

    // Draw axes
    $pdf->Line($chartX, $chartY, $chartX, $chartY + $chartHeight); // Y-axis
    $pdf->Line($chartX, $chartY + $chartHeight, $chartX + $chartWidth, $chartY + $chartHeight); // X-axis

    // Draw grid lines and labels
    $yStep = $chartHeight / 5;

    for ($i = 0; $i <= 5; $i++) {
        $y = $chartY + $chartHeight - ($i * $yStep);
        $pdf->Line($chartX, $y, $chartX + $chartWidth, $y, array('dash' => '1,1'));
        $pdf->Text($chartX - 15, $y - 3, number_format(($maxDowntime / 5) * (5 - $i), 0) . 'm');
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

    foreach ($months as $index => $month) {
        $barHeight = ($downtimes[$index] / $maxDowntime) * $chartHeight;
        $x = $chartX + ($index * $barWidth) + 2;
        $y = $chartY + $chartHeight - $barHeight;
        $width = $barWidth - 4;

        $color = $barColors[$index % count($barColors)];
        $pdf->SetFillColor($color[0], $color[1], $color[2]);
        $pdf->Rect($x, $y, $width, $barHeight, 'F');

        // Add month label (rotated)
        $pdf->StartTransform();
        $pdf->Rotate(45, $x + $width / 2, $chartY + $chartHeight + 5);
        $pdf->Text($x, $chartY + $chartHeight + 5, date('M Y', strtotime($month . '-01')));
        $pdf->StopTransform();
    }

    // 3. Incidents Table
    $pdf->AddPage();
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'Incident Details', 0, 1, 'L');

    if (empty($incidents)) {
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 10, 'No incidents found for the selected period.', 0, 1, 'C');
    } else {
        $pdf->SetFont('helvetica', '', 10);

        // Table header
        $header = ['Service', 'Start Time', 'End Time', 'Duration (min)', 'Status'];
        $w = [50, 45, 45, 30, 20];

        // Set fill color for header
        $pdf->SetFillColor(220, 220, 220);
        $pdf->SetFont('helvetica', 'B', 10);

        // Header
        for ($i = 0; $i < count($header); $i++) {
            $pdf->Cell($w[$i], 7, $header[$i], 1, 0, 'C', 1);
        }
        $pdf->Ln();

        // Data
        $pdf->SetFont('helvetica', '', 9);
        $fill = false;

        foreach ($incidents as $incident) {
            // Check if we need a new page
            if ($pdf->GetY() > 180) {
                $pdf->AddPage();
                // Add table header on new page
                $pdf->SetFont('helvetica', 'B', 10);
                for ($i = 0; $i < count($header); $i++) {
                    $pdf->Cell($w[$i], 7, $header[$i], 1, 0, 'C', 1);
                }
                $pdf->Ln();
                $pdf->SetFont('helvetica', '', 9);
            }

            // Format dates
            $startTime = date('Y-m-d H:i', strtotime($incident['incident_date']));
            $endTime = $incident['resolved_date'] ? date('Y-m-d H:i', strtotime($incident['resolved_date'])) : 'Ongoing';
            $duration = number_format($incident['downtime_minutes'], 2);

            // Set fill color for alternate rows
            $pdf->SetFillColor($fill ? 240 : 255, $fill ? 240 : 255, $fill ? 240 : 255);

            // Draw cells
            $pdf->Cell($w[0], 6, $incident['service_name'], 'LR', 0, 'L', $fill);
            $pdf->Cell($w[1], 6, $startTime, 'LR', 0, 'C', $fill);
            $pdf->Cell($w[2], 6, $endTime, 'LR', 0, 'C', $fill);
            $pdf->Cell($w[3], 6, $duration, 'LR', 0, 'R', $fill);

            // Status with color coding
            $status = $incident['status'] ?? 'open';
            $statusColor = $status === 'resolved' ? [50, 205, 50] : [255, 165, 0]; // Green for resolved, orange for others
            $pdf->SetFillColor($statusColor[0], $statusColor[1], $statusColor[2]);
            $pdf->Cell($w[4], 6, ucfirst($status), 'LR', 1, 'C', 1);

            $fill = !$fill;
        }

        // Close the table
        $pdf->Cell(array_sum($w), 0, '', 'T');
    }

    // Set filename
    $filename = 'SLA_Uptime_Report_' .
        ($companyId && !empty($incidents) ? preg_replace('/[^a-zA-Z0-9]/', '_', $incidents[0]['company_name']) . '_' : '') .
        $startDate . '_to_' . $endDate . '.pdf';

    // Output PDF to browser
    $pdf->Output($filename, 'D');
    exit;

} catch (Exception $e) {
    die('Error generating PDF: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
}
