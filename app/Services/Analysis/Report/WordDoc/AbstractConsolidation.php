<?php
/**
 *
 */

namespace App\Services\Analysis\Report\WordDoc;


use App\Models\Hardware\AzureAds;
use App\Models\Project\Environment;
use Auth;
use PhpOffice\PhpWord\Element\Cell;
use PhpOffice\PhpWord\Element\Header;
use PhpOffice\PhpWord\Element\Table;

class AbstractConsolidation
{
    /**
     * @var Environment
     */
    protected $environment;

    /**
     * @var Environment
     */
    protected $existingEnvironment;

    protected $headerStyle, $headerText, $firstStyle, $secondStyle, $firstStyleTarget, $secondStyleTarget, $textStyle, $textStyleBold, $viewCpm, $isCloud, $tablePStyle, $isConverged, $fill, $flip;

    /**
     * @var \PhpOffice\PhpWord\Element\Section
     */
    protected $section;

    /**
     * @var
     */
    protected $header;

    /**
     * @var \PhpOffice\PhpWord\Element\Table
     */
    protected $table;
    protected $noDetails;
    protected $numMappedColumns;
    protected $displayWork;
    protected $displayLoc;
    protected $displayEnv;
    protected $cellSizeNumber;
    protected $cellSizeNumber2;
    protected $remainingSpace;
    protected $textBlockSize;
    protected $cellSizeText;
    protected $cellSizeText2;
    protected $num_headers;

    /**
     * @var Cell
     */
    protected $cell;
    protected $textStyleBG;
    protected $style;
    protected $styleTarget;

    /**
     * @param \PhpOffice\PhpWord\Element\Section $section
     * @param Header|array $header
     * @param Environment $environment
     * @param Environment $existingEnvironment
     * @return $this
     */
    public function drawConsolidation(\PhpOffice\PhpWord\Element\Section $section, $header,Environment $environment, Environment $existingEnvironment)
    {
        $this->_setInitialVariables($section, $header, $environment, $existingEnvironment);

        $this->_drawSummaryHeaders()
            ->_drawSummaryExisting()
            ->_drawSummaryTarget();

        Table::$__widthAddition = 0;

        $this
            ->_drawConsolidationsDetail();

        return $this;
    }

    /**
     * @param \PhpOffice\PhpWord\Element\Section $section
     * @param Header|array $header
     * @param Environment $environment
     * @param Environment $existingEnvironment
     * @return $this
     */
    protected function _setInitialVariables(\PhpOffice\PhpWord\Element\Section $section, $header,Environment $environment, Environment $existingEnvironment)
    {
        $this->headerStyle = array('bgColor' => 'A6A6A6', 'borderSize' => 6, 'size' => 7);
        $this->headerText = array('size' => 7, 'bold' => true);
        $this->firstStyle = array('bgColor' => 'EEECE1', 'borderSize' => 6, 'valign' => 'center');
        $this->secondStyle = array('bgColor' => 'FABF8F', 'borderSize' => 6, 'valign' => 'center');
        $this->firstStyleTarget = array('bgColor' => 'DAD8CD', 'borderSize' => 6, 'valign' => 'center');
        $this->secondStyleTarget = array('bgColor' => 'E7AB7B', 'borderSize' => 6, 'valign' => 'center');
        $this->textStyle = array('size' => 7);
        $this->textStyleBold = array('size' => 7, 'bold' => true);
        $this->environment = $environment;
        $this->existingEnvironment = $existingEnvironment;
        $this->section = $section;
        $this->header = $header;
        $this->isCloud = $environment->isCloud();
        $this->viewCpm = Auth::user()->user->view_cpm || $this->isCloud;

        $this->isConverged = $environment->isConverged();
        
        $this->section->addPageBreak();
        $this->table = $this->section->addTable('bottomRuleTable');
        $this->table->addRow();
        $this->table->addCell(11000, array('borderBottomSize' => 1, 'borderBottomColor' => '000000'))->addText(htmlspecialchars($this->environment->name) . ' Consolidation Analysis', 'pageHeader');
        $this->section->addTextBreak(1);

        $this->num_headers = count($this->header);
        $this->displayWork = $this->displayLoc = $this->displayEnv = false;
        foreach ($this->environment->analysis->consolidations as $consolidation) {
            foreach ($consolidation->servers as $server) {
                if ($server->workload_type) {
                    $this->displayWork = true;
                }
                if ($server->location) {
                    $this->displayLoc = true;
                }
                if ($server->environment_detail) {
                    $this->displayEnv = true;
                }
            }
        }

        $this->noDetails = false;
        $this->numMappedColumns = $this->displayWork + $this->displayLoc + $this->displayEnv;
        if ($this->numMappedColumns == 0) {
            $this->numMappedColumns = 1;
            $this->noDetails = true;
        }

        $this->_setCellSizes();

        return $this;
    }

