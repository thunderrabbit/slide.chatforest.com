# UX Specification for Slide Practice Login Flow

## Purpose
This document captures the intended user experience for the login flow and puzzle state preservation in the Slide Practice game.

## Questions & Answers

### Q1: Login Flow Entry Point
**Question:** When a user is currently playing a puzzle and wants to log in, how should they initiate the login process?

**Answer:** 
- Login link location in navigation bar is fine - no changes needed
- Login and register pages themselves are fine - no changes needed
- The problem is what happens *after* they log in (or register)

**Intended Behavior:**
1. User is playing Puzzle A (e.g., puzzle code "abc123")
2. User clicks "Login" in the navigation bar
3. User completes login process
4. User should be redirected back to Puzzle A ("abc123") - the exact same puzzle
5. Since they were already playing this puzzle, they should have access to navigation buttons (Prev/Next) without needing to expand any menu

**Current Problem:**
After login/registration, users are redirected to a new puzzle instead of staying on the puzzle they were just playing. This breaks continuity and forces them to start over.

**What Needs to Work:**
- Puzzle state preservation during login/registration
- Proper redirect back to the original puzzle
- Navigation buttons should be available (since they were already on a puzzle page)

### Q2: Puzzle State Preservation
**Question:** When a user logs in and returns to their original puzzle, what specific state should be preserved?

**Answer:**
- **If they solved the puzzle:** Their path (solution) should be displayed as well as their solve time
- **If they were in the middle of solving it:** 
  - If it's trivial to store the half-done state, then preserve their progress
  - If not trivial, then show them the same puzzle without any path/progress (fresh start)
  - Priority: Keep it simple - don't over-engineer the partial state preservation

### Q3: Anonymous vs Logged-in User Experience
**Question:** What should happen to anonymous solve times when a user logs in?

**Answer:**
- Anonymous solve times should be transferred/migrated to their logged-in account
- Only save anonymous times if they don't already have a time for that puzzle (don't overwrite existing logged-in times)
- Once times are transferred (or skipped for puzzles they already solved), the localStorage times should be cleared
- This functionality should already be working

### Q4: Navigation Behavior After Login
**Question:** How should navigation buttons behave when a user logs in and returns to their original puzzle?

**Answer:**
- **Anonymous users:** Should NOT see "Earliest Unplayed" and "Next Unplayed" buttons
- **Anonymous users:** Should only see Prev/Next buttons
- **All users:** Navigation buttons should respect grid size (stay within same puzzle size) regardless of login state
- **Login indication:** Logged-in users see their username at top of screen; anonymous users see login button instead of username
- **Current status:** The username/login button display is already working properly

### Q5: Error Handling and Edge Cases
**Question:** What should happen in edge cases like puzzle not existing, corrupted data, or session issues?

**Answer:**
- **Fallback approach:** Do the absolute simplest thing in terms of lines of code
- **Solution:** Just refresh to a new puzzle (`/`)
- **Implementation:** Add a comment about what we're solving, then redirect to `/`
- **Priority:** Don't over-engineer this - keep it simple for the 0.001% edge cases

## Summary

**Core Problem:** After login/registration, users are redirected to a new puzzle instead of staying on the puzzle they were just playing.

**Required Solution:** 
1. Preserve puzzle state during login/registration
2. Redirect back to the exact same puzzle after successful login
3. Show solved puzzle with path + solve time if already completed
4. Show fresh puzzle if they were in the middle of solving
5. Keep navigation simple - anonymous users only see Prev/Next, logged-in users see all buttons
6. Respect grid size in all navigation
7. Simple fallback to `/` for edge cases

**Key Principle:** Keep it simple - don't over-engineer the solution.

## Grid Size Functionality

### Grid Size Selection Behavior
**Question:** How should grid size selection work when a user changes the dropdown?

**Answer:**
- **Grid size dropdown change:** Should NOT immediately affect the current puzzle
- **Function used:** `justSetNewPlannedGridSize(plannedSize)` - stores the selected size for next puzzle
- **Current puzzle:** Remains unchanged and solvable
- **Next puzzle generation:** Uses the planned grid size
- **Function used:** `actuallyUpdateTheGridSize(newSize)` - updates `this.N` and recalculates everything

**Intended Behavior:**
1. User is playing a 5x5 puzzle
2. User changes dropdown to 6x6
3. Current 5x5 puzzle remains unchanged and solvable
4. When user clicks "New" puzzle, a 6x6 puzzle is generated
5. The 6x6 puzzle uses the new grid size

**Key Principle:** Grid size changes take effect on the next puzzle generation, not immediately.
