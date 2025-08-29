ALTER TABLE puzzles ADD COLUMN puzzle_code CHAR(8) UNIQUE AFTER id;
CREATE INDEX idx_puzzle_code ON puzzles(puzzle_code);
