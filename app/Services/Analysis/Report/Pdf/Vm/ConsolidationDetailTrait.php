<?php
/**
 *
 */

namespace App\Services\Analysis\Report\Pdf\Vm;
use App\Models\Hardware\AzureAds;
use App\Models\Project\Environment;
use App\Models\Project\PrecisionPDF;

/**
 * Trait ConsolidationDetailTrait
 * @package App\Services\Analysis\Report\Pdf
 * @property $viewCmp
 * @property PrecisionPDF $pdf
 * @property Environment $environment
 * @property Environment $existingEnvironment
 * @property $header, $displayWork, $displayLoc, $displayEnv, $noDetails, $numMappedColumns, $cellSizeNumber, $cellSizeNumber2, $cellSizeText, $cellSizeText2, $headerHeight1, $headerHeight2, $num_headers, $cellHeight, $pageHeight, $bg1, $bg2, $bg1Target, $bg2Target;
 */
trait ConsolidationDetailTrait
{
    /**
     * @return $this
     */
    protected function _drawConsolidationsDetail()
    {
        $this->_drawConsolidationDetailHeader();

        foreach ($this->environment->analysis->consolidations as $consIndex => $consolidation) {
            // Set the current index
            $this->consIndex = $consIndex;
            $this->consolidation = $consolidation;

            $this->_drawConsolidationDetail();
        }

        return $this;
    }

    /**
     * @return $this
     */
    protected function _drawConsolidationDetail()
    {
        $this->_setExistingWidthAddition();
        if ($this->flip) {
            $color = $this->bg2;
            $this->colorTarget = $this->bg2Target;
        } else {
            $color = $this->bg1;
            $this->colorTarget = $this->bg1Target;
        }

        $this->pdf->fillColor($color);
        foreach ($this->consolidation->servers as $server) {
            $this->_drawConsolidationDetailExisting($server);
        }

        $this->_drawConsolidationDetailSubtotal()
            ->_drawConsolidationDetailTargetHeader();


        foreach ($this->consolidation->targets as $index => $target) {
            $this->_drawConsolidationDetailTarget($index, $target, $color);
        }
        $this->flip = !$this->flip;

        return $this;
    }

    /**
     * @return $this
     */
    protected function _setExistingWidthAddition()
    {
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

        $additionColumns += 6;
        $widthAddition = ($this->cellSizeText) / $additionColumns;
        $this->pdf->__widthAddition = $widthAddition;

        return $this;
    }

