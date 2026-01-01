<?php
/**
 * PDF Export Configuration and Helper Functions
 * Premium Fintech Design System
 */

// PDF Layout Constants (prefixed to avoid TCPDF conflicts)
const ETZ_PDF_MARGIN_LEFT = 15;
const ETZ_PDF_MARGIN_TOP = 25;
const ETZ_PDF_MARGIN_RIGHT = 15;
const ETZ_PDF_HEADER_MARGIN = 10;
const ETZ_PDF_FOOTER_MARGIN = 10;
const ETZ_PDF_AUTO_PAGE_BREAK = 25;

// Chart Constants
const CHART_PIE_RADIUS = 40;
const CHART_BAR_MAX_WIDTH = 15;
const CHART_HORIZONTAL_BAR_HEIGHT = 8;
const LEGEND_BOX_SIZE = 4;

// Table Constants
const TABLE_HEADER_HEIGHT = 7;
const TABLE_ROW_HEIGHT = 6;
const MAX_DETAILED_INCIDENTS = 100;
const MAX_COMPANIES_IN_CHART = 15;

// Premium Fintech Color Scheme (matching web design)
const FINTECH_COLORS = [
    // Primary
    'blue-600' => [37, 99, 235],
    'blue-50' => [239, 246, 255],
    
    // Status Colors (muted)
    'green-600' => [22, 163, 74],
    'green-50' => [240, 253, 244],
    'yellow-600' => [202, 138, 4],
    'yellow-50' => [254, 252, 232],
    'red-600' => [220, 38, 38],
    'red-50' => [254, 242, 242],
    
    // Neutrals
    'gray-900' => [17, 24, 39],
    'gray-600' => [75, 85, 99],
    'gray-200' => [229, 231, 235],
    'gray-50' => [249, 250, 251],
];

// Status Color Mapping
const STATUS_COLORS = [
    'pending' => [202, 138, 4],    // yellow-600
    'resolved' => [22, 163, 74],   // green-600
    'investigating' => [37, 99, 235], // blue-600
];

// Impact Level Color Mapping (lowercase keys for color lookup)
const IMPACT_COLORS = [
    'critical' => [220, 38, 38],   // red-600
    'high' => [239, 68, 68],       // red-500
    'medium' => [202, 138, 4],     // yellow-600
    'low' => [22, 163, 74],        // green-600
];

/**
 * Validate date range
 * 
 * @param string $startDate Start date in Y-m-d format
 * @param string $endDate End date in Y-m-d format
 * @return bool
 * @throws InvalidArgumentException
 */
function validateDateRange($startDate, $endDate) {
    if (!strtotime($startDate) || !strtotime($endDate)) {
        throw new InvalidArgumentException('Invalid date format. Use Y-m-d format.');
    }
    
    if (strtotime($startDate) > strtotime($endDate)) {
        throw new InvalidArgumentException('Start date must be before or equal to end date.');
    }
    
    $diff = (new DateTime($endDate))->diff(new DateTime($startDate));
    if ($diff->days > 365) {
        throw new InvalidArgumentException('Date range cannot exceed 1 year.');
    }
    
    return true;
}

/**
 * Validate and sanitize company ID
 * 
 * @param PDO $pdo Database connection
 * @param mixed $companyId Company ID to validate
 * @return int|null Validated company ID or null
 * @throws InvalidArgumentException
 */
function validateCompanyId($pdo, $companyId) {
    if (empty($companyId)) {
        return null;
    }
    
    if (!is_numeric($companyId)) {
        throw new InvalidArgumentException('Company ID must be numeric.');
    }
    
    $stmt = $pdo->prepare("SELECT company_id FROM companies WHERE company_id = ?");
    $stmt->execute([$companyId]);
    
    if (!$stmt->fetchColumn()) {
        throw new InvalidArgumentException('Invalid company ID.');
    }
    
    return (int)$companyId;
}

/**
 * Format duration in hours to human-readable string
 * 
 * @param float|null $hours Duration in hours
 * @return string Formatted duration
 */