    /**
     * @return $this
     */
    protected function _setCellSizes()
    {
        $this->cellSizeNumber = 10800 / (18 + $this->isConverged);
        $this->cellSizeNumber2 = 10800 / 17;
        $this->remainingSpace = (10800 - $this->cellSizeNumber * (5  + $this->isConverged) - $this->cellSizeNumber2 * 2);
        $this->textBlockSize = $this->remainingSpace / (8 + 2 * $this->numMappedColumns);
        $this->cellSizeText = $this->textBlockSize * 2;
        $this->cellSizeText2 = $this->textBlockSize * 3;
        $this->tablePStyle = 'singleSpace';

        return $this;
    }

    /**
     * @return $this
     */
    protected function _drawSummaryHeaders()
    {
        $this->table = $this->section->addTable(array('cellMarginLeft' => 50, 'cellMarginRight' => 50, 'cellMarginBottom' => 0));
        $this->table->addRow();
        $this->cell = $this->table->addCell($this->cellSizeText * ($this->numMappedColumns + 1) + $this->cellSizeText2 * 2, array('valign' => 'center'));
        if ($this->numMappedColumns > 0)
            $this->cell->getStyle()->setGridSpan(2);
        $this->cell->addText("Summary", array('size' => 11, 'bold' => true), $this->tablePStyle);

        $this->table->addCell($this->cellSizeNumber, $this->headerStyle)->addText("Servers", $this->headerText, $this->tablePStyle);

        for ($i = 7; $i < $this->num_headers; ++$i) {
            switch ($i) {
                case 7:
                case 8:
                case 9:
                    if ($this->isConverged && $i == 9) {
                        $this->table->addCell($this->cellSizeNumber, $this->headerStyle)->addText("Useable Storage (TB)", $this->headerText, $this->tablePStyle);
                        $this->table->addCell($this->cellSizeNumber, $this->headerStyle)->addText("IOPS", $this->headerText, $this->tablePStyle);
                    }
                    $this->table->addCell($this->cellSizeNumber, $this->headerStyle)->addText($this->header[$i], $this->headerText, $this->tablePStyle);
                    break;
                case 10:
                    $this->table->addCell($this->cellSizeNumber, $this->headerStyle)->addText($this->environment->analysis->totals->existing->ramMatch ? $this->header[$i] : "RAM @ Util", $this->headerText, $this->tablePStyle);
                    break;
                case 11:
                    if ($this->isCloud)
                        $this->table->addCell($this->cellSizeNumber2, $this->headerStyle)->addText("Cores @ 100%", $this->headerText, $this->tablePStyle);
                    else
                        $this->table->addCell($this->cellSizeNumber2, $this->headerStyle)->addText($this->header[$i], $this->headerText, $this->tablePStyle);
                    break;
                case 12:
                    if ($this->isCloud)
                        $this->table->addCell($this->cellSizeNumber2, $this->headerStyle)->addText("Cores @ " . $this->existingEnvironment->cpu_utilization . "%", $this->headerText, $this->tablePStyle);
                    else
                        $this->table->addCell($this->cellSizeNumber2, $this->headerStyle)->addText($this->environment->analysis->totals->existing->cpuMatch ? $this->header[$i] : "CPM @ Util", $this->headerText, $this->tablePStyle);
                    break;
                default:
                    $this->table->addCell($this->cellSizeText, $this->headerStyle)->addText($this->header[$i], $this->headerText, $this->tablePStyle);
            }
        }
        $this->section->addTextBreak(1);
        $this->table->addRow();
        if ($this->numMappedColumns > 0) {
            $this->cell = $this->table->addCell($this->cellSizeText * $this->numMappedColumns, array('size' => 7));
            $this->cell->addText(' ', $this->textStyle, $this->tablePStyle);
        }
        return $this;
    }
    
