/**
 * Builder module for slide puzzle game
 * Contains builder-specific functionality for creating custom puzzles
 */

import { SlideCore } from './core.js';

export class SlideBuilder extends SlideCore {
  constructor(canvasId) {
    super(canvasId);

    // Builder mode state
    this.builderMode = true; // Always in builder mode
    this.builderPhase = 'drawing'; // 'drawing', 'preview', 'testplay'
    this.builderPath = []; // the custom path being built
    this.builderBarrierCount = 6; // adjustable barrier density
    this.builderNumberCount = 4; // adjustable number count
    this.builderActiveEnd = 'end'; // 'start' or 'end' - which end of path to extend
    this.isBarrierEditingMode = false;

    // Manual number placement mode
    this.isNumberPlacementMode = false;
    this.spotPlacements = new Set(); // Using a Set to store keys of cells with spots
    this.chosenStartEnd = null; // To determine which end of the path is #1

    this.setupBuilderEventListeners();
  }

  // --- Builder-specific Drawing ---
  draw() {
    super.draw();

    // Override to add builder-specific visual elements
    if (this.builderMode && this.path.length > 1) {
      // Show both start and end with different styles
      const startPxy = this.px(this.path[0].r, this.path[0].c);
      const endPxy = this.px(this.path[this.path.length - 1].r, this.path[this.path.length - 1].c);

      // Active end gets bright green circle
      const activePxy = this.builderActiveEnd === 'start' ? startPxy : endPxy;
      this.ctx.beginPath();
      this.ctx.strokeStyle = 'rgba(67,192,122,0.85)';
      this.ctx.lineWidth = 3 * this.dpi;
      this.ctx.arc(activePxy.x, activePxy.y, Math.max(10 * this.dpi, this.cell * 0.18), 0, Math.PI * 2);
      this.ctx.stroke();

      // Inactive end gets clickable indicator (pulsing outline)
      const inactivePxy = this.builderActiveEnd === 'start' ? endPxy : startPxy;
      this.ctx.beginPath();
      this.ctx.strokeStyle = 'rgba(67,192,122,0.5)';
      this.ctx.lineWidth = 2 * this.dpi;
      this.ctx.arc(inactivePxy.x, inactivePxy.y, Math.max(8 * this.dpi, this.cell * 0.14), 0, Math.PI * 2);
      this.ctx.stroke();

      // Add a subtle outer ring to indicate it's clickable
      this.ctx.beginPath();
      this.ctx.strokeStyle = 'rgba(67,192,122,0.25)';
      this.ctx.lineWidth = 1 * this.dpi;
      this.ctx.arc(inactivePxy.x, inactivePxy.y, Math.max(12 * this.dpi, this.cell * 0.22), 0, Math.PI * 2);
      this.ctx.stroke();
    }

    // Draw temporary spots in placement mode
    if (this.isNumberPlacementMode) {
      this.ctx.strokeStyle = 'rgba(67, 192, 122, 0.8)';
      this.ctx.lineWidth = 2 * this.dpi;
      for (const cellKey of this.spotPlacements) {
        const [r, c] = cellKey.split(',').map(Number);
        const { x, y } = this.px(r, c);
        this.ctx.beginPath();
        this.ctx.arc(x, y, this.cell * 0.2, 0, Math.PI * 2);
        this.ctx.stroke();
      }
    }
  }

