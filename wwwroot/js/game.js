/**
 * Game module for slide puzzle game
 * Contains puzzle solving, timing, and game logic
 */

import { SlideCore } from './core.js';

export class SlideGame extends SlideCore {
  constructor(canvasId, options = {}) {
    super(canvasId);

    // Game state
    this.puzzleData = options.puzzleData || null;
    this.puzzleId = options.puzzleId || null;
    this.puzzleCode = options.puzzleCode || null;
    this.username = options.username || null;
    this.isExperienced = options.isExperienced || false;

    // Timing for solve speed tracking
    this.puzzleStartTime = null;
    this.puzzleSolved = false;
    this.solveTimeRecorded = false;
    this.puzzleAlreadySolvedByUser = false;

    // Auto-hide UI for experienced users
    this.uiHidden = false;
    this.inactivityTimer = null;
    this.gameStarted = false;
    this.touchesBlocked = false; // Block touches after puzzle is won

    this.setupGameEventListeners();
  }

  /**
   * Set the grid size for the game
   * @param {number} newSize - The new grid size (e.g., 5 for 5x5)
   */
  setGridSize(newSize) {
    this.N = newSize;
    this.resize(); // Resize canvas to match new grid size
    console.log('🔧 Grid size set to', newSize + 'x' + newSize);
  }

  // --- Puzzle Generation ---
  generateHamiltonianPath() {
    // Use faster algorithm for larger grids
    if (this.N >= 7) {
      return this.generateHamiltonianPathFast();
    }
    
    // Use original backtracking for smaller grids (5x5, 6x6)
    return this.generateHamiltonianPathBacktrack();
  }

  generateHamiltonianPathFast() {
    // Fast Hamiltonian path generation using snake-like patterns with randomization
    const totalCells = this.N * this.N;
    const path = [];
    const visited = new Set();
    
    // Start from a random position
    let r = Math.floor(Math.random() * this.N);
    let c = Math.floor(Math.random() * this.N);
    
    path.push({r, c});
    visited.add(this.key(r, c));
    
    // Generate snake-like path with random turns
    while (path.length < totalCells) {
      const neighbors = this.getUnvisitedNeighbors(r, c, visited);
      
      if (neighbors.length === 0) {
        // No more neighbors - try to connect to unvisited area
        const unvisited = this.findNearestUnvisited(r, c, visited);
        if (unvisited) {
          // Create a bridge to the unvisited area
          const bridge = this.createBridge({r, c}, unvisited, visited);
          path.push(...bridge);
          bridge.forEach(cell => visited.add(this.key(cell.r, cell.c)));
          r = bridge[bridge.length - 1].r;
          c = bridge[bridge.length - 1].c;
        } else {
          break; // Shouldn't happen in a valid Hamiltonian path
        }
      } else {
        // Choose random neighbor
        const next = neighbors[Math.floor(Math.random() * neighbors.length)];
        path.push(next);
        visited.add(this.key(next.r, next.c));
        r = next.r;
        c = next.c;
      }
    }
    
    return path;
  }

  generateHamiltonianPathBacktrack() {
    // Original backtracking algorithm for smaller grids
    const visited = new Set();
    const solution = [];
    const totalCells = this.N * this.N;

    // Start from a random cell
    const start = {r: Math.floor(Math.random() * this.N), c: Math.floor(Math.random() * this.N)};
    solution.push(start);
    visited.add(this.key(start.r, start.c));

    // Recursive backtracking algorithm
    const backtrack = (currentPos) => {
      if (solution.length === totalCells) return true;

      // Try neighbors in random order
      const directions = [{r: -1, c: 0}, {r: 1, c: 0}, {r: 0, c: -1}, {r: 0, c: 1}];
      this.shuffleArray(directions);

      for (const dir of directions) {
        const next = {r: currentPos.r + dir.r, c: currentPos.c + dir.c};
        const nextKey = this.key(next.r, next.c);

        if (this.inBounds(next.r, next.c) && !visited.has(nextKey)) {
          visited.add(nextKey);
          solution.push(next);

          if (backtrack(next)) return true;

          // Backtrack - remove the cell we just added
          solution.pop();
          visited.delete(nextKey);
        }
      }
      return false;
    };

    if (backtrack(start)) {
      return solution;
    }

    // Fallback: simple spiral pattern if backtracking fails
    return this.generateSpiralPath();
  }

  getUnvisitedNeighbors(r, c, visited) {
    const directions = [{r: -1, c: 0}, {r: 1, c: 0}, {r: 0, c: -1}, {r: 0, c: 1}];
    const neighbors = [];
    
    for (const dir of directions) {
      const nr = r + dir.r;
      const nc = c + dir.c;
      if (this.inBounds(nr, nc) && !visited.has(this.key(nr, nc))) {
        neighbors.push({r: nr, c: nc});
      }
    }
    
    return neighbors;
  }

