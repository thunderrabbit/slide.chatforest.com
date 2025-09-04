<?php

# Extract DreamHost project root: /home/username/domain.com
preg_match('#^(/home/[^/]+/[^/]+)#', __DIR__, $matches);
include_once $matches[1] . '/prepend.php';

// Require admin access
if (!$is_logged_in->isLoggedIn() || !$is_logged_in->isAdmin()) {
    http_response_code(403);
    exit('Admin access required');
}

header('Content-Type: application/json');

try {
    $backgroundGen = new BackgroundPuzzleGenerator($mla_database);

    // Get buffer status
    $bufferStatus = $backgroundGen->getBufferStatus();

    // Get recent generation stats
    $stmt = $mla_database->prepare("
        SELECT grid_size, difficulty, generation_time_ms, success, error_message, created_at
        FROM puzzle_generation_stats
        ORDER BY created_at DESC
        LIMIT 50
    ");
    $stmt->execute();
    $recentStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate average generation times
    $avgTimes = [];
    foreach (['easy', 'medium', 'hard'] as $difficulty) {
        $stmt = $mla_database->prepare("
            SELECT AVG(generation_time_ms) as avg_time,
                   COUNT(*) as total_count,
                   SUM(CASE WHEN success = 1 THEN 1 ELSE 0 END) as success_count
            FROM puzzle_generation_stats
            WHERE grid_size = 7 AND difficulty = ? AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        $stmt->execute([$difficulty]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        $avgTimes[$difficulty] = [
            'avg_time_ms' => round($result['avg_time'] ?? 0),
            'total_attempts' => (int)$result['total_count'],
            'success_rate' => $result['total_count'] > 0 ? round(($result['success_count'] / $result['total_count']) * 100, 1) : 0
        ];
    }

    echo json_encode([
        'buffer_status' => $bufferStatus,
        'needs_refill' => $backgroundGen->needsRefill(),
        'avg_generation_stats_24h' => $avgTimes,
        'recent_generation_log' => $recentStats
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
