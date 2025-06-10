<?php
namespace Database;

class ResultSetObjectStmt extends ResultSetObject {
    /* PHP result resource */

    private $stmt;
    private $fields;
    public $data = array();     // optimally public readonly.   Will hold the results of each statement for us to access

    /* Create a resultset */

    public function __construct($stmt) {
        $this->stmt = $stmt;
        $this->fields = $this->loadFieldInfo($this->stmt);
    }

    /* returns the number of rows in this ResultSet */

    public function numRows() {
        if (is_object($this->stmt)) {
            return $this->stmt->num_rows();
        }
    }

    public function setRow($rowNum) {
        if (is_object($this->stmt) && $this->numRows() > $rowNum) {
            $this->stmt->data_seek($rowNum);
            $this->currentRowNum = $rowNum;
            $vars = array();

            foreach ($this->fields as $field) {
                $fieldname = $field->name;
                $vars[$fieldname] = null;
                $this->data[$fieldname] = & $vars[$fieldname];
            }

            // PHP 8.1 requires spread operator https://www.phind.com/search?cache=nv6roe91bx0vkkqbt7x0w3uk
            if (!$this->stmt->bind_result(...array_values($this->data))) {
                throw new \Database\EDatabaseException("Bind result failed.  Check for duplicate vars in SELECT");
            }

            $this->stmt->fetch();
        } else {
            $this->data = array();     // must be an array for line 35 above
        }
    }

    public function fieldList($var) {
        return $this->fields;
    }

    public function close() {
        if (is_object($this->stmt)) {
            $this->stmt->free_result();
            $this->stmt->close();
        }
    }

    private function loadFieldInfo($resultSet) {
        $metadata = $resultSet->result_metadata();
        $fields = $metadata->fetch_fields();
        $metadata->close();
        return $fields;
    }

    function __destruct() {
        $this->close();
    }

}
