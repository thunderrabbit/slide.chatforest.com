<div class="PagePanel">
    What's up <?= $username ?>? <br />
</div>
<h1>Welcome to the MarbleTrack3 Admin Dashboard</h1>
<p>This page can show numbers of workers, parts, snippets, etc</p>
<?php
if ($has_pending_migrations) {
        echo "<h3>Pending DB Migrations</h3>";
        echo "<a href='/admin/migrate_tables.php'>Click here to migrate tables</a>";
    }
?>

<h3>Puzzle Buffer Management</h3>
<p>Manage pre-generated 7x7 puzzles to improve performance:</p>
<ul>
    <li>TODO:  make these values show up in a dashboard widget, not just a link to JSON: <a href="/admin/puzzle_buffer_status.php">View Buffer Status & Stats</a> - Check current buffer levels and generation performance</li>
    <li><a href="/admin/fill_puzzle_buffer.php">Manual Buffer Fill</a> - Manually generate puzzles to fill the buffer</li>
</ul>

<div class="fix">
    <p>Sentimental version: <?= $site_version ?></p>
</div>
