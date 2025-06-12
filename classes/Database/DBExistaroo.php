<?php

namespace Database;

class DBExistaroo {
    private \mysqli $conn;

    /**
     * DBExistaroo checks if the database exists and
     * creates a table to track DB versions if needed.
     * It's then asked to create TABLE `users` and `cookies`
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

        if (!$this->appliedDBVersionsTableExists()) {
            $this->applyBedrockSchema();
        }

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

    private function logSchemaApplication(string $version, string $direction): void
    {
        $stmt = $this->conn->prepare("INSERT INTO applied_DB_versions (applied_version, direction) VALUES (?, ?)");
        $stmt->bind_param('ss', $version, $direction);
        $stmt->execute();
    }
    private function appliedDBVersionsTableExists(): bool
    {
        $result = $this->conn->query("SHOW TABLES LIKE 'applied_DB_versions'");
        return $result && $result->num_rows > 0;
    }

    private function applyBedrockSchema(): void
    {
        echo "Applying bedrock schema...\n";
        $sql_path = $this->config->app_path . "/db_schemas/00_bedrock/create_schema.sql";
        $this->applySchemaPath($sql_path);
        $this->logSchemaApplication("00_bedrock", "up");
        echo "Bedrock schema applied successfully.\n";
    }
    private function applySchemaPath(string $sql_path): void
    {
        if (!file_exists($sql_path)) {
            throw new \Exception("Missing bedrock schema file: $sql_path");
        }
        $sql = file_get_contents($sql_path);
        // multi_query allows us to run multiple SQL statements at once
        // this is useful for creating the table and inserting initial data
        // in a single create_table.sql
        // but as of 12 June 2025, I only have a single table per schema file
        $this->conn->multi_query($sql);
        // this is necessary to clear out the results of the multi-query
        // so that we can continue without errors
        do {
            $this->conn->store_result(); // quietly discard each result
        } while ($this->conn->more_results() && $this->conn->next_result());
    }

    private function hasAnyUsers(): bool {
        $result = $this->conn->query("SELECT 1 FROM users LIMIT 1");
        return $result && $result->num_rows > 0;
    }
}
