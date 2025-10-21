# Puzzle Editing Implementation Plan

This document outlines the steps to implement puzzle editing functionality for admin users.

## Overview

When an admin is logged in and viewing a specific puzzle, they should be able to edit that puzzle. The implementation involves:

1. Adding an "Edit" button for admin users on puzzle view pages
2. Modifying the puzzle builder to support loading existing puzzles
3. Updating the save functionality to handle puzzle updates
4. Clearing solve times when a puzzle is modified

## Implementation Steps

### Step 1: Add Edit Button for Admin Users

**File:** `templates/index.tpl.php`

Add an "Edit" button in the puzzle navigation section that only appears for admin users:

```php
<?php if($is_admin && isset($puzzle_id) && $puzzle_id): ?>
<button id="editPuzzleBtn" class="puzzle-nav-btn edit" title="Edit this puzzle">Edit</button>
<?php endif; ?>
```

**Location:** Around line 40, in the puzzle navigation section after the "Next Unplayed" button.

**Styling:** Add CSS for the edit button in `wwwroot/css/slide-practice.css`:

```css
.puzzle-nav-btn.edit {
  background: #ff6b35;
  color: white;
  border: 1px solid #e55a2b;
}

.puzzle-nav-btn.edit:hover {
  background: #e55a2b;
}
```

### Step 2: Add JavaScript Handler for Edit Button

**File:** `wwwroot/js/game.js`

Add event listener for the edit button that redirects to the builder with the current puzzle loaded:

```javascript
// Add to the SlideGame class constructor or initialization
document.getElementById('editPuzzleBtn')?.addEventListener('click', () => {
  if (this.puzzleId && this.puzzleCode) {
    window.location.href = `/builder/?edit=${this.puzzleCode}`;
  }
});
```

### Step 3: Modify Builder to Support Loading Existing Puzzles

**File:** `wwwroot/builder/index.php`

Add logic to detect edit mode and load existing puzzle data:

```php
// Add after line 30, before the template include
$edit_mode = false;
$edit_puzzle_data = null;

if (isset($mla_request->get['edit']) && !empty($mla_request->get['edit'])) {
    $edit_mode = true;
    try {
        $puzzleManager = new PuzzleManager($mla_database);
        $edit_puzzle_data = $puzzleManager->getPuzzleByCode($mla_request->get['edit']);

        if (!$edit_puzzle_data) {
            // Puzzle not found, redirect to builder
            header('Location: /builder/');
            exit;
        }
    } catch (\Exception $e) {
        error_log("Error loading puzzle for editing: " . $e->getMessage());
        header('Location: /builder/');
        exit;
    }
}

// Pass edit data to template
$edit_mode_data = $edit_mode ? json_encode($edit_puzzle_data) : 'null';
```

**Template Updates:** Add the edit data to the JavaScript initialization:

```javascript
// In the builder script section
const editMode = <?= $edit_mode ? 'true' : 'false' ?>;
const editPuzzleData = <?= $edit_mode_data ?>;
```

### Step 4: Update Builder JavaScript to Load Existing Puzzles

**File:** `wwwroot/js/builder.js`

Modify the `SlideBuilder` class to support loading existing puzzle data:

