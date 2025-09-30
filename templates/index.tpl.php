<div class="PagePanel slide-practice">
  <div class="wrap">
    <header>
      <div class="top_controls">
        <label>Grid: <select id="gridSize">
          <option value="5" <?= isset($selected_grid_size) && $selected_grid_size == 5 ? 'selected' : '' ?>>5×5</option>
          <option value="6" <?= !isset($selected_grid_size) || $selected_grid_size == 6 ? 'selected' : '' ?>>6×6</option>
          <option value="7" <?= isset($selected_grid_size) && $selected_grid_size == 7 ? 'selected' : '' ?>>7×7</option>
          <option value="8" <?= isset($selected_grid_size) && $selected_grid_size == 8 ? 'selected' : '' ?>>8×8</option>
        </select></label>
        <select id="difficulty">
          <option value="easy">Easy</option>
          <option value="medium" selected>Medium</option>
          <option value="hard">Hard</option>
        </select>
        <?php if($is_admin): ?>
        <a href="/builder/" class="builder-link">Builder</a>
        <?php endif; ?>
      </div>
      <div class="lower_controls">
          <button id="puzzleBtn">New</button>
          <button id="earliestUnplayedBtn">Play Earliest Unplayed</button>
          <button id="solutionBtn">Solve</button>
          <?php if(isset($puzzle_id) && $puzzle_id && isset($puzzle_code) && $puzzle_code): ?>
          <div class="puzzle-nav">
            <?php if(isset($prev_puzzle_code) && $prev_puzzle_code): ?>
            <a href="/puzzle/<?= $prev_puzzle_code ?>" class="puzzle-nav-btn prev" title="Previous puzzle">← Prev</a>
            <?php else: ?>
            <span class="puzzle-nav-btn prev disabled">-----</span>
            <?php endif; ?>

            <a href="/puzzle/<?= $puzzle_code ?>" class="puzzle-info" title="Current puzzle">Puzzle #<?= $puzzle_id ?></a>

            <?php if(isset($next_puzzle_code) && $next_puzzle_code): ?>
            <a href="/puzzle/<?= $next_puzzle_code ?>" class="puzzle-nav-btn next" title="Next puzzle">Next →</a>
            <?php else: ?>
            <span class="puzzle-nav-btn next disabled">-----</span>
            <?php endif; ?>

            <button id="nextUnplayedBtn" class="puzzle-nav-btn next-unplayed" title="Next unplayed puzzle">Next Unplayed →</button>
          </div>
          <?php endif; ?>
        </div>
    </header>

    <div class="hint">Start with 1; drag one finger to draw; slide back to erase (backtrack). Long‑press to clear. Fill the entire board, visiting 1, 2, 3... in order and END on the highest number.</div>

    <div class="stage">
      <canvas id="board" width="800" height="800" aria-label="Slide grid"></canvas>
      <button id="escapeBtn" class="escape-button" title="Show menu">▲</button>
    </div>

    <div class="leaderboard-section">
      <?php if(!$username): ?>
      <div class="anonymous-times">
        <h3>Your Times (Local)</h3>
        <div id="anonymous-times"></div>
      </div>
      <?php endif; ?>

      <div class="global-times">
        <h3>Global Leaderboard</h3>
        <div id="global-times"></div>
      </div>
    </div>

  </div>
</div>

<script type="module">
import { SlideGame } from '/js/game.js';

