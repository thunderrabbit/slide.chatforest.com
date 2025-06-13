<div class="PagePanel">
    What's up <?= $username ?>? <br />
</div>
<h1>Welcome to the MarbleTrack3 Admin Dashboard</h1>
<p>This page can show numbers of workers, parts, snippets, etc</p>

<?php
if ($has_pending_migrations) {
        echo "<h3>Pending DB Migrations</h3><ul>";
        foreach ($pending_migrations as $migration) {
            echo "<li>$migration <button onclick=\"applyMigration('$migration')\">Apply</button></li>";
        }
        echo "</ul>";
    }
?>

<div class="PagePanel">
    <a href="/logout/">Logout</a> <br />
</div>
<div class="fix">
    <p>Sentimental version: <?= $site_version ?></p>
</div>
