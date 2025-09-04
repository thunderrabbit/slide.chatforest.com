CREATE TABLE puzzle_generation_stats (
    pg_stats_id INT AUTO_INCREMENT PRIMARY KEY,
    grid_size INT NOT NULL,
    difficulty ENUM('easy', 'medium', 'hard') NOT NULL,
    generation_time_ms INT NOT NULL,
    success BOOLEAN NOT NULL DEFAULT TRUE,
    error_message TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_grid_difficulty (grid_size, difficulty),
    INDEX idx_created_at (created_at)
);
