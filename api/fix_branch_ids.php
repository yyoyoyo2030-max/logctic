<?php
/**
 * إصلاح التحويلات التي ليس لها branch_id
 * يتم ربطها بالفرع بناءً على from_location
 */
require_once '../config/config.php';
header('Content-Type: application/json; charset=utf-8');

$results = [];

// جلب أسماء الفروع
$branches = $conn->query("SELECT id, name FROM branches")->fetchAll(PDO::FETCH_ASSOC);
$results['branches'] = $branches;

// جلب التحويلات بدون branch_id
$stmt = $conn->query("SELECT id, from_location FROM transfers WHERE branch_id IS NULL");
$orphans = $stmt->fetchAll(PDO::FETCH_ASSOC);
$results['orphan_count'] = count($orphans);

$fixed = 0;
foreach ($orphans as $transfer) {
    $from = trim($transfer['from_location']);
    $matched_branch_id = null;
    
    // 1. مطابقة تامة
    foreach ($branches as $b) {
        if (mb_strtolower($from) === mb_strtolower(trim($b['name']))) {
            $matched_branch_id = $b['id'];
            break;
        }
    }
    
    // 2. مطابقة جزئية (اسم الفرع يحتوي على from_location أو العكس)
    if (!$matched_branch_id) {
        foreach ($branches as $b) {
            $bname = mb_strtolower(trim($b['name']));
            $flow = mb_strtolower($from);
            if (mb_strpos($bname, $flow) !== false || mb_strpos($flow, $bname) !== false) {
                $matched_branch_id = $b['id'];
                break;
            }
        }
    }
    
    if ($matched_branch_id) {
        $upd = $conn->prepare("UPDATE transfers SET branch_id = ? WHERE id = ?");
        $upd->execute([$matched_branch_id, $transfer['id']]);
        $fixed++;
        $results['fixed_details'][] = [
            'transfer_id' => $transfer['id'],
            'from_location' => $from,
            'matched_branch_id' => $matched_branch_id
        ];
    } else {
        $results['unmatched'][] = [
            'transfer_id' => $transfer['id'],
            'from_location' => $from
        ];
    }
}

$results['fixed_count'] = $fixed;
echo json_encode($results, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
