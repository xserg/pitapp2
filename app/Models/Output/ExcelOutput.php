<?php

namespace App\Models\Output;

use PhpOffice\PhpSpreadsheet\Reader\IReader;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;
use App\Models\Output\ExcelOutput\ReadFilter;
use PhpOffice\PhpSpreadsheet\Style;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Exception;
use PhpOffice\PhpSpreadsheet\Writer\IWriter;

// This class creates a spreadsheet based on an existing template and input data
class ExcelOutput
{
    /**
     * @var Spreadsheet
     */
    protected $spreadsheet;

    /**
     * @var IReader.
     */
    protected $reader;

    protected $styleCache;

    /**
     * @param array $data
     */
    public function __construct($data)
    {
        // From stdClass to array
        $data = json_decode(json_encode($data), true);
        $this->reader = IOFactory::createReader('Xlsx');
        $this->spreadsheet = new Spreadsheet();
        try {
            $this->removeSheetByName('Worksheet');
            $this->setData($data);
        } catch (\PhpOffice\PhpSpreadsheet\Exception $e) {
            logger()->error($e->getMessage());
        }
    }

    /**
     * Remove worksheet by name
     * @param string $name
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    protected function removeSheetByName(string $name)
    {
        if ($this->spreadsheet->sheetNameExists($name)) {
            $sheetIndex = $this->spreadsheet->getIndex(
                $this->spreadsheet->getSheetByName($name)
            );
            $this->spreadsheet->removeSheetByIndex($sheetIndex);
        }
    }

    /**
     * load the right template spreadsheet
     * @param array $templateData
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    protected function setTemplate(array $templateData)
    {
        // Remove template
        $this->removeSheetByName('template');

        // Load template
        $this->reader->setReadFilter(new ReadFilter($templateData['range']));
        logger('Loading spreadsheet template data: ' .base_path() . $templateData['path'] );
        $template = $this->reader->load(base_path() . $templateData['path']);
        $template->getActiveSheet()->setTitle('template');

        // Set new template
        $this->spreadsheet->addExternalSheet($template->getActiveSheet());

        // Set column size and zoom scale
        $template = $this->spreadsheet->getSheetByName('template');
        $this->setColumnsSize($template, $templateData['columnSize'], range('A', $templateData['range']['endColumn']));
        $template->getSheetView()->setZoomScale($templateData['zoomScale']);
    }

    /**
     * @param $worksheet
     * @param int $size
     * @param $columnRange
     */
    protected function setColumnsSize($worksheet, int $size, $columnRange)
    {
        foreach($columnRange as $letter) {

            if ($letter == 'D') {
                $worksheet->getColumnDimension($letter)->setAutoSize(true);
            } else {
                $worksheet->getColumnDimension($letter)->setWidth($size);
            }
        }
    }

    /**
     * @param Style\Style $style
     * @param array|null $overrideStyles
     * @return array
     */
    protected function getFormatedStyle(Style\Style $style, $overrideStyles = null)
    {
        $formatedStyle = [
            'fill' => [
                'fillType' => $style->getFill()->getFillType(),
                'startColor' => ['argb' => $style->getFill()->getStartColor()->getARGB()],
                'endColor' => ['argb' => $style->getFill()->getEndColor()->getARGB()]
            ],
            'font' => [
                'size' => $style->getFont()->getSize(),
                'bold' => $style->getFont()->getBold(),
                'italic' => $style->getFont()->getItalic(),
                'color' => ['argb' => $style->getFont()->getColor()->getARGB()]
            ],
            'alignment' => [
                'horizontal' => $style->getAlignment()->getHorizontal()
            ]
        ];

        if ($overrideStyles) {
            $overrideStyles = is_array($overrideStyles)? $overrideStyles : [$overrideStyles];

            foreach ($overrideStyles as $overrideStyle) {
                $overrideStyle = explode(':', $overrideStyle);
                switch($overrideStyle[0]) {
                    case 'fill' :
                        $formatedStyle['fill']['startColor']['argb'] = $overrideStyle[1];
                        $formatedStyle['fill']['endColor']['argb'] = $overrideStyle[1];
                        $formatedStyle['fill']['fillType'] = Style\Fill::FILL_GRADIENT_LINEAR;
                        break;
                    case 'font-size' :
                        $formatedStyle['font']['size'] = $overrideStyle[1];
                        break;
                    case 'font-weight' :
                        $formatedStyle['font']['bold'] = $overrideStyle[1];
                        break;
                }
            }
        }

        return $formatedStyle;
    }

