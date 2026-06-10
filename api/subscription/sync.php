<?php

require_once '../../common/db.php';
require_once '../../common/functions.php';
require_once '../../common/response.php';

setCorsHeaders();
header('Content-Type: application/json; charset=utf-8');

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    echo jsonResponse(400, '请求体必须为JSON', null);
    exit;
}

$userId = $data['user_id'] ?? '';
$productId = $data['product_id'] ?? '';
$transactionId = $data['transaction_id'] ?? '';
$subscriptionStatus = $data['subscription_status'] ?? 'active';
$startTimeRaw = $data['start_time'] ?? '';
$expireTimeRaw = $data['expire_time'] ?? '';
$originalTransactionId = $data['original_transaction_id'] ?? null;

if (empty($userId) || empty($productId) || empty($transactionId) || empty($startTimeRaw) || empty($expireTimeRaw)) {
    echo jsonResponse(400, '参数缺失', null);
    exit;
}

$allowedStatuses = ['active', 'expired', 'cancelled', 'refunded'];
if (!in_array($subscriptionStatus, $allowedStatuses, true)) {
    echo jsonResponse(400, 'subscription_status不合法', null);
    exit;
}

try {
    $startDt = new DateTimeImmutable($startTimeRaw);
    $expireDt = new DateTimeImmutable($expireTimeRaw);
} catch (Exception $e) {
    echo jsonResponse(400, '时间格式不正确', null);
    exit;
}

$startTime = $startDt->format('Y-m-d H:i:s');
$expireTime = $expireDt->format('Y-m-d H:i:s');

$subscriptionId = $originalTransactionId ? (string)$originalTransactionId : (string)$transactionId;

try {
    global $pdo;

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM `users` WHERE `user_id` = ?");
    $stmt->execute([$userId]);
    if (intval($stmt->fetchColumn()) <= 0) {
        echo jsonResponse(404, '用户不存在', null);
        exit;
    }

    $pdo->beginTransaction();

    $hasTransactionIdColumn = false;
    $colStmt = $pdo->query("SHOW COLUMNS FROM `subscription_records` LIKE 'transaction_id'");
    if ($colStmt && $colStmt->fetch(PDO::FETCH_ASSOC)) {
        $hasTransactionIdColumn = true;
    }

    $hasUniqueSubscriptionId = false;
    $idxStmt = $pdo->query("SHOW INDEX FROM `subscription_records` WHERE `Column_name` = 'subscription_id' AND `Non_unique` = 0");
    if ($idxStmt && $idxStmt->fetch(PDO::FETCH_ASSOC)) {
        $hasUniqueSubscriptionId = true;
    }

    if ($hasUniqueSubscriptionId) {
        if ($hasTransactionIdColumn) {
            $sql = "INSERT INTO `subscription_records`
                (`subscription_id`, `user_id`, `product_id`, `transaction_id`, `subscription_status`, `start_time`, `expire_time`, `original_transaction_id`)
                VALUES
                (?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                `product_id` = VALUES(`product_id`),
                `transaction_id` = VALUES(`transaction_id`),
                `subscription_status` = VALUES(`subscription_status`),
                `start_time` = VALUES(`start_time`),
                `expire_time` = VALUES(`expire_time`),
                `original_transaction_id` = VALUES(`original_transaction_id`),
                `updated_at` = CURRENT_TIMESTAMP";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $subscriptionId,
                $userId,
                $productId,
                $transactionId,
                $subscriptionStatus,
                $startTime,
                $expireTime,
                $originalTransactionId
            ]);
        } else {
            $sql = "INSERT INTO `subscription_records`
                (`subscription_id`, `user_id`, `product_id`, `subscription_status`, `start_time`, `expire_time`, `original_transaction_id`)
                VALUES
                (?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                `product_id` = VALUES(`product_id`),
                `subscription_status` = VALUES(`subscription_status`),
                `start_time` = VALUES(`start_time`),
                `expire_time` = VALUES(`expire_time`),
                `original_transaction_id` = VALUES(`original_transaction_id`),
                `updated_at` = CURRENT_TIMESTAMP";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $subscriptionId,
                $userId,
                $productId,
                $subscriptionStatus,
                $startTime,
                $expireTime,
                $originalTransactionId
            ]);
        }
    } else {
        $stmt = $pdo->prepare("SELECT `id` FROM `subscription_records` WHERE `subscription_id` = ? ORDER BY `id` DESC LIMIT 1");
        $stmt->execute([$subscriptionId]);
        $existingId = $stmt->fetchColumn();

        if ($existingId) {
            if ($hasTransactionIdColumn) {
                $stmt = $pdo->prepare("UPDATE `subscription_records`
                    SET `user_id` = ?, `product_id` = ?, `transaction_id` = ?, `subscription_status` = ?, `start_time` = ?, `expire_time` = ?, `original_transaction_id` = ?, `updated_at` = CURRENT_TIMESTAMP
                    WHERE `id` = ?");
                $stmt->execute([$userId, $productId, $transactionId, $subscriptionStatus, $startTime, $expireTime, $originalTransactionId, $existingId]);
            } else {
                $stmt = $pdo->prepare("UPDATE `subscription_records`
                    SET `user_id` = ?, `product_id` = ?, `subscription_status` = ?, `start_time` = ?, `expire_time` = ?, `original_transaction_id` = ?, `updated_at` = CURRENT_TIMESTAMP
                    WHERE `id` = ?");
                $stmt->execute([$userId, $productId, $subscriptionStatus, $startTime, $expireTime, $originalTransactionId, $existingId]);
            }
        } else {
            if ($hasTransactionIdColumn) {
                $stmt = $pdo->prepare("INSERT INTO `subscription_records`
                    (`subscription_id`, `user_id`, `product_id`, `transaction_id`, `subscription_status`, `start_time`, `expire_time`, `original_transaction_id`)
                    VALUES
                    (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$subscriptionId, $userId, $productId, $transactionId, $subscriptionStatus, $startTime, $expireTime, $originalTransactionId]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO `subscription_records`
                    (`subscription_id`, `user_id`, `product_id`, `subscription_status`, `start_time`, `expire_time`, `original_transaction_id`)
                    VALUES
                    (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$subscriptionId, $userId, $productId, $subscriptionStatus, $startTime, $expireTime, $originalTransactionId]);
            }
        }
    }

    $stmt = $pdo->prepare("SELECT MAX(`expire_time`) AS `max_expire`
        FROM `subscription_records`
        WHERE `user_id` = ?
          AND `subscription_status` = 'active'
          AND `expire_time` > NOW()");
    $stmt->execute([$userId]);
    $maxExpire = $stmt->fetchColumn();

    if ($maxExpire) {
        $stmt = $pdo->prepare("UPDATE `users` SET `is_vip` = 1, `vip_expire_time` = ? WHERE `user_id` = ?");
        $stmt->execute([$maxExpire, $userId]);
    } else {
        $stmt = $pdo->prepare("UPDATE `users` SET `is_vip` = 0, `vip_expire_time` = NULL WHERE `user_id` = ?");
        $stmt->execute([$userId]);
    }

    $pdo->commit();

    echo jsonResponse(200, '同步成功', [
        'subscription_id' => $subscriptionId,
        'user_id' => $userId,
        'is_vip' => $maxExpire ? true : false,
        'vip_expire_time' => $maxExpire ?: null
    ]);
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo jsonResponse(500, '系统错误: ' . $e->getMessage(), null);
}