    /**
     * @return $this
     */
    protected function _drawSummaryExisting()
    {
        $this->cell = $this->table->addCell($this->cellSizeText2 * 2 + $this->cellSizeText, array('bgColor' => 'A6A6A6', 'borderSize' => 6, 'size' => 7, 'valign' => 'center'));
        $this->cell->addText('Existing Environment Totals', $this->textStyle, $this->tablePStyle);
        $this->table->addCell($this->cellSizeNumber, $this->firstStyle)->addText(number_format($this->environment->analysis->totals->existing->servers), $this->textStyle, $this->tablePStyle);
        $this->table->addCell($this->cellSizeNumber, $this->firstStyle)->addText(number_format($this->environment->analysis->totals->existing->socket_qty), $this->textStyle, $this->tablePStyle);
        $this->table->addCell($this->cellSizeNumber, $this->firstStyle)->addText(number_format($this->environment->analysis->totals->existing->total_cores), $this->textStyle, $this->tablePStyle);
        if ($this->isConverged) {
            $this->table->addCell($this->cellSizeNumber, $this->firstStyle)->addText(number_format($this->environment->analysis->totals->storage->existing, 2), $this->textStyle, $this->tablePStyle);
            $this->table->addCell($this->cellSizeNumber, $this->firstStyle)->addText(number_format($this->environment->analysis->totals->iops->existing, 0), $this->textStyle, $this->tablePStyle);
        }

        $this->table->addCell($this->cellSizeNumber, $this->firstStyle)->addText(number_format(round($this->environment->analysis->totals->existing->ram), 0), $this->textStyle, $this->tablePStyle);
        $this->table->addCell($this->cellSizeNumber, $this->firstStyle)->addText(number_format(round($this->environment->analysis->totals->existing->computedRam), 0), $this->textStyle, $this->tablePStyle);
        if (!$this->isCloud) {
            $this->table->addCell($this->cellSizeNumber2, $this->firstStyle)->addText(number_format(round($this->environment->analysis->totals->existing->rpm), 0), $this->textStyle, $this->tablePStyle);
            $this->table->addCell($this->cellSizeNumber2, $this->firstStyle)->addText(number_format(round($this->environment->analysis->totals->existing->computedRpm), 0), $this->textStyle, $this->tablePStyle);
        } else {
            $this->table->addCell($this->cellSizeNumber2, $this->firstStyle)->addText(number_format(round($this->environment->analysis->totals->existing->cores), 0), $this->textStyle, $this->tablePStyle);
            $this->table->addCell($this->cellSizeNumber2, $this->firstStyle)->addText(number_format(round($this->environment->analysis->totals->existing->computedCores), 0), $this->textStyle, $this->tablePStyle);
        }
        $this->table->addRow();
        if ($this->numMappedColumns > 0) {
            $this->cell = $this->table->addCell($this->cellSizeText * $this->numMappedColumns, array('size' => 7));
            $this->cell->addText(' ', $this->textStyle, $this->tablePStyle);
        }
        $this->cell = $this->table->addCell($this->cellSizeText2 * 2 + $this->cellSizeText, array('bgColor' => 'A6A6A6', 'borderSize' => 6, 'size' => 7, 'valign' => 'center'));
        return $this;
    }

    /**
     * @return $this
     */
    protected function _drawSummaryTarget()
    {
        $this->cell->addText('Target Environment Totals', $this->textStyle, $this->tablePStyle);
        $this->table->addCell($this->cellSizeNumber, $this->firstStyle)->addText(number_format($this->environment->analysis->totals->target->servers), $this->textStyle, $this->tablePStyle);
        $this->table->addCell($this->cellSizeNumber, $this->firstStyle)->addText(!isset($this->environment->analysis->totals->target->socket_qty) ? "" : number_format($this->environment->analysis->totals->target->socket_qty), $this->textStyle, $this->tablePStyle);
        $this->table->addCell($this->cellSizeNumber, $this->firstStyle)->addText(number_format($this->environment->analysis->totals->target->total_cores), $this->textStyle, $this->tablePStyle);
        if ($this->isConverged) {
            $this->table->addCell($this->cellSizeNumber, $this->firstStyle)->addText(number_format($this->environment->analysis->totals->storage->targetTotal, 2), $this->textStyle, $this->tablePStyle);
            $this->table->addCell($this->cellSizeNumber, $this->firstStyle)->addText(number_format($this->environment->analysis->totals->iops->targetTotal, 0), $this->textStyle, $this->tablePStyle);
        }

        $this->cell = $this->table->addCell($this->cellSizeNumber * 2, $this->firstStyle);
        $this->cell->getStyle()->setGridSpan(2);
        $this->cell->addText(number_format(round($this->environment->analysis->totals->target->utilRam, 0)), $this->textStyle, $this->tablePStyle);
        $this->cell = $this->table->addCell($this->cellSizeNumber2 * 2, $this->firstStyle);
        $this->cell->getStyle()->setGridSpan(2);
        if (!$this->isCloud) {
            $this->cell->addText(!isset($this->environment->analysis->totals->target->utilRpm) ? "" : number_format(round($this->environment->analysis->totals->target->utilRpm, 0)), $this->textStyle, $this->tablePStyle);
        } else {
            $this->cell->addText(number_format($this->environment->analysis->totals->target->total_cores, 0), $this->textStyle, $this->tablePStyle);
        }
        return $this;
    }

