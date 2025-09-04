<?php
/**
 * Background puzzle generation script
 * Run this from cron every few minutes to maintain puzzle buffer
 *
 * Example cron entry:
 * * * * * * /usr/bin/php /home/barefoot_rob/slide.chatforest.com/scripts/generate_puzzle_buffer.php >> /home/barefoot_rob/logs/puzzle_generation.log 2>&1
 */

# Extract DreamHost project root: /home/username/domain.com
preg_match('#^(/home/[^/]+/[^/]+)#', __DIR__, $matches);
include_once $matches[1] . '/prepend.php';

$startTime = microtime(true);
$logPrefix = "[" . date('Y-m-d H:i:s') . "] ";

try {
    $generator = new BackgroundPuzzleGenerator($mla_database);

    // Check current buffer status
    $status = $generator->getBufferStatus();
    echo $logPrefix . "Buffer status - Easy: {$status['easy']}, Medium: {$status['medium']}, Hard: {$status['hard']}\n";

    // Only run if any difficulty needs refill
    if (!$generator->needsRefill()) {
        echo $logPrefix . "Buffer is full, no generation needed\n";
        exit(0);
    }

    // Generate puzzles
    echo $logPrefix . "Starting background puzzle generation...\n";
    $stats = $generator->maintainBuffer();

    $totalGenerated = array_sum($stats);
    $executionTime = round((microtime(true) - $startTime) * 1000);

    echo $logPrefix . "Generation complete - Generated: " . json_encode($stats) . " (Total: {$totalGenerated}) in {$executionTime}ms\n";

    // Final status
    $finalStatus = $generator->getBufferStatus();
    echo $logPrefix . "Final buffer status - Easy: {$finalStatus['easy']}, Medium: {$finalStatus['medium']}, Hard: {$finalStatus['hard']}\n";

} catch (Exception $e) {
    echo $logPrefix . "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
