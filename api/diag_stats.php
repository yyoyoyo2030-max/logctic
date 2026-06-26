<?php
require_once '../config/config.php';
header('Content-Type: application/json; charset=utf-8');

$result = [];

// 1. All transfer statuses
$stmt = $conn->query("SELECT status, COUNT(*) as cnt FROM transfers GROUP BY status");
$result['transfer_statuses'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 2. Total transfers
$stmt = $conn->query("SELECT COUNT(*) as total FROM transfers");
$result['total_transfers'] = $stmt->fetch()['total'];

// 3. Transfers per branch (by branch_id)
$stmt = $conn->query("
    SELECT b.id, b.name, COUNT(t.id) as transfers_count 
    FROM branches b 
    LEFT JOIN transfers t ON b.id = t.branch_id 
    GROUP BY b.id, b.name
");
$result['transfers_per_branch'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 4. Users per branch
$stmt = $conn->query("
    SELECT b.id, b.name, COUNT(u.id) as users_count 
    FROM branches b 
    LEFT JOIN users u ON b.id = u.branch_id 
    GROUP BY b.id, b.name
");
$result['users_per_branch'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 5. Driver assignments
$stmt = $conn->query("
    SELECT t.status, COUNT(da.id) as assigned_count 
    FROM driver_assignments da 
    JOIN transfers t ON da.transfer_id = t.id 
    GROUP BY t.status
");
$result['assignments_by_status'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 6. Transfers without drivers
$stmt = $conn->query("
    SELECT COUNT(*) as cnt FROM transfers t 
    WHERE NOT EXISTS (SELECT 1 FROM driver_assignments da WHERE da.transfer_id = t.id)
");
$result['no_driver'] = $stmt->fetch()['cnt'];

// 7. Available drivers
$stmt = $conn->query("
    SELECT COUNT(*) as total FROM drivers d
    WHERE NOT EXISTS (
        SELECT 1 FROM driver_assignments da 
        JOIN transfers t ON da.transfer_id = t.id 
        WHERE da.driver_id = d.id 
        AND t.status IN ('assigned', 'in_transit')
    )
");
$result['available_drivers'] = $stmt->fetch()['total'];

// 8. Total drivers
$stmt = $conn->query("SELECT COUNT(*) as total FROM drivers");
$result['total_drivers'] = $stmt->fetch()['total'];

// 9. All transfers detail
$stmt = $conn->query("SELECT id, transfer_number, status, branch_id, from_location, to_location FROM transfers ORDER BY id DESC");
$result['all_transfers'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
