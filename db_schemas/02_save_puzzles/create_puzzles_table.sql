CREATE TABLE puzzles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    grid_size INT NOT NULL,
    barriers JSON NOT NULL,
    numbered_positions JSON NOT NULL,
    solution_path JSON NOT NULL,
    difficulty VARCHAR(20),
    created_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
