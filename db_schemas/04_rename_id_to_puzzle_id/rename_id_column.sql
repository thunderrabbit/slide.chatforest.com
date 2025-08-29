-- Rename primary key from 'id' to 'puzzle_id' for better readability
ALTER TABLE puzzles CHANGE COLUMN id puzzle_id INT AUTO_INCREMENT;