    /**
     * @return $this
     */
    protected function _drawConsolidationsDetail()
    {
        $this->_drawConsolidationDetailHeader();

        $this->fill = 0;
        $this->flip = false;
        foreach ($this->environment->analysis->consolidations as $consIndex => $consolidation) {
            $this->_drawConsolidationDetail($consIndex, $consolidation);
        }

        if ($this->isConverged) {
            if (isset($this->environment->analysis->storage) && count($this->environment->analysis->storage)) {
                $this->_drawStorageConsolidationDetail();
            }
            if (isset($this->environment->analysis->iops) && count($this->environment->analysis->iops)) {
                $this->_drawIopsConsolidationDetail();
            }
            if (isset($this->environment->analysis->converged) && count($this->environment->analysis->converged)) {
                $this->_drawConvergedAdditionalDetail();
            }
        }

        return $this;
    }

    /**
     * @param $consIndex
     * @param $consolidation
     * @return $this
     */
    protected function _drawConsolidationDetail($consIndex, $consolidation)
    {
        if ($this->flip) {
            $this->style = $this->secondStyle;
            $this->styleTarget = $this->secondStyleTarget;
            $this->textStyleBG = array('size' => 7, 'color' => 'FABF8F');
        } else {
            $this->style = $this->firstStyle;
            $this->styleTarget = $this->firstStyleTarget;
            $this->textStyleBG = array('size' => 7, 'color' => 'EEECE1');
        }
        
        foreach ($consolidation->servers as $server) {
            $this->_drawConsolidationDetailExisting($server);
        }

        $this->_drawConsolidationDetailSubtotal($consolidation)
            ->_drawConsolidationDetailTargetHeader();

        foreach ($consolidation->targets as $index => $target) {
            $this->_drawConsolidationDetailTarget($consIndex, $consolidation, $index, $target);
        }
        $this->flip = !$this->flip;

        return $this;
    }