  // --- Builder Path Logic ---
  tryAddCell(r, c) {
    console.log('🖱️ Builder tryAddCell called:', {
      cell: {r, c},
      pathLength: this.path.length,
      builderPhase: this.builderPhase,
      builderActiveEnd: this.builderActiveEnd
    });

    if (!this.inBounds(r, c)) return;
    const k = this.key(r, c);

    // FIRST: Check if user clicked on a path end to select it (highest priority!)
    if (this.path.length >= 2) {
      const startCell = this.path[0];
      const endCell = this.path[this.path.length - 1];

      console.log('🎯 Builder click detection:', {
        clickedCell: {r, c},
        startCell: startCell,
        endCell: endCell,
        currentActiveEnd: this.builderActiveEnd,
        clickedOnStart: this.equal({r, c}, startCell),
        clickedOnEnd: this.equal({r, c}, endCell)
      });

      if (this.equal({r, c}, startCell) && this.builderActiveEnd !== 'start') {
        // Clicked on start end - switch to start
        console.log('🎯 Switching to START end');
        this.builderActiveEnd = 'start';
        this.updateBuilderHint(`Now extending from START of path (${this.path.length}/${this.N * this.N} cells)`);
        this.haptic();
        this.draw();
        return;
      } else if (this.equal({r, c}, endCell) && this.builderActiveEnd !== 'end') {
        // Clicked on end end - switch to end
        console.log('🎯 Switching to END end');
        this.builderActiveEnd = 'end';
        this.updateBuilderHint(`Now extending from END of path (${this.path.length}/${this.N * this.N} cells)`);
        this.haptic();
        this.draw();
        return;
      }
    }

    if (this.path.length === 0) {
      // First cell in builder mode
      this.path.push({r, c});
      this.occupied.add(k);
      this.builderPath = [{r, c}];
      this.haptic();
      this.clearLongPress();
      this.draw();
      return;
    }

    // Get the active end of the path based on current selection
    const activeEndIndex = this.builderActiveEnd === 'end' ? this.path.length - 1 : 0;
    const activeCell = this.path[activeEndIndex];

    // Check for backtracking (undoing the last move on active end)
    if (this.builderActiveEnd === 'end' && this.path.length > 1 && this.equal({r, c}, this.path[this.path.length - 2])) {
      // Backtrack from end
      const removed = this.path.pop();
      this.occupied.delete(this.key(removed.r, removed.c));
      this.builderPath.pop();
      this.clearLongPress();
      this.draw();
      return;
    } else if (this.builderActiveEnd === 'start' && this.path.length > 1 && this.equal({r, c}, this.path[1])) {
      // Backtrack from start
      const removed = this.path.shift();
      this.occupied.delete(this.key(removed.r, removed.c));
      this.builderPath.shift();
      this.clearLongPress();
      this.draw();
      return;
    }

    // Check if new cell is adjacent to active end
    if (!this.neighbors(activeCell, {r, c})) return;
    if (this.occupied.has(k)) return;

    // Add cell to appropriate end of builder path
    if (this.builderActiveEnd === 'end') {
      this.path.push({r, c});
      this.builderPath.push({r, c});
    } else {
      this.path.unshift({r, c});
      this.builderPath.unshift({r, c});
    }
    this.occupied.add(k);

    this.haptic();
    this.clearLongPress();
    this.draw();

    // Check if path is complete
    if (this.path.length === this.N * this.N) {
      const validation = this.validateBuilderPath(this.builderPath);
      if (validation.valid) {
        this.flash('#1dd1a1'); // Success green
        this.updateBuilderHint('✅ Perfect! Path visits all cells. Now click "Add Barriers".');
      } else {
        this.flash('#ff6b6b'); // Error red
        this.updateBuilderHint('❌ ' + validation.error);
      }
    }
  }

  handleDragMove(e) {
    const rect = this.canvas.getBoundingClientRect();
    const x = (e.clientX - rect.left) * this.dpi;
    const y = (e.clientY - rect.top) * this.dpi;

    // Convert to grid coordinates
    const cellRC = this.cellAt(x, y);

    // In builder mode, use the active end; in normal mode, always use the last cell
    const activeIndex = this.builderActiveEnd === 'start' ? 0 : this.path.length - 1;
    const activeCellInPath = this.path[activeIndex];

    // Only use stepThrough for drag movements (continuous drawing)
    if (!activeCellInPath || !this.equal(cellRC, activeCellInPath)) {
      this.stepThrough(activeCellInPath, cellRC);
    }
  }