```javascript
// Add to constructor
this.editMode = false;
this.originalPuzzleData = null;

// Add method to load existing puzzle
loadExistingPuzzle(puzzleData) {
  this.editMode = true;
  this.originalPuzzleData = puzzleData;

  // Set grid size
  this.setGridSize(puzzleData.grid_size);

  // Load the solution path
  this.path = puzzleData.solution_path.map(coord => ({
    r: coord[0],
    c: coord[1]
  }));

  // Load barriers
  this.barriers = new Set(puzzleData.barriers.map(barrier =>
    this.key(barrier[0], barrier[1])
  ));

  // Load numbered positions
  this.spotPlacements = new Set(puzzleData.numbered_positions.map(pos =>
    this.key(pos[0], pos[1])
  ));

  // Set difficulty
  document.getElementById('difficulty').value = puzzleData.difficulty;

  // Update UI to show we're in edit mode
  this.updateEditModeUI();

  // Redraw
  this.draw();
}

updateEditModeUI() {
  // Change page title or add indicator
  const title = document.querySelector('h1') || document.querySelector('.page-title');
  if (title) {
    title.textContent = `Edit Puzzle #${this.originalPuzzleData.puzzle_id}`;
  }

  // Update save button text
  const saveBtn = document.getElementById('saveBuilderBtn');
  if (saveBtn) {
    saveBtn.textContent = 'Update Puzzle';
  }
}
```

**Initialization Update:** Modify the builder initialization to load existing puzzle if in edit mode:

```javascript
// In the builder initialization section
if (editMode && editPuzzleData) {
  builder.loadExistingPuzzle(editPuzzleData);
}
```

### Step 5: Update Save Functionality for Puzzle Updates

**File:** `wwwroot/save_puzzle.php`

Modify to handle both new puzzles and updates:

```php
// Add after line 9, before validation
$is_update = isset($input['puzzle_id']) && !empty($input['puzzle_id']);
$puzzle_id = $is_update ? (int)$input['puzzle_id'] : null;

