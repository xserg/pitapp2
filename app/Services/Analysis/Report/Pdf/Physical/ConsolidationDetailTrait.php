<?php
/**
 *
 */

namespace App\Services\Analysis\Report\Pdf\Physical;
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
    protected function _drawConsolidationDetailHeader()
    {
        $totals = &$this->environment->analysis->totals;
        $this->pdf->SetFont('', 'B');
        $this->pdf->fillColor(array(166, 166, 166));
        for ($i = 0; $i < $this->num_headers; ++$i) {
            switch ($i) {
                case 0:
                    if ($this->displayLoc)
                        $this->pdf->MultiCell($this->cellSizeText, $this->headerHeight1, $this->header[$i], 1, 'L', 1, 0, '', '', true, 0, false, true, $this->headerHeight1, 'T');
                    break;
                case 1:
                    if ($this->displayEnv)
                        $this->pdf->MultiCell($this->cellSizeText, $this->headerHeight1, $this->header[$i], 1, 'L', 1, 0, '', '', true, 0, false, true, $this->headerHeight1, 'T');
                    break;
                case 2:
                    if ($this->displayWork)
                        $this->pdf->MultiCell($this->cellSizeText, $this->headerHeight1, $this->header[$i], 1, 'L', 1, 0, '', '', true, 0, false, true, $this->headerHeight1, 'T');
                    if ($this->noDetails)
                        $this->pdf->MultiCell($this->cellSizeText, $this->headerHeight1, "", 1, 'L', 1, 0, '', '', true, 0, false, true, $this->headerHeight1, 'T');
                    break;
                case 4:
                    $this->pdf->MultiCell($this->cellSizeNumber, $this->headerHeight1, $this->header[$i], 1, 'L', 1, 0, '', '', true, 0, false, true, $this->headerHeight1, 'T');
                    break;
                case 5:
                    $this->pdf->MultiCell($this->cellSizeText2, $this->headerHeight1, $this->header[$i], 1, 'L', 1, 0, '', '', true, 0, false, true, $this->headerHeight1, 'T');
                    break;
                case 6:
                case 7:
                case 8:
                    $this->pdf->MultiCell($this->cellSizeNumber, $this->headerHeight1, $this->header[$i], 1, 'L', 1, 0, '', '', true, 0, false, true, $this->headerHeight1, 'T');
                    break;
                case 9:
                    if ($this->environment->isConverged()) {
                        $this->pdf->MultiCell($this->cellSizeText, $this->headerHeight1, "Useable Storage (TB)", 1, 'L', 1, 0, '', '', true, 0, false, true, $this->headerHeight1, 'T');
                        $this->pdf->MultiCell($this->cellSizeNumber, $this->headerHeight1, "IOPs", 1, 'L', 1, 0, '', '', true, 0, false, true, $this->headerHeight1, 'T');
                    }
                    $this->pdf->MultiCell($this->cellSizeNumber + (!$this->viewCpm * $this->cellSizeNumber2), $this->headerHeight1, $this->header[$i], 1, 'L', 1, 0, '', '', true, 0, false, true, $this->headerHeight1, 'T');
                    break;
                case 10:
                    $this->pdf->MultiCell($this->cellSizeNumber + (!$this->viewCpm * $this->cellSizeNumber2), $this->headerHeight1, $totals->existing->ramMatch ? $this->header[$i] : "RAM @ Util", 1, 'L', 1, 0, '', '', true, 0, false, true, $this->headerHeight1, 'T');
                    break;
                case 11:
                    if ($this->environment->isCloud())
                        $this->pdf->MultiCell($this->cellSizeNumber2, $this->headerHeight1, "Cores @ 100%", 1, 'L', 1, 0, '', '', true, 0, false, true, $this->headerHeight1, 'T');
                    else {
                        if ($this->viewCpm)
                            $this->pdf->MultiCell($this->cellSizeNumber2, $this->headerHeight1, $this->header[$i], 1, 'L', 1, 0, '', '', true, 0, false, true, $this->headerHeight1, 'T');
                    }
                    break;
                case 12:
                    if ($this->environment->isCloud())
                        $this->pdf->MultiCell($this->cellSizeNumber2, $this->headerHeight1, "Cores @ " . $this->existingEnvironment->cpu_utilization . "%", 1, 'L', 1, 0, '', '', true, 0, false, true, $this->headerHeight1, 'T');
                    else {
                        if ($this->viewCpm)
                            $this->pdf->MultiCell($this->cellSizeNumber2, $this->headerHeight1, $totals->existing->cpuMatch ? $this->header[$i] : "CPM @ Util", 1, 'L', 1, 0, '', '', true, 0, false, true, $this->headerHeight1, 'T');
                    }
                    break;
                default:
                    $this->pdf->MultiCell($this->cellSizeText, $this->headerHeight1, $this->header[$i], 1, 'L', 1, 0, '', '', true, 0, false, true, $this->headerHeight1, 'T');
            }
        }
        $this->pdf->SetFont('', '');
        $this->pdf->Ln();
        // Color and font restoration
        $this->pdf->SetTextColor(0);
        $this->pdf->SetFont('');

        return $this;
    }

    /**
     * @return $this
     */
    protected function _drawConsolidationDetail()
    {
        if ($this->flip) {
            $color = $this->bg2;
            $this->colorTarget = $this->bg2Target;
        } else {
            $color = $this->bg1;
            $this->colorTarget = $this->bg1Target;
        }

        $this->pdf->fillColor($color);
        foreach ($this->consolidation->servers as $server) {

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
            $rows = $this->pdf->getNumLines($server->manufacturer ? $server->manufacturer->name : "", $this->cellSizeText);
            $maxRows = $rows > $maxRows ? $rows : $maxRows;
            $rows = $this->pdf->getNumLines($server->server ? $server->server->name : "", $this->cellSizeText2);
            $maxRows = $rows > $maxRows ? $rows : $maxRows;
            $rows = $this->pdf->getNumLines($server->processor->name, $this->cellSizeText2);
            $maxRows = $rows > $maxRows ? $rows : $maxRows;
            $rows = $this->pdf->getNumLines($server->processor->ghz, $this->cellSizeText2);
            $maxRows = $rows > $maxRows ? $rows : $maxRows;

            $calculatedCellHeight = $maxRows * $this->cellHeight + 1;
            if ($this->displayLoc) {
                $this->tempHeight = $this->pdf->MultiCell($this->cellSizeText, $calculatedCellHeight, $server->location, 1, 'L', 1, 0, '', '', true, 0, false, true, $calculatedCellHeight, 'M');
            }
            if ($this->displayEnv) {
                $this->tempHeight = $this->pdf->MultiCell($this->cellSizeText, $calculatedCellHeight, $server->environment_detail, 1, 'L', 1, 0, '', '', true, 0, false, true, $calculatedCellHeight, 'M');
            }
            if ($this->displayWork) {
                $this->tempHeight = $this->pdf->MultiCell($this->cellSizeText, $calculatedCellHeight, $server->workload_type, 1, 'L', 1, 0, '', '', true, 0, false, true, $calculatedCellHeight, 'M');
            }
            if ($this->noDetails) {
                $this->tempHeight = $this->pdf->MultiCell($this->cellSizeText, $calculatedCellHeight, "", 1, 'L', 1, 0, '', '', true, 0, false, true, $calculatedCellHeight, 'M');
            }
            $this->tempHeight = $this->pdf->MultiCell($this->cellSizeText, $calculatedCellHeight, $server->manufacturer ? $server->manufacturer->name : "", 1, 'L', 1, 0, '', '', true, 0, false, true, $calculatedCellHeight, 'M');
            $this->tempHeight = $this->pdf->MultiCell($this->cellSizeNumber, $calculatedCellHeight, $server->server ? $server->server->name : '', 1, 'L', 1, 0, '', '', true, 0, false, true, $calculatedCellHeight, 'M');
            $this->tempHeight = $this->pdf->MultiCell($this->cellSizeText2, $calculatedCellHeight, $server->processor->name, 1, 'L', 1, 0, '', '', true, 0, false, true, $calculatedCellHeight, 'M');
            $this->tempHeight = $this->pdf->MultiCell($this->cellSizeNumber, $calculatedCellHeight, $server->processor->ghz, 1, 'L', 1, 0, '', '', true, 0, false, true, $calculatedCellHeight, 'M');
            $this->pdf->MultiCell($this->cellSizeNumber, $this->cellHeight, number_format($server->processor->socket_qty), 1, 'L', 1, 0, '', '', true, 0, false, true, $calculatedCellHeight, 'M');
            $this->pdf->MultiCell($this->cellSizeNumber, $this->cellHeight, number_format($server->processor->total_cores), 1, 'L', 1, 0, '', '', true, 0, false, true, $calculatedCellHeight, 'M');
            if ($this->environment->isConverged()) {
                $this->pdf->MultiCell($this->cellSizeText, $this->cellHeight, "", 1, 'L', 1, 0, '', '', true, 0, false, true, $calculatedCellHeight, 'M');
                $this->pdf->MultiCell($this->cellSizeNumber, $this->cellHeight, "", 1, 'L', 1, 0, '', '', true, 0, false, true, $calculatedCellHeight, 'M');
            }
            $this->pdf->MultiCell($this->cellSizeNumber + (!$this->viewCpm * $this->cellSizeNumber2), $this->cellHeight, number_format(round($server->baseRam)), 1, 'L', 1, 0, '', '', true, 0, false, true, $calculatedCellHeight, 'M');
            $this->pdf->MultiCell($this->cellSizeNumber + (!$this->viewCpm * $this->cellSizeNumber2), $this->cellHeight, number_format(round($server->computedRam)), 1, 'L', 1, 0, '', '', true, 0, false, true, $calculatedCellHeight, 'M');
            if (!$this->environment->isCloud()) {
                if ($this->viewCpm) {
                    $this->pdf->MultiCell($this->cellSizeNumber2, $this->cellHeight, $this->viewCpm ? number_format(round($server->baseRpm)) : '', 1, 'L', 1, 0, '', '', true, 0, false, true, $calculatedCellHeight, 'M');
                    $this->pdf->MultiCell($this->cellSizeNumber2, $this->cellHeight, $this->viewCpm ? number_format(round($server->computedRpm)) : '', 1, 'L', 1, 0, '', '', true, 0, false, true, $calculatedCellHeight, 'M');
                }
            } else {
                $this->pdf->MultiCell($this->cellSizeNumber2, $this->cellHeight, number_format(round($server->baseCores)), 1, 'L', 1, 0, '', '', true, 0, false, true, $calculatedCellHeight, 'M');
                $this->pdf->MultiCell($this->cellSizeNumber2, $this->cellHeight, number_format(round($server->computedCores)), 1, 'L', 1, 0, '', '', true, 0, false, true, $calculatedCellHeight, 'M');
            }
            $this->pdf->Ln();
            if ($this->pdf->GetY() > $this->pageHeight) {
                $this->pdf->AddPage();
            }
        }
        $this->pdf->SetFont('', 'B');
        $this->pdf->MultiCell($this->cellSizeText2 + $this->cellSizeText * (1 + $this->numMappedColumns + ($this->environment->isConverged() ? 1 : 0)) + $this->cellSizeNumber * (4 + ($this->environment->isConverged() ? 1 : 0)), $this->cellHeight, 'Sub-total', 1, 'R', 1, 0, '', '', true, 0, false, true, 0, 'M');
        $this->pdf->SetFont('');
        $this->pdf->MultiCell($this->cellSizeNumber + (!$this->viewCpm * $this->cellSizeNumber2), $this->cellHeight, number_format(round($this->consolidation->ramTotal)), 1, 'L', 1, 0, '', '', true, 0, false, true, 0, 'M');
        $this->pdf->MultiCell($this->cellSizeNumber + (!$this->viewCpm * $this->cellSizeNumber2), $this->cellHeight, number_format(round($this->consolidation->computedRamTotal)), 1, 'L', 1, 0, '', '', true, 0, false, true, 0, 'M');
        if (!$this->environment->isCloud()) {
            if ($this->viewCpm) {
                $this->pdf->MultiCell($this->cellSizeNumber2, $this->cellHeight, $this->viewCpm ? number_format(round($this->consolidation->rpmTotal)) : '', 1, 'L', 1, 0, '', '', true, 0, false, true, 0, 'M');
                $this->pdf->MultiCell($this->cellSizeNumber2, $this->cellHeight, $this->viewCpm ? number_format(round($this->consolidation->computedRpmTotal)) : '', 1, 'L', 1, 0, '', '', true, 0, false, true, 0, 'M');
            }
        } else {
            $this->pdf->MultiCell($this->cellSizeNumber2, $this->cellHeight, number_format(round($this->consolidation->coreTotal)), 1, 'L', 1, 0, '', '', true, 0, false, true, 0, 'M');
            $this->pdf->MultiCell($this->cellSizeNumber2, $this->cellHeight, number_format(round($this->consolidation->computedCoreTotal)), 1, 'L', 1, 0, '', '', true, 0, false, true, 0, 'M');
        }
        $this->pdf->Ln();
        if ($this->pdf->GetY() > $this->pageHeight) {
            $this->pdf->AddPage();
        }
        $this->pdf->fillColor($this->colorTarget);
        if ($this->numMappedColumns > 0) {
            $text = '';
            $this->tempHeight = $this->pdf->MultiCell($this->cellSizeText * $this->numMappedColumns, $this->headerHeight2, $text, 1, 'R', 1, 0, '', '', true, 0, false, true, 0, 'M');
            $this->height = $this->tempHeight > $this->height ? $this->tempHeight : $this->height;
            $this->pdf->SetFont('');
        }
        $this->pdf->SetFont('', 'B');
        $this->pdf->fillColor(array(166, 166, 166));
        for ($i = 3; $i < 9; ++$i) {
            switch ($i) {
                case 4:
                    $this->pdf->MultiCell($this->cellSizeNumber, $this->headerHeight2, $this->header[$i], 1, 'L', 1, 0, '', '', true, 0, false, true, $this->headerHeight2, 'T');
                    break;
                case 5:
                    $this->pdf->MultiCell($this->cellSizeText2, $this->headerHeight2, $this->header[$i], 1, 'L', 1, 0, '', '', true, 0, false, true, $this->headerHeight2, 'T');
                    break;
                case 6:
                case 7:
                case 8:
                    $this->pdf->MultiCell($this->cellSizeNumber, $this->headerHeight2, $this->header[$i], 1, 'L', 1, 0, '', '', true, 0, false, true, $this->headerHeight2, 'T');
                    break;
                default:
                    $this->pdf->MultiCell($this->cellSizeText, $this->headerHeight2, $this->header[$i], 1, 'L', 1, 0, '', '', true, 0, false, true, $this->headerHeight2, 'T');
            }
        }
        if ($this->environment->isConverged()) {
            $this->pdf->MultiCell($this->cellSizeText, $this->headerHeight2, 'Useable Storage (TB)', 1, 'L', 1, 0, '', '', true, 0, false, true, $this->headerHeight2, 'T');
            $this->pdf->MultiCell($this->cellSizeNumber, $this->headerHeight2, 'IOPs', 1, 'L', 1, 0, '', '', true, 0, false, true, $this->headerHeight2, 'T');
        }
        $this->pdf->MultiCell($this->cellSizeNumber * 2 + (!$this->viewCpm * $this->cellSizeNumber2 * 2), $this->headerHeight2, 'RAM (GB)', 1, 'L', 1, 0, '', '', true, 0, false, true, $this->headerHeight2, 'T');
        if (!$this->environment->isCloud()) {
            if ($this->viewCpm)
                $this->pdf->MultiCell($this->cellSizeNumber2 * 2, $this->headerHeight2, 'CPM', 1, 'L', 1, 0, '', '', true, 0, false, true, $this->headerHeight2, 'T');
        } else
            $this->pdf->MultiCell($this->cellSizeNumber2 * 2, $this->headerHeight2, 'Cores', 1, 'L', 1, 0, '', '', true, 0, false, true, $this->headerHeight2, 'T');
        $this->pdf->Ln();
        $this->pdf->SetFont('', '');
        $this->pdf->fillColor($this->colorTarget);
        if ($this->pdf->GetY() > $this->pageHeight) {
            $this->pdf->AddPage();
        }
        foreach ($this->consolidation->targets as $index => $target) {
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
            $rows = $this->pdf->getNumLines($manufacturer, $this->cellSizeText);
            $maxRows = $rows > $maxRows ? $rows : $maxRows;
            $rows = $this->pdf->getNumLines(!isset($target->server) ? "" : ($target->server ? $target->server->name : ''), $this->cellSizeText2);
            $maxRows = $rows > $maxRows ? $rows : $maxRows;
            $rows = $this->pdf->getNumLines($target->processor->name, $this->cellSizeText2);
            $maxRows = $rows > $maxRows ? $rows : $maxRows;
            $rows = $this->pdf->getNumLines(!isset($target->processor->ghz) ? "" : $target->processor->ghz, $this->cellSizeNumber);
            $maxRows = $rows > $maxRows ? $rows : $maxRows;

            $calculatedCellHeight = $maxRows * $this->cellHeight + 1;

            if ($this->numMappedColumns > 0) {
                $this->pdf->SetFont('', 'B');
                $this->tempHeight = $this->pdf->MultiCell($this->cellSizeText * $this->numMappedColumns, $calculatedCellHeight, $text, 1, 'R', 1, 0, '', '', true, 0, false, true, $calculatedCellHeight, 'M');
                $this->pdf->SetFont('');
            }
            $this->tempHeight = $this->pdf->MultiCell($this->cellSizeText, $calculatedCellHeight, $manufacturer, 1, 'L', 1, 0, '', '', true, 0, false, true, $calculatedCellHeight, 'M');
            $this->tempHeight = $this->pdf->MultiCell($this->cellSizeNumber, $calculatedCellHeight, !isset($target->server) ? "" : ($target->server ? $target->server->name : ''), 1, 'L', 1, 0, '', '', true, 0, false, true, $calculatedCellHeight, 'M');
            $this->tempHeight = $this->pdf->MultiCell($this->cellSizeText2, $calculatedCellHeight, $target->processor->name, 1, 'L', 1, 0, '', '', true, 0, false, true, $calculatedCellHeight, 'M');
            $this->tempHeight = $this->pdf->MultiCell($this->cellSizeNumber, $calculatedCellHeight, !isset($target->processor->ghz) ? "" : $target->processor->ghz, 1, 'L', 1, 0, '', '', true, 0, false, true, $calculatedCellHeight, 'M');

            $this->pdf->MultiCell($this->cellSizeNumber, $calculatedCellHeight, !isset($target->processor->socket_qty) ? "" : number_format($target->processor->socket_qty), 1, 'L', 1, 0, '', '', true, 0, false, true, $calculatedCellHeight, 'M');
            $this->pdf->MultiCell($this->cellSizeNumber, $calculatedCellHeight, number_format($target->processor->total_cores), 1, 'L', 1, 0, '', '', true, 0, false, true, $calculatedCellHeight, 'M');
            $tempWidth = $this->cellSizeText * $this->numMappedColumns + $this->cellSizeNumber * 4 + $this->cellSizeText + $this->cellSizeText2;
            if ($this->environment->isConverged()) {
                $this->pdf->MultiCell($this->cellSizeText, $calculatedCellHeight, number_format(@$target->useable_storage, 2), 1, 'L', 1, 0, '', '', true, 0, false, true, $calculatedCellHeight, 'M');
                $this->pdf->MultiCell($this->cellSizeNumber, $calculatedCellHeight, number_format(@$target->iops, 0), 1, 'L', 1, 0, '', '', true, 0, false, true, $calculatedCellHeight, 'M');
                $tempWidth += $this->cellSizeText + $this->cellSizeNumber;
            }
            $this->pdf->MultiCell($this->cellSizeNumber * 2 + (!$this->viewCpm * $this->cellSizeNumber2 * 2), $calculatedCellHeight, number_format(round($target->utilRam)), 1, 'L', 1, 0, '', '', true, 0, false, true, $calculatedCellHeight, 'M');
            $tempWidth += $this->cellSizeNumber * 2 + (!$this->viewCpm * $this->cellSizeNumber2 * 2);
            if (!$this->environment->isCloud()) {
                if ($this->viewCpm)
                    $this->pdf->MultiCell($this->cellSizeNumber2 * 2, $calculatedCellHeight, ($this->viewCpm && isset($target->utilRpm)) ? number_format(round($target->utilRpm)) : '', 1, 'L', 1, 0, '', '', true, 0, false, true, $calculatedCellHeight, 'M');
            } else {
                $this->pdf->MultiCell($this->cellSizeNumber2 * 2, $calculatedCellHeight, number_format($target->processor->total_cores), 1, 'L', 1, 0, '', '', true, 0, false, true, $calculatedCellHeight, 'M');
            }
            if (!$this->environment->isConverged()) {
                $tempWidth += $this->cellSizeNumber2 * 2;
            }
            $this->pdf->Ln();
            //Add blank line
            $this->pdf->fillColor($color);
            if (count($this->environment->analysis->consolidations) != ($this->consIndex + 1) &&
                count($this->consolidation->targets) == ($index + 1)) {

                $this->pdf->MultiCell($tempWidth, $this->cellHeight, '', 1, 'L', 1, 0, '', '', true, 0, false, true, $this->cellHeight, 'M');

                $this->pdf->Ln();
            }
            //Add an extra check to prevent adding a page after the last target of the last consolidation
            if ($this->pdf->GetY() > $this->pageHeight &&
                !(count($this->environment->analysis->consolidations) == ($this->consIndex + 1) &&
                    count($this->consolidation->targets) == ($index + 1))) {
                $this->pdf->AddPage();
            }
        }
        $this->flip = !$this->flip;

        return $this;
    }
}
