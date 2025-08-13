<?php
namespace Database;

class ResultSetObjectPDO extends ResultSetObject {
    /* PDO statement */
    private ?\PDOStatement $stmt;
    private array $allData = [];
    private bool $dataLoaded = false;

    /* Create a resultset */
    public function __construct(?\PDOStatement $stmt) {
        $this->stmt = $stmt;
        $this->loadAllData();
    }

    private function loadAllData(): void {
        if ($this->dataLoaded || $this->stmt === null) {
            return;
        }
        
        try {
            $this->allData = $this->stmt->fetchAll(\PDO::FETCH_ASSOC);
            $this->dataLoaded = true;
            $this->numRows = count($this->allData);
        } catch (\PDOException $e) {
            $this->allData = [];
            $this->numRows = 0;
        }
    }

    /* returns the number of rows in this ResultSet */
    public function numRows() {
        return $this->numRows ?? 0;
    }

    public function setRow($rowNum) {
        if ($rowNum >= 0 && $rowNum < $this->numRows()) {
            $this->currentRowNum = $rowNum;
            $this->data = $this->allData[$rowNum];
        } else {
            $this->data = false;
        }
    }

    /* OVERRIDE WITH A FASTER VERSION FOR PDO STATEMENTS - Advance to the next row */
    public function next(): void {
        $nextRowNum = $this->currentRowNum + 1;
        if ($nextRowNum < $this->numRows()) {
            $this->currentRowNum = $nextRowNum;
            $this->data = $this->allData[$nextRowNum];
        } else {
            $this->data = false;
        }
    }

    public function fieldList(): array {
        if ($this->stmt === null) {
            return [];
        }
        
        $fields = [];
        $columnCount = $this->stmt->columnCount();
        
        for ($i = 0; $i < $columnCount; $i++) {
            $meta = $this->stmt->getColumnMeta($i);
            $fields[] = (object) [
                'name' => $meta['name'] ?? '',
                'table' => $meta['table'] ?? '',
                'type' => $meta['native_type'] ?? '',
            ];
        }
        
        return $fields;
    }

    public function close() {
        if ($this->stmt !== null) {
            $this->stmt->closeCursor();
            $this->stmt = null;
        }
    }

    function __destruct() {
        $this->close();
    }
}