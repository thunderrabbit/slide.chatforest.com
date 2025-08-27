<div class="PagePanel">
    Welcome back, <?= $username ?>!
</div>

<h1>Slide Chat Forest</h1>

<div class="PagePanel slide-practice">
  <div class="wrap">
    <header>
      <h1>Slide Practice</h1>
      <div class="controls">
        <label>Grid: <select id="gridSize">
          <option value="5" selected>5×5</option>
          <option value="6">6×6</option>
          <option value="8">8×8</option>
        </select></label>
        <button id="clearBtn" title="Long‑press also clears">Clear</button>
        <button id="undoBtn">Undo</button>
        <label><input type="checkbox" id="showNumbers" checked /> # step</label>
      </div>
    </header>

    <div class="hint">Drag one finger to draw; slide back to erase (backtrack). Long‑press anywhere to clear.</div>

    <div class="stage">
      <canvas id="board" width="800" height="800" aria-label="Slide grid"></canvas>
    </div>

    <footer>
      Simplified: no anchors, no auto‑reset. Draw freely with snap‑to‑cell and backtrack.
    </footer>
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

  function clearAll(){
    path = [];
    occupied.clear();
    draw();
  }

  function undo(){
    if (path.length===0) return;
    const last = path.pop();
    occupied.delete(key(last.r,last.c));
    draw();
  }

  function tryAddCell(r,c){
    if(!inBounds(r,c)) return;
    const k = key(r,c);
    if (path.length===0){
      path.push({r,c});
      occupied.add(k);
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

    path.push({r,c});
    occupied.add(k);
    haptic();
    clearLongPress();
    draw();

    if (path.length === N*N){
      flash('#1dd1a1');
    }
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

    if (path.length>0){
      ctx.lineWidth = Math.max(6*dpi, Math.floor(cell*0.22));
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
        ctx.font = `${Math.max(10, Math.floor(cell*0.26)) * dpi}px ui-sans-serif`;
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

  seedAnchors();
  resize();
  window.addEventListener('resize', resize);
})();
</script>
</div>

<div class="PagePanel">
    <h2>Site Status</h2>
    <p>Everything is running smoothly.</p>
    <?php if (isset($site_version)): ?>
        <p><small>Version: <?= $site_version ?></small></p>
    <?php endif; ?>
</div>