  handleInitialClick(e) {
    const rect = this.canvas.getBoundingClientRect();
    const x = (e.clientX - rect.left) * this.dpi;
    const y = (e.clientY - rect.top) * this.dpi;

    // Convert to grid coordinates
    const cellRC = this.cellAt(x, y);

    if (this.isBarrierEditingMode) {
      const edge = this.edgeAt(x, y);
      console.log('🔧 Barrier editing click at:', x, y, 'edge:', edge);
      if (edge) {
        // Check if this edge is part of the solution path
        const pathEdges = new Set();
        for (let i = 0; i < this.builderPath.length - 1; i++) {
          pathEdges.add(this.edgeKey(this.builderPath[i].r, this.builderPath[i].c, this.builderPath[i + 1].r, this.builderPath[i + 1].c));
        }

        const currentEdgeKey = this.edgeKey(edge.cell1.r, edge.cell1.c, edge.cell2.r, edge.cell2.c);
        console.log('🔧 Current edge key:', currentEdgeKey);
        console.log('🔧 Edge barriers before:', Array.from(this.edgeBarriers));
        
        if (pathEdges.has(currentEdgeKey)) {
          this.updateBuilderHint('Cannot place a barrier on the solution path.');
          return;
        }

        // Toggle the barrier
        if (this.edgeBarriers.has(currentEdgeKey)) {
          console.log('🔧 Removing barrier:', currentEdgeKey);
          this.edgeBarriers.delete(currentEdgeKey);
        } else {
          console.log('🔧 Adding barrier:', currentEdgeKey);
          this.edgeBarriers.add(currentEdgeKey);
        }
        console.log('🔧 Edge barriers after:', Array.from(this.edgeBarriers));
        this.draw();
      } else {
        console.log('🔧 No edge detected at click position');
      }
      return; // Prevent other click actions
    }

    if (this.isNumberPlacementMode) {
      if (!this.builderPath || this.builderPath.length < 2) {
        this.updateBuilderHint('Please draw a complete path first.');
        return;
      }

      const clickedCellKey = this.key(cellRC.r, cellRC.c);
      const pathIndex = this.builderPath.findIndex(p => this.equal(p, cellRC));

      // Ignore clicks off the path
      if (pathIndex === -1) {
        this.updateBuilderHint('Spots can only be placed on the drawn path.');
        return;
      }

      // Toggle the spot
      if (this.spotPlacements.has(clickedCellKey)) {
        this.spotPlacements.delete(clickedCellKey);
        // If the user removes the chosen start end, reset it
        if (this.chosenStartEnd && this.equal(this.chosenStartEnd, cellRC)) {
          this.chosenStartEnd = null;
        }
      } else {
        this.spotPlacements.add(clickedCellKey);
        const startCell = this.builderPath[0];
        const endCell = this.builderPath[this.builderPath.length - 1];
        // If this is the first end cell clicked, set it as the start
        if (!this.chosenStartEnd && (this.equal(cellRC, startCell) || this.equal(cellRC, endCell))) {
          this.chosenStartEnd = cellRC;
        }
      }

      // Check for completion condition
      const startCell = this.builderPath[0];
      const endCell = this.builderPath[this.builderPath.length - 1];
      const startKey = this.key(startCell.r, startCell.c);
      const endKey = this.key(endCell.r, endCell.c);

      if (this.spotPlacements.has(startKey) && this.spotPlacements.has(endKey)) {
        // --- Convert spots to numbers ---
        const numberingPath = (this.chosenStartEnd && this.equal(this.chosenStartEnd, endCell))
          ? this.builderPath.slice().reverse()
          : this.builderPath;

        let currentNumber = 1;
        this.numberHints.clear();
        for (const pathCell of numberingPath) {
          const pathCellKey = this.key(pathCell.r, pathCell.c);
          if (this.spotPlacements.has(pathCellKey)) {
            this.numberHints.set(pathCellKey, currentNumber++);
          }
        }

        // --- Exit mode ---
        this.isNumberPlacementMode = false;
        this.solutionPath = this.builderPath.map(cell => ({r: cell.r, c: cell.c}));
        this.updateBuilderHint('Numbers placed! You can now test or save the puzzle.');
        document.getElementById('addNumbersBtn').textContent = 'Add Numbers';
        this.canvas.classList.remove('number-placement-mode');
      }

      this.draw();
      return; // Prevent normal path drawing
    }

    // Call tryAddCell directly for precise click handling (enables builder start/end switching)
    this.tryAddCell(cellRC.r, cellRC.c);
  }

  // --- Builder Validation ---
  validateBuilderPath(path) {
    // Must visit all squares exactly once
    if (path.length !== this.N * this.N) {
      return { valid: false, error: `Path must visit all ${this.N * this.N} cells (currently ${path.length})` };
    }

    // Check that all cells are within bounds and unique
    const visited = new Set();
    for (const cell of path) {
      if (!this.inBounds(cell.r, cell.c)) {
        return { valid: false, error: 'Path goes out of bounds' };
      }

      const cellKey = this.key(cell.r, cell.c);
      if (visited.has(cellKey)) {
        return { valid: false, error: 'Path visits the same cell twice' };
      }
      visited.add(cellKey);
    }

    // Check that consecutive cells are adjacent
    for (let i = 1; i < path.length; i++) {
      const prev = path[i - 1];
      const curr = path[i];
      if (!this.neighbors(prev, curr)) {
        return { valid: false, error: 'Path has non-adjacent cells' };
      }
    }

    return { valid: true };
  }

