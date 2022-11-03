<?php
/**
 *
 */

namespace App\Services\Analysis\Report\WordDoc;


use App\Models\Hardware\AzureAds;
use PhpOffice\PhpWord\Element\Table;

class HybridConsolidation extends AbstractConsolidation
{
    /**
     * @return $this|AbstractConsolidation
     */
    protected function _setCellSizes()
    {
        $this->cellSizeNumber = 10800 / (14);
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
        $widthAddition = $this->environment->isConverged() ? 0 : ($this->cellSizeText + $this->cellSizeNumber) / (9);

        Table::$__widthAddition = $widthAddition - 70;

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

        $this->table->addCell($this->cellSizeNumber, $this->headerStyle)->addText("Physical Servers", $this->headerText, $this->tablePStyle);
        $this->table->addCell($this->cellSizeNumber, $this->headerStyle)->addText("Processors", $this->headerText, $this->tablePStyle);
        $this->table->addCell($this->cellSizeNumber, $this->headerStyle)->addText("Physical Cores", $this->headerText, $this->tablePStyle);
        if ($this->isCloud) {
            $this->table->addCell($this->cellSizeNumber2, $this->headerStyle)->addText("Physical Cores @" . $cpuMatch(), $this->headerText, $this->tablePStyle);
        }
        

        if ($this->isConverged) {
            $this->table->addCell($this->cellSizeText, $this->headerStyle)->addText("Useable Storage (TB)", $this->headerText, $this->tablePStyle);
            $this->table->addCell($this->cellSizeNumber, $this->headerStyle)->addText("IOPS", $this->headerText, $this->tablePStyle);
        }

        $this->table->addCell($this->cellSizeNumber, $this->headerStyle)->addText("Physical RAM @" . $ramMatch(), $this->headerText, $this->tablePStyle);


        if (!$this->isCloud) {
            $this->table->addCell($this->cellSizeNumber2, $this->headerStyle)->addText("Physical CPM @" . $cpuMatch(), $this->headerText, $this->tablePStyle);
        }
        $this->table->addCell($this->cellSizeNumber, $this->headerStyle)->addText("VM Servers", $this->headerText, $this->tablePStyle);
        $this->table->addCell($this->cellSizeNumber, $this->headerStyle)->addText("VM Cores", $this->headerText, $this->tablePStyle);
        $this->table->addCell($this->cellSizeNumber, $this->headerStyle)->addText("VM RAM @" . $ramMatch(), $this->headerText, $this->tablePStyle);

        if (!$this->isCloud) {
            $this->table->addCell($this->cellSizeNumber2, $this->headerStyle)->addText("VM CPM @" . $cpuMatch(), $this->headerText, $this->tablePStyle);
        } else if ($this->isCloud) {
            $this->table->addCell($this->cellSizeNumber2, $this->headerStyle)->addText("VM Cores @" . $cpuMatch(), $this->headerText, $this->tablePStyle);
        } else if (!$this->viewCpm) {
//            $this->table->addCell($this->cellSizeNumber2, $this->headerStyle)->addText("", $this->headerText, $this->tablePStyle);
//            $this->table->addCell($this->cellSizeNumber2, $this->headerStyle)->addText("", $this->headerText, $this->tablePStyle);
        }

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
        if ($this->isCloud) {
            return $this->_drawCloudSummaryExisting();
        } else {
            return $this->_drawPhysicalSummaryExisting();
        }
    }

