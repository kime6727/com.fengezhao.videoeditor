<?php
/**
 * 视频素材批量操作处理
 */
require_once __DIR__ . '/../common/session.php';
require_once __DIR__ . '/../../common/db.php';
require_once __DIR__ . '/../../common/functions.php';

checkAdminLogin();

global $pdo;

$action = $_POST['action'] ?? '';
$materialType = intval($_POST['material_type'] ?? 0);
$selectedIds = $_POST['selected_ids'] ?? [];

if (empty($selectedIds)) {
    header('Location: list.php?error=' . urlencode('请选择要操作的素材'));
    exit;
}

if (empty($action)) {
    header('Location: list.php?error=' . urlencode('请选择操作类型'));
    exit;
}

try {
    $pdo->beginTransaction();

    $successCount = 0;
    $tableName = '';

    // 根据素材类型确定表名
    switch ($materialType) {
        case 1:
            $tableName = 'video_materials';
            break;
        case 2:
            $tableName = 'image_text_materials';
            break;
        case 3:
            $tableName = 'video_text_materials';
            break;
        case 4:
            $tableName = 'text_materials';
            break;
        default:
            throw new Exception('无效的素材类型');
    }

    $placeholders = str_repeat('?,', count($selectedIds) - 1) . '?';

    switch ($action) {
        case '1': // 批量上架
        case '2': // 批量下架
            $newStatus = $action === '1' ? 1 : 0;
            $stmt = $pdo->prepare("UPDATE `{$tableName}` SET `status` = ? WHERE `material_id` IN ({$placeholders})");
            $stmt->execute(array_merge([$newStatus], $selectedIds));
            $successCount = $stmt->rowCount();
            $statusText = $newStatus === 1 ? '上架' : '下架';
            header('Location: list.php?success=' . urlencode("成功将 {$successCount} 个素材设置为{$statusText}状态"));
            break;

        case '3': // 批量删除
            // 先删除分类关联
            $stmt = $pdo->prepare("DELETE FROM `category_relations` WHERE `material_id` IN ({$placeholders}) AND `material_type` = ?");
            $stmt->execute(array_merge($selectedIds, [$materialType]));

            // 删除收藏记录
            $stmt = $pdo->prepare("DELETE FROM `user_favorites` WHERE `material_id` IN ({$placeholders}) AND `material_type` = ?");
            $stmt->execute(array_merge($selectedIds, [$materialType]));

            // 删除下载记录
            $stmt = $pdo->prepare("DELETE FROM `download_logs` WHERE `material_id` IN ({$placeholders}) AND `material_type` = ?");
            $stmt->execute(array_merge($selectedIds, [$materialType]));

            // 删除素材主表记录
            $stmt = $pdo->prepare("DELETE FROM `{$tableName}` WHERE `material_id` IN ({$placeholders})");
            $stmt->execute($selectedIds);
            $successCount = $stmt->rowCount();
            header('Location: list.php?success=' . urlencode("成功删除 {$successCount} 个素材"));
            break;

        case '4': // 批量修改发布者
            $authorId = $_POST['author_id'] ?? '';
            if (empty($authorId)) {
                throw new Exception('请选择发布者');
            }
            $stmt = $pdo->prepare("UPDATE `{$tableName}` SET `author_id` = ? WHERE `material_id` IN ({$placeholders})");
            $stmt->execute(array_merge([$authorId], $selectedIds));
            $successCount = $stmt->rowCount();
            header('Location: list.php?success=' . urlencode("成功修改 {$successCount} 个素材的发布者"));
            break;

        default:
            throw new Exception('未知的操作类型');
    }

    $pdo->commit();

} catch (Exception $e) {
    $pdo->rollBack();
    header('Location: list.php?error=' . urlencode('操作失败：' . $e->getMessage()));
}
?>