  // --- Builder Generation ---
  generateBuilderBarriers(customPath) {
    const barriers = [];
    const pathEdges = new Set();

    // Create set of edges used in the solution path
    for (let i = 0; i < customPath.length - 1; i++) {
      const curr = customPath[i];
      const next = customPath[i + 1];
      pathEdges.add(this.edgeKey(curr.r, curr.c, next.r, next.c));
    }

    // Use adjustable barrier count
    const targetBarriers = this.builderBarrierCount;
    let attempts = 0;

    while (barriers.length < targetBarriers && attempts < 200) {
      const r1 = Math.floor(Math.random() * this.N);
      const c1 = Math.floor(Math.random() * this.N);

      // Pick random adjacent cell
      const directions = [{r: -1, c: 0}, {r: 1, c: 0}, {r: 0, c: -1}, {r: 0, c: 1}];
      const validDirections = directions.filter(dir =>
        this.inBounds(r1 + dir.r, c1 + dir.c)
      );

      if (validDirections.length > 0) {
        const dir = validDirections[Math.floor(Math.random() * validDirections.length)];
        const r2 = r1 + dir.r;
        const c2 = c1 + dir.c;
        const edge = this.edgeKey(r1, c1, r2, c2);

        // Don't block solution path edges and avoid duplicates
        if (!pathEdges.has(edge) && !barriers.some(b =>
          this.edgeKey(b.y1, b.x1, b.y2, b.x2) === edge
        )) {
          barriers.push({
            x1: c1, y1: r1,
            x2: c2, y2: r2,
            type: r1 === r2 ? 'horizontal' : 'vertical'
          });
        }
      }
      attempts++;
    }

    return barriers;
  }

  generateBuilderNumbers(customPath) {
    const numberedPositions = {};
    const pathLength = customPath.length;

    // Always include start and end
    const hintPositions = [0, pathLength - 1];

    // Use adjustable number count (subtract 2 because start/end are always included)
    const additionalHints = Math.max(0, this.builderNumberCount - 2);
    while (hintPositions.length < this.builderNumberCount && additionalHints > 0) {
      const randomPos = Math.floor(Math.random() * (pathLength - 2)) + 1;
      if (!hintPositions.includes(randomPos)) {
        hintPositions.push(randomPos);
      }
      if (hintPositions.length >= pathLength) break; // Safety check
    }

    hintPositions.sort((a, b) => a - b);

    // Place consecutive numbers at these positions
    let hintNumber = 1;
    for (const position of hintPositions) {
      const cell = customPath[position];
      numberedPositions[hintNumber] = {x: cell.c, y: cell.r};
      hintNumber++;
    }

    return numberedPositions;
  }

  // --- Builder Controls ---
  clearPath() {
    this.builderPath = [];
    this.path = [];
    this.occupied.clear();
    this.numberHints.clear(); // Clear numbers too
    this.spotPlacements.clear(); // Clear spot placements

    // Reset modes and re-enable buttons
    this.isBarrierEditingMode = false;
    this.isNumberPlacementMode = false;
    document.getElementById('addBarriersBtn').disabled = false;
    document.getElementById('addNumbersBtn').disabled = false;
    document.getElementById('addBarriersBtn').textContent = 'Add Barriers';
    document.getElementById('addNumbersBtn').textContent = 'Add Numbers';
    this.canvas.classList.remove('barrier-editing-mode');
    this.canvas.classList.remove('number-placement-mode');

    this.builderPhase = 'drawing';
    this.builderActiveEnd = 'end';
    this.updateBuilderHint('Draw a path that visits all 49 cells exactly once. Click path ends to switch between extending start or end.');
    this.draw();
  }

