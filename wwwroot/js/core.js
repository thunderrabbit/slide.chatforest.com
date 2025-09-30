/**
 * Core module for slide puzzle game
 * Contains shared functionality for canvas, drawing, path logic, and utilities
 */

export class SlideCore {
  constructor(canvasId) {
    this.canvas = document.getElementById(canvasId);
    this.ctx = this.canvas.getContext('2d');
    this.dpi = Math.max(1, Math.floor(window.devicePixelRatio || 1));

    // Core state
    this.N = 5; // default grid size
    this.cell = 1; // px; computed later
    this.origin = {x: 0, y: 0};
    this.path = []; // array of {r,c}
    this.occupied = new Set(); // key r,c
    this.drawing = false;
    this.anchors = new Map(); // no anchors by default

    // Puzzle state
    this.edgeBarriers = new Set(); // edges that are blocked (format: "r1,c1|r2,c2")
    this.numberHints = new Map(); // key r,c -> number
    this.solutionPath = []; // the valid solution
    this.puzzleMode = false; // toggle between practice and puzzle mode
    this.nextRequiredNumber = 1; // the next number that must be reached in sequence
    this.showingSolution = false; // whether to display the solution path

    // Pointer handling state
    this.longPressTimer = null;
    this.downPos = null; // {x,y} in CSS pixels * dpi
    this.isDragging = false; // Track if we're actually dragging

    this.setupEventListeners();
  }

  // --- Utility Functions ---
  key(r, c) { return r + ',' + c }

  equal(a, b) { return a && b && a.r === b.r && a.c === b.c }

  inBounds(r, c) { return r >= 0 && r < this.N && c >= 0 && c < this.N }

  neighbors(a, b) {
    return a && b && ((a.r === b.r && Math.abs(a.c - b.c) === 1) || (a.c === b.c && Math.abs(a.r - b.r) === 1));
  }

  cellAt(x, y) {
    const cx = Math.round((x - this.origin.x - this.cell / 2) / this.cell);
    const cy = Math.round((y - this.origin.y - this.cell / 2) / this.cell);
    return {r: cy, c: cx};
  }

  px(r, c) {
    return {
      x: this.origin.x + c * this.cell + this.cell / 2,
      y: this.origin.y + r * this.cell + this.cell / 2
    };
  }

  edgeAt(x, y) {
    const tolerance = this.cell * 0.2; // Click within 20% of the edge
    const cellPos = this.cellAt(x, y);
    const cellCenter = this.px(cellPos.r, cellPos.c);

    const dx = x - cellCenter.x;
    const dy = y - cellCenter.y;

    // Check if click is near a vertical edge
    if (Math.abs(dx) > this.cell / 2 - tolerance) {
      const adjacentC = cellPos.c + (dx > 0 ? 1 : -1);
      if (this.inBounds(cellPos.r, adjacentC)) {
        return { cell1: cellPos, cell2: { r: cellPos.r, c: adjacentC } };
      }
    }
    // Check if click is near a horizontal edge
    else if (Math.abs(dy) > this.cell / 2 - tolerance) {
      const adjacentR = cellPos.r + (dy > 0 ? 1 : -1);
      if (this.inBounds(adjacentR, cellPos.c)) {
        return { cell1: cellPos, cell2: { r: adjacentR, c: cellPos.c } };
      }
    }

    return null;
  }

  // Edge barrier helper functions
  edgeKey(r1, c1, r2, c2) {
    // Normalize edge key so (1,1)-(1,2) is same as (1,2)-(1,1)
    if (r1 > r2 || (r1 === r2 && c1 > c2)) {
      [r1, c1, r2, c2] = [r2, c2, r1, c1];
    }
    return `${r1},${c1}|${r2},${c2}`;
  }

  isEdgeBlocked(r1, c1, r2, c2) {
    return this.edgeBarriers.has(this.edgeKey(r1, c1, r2, c2));
  }

  isNumberedCellAccessible(r, c) {
    if (!this.puzzleMode) return true; // In practice mode, all cells accessible

    const cellKey = this.key(r, c);
    const cellNumber = this.numberHints.get(cellKey);

    // If this cell doesn't have a number, it's accessible
    if (!cellNumber) return true;

    // Number 1 is always accessible, other numbers only if previous was reached
    return cellNumber <= this.nextRequiredNumber;
  }

  // --- Canvas and Drawing ---
  resize() {
    const size = Math.min(window.innerWidth - 24, window.innerHeight - 200, 900);
    const css = Math.max(280, size);
    this.canvas.style.width = this.canvas.style.height = css + 'px';
    this.canvas.width = Math.floor(css * this.dpi);
    this.canvas.height = Math.floor(css * this.dpi);
    this.cell = Math.floor((Math.min(this.canvas.width, this.canvas.height) - 40 * this.dpi) / this.N);
    this.origin.x = this.origin.y = Math.floor((Math.min(this.canvas.width, this.canvas.height) - this.cell * this.N) / 2);
    this.draw();
  }

