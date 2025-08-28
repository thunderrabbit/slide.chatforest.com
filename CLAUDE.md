# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a minimalist PHP web application framework designed for DreamHost deployment. It's a custom template-based site with admin dashboard functionality, database migration system, and user authentication using cookies stored in the database. The current implementation includes a Slide Practice puzzle game as the main application feature.

## Key Architecture

### Core Components

- **Template System**: `classes/Template.php` - Custom templating engine with layout nesting via `grabTheGoods()` method
- **Database Layer**: `classes/Database/` - PDO-based database abstraction with migration system
- **Authentication**: `classes/Auth/` - Cookie-based login system with IP tracking
- **Configuration**: Must create `classes/Config.php` from `classes/ConfigSample.php` with actual database credentials
- **Bootstrap**: `prepend.php` - Application initialization, autoloader, and database checks

### Database Migration System

- Migrations stored in `db_schemas/` with numbered prefixes (00_, 01_, 02_, etc.)
- `DBExistaroo` class handles automatic schema application and tracking
- Applied migrations tracked in `applied_DB_versions` table
- Manual rollbacks via PHPMyAdmin (no automated rollback system)

### Project Structure

- `wwwroot/` - Public web directory (DreamHost web root points here)
- `templates/` - Template files (.tpl.php) organized by feature
- `classes/` - PHP classes with PSR-4-style autoloading via `Mlaphp\Autoloader`
- `db_schemas/` - Database migration files

## Development Workflow

### Deployment

- Uses `scp_files_to_dh.sh` for automatic file watching and deployment to DreamHost
- Script monitors file changes and syncs to remote server via SSH/SCP
- Target configured for user "barefoot_rob" on "drc" host

### Initial Setup

1. Copy `classes/ConfigSample.php` to `classes/Config.php` and configure database credentials
2. First visit to site triggers automatic schema creation and admin user setup
3. Database must exist before application runs (checked by `DBExistaroo`)

### Authentication Flow

- Session-based with database-stored cookies
- First-time setup redirects to admin user creation unless visiting `/login/register.php`
- IP address tracking via `Auth\IPBin` class
- Login state managed by `Auth\IsLoggedIn` class

## Important Files

- `prepend.php` - Main application bootstrap (included by all pages)
- `wwwroot/index.php` - Site entry point with hardcoded DreamHost path
- `classes/Template.php` - Core templating functionality
- `classes/Database/DBExistaroo.php` - Database existence/migration manager
- `classes/Database/Base.php` - PDO connection and utility methods

## Development Notes

- No package manager (composer/npm) - pure PHP with custom autoloader
- Debug mode available via `?debug=1` URL parameter
- Uses `print_rob()` function for debugging output
- Error display enabled in `prepend.php` for development
- Templates use `.tpl.php` extension and PHP template syntax

### Including prepend.php

All PHP files (except templates and prepend.php itself) must include prepend.php. Use this consistent pattern regardless of directory depth:

```php
# Extract DreamHost project root: /home/username/domain.com
preg_match('#^(/home/[^/]+/[^/]+)#', __DIR__, $matches);
include_once $matches[1] . '/prepend.php';
```

This leverages DreamHost's consistent `/home/username/domain.com/` path structure to dynamically find the project root.

## Database Schema Management

- Automatic application of schemas with prefixes "00" and "01"
- Manual migration application via admin interface (`/admin/migrate_tables.php`)
- Schema files must follow `create_*.sql` naming convention
- Each schema directory represents a version (e.g., `00_bedrock/`, `01_gumdrop_cloud/`)

## Common Development Tasks

### Local Development
- No build, test, or lint commands - pure PHP development
- PHP errors displayed on screen via `prepend.php` configuration
- Debug mode: Add `?debug=1` to any URL for additional debugging output
- Use `print_rob($variable)` function for debugging (similar to `var_dump` but formatted)

### File Deployment
- **Note**: `scp_files_to_dh.sh` is gitignored and must be created locally
- Script should monitor file changes and deploy to DreamHost via SCP
- Target format: `barefoot_rob@drc:/home/username/domain.com/`
- Alternative: Manual file sync to DreamHost

### Database Operations
- Visit `/admin/migrate_tables.php` to manually apply pending migrations
- Database schemas automatically applied for prefixes "00" and "01"
- First-time setup creates admin user automatically (or redirects to `/login/register.php`)

## Error Handling and Debugging

- Application bootstrap in `prepend.php:46` performs database existence checks
- Missing users table triggers admin registration flow (`prepend.php:48-59`)
- All PHP errors displayed to screen during development (`prepend.php:5-8`)
- Template system supports debug context via `?debug=1` parameter

## Slide Practice Game

The main application feature is a puzzle game implemented in `templates/index.tpl.php` with supporting CSS in `wwwroot/css/slide-practice.css`.

### Game Features

- **Canvas-based HTML5 game** with touch and mouse support
- **Hamiltonian path puzzle generation** using recursive backtracking algorithm
- **Sequential number access system** - players must visit numbered cells in order (1, 2, 3, etc.)
- **Edge-based barrier system** - walls block passages between grid cells
- **Solution visualization** - "Show Solution" button reveals complete path with dashed overlay
- **Difficulty levels** - Easy (6x6), Medium (8x8), Hard (10x10) with variable hint counts
- **Flexible number placement** - variable number of numbered cells along solution path

### Technical Implementation

#### Puzzle Generation (`templates/index.tpl.php`)

Key functions:
- `generateHamiltonianPath(grid, startX, startY)` - Creates valid solution path using backtracking
- `generatePuzzle()` - Main puzzle creation with barriers and number placement  
- `validateSolutionPath(path, gridSize)` - Ensures solution integrity
- `isNumberedCellAccessible(x, y)` - Sequential access control

#### Game Logic

- **Path validation**: Ensures all cells visited exactly once in valid sequence
- **Barrier generation**: Edge barriers placed randomly without blocking solution
- **Number placement**: Start gets 1, end gets highest number, random distribution in between
- **Win condition**: Player reaches final numbered cell in correct sequence

#### Rendering System

- Canvas-based 2D rendering with cell/wall/number layers
- Touch-friendly interface with drag-based movement
- Real-time path validation and visual feedback
- Solution overlay with toggle functionality

### Development History

Notable fixes and improvements:
- Fixed PDO exception handling in `DBExistaroo.php:131` for missing users table
- Corrected domain paths from MarbleTrack3 to slide.chatforest.com
- Resolved Hamiltonian path generation bugs (variable scope, bounds checking)
- Implemented sequential number access with state tracking
- Added solution visualization system
- Created flexible number placement (non-rigid spacing)