  addBarriers() {
    if (this.builderPath.length > 0) {
      // If barriers haven't been generated yet, do it once.
      if (this.edgeBarriers.size === 0) {
        const validation = this.validateBuilderPath(this.builderPath);
        if (!validation.valid) {
          alert('Path is invalid: ' + validation.error);
          return;
        }
        const barriers = this.generateBuilderBarriers(this.builderPath);
        barriers.forEach(barrier => {
          const edgeId = this.edgeKey(barrier.y1, barrier.x1, barrier.y2, barrier.x2);
          this.edgeBarriers.add(edgeId);
        });
      }

      // Toggle barrier editing mode
      this.isBarrierEditingMode = !this.isBarrierEditingMode;

      if (this.isBarrierEditingMode) {
        this.isNumberPlacementMode = false; // Ensure number mode is off
        document.getElementById('addBarriersBtn').textContent = 'Done Editing Barriers';
        document.getElementById('addNumbersBtn').disabled = true; // Disable numbers button
        this.updateBuilderHint('Click on any grid line to add or remove a barrier. Barriers cannot block the solution path.');
        this.canvas.classList.add('barrier-editing-mode');
        this.canvas.classList.remove('number-placement-mode');
      } else {
        document.getElementById('addBarriersBtn').textContent = 'Add Barriers';
        document.getElementById('addNumbersBtn').disabled = false; // Re-enable numbers button
        this.updateBuilderHint('Barriers set. Click "Add Numbers" to continue.');
        this.canvas.classList.remove('barrier-editing-mode');
      }

      this.builderPhase = 'preview';
      this.draw();
    }
  }

  addNumbers() {
    this.isNumberPlacementMode = !this.isNumberPlacementMode; // Toggle the mode

    if (this.isNumberPlacementMode) {
      // --- Enter Spot Placement Mode ---
      this.isBarrierEditingMode = false; // Ensure barrier mode is off
      this.numberHints.clear();
      this.spotPlacements.clear();
      this.chosenStartEnd = null;
      this.updateBuilderHint(`Click cells on the path to place number spots. Click an end cell first to set the start.`);
      document.getElementById('addNumbersBtn').textContent = 'Cancel Placing';
      document.getElementById('addBarriersBtn').disabled = true; // Disable barriers button
      this.canvas.classList.add('number-placement-mode');
      this.canvas.classList.remove('barrier-editing-mode');
      this.draw();
    } else {
      // --- Exit Spot Placement Mode ---
      this.updateBuilderHint('Spot placement cancelled.');
      document.getElementById('addNumbersBtn').textContent = 'Add Numbers';
      document.getElementById('addBarriersBtn').disabled = false; // Re-enable barriers button
      this.canvas.classList.remove('number-placement-mode');
    }
  }

  testPlay() {
    if (this.builderPath.length > 0) {
      const validation = this.validateBuilderPath(this.builderPath);
      if (!validation.valid) {
        alert('Path is invalid: ' + validation.error);
        return;
      }

      // Check if currently in editing modes
      if (this.isBarrierEditingMode) {
        alert('Please finish editing barriers first (click "Done Editing Barriers")');
        return;
      }
      if (this.isNumberPlacementMode) {
        alert('Please finish placing numbers first');
        return;
      }

      // Check if barriers have been added
      if (this.edgeBarriers.size === 0) {
        alert('Please add barriers first (click "Add Barriers")');
        return;
      }

      // Check if numbers have been added
      if (this.numberHints.size === 0) {
        alert('Please add numbers first (click "Add Numbers")');
        return;
      }

      // Switch to test play mode
      this.builderPhase = 'testplay';
      this.puzzleMode = true;
      this.nextRequiredNumber = 1;
      this.path = [];
      this.occupied.clear();
      this.updateBuilderHint('Test your puzzle! Try to solve it. Click "Save Puzzle" when ready.');
      this.draw();
    }
  }