    /**
     * @return $this
     */
    protected function _drawConsolidationDetailHeader()
    {
        $this->table = $this->section->addTable(array('cellMarginLeft' => 50, 'cellMarginRight' => 50, 'cellMarginBottom' => 0));
        $this->table->addRow();
        for ($i = 0; $i < $this->num_headers; ++$i) {
            switch ($i) {
                case 0:
                    if ($this->displayLoc)
                        $this->table->addCell($this->cellSizeText, $this->headerStyle)->addText($this->header[$i], $this->headerText, $this->tablePStyle);
                    break;
                case 1:
                    if ($this->displayEnv)
                        $this->table->addCell($this->cellSizeText, $this->headerStyle)->addText($this->header[$i], $this->headerText, $this->tablePStyle);
                    break;
                case 2:
                    if ($this->displayWork)
                        $this->table->addCell($this->cellSizeText, $this->headerStyle)->addText($this->header[$i], $this->headerText, $this->tablePStyle);
                    if ($this->noDetails)
                        $this->table->addCell($this->cellSizeText, $this->headerStyle)->addText("", $this->headerText, $this->tablePStyle);
                    break;
                case 4:
                case 5:
                    $this->table->addCell($this->cellSizeText2, $this->headerStyle)->addText($this->header[$i], $this->headerText, $this->tablePStyle);
                    break;
                case 6:
                case 7:
                case 8:
                    $this->table->addCell($this->cellSizeNumber, $this->headerStyle)->addText($this->header[$i], $this->headerText, $this->tablePStyle);
                    break;
                case 9:
                    if ($this->isConverged) {
                        $this->cell = $this->table->addCell($this->cellSizeNumber, $this->headerStyle);
                        $this->cell->addText("Useable Storage (TB)", $this->headerText, $this->tablePStyle);
                        $this->cell = $this->table->addCell($this->cellSizeNumber, $this->headerStyle);
                        $this->cell->addText("IOPS", $this->headerText, $this->tablePStyle);
                    }
                    $this->cell = $this->table->addCell($this->cellSizeNumber + (!$this->viewCpm * $this->cellSizeNumber2), $this->headerStyle);
                    $this->cell->addText($this->header[$i], $this->headerText, $this->tablePStyle);
                    break;
                case 10:
                    $this->cell = $this->table->addCell($this->cellSizeNumber + (!$this->viewCpm * $this->cellSizeNumber2), $this->headerStyle);
                    $this->cell->addText($this->environment->analysis->totals->existing->ramMatch ? $this->header[$i] : "RAM @ Util", $this->headerText, $this->tablePStyle);
                    break;
                case 11:
                    if ($this->isCloud)
                        $this->table->addCell($this->cellSizeNumber2, $this->headerStyle)->addText("Cores @ 100%", $this->headerText, $this->tablePStyle);
                    else {
                        if ($this->viewCpm)
                            $this->table->addCell($this->cellSizeNumber2, $this->headerStyle)->addText($this->header[$i], $this->headerText, $this->tablePStyle);
                    }
                    break;
                case 12:
                    if ($this->isCloud)
                        $this->table->addCell($this->cellSizeNumber2, $this->headerStyle)->addText("Cores @ " . $this->existingEnvironment->cpu_utilization . "%", $this->headerText, $this->tablePStyle);
                    else {
                        if ($this->viewCpm) {
                            $this->table->addCell($this->cellSizeNumber2, $this->headerStyle)->addText($this->environment->analysis->totals->existing->cpuMatch ? $this->header[$i] : "CPM @ Util", $this->headerText, $this->tablePStyle);
                        }
                    }
                    break;
                default:
                    $this->table->addCell($this->cellSizeText, $this->headerStyle)->addText($this->header[$i], $this->headerText, $this->tablePStyle);
            }
        }

        return $this;
    }

    /**
     * @param $server
     * @return $this
     */
    protected function _drawConsolidationDetailExisting($server)
    {
        $this->table->addRow();
        if ($this->displayLoc) {
            $this->table->addCell($this->cellSizeText, $this->style)->addText($server->location, $this->textStyle, $this->tablePStyle);
        }
        if ($this->displayEnv) {
            $this->table->addCell($this->cellSizeText, $this->style)->addText($server->environment_detail, $this->textStyle, $this->tablePStyle);
        }
        if ($this->displayWork) {
            $this->table->addCell($this->cellSizeText, $this->style)->addText($server->workload_type, $this->textStyle, $this->tablePStyle);
        }
        if ($this->noDetails) {
            $this->table->addCell($this->cellSizeText, $this->style)->addText("", $this->textStyle, $this->tablePStyle);
        }
        $this->table->addCell($this->cellSizeText, $this->style)->addText($server->manufacturer ? $server->manufacturer->name : "", $this->textStyle, $this->tablePStyle);
        $this->table->addCell($this->cellSizeText2, $this->style)->addText($server->server ? $server->server->name : "", $this->textStyle, $this->tablePStyle);
        $this->table->addCell($this->cellSizeText2, $this->style)->addText($server->processor->name, $this->textStyle, $this->tablePStyle);
        $this->table->addCell($this->cellSizeNumber, $this->style)->addText($server->processor->ghz, $this->textStyle, $this->tablePStyle);
        $this->table->addCell($this->cellSizeNumber, $this->style)->addText(number_format($server->processor->socket_qty), $this->textStyle, $this->tablePStyle);
        $this->table->addCell($this->cellSizeNumber, $this->style)->addText(number_format($server->processor->total_cores), $this->textStyle, $this->tablePStyle);
        if ($this->isConverged) {
            $this->table->addCell($this->cellSizeNumber, $this->style)->addText("", $this->textStyle, $this->tablePStyle);
            $this->table->addCell($this->cellSizeNumber, $this->style)->addText("", $this->textStyle, $this->tablePStyle);
        }

        $this->cell = $this->table->addCell($this->cellSizeNumber + (!$this->viewCpm * $this->cellSizeNumber2), $this->style);
        $this->cell->addText(number_format(round($server->baseRam)), $this->textStyle, $this->tablePStyle);
        $this->cell = $this->table->addCell($this->cellSizeNumber + (!$this->viewCpm * $this->cellSizeNumber2), $this->style);
        $this->cell->addText(number_format(round($server->computedRam)), $this->textStyle, $this->tablePStyle);
        if (!$this->isCloud) {
            if ($this->viewCpm) {
                $this->table->addCell($this->cellSizeNumber2, $this->style)->addText(number_format(round($server->baseRpm)), $this->textStyle, $this->tablePStyle);
                $this->table->addCell($this->cellSizeNumber2, $this->style)->addText(number_format(round($server->computedRpm)), $this->textStyle, $this->tablePStyle);
            }
        } else {
            $this->table->addCell($this->cellSizeNumber2, $this->style)->addText(number_format(round($server->baseCores)), $this->textStyle, $this->tablePStyle);
            $this->table->addCell($this->cellSizeNumber2, $this->style)->addText(number_format(round($server->computedCores)), $this->textStyle, $this->tablePStyle);
        }
        
        return $this;
    }
    
