<?php

class PuzzleCodeGenerator
{
    // Clean character set: removed look-alikes O,0,Z,2,S,5,I,1,l,G,B per requirements
    // Kept lowercase preference and arabic numerals for easier typing
    private const CHARACTERS = 'abcdefghjkmnopqrstuvwxyzACDEFHJKLMNPQRTUVWXY34679';
    private const CODE_LENGTH = 8;

    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function generateUniqueCode(): string
    {
        $maxAttempts = 100;
        $attempts = 0;

        do {
            $code = $this->generateRandomCode();
            $attempts++;

            if (!$this->codeExists($code)) {
                return $code;
            }

        } while ($attempts < $maxAttempts);

        throw new \Exception("Failed to generate unique puzzle code after $maxAttempts attempts");
    }

    private function generateRandomCode(): string
    {
        $code = '';
        $chars = self::CHARACTERS;
        $maxIndex = strlen($chars) - 1;

        for ($i = 0; $i < self::CODE_LENGTH; $i++) {
            $code .= $chars[random_int(0, $maxIndex)];
        }

        return $code;
    }

    private function codeExists(string $code): bool
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM puzzles WHERE puzzle_code = ?");
        $stmt->execute([$code]);
        return $stmt->fetchColumn() > 0;
    }

    public static function isValidCode(string $code): bool
    {
        if (strlen($code) !== self::CODE_LENGTH) {
            return false;
        }

        // Check if all characters are in our allowed set
        $allowedChars = self::CHARACTERS;
        for ($i = 0; $i < strlen($code); $i++) {
            if (strpos($allowedChars, $code[$i]) === false) {
                return false;
            }
        }

        return true;
    }
}
