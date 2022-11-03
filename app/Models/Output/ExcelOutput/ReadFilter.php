<?php
namespace App\Models\Output\ExcelOutput;

use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

class ReadFilter implements IReadFilter
{
    private $startRow = 0;
    private $endRow   = 0;
    private $columns  = [];

    public function __construct($range) {
        $this->startRow = 0;
        $this->endRow   = $range['rowCount'];
        $this->columns  = range('A', $range['endColumn']);
    }

    public function readCell($column, $row, $worksheetName = '') {
        //  Only read the rows and columns that were configured
        if ($row >= $this->startRow && $row <= $this->endRow) {
            if (in_array($column,$this->columns)) {
                return true;
            }
        }
        return false;
    }
}