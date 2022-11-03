<?php
/**
 *
 */

namespace App\Services\Analysis\Report\WordDoc;


use App\Models\Hardware\AzureAds;
use PhpOffice\PhpWord\Element\Table;

class VmConsolidation extends AbstractConsolidation
{
    /**
     * @return $this|AbstractConsolidation
     */
    protected function _setCellSizes()
    {
        $this->cellSizeNumber = 10800 / (20);
        $this->cellSizeNumber2 = 10800 / 15;
        $this->remainingSpace = (10800 - $this->cellSizeNumber * (5) - $this->cellSizeNumber2 * 2);
        $this->textBlockSize = $this->remainingSpace / (8 + 2 * 2);
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
        $widthAddition = $this->environment->isConverged() ? 0 : ($this->cellSizeText + $this->cellSizeNumber) / (3);

        Table::$__widthAddition = $widthAddition - 0;

        $totals = &$this->environment->analysis->totals;
        $cpuMatch = function() use ($totals) {
            return ($totals->existing->cpuMatch ?? false)  ? round($this->existingEnvironment->getCpuUtilization(),0) . '%' : 'Util';
        };
        $ramMatch = function() use ($totals) {
            return ($totals->existing->ramMatch ?? false)  ? round($this->existingEnvironment->getRamUtilization(),0) . '%' : 'Util';
        };
        $this->table = $this->section->addTable(array('cellMarginLeft' => 50, 'cellMarginRight' => 50, 'cellMarginBottom' => 0));
        $this->table->addRow();
        $this->cell = $this->table->iwaddCell($this->cellSizeText * 2 + $this->cellSizeText2, array('valign' => 'center'));
        if ($this->numMappedColumns > 0) {
            $this->cell->getStyle()->setGridSpan(2);
        }
        $this->cell->addText("Summary", array('size' => 11, 'bold' => true), $this->tablePStyle);

        $this->table->addCell($this->cellSizeNumber, $this->headerStyle)->addText("VM Servers", $this->headerText, $this->tablePStyle);
        $this->table->addCell($this->cellSizeNumber, $this->headerStyle)->addText("VM Total Cores", $this->headerText, $this->tablePStyle);

        $this->table->addCell($this->cellSizeNumber, $this->headerStyle)->addText("VM RAM @ 100%", $this->headerText, $this->tablePStyle);
        $this->table->addCell($this->cellSizeNumber, $this->headerStyle)->addText("VM RAM @" . $ramMatch(), $this->headerText, $this->tablePStyle);

        $this->table->addCell($this->cellSizeNumber2, $this->headerStyle)->addText("VM Cores @ 100%", $this->headerText, $this->tablePStyle);
        $this->table->addCell($this->cellSizeNumber2, $this->headerStyle)->addText("VM Cores @" . $cpuMatch(), $this->headerText, $this->tablePStyle);

        $this->section->addTextBreak(1);
        $this->table->addRow();
        if ($this->numMappedColumns > 0) {
            $this->cell = $this->table->iwaddCell($this->cellSizeText, array('size' => 7));
            $this->cell->addText(' ', $this->textStyle, $this->tablePStyle);
        }
        return $this;
    }