  draw() {
    this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
    this.ctx.save();
    this.ctx.translate(0.5, 0.5);

    // Draw grid
    this.ctx.lineWidth = 1 * this.dpi;
    this.ctx.strokeStyle = '#2a3146';
    for (let r = 0; r < this.N; r++) {
      for (let c = 0; c < this.N; c++) {
        const x = this.origin.x + c * this.cell, y = this.origin.y + r * this.cell;
        this.ctx.strokeRect(x, y, this.cell, this.cell);
      }
    }

    // Draw edge barriers as walls
    this.ctx.strokeStyle = '#ff6b6b';
    this.ctx.lineWidth = Math.max(4 * this.dpi, this.cell * 0.15);
    for (const edgeKey of this.edgeBarriers) {
      const [cell1, cell2] = edgeKey.split('|');
      const [r1, c1] = cell1.split(',').map(Number);
      const [r2, c2] = cell2.split(',').map(Number);

      // Calculate wall position along the shared edge between cells
      let wallX1, wallY1, wallX2, wallY2;

      if (r1 === r2) {
        // Horizontal edge (wall runs vertically)
        const wallX = this.origin.x + Math.max(c1, c2) * this.cell;
        wallX1 = wallX2 = wallX;
        wallY1 = this.origin.y + r1 * this.cell + 2;
        wallY2 = this.origin.y + r1 * this.cell + this.cell - 2;
      } else {
        // Vertical edge (wall runs horizontally)
        const wallY = this.origin.y + Math.max(r1, r2) * this.cell;
        wallY1 = wallY2 = wallY;
        wallX1 = this.origin.x + c1 * this.cell + 2;
        wallX2 = this.origin.x + c1 * this.cell + this.cell - 2;
      }

      // Draw wall
      this.ctx.beginPath();
      this.ctx.moveTo(wallX1, wallY1);
      this.ctx.lineTo(wallX2, wallY2);
      this.ctx.stroke();
    }

    // Draw path
    if (this.path.length > 0) {
      this.ctx.lineWidth = Math.max(6 * this.dpi, Math.floor(this.cell * 0.8));
      this.ctx.lineCap = 'round';
      this.ctx.lineJoin = 'round';
      this.ctx.strokeStyle = '#5aa6ff';
      this.ctx.beginPath();
      for (let i = 0; i < this.path.length; i++) {
        const {x, y} = this.px(this.path[i].r, this.path[i].c);
        if (i === 0) this.ctx.moveTo(x, y); else this.ctx.lineTo(x, y);
      }
      this.ctx.stroke();

      // Show green circle at end
      const cur = this.path[this.path.length - 1];
      const pxy = this.px(cur.r, cur.c);
      this.ctx.beginPath();
      this.ctx.strokeStyle = 'rgba(67,192,122,0.85)';
      this.ctx.lineWidth = 3 * this.dpi;
      this.ctx.arc(pxy.x, pxy.y, Math.max(10 * this.dpi, this.cell * 0.18), 0, Math.PI * 2);
      this.ctx.stroke();
    }

    // Draw solution path if enabled
    if (this.showingSolution && this.solutionPath.length > 0) {
      this.ctx.lineWidth = Math.max(3 * this.dpi, Math.floor(this.cell * 0.4));
      this.ctx.lineCap = 'round';
      this.ctx.lineJoin = 'round';
      this.ctx.strokeStyle = 'rgba(255, 255, 255, 0.6)'; // Semi-transparent white
      this.ctx.setLineDash([5 * this.dpi, 5 * this.dpi]); // Dashed line
      this.ctx.beginPath();
      for (let i = 0; i < this.solutionPath.length; i++) {
        const {x, y} = this.px(this.solutionPath[i].r, this.solutionPath[i].c);
        if (i === 0) this.ctx.moveTo(x, y); else this.ctx.lineTo(x, y);
      }
      this.ctx.stroke();
      this.ctx.setLineDash([]); // Reset line dash
    }

    // Draw number hints
    this.ctx.fillStyle = '#ffb556';
    this.ctx.font = `${Math.max(12, Math.floor(this.cell * 0.25)) * this.dpi}px ui-sans-serif`;
    this.ctx.textAlign = 'center';
    this.ctx.textBaseline = 'middle';
    for (const [cellKey, number] of this.numberHints) {
      const [r, c] = cellKey.split(',').map(Number);
      const {x, y} = this.px(r, c);
      this.ctx.fillText(number.toString(), x, y);
    }

    this.ctx.restore();
  }

  // --- Path Manipulation ---
  clearAll() {
    this.path = [];
    this.occupied.clear();
    this.nextRequiredNumber = 1; // Reset sequence tracker
    this.showingSolution = false; // Hide solution when clearing
    this.draw();
  }

  undo() {
    if (this.path.length === 0) return;
    const last = this.path.pop();
    const lastKey = this.key(last.r, last.c);
    this.occupied.delete(lastKey);

    // If we're undoing a numbered cell, we might need to adjust next required number
    const cellNumber = this.numberHints.get(lastKey);
    if (cellNumber && cellNumber === this.nextRequiredNumber - 1) {
      this.nextRequiredNumber--;
    }

    this.draw();
  }

