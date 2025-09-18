<?php
require_once '../../prepend.php';

// Check if user is admin
$is_admin = false;
if ($is_logged_in->isLoggedIn()) {
    $is_admin = $is_logged_in->isAdmin();
}

if (!$is_admin) {
    header('Location: /');
    exit;
}

$page_title = 'Puzzle Builder';
$page_description = 'Create custom slide puzzles';

// Set up template variables
$username = "";
if ($is_logged_in->isLoggedIn()) {
    $username = $is_logged_in->getLoggedInUsername();
}

// Check if user is experienced (3+ solved puzzles) for auto-hide UI feature
$is_experienced = false;
if ($is_logged_in->isLoggedIn()) {
    $experienceChecker = new AreYouExperienced($mla_database);
    $is_experienced = $experienceChecker->DesuKa($is_logged_in->loggedInID());
}
?>

<?php include '../../templates/layout/admin_base.tpl.php'; ?>

<div class="PagePanel slide-practice">
  <div class="wrap">
    <header>
      <div class="top_controls">
        <label>Grid: <select id="gridSize">
          <option value="5">5×5</option>
          <option value="6">6×6</option>
          <option value="7" selected>7×7</option>
          <option value="8">8×8</option>
        </select></label>
        <select id="difficulty">
          <option value="easy">Easy</option>
          <option value="medium" selected>Medium</option>
          <option value="hard">Hard</option>
        </select>
      </div>
      <div class="lower_controls">
        <div class="builder-controls" id="builderControls">
          <button id="clearPathBtn">Clear Path</button>
          <button id="randomBarriersBtn">Random Barriers</button>
          <div class="builder-option">
            <button id="addBarriersBtn">Add Barriers</button>
            <div class="density-control">
              <button class="density-btn" id="barrierDownBtn">−</button>
              <span id="barrierCount">6</span>
              <button class="density-btn" id="barrierUpBtn">+</button>
            </div>
          </div>
          <div class="builder-option">
            <button id="addNumbersBtn">Add Numbers</button>
            <div class="density-control">
              <button class="density-btn" id="numberDownBtn">−</button>
              <span id="numberCount">4</span>
              <button class="density-btn" id="numberUpBtn">+</button>
            </div>
          </div>
          <button id="testPlayBtn">Test Play</button>
          <button id="saveBuilderBtn">Save Puzzle</button>
        </div>
      </div>
    </header>

    <div class="hint">Draw a path that visits all 49 cells exactly once. Click path ends to switch between extending start or end.</div>

    <div class="stage">
      <canvas id="board" width="800" height="800" aria-label="Slide grid"></canvas>
    </div>

  </div>
</div>

<script type="module">
import { SlideBuilder } from '../js/builder.js';

(function(){
  const builder = new SlideBuilder('board');

  // Initialize builder
  builder.initializeBuilder();

  // Handle grid size changes
  document.getElementById('gridSize').addEventListener('change', (e) => {
    const newSize = parseInt(e.target.value, 10);
    builder.setGridSize(newSize);
  });

  // Handle window resize
  window.addEventListener('resize', () => builder.resize());
})();
</script>

<style>
.builder-controls {
  display: flex;
  gap: 1rem;
  align-items: center;
  flex-wrap: wrap;
}

.builder-option {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.density-control {
  display: flex;
  align-items: center;
  gap: 0.25rem;
}

.density-btn {
  width: 24px;
  height: 24px;
  border: 1px solid #ccc;
  background: white;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 14px;
}

.density-btn:hover {
  background: #f0f0f0;
}

.barrier-editing-mode {
  cursor: crosshair;
}

.number-placement-mode {
  cursor: pointer;
}

#barrierCount, #numberCount {
  min-width: 20px;
  text-align: center;
  font-weight: bold;
}
</style>
