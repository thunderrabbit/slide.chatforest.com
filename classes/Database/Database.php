<?php
namespace Database;

class Database implements DbInterface {

    private $charEncoding = "UTF8";
    private $dbObj;
    private $host;
    private $username;
    private $passwd;
    private $dbname;
    private $tz_offset = false;
    private $persistant;
    private $affected_rows;

    public function __construct($host, $username, $passwd, $dbname = '', $charEncoding = 'UTF8') {
        $this->host = $host;
        $this->username = $username;
        $this->passwd = $passwd;
        $this->dbname = $dbname;
        $this->charEncoding = $charEncoding;
        if ($this->dbname && strtolower(substr($this->dbname, 0, 2)) == "p:") {
            $this->persistant = true;
        } else {
            $this->persistant = false;
        }
    }

// end __construct

    public function connect() {
//If we're already connected return true;
        if (!is_object($this->dbObj) || !$this->dbObj->ping()) {

            $this->dbObj = new \mysqli($this->host, $this->username, $this->passwd, $this->dbname);

            if (!is_object($this->dbObj) || $this->dbObj->connect_error) {
                sleep(1);
                $this->dbObj = new \mysqli($this->host, $this->username, $this->passwd, $this->dbname);
                if (!is_object($this->dbObj) || $this->dbObj->connect_error) {
                    throw new \Database\EDatabaseException("Could not connect to server after trying with 1s sleep (" . $this->dbObj->errno . ") " . $this->dbObj->error);
                } else {
                    $this->setEncoding($this->charEncoding);
                }
            } else {
                $this->setEncoding($this->charEncoding);
            }
        }
        $this->setTimezone();
        return true;
    }

    public function setTimezone(){
        $now = new \DateTime();
        $mins = $now->getOffset() / 60;

        $sgn = ($mins < 0 ? -1 : 1);
        $mins = abs($mins);
        $hrs = floor($mins / 60);
        $mins -= $hrs * 60;
        $offset = sprintf('%+d:%02d', $hrs*$sgn, $mins);

        if($this->tz_offset === false || $this->tz_offset != $offset){
            $this->tz_offset = $offset;
            $this->executeSql("SET time_zone='$offset';");
        }
    }

    public function setEncoding($charEncoding) {
        $this->charEncoding = $charEncoding;
        $this->dbObj->set_charset($this->charEncoding);
    }

    public function prepare($sql) {
        $this->connect(); //Connect if we aren't already

        if (!$stmt = $this->dbObj->prepare($sql))
            throw new \Database\EDatabaseException($this->dbObj->error);
        return $stmt;
    }

    /*
     * Executes an UPDATE, INSERT or DELETE statement on the database.
     * Returns the insert_id or affected_rows, where relevant, or null.
     */

    private function executeSimpleSQL($sql) {
        $this->connect(); //Connect if we aren't already

        $this->dbObj->query($sql);

        $result = ($this->dbObj->insert_id > 0) ? $this->dbObj->insert_id : NULL;
        $this->affected_rows = $this->dbObj->affected_rows;

        return $result;
    }

    /*
     * Executes an UPDATE, INSERT or DELETE statement on the database.
     * Returns the insert_id or affected_rows, where relevant, or null.
     */

    private function executePreparedSQL($sql, $paramtypes, $parameters) {
        $bindParam = [];
        //Connect attempt is made in the prepare function.
        $stmt = $this->prepare($sql);

        $bindParam[] = $paramtypes;

        $param_arr = array_merge($bindParam, $parameters);
        if (!call_user_func_array(array($stmt, 'bind_param'), $this->refValues($param_arr))) {
            throw new \Database\EDatabaseException("Bind parameters failed");
        }

        $stmt->execute();
        if ($this->dbObj->errno != 0) {
            if ($this->dbObj->errno == ERROR_DUPLICATE_KEY) {
                throw new \Database\EDuplicateKey($this->dbObj->error);
            } else {
                throw new \Database\EDatabaseException($this->dbObj->error);
            }
        }
        $stmt->store_result();

        $result = ($stmt->insert_id > 0) ? $stmt->insert_id : NULL;
        $this->affected_rows = $stmt->affected_rows;

        $stmt->free_result();
        $stmt->close();
        unset($stmt);
        return $result;
    }

