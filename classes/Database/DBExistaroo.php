<?php

namespace Database;

class DBExistaroo {
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

        if (!$this->usersTableExists()) {
            $this->createUsersTable();
        }

        if (!$this->hasAnyUsers()) {
            $errors[] = "Users table is empty. Admin setup required.";
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
     * Checks if the database exists.  Doesn't yet distinguish missing host vs invalid credentials.
     * @return bool True if the database exists, false otherwise.
     */
    private function dbExists(): bool {
        try {
            return $this->dbase->databaseExists();
        } catch (\Database\MySQLiCouldNotConnectToServer $e) {
            die("Please check the host, username, and password in your Config." . $e->getMessage());
        } catch (\Database\ECouldNotConnectToServer $e) {
            // I couldn't trigger this one by changing the config, so I don't know how to test it.
            die("Could not connect to DB server. Check host and credentials." . $e->getMessage());
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

    private function usersTableExists(): bool {
        $result = $this->conn->query("SHOW TABLES LIKE 'users'");
        return $result && $result->num_rows > 0;
    }

    private function createUsersTable(): void {
        $sql = <<<SQL
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'user') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL;

        $this->conn->query($sql);
    }

    private function hasAnyUsers(): bool {
        $result = $this->conn->query("SELECT 1 FROM users LIMIT 1");
        return $result && $result->num_rows > 0;
    }
}
