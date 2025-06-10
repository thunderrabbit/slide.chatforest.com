<?php
namespace Database;

abstract class ResultSetObject implements \Iterator {
    /* Number of rows */
    protected $numRows;

    /* The current row */
    protected $data;

    /* Current row number */
    protected $currentRowNum = -1;

    protected $array_of_data;

    public function __get($var) {
        //variable $this->data is read only
        if($var == "data") {
            return $this->data;
        } else {
            return null;
        }
    }

    public function current(): mixed {
        return $this->data;
    }

    /* This method is called after Iterator::rewind and Iterator::next to check if the current position is valid. */
    public function valid(): bool {
        return $this->data != false;
    }

    /* Returns the key of the current element. */
    public function key(): mixed {
        return $this->currentRowNum;
    }

    /* Moves the current position to the next element.  */
    public function next(): void {
        $this->setRow($this->currentRowNum+1);
    }

    /* Rewinds back to the first element of the Iterator.  */
    public function rewind(): void {
        $this->setRow(0);
    }

    public function toArray() {
        if(!is_array($this->array_of_data)) {
            $this->array_of_data = array();
            if($this->numRows()) {
                $tmpRowNum = ($this->currentRowNum >= 0) ? $this->currentRowNum : 0;
                $this->rewind();

                while($this->valid()) {
                    $this->array_of_data[] = $this->data;
                    $this->next();
                }

                if($tmpRowNum !== false) {
                    $this->setRow($tmpRowNum);
                }
            }
        }
        return $this->array_of_data;
    }

	/* returns the number of rows in this ResultSet*/
    abstract public function numRows();
    abstract public function setRow($rowNum);
    abstract public function close();
}