    /**
     * @return $this
     */
    protected function _drawSummaryExisting()
    {
        $totals = &$this->environment->analysis->totals;
        $this->cell = $this->table->iwaddCell($this->cellSizeText2 + $this->cellSizeText, array('bgColor' => 'A6A6A6', 'borderSize' => 6, 'size' => 7, 'valign' => 'center'));
        $this->cell->addText('Existing Environment Totals', $this->textStyle, $this->tablePStyle);

        $this->table->addCell($this->cellSizeNumber, $this->firstStyle)->addText(number_format($totals->existing->vms), $this->textStyle, $this->tablePStyle);
        $this->table->addCell($this->cellSizeNumber, $this->firstStyle)->addText(number_format($totals->existing->total_cores), $this->textStyle, $this->tablePStyle);

        $this->table->addCell($this->cellSizeNumber, $this->firstStyle)->addText(number_format(round($totals->existing->ram), 0), $this->textStyle, $this->tablePStyle);
        $this->table->addCell($this->cellSizeNumber, $this->firstStyle)->addText(number_format(round($totals->existing->computedRam), 0), $this->textStyle, $this->tablePStyle);

        $this->table->addCell($this->cellSizeNumber2, $this->firstStyle)->addText(mixed_number(round($totals->existing->cores), 0), $this->textStyle, $this->tablePStyle);
        $this->table->addCell($this->cellSizeNumber2, $this->firstStyle)->addText(mixed_number(round($totals->existing->computedCores), 0), $this->textStyle, $this->tablePStyle);

        $this->table->addRow();
        if ($this->numMappedColumns > 0) {
            $this->cell = $this->table->iwaddCell($this->cellSizeText, array('size' => 7));
            $this->cell->addText(' ', $this->textStyle, $this->tablePStyle);
        }
        return $this;
    }

    /**
     * @return $this
     */
    protected function _drawSummaryTarget()
    {
        $totals = &$this->environment->analysis->totals;
        $this->cell = $this->table->iwaddCell($this->cellSizeText2 + $this->cellSizeText, array('bgColor' => 'A6A6A6', 'borderSize' => 6, 'size' => 7, 'valign' => 'center'));
        $this->cell->addText('Target Environment Totals', $this->textStyle, $this->tablePStyle);

        $this->table->addCell($this->cellSizeNumber, $this->firstStyle)->addText(number_format($totals->target->vms), $this->textStyle, $this->tablePStyle);
        $this->table->addCell($this->cellSizeNumber, $this->firstStyle)->addText(mixed_number($totals->target->total_cores), $this->textStyle, $this->tablePStyle);

        $this->cell = $this->table->addCell($this->cellSizeNumber, $this->firstStyle);
        $this->cell->addText(number_format(round($totals->target->utilRam, 0)), $this->textStyle, $this->tablePStyle);

        $this->cell = $this->table->addCell($this->cellSizeNumber, $this->firstStyle);
        $this->cell->addText(number_format(round($totals->target->utilRam, 0)), $this->textStyle, $this->tablePStyle);

        $this->cell = $this->table->addCell($this->cellSizeNumber2, $this->firstStyle);
        $this->cell->addText(number_format($totals->target->total_cores, 0), $this->textStyle, $this->tablePStyle);
        $this->cell = $this->table->addCell($this->cellSizeNumber2, $this->firstStyle);
        $this->cell->addText(number_format($totals->target->total_cores, 0), $this->textStyle, $this->tablePStyle);

        return $this;
    }

    /**
     * @return $this
     */
    protected function _setExistingWidthAddition()
    {
        $widthAddition = 0;
        if (!$this->isCloud && !$this->viewCpm) {
            $additionColumns = 0;
            if ($this->displayLoc) {
                $additionColumns++;
            }
            if ($this->displayEnv) {
                $additionColumns++;
            }
            if ($this->displayWork) {
                $additionColumns++;
            }
            if ($this->noDetails) {
                $additionColumns++;
            }
            if (!$this->displayLoc || !$this->displayEnv || !$this->displayWork) {
                $additionColumns++;
            }

            $additionColumns += 4;
            $widthAddition = ($this->cellSizeText * 2) / $additionColumns;
        }
        Table::$__widthAddition = $widthAddition;

        return $this;
    }

    /**
     * @return $this
     */
    protected function _setSubtotalWidthAddition()
    {
        $widthAddition = 0;
        if (!$this->environment->isCloud() && !$this->viewCpm) {
            $widthAddition = ($this->cellSizeText * 2);
        }
        Table::$__widthAddition = $widthAddition;

        return $this;
    }