// Modify the save logic
if ($is_update) {
    // Update existing puzzle
    $stmt = $mla_database->prepare("
        UPDATE puzzles
        SET grid_size = ?, barriers = ?, numbered_positions = ?, solution_path = ?, difficulty = ?
        WHERE puzzle_id = ?
    ");

    $stmt->execute([
        $input['grid_size'],
        json_encode($input['barriers']),
        json_encode($input['numbered_positions']),
        json_encode($input['solution_path']),
        $input['difficulty'],
        $puzzle_id
    ]);

    // Clear all solve times for this puzzle
    $clearStmt = $mla_database->prepare("DELETE FROM solve_times WHERE puzzle_id = ?");
    $clearStmt->execute([$puzzle_id]);

    // Get the puzzle code for response
    $codeStmt = $mla_database->prepare("SELECT puzzle_code FROM puzzles WHERE puzzle_id = ?");
    $codeStmt->execute([$puzzle_id]);
    $puzzle_code = $codeStmt->fetchColumn();

    echo json_encode([
        "success" => true,
        "puzzle_id" => $puzzle_id,
        "puzzle_code" => $puzzle_code,
        "updated" => true
    ]);
} else {
    // Create new puzzle (existing logic)
    $puzzleManager = new PuzzleManager($mla_database);
    $puzzleResult = $puzzleManager->savePuzzle($input);

    echo json_encode([
        "success" => true,
        "puzzle_id" => $puzzleResult['puzzle_id'],
        "puzzle_code" => $puzzleResult['puzzle_code'],
        "updated" => false
    ]);
}
```

### Step 6: Update Builder JavaScript Save Logic

**File:** `wwwroot/js/builder.js`

Modify the save functionality to include puzzle ID when updating:

```javascript
// In the save method, modify the data being sent
const saveData = {
  grid_size: this.gridSize,
  barriers: Array.from(this.barriers).map(key => {
    const [r, c] = key.split(',').map(Number);
    return [r, c];
  }),
  numbered_positions: Array.from(this.spotPlacements).map(key => {
    const [r, c] = key.split(',').map(Number);
    return [r, c];
  }),
  solution_path: this.path.map(cell => [cell.r, cell.c]),
  difficulty: document.getElementById('difficulty').value
};

// Include puzzle ID if in edit mode
if (this.editMode && this.originalPuzzleData) {
  saveData.puzzle_id = this.originalPuzzleData.puzzle_id;
}
```

### Step 7: Add Confirmation Dialog for Puzzle Updates

**File:** `wwwroot/js/builder.js`

Add confirmation when updating an existing puzzle:

```javascript
// In the save method, before sending the request
if (this.editMode) {
  const confirmed = confirm(
    'Updating this puzzle will clear all existing solve times. ' +
    'Are you sure you want to continue?'
  );
  if (!confirmed) {
    return;
  }
}
```

### Step 8: Add Visual Indicators for Edit Mode

**File:** `wwwroot/css/slide-practice.css`

Add styles to distinguish edit mode:

```css
.edit-mode .builder-controls {
  border: 2px solid #ff6b35;
  background: rgba(255, 107, 53, 0.1);
  border-radius: 8px;
  padding: 10px;
}

.edit-mode .hint {
  background: #fff3cd;
  border: 1px solid #ffeaa7;
  color: #856404;
}
```

**File:** `wwwroot/js/builder.js`

Add edit mode class to the page:

```javascript
// In loadExistingPuzzle method
document.body.classList.add('edit-mode');
```

### Step 9: Add Navigation Back to Original Puzzle

**File:** `wwwroot/builder/index.php`

Add a "Back to Puzzle" link when in edit mode:

```php
<?php if ($edit_mode && $edit_puzzle_data): ?>
<div class="edit-mode-nav">
  <a href="/puzzle/<?= $edit_puzzle_data['puzzle_code'] ?>" class="back-to-puzzle">
    ← Back to Puzzle #<?= $edit_puzzle_data['puzzle_id'] ?>
  </a>
</div>
<?php endif; ?>
```

**CSS for the navigation:**

```css
.edit-mode-nav {
  margin-bottom: 20px;
  padding: 10px;
  background: #f8f9fa;
  border-radius: 5px;
}

.back-to-puzzle {
  color: #007bff;
  text-decoration: none;
  font-weight: bold;
}

.back-to-puzzle:hover {
  text-decoration: underline;
}
```

### Step 10: Add Puzzle Modification Tracking (Optional)

**File:** Database migration

Create a new migration to track puzzle modifications:

```sql
-- Add modification tracking to puzzles table
ALTER TABLE puzzles
ADD COLUMN last_modified TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
ADD COLUMN modified_by INT UNSIGNED NULL,
ADD FOREIGN KEY (modified_by) REFERENCES users(user_id) ON DELETE SET NULL;
```

**File:** `wwwroot/save_puzzle.php`

Update the puzzle update query to track who modified it:

```php
// In the update section, add modified_by
$stmt = $mla_database->prepare("
    UPDATE puzzles
    SET grid_size = ?, barriers = ?, numbered_positions = ?, solution_path = ?,
        difficulty = ?, modified_by = ?
    WHERE puzzle_id = ?
");

$modified_by = $is_logged_in->isLoggedIn() ? $is_logged_in->loggedInID() : null;
$stmt->execute([
    $input['grid_size'],
    json_encode($input['barriers']),
    json_encode($input['numbered_positions']),
    json_encode($input['solution_path']),
    $input['difficulty'],
    $modified_by,
    $puzzle_id
]);
```

## Testing Checklist

- [ ] Edit button appears only for admin users on puzzle pages
- [ ] Clicking edit button redirects to builder with puzzle loaded
- [ ] Builder correctly loads existing puzzle data (path, barriers, numbers, difficulty)
- [ ] Grid size is set correctly when loading existing puzzle
- [ ] Save functionality works for both new and updated puzzles
- [ ] Solve times are cleared when puzzle is updated
- [ ] Confirmation dialog appears before updating
- [ ] Visual indicators show edit mode clearly
- [ ] Navigation back to original puzzle works
- [ ] Error handling works for invalid puzzle codes
- [ ] Non-admin users cannot access edit functionality

## Security Considerations

1. **Admin-only access**: Edit functionality is only available to admin users
2. **Puzzle validation**: Existing puzzle data is validated before loading
3. **Input sanitization**: All puzzle data is properly sanitized before saving
4. **Error handling**: Graceful fallbacks for invalid puzzle codes or database errors

## Database Impact

- **Solve times cleared**: When a puzzle is updated, all associated solve times are deleted
- **No data loss**: Original puzzle data is preserved until explicitly updated
- **Audit trail**: Optional modification tracking can be added for accountability

## Future Enhancements

1. **Version history**: Track multiple versions of the same puzzle
2. **Bulk editing**: Edit multiple puzzles at once
3. **Puzzle templates**: Save common puzzle configurations as templates
4. **Advanced validation**: More sophisticated puzzle validation rules
5. **Collaborative editing**: Allow multiple admins to work on puzzles