    /**
     * @return $this
     */
    protected function _setTargetWidthAddition()
    {
        $widthAddition = 0;
        $widthAddition = ($this->cellSizeText / 6);
        $this->pdf->__widthAddition = $widthAddition;

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

        $this->pdf->SetFont('', 'B');
        $this->pdf->fillColor(array(166, 166, 166));
        $multiplier = $this->numMappedColumns <= 1 ? 2 : 1;
        $missingOne = !$this->displayLoc || !$this->displayEnv || !$this->displayWork;
        if (!$missingOne) {
            $multiplier = 2/3;
        }
        if ($this->displayLoc) {
            $this->pdf->MultiCell($this->cellSizeText * $multiplier, $this->headerHeight1, $this->header[0], 1, 'L', 1, 0, '', '', true, 0, false, true, $this->headerHeight1, 'T');
        }
        if ($this->displayEnv) {
            $this->pdf->MultiCell($this->cellSizeText * $multiplier, $this->headerHeight1, $this->header[1], 1, 'L', 1, 0, '', '', true, 0, false, true, $this->headerHeight1, 'T');
        }
        if ($this->displayWork) {
            $this->pdf->MultiCell($this->cellSizeText * $multiplier, $this->headerHeight1, $this->header[2], 1, 'L', 1, 0, '', '', true, 0, false, true, $this->headerHeight1, 'T');
        }
        if ($this->noDetails) {
            $this->pdf->MultiCell($this->cellSizeText  * $multiplier, $this->headerHeight1, "", 1, 'L', 1, 0, '', '', true, 0, false, true, $this->headerHeight1, 'T');
        }

        $this->pdf->MultiCell($this->cellSizeText * 3, $this->headerHeight1, "VM ID", 1, 'L', 1, 0, '', '', true, 0, false, true, $this->headerHeight1, 'T');

        $this->pdf->MultiCell($this->cellSizeText, $this->headerHeight1, "VM Total Cores", 1, 'L', 1, 0, '', '', true, 0, false, true, $this->headerHeight1, 'T');

        $this->pdf->MultiCell($this->cellSizeText, $this->headerHeight1, "VM Ram @ 100%", 1, 'L', 1, 0, '', '', true, 0, false, true, $this->headerHeight1, 'T');
        $this->pdf->MultiCell($this->cellSizeText, $this->headerHeight1, "VM Ram @ " . $ramMatch(), 1, 'L', 1, 0, '', '', true, 0, false, true, $this->headerHeight1, 'T');

        $this->pdf->MultiCell($this->cellSizeText, $this->headerHeight1, "VM Cores @ 100%", 1, 'L', 1, 0, '', '', true, 0, false, true, $this->headerHeight1, 'T');
        $this->pdf->MultiCell($this->cellSizeText, $this->headerHeight1, "VM Cores @ " . $cpuMatch(), 1, 'L', 1, 0, '', '', true, 0, false, true, $this->headerHeight1, 'T');

        $this->pdf->SetFont('', '');
        $this->pdf->Ln();
        // Color and font restoration
        $this->pdf->SetTextColor(0);
        $this->pdf->SetFont('');

        return $this;
    }

    /**
     * @param $server
     * @return $this
     */
    protected function _drawConsolidationDetailExisting($server)
    {
        $this->startX = $this->pdf->GetX();
        $this->startY = $this->pdf->GetY();
        $this->height = 0;
        $this->tempHeight = 0;
        $maxRows = 1;
        if ($this->displayLoc) {
            $rows = $this->pdf->getNumLines($server->location, $this->cellSizeText);
            $maxRows = $rows > $maxRows ? $rows : $maxRows;
        }
        if ($this->displayEnv) {
            $rows = $this->pdf->getNumLines($server->environment_detail, $this->cellSizeText);
            $maxRows = $rows > $maxRows ? $rows : $maxRows;
        }
        if ($this->displayWork) {
            $rows = $this->pdf->getNumLines($server->workload_type, $this->cellSizeText);
            $maxRows = $rows > $maxRows ? $rows : $maxRows;
        }
        if ($this->noDetails) {
            $rows = $this->pdf->getNumLines("", $this->cellSizeText);
            $maxRows = $rows > $maxRows ? $rows : $maxRows;
        }
        $rows = $this->pdf->getNumLines($server->vm_id ?: "", $this->cellSizeText * 3);
        $maxRows = $rows > $maxRows ? $rows : $maxRows;

        $calculatedCellHeight = $maxRows * $this->cellHeight + 1;
        $multiplier = $this->numMappedColumns <= 1 ? 2 : 1;
        $missingOne = !$this->displayLoc || !$this->displayEnv || !$this->displayWork;
        if (!$missingOne) {
            $multiplier = 2/3;
        }
        if ($this->displayLoc) {
            $this->tempHeight = $this->pdf->MultiCell($this->cellSizeText * $multiplier, $calculatedCellHeight, $server->location, 1, 'L', 1, 0, '', '', true, 0, false, true, $calculatedCellHeight, 'M');
        }
        if ($this->displayEnv) {
            $this->tempHeight = $this->pdf->MultiCell($this->cellSizeText * $multiplier, $calculatedCellHeight, $server->environment_detail, 1, 'L', 1, 0, '', '', true, 0, false, true, $calculatedCellHeight, 'M');
        }
        if ($this->displayWork) {
            $this->tempHeight = $this->pdf->MultiCell($this->cellSizeText * $multiplier, $calculatedCellHeight, $server->workload_type, 1, 'L', 1, 0, '', '', true, 0, false, true, $calculatedCellHeight, 'M');
        }
        if ($this->noDetails) {
            $this->tempHeight = $this->pdf->MultiCell($this->cellSizeText * $multiplier, $calculatedCellHeight, "", 1, 'L', 1, 0, '', '', true, 0, false, true, $calculatedCellHeight, 'M');
        }
        $this->tempHeight = $this->pdf->MultiCell($this->cellSizeText * 3, $calculatedCellHeight, $server->vm_id ?: "", 1, 'L', 1, 0, '', '', true, 0, false, true, $calculatedCellHeight, 'M');
        $this->tempHeight = $this->pdf->MultiCell($this->cellSizeText, $calculatedCellHeight, mixed_number($server->vm_cores ?: 0,0), 1, 'L', 1, 0, '', '', true, 0, false, true, $calculatedCellHeight, 'M');

        $this->pdf->MultiCell($this->cellSizeText, $calculatedCellHeight, number_format(round($server->baseRam)), 1, 'L', 1, 0, '', '', true, 0, false, true, $calculatedCellHeight, 'M');
        $this->pdf->MultiCell($this->cellSizeText, $calculatedCellHeight, number_format(round($server->computedRam)), 1, 'L', 1, 0, '', '', true, 0, false, true, $calculatedCellHeight, 'M');

        $this->pdf->MultiCell($this->cellSizeText, $calculatedCellHeight, $this->viewCpm ? mixed_number(round($server->baseCores)) : '', 1, 'L', 1, 0, '', '', true, 0, false, true, $calculatedCellHeight, 'M');
        $this->pdf->MultiCell($this->cellSizeText, $calculatedCellHeight, $this->viewCpm ? number_format(round($server->computedCores)) : '', 1, 'L', 1, 0, '', '', true, 0, false, true, $calculatedCellHeight, 'M');

        $this->pdf->Ln();
        if ($this->pdf->GetY() > $this->pageHeight) {
            $this->pdf->AddPage();
        }

        return $this;
    }