    /**
     * @return $this
     */
    protected function _setTargetWidthAddition()
    {
        $widthAddition = 0;
        Table::$__widthAddition = $widthAddition;

        if ($this->numMappedColumns >= 2) {
            for ($i = $this->numMappedColumns; $i > 1; $i--) {
                Table::$__widthAddition -= $this->cellSizeText / ($this->numMappedColumns == 2 ? 24 : 20);
            }
        } else {
            Table::$__widthAddition += 75;
        }

        return $this;
    }

    /**
     * @return $this
     */
    protected function _drawConsolidationDetailHeader()
    {
        $this->_setExistingWidthAddition();
        $totals = &$this->environment->analysis->totals;
        $cpuMatch = function() use ($totals) {
            return ($totals->existing->cpuMatch ?? false)  ? round($this->existingEnvironment->getCpuUtilization(),0) . '%' : 'Util';
        };
        $ramMatch = function() use ($totals) {
            return ($totals->existing->ramMatch ?? false)  ? round($this->existingEnvironment->getRamUtilization(),0) . '%' : 'Util';
        };
        $this->table = $this->section->addTable(array('cellMarginLeft' => 50, 'cellMarginRight' => 50, 'cellMarginBottom' => 0));
        $this->table->addRow();

        $multiplier = $this->numMappedColumns <= 1 ? 2 : 1;

        if ($this->displayLoc) {
            $this->cell = $this->table->addCell($this->cellSizeText * $multiplier, $this->headerStyle);
            $this->cell->addText($this->header[0], $this->headerText, $this->tablePStyle);
        }
        if ($this->displayEnv) {
            $this->cell = $this->table->addCell($this->cellSizeText * $multiplier, $this->headerStyle);
            $this->cell->addText($this->header[1], $this->headerText, $this->tablePStyle);
        }
        if ($this->displayWork) {
            $this->cell = $this->table->addCell($this->cellSizeText * $multiplier, $this->headerStyle);
            $this->cell->addText($this->header[2], $this->headerText, $this->tablePStyle);
        }
        if ($this->noDetails) {
            $this->cell = $this->table->addCell($this->cellSizeText * $multiplier, $this->headerStyle);
            $this->cell->addText("", $this->headerText, $this->tablePStyle);
        }

        $this->cell = $this->cell = $this->table->addCell($this->cellSizeText * 3, $this->headerStyle);
        $this->cell->getStyle()->setGridSpan(3);
        $this->cell->addText("VM ID", $this->headerText, $this->tablePStyle);

        $multiplier = 1;
        $this->cell = $this->table->addCell($this->cellSizeText, $this->headerStyle);
        $this->cell->addText("VM Total Cores", $this->headerText, $this->tablePStyle);

        $this->table->addCell($this->cellSizeText, $this->headerStyle)->addText("VM Ram @ 100%", $this->headerText, $this->tablePStyle);
        $this->table->addCell($this->cellSizeText, $this->headerStyle)->addText("VM Ram @ " . $ramMatch(), $this->headerText, $this->tablePStyle);

        $this->table->addCell($this->cellSizeText, $this->headerStyle)->addText("VM Cores @ 100%", $this->headerText, $this->tablePStyle);
        $this->table->addCell($this->cellSizeText, $this->headerStyle)->addText("VM Cores @ " . $cpuMatch(), $this->headerText, $this->tablePStyle);

        return $this;
    }