    /**
     * @param $string
     * @param array $worksheetData
     * @return mixed array | string
     */
    protected function replaceTagData($string, array $worksheetData){
        // return if string is empty
        if ($string === "" || $string === NULL) return false;

        if (preg_match('/<%(.*?)%>/', $string, $match) == 1) { // Match for data block tags <%tag_name%>
            $dataLabels = explode('.', $match[1]);
            return $worksheetData[$dataLabels[0]][$dataLabels[1]]; //returns array
        } else if (preg_match('/<&(.*?)&>/', $string, $match) == 1) { // Matches for style tags <&style_name:style_value&>
                $dataTag = $match[0];
                $styles = explode(';', $match[1]);
                return ['style' => $styles, 'value' => $this->replaceTagData(str_replace($dataTag, '', $string), $worksheetData)];
        } else if (preg_match('/<(.*?)>/', $string, $match) == 1) { // Match for cell data tags <tag_name>
            $dataTag = $match[0];
            $dataLabel = $match[1];
            $outputData = $this->getCellData($dataLabel, $worksheetData);
        }  else {
            return $string;
        }

        // Recursively check if there are any more tags in the same string
        return $this->replaceTagData(str_replace($dataTag, $outputData, $string), $worksheetData);
    }

    /**
     * Get the correct data from the worksheetData array
     * @param $dataLabel
     * @param array $worksheetData
     * @return string
     */
    protected function getCellData($dataLabel, array $worksheetData)
    {
        $dataLabels = explode('.', $dataLabel);
        $tmpData = $worksheetData;

        // loop through object to get the data
        foreach($dataLabels as $label) {
            if (isset($tmpData[$label])) {
                $tmpData = $tmpData[$label];
            } else {
                // The data does not exist
                $tmpData = "!" . $dataLabel . "!";
                break;
            }
        }

        return $tmpData;
    }

    /**
     * @param array $data
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    protected function setData($data)
    {
        foreach($data as $i => $datum) {
            $this->setWorksheetData($datum);
        }

        // Remove the template worksheet
        $this->removeSheetByName('template');
    }

    /**
     * Sanitize the title before it is set to the spreadsheet
     * @param $worksheet
     * @param string $title
     */
    protected function setTitle($worksheet, string $title)
    {
        $worksheet->setTitle(substr(str_replace($worksheet->getInvalidCharacters(), '', $title), 0, 31));
    }

    /**
     * @param array $worksheetData
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    protected function setWorksheetData(array $worksheetData)
    {
        $this->setTemplate($worksheetData['template']);
        $template = $this->spreadsheet->getSheetByName('template');
        $worksheet = clone $template;
        $this->setTitle($worksheet, $worksheetData['worksheetTitle']);
        $this->spreadsheet->addSheet($worksheet);

        $addRowIndex = 0; // For additional rows

        $this->styleCache = [];

        for ($row = 1; $row <= $worksheetData['template']['range']['rowCount']; ++$row) {
            for ($col = 1; $col <= 100; ++$col) {
                $cell = $template->getCellByColumnAndRow($col, $row);
                if ($value = $this->replaceTagData($cell->getFormattedValue(), $worksheetData)) {
                    // If data is array add new rows to spreadsheet
                    if (is_array($value)) {
                        $addRowIndex = $this->setCellArrayData($worksheet, $worksheetData, $row, $col, $addRowIndex, $value);
                        continue;
                    } else {
                        $this->setCellData($worksheet, $col, $row, $addRowIndex, $value);
                    }
                }
            }
        }

        foreach ($this->styleCache as $style => $cells) {
            /**
             * $cells is array of row,col pairs. E.g. [[col1, row1], [col2, row2], ..., [col1, row5]]
             * The goal is to apply the style to all the cells using spreadsheet ranges instead of
             * individual cell access.
             */

            $styleArr = json_decode($style, true);

            // We will first group the cells by cols they are in
            $byColumn = [];
            foreach ($cells as $cell) {
                if (!array_key_exists($cell[0], $byColumn)) {
                    $byColumn[$cell[0]] = [];
                }
                $sameCol = &$byColumn[$cell[0]];
                array_push($sameCol, $cell[1]);
            }

            /**
             * Format is now [col1 => [row1, row2, ..., rowN], col2 => [row1, ...]].
             */