function formatDuration($hours) {
    if ($hours === null || $hours < 0) {
        return 'N/A';
    }
    
    if ($hours < 1) {
        return round($hours * 60) . ' minutes';
    }
    
    if ($hours < 24) {
        return round($hours, 1) . ' hours';
    }
    
    return round($hours / 24, 1) . ' days';
}

/**
 * Format percentage with proper handling of division by zero
 * 
 * @param int|float $value Numerator
 * @param int|float $total Denominator
 * @param int $decimals Number of decimal places
 * @return string Formatted percentage
 */
function formatPercentage($value, $total, $decimals = 1) {
    if ($total == 0) {
        return '0%';
    }
    
    return number_format(($value / $total) * 100, $decimals) . '%';
}

/**
 * Truncate text to specified length with ellipsis
 * 
 * @param string $text Text to truncate
 * @param int $maxLength Maximum length
 * @return string Truncated text
 */
function truncateText($text, $maxLength) {
    if (strlen($text) <= $maxLength) {
        return $text;
    }
    
    return substr($text, 0, $maxLength - 3) . '...';
}

/**
 * Draw a professional pie chart with fintech styling
 * 
 * @param TCPDF $pdf PDF object
 * @param array $data Data values
 * @param array $labels Labels for each slice
 * @param array $colors Color mapping (keys should match lowercase labels)
 * @param float $centerX X coordinate of center
 * @param float $centerY Y coordinate of center
 * @param float $radius Radius of pie chart
 * @return void
 */
function drawPieChart($pdf, $data, $labels, $colors, $centerX, $centerY, $radius) {
    $total = array_sum($data);
    
    if ($total == 0) {
        // Draw empty state
        $pdf->SetFillColor(229, 231, 235); // gray-200
        $pdf->PieSector($centerX, $centerY, $radius, 0, 360, 'F', false, 0, 2);
        
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->SetTextColor(156, 163, 175); // gray-400
        $pdf->Text($centerX - 15, $centerY - 3, 'No Data');
        $pdf->SetTextColor(17, 24, 39); // Reset to gray-900
        return;
    }
    
    // Draw pie slices
    $startAngle = 0;
    foreach ($data as $index => $value) {
        if ($value > 0) {
            $angle = ($value / $total) * 360;
            $label = strtolower($labels[$index]); // Convert to lowercase for color lookup
            $color = $colors[$label] ?? [200, 200, 200]; // Default gray if not found
            
            $pdf->SetFillColor($color[0], $color[1], $color[2]);
            $pdf->PieSector($centerX, $centerY, $radius, $startAngle, $startAngle + $angle, 'F', false, 0, 2);
            $startAngle += $angle;
        }
    }
}

/**
 * Add legend for charts
 * 
 * @param TCPDF $pdf PDF object
 * @param array $labels Labels
 * @param array $data Data values
 * @param array $colors Color mapping (keys should match lowercase labels)
 * @param float $x X coordinate
 * @param float $y Y coordinate
 * @return void
 */
function addChartLegend($pdf, $labels, $data, $colors, $x, $y) {
    $total = array_sum($data);
    $boxSize = LEGEND_BOX_SIZE;
    
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetTextColor(17, 24, 39); // gray-900
    
    foreach ($labels as $index => $label) {
        $colorKey = strtolower($label); // Convert to lowercase for color lookup
        $color = $colors[$colorKey] ?? [200, 200, 200];
        $pdf->SetFillColor($color[0], $color[1], $color[2]);
        $pdf->Rect($x, $y + $index * 6, $boxSize, $boxSize, 'F');
        
        $value = $data[$index] ?? 0;
        $percentage = formatPercentage($value, $total);
        $pdf->Text($x + $boxSize + 2, $y + $index * 6 + $boxSize - 1, 
                  "$label: $value ($percentage)");
    }
}

/**
 * Apply premium fintech styling to PDF
 * 
 * @param TCPDF $pdf PDF object
 * @return void
 */
function applyFintechStyling($pdf) {
    // Set professional fonts
    $pdf->SetFont('helvetica', '', 10);
    
    // Set subtle borders
    $pdf->SetDrawColor(229, 231, 235); // gray-200
    $pdf->SetLineWidth(0.1);
    
    // Set text color
    $pdf->SetTextColor(17, 24, 39); // gray-900
}