    public function executeSQL($sql, $paramtypes = null, $var1 = null) {
        $this->affected_rows = NULL;
        if (isset($paramtypes)) {
            $parameters = array();
            $paramcount = func_num_args();
            for ($i = 2; $i < $paramcount; $i++) {
                $tmpVar = func_get_arg($i);
                if (is_array($tmpVar)) {
                    foreach ($tmpVar as $var) {
                        $parameters[] = $var;
                    }
                } else {
                    $parameters[] = $tmpVar;
                }
            }

            return $this->executePreparedSQL($sql, $paramtypes, $parameters);
        } else {
            return $this->executeSimpleSQL($sql);
        }
    }

    private function fetchSimpleResults($sql) {
        $this->connect(); //Connect if we aren't already

        $result = $this->dbObj->query($sql);

        return new ResultSetObjectResult($result);
    }

    private function fetchPreparedResults($sql, $paramtypes, $params) {
        $bindParam = [];
        //Connect attempt is made in the prepare function.
        $stmt = $this->prepare($sql);

        if (!empty($paramtypes) && !empty($params)) {
            $bindParam[] = $paramtypes;
            $param_arr = array_merge($bindParam, $params);
            if (!call_user_func_array(array($stmt, 'bind_param'), $this->refValues($param_arr))) {
                throw new \Database\EDatabaseException("Bind parameters failed");
            }
        }

        $stmt->execute();
        if ($this->dbObj->errno != 0) {
            throw new \Database\EDatabaseException($this->dbObj->error);
        }
        $stmt->store_result();

        return new ResultSetObjectStmt($stmt);
    }

    private function refValues($arr) {
        if (strnatcmp(phpversion(), '5.3') >= 0) { //Reference is required for PHP 5.3+
            $refs = array();
            foreach ($arr as $key => $value)
                $refs[$key] = &$arr[$key];
            return $refs;
        }
        return $arr;
    }

    public function fetchResults($sql, $paramtypes = null, $var1 = null) {
        $this->affected_rows = NULL; // Reset this here because we shouldn't have this set for a result.
        if (isset($paramtypes) && $paramtypes) {
            $params = array();
            $paramcount = func_num_args();
            for ($i = 2; $i < $paramcount; $i++) {
                $tmpVar = func_get_arg($i);
                if (is_array($tmpVar)) {
                    foreach ($tmpVar as $var) {
                        $params[] = $var;
                    }
                } else {
                    $params[] = $tmpVar;
                }
            }
            return $this->fetchPreparedResults($sql, $paramtypes, $params);
        } else {
            return $this->fetchSimpleResults($sql);
        }
    }

    public function insertFromRecord($tablename, $paramtypes, $record) {
        return $this->insertRecord($tablename, false, $paramtypes, $record);
    }

    public function insertRecord($tablename, $ignore_duplicate_keys, $paramtypes, $record) {
        $vars = NULL;
        $values = NULL;

        if (strlen($paramtypes) != (is_countable($record) ? count($record) : 0)) {
            throw new \Database\EDatabaseException(__FUNCTION__ . ": Num elements in paramtype string != num of bind variables in record array. " . strlen($paramtypes) . " != " . (is_countable($record) ? count($record) : 0));
        }

        foreach ($record as $key => $val) {
            $vars .= "`" . $key . "`, ";
            $values .=" ? , ";
        }
        $vars = rtrim($vars, ", ");
        $values = rtrim($values, ", ");

        if ($ignore_duplicate_keys) {
            return $this->executeSQL("INSERT IGNORE INTO `" . trim($tablename, " `") . "` ({$vars}) VALUES ({$values}) ", $paramtypes, $record);
        } else {
            return $this->executeSQL("INSERT INTO `" . trim($tablename, " `") . "` ({$vars}) VALUES ({$values}) ", $paramtypes, $record);
        }
    }
}