  tryAddCell(r, c) {
    if (!this.inBounds(r, c)) return;
    const k = this.key(r, c);

    if (this.path.length === 0) {
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
  }

  stepThrough(from, to) {
    if (!to) return;
    if (!from) { this.tryAddCell(to.r, to.c); return; }

    if (from.r !== to.r && from.c !== to.c) return;
    const dr = Math.sign(to.r - from.r), dc = Math.sign(to.c - from.c);
    let r = from.r, c = from.c;
    while (r !== to.r || c !== to.c) {
      r += dr; c += dc;
      this.tryAddCell(r, c);
      // Check if the cell was actually added
      const activeCell = this.path[this.path.length - 1];
      if (!activeCell || activeCell.r !== r || activeCell.c !== c) break;
    }
  }

  // --- Utility Functions ---
  haptic() {
    if (window.navigator && 'vibrate' in window.navigator) {
      window.navigator.vibrate(5);
    }
  }

  flash(color) {
    const prev = this.canvas.style.boxShadow;
    this.canvas.style.boxShadow = `0 0 0 4px ${color}`;
    setTimeout(() => this.canvas.style.boxShadow = prev, 250);
  }

  startLongPress() {
    this.clearLongPress();
    this.longPressTimer = setTimeout(() => {
      this.clearAll();
      this.flash('#4d6aff');
    }, 700);
  }

  clearLongPress() {
    if (this.longPressTimer) {
      clearTimeout(this.longPressTimer);
      this.longPressTimer = null;
    }
  }

  // --- Event Handlers ---
  onPointerDown(e) {
    e.preventDefault();

    this.canvas.setPointerCapture(e.pointerId);
    this.drawing = true;
    this.isDragging = false; // Track if we're actually dragging
    const rect = this.canvas.getBoundingClientRect();
    this.downPos = { x: (e.clientX - rect.left) * this.dpi, y: (e.clientY - rect.top) * this.dpi };

    // Handle initial click directly (not as drag)
    this.handleInitialClick(e);
    this.startLongPress();
  }

  onPointerMove(e) {
    if (!this.drawing) return;

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

  onPointerUp(e) {
    this.drawing = false;
    this.isDragging = false;
    this.clearLongPress();
    this.canvas.releasePointerCapture?.(e.pointerId);
  }

  handleInitialClick(e) {
    const rect = this.canvas.getBoundingClientRect();
    const x = (e.clientX - rect.left) * this.dpi;
    const y = (e.clientY - rect.top) * this.dpi;

    // Convert to grid coordinates
    const cellRC = this.cellAt(x, y);

    // Call tryAddCell directly for precise click handling
    this.tryAddCell(cellRC.r, cellRC.c);
  }

  handleDragMove(e) {
    const rect = this.canvas.getBoundingClientRect();
    const x = (e.clientX - rect.left) * this.dpi;
    const y = (e.clientY - rect.top) * this.dpi;

    // Convert to grid coordinates
    const cellRC = this.cellAt(x, y);

    // Always use the last cell for drag movements
    const activeCellInPath = this.path[this.path.length - 1];

    // Only use stepThrough for drag movements (continuous drawing)
    if (!activeCellInPath || !this.equal(cellRC, activeCellInPath)) {
      this.stepThrough(activeCellInPath, cellRC);
    }
  }

  setupEventListeners() {
    this.canvas.addEventListener('pointerdown', (e) => this.onPointerDown(e));
    this.canvas.addEventListener('pointermove', (e) => this.onPointerMove(e));
    this.canvas.addEventListener('pointerup', (e) => this.onPointerUp(e));
    this.canvas.addEventListener('pointercancel', (e) => this.onPointerUp(e));
  }

  // --- Public API ---
  setGridSize(size) {
    this.N = size;
    this.clearAll();
    this.resize();
  }

  justSetNewPlannedGridSize(plannedSize) {
    this.selectedGridSize = plannedSize;
    console.log('🔧 Planned grid size set to', plannedSize + 'x' + plannedSize, 'for next puzzle');
  }

  loadPuzzleData(data) {
    if (!data) {
      this.puzzleMode = false;
      return;
    }

    // Set grid size
    this.N = data.grid_size;

    // Clear existing data
    this.edgeBarriers.clear();
    this.numberHints.clear();

    // Load barriers
    if (data.barriers) {
      data.barriers.forEach(barrier => {
        const edgeId = this.edgeKey(barrier.y1, barrier.x1, barrier.y2, barrier.x2);
        this.edgeBarriers.add(edgeId);
      });
    }

    // Load numbered positions
    if (data.numbered_positions) {
      Object.entries(data.numbered_positions).forEach(([number, pos]) => {
        this.numberHints.set(this.key(pos.y, pos.x), parseInt(number));
      });
    }

    // Load solution path
    if (data.solution_path) {
      this.solutionPath = data.solution_path.map(pos => ({r: pos.y, c: pos.x}));
    }

    this.puzzleMode = true;
    this.nextRequiredNumber = 1;
    this.showingSolution = false;
  }

  toggleSolution() {
    if (!this.puzzleMode || this.solutionPath.length === 0) return;
    this.showingSolution = !this.showingSolution;
    this.draw();
  }
}