  savePuzzle(difficulty) {
    if (this.builderPath.length > 0) {
      const validation = this.validateBuilderPath(this.builderPath);
      if (!validation.valid) {
        alert('Path is invalid: ' + validation.error);
        return;
      }

      if (this.edgeBarriers.size === 0 || this.numberHints.size === 0) {
        alert('Please add barriers and numbers before saving');
        return;
      }

      // Convert data to same format as regular puzzles
      const barriers = [];
      this.edgeBarriers.forEach(edgeId => {
        const [cell1, cell2] = edgeId.split('|');
        const [r1, c1] = cell1.split(',').map(Number);
        const [r2, c2] = cell2.split(',').map(Number);

        barriers.push({
          x1: c1, y1: r1,
          x2: c2, y2: r2,
          type: r1 === r2 ? 'horizontal' : 'vertical'
        });
      });

      const numbered_positions = {};
      this.numberHints.forEach((number, cellKey) => {
        const [r, c] = cellKey.split(',').map(Number);
        numbered_positions[number] = {x: c, y: r};
      });

      const solution_path = this.builderPath.map(cell => ({x: cell.c, y: cell.r}));

      const requestData = {
        grid_size: this.N,
        barriers: barriers,
        numbered_positions: numbered_positions,
        solution_path: solution_path,
        difficulty: difficulty
      };

      // Save to server
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
          const puzzleUrl = `https://slide.chatforest.com/puzzle/${data.puzzle_code}`;
          this.updateBuilderHint(`✅ Puzzle Saved! <a href="${puzzleUrl}" target="slidetesttab">Open Puzzle</a>. Draw a new path to create another.`);

          // Stay in builder mode but reset for next puzzle
          this.builderPhase = 'drawing';
          this.builderPath = [];
          this.path = [];
          this.occupied.clear();
          this.edgeBarriers.clear();
          this.numberHints.clear();
          this.solutionPath = [];
          this.puzzleMode = false;
          this.builderActiveEnd = 'end';

          this.draw();
        } else {
          alert('❌ Failed to save puzzle: ' + data.error);
        }
      })
      .catch(error => {
        alert('❌ Error saving puzzle: ' + error.message);
      });
    }
  }

  // --- Builder UI ---
  updateBuilderHint(message) {
    const hint = document.querySelector('.hint');
    if (hint) {
      hint.innerHTML = message;
      hint.style.color = message ? '#ffb556' : '';
    }
  }

  updateBarrierCount(delta) {
    this.builderBarrierCount = Math.max(1, Math.min(15, this.builderBarrierCount + delta));
    document.getElementById('barrierCount').textContent = this.builderBarrierCount;
  }

  updateNumberCount(delta) {
    this.builderNumberCount = Math.max(2, Math.min(10, this.builderNumberCount + delta));
    document.getElementById('numberCount').textContent = this.builderNumberCount;
  }

  setupBuilderEventListeners() {
    // Builder controls
    const clearPathBtn = document.getElementById('clearPathBtn');
    const addBarriersBtn = document.getElementById('addBarriersBtn');
    const addNumbersBtn = document.getElementById('addNumbersBtn');
    const testPlayBtn = document.getElementById('testPlayBtn');
    const saveBuilderBtn = document.getElementById('saveBuilderBtn');

    if (clearPathBtn) {
      clearPathBtn.addEventListener('click', () => this.clearPath());
    }

    if (addBarriersBtn) {
      addBarriersBtn.addEventListener('click', () => this.addBarriers());
    }

    if (addNumbersBtn) {
      addNumbersBtn.addEventListener('click', () => this.addNumbers());
    }

    if (testPlayBtn) {
      testPlayBtn.addEventListener('click', () => this.testPlay());
    }

    if (saveBuilderBtn) {
      saveBuilderBtn.addEventListener('click', () => {
        const difficulty = document.getElementById('difficulty').value;
        this.savePuzzle(difficulty);
      });
    }

    // Density control handlers
    const barrierUpBtn = document.getElementById('barrierUpBtn');
    const barrierDownBtn = document.getElementById('barrierDownBtn');
    const numberUpBtn = document.getElementById('numberUpBtn');
    const numberDownBtn = document.getElementById('numberDownBtn');

    if (barrierUpBtn) {
      barrierUpBtn.addEventListener('click', () => this.updateBarrierCount(1));
    }

    if (barrierDownBtn) {
      barrierDownBtn.addEventListener('click', () => this.updateBarrierCount(-1));
    }

    if (numberUpBtn) {
      numberUpBtn.addEventListener('click', () => this.updateNumberCount(1));
    }

    if (numberDownBtn) {
      numberDownBtn.addEventListener('click', () => this.updateNumberCount(-1));
    }
  }

  // --- Builder Initialization ---
  initializeBuilder() {
    this.N = 7; // Force 7x7 for builder mode
    this.builderPhase = 'drawing';
    this.builderPath = [];
    this.path = [];
    this.occupied.clear();
    this.edgeBarriers.clear();
    this.numberHints.clear();
    this.solutionPath = [];
    this.puzzleMode = false;
    this.builderActiveEnd = 'end';
    this.updateBuilderHint('Draw a path that visits all 49 cells exactly once. Click path ends to switch between extending start or end.');
    this.resize();
  }
}