    /**
     * @param $server
     * @return $this
     */
    protected function _drawConsolidationDetailExisting($server)
    {
        $this->table->addRow();
        $multiplier = $this->numMappedColumns <= 1 ? 2 : 1;
        if ($this->displayLoc) {
            $this->table->addCell($this->cellSizeText * $multiplier, $this->style)->addText($server->location, $this->textStyle, $this->tablePStyle);
        }
        if ($this->displayEnv) {
            $this->table->addCell($this->cellSizeText * $multiplier, $this->style)->addText($server->environment_detail, $this->textStyle, $this->tablePStyle);
        }
        if ($this->displayWork) {
            $this->table->addCell($this->cellSizeText * $multiplier, $this->style)->addText($server->workload_type, $this->textStyle, $this->tablePStyle);
        }
        if ($this->noDetails) {
            $this->table->addCell($this->cellSizeText * $multiplier, $this->style)->addText("", $this->textStyle, $this->tablePStyle);
        }
        $this->cell = $this->table->addCell($this->cellSizeText * 3, $this->style);
        $this->cell->getStyle()->setGridSpan(3);
        $this->cell->addText($server->vm_id ?: "", $this->textStyle, $this->tablePStyle);

        $multiplier = 1;
        $this->cell = $this->table->addCell($this->cellSizeText * $multiplier, $this->style);
        $this->cell->getStyle()->setGridSpan($multiplier);
        $this->cell->addText(mixed_number($server->vm_cores ?: 0), $this->textStyle, $this->tablePStyle);

        $this->table->addCell($this->cellSizeText, $this->style)->addText(number_format(round($server->baseRam)), $this->textStyle, $this->tablePStyle);
        $this->table->addCell($this->cellSizeText, $this->style)->addText(number_format(round($server->computedRam)), $this->textStyle, $this->tablePStyle);

        $this->table->addCell($this->cellSizeText, $this->style)->addText(mixed_number(round($server->baseCores)), $this->textStyle, $this->tablePStyle);
        $this->table->addCell($this->cellSizeText, $this->style)->addText(mixed_number(round($server->computedCores)), $this->textStyle, $this->tablePStyle);

        return $this;
    }

    /**
     * @param $consolidation
     * @return $this
     */
    protected function _drawConsolidationDetailSubtotal($consolidation)
    {
//        $this->_setSubtotalWidthAddition();
        $this->table->addRow();

        $cols = $this->numMappedColumns + 1 + 2 + 1;

/*        if ($this->numMappedColumns <= 2) {
            $cols++;
        }*/

        $this->cell = $this->table->addCell($this->cellSizeText * $cols, $this->style);

        $this->cell->getStyle()->setGridSpan($cols);
        $this->cell->addText('Sub-total', $this->textStyleBold, 'singleSpaceRight');

        $this->cell = $this->table->iwaddCell($this->cellSizeText, $this->style)->addText(number_format(round($consolidation->computedRamTotal)), $this->textStyle, $this->tablePStyle);
        $this->cell = $this->table->iwaddCell($this->cellSizeText, $this->style)->addText(number_format(round($consolidation->computedRamTotal)), $this->textStyle, $this->tablePStyle);

        $this->table->addCell($this->cellSizeText, $this->style)->addText(number_format(round($consolidation->coreTotal)), $this->textStyle, $this->tablePStyle);
        $this->table->addCell($this->cellSizeText, $this->style)->addText(number_format(round($consolidation->computedCoreTotal)), $this->textStyle, $this->tablePStyle);

        return $this;
    }

