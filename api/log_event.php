<?php
require_once __DIR__ . '/../config/config.php';

// Set headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    // Parse JSON body
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid JSON body']);
        exit;
    }

    // Extract fields
    $type     = $input['type'] ?? 'activity';
    $action   = $input['action'] ?? '';
    $details  = $input['details'] ?? null;
    $page_url = $input['page_url'] ?? null;

    // Validate required fields
    if (empty($action)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Action is required']);
        exit;
    }

    // Validate log type
    $allowed_types = ['activity', 'error', 'page_view'];
    if (!in_array($type, $allowed_types)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid log type']);
        exit;
    }

    // If details is array/object, encode to JSON string
    if (is_array($details) || is_object($details)) {
        $details = json_encode($details, JSON_UNESCAPED_UNICODE);
    }

    // Get user_id from session (NULL if not logged in)
    $user_id = $_SESSION['user_id'] ?? null;

    // Get client IP
    $ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;
    // Take only the first IP if multiple are forwarded
    if ($ip_address && strpos($ip_address, ',') !== false) {
        $ip_address = trim(explode(',', $ip_address)[0]);
    }

    // Insert log entry
    $stmt = $conn->prepare(
        "INSERT INTO system_logs (user_id, log_type, action, details, page_url, ip_address, created_at)
         VALUES (:user_id, :log_type, :action, :details, :page_url, :ip_address, NOW())"
    );

    $stmt->execute([
        ':user_id'    => $user_id,
        ':log_type'   => $type,
        ':action'     => $action,
        ':details'    => $details,
        ':page_url'   => $page_url,
        ':ip_address' => $ip_address,
    ]);

    // Auto-cleanup: ~1% chance (every ~100th request)
    if (mt_rand(1, 100) === 1) {
        $conn->exec("DELETE FROM system_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 7 DAY) AND log_type != 'error'");
    }

    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
