<?php

# Extract DreamHost project root: /home/username/domain.com
preg_match('#^(/home/[^/]+/[^/]+)#', __DIR__, $matches);
include_once $matches[1] . '/prepend.php';

// Require admin access
if (!$is_logged_in->isLoggedIn() || !$is_logged_in->isAdmin()) {
    http_response_code(403);
    exit('Admin access required');
}

header('Content-Type: text/plain');

$startTime = microtime(true);
echo "Starting manual puzzle buffer fill...\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n\n";

try {
    $generator = new BackgroundPuzzleGenerator($mla_database);

    // Show initial status
    $initialStatus = $generator->getBufferStatus();
    echo "Initial buffer status:\n";
    foreach ($initialStatus as $difficulty => $count) {
        echo "  {$difficulty}: {$count} puzzles\n";
    }
    echo "\n";

    // Generate puzzles
    echo "Generating puzzles...\n";
    $stats = $generator->maintainBuffer();

    // Show results
    echo "\nGeneration results:\n";
    $totalGenerated = 0;
    foreach ($stats as $difficulty => $count) {
        echo "  {$difficulty}: {$count} new puzzles\n";
        $totalGenerated += $count;
    }

    echo "\nTotal generated: {$totalGenerated} puzzles\n";

    // Show final status
    $finalStatus = $generator->getBufferStatus();
    echo "\nFinal buffer status:\n";
    foreach ($finalStatus as $difficulty => $count) {
        echo "  {$difficulty}: {$count} puzzles\n";
    }

    $executionTime = round((microtime(true) - $startTime) * 1000);
    echo "\nExecution time: {$executionTime}ms\n";
    echo "Buffer fill complete!\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