    /**
     * @param $consolidation
     * @return $this
     */
    protected function _drawConsolidationDetailSubtotal($consolidation)
    {
        $this->table->addRow();
        $this->cell = $this->table->addCell($this->cellSizeText * ($this->numMappedColumns + 1) + $this->cellSizeText2 * 2 + $this->cellSizeNumber * 3, $this->style);

        $addConverged = 0;
        if ($this->isConverged) {
            $addConverged++;
        }

        $this->cell->getStyle()->setGridSpan(6 + $this->numMappedColumns + $this->isConverged + $addConverged);
        $this->cell->addText('Sub-total', $this->textStyleBold, 'singleSpaceRight');

        $this->cell = $this->table->addCell($this->cellSizeNumber + (!$this->viewCpm * $this->cellSizeNumber2), $this->style);
        $this->cell->addText(number_format(round($consolidation->ramTotal)), $this->textStyle, $this->tablePStyle);
        $this->cell = $this->table->addCell($this->cellSizeNumber + (!$this->viewCpm * $this->cellSizeNumber2), $this->style);
        $this->cell->addText(number_format(round($consolidation->computedRamTotal)), $this->textStyle, $this->tablePStyle);

        if (!$this->isCloud && $this->viewCpm) {
            $this->table->addCell($this->cellSizeNumber2, $this->style)->addText(number_format(round($consolidation->rpmTotal)), $this->textStyle, $this->tablePStyle);
            $this->table->addCell($this->cellSizeNumber2, $this->style)->addText(number_format(round($consolidation->computedRpmTotal)), $this->textStyle, $this->tablePStyle);
        } else {
            $this->table->addCell($this->cellSizeNumber2, $this->style)->addText(number_format(round($consolidation->coreTotal)), $this->textStyle, $this->tablePStyle);
            $this->table->addCell($this->cellSizeNumber2, $this->style)->addText(number_format(round($consolidation->computedCoreTotal)), $this->textStyle, $this->tablePStyle);
        }

        return $this;
    }