  findNearestUnvisited(r, c, visited) {
    // Find the nearest unvisited cell using BFS
    const queue = [{r, c, dist: 0}];
    const seen = new Set();
    seen.add(this.key(r, c));
    
    while (queue.length > 0) {
      const current = queue.shift();
      
      if (!visited.has(this.key(current.r, current.c))) {
        return {r: current.r, c: current.c};
      }
      
      const directions = [{r: -1, c: 0}, {r: 1, c: 0}, {r: 0, c: -1}, {r: 0, c: 1}];
      for (const dir of directions) {
        const nr = current.r + dir.r;
        const nc = current.c + dir.c;
        const key = this.key(nr, nc);
        
        if (this.inBounds(nr, nc) && !seen.has(key)) {
          seen.add(key);
          queue.push({r: nr, c: nc, dist: current.dist + 1});
        }
      }
    }
    
    return null;
  }

  createBridge(from, to, visited) {
    // Create a simple path from 'from' to 'to' avoiding visited cells
    const path = [];
    let r = from.r;
    let c = from.c;
    
    while (r !== to.r || c !== to.c) {
      const dr = Math.sign(to.r - r);
      const dc = Math.sign(to.c - c);
      
      // Try to move in the direction of the target
      if (dr !== 0 && this.inBounds(r + dr, c) && !visited.has(this.key(r + dr, c))) {
        r += dr;
      } else if (dc !== 0 && this.inBounds(r, c + dc) && !visited.has(this.key(r, c + dc))) {
        c += dc;
      } else {
        // Try alternative directions
        const directions = [{r: -1, c: 0}, {r: 1, c: 0}, {r: 0, c: -1}, {r: 0, c: 1}];
        this.shuffleArray(directions);
        
        let moved = false;
        for (const dir of directions) {
          const nr = r + dir.r;
          const nc = c + dir.c;
          if (this.inBounds(nr, nc) && !visited.has(this.key(nr, nc))) {
            r = nr;
            c = nc;
            moved = true;
            break;
          }
        }
        
        if (!moved) break; // Can't find a path
      }
      
      path.push({r, c});
    }
    
    return path;
  }

  shuffleArray(array) {
    for (let i = array.length - 1; i > 0; i--) {
      const j = Math.floor(Math.random() * (i + 1));
      [array[i], array[j]] = [array[j], array[i]];
    }
  }

  generateSpiralPath() {
    // Simple spiral fallback
    const solution = [];
    let r = 0, c = 0;
    let dr = 0, dc = 1;

    for (let i = 0; i < this.N * this.N; i++) {
      solution.push({r, c});

      // Calculate next position
      let nr = r + dr, nc = c + dc;

      // If next position is out of bounds or already visited, turn right
      if (!this.inBounds(nr, nc) || solution.some(p => p.r === nr && p.c === nc)) {
        [dr, dc] = [-dc, dr]; // Turn right
        nr = r + dr;
        nc = c + dc;
      }

      // Update position only if we're not at the last cell
      if (i < this.N * this.N - 1) {
        r = nr;
        c = nc;
      }
    }
    return solution;
  }

  validateSolutionPath(path) {
    // Check that path visits exactly N*N cells
    if (path.length !== this.N * this.N) return false;

    // Check that all cells are within bounds
    for (const cell of path) {
      if (!this.inBounds(cell.r, cell.c)) return false;
    }

    // Check that each cell is visited exactly once
    const visited = new Set();
    for (const cell of path) {
      const cellKey = this.key(cell.r, cell.c);
      if (visited.has(cellKey)) return false; // Duplicate cell
      visited.add(cellKey);
    }

    // Check that consecutive cells are adjacent
    for (let i = 1; i < path.length; i++) {
      const prev = path[i - 1];
      const curr = path[i];
      if (!this.neighbors(prev, curr)) return false; // Not adjacent
    }

    return true;
  }

