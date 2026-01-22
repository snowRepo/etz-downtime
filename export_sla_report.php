<?php
// Set error reporting to suppress deprecation warnings for PhpSpreadsheet
// This is needed for PHP 8.2 compatibility
if (PHP_VERSION_ID >= 80000) {
    error_reporting(E_ALL & ~E_DEPRECATED);
} else {
    error_reporting(E_ALL);
}

// Ensure no output is sent before headers
if (ob_get_level()) {
    ob_end_clean();
}
ob_start();

require_once 'config.php';
require_once 'auth.php';
require_once 'vendor/autoload.php';

session_start();
Auth::requireLogin();

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

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
                ir.issue_id,
                ir.created_at as incident_date,
                ir.resolved_at as resolved_date,
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
              AND (ir.resolved_at IS NULL OR ir.resolved_at > :period_start)";

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

    // Calculate total downtime per company and service
    $downtimeSummary = [];
    $totalDowntimeMinutes = 0;

    foreach ($incidents as $incident) {
        $key = $incident['company_id'] . '_' . $incident['service_name'];
        if (!isset($downtimeSummary[$key])) {
            $downtimeSummary[$key] = [
                'company_name' => $incident['company_name'],
                'service_name' => $incident['service_name'],
                'total_downtime' => 0
            ];
        }
        $downtimeMinutes = max(0, (int) $incident['downtime_minutes']);
        $downtimeSummary[$key]['total_downtime'] += $downtimeMinutes;
        $totalDowntimeMinutes += $downtimeMinutes;
    }

    // Create new Spreadsheet object
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Set document properties
    $companyName = (!empty($incidents) && $companyId) ? $incidents[0]['company_name'] : ($companyId ? 'Selected Company' : 'All Companies');
    $spreadsheet->getProperties()
        ->setCreator('eTranzact Downtime Report')
        ->setTitle('SLA Uptime Report - ' . $companyName . ' - ' . $startDate . ' to ' . $endDate)
        ->setSubject('SLA Uptime Report')
        ->setDescription('SLA Uptime Report generated on ' . date('Y-m-d H:i:s'));

    // Add headers
    $headers = [
        'Company',
        'Service',
        'Total Minutes in Period',
        'Downtime (minutes)',
        'Uptime %',
        'SLA Target %',
        'Incident Date',
        'Resolved Date',
        'Impact Level',
        'Root Cause'
    ];

    // Set headers with simple bold styling
    $sheet->fromArray($headers, NULL, 'A1');
    $headerStyle = [
        'font' => ['bold' => true],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT]
    ];
    $sheet->getStyle('A1:' . chr(65 + count($headers) - 1) . '1')->applyFromArray($headerStyle);

    // Add data
    $row = 2;
    foreach ($incidents as $incident) {
        $companyKey = $incident['company_id'] . '_' . $incident['service_name'];
        $downtimeMinutes = max(0, (int) $incident['downtime_minutes']);
        // Calculate uptime percentage, ensuring it doesn't exceed the SLA target
        $uptimePercentage = $totalMinutesInPeriod > 0
            ? max(0, min($slaTarget, (($totalMinutesInPeriod - $downtimeMinutes) / $totalMinutesInPeriod) * 100))
            : $slaTarget;

        $slaStatus = $uptimePercentage >= $slaTarget ? 'MET' : 'NOT MET';

        $sheet->fromArray([
            $incident['company_name'],
            $incident['service_name'],
            $totalMinutesInPeriod,
            $downtimeMinutes,
            $uptimePercentage,
            $slaTarget,
            $incident['incident_date'],
            $incident['resolved_date'] ?: 'Ongoing',
            $incident['impact_level'],
            $incident['root_cause']
        ], NULL, 'A' . $row);

        // Format percentage cells
        $sheet->getStyle('E' . $row . ':F' . $row)
            ->getNumberFormat()
            ->setFormatCode('0.00"%"');

        $row++;
    }

    // Add summary section
    $summaryRow = $row + 2;
    $sheet->setCellValue('A' . $summaryRow, 'Summary');
    $sheet->getStyle('A' . $summaryRow)->getFont()->setBold(true);

    $summaryRow++;
    $sheet->fromArray(['Total Period (minutes):', $totalMinutesInPeriod], NULL, 'A' . $summaryRow);
    $summaryRow++;
    $sheet->fromArray(['Total Downtime (minutes):', $totalDowntimeMinutes], NULL, 'A' . $summaryRow);
    $summaryRow++;

    $overallUptime = $totalMinutesInPeriod > 0
        ? max(0, min($slaTarget, (($totalMinutesInPeriod - $totalDowntimeMinutes) / $totalMinutesInPeriod) * 100))
        : $slaTarget;
    $sheet->fromArray(['Overall Uptime %:', $overallUptime], NULL, 'A' . $summaryRow);
    $sheet->getStyle('B' . $summaryRow)->getNumberFormat()->setFormatCode('0.00"%"');
    $summaryRow++;

    $sheet->fromArray(['SLA Target %:', $slaTarget], NULL, 'A' . $summaryRow);
    $sheet->getStyle('B' . $summaryRow)->getNumberFormat()->setFormatCode('0.00"%"');
    $summaryRow++;

    // Auto-size columns
    foreach (range('A', 'L') as $columnID) {
        $sheet->getColumnDimension($columnID)->setAutoSize(true);
    }


    // Freeze the header row
    $sheet->freezePane('A2');

    // Set the active sheet index to the first sheet
    $spreadsheet->setActiveSheetIndex(0);

    // Clear any previous output
    ob_end_clean();

    // Redirect output to a client's web browser (Excel2007)
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="SLA_Report_' . ($companyId ? preg_replace('/[^a-zA-Z0-9_-]/', '_', $incidents[0]['company_name']) . '_' : '') . $startDate . '_to_' . $endDate . '.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;

} catch (Exception $e) {
    die('Error generating Excel file: ' . $e->getMessage());
}
