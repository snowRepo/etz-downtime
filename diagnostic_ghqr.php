<?php
require_once 'config.php';

echo "<h2>Database Investigation - GHQR Incident</h2>";

// Check the actual database values
$stmt = $pdo->query("
    SELECT 
        i.issue_id,
        i.service_id,
        s.service_name,
        c.company_name,
        i.status,
        i.resolved_by,
        i.resolved_at,
        i.created_at,
        i.updated_at
    FROM issues_reported i
    JOIN services s ON i.service_id = s.service_id
    JOIN companies c ON i.company_id = c.company_id
    WHERE s.service_name = 'GHQR Transactions'
    ORDER BY i.created_at DESC
");

$incidents = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h3>Raw Database Records:</h3>";
echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
echo "<tr style='background: #f0f0f0;'>
    <th>Issue ID</th>
    <th>Service</th>
    <th>Company</th>
    <th>Status</th>
    <th>Resolved By</th>
    <th>Resolved At</th>
    <th>Created At</th>
    <th>Updated At</th>
</tr>";

foreach ($incidents as $incident) {
    $rowStyle = '';
    if ($incident['status'] === 'pending' && $incident['resolved_at'] !== null) {
        $rowStyle = 'style="background-color: #ffcccc; font-weight: bold;"';
    }
    
    echo "<tr $rowStyle>";
    echo "<td>{$incident['issue_id']}</td>";
    echo "<td>{$incident['service_name']}</td>";
    echo "<td>{$incident['company_name']}</td>";
    echo "<td><strong>{$incident['status']}</strong></td>";
    echo "<td>" . ($incident['resolved_by'] ?? 'NULL') . "</td>";
    echo "<td>" . ($incident['resolved_at'] ?? 'NULL') . "</td>";
    echo "<td>{$incident['created_at']}</td>";
    echo "<td>{$incident['updated_at']}</td>";
    echo "</tr>";
}

echo "</table>";

if (empty($incidents)) {
    echo "<p><strong>No GHQR incidents found in database!</strong></p>";
}

echo "<hr>";
echo "<h3>Dashboard Query Result:</h3>";

// Run the exact dashboard query
$dashboard_query = $pdo->query("
    SELECT 
        s.service_name,
        s.service_id,
        GROUP_CONCAT(DISTINCT c.company_name ORDER BY c.company_name) as company_names,
        COUNT(DISTINCT i.issue_id) as incident_count,
        MAX(i.created_at) as date_reported,
        MAX(i.resolved_at) as date_resolved,
        GROUP_CONCAT(DISTINCT i.status) as statuses
    FROM issues_reported i
    JOIN services s ON i.service_id = s.service_id
    JOIN companies c ON i.company_id = c.company_id
    WHERE s.service_name = 'GHQR Transactions'
    GROUP BY s.service_id, s.service_name
");

$dashboard_result = $dashboard_query->fetchAll(PDO::FETCH_ASSOC);

echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
echo "<tr style='background: #f0f0f0;'>
    <th>Service</th>
    <th>Companies</th>
    <th>Count</th>
    <th>Date Reported</th>
    <th>Date Resolved</th>
    <th>Statuses</th>
</tr>";

foreach ($dashboard_result as $row) {
    echo "<tr>";
    echo "<td>{$row['service_name']}</td>";
    echo "<td>{$row['company_names']}</td>";
    echo "<td>{$row['incident_count']}</td>";
    echo "<td>{$row['date_reported']}</td>";
    echo "<td>" . ($row['date_resolved'] ?? 'NULL') . "</td>";
    echo "<td>{$row['statuses']}</td>";
    echo "</tr>";
}

echo "</table>";

echo "<p style='color: red; font-weight: bold;'>Red rows = DATA CORRUPTION (pending status but has resolved_at date)</p>";
?>