  generatePuzzle(difficulty = 'medium') {
    // Reset game state for new puzzle
    this.puzzleSolved = false;
    this.touchesBlocked = false;
    this.solveTimeRecorded = false;
    this.puzzleAlreadySolvedByUser = false;
    
    this.edgeBarriers.clear();
    this.numberHints.clear();
    this.nextRequiredNumber = 1; // Reset sequence tracker for new puzzle
    this.showingSolution = false; // Hide solution for new puzzle

    // Generate solution path with validation
    let attempts = 0;
    do {
      this.solutionPath = this.generateHamiltonianPath();
      attempts++;
      if (attempts > 10) {
        // Force use spiral if backtracking keeps failing
        this.solutionPath = this.generateSpiralPath();
        break;
      }
    } while (!this.validateSolutionPath(this.solutionPath));

    // Double-check we have a valid solution
    if (!this.validateSolutionPath(this.solutionPath)) {
      console.error("Failed to generate valid solution path");
      return;
    }

    // Place number hints at random positions along the solution path
    const minHints = difficulty === 'easy' ? 6 : difficulty === 'medium' ? 4 : 3;
    const maxHints = Math.floor(this.solutionPath.length / 3); // At most 1/3 of cells
    const hintCount = Math.max(minHints, Math.min(maxHints, Math.floor(Math.random() * 4) + minHints));

    // Always include position 0 (start) and final position (end)
    const hintPositions = [0, this.solutionPath.length - 1];

    // Add random positions in between
    while (hintPositions.length < hintCount) {
      const randomPos = Math.floor(Math.random() * (this.solutionPath.length - 2)) + 1; // Exclude 0 and final
      if (!hintPositions.includes(randomPos)) {
        hintPositions.push(randomPos);
      }
    }

    // Sort positions to ensure correct numbering order
    hintPositions.sort((a, b) => a - b);

    // Place consecutive numbers at these positions
    let hintNumber = 1;
    for (const position of hintPositions) {
      const cell = this.solutionPath[position];
      // Safety check: ensure cell is within bounds
      if (this.inBounds(cell.r, cell.c)) {
        this.numberHints.set(this.key(cell.r, cell.c), hintNumber);
        hintNumber++;
      }
    }

    // Create set of solution edges (edges used in the solution path)
    const solutionEdges = new Set();
    for (let i = 0; i < this.solutionPath.length - 1; i++) {
      const curr = this.solutionPath[i];
      const next = this.solutionPath[i + 1];
      solutionEdges.add(this.edgeKey(curr.r, curr.c, next.r, next.c));
    }

    // Add edge barriers (don't block solution path edges)
    const barrierCount = Math.floor((this.N * this.N - 1) * (difficulty === 'easy' ? 0.1 : difficulty === 'medium' ? 0.15 : 0.2));
    let barrierAttempts = 0;
    while (this.edgeBarriers.size < barrierCount && barrierAttempts < 200) {
      // Pick random adjacent cells
      const r1 = Math.floor(Math.random() * this.N);
      const c1 = Math.floor(Math.random() * this.N);

      // Pick a random direction (horizontal or vertical)
      const directions = [];
      if (r1 > 0) directions.push({r: r1 - 1, c: c1}); // up
      if (r1 < this.N - 1) directions.push({r: r1 + 1, c: c1}); // down
      if (c1 > 0) directions.push({r: r1, c: c1 - 1}); // left
      if (c1 < this.N - 1) directions.push({r: r1, c: c1 + 1}); // right

      if (directions.length > 0) {
        const neighbor = directions[Math.floor(Math.random() * directions.length)];
        const r2 = neighbor.r, c2 = neighbor.c;
        const edgeId = this.edgeKey(r1, c1, r2, c2);

        // Don't block solution path edges
        if (!solutionEdges.has(edgeId)) {
          this.edgeBarriers.add(edgeId);
        }
      }
      barrierAttempts++;
    }

    this.puzzleMode = true;

    // Start timing for new puzzle
    if (!this.puzzleStartTime) {
      this.puzzleStartTime = Date.now();
      console.log('⏰ Started timing for new generated puzzle at:', this.puzzleStartTime);
      this.gameStarted = true;
      this.hideUIForExperiencedUsers();
    }
  }