    /**
     * @return $this
     */
    protected function _drawCloudSummaryExisting()
    {
        $totals = &$this->environment->analysis->totals;
        $this->cell = $this->table->iwaddCell($this->cellSizeText2 + $this->cellSizeText, array('bgColor' => 'A6A6A6', 'borderSize' => 6, 'size' => 7, 'valign' => 'center'));
        $this->cell->addText('Existing Environment Totals', $this->textStyle, $this->tablePStyle);
        $this->table->addCell($this->cellSizeNumber, $this->firstStyle)->addText(number_format($totals->existing->servers), $this->textStyle, $this->tablePStyle);
        $this->table->addCell($this->cellSizeNumber, $this->firstStyle)->addText(!isset($totals->existing->socket_qty) ? "" : number_format($totals->existing->socket_qty), $this->textStyle, $this->tablePStyle);
        $this->table->addCell($this->cellSizeNumber, $this->firstStyle)->addText(mixed_number($totals->existing->physical_cores), $this->textStyle, $this->tablePStyle);
        $this->table->addCell($this->cellSizeNumber2, $this->firstStyle)->addText(number_format($totals->existing->physicalCoresUtil), $this->textStyle, $this->tablePStyle);
        $this->table->addCell($this->cellSizeNumber, $this->firstStyle)->addText(number_format(round($totals->existing->physicalRamUtil), 0), $this->textStyle, $this->tablePStyle);
        $this->table->addCell($this->cellSizeNumber, $this->firstStyle)->addText(number_format($totals->existing->vms), $this->textStyle, $this->tablePStyle);
        $this->table->addCell($this->cellSizeNumber, $this->firstStyle)->addText(floatval(number_format($totals->existing->vm_cores, 1)), $this->textStyle, $this->tablePStyle);
        $this->table->addCell($this->cellSizeNumber, $this->firstStyle)->addText(number_format(round($totals->existing->computedRam), 0), $this->textStyle, $this->tablePStyle);
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
    protected function _drawPhysicalSummaryExisting()
    {
        $totals = &$this->environment->analysis->totals;
        $this->cell = $this->table->iwaddCell($this->cellSizeText2 + $this->cellSizeText, array('bgColor' => 'A6A6A6', 'borderSize' => 6, 'size' => 7, 'valign' => 'center'));
        $this->cell->addText('Existing Environment Totals', $this->textStyle, $this->tablePStyle);
        $this->table->addCell($this->cellSizeNumber, $this->firstStyle)->addText(number_format($totals->existing->servers), $this->textStyle, $this->tablePStyle);
        $this->table->addCell($this->cellSizeNumber, $this->firstStyle)->addText(!isset($totals->existing->socket_qty) ? "" : number_format($totals->existing->socket_qty), $this->textStyle, $this->tablePStyle);
        $this->table->addCell($this->cellSizeNumber, $this->firstStyle)->addText(mixed_number($totals->existing->physical_cores), $this->textStyle, $this->tablePStyle);
        if ($this->isConverged) {
            $this->table->addCell($this->cellSizeText, $this->firstStyle)->addText(number_format($totals->storage->existing, 2), $this->textStyle, $this->tablePStyle);
            $this->table->addCell($this->cellSizeNumber, $this->firstStyle)->addText(number_format($totals->iops->existing, 0), $this->textStyle, $this->tablePStyle);
        }
        $this->table->addCell($this->cellSizeNumber, $this->firstStyle)->addText(number_format(round($totals->existing->physical_ram), 0), $this->textStyle, $this->tablePStyle);
        $this->table->addCell($this->cellSizeNumber2, $this->firstStyle)->addText(number_format(round($totals->existing->physical_rpm), 0), $this->textStyle, $this->tablePStyle);
        $this->table->addCell($this->cellSizeNumber, $this->firstStyle)->addText(number_format($totals->existing->vms), $this->textStyle, $this->tablePStyle);
        $this->table->addCell($this->cellSizeNumber, $this->firstStyle)->addText(floatval(number_format($totals->existing->vm_cores, 1)), $this->textStyle, $this->tablePStyle);
        $this->table->addCell($this->cellSizeNumber, $this->firstStyle)->addText(number_format(round($totals->existing->computedRam), 0), $this->textStyle, $this->tablePStyle);
        $this->table->addCell($this->cellSizeNumber2, $this->firstStyle)->addText(number_format(round($totals->existing->computedRpm), 0), $this->textStyle, $this->tablePStyle);
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
        if ($this->isCloud) {
            return $this->_drawCloudSummaryTarget();
        } else {
            return $this->_drawPhysicalSummaryTarget();
        }
    }

    /**
     * @return $this
     */
    protected function _drawCloudSummaryTarget()
    {
        $totals = &$this->environment->analysis->totals;
        $this->cell = $this->table->iwaddCell($this->cellSizeText2 + $this->cellSizeText, array('bgColor' => 'A6A6A6', 'borderSize' => 6, 'size' => 7, 'valign' => 'center'));
        $this->cell->addText('Target Environment Totals', $this->textStyle, $this->tablePStyle);
        $this->table->addCell($this->cellSizeNumber, $this->firstStyle)->addText(number_format($totals->target->servers), $this->textStyle, $this->tablePStyle);
        $this->cell = $this->table->addCell($this->cellSizeNumber2, $this->firstStyle);
        $this->cell->addText("", $this->textStyle, $this->tablePStyle);
        $this->table->addCell($this->cellSizeNumber, $this->firstStyle)->addText("" , $this->textStyle, $this->tablePStyle);
        $this->table->addCell($this->cellSizeNumber, $this->firstStyle)->addText("", $this->textStyle, $this->tablePStyle);
        $this->cell = $this->table->addCell($this->cellSizeNumber, $this->firstStyle);
        $this->cell->addText(number_format(round($totals->target->utilRam, 0)), $this->textStyle, $this->tablePStyle);
        $this->table->addCell($this->cellSizeNumber, $this->firstStyle)->addText(number_format($totals->target->vms), $this->textStyle, $this->tablePStyle);
        $this->table->addCell($this->cellSizeNumber, $this->firstStyle)->addText(floatval(number_format($totals->target->physical_cores, 1)), $this->textStyle, $this->tablePStyle);
        $this->cell = $this->table->addCell($this->cellSizeNumber, $this->firstStyle);
        $this->cell->addText(number_format(round(!$this->isCloud ? $totals->existing->comparisonComputedRam : $totals->target->utilRam, 0)), $this->textStyle, $this->tablePStyle);
        $this->cell = $this->table->addCell($this->cellSizeNumber2, $this->firstStyle);
        $this->cell->addText(number_format($totals->target->total_cores, 0), $this->textStyle, $this->tablePStyle);

        return $this;
    }

    /**
     * @return $this
     */
    protected function _drawPhysicalSummaryTarget()
    {
        $totals = &$this->environment->analysis->totals;
        $this->cell = $this->table->iwaddCell($this->cellSizeText2 + $this->cellSizeText, array('bgColor' => 'A6A6A6', 'borderSize' => 6, 'size' => 7, 'valign' => 'center'));
        $this->cell->addText('Target Environment Totals', $this->textStyle, $this->tablePStyle);
        $this->table->addCell($this->cellSizeNumber, $this->firstStyle)->addText(number_format($totals->target->servers), $this->textStyle, $this->tablePStyle);
        $this->table->addCell($this->cellSizeNumber, $this->firstStyle)->addText(!isset($totals->target->socket_qty) ? "" : number_format($totals->target->socket_qty), $this->textStyle, $this->tablePStyle);
        $this->table->addCell($this->cellSizeNumber, $this->firstStyle)->addText(number_format($totals->target->physical_cores), $this->textStyle, $this->tablePStyle);
        if ($this->isConverged) {
            $this->table->addCell($this->cellSizeText, $this->firstStyle)->addText(number_format($totals->storage->targetTotal, 2), $this->textStyle, $this->tablePStyle);
            $this->table->addCell($this->cellSizeNumber, $this->firstStyle)->addText(number_format($totals->iops->targetTotal, 0), $this->textStyle, $this->tablePStyle);
        }
        $this->cell = $this->table->addCell($this->cellSizeNumber, $this->firstStyle);
        $this->cell->addText(number_format(round($totals->target->utilRam, 0)), $this->textStyle, $this->tablePStyle);
        $this->cell = $this->table->addCell($this->cellSizeNumber, $this->firstStyle);
        $this->cell->addText(!isset($totals->target->utilRpm) ? "" : number_format(round($totals->target->utilRpm, 0)), $this->textStyle, $this->tablePStyle);
        $this->table->addCell($this->cellSizeNumber, $this->firstStyle)->addText(number_format($totals->target->vms), $this->textStyle, $this->tablePStyle);
        $this->table->addCell($this->cellSizeNumber, $this->firstStyle)->addText(floatval(number_format($totals->target->total_cores, 1)), $this->textStyle, $this->tablePStyle);
        $this->cell = $this->table->addCell($this->cellSizeNumber, $this->firstStyle);
        $this->cell->addText(number_format(round(!$this->isCloud ? $totals->existing->comparisonComputedRam : $totals->target->utilRam, 0)), $this->textStyle, $this->tablePStyle);
        $this->cell = $this->table->addCell($this->cellSizeNumber, $this->firstStyle);
        $this->cell->addText(!isset($totals->existing->comparisonComputedRpm) ? "" : number_format(round($totals->existing->comparisonComputedRpm, 0)), $this->textStyle, $this->tablePStyle);

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
        if (!$this->isConverged) {
            $widthAddition = ($this->cellSizeText) / ($this->numMappedColumns + 10);
        }
        Table::$__widthAddition = $widthAddition;

        if ($this->numMappedColumns > 1) {
            for ($i = $this->numMappedColumns; $i > 1; $i--) {
                Table::$__widthAddition -= $this->cellSizeText / ($this->numMappedColumns == 2 ? 12 : 20);
            }
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
        $targetCpu = function() use ($totals) {
            return ($totals->target->cpuUtilization ?? '100') . '%';
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

        $multiplier = $this->isConverged ? 2 : 1;
        if (!$this->isCloud) {
            $this->cell = $this->table->addCell($this->cellSizeText * 2, $this->headerStyle);
            $this->cell->getStyle()->setGridSpan(2);
            $this->cell->addText("Old VM Cores", $this->headerText, $this->tablePStyle);
            $this->cell = $this->table->addCell($this->cellSizeText * $multiplier, $this->headerStyle);
            $this->cell->getStyle()->setGridSpan($multiplier);
            $this->cell->addText("New VM Cores", $this->headerText, $this->tablePStyle);
        } else {
            $this->cell = $this->table->addCell($this->cellSizeText * 2, $this->headerStyle);
            $this->cell->getStyle()->setGridSpan(2);
            $this->cell->addText("VM Total Cores", $this->headerText, $this->tablePStyle);

            $this->cell = $this->table->addCell($this->cellSizeText * $multiplier, $this->headerStyle);
            $this->cell->getStyle()->setGridSpan($multiplier);
            $this->cell->addText("VM Total Ram", $this->headerText, $this->tablePStyle);
        }

        if (!$this->displayLoc || !$this->displayEnv || !$this->displayWork) {
            $this->table->addCell($this->cellSizeText, $this->headerStyle)->addText("", $this->headerText, $this->tablePStyle);
        }

        if ($this->viewCpm && !$this->isCloud) {
            $this->table->addCell($this->cellSizeText, $this->headerStyle)->addText("Old VM CPM @ " . $cpuMatch(), $this->headerText, $this->tablePStyle);
            $this->table->addCell($this->cellSizeText, $this->headerStyle)->addText("New VM CPM", $this->headerText, $this->tablePStyle);
        } else if ($this->isCloud) {
            $this->table->addCell($this->cellSizeText, $this->headerStyle)->addText("VM Cores @ 100%", $this->headerText, $this->tablePStyle);
            $this->table->addCell($this->cellSizeText, $this->headerStyle)->addText("VM Cores @ " . $cpuMatch(), $this->headerText, $this->tablePStyle);
        }

        if (!$this->isCloud && !$this->viewCpm) {
            $this->cell = $this->table->addCell($this->cellSizeText, $this->headerStyle);
            $this->cell->addText("VM Ram", $this->headerText, $this->tablePStyle);
            $this->table->addCell($this->cellSizeText, $this->headerStyle)->addText("", $this->headerText, $this->tablePStyle);
            $this->table->addCell($this->cellSizeText, $this->headerStyle)->addText("", $this->headerText, $this->tablePStyle);
        } else {
            $this->table->addCell($this->cellSizeText, $this->headerStyle)->addText("VM Ram", $this->headerText, $this->tablePStyle);
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

        $multiplier = 2;
        $this->cell = $this->table->addCell($this->cellSizeText * $multiplier, $this->style);
        $this->cell->getStyle()->setGridSpan($multiplier);
        $this->cell->addText(mixed_number($server->vm_cores ?: 0), $this->textStyle, $this->tablePStyle);

        $multiplier = $this->isConverged ? 2 : 1;
        if (!$this->isCloud) {
            $this->cell = $this->table->addCell($this->cellSizeText * $multiplier, $this->style);
            $this->cell->getStyle()->setGridSpan($multiplier);
            $this->cell->addText(mixed_number($server->comparison_server->vm_cores ?: 0, 0), $this->textStyle, $this->tablePStyle);
        } else {
            $this->cell = $this->table->addCell($this->cellSizeText * $multiplier, $this->style);
            $this->cell->getStyle()->setGridSpan($multiplier);
            $this->cell->addText($server->baseRam, $this->textStyle, $this->tablePStyle);
        }

        if (!$this->displayLoc || !$this->displayEnv || !$this->displayWork) {
            $this->table->addCell($this->cellSizeText, $this->style)->addText("", $this->textStyle, $this->tablePStyle);
        }

        if (!$this->isCloud && $this->viewCpm) {
            $this->table->addCell($this->cellSizeText, $this->style)->addText(number_format(round($server->computedRpm)), $this->textStyle, $this->tablePStyle);
            $this->table->addCell($this->cellSizeText, $this->style)->addText(number_format(round($server->comparison_server->computedRpm)), $this->textStyle, $this->tablePStyle);
        } else if ($this->isCloud) {
            $this->table->addCell($this->cellSizeText, $this->style)->addText(mixed_number(round($server->baseCores)), $this->textStyle, $this->tablePStyle);
            $this->table->addCell($this->cellSizeText, $this->style)->addText(mixed_number(round($server->computedCores)), $this->textStyle, $this->tablePStyle);
        }

        if (!$this->isCloud && !$this->viewCpm) {
            $this->cell = $this->table->addCell($this->cellSizeText, $this->style);
            $this->cell->addText(number_format(round($server->computedRam)), $this->textStyle, $this->tablePStyle);
            $this->table->addCell($this->cellSizeText, $this->style)->addText("", $this->textStyle, $this->tablePStyle);
            $this->table->addCell($this->cellSizeText, $this->style)->addText("", $this->textStyle, $this->tablePStyle);
        } else {
            $this->table->addCell($this->cellSizeText, $this->style)->addText(number_format(round($server->computedRam)), $this->textStyle, $this->tablePStyle);
        }

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

        $cols = $this->numMappedColumns + 3 + 2 + 1;

        if ($this->isConverged) {
            $cols++;
        }

        if ($this->numMappedColumns <= 2) {
            $cols++;
        }

        $this->cell = $this->table->addCell($this->cellSizeText * $cols, $this->style);

        $this->cell->getStyle()->setGridSpan($cols);
        $this->cell->addText('Sub-total', $this->textStyleBold, 'singleSpaceRight');

        if (!$this->isCloud && $this->viewCpm) {
            $this->table->addCell($this->cellSizeText, $this->style)->addText(number_format(round($consolidation->computedRpmTotal)), $this->textStyle, $this->tablePStyle);
            $this->table->addCell($this->cellSizeText, $this->style)->addText(number_format(round($consolidation->comparisonComputedRpmTotal)), $this->textStyle, $this->tablePStyle);
        } else if ($this->isCloud) {
            $this->table->addCell($this->cellSizeText, $this->style)->addText(mixed_number(round($consolidation->coreTotal)), $this->textStyle, $this->tablePStyle);
            $this->table->addCell($this->cellSizeText, $this->style)->addText(mixed_number(round($consolidation->computedCoreTotal)), $this->textStyle, $this->tablePStyle);
        } else {
            $this->table->addCell($this->cellSizeText, $this->style)->addText("", $this->textStyle, $this->tablePStyle);
            $this->table->addCell($this->cellSizeText, $this->style)->addText("", $this->textStyle, $this->tablePStyle);
        }

        $this->cell = $this->table->iwaddCell($this->cellSizeText, $this->style)->addText(number_format(round($consolidation->computedRamTotal)), $this->textStyle, $this->tablePStyle);

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
            $multiplier = min($this->numMappedColumns, 2);
            for ($i = 0; $i < $multiplier; $i++) {
                $this->cell = $this->table->addCell($this->cellSizeText, $this->styleTarget, $this->tablePStyle);
                $this->cell->addText($text, $this->textStyleBold, 'singleSpaceRight');
            }
        }

        /*for ($i = 0; $i < 11; $i++) {
            $this->cell = $this->table->addCell($this->cellSizeText, $this->headerStyle)->addText("", $this->headerText, $this->tablePStyle);
        }
        return $this;*/

        $this->cell = $this->table->addCell($this->cellSizeText, $this->headerStyle)->addText("Provider", $this->headerText, $this->tablePStyle);
        $this->cell = $this->table->addCell($this->cellSizeText, $this->headerStyle)->addText("Model", $this->headerText, $this->tablePStyle);
        $this->cell = $this->table->addCell($this->cellSizeText, $this->headerStyle)->addText("Instance Type", $this->headerText, $this->tablePStyle);
        $this->cell = $this->table->addCell($this->cellSizeText, $this->headerStyle)->addText("Ghz", $this->headerText, $this->tablePStyle);
        $this->cell = $this->table->addCell($this->cellSizeText, $this->headerStyle)->addText("Processors", $this->headerText, $this->tablePStyle);
        $this->cell = $this->table->addCell($this->cellSizeText, $this->headerStyle)->addText("Total Cores", $this->headerText, $this->tablePStyle);


        if ($this->isConverged) {
            $this->table->addCell($this->cellSizeText, $this->headerStyle)->addText("Useable Storage (TB)", $this->headerText, $this->tablePStyle);
            $this->table->addCell($this->cellSizeText, $this->headerStyle)->addText("IOPS", $this->headerText, $this->tablePStyle);
        } else {
            $this->table->addCell($this->cellSizeText, $this->headerStyle)->addText("", $this->headerText, $this->tablePStyle);
        }

        
        if (!$this->isCloud && $this->viewCpm) {
            $this->cell = $this->table->addCell($this->cellSizeText, $this->headerStyle);
            $this->cell->addText("CPM", $this->headerText, $this->tablePStyle);
            $this->cell = $this->table->addCell($this->cellSizeText, $this->headerStyle);
            $this->cell->addText("", $this->headerText, $this->tablePStyle);
        } else if ($this->isCloud) {
            $this->cell = $this->table->addCell($this->cellSizeText * 1, $this->headerStyle);
            $this->cell->addText("Cores", $this->headerText, $this->tablePStyle);
            $this->cell = $this->table->addCell($this->cellSizeText * 1, $this->headerStyle);
            $this->cell->addText("", $this->headerText, $this->tablePStyle);
        }

        if (!$this->isCloud && !$this->viewCpm) {
            $this->cell = $this->table->addCell($this->cellSizeText, $this->headerStyle);
            $this->cell->addText("RAM (GB)", $this->headerText, $this->tablePStyle);
            $this->table->addCell($this->cellSizeText, $this->headerStyle)->addText("", $this->headerText, $this->tablePStyle);
            $this->table->addCell($this->cellSizeText, $this->headerStyle)->addText("", $this->headerText, $this->tablePStyle);
        } else {
            $this->table->addCell($this->cellSizeText, $this->headerStyle)->addText("RAM (GB)", $this->headerText, $this->tablePStyle);
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
            $text = '';
            $multiplier = min($this->numMappedColumns, 2);
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

        /*for ($i = 0; $i < 11; $i++) {
            $this->table->addCell($this->cellSizeText, $this->styleTarget)->addText('', $this->textStyle, $this->tablePStyle);
        }
        return $this;*/
        $this->table->addCell($this->cellSizeText, $this->styleTarget)->addText($manufacturer, $this->textStyle, $this->tablePStyle);
        $this->table->addCell($this->cellSizeText, $this->styleTarget)->addText(!isset($target->server) ? "" : $target->server->name, $this->textStyle, $this->tablePStyle);
        $this->table->addCell($this->cellSizeText, $this->styleTarget)->addText($target->processor->name, $this->textStyle, $this->tablePStyle);
        $this->table->addCell($this->cellSizeText, $this->styleTarget)->addText(!isset($target->processor->ghz) ? "" : $target->processor->ghz, $this->textStyle, $this->tablePStyle);
        $this->table->addCell($this->cellSizeText, $this->styleTarget)->addText(!isset($target->processor->socket_qty) ? "" : number_format($target->processor->socket_qty), $this->textStyle, $this->tablePStyle);
        $this->table->addCell($this->cellSizeText, $this->styleTarget)->addText(number_format($target->processor->total_cores), $this->textStyle, $this->tablePStyle);

        if ($this->isConverged) {
            $this->table->addCell($this->cellSizeText, $this->styleTarget)->addText(number_format($target->useable_storage, 2), $this->textStyle, $this->tablePStyle);
            $this->table->addCell($this->cellSizeText, $this->styleTarget)->addText(number_format($target->iops, 0), $this->textStyle, $this->tablePStyle);
        } else {
            $this->table->addCell($this->cellSizeText, $this->styleTarget)->addText("", $this->textStyle, $this->tablePStyle);
        }

        if (!$this->isCloud && $this->viewCpm) {
            $this->cell = $this->table->addCell($this->cellSizeText, $this->styleTarget);
            $this->cell->addText(!isset($target->utilRpm) ? "" : number_format(round($target->utilRpm)), $this->textStyle, $this->tablePStyle);
            $this->cell = $this->table->addCell($this->cellSizeText, $this->styleTarget);
            $this->cell->addText("", $this->textStyle, $this->tablePStyle);
        } else if ($this->isCloud) {
            $this->cell = $this->table->addCell($this->cellSizeText, $this->styleTarget);
            $this->cell->addText(number_format($target->processor->total_cores), $this->textStyle, $this->tablePStyle);
            $this->cell = $this->table->addCell($this->cellSizeText, $this->styleTarget);
            $this->cell->addText(number_format($target->processor->total_cores), $this->textStyle, $this->tablePStyle);
        }

        if (!$this->isCloud && !$this->viewCpm) {
            $this->cell = $this->table->addCell($this->cellSizeText, $this->styleTarget);
            $this->cell->addText(number_format(round($target->utilRam)), $this->textStyle, $this->tablePStyle);
            $this->table->addCell($this->cellSizeText, $this->style)->addText("", $this->textStyle, $this->tablePStyle);
            $this->table->addCell($this->cellSizeText, $this->style)->addText("", $this->textStyle, $this->tablePStyle);
        } else {
            $this->cell = $this->table->addCell($this->cellSizeNumber, $this->styleTarget);
            $this->cell->addText(number_format(round($target->utilRam)), $this->textStyle, $this->tablePStyle);
        }

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