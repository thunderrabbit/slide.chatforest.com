<?php

namespace Database;

class DBExistaroo {
    private \mysqli $conn;

    private $automaticSchemaPrefixes = [
        "00",
        "01",
    ];

    /**
     * DBExistaroo checks if the database exists and
     * creates a table to track DB versions if needed.
     * It's then asked to create TABLE `users` and `cookies`
     * It also checks if there are any users in the users table.
     *
     * @param \Config $config Configuration object containing DB connection details.
     * @param \Database\DatabasePDO $dbase Database object for checking database existence.
     */
    public function __construct(
        private \Config $config,
        private \Database\DatabasePDO $dbase,
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
            $this->applyInitialSchemas();
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
        $direction = "up"; // use PHPMyAdmin to drop migrations
        $stmt = $this->conn->prepare("INSERT INTO applied_DB_versions (applied_version, direction) VALUES (?, ?)");
        $stmt->bind_param('ss', $version, $direction);
        $stmt->execute();
    }
    private function appliedDBVersionsTableExists(): bool
    {
        $result = $this->conn->query("SHOW TABLES LIKE 'applied_DB_versions'");
        return $result && $result->num_rows > 0;
    }

    private function applyInitialSchemas(): void
    {
        foreach ($this->automaticSchemaPrefixes as $prefix) {
            $dir = $this->config->app_path . "/db_schemas";
            $schema_dirs = glob("$dir/{$prefix}_*", GLOB_ONLYDIR);

            foreach ($schema_dirs as $schema_dir) {
                $version = basename($schema_dir);
                $sql_files = glob("$schema_dir/create_*.sql");

                foreach ($sql_files as $sql_path) {
                    echo "Applying schema file: $sql_path<br>";
                    $this->applySchemaPath($sql_path);
                    $this->logSchemaApplication($version . '/' . basename($sql_path), "up");
                    echo "Schema file " . basename($sql_path) . " applied successfully.<br>";
                }
            }
        }
    }

    private function applySchemaPath(string $sql_path): void
    {
        // $sql_path cannot be empty
        if (empty($sql_path)) {
            throw new \Exception("Schema path cannot be empty.");
        }
        // $sql_path must be a valid file path
        if (!file_exists($sql_path)) {
            throw new \Exception("Missing schema file: $sql_path");
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

    public function getPendingMigrations(): array {
        $pending = [];
        $applied = $this->getAppliedVersions();
        $base_dir = $this->config->app_path . "/db_schemas";

        foreach (["00", "01", "02", "03", "04"] as $prefix) {
            // Get all schema directories that match the prefix
            $schema_dirs = glob("$base_dir/{$prefix}_*", GLOB_ONLYDIR);

            foreach ($schema_dirs as $schema_dir) {
                $version = basename($schema_dir);   // directory name in $base_dir, e.g. "02_workers"
                // print_rob($version, false);
                $create_files = glob("$schema_dir/create_*.sql");

                foreach ($create_files as $file) {
                    $key = "$version/" . basename($file);
                    if (!in_array($key, $applied)) {
                        $pending[] = $key;
                        // echo "Pending migration found: $key<br>";
                    } else {
                        // echo "Skipping already applied migration: $key<br>";
                    }
                }
            }
        }

        return $pending;
    }

    private function getAppliedVersions(): array {
        $versions = [];
        $result = $this->conn->query("SELECT applied_version FROM applied_DB_versions");

        while ($row = $result->fetch_assoc()) {
            $versions[] = $row['applied_version'];
        }
        return $versions;
    }

    public function applyMigration(string $versionWithFile): void {
        $path = \Utilities::getSchemaFilePath($this->config->app_path, $versionWithFile);

        $this->applySchemaPath($path);
        $this->logSchemaApplication($versionWithFile, "up");
    }

    /**
     * Use PHPMyAdmin to drop migrations
     * I'm not going to write code to drop migrations.
     *
     * But if I did, I think the simplest way would be to:
     * 1. Not "log" drops in TABLE `applied_DB_versions` with logSchemaApplication
     * 2. allow only the most recent row to be dropped from TABLE `applied_DB_versions`.
     *
     * Otherwise, we need to parse a log of all the adds, drops, adds, drops, etc which is probably too annoying.
     */

}