  generatePuzzleUsingPHP(difficulty) {
    console.log('🚀 Using PHP generator for', this.N + 'x' + this.N, 'puzzle with difficulty:', difficulty);

    // Send request to PHP generator
    fetch('/generate_puzzle.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        grid_size: this.N,
        difficulty: difficulty
      })
    })
    .then(response => {
      console.log('🔍 PHP response status:', response.status);
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }
      return response.text();
    })
    .then(text => {
      console.log('🔍 PHP response text:', text.substring(0, 200) + '...');
      try {
        const data = JSON.parse(text);
        if (data.success) {
        console.log('✅ PHP puzzle generated with code:', data.puzzle_code, 'and ID:', data.puzzle_id);

        // Load the generated puzzle data into the game
        this.loadPuzzleData({
          puzzle_id: data.puzzle_id,
          puzzle_code: data.puzzle_code,
          grid_size: data.puzzle_data.grid_size,
          barriers: data.puzzle_data.barriers,
          numbered_positions: data.puzzle_data.numbered_positions,
          solution_path: data.puzzle_data.solution_path,
          difficulty: data.puzzle_data.difficulty
        });

        // Update global puzzleData
        this.puzzleData = {
          puzzle_id: data.puzzle_id,
          puzzle_code: data.puzzle_code
        };

        // Store last played puzzle
        localStorage.setItem('lastPlayedPuzzle', data.puzzle_code);

        // Show the puzzle code in the UI
        this.showPuzzleCode(data.puzzle_id, data.puzzle_code);

        // Reset game state for new puzzle
        this.puzzleSolved = false;
        this.touchesBlocked = false;
        this.solveTimeRecorded = false;
        this.puzzleAlreadySolvedByUser = false;
        
        // Start timing for new PHP-generated puzzle
        if (!this.puzzleStartTime) {
          this.puzzleStartTime = Date.now();
          console.log('⏰ Started timing for new PHP-generated puzzle at:', this.puzzleStartTime);
          this.gameStarted = true;
          this.hideUIForExperiencedUsers();
        }

        // Clear any existing path and redraw
        this.clearAll();
        this.draw();

        } else {
          console.error('❌ Failed to generate PHP puzzle:', data.error);
          alert('Failed to generate puzzle: ' + data.error);
        }
      } catch (parseError) {
        console.error('❌ JSON parse error:', parseError);
        console.error('❌ Raw response text:', text);
        alert('Error parsing puzzle response. Check console for details.');
      }
    })
    .catch(error => {
      console.error('❌ Error generating PHP puzzle:', error);
      alert('Error generating puzzle. Please try again.');
    });
  }

  // --- Game Logic ---
  tryAddCell(r, c) {
    if (!this.inBounds(r, c)) return;
    const k = this.key(r, c);

    if (this.path.length === 0) {
      // Timer already started when puzzle loaded - no need to restart here

      // Check if first cell is accessible (only matters for numbered cells)
      if (!this.isNumberedCellAccessible(r, c)) return;

      // If first cell has a number, update the next required number
      const cellNumber = this.numberHints.get(k);
      if (cellNumber && cellNumber === this.nextRequiredNumber) {
        this.nextRequiredNumber++;
      } else {
        // Path length is 0, so if the check above failed,
        // user did not start with the anchor cell [1]
        // Ignore the touch
        return;
      }

      this.path.push({r, c});
      this.occupied.add(k);

      this.haptic();
      this.clearLongPress();
      this.draw();
      return;
    }

    const prev = this.path[this.path.length - 1];
    if (this.path.length > 1 && this.equal({r, c}, this.path[this.path.length - 2])) {
      this.undo();
      this.clearLongPress();
      return;
    }
    if (!this.neighbors(prev, {r, c})) return;
    if (this.occupied.has(k)) return;

    // Check for edge barriers between previous cell and this cell
    if (this.isEdgeBlocked(prev.r, prev.c, r, c)) return;

    // Check if numbered cell is accessible in sequence
    if (!this.isNumberedCellAccessible(r, c)) return;

    this.path.push({r, c});
    this.occupied.add(k);

    // If this cell has a number, update the next required number
    const cellNumber = this.numberHints.get(k);
    if (cellNumber && cellNumber === this.nextRequiredNumber) {
      this.nextRequiredNumber++;
    }

    this.haptic();
    this.clearLongPress();
    this.draw();

    console.log('🔍 Path completed! path.length:', this.path.length, 'N*N:', this.N * this.N, 'puzzleMode:', this.puzzleMode);

    if (this.path.length === this.N * this.N) {
      if (this.puzzleMode) {
        // Check if solution is correct
        console.log('🔍 Checking solution...');
        const solutionCorrect = this.checkSolution();
        console.log('🔍 Solution correct?', solutionCorrect);

        if (solutionCorrect) {
          this.puzzleSolved = true;
          this.touchesBlocked = true; // Block touches after win
          this.flash('#1dd1a1'); // Success green
          this.showUIForExperiencedUsers(); // Show UI when puzzle is completed

          if (this.puzzleAlreadySolvedByUser) {
            console.log('🎉 PUZZLE COMPLETED AGAIN! (But time not recorded - already solved before)');
            const solveTimeMs = Date.now() - this.puzzleStartTime;
            const seconds = (solveTimeMs / 1000).toFixed(2);
            // Show completion message but no timing
            this.showCompletionMessage('🎉 Solved again in ' + seconds + 's!  But only your first solve time counts.');
          } else {
            console.log('🎉 PUZZLE SOLVED FOR FIRST TIME!');

            // Record solve time (only once per solve)
            if (this.puzzleStartTime && !this.solveTimeRecorded) {
              const solveTimeMs = Date.now() - this.puzzleStartTime;
              console.log('⏱️ Puzzle solved! Time:', solveTimeMs + 'ms');
              console.log('⏱️ Recording solve time');
              this.recordSolveTime(solveTimeMs);
              this.solveTimeRecorded = true; // Prevent duplicate recordings
            } else if (this.solveTimeRecorded) {
              console.log('⏱️ Time already recorded for this solve');
            } else {
              console.log('⏱️ No puzzleStartTime, cannot record');
              this.showCompletionMessage('🎉 Solved!');
            }
          }
        } else {
          console.log('❌ Solution incorrect');
          this.flash('#ff6b6b'); // Error red
        }
      } else {
        this.flash('#1dd1a1');
      }
    }
  }

  checkSolution() {
    console.log('🔍 checkSolution: path.length =', this.path.length, 'expected:', this.N * this.N);

    // Must visit all squares
    if (this.path.length !== this.N * this.N) {
      console.log('❌ Length mismatch - need to visit all', this.N * this.N, 'squares');
      return false;
    }

    // Check if path visits all numbered cells in correct order
    const numberedCells = Array.from(this.numberHints.entries()).sort((a, b) => a[1] - b[1]);
    console.log('🔍 Numbered cells to check:', numberedCells);

    // Find the highest number (last cell we must end on)
    const maxNumber = numberedCells.length;
    const lastNumberedCell = numberedCells.find(([cellKey, number]) => number === maxNumber);
    const lastCell = this.path[this.path.length - 1];
    const lastCellKey = this.key(lastCell.r, lastCell.c);

    console.log('🔍 Must end on cell with number', maxNumber, 'at key:', lastNumberedCell[0]);
    console.log('🔍 Actually ended on key:', lastCellKey);

    // Must end on the highest numbered cell
    if (lastCellKey !== lastNumberedCell[0]) {
      console.log('❌ Must end on the highest numbered cell (', maxNumber, ')');
      return false;
    }

    let expectedNumber = 1;

    for (let i = 0; i < this.path.length; i++) {
      const cell = this.path[i];
      const cellKey = this.key(cell.r, cell.c);

      if (this.numberHints.has(cellKey)) {
        const cellNumber = this.numberHints.get(cellKey);
        console.log('🔍 At path index', i, 'found numbered cell', cellNumber, 'expected', expectedNumber);

        if (cellNumber !== expectedNumber) {
          console.log('❌ Wrong sequence - found number', cellNumber, 'but expected', expectedNumber);
          return false;
        }
        expectedNumber++;
      }
    }

    // Must have visited all numbered cells
    if (expectedNumber !== numberedCells.length + 1) {
      console.log('❌ Missing numbered cells - only visited up to', expectedNumber - 1, 'of', numberedCells.length);
      return false;
    }

    console.log('✅ Solution valid - visited all squares in correct numbered sequence and ended on final number!');
    return true;
  }

  // --- Timing and Leaderboards ---
  recordSolveTime(solveTimeMs) {
    console.log('🎯 recordSolveTime called with:', solveTimeMs + 'ms');
    console.log('🎯 puzzleData:', this.puzzleData);
    console.log('🎯 puzzleData.puzzle_id:', this.puzzleData?.puzzle_id);

    if (!this.puzzleData || !this.puzzleData.puzzle_id) {
      console.log('❌ Early return: puzzleData missing or no puzzle_id');
      return;
    }

    console.log('🎯 username:', this.username);
    console.log('🎯 username truthy?:', !!this.username);

    if (this.username) {
      // Logged-in user: save to database
      fetch('/save_solve_time.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          puzzle_id: this.puzzleData.puzzle_id,
          puzzle_code: this.puzzleData.puzzle_code,
          solve_time_ms: solveTimeMs
        })
      }).then(response => response.json())
        .then(data => {
          if (data.success) {
            console.log('Solve time recorded:', solveTimeMs + 'ms');
            this.showCompletionMessage(`🎉 First solve! Time: ${(solveTimeMs / 1000).toFixed(2)}s`);
            this.loadGlobalTimes(); // Refresh times after recording
          } else if (data.already_solved) {
            console.log('User already solved this puzzle previously');
            this.showCompletionMessage('🎉 Solved! (But your first time already counts)');
            this.puzzleAlreadySolvedByUser = true; // Update status
          } else {
            console.error('Failed to record solve time:', data.error);
            this.showCompletionMessage('🎉 Solved! (Error saving time)');
          }
        })
        .catch(error => {
          console.error('Error recording solve time:', error);
          this.showCompletionMessage('🎉 Solved! (Error saving time)');
        });
    } else {
      console.log('📱 Anonymous user branch - calling saveAnonymousTime');

      // Check if we have a temporary puzzle_id (puzzle not saved to server yet)
      if (typeof this.puzzleData.puzzle_id === 'string' && this.puzzleData.puzzle_id.startsWith('temp_')) {
        console.log('📱 Temporary puzzle detected, will save time later when puzzle is saved');

        // Store the solve time temporarily until the puzzle gets saved
        window.pendingSolveTime = solveTimeMs;
        console.log('📱 Stored pendingSolveTime:', window.pendingSolveTime);

        this.loadGlobalTimes(); // Still load global times
      } else {
        // Anonymous user: save to localStorage and refresh displays
        this.saveAnonymousTime(this.puzzleData.puzzle_id, solveTimeMs);
        console.log('📱 Anonymous user branch - calling loadAnonymousTimes');
        this.loadAnonymousTimes();
        console.log('📱 Anonymous user branch - calling loadGlobalTimes');
        this.loadGlobalTimes();
      }
    }
  }

  // --- UI Management ---
  showCompletionMessage(message) {
    // Show a temporary message overlay
    const hint = document.querySelector('.hint');
    if (hint) {
      const originalText = hint.innerHTML;
      const originalColor = hint.style.color;

      hint.innerHTML = message;
      hint.style.color = 'var(--good)';

      // Revert after 3 seconds
      setTimeout(() => {
        hint.innerHTML = originalText;
        hint.style.color = originalColor;
      }, 3000);
    }
  }

  hideUIForExperiencedUsers() {
    if (!this.isExperienced || this.uiHidden) return;

    const header = document.querySelector('header');
    const hint = document.querySelector('.hint');
    const leaderboard = document.querySelector('.leaderboard-section');

    if (header) {
      header.style.height = '0';
      header.style.overflow = 'hidden';
      header.style.padding = '0';
      header.style.border = 'none';
    }
    if (hint) {
      hint.style.height = '0';
      hint.style.overflow = 'hidden';
      hint.style.padding = '0';
    }
    if (leaderboard) {
      leaderboard.style.height = '0';
      leaderboard.style.overflow = 'hidden';
      leaderboard.style.padding = '0';
      leaderboard.style.border = 'none';
    }

    this.uiHidden = true;
    this.startInactivityTimer();
  }

  showUIForExperiencedUsers() {
    if (!this.uiHidden) return;

    const restoreUI = () => {
      const header = document.querySelector('header');
      const hint = document.querySelector('.hint');
      const leaderboard = document.querySelector('.leaderboard-section');

      if (header) {
        header.style.height = '';
        header.style.overflow = '';
        header.style.padding = '';
        header.style.border = '';
      }
      if (hint) {
        hint.style.height = '';
        hint.style.overflow = '';
        hint.style.padding = '';
      }
      if (leaderboard) {
        leaderboard.style.height = '';
        leaderboard.style.overflow = '';
        leaderboard.style.padding = '';
        leaderboard.style.border = '';
      }

      this.uiHidden = false;
      this.clearInactivityTimer();
    };

    if (this.puzzleSolved) {
      // When puzzle is solved, add delay before restoring UI to prevent grid movement affecting touches
      setTimeout(restoreUI, 500); // 0.5 second delay
    } else {
      // For other cases (inactivity timeout), restore immediately
      restoreUI();
    }
  }

  startInactivityTimer() {
    this.clearInactivityTimer();
    this.inactivityTimer = setTimeout(() => {
      this.showUIForExperiencedUsers();
    }, 60000); // 1 minute
  }

  clearInactivityTimer() {
    if (this.inactivityTimer) {
      clearTimeout(this.inactivityTimer);
      this.inactivityTimer = null;
    }
  }

  resetInactivityTimer() {
    if (this.uiHidden) {
      this.startInactivityTimer();
    }
  }

  // --- Event Handlers ---
  onPointerDown(e) {
    e.preventDefault();

    // Block touches if puzzle is won and touches are disabled
    if (this.touchesBlocked) return;

    this.canvas.setPointerCapture(e.pointerId);
    this.drawing = true;
    this.isDragging = false; // Track if we're actually dragging
    const rect = this.canvas.getBoundingClientRect();
    this.downPos = { x: (e.clientX - rect.left) * this.dpi, y: (e.clientY - rect.top) * this.dpi };

    // Reset inactivity timer on user interaction
    this.resetInactivityTimer();

    // Handle initial click directly (not as drag)
    this.handleInitialClick(e);
    this.startLongPress();
  }

  onPointerMove(e) {
    if (!this.drawing) return;

    // Block touches if puzzle is won and touches are disabled
    if (this.touchesBlocked) return;

    // Reset inactivity timer on user interaction
    this.resetInactivityTimer();

    // Check if we've moved enough to start dragging
    if (!this.isDragging && this.downPos) {
      const rect = this.canvas.getBoundingClientRect();
      const currentX = (e.clientX - rect.left) * this.dpi;
      const currentY = (e.clientY - rect.top) * this.dpi;

      const dx = Math.abs(currentX - this.downPos.x);
      const dy = Math.abs(currentY - this.downPos.y);
      const moveThresh = Math.max(8 * this.dpi, this.cell * 0.15);

      if (dx > moveThresh || dy > moveThresh) {
        this.isDragging = true;
        this.clearLongPress(); // Cancel long press when dragging starts
      }
    }

    // Only use stepThrough when actually dragging
    if (this.isDragging) {
      this.handleDragMove(e);
    }
  }

  setupGameEventListeners() {
    // Override the core event listeners to add game-specific behavior
    this.canvas.removeEventListener('pointerdown', this.onPointerDown);
    this.canvas.removeEventListener('pointermove', this.onPointerMove);
    this.canvas.removeEventListener('pointerup', this.onPointerUp);
    this.canvas.removeEventListener('pointercancel', this.onPointerUp);

    this.canvas.addEventListener('pointerdown', (e) => this.onPointerDown(e));
    this.canvas.addEventListener('pointermove', (e) => this.onPointerMove(e));
    this.canvas.addEventListener('pointerup', (e) => this.onPointerUp(e));
    this.canvas.addEventListener('pointercancel', (e) => this.onPointerUp(e));
  }

  // --- Public API ---
  loadPuzzleData(data) {
    super.loadPuzzleData(data);

    if (data) {
      // Store last played puzzle for potential restoration after login/registration
      if (data.puzzle_code) {
        localStorage.setItem('lastPlayedPuzzle', data.puzzle_code);
      }

      // Set grid size
      this.N = data.grid_size;
      const gridSizeSelect = document.getElementById('gridSize');
      if (gridSizeSelect) {
        gridSizeSelect.value = this.N.toString();
      }
      const difficultySelect = document.getElementById('difficulty');
      if (difficultySelect) {
        difficultySelect.value = data.difficulty || 'medium';
      }

      // Start timing for existing puzzle and hide UI for experienced users
      if (!this.puzzleStartTime) {
        this.puzzleStartTime = Date.now();
        console.log('⏰ Started timing for existing puzzle at:', this.puzzleStartTime);
        this.gameStarted = true;
        this.hideUIForExperiencedUsers();
      }

      // Check if user already solved this puzzle (logged-in or anonymous)
      this.checkIfAlreadySolved();
    }
  }

  showPuzzleCode(puzzleId, puzzleCode) {
    // Find the lower_controls div and add/update puzzle info
    const lowerControls = document.querySelector('.lower_controls');
    if (!lowerControls) return;

    let puzzleInfo = lowerControls.querySelector('.puzzle-info');

    if (!puzzleInfo) {
      // Create puzzle info link if it doesn't exist
      puzzleInfo = document.createElement('a');
      puzzleInfo.className = 'puzzle-info';
      lowerControls.appendChild(puzzleInfo);
    }

    puzzleInfo.href = `/puzzle/${puzzleCode}`;
    puzzleInfo.textContent = `Puzzle #${puzzleId}`;
  }

  // Leaderboard functionality
  checkIfAlreadySolved() {
    if (!this.puzzleData || !this.puzzleData.puzzle_id) return;

    if (this.username) {
      // Logged-in user: check database
      fetch(`/check_solved.php?puzzle_id=${this.puzzleData.puzzle_id}`)
        .then(response => response.json())
        .then(data => {
          if (data.solved) {
            this.puzzleAlreadySolvedByUser = true;
            console.log('✅ Logged-in user already solved this puzzle in', data.solve_time_ms + 'ms');
            this.updateSolvedUI(data.solve_time_ms, data.completed_at);
          } else {
            this.puzzleAlreadySolvedByUser = false;
            console.log('🆕 Logged-in user has not solved this puzzle yet');
            if (!this.puzzleStartTime) {
              this.puzzleStartTime = Date.now();
              console.log('⏰ Started timing for logged-in user at:', this.puzzleStartTime);
            }
          }
        })
        .catch(error => {
          console.error('Error checking solve status:', error);
          this.puzzleAlreadySolvedByUser = false;
          if (!this.puzzleStartTime) {
            this.puzzleStartTime = Date.now();
            console.log('⏰ Started timing on error (assumed first-time) at:', this.puzzleStartTime);
          }
        });
    } else {
      // Anonymous user: check localStorage
      const key = `slide_times_${this.puzzleData.puzzle_id}`;
      const times = JSON.parse(localStorage.getItem(key) || '[]');

      if (times.length > 0) {
        this.puzzleAlreadySolvedByUser = true;
        console.log('✅ Anonymous user already solved this puzzle in', times[0].solve_time_ms + 'ms');
        this.updateSolvedUI(times[0].solve_time_ms, times[0].completed_at);
      } else {
        this.puzzleAlreadySolvedByUser = false;
        console.log('🆕 Anonymous user has not solved this puzzle yet');
        if (!this.puzzleStartTime) {
          this.puzzleStartTime = Date.now();
          console.log('⏰ Started timing for anonymous user at:', this.puzzleStartTime);
        }
      }
    }
  }

  updateSolvedUI(solveTimeMs, completedAt) {
    const hint = document.querySelector('.hint');
    if (hint) {
      const seconds = (solveTimeMs / 1000).toFixed(2);
      const date = new Date(completedAt).toLocaleDateString();
      hint.innerHTML = `🎉 Already solved in ${seconds}s on ${date}! You can still play for fun, but only your first solve time counts.`;
      hint.style.color = 'var(--good)';
    }
  }

  loadGlobalTimes() {
    if (!this.puzzleData || !this.puzzleData.puzzle_id) return;

    fetch(`/get_user_times.php?puzzle_id=${this.puzzleData.puzzle_id}`)
      .then(response => {
        if (!response.ok) {
          console.log('User times request failed:', response.status);
          return;
        }
        return response.text();
      })
      .then(text => {
        if (!text) return;
        try {
          const data = JSON.parse(text);
          if (data.success) {
            this.displayGlobalTimes(data.times, data.current_user_id);
          } else {
            console.log('User times error:', data.error);
          }
        } catch (e) {
          console.error('Invalid JSON response from get_user_times.php:', text);
        }
      })
      .catch(error => {
        console.error('Error loading user times:', error);
      });
  }

  displayGlobalTimes(times, currentUserId) {
    const container = document.getElementById('global-times');
    if (!container) return;

    if (times.length === 0) {
      container.innerHTML = '<p class="no-times">No times recorded yet for this puzzle.</p>';
      return;
    }

    const timesList = times.map((time, index) => {
      const seconds = (time.solve_time_ms / 1000).toFixed(2);
      const date = new Date(time.completed_at).toLocaleDateString();
      const isCurrentUser = currentUserId && parseInt(time.user_id) === currentUserId;
      const entryClass = isCurrentUser ? 'time-entry current-user' : 'time-entry';

      return `<div class="${entryClass}">
        <span class="rank">#${index + 1}</span>
        <span class="time">${seconds}s</span>
        <span class="username">${time.username}</span>
        <span class="date">${date}</span>
      </div>`;
    }).join('');

    container.innerHTML = `<div class="times-list">${timesList}</div>`;
  }

  loadAnonymousTimes() {
    if (!this.puzzleData || !this.puzzleData.puzzle_id) return;

    const key = `slide_times_${this.puzzleData.puzzle_id}`;
    const times = JSON.parse(localStorage.getItem(key) || '[]');

    this.displayAnonymousTimes(times);
  }

  displayAnonymousTimes(times) {
    const container = document.getElementById('anonymous-times');
    if (!container) return;

    if (times.length === 0) {
      container.innerHTML = `
        <p class="no-times">No times recorded yet for this puzzle.</p>
        <div class="register-prompt">
          <p>🏆 <a href="/login/register.php">Create an account</a> or <a href="/login/">Log in</a> to permanently save your solve times and compete on global leaderboards!</p>
        </div>
      `;
      return;
    }

    const timesList = times.map((time, index) => {
      const seconds = (time.solve_time_ms / 1000).toFixed(2);
      const date = new Date(time.completed_at).toLocaleDateString();
      return `<div class="time-entry">
        <span class="rank">#${index + 1}</span>
        <span class="time">${seconds}s</span>
        <span class="date">${date}</span>
      </div>`;
    }).join('');

    container.innerHTML = `
      <div class="times-list">${timesList}</div>
      <div class="register-prompt">
        <p>🏆 <a href="/login/register.php">Create an account</a> or <a href="/login/">Log in</a> to permanently save your solve times and compete on global leaderboards!</p>
      </div>
    `;
  }


  saveAnonymousTime(puzzleId, solveTimeMs) {
    console.log('💾 saveAnonymousTime called with puzzleId:', puzzleId, 'solveTime:', solveTimeMs);
    const key = `slide_times_${puzzleId}`;
    console.log('💾 localStorage key:', key);

    let times = JSON.parse(localStorage.getItem(key) || '[]');
    console.log('💾 existing times:', times);

    // For anonymous users, only save first solve
    if (times.length === 0) {
      times.push({
        solve_time_ms: solveTimeMs,
        completed_at: new Date().toISOString()
      });
      console.log('💾 First solve saved:', times);

      localStorage.setItem(key, JSON.stringify(times));
      console.log('💾 saved to localStorage successfully');

      this.showCompletionMessage(`🎉 First solve! Time: ${(solveTimeMs / 1000).toFixed(2)}s`);
    } else {
      console.log('💾 Anonymous user already has a time for this puzzle - not saving duplicate');
      this.showCompletionMessage('🎉 Solved again! Your first time still counts.');
    }

    // Verify current state
    const saved = localStorage.getItem(key);
    console.log('💾 verification - localStorage now contains:', saved);
  }

  migrateAnonymousTimes() {
    // Find all localStorage puzzle times and migrate them
    const keysToMigrate = [];
    for (let i = 0; i < localStorage.length; i++) {
      const key = localStorage.key(i);
      if (key && key.startsWith('slide_times_')) {
        keysToMigrate.push(key);
      }
    }

    if (keysToMigrate.length === 0) return;

    console.log(`Migrating ${keysToMigrate.length} puzzle times to account...`);

    keysToMigrate.forEach(key => {
      const puzzleId = key.replace('slide_times_', '');
      const times = JSON.parse(localStorage.getItem(key) || '[]');

      times.forEach(time => {
        // Save each time to the database
        fetch('/save_solve_time.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({
            puzzle_id: parseInt(puzzleId),
            solve_time_ms: time.solve_time_ms
          })
        }).then(response => response.json())
          .then(data => {
            if (data.success) {
              console.log(`Migrated time ${time.solve_time_ms}ms for puzzle ${puzzleId}`);
            }
          })
          .catch(error => {
            console.error('Error migrating time:', error);
          });
      });

      // Clear the localStorage key after migration
      localStorage.removeItem(key);
    });

    // Refresh the global leaderboard after migration
    setTimeout(() => {
      console.log('🔄 Migration complete, refreshing displays');
      this.loadGlobalTimes();

      // Also remove the anonymous times section since user is now logged in
      const anonymousSection = document.getElementById('anonymous-times');
      if (anonymousSection && anonymousSection.parentElement) {
        anonymousSection.parentElement.style.display = 'none';
      }
    }, 1500); // Wait a bit longer for all migrations to complete
  }

  savePuzzle(difficulty) {
    // Convert edgeBarriers Set to array for JSON
    const barriers = [];
    this.edgeBarriers.forEach(edgeId => {
      const [cell1, cell2] = edgeId.split('|');
      const [r1, c1] = cell1.split(',').map(Number);
      const [r2, c2] = cell2.split(',').map(Number);

      // Determine if it's vertical or horizontal
      const isVertical = c1 === c2;
      barriers.push({
        x1: c1, y1: r1,
        x2: c2, y2: r2,
        type: isVertical ? 'vertical' : 'horizontal'
      });
    });

    // Convert numberHints Map to object
    const numbered_positions = {};
    this.numberHints.forEach((number, cellKey) => {
      const [r, c] = cellKey.split(',').map(Number);
      numbered_positions[number] = {x: c, y: r};
    });

    // Convert solution path
    const solution_path = this.solutionPath.map(cell => ({x: cell.c, y: cell.r}));

    const requestData = {
      grid_size: this.N,
      barriers: barriers,
      numbered_positions: numbered_positions,
      solution_path: solution_path,
      difficulty: difficulty
    };

    // Send to server
    fetch('/save_puzzle.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(requestData)
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        console.log('Puzzle saved with code:', data.puzzle_code, 'and ID:', data.puzzle_id);

        // Update global puzzleData so recordSolveTime() can access puzzle_id
        console.log('💽 Updating global puzzleData with server response');
        console.log('💽 Before update - puzzleData:', this.puzzleData);
        if (!this.puzzleData) {
          this.puzzleData = {};
        }
        this.puzzleData.puzzle_id = data.puzzle_id;
        this.puzzleData.puzzle_code = data.puzzle_code;
        console.log('💽 After update - puzzleData:', this.puzzleData);

        // Check if there's a pending solve time to save now that we have a real puzzle_id
        if (window.pendingSolveTime && !this.username) {
          console.log('💽 Found pendingSolveTime, saving to localStorage now');
          this.saveAnonymousTime(data.puzzle_id, window.pendingSolveTime);
          this.loadAnonymousTimes();
          window.pendingSolveTime = null; // Clear pending time
        }

        // Store last played puzzle
        localStorage.setItem('lastPlayedPuzzle', data.puzzle_code);
        // Show the puzzle code in the UI
        this.showPuzzleCode(data.puzzle_id, data.puzzle_code);
      } else {
        console.error('Failed to save puzzle:', data.error);
      }
    })
    .catch(error => {
      console.error('Error saving puzzle:', error);
    });
  }
}