    /**
     * @return $this
     */
    protected function _drawConsolidationDetailSubtotal()
    {
        $this->pdf->SetFont('', 'B');
        $multiplier = 0;
        if ($this->displayLoc) {
            $multiplier ++;
        }
        if ($this->displayEnv) {
            $multiplier++;
        }
        if ($this->displayWork) {
            $multiplier++;
        }
        $multiplier = $multiplier ?: 1;
        $multiplier += 2;
        $this->pdf->iwMultiCell(($this->pdf->__widthAddition * $multiplier) + $this->cellSizeText * (4 + 2), $this->cellHeight, 'Sub-total', 1, 'R', 1, 0, '', '', true, 0, false, true, 0, 'M');
        $this->pdf->SetFont('');
        $this->pdf->MultiCell($this->cellSizeText, $this->cellHeight, number_format(round($this->consolidation->ramTotal)), 1, 'L', 1, 0, '', '', true, 0, false, true, 0, 'M');
        $this->pdf->MultiCell($this->cellSizeText, $this->cellHeight, number_format(round($this->consolidation->computedRamTotal)), 1, 'L', 1, 0, '', '', true, 0, false, true, 0, 'M');
        $this->pdf->MultiCell($this->cellSizeText, $this->cellHeight, mixed_number(round($this->consolidation->coreTotal)), 1, 'L', 1, 0, '', '', true, 0, false, true, 0, 'M');
        $this->pdf->MultiCell($this->cellSizeText, $this->cellHeight, mixed_number(round($this->consolidation->computedCoreTotal)), 1, 'L', 1, 0, '', '', true, 0, false, true, 0, 'M');
        $this->pdf->Ln();
        if ($this->pdf->GetY() > $this->pageHeight) {
            $this->pdf->AddPage();
        }
        $this->pdf->fillColor($this->colorTarget);

        return $this;
    }

