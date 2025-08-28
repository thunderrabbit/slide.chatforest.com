<div class="PagePanel slide-practice">
  <div class="wrap">
    <header>
      <div class="controls">
        <label>Grid: <select id="gridSize">
          <option value="5" selected>5×5</option>
          <option value="6">6×6</option>
          <option value="8">8×8</option>
        </select></label>
        <button id="clearBtn" title="Long‑press also clears">Clear</button>
        <button id="undoBtn">Undo</button>
        <button id="puzzleBtn">New Puzzle</button>
        <button id="solutionBtn">Show Solution</button>
        <select id="difficulty">
          <option value="easy">Easy</option>
          <option value="medium" selected>Medium</option>
          <option value="hard">Hard</option>
        </select>
        <label><input type="checkbox" id="showNumbers" checked /> # step</label>
      </div>
    </header>

    <div class="hint">Drag one finger to draw; slide back to erase (backtrack). Long‑press anywhere to clear.</div>

    <div class="stage">
      <canvas id="board" width="800" height="800" aria-label="Slide grid"></canvas>
    </div>

  </div>
</div>

<script>
(function(){
  const canvas = document.getElementById('board');
  const ctx = canvas.getContext('2d');
  const dpi = Math.max(1, Math.floor(window.devicePixelRatio || 1));

  // --- Config / State ---
  let N = 5; // default grid size
  let cell = 1; // px; computed later
  let origin = {x:0, y:0};
  let path = []; // array of {r,c}
  let occupied = new Set(); // key r,c
  let drawing = false;
  let anchors = new Map(); // no anchors by default
  let showNumbers = true;

  // Puzzle generation state
  let edgeBarriers = new Set(); // edges that are blocked (format: "r1,c1|r2,c2")
  let numberHints = new Map(); // key r,c -> number
  let solutionPath = []; // the valid solution
  let puzzleMode = false; // toggle between practice and puzzle mode
  let nextRequiredNumber = 1; // the next number that must be reached in sequence
  let showingSolution = false; // whether to display the solution path

  // Long-press tracking (so we don't nuke the path while drawing)
  let longPressTimer = null;
  let downPos = null; // {x,y} in CSS pixels * dpi

  function seedAnchors() {
    anchors.clear(); // no anchors in practice mode
  }

  function resize() {
    const size = Math.min(window.innerWidth - 24, window.innerHeight - 200, 900);
    const css = Math.max(280, size);
    canvas.style.width = canvas.style.height = css + 'px';
    canvas.width = Math.floor(css * dpi);
    canvas.height = Math.floor(css * dpi);
    cell = Math.floor((Math.min(canvas.width, canvas.height) - 40*dpi) / N);
    origin.x = origin.y = Math.floor((Math.min(canvas.width, canvas.height) - cell*N)/2);
    draw();
  }

  function key(r,c){ return r+','+c }
  function equal(a,b){ return a && b && a.r===b.r && a.c===b.c }
  function inBounds(r,c){ return r>=0 && r<N && c>=0 && c<N }
  function neighbors(a,b){ return a && b && ((a.r===b.r && Math.abs(a.c-b.c)===1) || (a.c===b.c && Math.abs(a.r-b.r)===1)); }

  function cellAt(x,y){
    const cx = Math.round((x - origin.x - cell/2) / cell);
    const cy = Math.round((y - origin.y - cell/2) / cell);
    return {r: cy, c: cx};
  }

  function px(r,c){ return { x: origin.x + c*cell + cell/2, y: origin.y + r*cell + cell/2 } }

  // Edge barrier helper functions
  function edgeKey(r1, c1, r2, c2) {
    // Normalize edge key so (1,1)-(1,2) is same as (1,2)-(1,1)
    if (r1 > r2 || (r1 === r2 && c1 > c2)) {
      [r1, c1, r2, c2] = [r2, c2, r1, c1];
    }
    return `${r1},${c1}|${r2},${c2}`;
  }

  function isEdgeBlocked(r1, c1, r2, c2) {
    return edgeBarriers.has(edgeKey(r1, c1, r2, c2));
  }

  function isNumberedCellAccessible(r, c) {
    if (!puzzleMode) return true; // In practice mode, all cells accessible

    const cellKey = key(r, c);
    const cellNumber = numberHints.get(cellKey);

    // If this cell doesn't have a number, it's accessible
    if (!cellNumber) return true;

    // Number 1 is always accessible, other numbers only if previous was reached
    return cellNumber <= nextRequiredNumber;
  }

  // --- Puzzle Generation ---
  function generateHamiltonianPath(){
    // Generate a random Hamiltonian path (visits every cell exactly once)
    const visited = new Set();
    const solution = [];
    const totalCells = N * N;

    // Start from a random cell
    const start = {r: Math.floor(Math.random() * N), c: Math.floor(Math.random() * N)};
    solution.push(start);
    visited.add(key(start.r, start.c));

    // Recursive backtracking algorithm
    function backtrack(currentPos) {
      if (solution.length === totalCells) return true;

      // Try neighbors in random order
      const directions = [{r:-1,c:0}, {r:1,c:0}, {r:0,c:-1}, {r:0,c:1}];
      shuffleArray(directions);

      for (const dir of directions) {
        const next = {r: currentPos.r + dir.r, c: currentPos.c + dir.c};
        const nextKey = key(next.r, next.c);

        if (inBounds(next.r, next.c) && !visited.has(nextKey)) {
          visited.add(nextKey);
          solution.push(next);

          if (backtrack(next)) return true;

          // Backtrack - remove the cell we just added
          solution.pop();
          visited.delete(nextKey);
        }
      }
      return false;
    }

    if (backtrack(start)) {
      return solution;
    }

    // Fallback: simple spiral pattern if backtracking fails
    return generateSpiralPath();
  }

  function shuffleArray(array) {
    for (let i = array.length - 1; i > 0; i--) {
      const j = Math.floor(Math.random() * (i + 1));
      [array[i], array[j]] = [array[j], array[i]];
    }
  }

  function generateSpiralPath() {
    // Simple spiral fallback
    const solution = [];
    let r = 0, c = 0;
    let dr = 0, dc = 1;

    for (let i = 0; i < N * N; i++) {
      solution.push({r, c});

      // Calculate next position
      let nr = r + dr, nc = c + dc;

      // If next position is out of bounds or already visited, turn right
      if (!inBounds(nr, nc) || solution.some(p => p.r === nr && p.c === nc)) {
        [dr, dc] = [-dc, dr]; // Turn right
        nr = r + dr;
        nc = c + dc;
      }

      // Update position only if we're not at the last cell
      if (i < N * N - 1) {
        r = nr;
        c = nc;
      }
    }
    return solution;
  }

  function validateSolutionPath(path) {
    // Check that path visits exactly N*N cells
    if (path.length !== N * N) return false;

    // Check that all cells are within bounds
    for (const cell of path) {
      if (!inBounds(cell.r, cell.c)) return false;
    }

    // Check that each cell is visited exactly once
    const visited = new Set();
    for (const cell of path) {
      const cellKey = key(cell.r, cell.c);
      if (visited.has(cellKey)) return false; // Duplicate cell
      visited.add(cellKey);
    }

    // Check that consecutive cells are adjacent
    for (let i = 1; i < path.length; i++) {
      const prev = path[i-1];
      const curr = path[i];
      if (!neighbors(prev, curr)) return false; // Not adjacent
    }

    return true;
  }

  function generatePuzzle(difficulty = 'medium') {
    edgeBarriers.clear();
    numberHints.clear();
    nextRequiredNumber = 1; // Reset sequence tracker for new puzzle
    showingSolution = false; // Hide solution for new puzzle

    // Generate solution path with validation
    let attempts = 0;
    do {
      solutionPath = generateHamiltonianPath();
      attempts++;
      if (attempts > 10) {
        // Force use spiral if backtracking keeps failing
        solutionPath = generateSpiralPath();
        break;
      }
    } while (!validateSolutionPath(solutionPath));

    // Double-check we have a valid solution
    if (!validateSolutionPath(solutionPath)) {
      console.error("Failed to generate valid solution path");
      return;
    }

    // Place number hints at random positions along the solution path
    // Number of hints varies by difficulty but can be flexible
    const minHints = difficulty === 'easy' ? 6 : difficulty === 'medium' ? 4 : 3;
    const maxHints = Math.floor(solutionPath.length / 3); // At most 1/3 of cells
    const hintCount = Math.max(minHints, Math.min(maxHints, Math.floor(Math.random() * 4) + minHints));

    // Always include position 0 (start) and final position (end)
    const hintPositions = [0, solutionPath.length - 1];

    // Add random positions in between
    while (hintPositions.length < hintCount) {
      const randomPos = Math.floor(Math.random() * (solutionPath.length - 2)) + 1; // Exclude 0 and final
      if (!hintPositions.includes(randomPos)) {
        hintPositions.push(randomPos);
      }
    }

    // Sort positions to ensure correct numbering order
    hintPositions.sort((a, b) => a - b);

    // Place consecutive numbers at these positions
    let hintNumber = 1;
    for (const position of hintPositions) {
      const cell = solutionPath[position];
      // Safety check: ensure cell is within bounds
      if (inBounds(cell.r, cell.c)) {
        numberHints.set(key(cell.r, cell.c), hintNumber);
        hintNumber++;
      }
    }

    // Create set of solution edges (edges used in the solution path)
    const solutionEdges = new Set();
    for (let i = 0; i < solutionPath.length - 1; i++) {
      const curr = solutionPath[i];
      const next = solutionPath[i + 1];
      solutionEdges.add(edgeKey(curr.r, curr.c, next.r, next.c));
    }

    // Add edge barriers (don't block solution path edges)
    const barrierCount = Math.floor((N * N - 1) * (difficulty === 'easy' ? 0.1 : difficulty === 'medium' ? 0.15 : 0.2));
    let barrierAttempts = 0;
    while (edgeBarriers.size < barrierCount && barrierAttempts < 200) {
      // Pick random adjacent cells
      const r1 = Math.floor(Math.random() * N);
      const c1 = Math.floor(Math.random() * N);

      // Pick a random direction (horizontal or vertical)
      const directions = [];
      if (r1 > 0) directions.push({r: r1-1, c: c1}); // up
      if (r1 < N-1) directions.push({r: r1+1, c: c1}); // down
      if (c1 > 0) directions.push({r: r1, c: c1-1}); // left
      if (c1 < N-1) directions.push({r: r1, c: c1+1}); // right

      if (directions.length > 0) {
        const neighbor = directions[Math.floor(Math.random() * directions.length)];
        const r2 = neighbor.r, c2 = neighbor.c;
        const edgeId = edgeKey(r1, c1, r2, c2);

        // Don't block solution path edges
        if (!solutionEdges.has(edgeId)) {
          edgeBarriers.add(edgeId);
        }
      }
      barrierAttempts++;
    }

    puzzleMode = true;
  }

  function clearAll(){
    path = [];
    occupied.clear();
    nextRequiredNumber = 1; // Reset sequence tracker
    showingSolution = false; // Hide solution when clearing
    if (!puzzleMode) {
      edgeBarriers.clear();
      numberHints.clear();
      solutionPath = [];
    }
    draw();
  }

  function toggleSolution() {
    if (!puzzleMode || solutionPath.length === 0) return; // Only works in puzzle mode
    showingSolution = !showingSolution;
    draw();
  }

  function undo(){
    if (path.length===0) return;
    const last = path.pop();
    const lastKey = key(last.r, last.c);
    occupied.delete(lastKey);

    // If we're undoing a numbered cell, we might need to adjust next required number
    const cellNumber = numberHints.get(lastKey);
    if (cellNumber && cellNumber === nextRequiredNumber - 1) {
      nextRequiredNumber--;
    }

    draw();
  }

  function tryAddCell(r,c){
    if(!inBounds(r,c)) return;
    const k = key(r,c);

    if (path.length===0){
      // Check if first cell is accessible (only matters for numbered cells)
      if (!isNumberedCellAccessible(r, c)) return;

      path.push({r,c});
      occupied.add(k);

      // If first cell has a number, update the next required number
      const cellNumber = numberHints.get(k);
      if (cellNumber && cellNumber === nextRequiredNumber) {
        nextRequiredNumber++;
      }

      haptic();
      clearLongPress();
      draw();
      return;
    }
    const prev = path[path.length-1];
    if (path.length>1 && equal({r,c}, path[path.length-2])){
      undo();
      clearLongPress();
      return;
    }
    if (!neighbors(prev,{r,c})) return;
    if (occupied.has(k)) return;

    // Check for edge barriers between previous cell and this cell
    if (isEdgeBlocked(prev.r, prev.c, r, c)) return;

    // Check if numbered cell is accessible in sequence
    if (!isNumberedCellAccessible(r, c)) return;

    path.push({r,c});
    occupied.add(k);

    // If this cell has a number, update the next required number
    const cellNumber = numberHints.get(k);
    if (cellNumber && cellNumber === nextRequiredNumber) {
      nextRequiredNumber++;
    }

    haptic();
    clearLongPress();
    draw();

    if (path.length === N*N){
      if (puzzleMode) {
        // Check if solution is correct
        if (checkSolution()) {
          flash('#1dd1a1'); // Success green
        } else {
          flash('#ff6b6b'); // Error red
        }
      } else {
        flash('#1dd1a1');
      }
    }
  }

  function checkSolution() {
    if (path.length !== solutionPath.length) return false;
    for (let i = 0; i < path.length; i++) {
      if (!equal(path[i], solutionPath[i])) return false;
    }
    return true;
  }

  // --- Rendering ---
  function draw(){
    ctx.clearRect(0,0,canvas.width,canvas.height);
    ctx.save();
    ctx.translate(0.5,0.5);

    ctx.lineWidth = 1*dpi;
    ctx.strokeStyle = '#2a3146';
    for(let r=0;r<N;r++){
      for(let c=0;c<N;c++){
        const x = origin.x + c*cell, y = origin.y + r*cell;
        ctx.strokeRect(x, y, cell, cell);
      }
    }

    // Draw edge barriers as walls
    ctx.strokeStyle = '#ff6b6b';
    ctx.lineWidth = Math.max(4*dpi, cell*0.15);
    for (const edgeKey of edgeBarriers) {
      const [cell1, cell2] = edgeKey.split('|');
      const [r1, c1] = cell1.split(',').map(Number);
      const [r2, c2] = cell2.split(',').map(Number);

      // Calculate wall position along the shared edge between cells
      let wallX1, wallY1, wallX2, wallY2;

      if (r1 === r2) {
        // Horizontal edge (wall runs vertically)
        const wallX = origin.x + Math.max(c1, c2) * cell;
        wallX1 = wallX2 = wallX;
        wallY1 = origin.y + r1 * cell + 2;
        wallY2 = origin.y + r1 * cell + cell - 2;
      } else {
        // Vertical edge (wall runs horizontally)
        const wallY = origin.y + Math.max(r1, r2) * cell;
        wallY1 = wallY2 = wallY;
        wallX1 = origin.x + c1 * cell + 2;
        wallX2 = origin.x + c1 * cell + cell - 2;
      }

      // Draw wall
      ctx.beginPath();
      ctx.moveTo(wallX1, wallY1);
      ctx.lineTo(wallX2, wallY2);
      ctx.stroke();
    }

    if (path.length>0){
      ctx.lineWidth = Math.max(6*dpi, Math.floor(cell*0.8));
      ctx.lineCap = 'round';
      ctx.lineJoin = 'round';
      ctx.strokeStyle = '#5aa6ff';
      ctx.beginPath();
      for(let i=0;i<path.length;i++){
        const {x,y} = px(path[i].r, path[i].c);
        if (i===0) ctx.moveTo(x,y); else ctx.lineTo(x,y);
      }
      ctx.stroke();

      if (showNumbers){
        ctx.fillStyle = '#0b0c0f';
        ctx.font = `${Math.max(8, Math.floor(cell*0.16)) * dpi}px ui-sans-serif`;
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        for(let i=0;i<path.length;i++){
          const {x,y} = px(path[i].r, path[i].c);
          ctx.fillText((i+1).toString(), x, y);
        }
      }

      const cur = path[path.length-1];
      const pxy = px(cur.r, cur.c);
      ctx.beginPath();
      ctx.strokeStyle = 'rgba(67,192,122,0.85)';
      ctx.lineWidth = 3*dpi;
      ctx.arc(pxy.x, pxy.y, Math.max(10*dpi, cell*0.18), 0, Math.PI*2);
      ctx.stroke();
    }

    // Draw solution path if enabled
    if (showingSolution && solutionPath.length > 0) {
      ctx.lineWidth = Math.max(3*dpi, Math.floor(cell*0.4));
      ctx.lineCap = 'round';
      ctx.lineJoin = 'round';
      ctx.strokeStyle = 'rgba(255, 255, 255, 0.6)'; // Semi-transparent white
      ctx.setLineDash([5*dpi, 5*dpi]); // Dashed line
      ctx.beginPath();
      for(let i=0;i<solutionPath.length;i++){
        const {x,y} = px(solutionPath[i].r, solutionPath[i].c);
        if (i===0) ctx.moveTo(x,y); else ctx.lineTo(x,y);
      }
      ctx.stroke();
      ctx.setLineDash([]); // Reset line dash
    }

    // Draw number hints on top (all look the same - no visual hints about accessibility)
    ctx.fillStyle = '#ffb556';
    ctx.font = `${Math.max(12, Math.floor(cell*0.25)) * dpi}px ui-sans-serif`;
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    for (const [cellKey, number] of numberHints) {
      const [r, c] = cellKey.split(',').map(Number);
      const {x, y} = px(r, c);
      ctx.fillText(number.toString(), x, y);
    }

    ctx.restore();
  }

  // --- Pointer handling ---
  function onPointerDown(e){
    e.preventDefault();
    canvas.setPointerCapture(e.pointerId);
    drawing = true;
    const rect = canvas.getBoundingClientRect();
    downPos = { x: (e.clientX - rect.left) * dpi, y: (e.clientY - rect.top) * dpi };
    handlePointer(e);
    startLongPress();
  }
  function onPointerMove(e){ if(!drawing) return; handlePointer(e); }
  function onPointerUp(e){ drawing = false; clearLongPress(); canvas.releasePointerCapture?.(e.pointerId); }

  function handlePointer(e){
    const rect = canvas.getBoundingClientRect();
    const x = (e.clientX - rect.left) * dpi;
    const y = (e.clientY - rect.top) * dpi;

    if (downPos){
      const dx = Math.abs(x - downPos.x), dy = Math.abs(y - downPos.y);
      const moveThresh = Math.max(8*dpi, cell*0.15);
      if (dx > moveThresh || dy > moveThresh) clearLongPress();
    }

    const cellRC = cellAt(x,y);
    const last = path[path.length-1];
    if (!last || !equal(cellRC,last)) {
      stepThrough(last, cellRC);
    }
  }

  function stepThrough(from, to){
    if (!to) return;
    if (!from){ tryAddCell(to.r, to.c); return; }

    if (from.r!==to.r && from.c!==to.c) return;
    const dr = Math.sign(to.r - from.r), dc = Math.sign(to.c - from.c);
    let r = from.r, c = from.c;
    while (r!==to.r || c!==to.c){
      r += dr; c += dc;
      tryAddCell(r,c);
      const last = path[path.length-1];
      if (!last || last.r!==r || last.c!==c) break;
    }
  }

  function haptic(){
    if (window.navigator && 'vibrate' in window.navigator){
      window.navigator.vibrate(5);
    }
  }

  function flash(color){
    const prev = canvas.style.boxShadow;
    canvas.style.boxShadow = `0 0 0 4px ${color}`;
    setTimeout(()=> canvas.style.boxShadow = prev, 250);
  }

  function startLongPress(){
    clearLongPress();
    longPressTimer = setTimeout(()=>{ clearAll(); flash('#4d6aff'); }, 700);
  }
  function clearLongPress(){ if (longPressTimer) { clearTimeout(longPressTimer); longPressTimer=null; } }

  // --- UI wiring ---
  canvas.addEventListener('pointerdown', onPointerDown);
  canvas.addEventListener('pointermove', onPointerMove);
  canvas.addEventListener('pointerup', onPointerUp);
  canvas.addEventListener('pointercancel', onPointerUp);

  document.getElementById('clearBtn').addEventListener('click', ()=>{ clearAll(); flash('#4d6aff'); });
  document.getElementById('undoBtn').addEventListener('click', undo);
  document.getElementById('gridSize').addEventListener('change', (e)=>{
    N = parseInt(e.target.value,10);
    seedAnchors();
    clearAll();
    resize();
  });
  document.getElementById('showNumbers').addEventListener('change', (e)=>{ showNumbers = e.target.checked; draw(); });
  document.getElementById('puzzleBtn').addEventListener('click', ()=>{
    const difficulty = document.getElementById('difficulty').value;
    generatePuzzle(difficulty);
    clearAll();
    draw();
  });
  document.getElementById('solutionBtn').addEventListener('click', toggleSolution);

  seedAnchors();
  resize();
  window.addEventListener('resize', resize);
})();
</script>
