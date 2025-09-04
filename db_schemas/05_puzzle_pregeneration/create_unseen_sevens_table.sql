CREATE TABLE unseen_sevens (
    unseen_7_id INT AUTO_INCREMENT PRIMARY KEY,
    grid_size INT NOT NULL DEFAULT 7,
    barriers JSON NOT NULL,
    numbered_positions JSON NOT NULL,
    solution_path JSON NOT NULL,
    difficulty ENUM('easy', 'medium', 'hard') NOT NULL DEFAULT 'medium',
    generation_time_ms INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_difficulty (difficulty),
    INDEX idx_created_at (created_at)
);
