-- Add unique constraint to ensure only one solve time per user per puzzle
-- This enforces "first solve only" at the database level

ALTER TABLE solve_times
ADD CONSTRAINT unique_user_puzzle_solve
UNIQUE (user_id, puzzle_id);