    /**
     * @return $this
     */
    protected function _drawConsolidationDetailTargetHeader()
    {
//        $this->_setTargetWidthAddition();
        if ($this->numMappedColumns > 0) {
            $multiplier = 0;
            if ($this->displayLoc) {
                $multiplier ++;
            }
            if ($this->displayEnv) {
                $multiplier++;
            }
            if ($this->displayWork) {
                $multiplier++;
            }
            $multiplier = $multiplier ?: 1;
            $text = '';
            $this->tempHeight = $this->pdf->iwMultiCell(($this->pdf->__widthAddition * $multiplier) + $this->cellSizeText * 2, $this->headerHeight2, $text, 1, 'R', 1, 0, '', '', true, 0, false, true, 0, 'M');
            $this->height = $this->tempHeight > $this->height ? $this->tempHeight : $this->height;
            $this->pdf->SetFont('');
        }
        $this->pdf->SetFont('', 'B');
        $this->pdf->fillColor(array(166, 166, 166));
        $this->pdf->iwMultiCell((($this->pdf->__widthAddition) / 2) + $this->cellSizeText * 1.5, $this->headerHeight2, "Manufacturer", 1, 'L', 1, 0, '', '', true, 0, false, true, $this->headerHeight2, 'T');
        $this->pdf->iwMultiCell((($this->pdf->__widthAddition) / 2) + $this->cellSizeText * 1.5, $this->headerHeight2, "Processor Type", 1, 'L', 1, 0, '', '', true, 0, false, true, $this->headerHeight2, 'T');
        $this->pdf->MultiCell($this->cellSizeText, $this->headerHeight2, "Total Cores", 1, 'L', 1, 0, '', '', true, 0, false, true, $this->headerHeight2, 'T');

        $this->pdf->iwMultiCell(($this->pdf->__widthAddition * 2) + $this->cellSizeText * 2, $this->headerHeight2, 'RAM (GB)', 1, 'L', 1, 0, '', '', true, 0, false, true, $this->headerHeight2, 'T');

        $this->pdf->iwMultiCell(($this->pdf->__widthAddition * 2) + $this->cellSizeText * 2, $this->headerHeight2, 'Cores', 1, 'L', 1, 0, '', '', true, 0, false, true, $this->headerHeight2, 'T');

        $this->pdf->Ln();
        $this->pdf->SetFont('', '');
        $this->pdf->fillColor($this->colorTarget);
        if ($this->pdf->GetY() > $this->pageHeight) {
            $this->pdf->AddPage();
        }

        return $this;
    }

