<?php
/**
 * 批量操作处理（视频+文案）
 */
require_once '../common/session.php';
require_once '../../common/db.php';

checkAdminLogin();

$action = $_POST['action'] ?? '';
$ids = $_POST['ids'] ?? [];

if (empty($action) || empty($ids)) {
    header('Location: list.php?msg=请选择要操作的项目');
    exit;
}

global $pdo;

try {
    $pdo->beginTransaction();

    if ($action === 'delete') {
        // 批量删除
        foreach ($ids as $materialId) {
            // 删除分类关联
            $stmt = $pdo->prepare("DELETE FROM `category_relations`
                                  WHERE `material_id` = ? AND `material_type` = 3");
            $stmt->execute([$materialId]);

            // 删除文案
            $stmt = $pdo->prepare("DELETE FROM `video_text_contents` WHERE `material_id` = ?");
            $stmt->execute([$materialId]);

            // 删除收藏记录
            $stmt = $pdo->prepare("DELETE FROM `user_favorites`
                                  WHERE `material_id` = ? AND `material_type` = 3");
            $stmt->execute([$materialId]);

            // 删除下载记录
            $stmt = $pdo->prepare("DELETE FROM `download_logs`
                                  WHERE `material_id` = ? AND `material_type` = 3");
            $stmt->execute([$materialId]);

            // 删除复制记录
            $stmt = $pdo->prepare("DELETE FROM `copy_logs`
                                  WHERE `material_id` = ? AND `material_type` = 3");
            $stmt->execute([$materialId]);

            // 删除隐藏记录
            $stmt = $pdo->prepare("DELETE FROM `user_hidden_materials`
                                  WHERE `material_id` = ? AND `material_type` = 3");
            $stmt->execute([$materialId]);

            // 删除举报记录
            $stmt = $pdo->prepare("DELETE FROM `material_reports`
                                  WHERE `material_id` = ? AND `material_type` = 3");
            $stmt->execute([$materialId]);

            // 删除素材
            $stmt = $pdo->prepare("DELETE FROM `video_text_materials` WHERE `material_id` = ?");
            $stmt->execute([$materialId]);
        }

        $msg = "成功删除 " . count($ids) . " 条记录";

    } elseif ($action === 'update_category') {
        // 批量修改分类
        $categoryIds = !empty($_POST['category_ids']) ? array_map('trim', explode(',', $_POST['category_ids'])) : [];

        foreach ($ids as $materialId) {
            // 删除旧分类关联
            $stmt = $pdo->prepare("DELETE FROM `category_relations`
                                  WHERE `material_id` = ? AND `material_type` = 3");
            $stmt->execute([$materialId]);

            // 添加新分类关联
            if (!empty($categoryIds)) {
                $stmt = $pdo->prepare("INSERT IGNORE INTO `category_relations`
                                      (`category_id`, `material_id`, `material_type`)
                                      VALUES (?, ?, 3)");
                foreach ($categoryIds as $categoryId) {
                    if (!empty($categoryId)) {
                        $stmt->execute([$categoryId, $materialId]);
                    }
                }
            }
        }

        $msg = "成功更新 " . count($ids) . " 条记录的分类";

    } elseif ($action === 'toggle_status') {
        // 批量切换状态
        $status = intval($_POST['status'] ?? 1);

        $stmt = $pdo->prepare("UPDATE `video_text_materials` SET `status` = ? WHERE `material_id` = ?");
        foreach ($ids as $materialId) {
            $stmt->execute([$status, $materialId]);
        }

        $statusText = $status == 1 ? '上架' : '下架';
        $msg = "成功将 " . count($ids) . " 条记录设置为{$statusText}";
    }

    $pdo->commit();
    header('Location: list.php?msg=' . urlencode($msg));

} catch (Exception $e) {
    $pdo->rollBack();
    header('Location: list.php?msg=操作失败：' . urlencode($e->getMessage()));
}
