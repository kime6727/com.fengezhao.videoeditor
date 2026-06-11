<?php
// Cleanup duplicate video records
header('Content-Type: application/json; charset=utf-8');

$dbConfig = require __DIR__ . '/config/database.php';
$dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}";

try {
    $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);
    
    // Find duplicate material_ids in video_materials
    $stmt = $pdo->query("SELECT material_id, COUNT(*) as cnt FROM video_materials GROUP BY material_id HAVING cnt > 1");
    $dupes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $result = ['duplicates_found' => count($dupes), 'deleted' => 0, 'details' => []];
    
    foreach ($dupes as $dupe) {
        $mid = $dupe['material_id'];
        $cnt = $dupe['cnt'];
        
        // Keep the newest record, delete older ones
        $stmt = $pdo->prepare("SELECT id FROM video_materials WHERE material_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$mid]);
        $keepId = $stmt->fetchColumn();
        
        $stmt = $pdo->prepare("DELETE FROM video_materials WHERE material_id = ? AND id != ?");
        $stmt->execute([$mid, $keepId]);
        $deleted = $stmt->rowCount();
        
        // Also clean up duplicate category_relations
        $stmt2 = $pdo->prepare("DELETE cr1 FROM category_relations cr1 
                                 INNER JOIN category_relations cr2 
                                 ON cr1.material_id = cr2.material_id 
                                 AND cr1.category_id = cr2.category_id 
                                 AND cr1.material_type = cr2.material_type 
                                 AND cr1.id > cr2.id");
        $stmt2->execute();
        $catDeleted = $stmt2->rowCount();
        
        $result['deleted'] += $deleted;
        $result['details'][] = [
            'material_id' => $mid,
            'kept_id' => $keepId,
            'deleted_rows' => $deleted,
            'duplicate_category_relations_removed' => $catDeleted,
        ];
    }
    
    // Final count
    $stmt = $pdo->query("SELECT COUNT(*) FROM video_materials");
    $result['remaining_videos'] = intval($stmt->fetchColumn());
    
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
