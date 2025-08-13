<?php
namespace Database;

// Include exceptions
require_once __DIR__ . "/EDatabaseExceptions.php";

class DatabasePDO implements DbInterface {

    private string $charEncoding = "UTF8";
    private ?\PDO $dbObj = null;
    private string $host;
    private string $username;
    private string $passwd;
    private string $dbname;
    private string $tz_offset = '';
    private ?int $affected_rows = null;

    public function __construct($host, $username, $passwd, $dbname = '', $charEncoding = 'UTF8') {
        $this->host = $host;
        $this->username = $username;
        $this->passwd = $passwd;
        $this->dbname = $dbname;
        $this->charEncoding = $charEncoding;
    }

    public function connect(): bool {
        if ($this->dbObj === null || !$this->isConnected()) {
            try {
                $dsn = "mysql:host={$this->host}";
                if (!empty($this->dbname)) {
                    $dsn .= ";dbname={$this->dbname}";
                }
                $dsn .= ";charset={$this->charEncoding}";

                $options = [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                    \PDO::ATTR_EMULATE_PREPARES => false,
                ];

                $this->dbObj = new \PDO($dsn, $this->username, $this->passwd, $options);
                
                $this->setTimezone();
                
            } catch (\PDOException $e) {
                // Try once more with a sleep (mimic original behavior)
                sleep(1);
                try {
                    $this->dbObj = new \PDO($dsn, $this->username, $this->passwd, $options);
                    $this->setTimezone();
                } catch (\PDOException $e2) {
                    throw new \Database\EDatabaseException("Could not connect to server after trying with 1s sleep: " . $e2->getMessage());
                }
            }
        }
        return true;
    }

    private function isConnected(): bool {
        if ($this->dbObj === null) {
            return false;
        }
        
        try {
            $this->dbObj->query('SELECT 1');
            return true;
        } catch (\PDOException $e) {
            return false;
        }
    }

    public function setTimezone(): void {
        $now = new \DateTime();
        $mins = $now->getOffset() / 60;

        $sgn = ($mins < 0 ? -1 : 1);
        $mins = abs($mins);
        $hrs = floor($mins / 60);
        $mins -= $hrs * 60;
        $offset = sprintf('%+d:%02d', $hrs*$sgn, $mins);

        if($this->tz_offset !== $offset){
            $this->tz_offset = $offset;
            $this->execSimple("SET time_zone='$offset';");
        }
    }

    public function setEncoding($charEncoding) {
        $this->charEncoding = $charEncoding;
        if ($this->dbObj !== null) {
            $this->dbObj->exec("SET NAMES {$this->charEncoding}");
        }
    }

    public function prepare($sql) {
        $this->connect();
        
        try {
            $stmt = $this->dbObj->prepare($sql);
            if (!$stmt) {
                throw new \Database\EDatabaseException("Failed to prepare statement");
            }
            return $stmt;
        } catch (\PDOException $e) {
            throw new \Database\EDatabaseException($e->getMessage());
        }
    }

    private function executeSimpleSQL(string $sql): ?int {
        $this->connect();

        try {
            $affectedRows = $this->dbObj->exec($sql);
            $this->affected_rows = $affectedRows;
            $insertId = $this->dbObj->lastInsertId();
            
            return $insertId ? (int)$insertId : null;
        } catch (\PDOException $e) {
            throw new \Database\EDatabaseException($e->getMessage());
        }
    }

    private function executePreparedSQL(string $sql, string $paramtypes, array $parameters): ?int {
        $stmt = $this->prepare($sql);
        
        try {
            $stmt->execute($parameters);
            $insertId = $this->dbObj->lastInsertId();
            $this->affected_rows = $stmt->rowCount();
            
            return $insertId ? (int)$insertId : null;
        } catch (\PDOException $e) {
            if ($e->getCode() == '23000') { // Duplicate key error
                throw new \Database\EDuplicateKey($e->getMessage());
            } else {
                throw new \Database\EDatabaseException($e->getMessage());
            }
        }
    }

    public function executeSQL($sql, $paramtypes = null, ...$parameters) {
        $this->affected_rows = null;
        
        if ($paramtypes !== null) {
            // Flatten parameters array like original implementation
            $flatParams = [];
            foreach ($parameters as $param) {
                if (is_array($param)) {
                    $flatParams = array_merge($flatParams, $param);
                } else {
                    $flatParams[] = $param;
                }
            }
            
            return $this->executePreparedSQL($sql, $paramtypes, $flatParams);
        } else {
            return $this->executeSimpleSQL($sql);
        }
    }

