CREATE TABLE solve_times (
    solve_times_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    puzzle_id INT NOT NULL,
    puzzle_code CHAR(8),
    solve_time_ms INT UNSIGNED NOT NULL,
    completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_puzzle_times (puzzle_id, solve_time_ms),
    INDEX idx_puzzle_code_times (puzzle_code, solve_time_ms),
    INDEX idx_solve_time (solve_time_ms),
    INDEX idx_user_times (user_id, solve_time_ms),

    FOREIGN KEY (puzzle_id) REFERENCES puzzles(puzzle_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;
