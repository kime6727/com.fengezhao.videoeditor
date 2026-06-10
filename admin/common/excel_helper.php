<?php
/**
 * CSV导入导出辅助类
 * 使用CSV格式，PHP原生支持，无需安装额外库
 */

// 修复路径：从 backend/admin/common/excel_helper.php 到 backend/common/db.php
// 应该是 ../common/db.php（向上到admin，再进入common）
$dbPath = dirname(__DIR__) . '/../common/db.php';
$functionsPath = dirname(__DIR__) . '/../common/functions.php';

if (!file_exists($dbPath)) {
    // 尝试备用路径
    $dbPath = __DIR__ . '/../../common/db.php';
    $functionsPath = __DIR__ . '/../../common/functions.php';
}

require_once $dbPath;
require_once $functionsPath;

/**
 * 创建单视频素材CSV模板
 */
function createVideoTemplate() {
    // 设置UTF-8 BOM，确保Excel正确显示中文
    $bom = "\xEF\xBB\xBF";
    
    // 表头
    $headers = ['视频名称', '视频URL', '缩略图URL', '分类ID（多个用逗号分隔）', '状态（1-上架，0-下架）'];
    
    // 示例数据
    $examples = [
        ['示例视频1', 'https://example.com/video1.mp4', 'https://example.com/thumb1.jpg', 'cat_id_1,cat_id_2', '1'],
        ['示例视频2', 'https://example.com/video2.mp4', 'https://example.com/thumb2.jpg', 'cat_id_1', '1'],
    ];
    
    // 输出CSV
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment;filename="单视频素材导入模板.csv"');
    header('Cache-Control: max-age=0');
    
    $output = fopen('php://output', 'w');
    
    // 输出BOM
    echo $bom;
    
    // 输出表头
    fputcsv($output, $headers);
    
    // 输出示例数据
    foreach ($examples as $row) {
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit;
}

/**
 * 导入单视频素材（CSV格式）
 */
function importVideos($filePath) {
    global $pdo;
    
    try {
        // 读取CSV文件
        $file = fopen($filePath, 'r');
        if (!$file) {
            return ['error' => '无法打开文件'];
        }
        
        // 跳过BOM（如果存在）
        $firstLine = fgets($file);
        if (substr($firstLine, 0, 3) === "\xEF\xBB\xBF") {
            // 有BOM，重新打开文件
            fclose($file);
            $file = fopen($filePath, 'r');
            fgets($file); // 跳过BOM行
        } else {
            // 没有BOM，重置文件指针
            rewind($file);
        }
        
        // 跳过表头
        fgetcsv($file);
        
        $successCount = 0;
        $errorCount = 0;
        $errors = [];
        $rowNum = 1; // 行号（表头是第1行）
        
        $pdo->beginTransaction();
        
        while (($row = fgetcsv($file)) !== false) {
            $rowNum++;
            
            // 跳过空行
            if (empty($row) || (count($row) < 2) || empty(trim($row[0])) || empty(trim($row[1]))) {
                continue;
            }
            
            $name = trim($row[0]);
            $videoUrl = trim($row[1]);
            $thumbnailUrl = trim($row[2] ?? '');
            $categoryIds = !empty($row[3]) ? array_map('trim', explode(',', $row[3])) : [];
            $status = intval($row[4] ?? 1);
            
            try {
                // 生成素材ID
                $materialId = generateUniqueIdWithCheck('video_materials', 'material_id');
                
                // 插入视频素材
                $stmt = $pdo->prepare("INSERT INTO `video_materials` 
                                      (`material_id`, `name`, `video_url`, `thumbnail_url`, `status`) 
                                      VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$materialId, $name, $videoUrl, $thumbnailUrl, $status]);
                
                // 添加分类关联
                if (!empty($categoryIds)) {
                    $stmt = $pdo->prepare("INSERT INTO `category_relations` 
                                          (`category_id`, `material_id`, `material_type`) 
                                          VALUES (?, ?, 1)");
                    foreach ($categoryIds as $categoryId) {
                        if (!empty($categoryId)) {
                            $stmt->execute([$categoryId, $materialId]);
                        }
                    }
                }
                
                $successCount++;
            } catch (Exception $e) {
                $errorCount++;
                $errors[] = "第{$rowNum}行: " . $e->getMessage();
            }
        }
        
        fclose($file);
        $pdo->commit();
        
        return [
            'success' => true,
            'success_count' => $successCount,
            'error_count' => $errorCount,
            'errors' => $errors
        ];
        
    } catch (Exception $e) {
        if (isset($file) && is_resource($file)) {
            fclose($file);
        }
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['error' => '导入失败：' . $e->getMessage()];
    }
}

/**
 * 创建纯文案素材CSV模板
 */
function createTextTemplate() {
    // 设置UTF-8 BOM，确保Excel正确显示中文
    $bom = "\xEF\xBB\xBF";
    
    // 表头
    $headers = ['文案内容', '分类ID（多个用逗号分隔）', '状态（1-上架，0-下架）'];
    
    // 示例数据
    $examples = [
        ['这是一条示例文案内容', 'cat_id_1,cat_id_2', '1'],
        ['这是另一条示例文案', 'cat_id_1', '1'],
    ];
    
    // 输出CSV
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment;filename="纯文案素材导入模板.csv"');
    header('Cache-Control: max-age=0');
    
    $output = fopen('php://output', 'w');
    
    // 输出BOM
    echo $bom;
    
    // 输出表头
    fputcsv($output, $headers);
    
    // 输出示例数据
    foreach ($examples as $row) {
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit;
}

/**
 * 导入纯文案素材（CSV格式）
 */
function importTexts($filePath) {
    global $pdo;
    
    try {
        // 读取CSV文件
        $file = fopen($filePath, 'r');
        if (!$file) {
            return ['error' => '无法打开文件'];
        }
        
        // 跳过BOM（如果存在）
        $firstLine = fgets($file);
        if (substr($firstLine, 0, 3) === "\xEF\xBB\xBF") {
            // 有BOM，重新打开文件
            fclose($file);
            $file = fopen($filePath, 'r');
            fgets($file); // 跳过BOM行
        } else {
            // 没有BOM，重置文件指针
            rewind($file);
        }
        
        // 跳过表头
        fgetcsv($file);
        
        $successCount = 0;
        $errorCount = 0;
        $errors = [];
        $rowNum = 1; // 行号（表头是第1行）
        
        $pdo->beginTransaction();
        
        while (($row = fgetcsv($file)) !== false) {
            $rowNum++;
            
            // 跳过空行
            if (empty($row) || empty(trim($row[0]))) {
                continue;
            }
            
            $content = trim($row[0]);
            $categoryIds = !empty($row[1]) ? array_map('trim', explode(',', $row[1])) : [];
            $status = intval($row[2] ?? 1);
            
            try {
                $materialId = generateUniqueIdWithCheck('text_materials', 'material_id');
                
                $stmt = $pdo->prepare("INSERT INTO `text_materials` (`material_id`, `content`, `status`) VALUES (?, ?, ?)");
                $stmt->execute([$materialId, $content, $status]);
                
                if (!empty($categoryIds)) {
                    $stmt = $pdo->prepare("INSERT INTO `category_relations` 
                                          (`category_id`, `material_id`, `material_type`) 
                                          VALUES (?, ?, 4)");
                    foreach ($categoryIds as $categoryId) {
                        if (!empty($categoryId)) {
                            $stmt->execute([$categoryId, $materialId]);
                        }
                    }
                }
                
                $successCount++;
            } catch (Exception $e) {
                $errorCount++;
                $errors[] = "第{$rowNum}行: " . $e->getMessage();
            }
        }
        
        fclose($file);
        $pdo->commit();
        
        return [
            'success' => true,
            'success_count' => $successCount,
            'error_count' => $errorCount,
            'errors' => $errors
        ];
        
    } catch (Exception $e) {
        if (isset($file) && is_resource($file)) {
            fclose($file);
        }
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['error' => '导入失败：' . $e->getMessage()];
    }
}


/**
 * 创建图片+文案素材CSV模板
 */
function createImageTextTemplate() {
    $bom = "\xEF\xBB\xBF";
    $headers = ['图片URL（多个用|分隔，最多9个）', '文案内容（多个用|分隔，最多30个）', '分类ID（多个用逗号分隔）', '状态（1-上架，0-下架）'];
    $examples = [
        ['https://example.com/img1.jpg|https://example.com/img2.jpg', '文案1|文案2|文案3', 'cat_id_1,cat_id_2', '1'],
        ['https://example.com/img1.jpg', '文案1', 'cat_id_1', '1'],
    ];
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment;filename="图片+文案素材导入模板.csv"');
    header('Cache-Control: max-age=0');
    $output = fopen('php://output', 'w');
    echo $bom;
    fputcsv($output, $headers);
    foreach ($examples as $row) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
}

/**
 * 导入图片+文案素材（CSV格式）
 */
function importImageTexts($filePath) {
    global $pdo;
    try {
        $file = fopen($filePath, 'r');
        if (!$file) return ['error' => '无法打开文件'];
        $firstLine = fgets($file);
        if (substr($firstLine, 0, 3) === "\xEF\xBB\xBF") {
            fclose($file);
            $file = fopen($filePath, 'r');
            fgets($file);
        } else {
            rewind($file);
        }
        fgetcsv($file);
        $successCount = 0;
        $errorCount = 0;
        $errors = [];
        $rowNum = 1;
        $pdo->beginTransaction();
        while (($row = fgetcsv($file)) !== false) {
            $rowNum++;
            if (empty($row) || empty(trim($row[0]))) continue;
            $imageUrls = !empty($row[0]) ? array_filter(array_map('trim', explode('|', $row[0]))) : [];
            $contents = !empty($row[1]) ? array_filter(array_map('trim', explode('|', $row[1]))) : [];
            $categoryIds = !empty($row[2]) ? array_map('trim', explode(',', $row[2])) : [];
            $status = intval($row[3] ?? 1);
            if (count($imageUrls) > 9) {
                $errorCount++;
                $errors[] = "第{$rowNum}行: 图片数量不能超过9张";
                continue;
            }
            if (count($contents) > 30) {
                $errorCount++;
                $errors[] = "第{$rowNum}行: 文案数量不能超过30条";
                continue;
            }
            if (empty($imageUrls) || empty($contents)) {
                $errorCount++;
                $errors[] = "第{$rowNum}行: 图片和文案不能为空";
                continue;
            }
            try {
                $materialId = generateUniqueIdWithCheck('image_text_materials', 'material_id');
                $stmt = $pdo->prepare("INSERT INTO `image_text_materials` (`material_id`, `status`) VALUES (?, ?)");
                $stmt->execute([$materialId, $status]);
                $stmt = $pdo->prepare("INSERT INTO `image_text_images` (`material_id`, `image_url`, `sort`) VALUES (?, ?, ?)");
                foreach ($imageUrls as $index => $imageUrl) {
                    $stmt->execute([$materialId, $imageUrl, $index]);
                }
                $stmt = $pdo->prepare("INSERT INTO `image_text_contents` (`material_id`, `content`, `sort`) VALUES (?, ?, ?)");
                foreach ($contents as $index => $content) {
                    $stmt->execute([$materialId, $content, $index]);
                }
                if (!empty($categoryIds)) {
                    $stmt = $pdo->prepare("INSERT INTO `category_relations` (`category_id`, `material_id`, `material_type`) VALUES (?, ?, 2)");
                    foreach ($categoryIds as $categoryId) {
                        if (!empty($categoryId)) {
                            $stmt->execute([$categoryId, $materialId]);
                        }
                    }
                }
                $successCount++;
            } catch (Exception $e) {
                $errorCount++;
                $errors[] = "第{$rowNum}行: " . $e->getMessage();
            }
        }
        fclose($file);
        $pdo->commit();
        return ['success' => true, 'success_count' => $successCount, 'error_count' => $errorCount, 'errors' => $errors];
    } catch (Exception $e) {
        if (isset($file) && is_resource($file)) fclose($file);
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['error' => '导入失败：' . $e->getMessage()];
    }
}

/**
 * 创建视频+文案素材CSV模板
 */
function createVideoTextTemplate() {
    $bom = "\xEF\xBB\xBF";
    $headers = ['视频URL', '缩略图URL（可选）', '文案内容（多个用|分隔，最多30个）', '分类ID（多个用逗号分隔）', '状态（1-上架，0-下架）'];
    $examples = [
        ['https://example.com/video1.mp4', 'https://example.com/thumb1.jpg', '文案1|文案2|文案3', 'cat_id_1,cat_id_2', '1'],
        ['https://example.com/video2.mp4', '', '文案1', 'cat_id_1', '1'],
    ];
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment;filename="视频+文案素材导入模板.csv"');
    header('Cache-Control: max-age=0');
    $output = fopen('php://output', 'w');
    echo $bom;
    fputcsv($output, $headers);
    foreach ($examples as $row) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
}

/**
 * 导入视频+文案素材（CSV格式）
 */
function importVideoTexts($filePath) {
    global $pdo;
    try {
        $file = fopen($filePath, 'r');
        if (!$file) return ['error' => '无法打开文件'];
        $firstLine = fgets($file);
        if (substr($firstLine, 0, 3) === "\xEF\xBB\xBF") {
            fclose($file);
            $file = fopen($filePath, 'r');
            fgets($file);
        } else {
            rewind($file);
        }
        fgetcsv($file);
        $successCount = 0;
        $errorCount = 0;
        $errors = [];
        $rowNum = 1;
        $pdo->beginTransaction();
        while (($row = fgetcsv($file)) !== false) {
            $rowNum++;
            if (empty($row) || empty(trim($row[0]))) continue;
            $videoUrl = trim($row[0]);
            $thumbnailUrl = trim($row[1] ?? '');
            $contents = !empty($row[2]) ? array_filter(array_map('trim', explode('|', $row[2]))) : [];
            $categoryIds = !empty($row[3]) ? array_map('trim', explode(',', $row[3])) : [];
            $status = intval($row[4] ?? 1);
            if (count($contents) > 30) {
                $errorCount++;
                $errors[] = "第{$rowNum}行: 文案数量不能超过30条";
                continue;
            }
            if (empty($contents)) {
                $errorCount++;
                $errors[] = "第{$rowNum}行: 文案不能为空";
                continue;
            }
            try {
                $materialId = generateUniqueIdWithCheck('video_text_materials', 'material_id');
                $stmt = $pdo->prepare("INSERT INTO `video_text_materials` (`material_id`, `video_url`, `thumbnail_url`, `status`) VALUES (?, ?, ?, ?)");
                $stmt->execute([$materialId, $videoUrl, $thumbnailUrl, $status]);
                $stmt = $pdo->prepare("INSERT INTO `video_text_contents` (`material_id`, `content`, `sort`) VALUES (?, ?, ?)");
                foreach ($contents as $index => $content) {
                    $stmt->execute([$materialId, $content, $index]);
                }
                if (!empty($categoryIds)) {
                    $stmt = $pdo->prepare("INSERT INTO `category_relations` (`category_id`, `material_id`, `material_type`) VALUES (?, ?, 3)");
                    foreach ($categoryIds as $categoryId) {
                        if (!empty($categoryId)) {
                            $stmt->execute([$categoryId, $materialId]);
                        }
                    }
                }
                $successCount++;
            } catch (Exception $e) {
                $errorCount++;
                $errors[] = "第{$rowNum}行: " . $e->getMessage();
            }
        }
        fclose($file);
        $pdo->commit();
        return ['success' => true, 'success_count' => $successCount, 'error_count' => $errorCount, 'errors' => $errors];
    } catch (Exception $e) {
        if (isset($file) && is_resource($file)) fclose($file);
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['error' => '导入失败：' . $e->getMessage()];
    }
}