(function(){
  // Puzzle data from server (for loading existing puzzles)
  const puzzleData = <?= isset($puzzle_data) ? $puzzle_data : 'null' ?>;
  const puzzleId = <?= isset($puzzle_id) && $puzzle_id ? $puzzle_id : 'null' ?>;
  const puzzleCode = <?= isset($puzzle_code) ? '"' . $puzzle_code . '"' : 'null' ?>;
  const username = '<?= $username ?>';
  const isExperienced = <?= $is_experienced ? 'true' : 'false' ?>;

  // Show server version for debugging
  console.log('🚀 Server Version:', '<?= SENTIMENTAL_VERSION ?>');
  console.log('🚀 Cache Buster:', <?= $firefox_cache_buster ?>);

  // Initialize the game
  const game = new SlideGame('board', {
    puzzleData: puzzleData,
    puzzleId: puzzleId,
    puzzleCode: puzzleCode,
    username: username,
    isExperienced: isExperienced
  });

  // Load puzzle data if available
  if (puzzleData) {
    game.loadPuzzleData(puzzleData);
  }

  // Handle grid size changes
  document.getElementById('gridSize').addEventListener('change', (e) => {
    const newSize = parseInt(e.target.value, 10);

    // If we have an active puzzle loaded, don't change the grid size immediately
    // The new size will be used when generating the next puzzle
    if (game.puzzleMode && (game.puzzleData || game.edgeBarriers.size > 0 || game.numberHints.size > 0)) {
      console.log('🔒 Grid size will change on next puzzle generation (current puzzle preserved)');
      return;
    }

    // Safe to change grid size for practice mode or empty state
    game.justSetNewPlannedGridSize(newSize);
  });

  // Handle puzzle generation
  document.getElementById('puzzleBtn').addEventListener('click', () => {
    // Get selected grid size and difficulty, then generate new puzzle via PHP
    const selectedGridSize = parseInt(document.getElementById('gridSize').value, 10);
    const difficulty = document.getElementById('difficulty').value;

    // Update N to the selected grid size and resize canvas
    game.N = selectedGridSize;
    game.resize();

    // Always use PHP generator for new puzzles
    console.log('🎲 Generating new', selectedGridSize + 'x' + selectedGridSize, 'puzzle with difficulty:', difficulty);
    game.generatePuzzleUsingPHP(difficulty);
  });

  // Handle solution toggle
  document.getElementById('solutionBtn').addEventListener('click', () => {
    game.toggleSolution();
  });

  // Handle earliest unplayed puzzle
  document.getElementById('earliestUnplayedBtn').addEventListener('click', async () => {
    try {
      const selectedGridSize = parseInt(document.getElementById('gridSize').value, 10);
      const response = await fetch('/earliest_unplayed.php?grid_size=' + selectedGridSize);
      const data = await response.json();

      if (data.success) {
        // Navigate to the earliest unplayed puzzle with grid size parameter
        window.location.href = '/puzzle/' + data.puzzle_code + '?grid_size=' + data.grid_size;
      } else {
        // Handle case where all puzzles are solved or no puzzles exist
        alert(data.message || 'No unplayed puzzles found');
      }
    } catch (error) {
      console.error('Error finding earliest unplayed puzzle:', error);
      alert('Error finding earliest unplayed puzzle');
    }
  });

  // Handle next unplayed puzzle (only exists on puzzle pages)
  const nextUnplayedBtn = document.getElementById('nextUnplayedBtn');
  if (nextUnplayedBtn) {
    nextUnplayedBtn.addEventListener('click', async () => {
      try {
        const currentPuzzleId = puzzleId || (puzzleData && puzzleData.puzzle_id);
        if (!currentPuzzleId) {
          console.error('No current puzzle ID available');
          alert('Error: Current puzzle ID not found');
          return;
        }

        const response = await fetch('/next_unplayed.php?current_puzzle_id=' + currentPuzzleId);
        const data = await response.json();

        if (data.success) {
          // Navigate to the next unplayed puzzle
          window.location.href = '/puzzle/' + data.puzzle_code;
        } else if (data.redirect_to_new) {
          // No more unplayed puzzles - redirect to main page for new puzzle
          window.location.href = '/';
        } else {
          alert(data.message || 'No more unplayed puzzles found');
        }
      } catch (error) {
        console.error('Error finding next unplayed puzzle:', error);
        alert('Error finding next unplayed puzzle');
      }
    });
  }

  // Initialize the game
  game.resize();
  window.addEventListener('resize', () => game.resize());

  // Handle escape button for experienced users
  const escapeBtn = document.getElementById('escapeBtn');
  if (escapeBtn && isExperienced) {
    // Show escape button when UI is hidden
    const observer = new MutationObserver(() => {
      const header = document.querySelector('header');
      const isHidden = header && (header.style.height === '0px' || header.style.height === '0');
      console.log('🔍 Escape button check - header height:', header?.style.height, 'isHidden:', isHidden);

      if (isHidden) {
        escapeBtn.classList.add('visible');
        console.log('🔍 Escape button made visible');
      } else {
        escapeBtn.classList.remove('visible');
        console.log('🔍 Escape button hidden');
      }
    });

    observer.observe(document.querySelector('header'), {
      attributes: true,
      attributeFilter: ['style']
    });

    // Handle escape button click
    escapeBtn.addEventListener('click', () => {
      game.showUIForExperiencedUsers();
    });

    // Initial check in case UI is already hidden
    setTimeout(() => {
      const header = document.querySelector('header');
      const isHidden = header && (header.style.height === '0px' || header.style.height === '0');
      console.log('🔍 Initial escape button check - header height:', header?.style.height, 'isHidden:', isHidden);

      if (isHidden) {
        escapeBtn.classList.add('visible');
        console.log('🔍 Initial escape button made visible');
      }
    }, 100);
  }

  // Check if user just registered or logged in and trigger migration
  const urlParams = new URLSearchParams(window.location.search);

  if (username && (urlParams.has('newuser') || urlParams.has('returning'))) {
    // User just logged in or registered, migrate their localStorage times
    game.migrateAnonymousTimes();

    // Clean up the URL parameter
    if (urlParams.has('newuser') || urlParams.has('returning')) {
      const cleanUrl = window.location.pathname;
      window.history.replaceState({}, document.title, cleanUrl);
    }
  }

  // Load leaderboards for existing puzzles
  if (puzzleData) {
    if (!username) {
      game.loadAnonymousTimes(); // Load local times for anonymous users
    }
    game.loadGlobalTimes(); // Always load global leaderboard
  }

  // If no puzzle data, automatically generate a new puzzle
  if (!puzzleData) {
    console.log('🎲 No initial puzzleData, generating new puzzle');
    const difficulty = document.getElementById('difficulty').value;
    const selectedGridSize = parseInt(document.getElementById('gridSize').value, 10);

    // Update N to the selected grid size for initial puzzle generation
    game.N = selectedGridSize;

    // Always use PHP generator for all puzzle sizes
    console.log('🎲 Grid size', game.N + 'x' + game.N, '- using PHP generator for initial puzzle');
    game.generatePuzzleUsingPHP(difficulty);
  }

})();
</script>

<style>
.builder-link {
  display: inline-block;
  padding: 0.5rem 1rem;
  background: #ffb556;
  color: #2a3146;
  text-decoration: none;
  border-radius: 4px;
  font-weight: bold;
  margin-left: 1rem;
}

.builder-link:hover {
  background: #ffa726;
}

.escape-button {
  position: absolute;
  top: 20px;
  right: 20px;
  width: 40px;
  height: 40px;
  background: rgba(42, 49, 70, 0.8);
  color: white;
  border: none;
  border-radius: 50%;
  font-size: 18px;
  cursor: pointer;
  z-index: 1000;
  opacity: 0;
  transition: opacity 0.3s ease;
  display: flex;
  align-items: center;
  justify-content: center;
}

.escape-button:hover {
  background: rgba(42, 49, 70, 1);
}

.escape-button.visible {
  opacity: 1;
}

.stage {
  position: relative;
}
</style>