            // Next we want to make sure rows are sorted
            foreach ($byColumn as $colNum => $cells) {
                // Order by row, ascending
                sort($cells);
                // Apply style to adjacent cells in groups
                $startRow = $endRow = null;
                foreach ($cells as $row) {
                    if (!$startRow) {
                        $startRow = $endRow = $row;
                    } else if ($row - $endRow == 1) {
                        $endRow = $row;
                    } else {
                        // Apply the style and reset start/end
                        $worksheet->getStyleByColumnAndRow($colNum, $startRow, $colNum, $endRow)
                            ->applyFromArray($styleArr);
                        $startRow = $endRow = $row;
                    }
                }
                // Apply the style and reset start/end to last section
                $worksheet->getStyleByColumnAndRow($colNum, $startRow, $colNum, $endRow)
                    ->applyFromArray($styleArr);
            }

        }
    }

    /**
     * @param $worksheet
     * @param array $worksheetData
     * @param int $rowIndex
     * @param int $columnIndex
     * @param int $addRowIndex
     * @param array $data
     * @return int
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    protected function setCellArrayData($worksheet, array $worksheetData, int $rowIndex, int $columnIndex, int $addRowIndex, array $data): int
    {
        $cIndex = $columnIndex;
        foreach ($data as $datum) {
            if (is_array($datum)) {
                $this->setCellArrayData($worksheet, $worksheetData, $rowIndex, $cIndex, $addRowIndex, $datum);
                $addRowIndex++;
            } else {
                $value = $this->replaceTagData($datum, $worksheetData);
                $style = isset($value['style']) ? $value['style'] : null;
                $value = isset($value['value']) ? $value['value'] : $value;
                $this->setCellData($worksheet, $cIndex, $rowIndex, $addRowIndex, $value, $style);
                $cIndex += 1;
            }
        }
        return $addRowIndex;
    }

    /**
     * Removes tags from worksheet if it has not been overridden by data
     * @param Worksheet $worksheet
     * @param int $colIndex
     * @param int $rowIndex
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    protected function removeTag(Worksheet $worksheet, int $colIndex, int $rowIndex)
    {
        $value = $worksheet->getCellByColumnAndRow($colIndex, $rowIndex)->getValue();
        if (preg_match('/<%(.*?)%>/', $value) == 1) {
            $worksheet->getCellByColumnAndRow($colIndex, $rowIndex)->setValue('');
        }
    }

    /**
     * @param Worksheet $worksheet
     * @param int $colIndex
     * @param int $rowIndex
     * @param int $addRowIndex
     * @param string $value
     * @param null $overrideStyles
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    protected function setCellData($worksheet, int $colIndex, int $rowIndex, int $addRowIndex, string $value, $overrideStyles = null)
    {
        $cell = $worksheet->getCellByColumnAndRow($colIndex, $rowIndex + $addRowIndex);
        $cell->setValue($value);
        $styleCell = $this->getFormatedStyle($worksheet->getCellByColumnAndRow($colIndex, $rowIndex)->getStyle(), $overrideStyles);
        $styleKey = json_encode($styleCell);
        if (!array_key_exists($styleKey, $this->styleCache)) {
            $this->styleCache[$styleKey] = [];
        }
        $styleCells = &$this->styleCache[$styleKey];
        array_push($styleCells, [$colIndex, $rowIndex + $addRowIndex]);
    }

    /**
     * Get all the worksheets data
     * @return array
     */
    public function getData()
    {
        $data = [];
        foreach($this->spreadsheet->getAllSheets() as $worksheet) {
            $data[] = getWorksheetData($worksheet);
        }

        return $data;
    }

    /**
     * Get spreadsheet data
     * @param Worksheet $worksheet
     * @return array
     */
    public function getWorksheetData($worksheet)
    {
        $data = [];
        $colIndex = 0; // Use number indexes instead of letters

        foreach ($worksheet->getRowIterator() as $rowIndex => $row) {
            foreach ($row->getCellIterator() as $cell) {
                // Only add to array if either style or value exists
                if ($cell->getStyle() !== NULL || $cell->getValue() !== NULL) {
                    $data[$rowIndex][$colIndex] = [
                        'value' => $cell->getFormattedValue(),
                        'style' => $this->getFormatedStyle($cell->getStyle())
                    ];
                }
                $colIndex++;
            }
            $colIndex = 0;
        }

        return $data;
    }

    /**
     * @param $fileName
     * @throws Exception
     */
    public function downloadSpreadsheet($fileName)
    {
        $writer = IOFactory::createWriter($this->spreadsheet, 'Xlsx');

        header("Content-Description: File Transfer");
        header('Content-Disposition: attachment;filename="' . $fileName . '.xlsx"');
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Transfer-Encoding: binary');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Expires: 0');

        return $writer->save('php://output');
    }
}