    /**
     * @param $consolidation
     * @return $this
     */
    protected function _drawConsolidationDetailTargetHeader()
    {
        $this->table->addRow();
        if ($this->numMappedColumns > 0) {
            $text = '';
            $this->cell = $this->table->addCell($this->cellSizeText * ($this->numMappedColumns + $this->isConverged), $this->styleTarget, $this->tablePStyle);
            $this->cell->getStyle()->setGridSpan($this->numMappedColumns);
            $this->cell->addText($text, $this->textStyleBold, 'singleSpaceRight');
        }
        for ($i = 3; $i < 9; ++$i) {
            switch ($i) {
                case 4:
                case 5:
                    $this->table->addCell($this->cellSizeText2, $this->headerStyle)->addText($this->header[$i], $this->headerText, $this->tablePStyle);
                    break;
                case 6:
                case 7:
                case 8:
                    $this->table->addCell($this->cellSizeNumber, $this->headerStyle)->addText($this->header[$i], $this->headerText, $this->tablePStyle);
                    break;
                default:
                    $this->table->addCell($this->cellSizeText, $this->headerStyle)->addText($this->header[$i], $this->headerText, $this->tablePStyle);
            }
        }
        if ($this->isConverged) {
            $this->table->addCell($this->cellSizeNumber, $this->headerStyle)->addText("Useable Storage (TB)", $this->headerText, $this->tablePStyle);
            $this->table->addCell($this->cellSizeNumber, $this->headerStyle)->addText("IOPS", $this->headerText, $this->tablePStyle);
        }

        $this->cell = $this->table->addCell($this->cellSizeNumber * 2 + (!$this->viewCpm * $this->cellSizeNumber2 * 2), $this->headerStyle);
        $this->cell->getStyle()->setGridSpan(2 /*+ (!$this->viewCpm * 2)*/);
        $this->cell->addText("RAM (GB)", $this->headerText, $this->tablePStyle);

        if (!$this->isCloud && $this->viewCpm) {
            $this->cell = $this->table->addCell($this->cellSizeNumber2 * 2, $this->headerStyle);
            $this->cell->getStyle()->setGridSpan(2);
            $this->cell->addText("CPM", $this->headerText, $this->tablePStyle);
        } else {
            $this->cell = $this->table->addCell($this->cellSizeNumber2 * 2, $this->headerStyle);
            $this->cell->getStyle()->setGridSpan(2);
            $this->cell->addText("Cores", $this->headerText, $this->tablePStyle);
        }

        return $this;
    }

    /**
     * @param $consIndex
     * @param $consolidation
     * @param $index
     * @param $target
     * @return $this
     */
    protected function _drawConsolidationDetailTarget($consIndex, $consolidation, $index, $target)
    {
        $this->table->addRow();
        if ($this->numMappedColumns > 0) {
            $text = $index == 0 ? 'Consolidation Target' : ' ';
            $this->cell = $this->table->addCell($this->cellSizeText * ($this->numMappedColumns * $this->isConverged), $this->styleTarget, $this->tablePStyle);
            $this->cell->getStyle()->setGridSpan($this->numMappedColumns);
            $this->cell->addText($text, $this->textStyleBold, 'singleSpaceRight');
        }
        if (!isset($target->instance_type)) {
            $manufacturer = $target->manufacturer->name;
        } else {
            if ($target->instance_type == "Azure" || $target->instance_type === AzureAds::INSTANCE_TYPE_ADS) {
                $manufacturer = "Microsoft";
            } else if ($target->instance_type == "Google") {
                $manufacturer = "Google";
            } else if ($target->instance_type == "IBMPVS") {
                $manufacturer = "IBMPVS";
            } else {
                $manufacturer = "AWS";
            }
        }
        $this->table->addCell($this->cellSizeText, $this->styleTarget)->addText($manufacturer, $this->textStyle, $this->tablePStyle);
        $this->table->addCell($this->cellSizeText2, $this->styleTarget)->addText(!isset($target->server) ? "" : $target->server->name, $this->textStyle, $this->tablePStyle);
        $this->table->addCell($this->cellSizeText2, $this->styleTarget)->addText($target->processor->name, $this->textStyle, $this->tablePStyle);
        $this->table->addCell($this->cellSizeNumber, $this->styleTarget)->addText(!isset($target->processor->ghz) ? "" : $target->processor->ghz, $this->textStyle, $this->tablePStyle);
        $this->table->addCell($this->cellSizeNumber, $this->styleTarget)->addText(!isset($target->processor->socket_qty) ? "" : number_format($target->processor->socket_qty), $this->textStyle, $this->tablePStyle);
        $this->table->addCell($this->cellSizeNumber, $this->styleTarget)->addText(number_format($target->processor->total_cores), $this->textStyle, $this->tablePStyle);
        if ($this->isConverged) {
            $this->table->addCell($this->cellSizeNumber, $this->styleTarget)->addText(number_format($target->useable_storage, 2), $this->textStyle, $this->tablePStyle);
            $this->table->addCell($this->cellSizeNumber, $this->styleTarget)->addText(number_format($target->iops, 0), $this->textStyle, $this->tablePStyle);
        }

        $this->cell = $this->table->addCell($this->cellSizeNumber * 2 + (!$this->viewCpm * $this->cellSizeNumber2 * 2), $this->styleTarget);
        $this->cell->getStyle()->setGridSpan(2 /*+ (!$this->viewCpm * 2 * 2)*/);
        $this->cell->addText(number_format(round($target->utilRam)), $this->textStyle, $this->tablePStyle);
        if (!$this->isCloud) {
            if ($this->viewCpm) {
                $this->cell = $this->table->addCell($this->cellSizeNumber2 * 2, $this->styleTarget);
                $this->cell->getStyle()->setGridSpan(2);
                $this->cell->addText(!isset($target->utilRpm) ? "" : number_format(round($target->utilRpm)), $this->textStyle, $this->tablePStyle);
            }
        } else {
            $this->cell = $this->table->addCell($this->cellSizeNumber2 * 2, $this->styleTarget);
            $this->cell->getStyle()->setGridSpan(2);
            $this->cell->addText(number_format($target->processor->total_cores), $this->textStyle, $this->tablePStyle);
        }

        if (!is_null($consIndex) && !is_null($consolidation)) {
            if (count($this->environment->analysis->consolidations) != ($consIndex + 1) &&
                count($consolidation->targets) == ($index + 1)) {
                $this->table->addRow();
                $this->cell = $this->table->addCell(10800, $this->style);
                $this->cell->getStyle()->setGridSpan(8 + $this->numMappedColumns + (2 * $this->viewCpm) + $this->isConverged);
                $this->cell->addText("_", $this->textStyleBG, $this->tablePStyle);
            }
        }

        return $this;
    }