    /**
     * @param $index
     * @param $target
     * @param $color
     * @return $this
     */
    protected function _drawConsolidationDetailTarget($index, $target, $color)
    {
        $this->startX = $this->pdf->GetX();
        $this->startY = $this->pdf->GetY();
        $maxRows = 1;
        $text = $index == 0 ? 'Consolidation Target' : '';
        if ($this->numMappedColumns > 0) {
            $this->pdf->SetFont('', 'B');
            $rows = $this->pdf->getNumLines($text, $this->cellSizeText * $this->numMappedColumns);
            $maxRows = $rows > $maxRows ? $rows : $maxRows;
            $this->pdf->SetFont('');
        }
        if (!isset($target->instance_type)) {
            $manufacturer = $target->manufacturer->name;
        } else {
            if ($target->instance_type == "Azure" || $target->instance_type === AzureAds::INSTANCE_TYPE_ADS) $manufacturer = "Microsoft";
            else if ($target->instance_type == "Google") $manufacturer = "Google";
            else if ($target->instance_type == "IBMPVS") $manufacturer = "IBMPVS";
            else $manufacturer = "AWS";
        }
        $rows = $this->pdf->getNumLines($manufacturer, $this->cellSizeText);
        $maxRows = $rows > $maxRows ? $rows : $maxRows;
        $proc =$target->processor->name;
        if ($target->instance_type === AzureAds::INSTANCE_TYPE_ADS) {
            $proc = $target->server->name . " / " . $proc;
        }
        $rows = $this->pdf->getNumLines($proc, $this->cellSizeText2);
        $maxRows = $rows > $maxRows ? $rows : $maxRows;

        $calculatedCellHeight = $maxRows * $this->cellHeight + 1;

        if ($this->numMappedColumns > 0) {
            $multiplier = 0;
            if ($this->displayLoc) {
                $multiplier ++;
            }
            if ($this->displayEnv) {
                $multiplier++;
            }
            if ($this->displayWork) {
                $multiplier++;
            }
            $multiplier = $multiplier ?: 1;
            $this->pdf->SetFont('', 'B');
            $this->tempHeight = $this->pdf->iwMultiCell(($this->pdf->__widthAddition * $multiplier) + $this->cellSizeText * 2, $calculatedCellHeight, $text, 1, 'R', 1, 0, '', '', true, 0, false, true, $calculatedCellHeight, 'M');
            $this->pdf->SetFont('');
        }
        $this->tempHeight = $this->pdf->iwMultiCell((($this->pdf->__widthAddition) / 2) + $this->cellSizeText * 1.5, $calculatedCellHeight, $manufacturer, 1, 'L', 1, 0, '', '', true, 0, false, true, $calculatedCellHeight, 'M');
        $this->tempHeight = $this->pdf->iwMultiCell((($this->pdf->__widthAddition) / 2) + $this->cellSizeText * 1.5, $calculatedCellHeight, $proc, 1, 'L', 1, 0, '', '', true, 0, false, true, $calculatedCellHeight, 'M');
        $this->pdf->MultiCell($this->cellSizeText, $calculatedCellHeight, number_format($target->processor->total_cores), 1, 'L', 1, 0, '', '', true, 0, false, true, $calculatedCellHeight, 'M');

        $this->pdf->iwMultiCell(($this->pdf->__widthAddition * 2) + $this->cellSizeText * 2, $calculatedCellHeight, number_format(round($target->utilRam)), 1, 'L', 1, 0, '', '', true, 0, false, true, $calculatedCellHeight, 'M');

        $this->pdf->iwMultiCell(($this->pdf->__widthAddition * 2) + $this->cellSizeText * 2, $calculatedCellHeight, number_format($target->processor->total_cores), 1, 'L', 1, 0, '', '', true, 0, false, true, $calculatedCellHeight, 'M');

        $this->pdf->Ln();
        //Add blank line
        $this->pdf->fillColor($color);

        $fillerWidth = $this->cellSizeText * (max($this->numMappedColumns - 1, 2) + 3);
        $fillerWidth += ($this->cellSizeText * .75) + (3 * (($this->cellSizeText * 2) / 3)) + ($this->cellSizeText * 1.25);

        if ($this->environment->isCloud() || $this->viewCpm) {
            $fillerWidth += $this->cellSizeText * 2;
        }

        if (count($this->environment->analysis->consolidations) != ($this->consIndex + 1) &&
            count($this->consolidation->targets) == ($index + 1)) {

            $this->pdf->iwMultiCell($fillerWidth + ((!$this->environment->isCloud() &&! $this->viewCpm) ? $this->cellSizeText * 2 : 0), $this->cellHeight, '', 1, 'L', 1, 0, '', '', true, 0, false, true, $this->cellHeight, 'M');

            $this->pdf->Ln();
        }
        //Add an extra check to prevent adding a page after the last target of the last consolidation
        if ($this->pdf->GetY() > $this->pageHeight &&
            !(count($this->environment->analysis->consolidations) == ($this->consIndex + 1) &&
                count($this->consolidation->targets) == ($index + 1))) {
            $this->pdf->AddPage();
        }

        return $this;
    }
}