    private function fetchSimpleResults(string $sql): ResultSetObjectPDO {
        $this->connect();

        try {
            $stmt = $this->dbObj->query($sql);
            return new ResultSetObjectPDO($stmt);
        } catch (\PDOException $e) {
            throw new \Database\EDatabaseException($e->getMessage());
        }
    }

    private function fetchPreparedResults(string $sql, string $paramtypes, array $params): ResultSetObjectPDO {
        $stmt = $this->prepare($sql);

        try {
            if (!empty($paramtypes) && !empty($params)) {
                $stmt->execute($params);
            } else {
                $stmt->execute();
            }
            
            return new ResultSetObjectPDO($stmt);
        } catch (\PDOException $e) {
            throw new \Database\EDatabaseException($e->getMessage());
        }
    }

    public function fetchResults($sql, $paramtypes = null, ...$parameters) {
        $this->affected_rows = null;
        
        if ($paramtypes !== null) {
            // Flatten parameters array like original implementation
            $flatParams = [];
            foreach ($parameters as $param) {
                if (is_array($param)) {
                    $flatParams = array_merge($flatParams, $param);
                } else {
                    $flatParams[] = $param;
                }
            }
            return $this->fetchPreparedResults($sql, $paramtypes, $flatParams);
        } else {
            return $this->fetchSimpleResults($sql);
        }
    }

    public function insertFromRecord($tablename, $paramtypes, $record) {
        return $this->insertRecord($tablename, false, $paramtypes, $record);
    }

    public function insertRecord(string $tablename, bool $ignore_duplicate_keys, string $paramtypes, array $record): ?int {
        if (strlen($paramtypes) != count($record)) {
            throw new \Database\EDatabaseException(__FUNCTION__ . ": Num elements in paramtype string != num of bind variables in record array. " . strlen($paramtypes) . " != " . count($record));
        }

        $vars = implode('`, `', array_keys($record));
        $placeholders = str_repeat('?,', count($record));
        $placeholders = rtrim($placeholders, ',');

        $tablename = trim($tablename, " `");
        
        if ($ignore_duplicate_keys) {
            $sql = "INSERT IGNORE INTO `{$tablename}` (`{$vars}`) VALUES ({$placeholders})";
        } else {
            $sql = "INSERT INTO `{$tablename}` (`{$vars}`) VALUES ({$placeholders})";
        }

        return $this->executeSQL($sql, $paramtypes, array_values($record));
    }

    /**
     * Check if database exists
     */
    public function databaseExists(): bool {
        try {
            // Connect without database name to check if server is reachable
            $dsn = "mysql:host={$this->host};charset={$this->charEncoding}";
            $options = [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            ];
            
            $conn = new \PDO($dsn, $this->username, $this->passwd, $options);
        } catch (\PDOException $e) {
            throw new \Database\ECouldNotConnectToServer("Check Config because " . $e->getMessage());
        }

        try {
            $stmt = $conn->prepare("SHOW DATABASES LIKE ?");
            $stmt->execute([$this->dbname]);
            $result = $stmt->fetchAll();
            
            if (count($result) === 0) {
                throw new \Database\EDatabaseMissing("Database '{$this->dbname}' not found.");
            }
            
            return count($result) > 0;
        } catch (\PDOException $e) {
            throw new \Database\EDatabaseException("Failed to query for DB existence: " . $e->getMessage());
        }
    }

    private function execSimple(string $sql): void {
        $this->connect();
        try {
            $this->dbObj->exec($sql);
        } catch (\PDOException $e) {
            throw new \Database\EDatabaseException($e->getMessage());
        }
    }

    /**
     * Execute multiple SQL statements from a string (for schema migrations)
     * Splits on semicolons and executes each statement separately
     */
    public function executeMultipleSQL(string $sql): void {
        $this->connect();
        
        // Split SQL into individual statements
        $statements = array_filter(
            array_map('trim', explode(';', $sql)),
            function($stmt) { return !empty($stmt); }
        );
        
        foreach ($statements as $statement) {
            if (!empty($statement)) {
                try {
                    $this->dbObj->exec($statement);
                } catch (\PDOException $e) {
                    throw new \Database\EDatabaseException("Error executing statement: $statement. Error: " . $e->getMessage());
                }
            }
        }
    }

    /**
     * Get the underlying PDO connection for advanced operations
     * Use sparingly - prefer using the abstracted methods
     */
    public function getPDOConnection(): \PDO {
        $this->connect();
        return $this->dbObj;
    }
}