    /**
     * @return $this
     */
    protected function _drawStorageConsolidationDetail()
    {
        $this->section->addTextBreak(1);
        $this->section->addText(
            "RAM and CPM constraints are met.",
            $this->textStyleBold,
            'singleSpace'
        );

        $this->section->addText("Useable storage deficit = " . number_format($this->environment->analysis->totals->storage->existing, 2) .
            "TB - " . number_format($this->environment->analysis->totals->storage->target, 2) .
            "TB = " . number_format($this->environment->analysis->totals->storage->deficit, 2) .
            "TB",
            $this->textStyleBold,
            'singleSpace'
        );

        $this->section->addText("Additional Nodes Required for Useable Storage deficit:", $this->textStyleBold, 'singleSpace');

        $this->table = $this->section->addTable(array('cellMarginLeft' => 50, 'cellMarginRight' => 50, 'cellMarginBottom' => 0));


        $this->_drawConsolidationDetailTargetHeader();

        foreach ($this->environment->analysis->storage as $index => $target) {
            $this->_drawConsolidationDetailTarget(null, null, $index, $target);
        }

        return $this;
    }

    /**
     * @return $this
     */
    protected function _drawIopsConsolidationDetail()
    {
        $this->section->addTextBreak(1);
        $this->section->addText(
            "RAM, CPM and Useable Storage constraints are met.", $this->textStyleBold,
            'singleSpace')
        ;
        $this->section->addText("IOPS deficit = " . number_format($this->environment->analysis->totals->iops->existing, 0) .
            " IOPS - " . number_format($this->environment->analysis->totals->iops->target, 0) .
            " IOPS = " . number_format($this->environment->analysis->totals->iops->deficit, 0) . ' IOPS',
            $this->textStyleBold,
            'singleSpace'
        );

        $this->section->addText("Additional Nodes Required for IOPS deficit:", $this->textStyleBold, 'singleSpace');

        $this->table = $this->section->addTable(array('cellMarginLeft' => 50, 'cellMarginRight' => 50, 'cellMarginBottom' => 0));

        $this->_drawConsolidationDetailTargetHeader();

        foreach ($this->environment->analysis->iops as $index => $target) {
            $this->_drawConsolidationDetailTarget(null, null, $index, $target);
        }

        return $this;
    }

    /**
     * @return $this
     */
    protected function _drawConvergedAdditionalDetail()
    {
        $this->section->addTextBreak(1);
        $this->section->addText("The consolidation analysis results require one node.  Converged environments require at least two nodes. An additional node was added to satisfy the two node minimum requirement:", $this->textStyleBold, 'singleSpace');

        $this->table = $this->section->addTable(array('cellMarginLeft' => 50, 'cellMarginRight' => 50, 'cellMarginBottom' => 0));

        $this->_drawConsolidationDetailTargetHeader();

        foreach ($this->environment->analysis->converged as $index => $target) {
            $this->_drawConsolidationDetailTarget(null, null, $index, $target);
        }

        return $this;
    }
}