<?php
require_once '../config.php';
require_once '../auth.php';
session_start();

// Ensure user is logged in
if (!Auth::isLoggedIn()) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$service_id = filter_var($_GET['service_id'] ?? null, FILTER_VALIDATE_INT);

if (!$service_id) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid Service ID']);
    exit;
}

try {
    // Fetch components
    $stmt = $pdo->prepare("SELECT component_id, name FROM service_components WHERE service_id = ? AND is_active = 1 ORDER BY name");
    $stmt->execute([$service_id]);
    $components = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch incident types
    $stmt = $pdo->prepare("SELECT type_id, name FROM incident_types WHERE service_id = ? AND is_active = 1 ORDER BY name");
    $stmt->execute([$service_id]);
    $types = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: application/json');
    echo json_encode([
        'components' => $components,
        'types' => $types
    ]);

} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