    /**
     * @param $consolidation
     * @return $this
     */
    protected function _drawConsolidationDetailTargetHeader()
    {
        $this->_setTargetWidthAddition();
        $this->table->addRow();
        if ($this->numMappedColumns > 0) {
            $text = '';
            $multiplier = min($this->numMappedColumns, 3);
            for ($i = 0; $i < $multiplier; $i++) {
                $this->cell = $this->table->addCell($this->cellSizeText, $this->styleTarget, $this->tablePStyle);
                $this->cell->addText($text, $this->textStyleBold, 'singleSpaceRight');
            }
        }

        /*for ($i = 0; $i < 8; $i++) {
            $this->cell = $this->table->addCell($this->cellSizeText, $this->headerStyle)->addText("", $this->headerText, $this->tablePStyle);
        }
        return $this;*/

        $this->cell = $this->table->addCell($this->cellSizeText, $this->headerStyle)->addText("Manufacturer", $this->headerText, $this->tablePStyle);
        $this->cell = $this->table->addCell($this->cellSizeText, $this->headerStyle)->addText("Processor Type", $this->headerText, $this->tablePStyle);
        $this->cell = $this->table->addCell($this->cellSizeText, $this->headerStyle)->addText("Total Cores", $this->headerText, $this->tablePStyle);


        $this->table->addCell($this->cellSizeText, $this->headerStyle)->addText("", $this->headerText, $this->tablePStyle);

        $this->cell = $this->table->addCell($this->cellSizeText , $this->headerStyle);
        $this->cell->addText("RAM (GB)", $this->headerText, $this->tablePStyle);

        $this->cell = $this->table->addCell($this->cellSizeText , $this->headerStyle);
        $this->cell->addText("", $this->headerText, $this->tablePStyle);


        $this->cell = $this->table->addCell($this->cellSizeText, $this->headerStyle);
        $this->cell->addText("Cores", $this->headerText, $this->tablePStyle);

        $this->cell = $this->table->addCell($this->cellSizeText, $this->headerStyle);
        $this->cell->addText("", $this->headerText, $this->tablePStyle);
//        $this->cell = $this->table->addCell($this->cellSizeText * 1, $this->headerStyle);
//        $this->cell->addText("", $this->headerText, $this->tablePStyle);

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
            $text = '';
            $multiplier = min($this->numMappedColumns, 3);
            for ($i = 0; $i < $multiplier; $i++) {
                $this->cell = $this->table->addCell($this->cellSizeText, $this->styleTarget, $this->tablePStyle);
                $this->cell->addText($text, $this->textStyleBold, 'singleSpaceRight');
            }
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

        $proc =$target->processor->name;
        if ($target->instance_type === AzureAds::INSTANCE_TYPE_ADS) {
            $proc = $target->server->name . " / " . $proc;
        }
        $this->table->addCell($this->cellSizeText, $this->styleTarget)->addText($manufacturer, $this->textStyle, $this->tablePStyle);
        $this->table->addCell($this->cellSizeText, $this->styleTarget)->addText($proc, $this->textStyle, $this->tablePStyle);
        $this->table->addCell($this->cellSizeText, $this->styleTarget)->addText(number_format($target->processor->total_cores), $this->textStyle, $this->tablePStyle);

        $this->table->addCell($this->cellSizeText, $this->styleTarget)->addText("", $this->textStyle, $this->tablePStyle);

        $this->cell = $this->table->addCell($this->cellSizeNumber, $this->styleTarget);
        $this->cell->addText(number_format(round($target->utilRam)), $this->textStyle, $this->tablePStyle);

        $this->cell = $this->table->addCell($this->cellSizeNumber, $this->styleTarget);
        $this->cell->addText(number_format(round($target->utilRam)), $this->textStyle, $this->tablePStyle);

        $this->cell = $this->table->addCell($this->cellSizeText, $this->styleTarget);
        $this->cell->addText(number_format($target->processor->total_cores), $this->textStyle, $this->tablePStyle);

        $this->cell = $this->table->addCell($this->cellSizeText, $this->styleTarget);
        $this->cell->addText(number_format($target->processor->total_cores), $this->textStyle, $this->tablePStyle);

        if (!is_null($consIndex) && !is_null($consolidation)) {
            if (count($this->environment->analysis->consolidations) != ($consIndex + 1) &&
                count($consolidation->targets) == ($index + 1)) {
                $this->table->addRow();
                $this->cell = $this->table->addCell(10800, $this->style);
                $this->cell->getStyle()->setGridSpan(10 + $this->numMappedColumns + $this->isConverged);
                $this->cell->addText("_", $this->textStyleBG, $this->tablePStyle);
            }
        }

        return $this;
    }
}