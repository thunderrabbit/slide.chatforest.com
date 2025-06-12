<?php

namespace Database;

class DBExistaroo {
    private \mysqli $conn;

    /**
     * DBExistaroo checks if the database exists and if the users table is present.
     * It also checks if there are any users in the users table.
     *
     * @param \Config $config Configuration object containing DB connection details.
     * @param \Database\Database $dbase Database object for checking database existence.
     */
    public function __construct(
        private \Config $config,
        private \Database\Database $dbase,
    ){
    }

    public function checkaroo(): array {
        $errors = [];

        if (!$this->configIsValid()) {
            $errors[] = "Config missing one or more DB values:";
            $errors[] = "dbHost";
            $errors[] = "dbUser";
            $errors[] = "dbPass";
            $errors[] = "dbName";
            return $errors;
        }

        if (!$this->dbExists()) {
            $errors[] = "Database '{$this->config->dbName}' does not exist.";
            return $errors;
        }

        $this->connectToDB();

        if (!$this->hasAnyUsers()) {
            $errors[] = "YallGotAnyMoreOfThemUsers";
        }

        return $errors;
    }

    private function configIsValid(): bool {
        return !empty($this->config->dbHost)
            && !empty($this->config->dbUser)
            && !empty($this->config->dbPass)
            && !empty($this->config->dbName);
    }

    /**
     * Checks if the database exists.
     * databaseExists() will die if it cannot access the server or log in.
     * We catch EDatabaseMissing if the database is missing.
     * @return bool True if the database exists, false otherwise.
     */
    private function dbExists(): bool {
        try {
            return $this->dbase->databaseExists();
        } catch (\Database\EDatabaseMissing $e) {
            return false;
        } catch (\Database\EDatabaseException $e) {
            throw new \Exception("Unexpected DB error: " . $e->getMessage());
        }
    }



    private function connectToDB(): void {
        $this->conn = new \mysqli(
            $this->config->dbHost,
            $this->config->dbUser,
            $this->config->dbPass,
            $this->config->dbName
        );

        if ($this->conn->connect_error) {
            throw new \Exception("Connection to DB failed: " . $this->conn->connect_error);
        }
    }

    private function hasAnyUsers(): bool {
        $result = $this->conn->query("SELECT 1 FROM users LIMIT 1");
        return $result && $result->num_rows > 0;
    }
}
