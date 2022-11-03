<?php
/**
 *
 */

namespace App\Services\Analysis\Report\Pdf\Physical;
use App\Models\Project\Environment;
use App\Models\Project\PrecisionPDF;


/**
 * Trait ConsolidationConvergedTrait
 * @package App\Services\Analysis\Report\Pdf
 * @property $viewCmp
 * @property PrecisionPDF $pdf
 * @property Environment $environment
 * @property Environment $existingEnvironment
 * @property $header, $displayWork, $displayLoc, $displayEnv, $noDetails, $numMappedColumns, $cellSizeNumber, $cellSizeNumber2, $cellSizeText, $cellSizeText2, $headerHeight1, $headerHeight2, $num_headers, $cellHeight, $pageHeight, $bg1, $bg2, $bg1Target, $bg2Target;
 */
trait ConsolidationConvergedTrait
{
    /**
     * @return $this
     */
    protected function _drawStorageConsolidationDetail()
    {
        //start of additional storage
        if ($this->environment->isConverged() && isset($this->environment->analysis->storage) && count($this->environment->analysis->storage)) {
            $this->pdf->fillColor(array(255, 255, 255));
            if ($this->environment->analysis->totals->storage->existing > $this->environment->analysis->totals->storage->target) {
                $this->pdf->Ln();
                $this->pdf->MultiCell(190, $this->headerHeight2,
                    "Useable storage deficit = "
                    . number_format($this->environment->analysis->totals->storage->existing, 2)
                    . "TB - " . number_format($this->environment->analysis->totals->storage->target, 2)
                    . "TB = " . number_format($this->environment->analysis->totals->storage->deficit, 2) . "TB"
                    , 0, 'L', 1, 0, '', '', true, 0, false, true, 0, 'M');
            }

            if ($this->pdf->GetY() > $this->pageHeight) {
                $this->pdf->AddPage();
            }
            $this->pdf->Ln();
            $this->pdf->MultiCell(190, $this->headerHeight2, "Additional Nodes Required for Useable deficit:", 0, 'L', 1, 0, '', '', true, 0, false, true, 0, 'M');
            if ($this->pdf->GetY() > $this->pageHeight) {
                $this->pdf->AddPage();
            }
            $this->pdf->Ln();
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
                    case 5:
                        $this->pdf->MultiCell($this->cellSizeText, $this->headerHeight2, $this->header[$i], 1, 'L', 1, 0, '', '', true, 0, false, true, $this->headerHeight2, 'T');
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

            $this->pdf->MultiCell($this->cellSizeText, $this->headerHeight2, 'Useable Storage (TB)', 1, 'L', 1, 0, '', '', true, 0, false, true, $this->headerHeight2, 'T');
            $this->pdf->MultiCell($this->cellSizeNumber, $this->headerHeight2, 'IOPs', 1, 'L', 1, 0, '', '', true, 0, false, true, $this->headerHeight2, 'T');

            $this->pdf->MultiCell($this->cellSizeNumber * 2 + (!$this->viewCpm * $this->cellSizeNumber2 * 2), $this->headerHeight2, 'RAM (GB)', 1, 'L', 1, 0, '', '', true, 0, false, true, $this->headerHeight2, 'T');
            if ($this->viewCpm)
                $this->pdf->MultiCell($this->cellSizeNumber2 * 2, $this->headerHeight2, 'CPM', 1, 'L', 1, 0, '', '', true, 0, false, true, $this->headerHeight2, 'T');
            $this->pdf->Ln();
            $this->pdf->SetFont('', '');
            $this->pdf->fillColor($this->colorTarget);
            if ($this->pdf->GetY() > $this->pageHeight) {
                $this->pdf->AddPage();
            }
            foreach ($this->environment->analysis->storage as $index => $target) {
                $this->startX = $this->pdf->GetX();
                $this->startY = $this->pdf->GetY();
                $maxRows = 1;
                $text = ' ';
                if ($this->numMappedColumns > 0) {
                    $this->pdf->SetFont('', 'B');
                    $rows = $this->pdf->getNumLines($text, $this->cellSizeText * $this->numMappedColumns);
                    $maxRows = $rows > $maxRows ? $rows : $maxRows;
                    $this->pdf->SetFont('');
                }
                $rows = $this->pdf->getNumLines(!isset($target->manufacturer->name) ? "" : $target->manufacturer->name, $this->cellSizeText);
                $maxRows = $rows > $maxRows ? $rows : $maxRows;
                $rows = $this->pdf->getNumLines(!isset($target->server) ? "" : ($target->server ? $target->server->name : ''), $this->cellSizeText);
                $maxRows = $rows > $maxRows ? $rows : $maxRows;
                $rows = $this->pdf->getNumLines($target->processor->name, $this->cellSizeText);
                $maxRows = $rows > $maxRows ? $rows : $maxRows;
                $rows = $this->pdf->getNumLines(!isset($target->processor->ghz) ? "" : $target->processor->ghz, $this->cellSizeNumber);
                $maxRows = $rows > $maxRows ? $rows : $maxRows;

                $calculatedCellHeight = $maxRows * $this->cellHeight + 1;

                if ($this->numMappedColumns > 0) {
                    $this->pdf->SetFont('', 'B');
                    $this->tempHeight = $this->pdf->MultiCell($this->cellSizeText * $this->numMappedColumns, $calculatedCellHeight, $text, 1, 'R', 1, 0, '', '', true, 0, false, true, $calculatedCellHeight, 'M');
                    $this->pdf->SetFont('');
                }
                $this->tempHeight = $this->pdf->MultiCell($this->cellSizeText, $calculatedCellHeight, !isset($target->manufacturer->name) ? "" : $target->manufacturer->name, 1, 'L', 1, 0, '', '', true, 0, false, true, $calculatedCellHeight, 'M');
                $this->tempHeight = $this->pdf->MultiCell($this->cellSizeText, $calculatedCellHeight, !isset($target->server) ? "" : ($target->server ? $target->server->name : ''), 1, 'L', 1, 0, '', '', true, 0, false, true, $calculatedCellHeight, 'M');
                $this->tempHeight = $this->pdf->MultiCell($this->cellSizeText, $calculatedCellHeight, $target->processor->name, 1, 'L', 1, 0, '', '', true, 0, false, true, $calculatedCellHeight, 'M');
                $this->tempHeight = $this->pdf->MultiCell($this->cellSizeNumber, $calculatedCellHeight, !isset($target->processor->ghz) ? "" : $target->processor->ghz, 1, 'L', 1, 0, '', '', true, 0, false, true, $calculatedCellHeight, 'M');
                $this->pdf->MultiCell($this->cellSizeNumber, $calculatedCellHeight, !isset($target->processor->socket_qty) ? "" : number_format($target->processor->socket_qty), 1, 'L', 1, 0, '', '', true, 0, false, true, $calculatedCellHeight, 'M');
                $this->pdf->MultiCell($this->cellSizeNumber, $calculatedCellHeight, number_format($target->processor->total_cores), 1, 'L', 1, 0, '', '', true, 0, false, true, $calculatedCellHeight, 'M');
                $this->pdf->MultiCell($this->cellSizeText, $calculatedCellHeight, number_format($target->useable_storage, 2), 1, 'L', 1, 0, '', '', true, 0, false, true, $calculatedCellHeight, 'M');
                $this->pdf->MultiCell($this->cellSizeNumber, $calculatedCellHeight, number_format($target->iops, 0), 1, 'L', 1, 0, '', '', true, 0, false, true, $calculatedCellHeight, 'M');

                $this->pdf->MultiCell($this->cellSizeNumber * 2 + (!$this->viewCpm * $this->cellSizeNumber2 * 2), $calculatedCellHeight, number_format(round($target->utilRam)), 1, 'L', 1, 0, '', '', true, 0, false, true, $calculatedCellHeight, 'M');
                if (!$this->environment->isCloud()) {
                    if ($this->viewCpm)
                        $this->pdf->MultiCell($this->cellSizeNumber2 * 2, $calculatedCellHeight, ($this->viewCpm && isset($target->utilRpm)) ? number_format(round($target->utilRpm)) : '', 1, 'L', 1, 0, '', '', true, 0, false, true, $calculatedCellHeight, 'M');
                } else
                    $this->pdf->MultiCell($this->cellSizeNumber2 * 2, $calculatedCellHeight, number_format($target->processor->total_cores), 1, 'L', 1, 0, '', '', true, 0, false, true, $calculatedCellHeight, 'M');
                $this->pdf->Ln();
                //Add blank line
                //$this->pdf->fillColor($color);
                if (count($this->environment->analysis->consolidations) != ($this->consIndex + 1) &&
                    count($this->consolidation->targets) == ($index + 1)) {
                    $this->pdf->MultiCell(190, $this->cellHeight, '', 1, 'L', 1, 0, '', '', true, 0, false, true, $this->cellHeight, 'M');
                    $this->pdf->Ln();
                }
                //Add an extra check to prevent adding a page after the last target of the last consolidation
                if ($this->pdf->GetY() > $this->pageHeight &&
                    !(count($this->environment->analysis->consolidations) == ($this->consIndex + 1) &&
                        count($this->consolidation->targets) == ($index + 1))) {
                    $this->pdf->AddPage();
                }
            }
        }

        return $this;
    }

    /**
     * @return $this
     */
    protected function _drawIopsConsolidationDetail()
    {
        //start of additional iops
        if ($this->environment->isConverged() && isset($this->environment->analysis->iops) && count($this->environment->analysis->iops)) {
            if ($this->pdf->GetY() + $this->headerHeight2 * 3 > $this->pageHeight) {
                $this->pdf->AddPage();
            }
            // $this->pdf->Ln();
            $this->pdf->fillColor(array(255, 255, 255));
            if ($this->environment->analysis->totals->iops->existing > 0 && $this->environment->analysis->totals->iops->existing > $this->environment->analysis->totals->iops->target) {
                $this->pdf->Ln();
                $this->pdf->MultiCell(190, $this->headerHeight2,
                    "IOPS deficit = "
                    . number_format($this->environment->analysis->totals->iops->existing, 0)
                    . " IOPS - " . number_format($this->environment->analysis->totals->iops->target, 0)
                    . " IOPS = " . number_format($this->environment->analysis->totals->iops->deficit, 0) . ' IOPS'
                    , 0, 'L', 1, 0, '', '', true, 0, false, true, 0, 'M');
            }

            if ($this->pdf->GetY() > $this->pageHeight) {
                $this->pdf->AddPage();
            }
            $this->pdf->Ln();
            $this->pdf->MultiCell(190, $this->headerHeight2, "Additional Nodes Required for IOPS deficit:", 0, 'L', 1, 0, '', '', true, 0, false, true, 0, 'M');
            if ($this->pdf->GetY() > $this->pageHeight) {
                $this->pdf->AddPage();
            }
            $this->pdf->Ln();
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
                    case 5:
                        $this->pdf->MultiCell($this->cellSizeText, $this->headerHeight2, $this->header[$i], 1, 'L', 1, 0, '', '', true, 0, false, true, $this->headerHeight2, 'T');
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

            $this->pdf->MultiCell($this->cellSizeText, $this->headerHeight2, 'Useable Storage (TB)', 1, 'L', 1, 0, '', '', true, 0, false, true, $this->headerHeight2, 'T');
            $this->pdf->MultiCell($this->cellSizeNumber, $this->headerHeight2, 'IOPs', 1, 'L', 1, 0, '', '', true, 0, false, true, $this->headerHeight2, 'T');

            $this->pdf->MultiCell($this->cellSizeNumber * 2 + (!$this->viewCpm * $this->cellSizeNumber2 * 2), $this->headerHeight2, 'RAM (GB)', 1, 'L', 1, 0, '', '', true, 0, false, true, $this->headerHeight2, 'T');
            if ($this->viewCpm)
                $this->pdf->MultiCell($this->cellSizeNumber2 * 2, $this->headerHeight2, 'CPM', 1, 'L', 1, 0, '', '', true, 0, false, true, $this->headerHeight2, 'T');
            $this->pdf->Ln();
            $this->pdf->SetFont('', '');
            $this->pdf->fillColor($this->colorTarget);
            if ($this->pdf->GetY() > $this->pageHeight) {
                $this->pdf->AddPage();
            }
            foreach ($this->environment->analysis->iops as $index => $target) {
                $this->startX = $this->pdf->GetX();
                $this->startY = $this->pdf->GetY();
                $maxRows = 1;
                $text = ' ';
                if ($this->numMappedColumns > 0) {
                    $this->pdf->SetFont('', 'B');
                    $rows = $this->pdf->getNumLines($text, $this->cellSizeText * $this->numMappedColumns);
                    $maxRows = $rows > $maxRows ? $rows : $maxRows;
                    $this->pdf->SetFont('');
                }
                $rows = $this->pdf->getNumLines(!isset($target->manufacturer->name) ? "" : $target->manufacturer->name, $this->cellSizeText);
                $maxRows = $rows > $maxRows ? $rows : $maxRows;
                $rows = $this->pdf->getNumLines(!isset($target->server) ? "" : ($target->server ? $target->server->name : ''), $this->cellSizeText);
                $maxRows = $rows > $maxRows ? $rows : $maxRows;
                $rows = $this->pdf->getNumLines($target->processor->name, $this->cellSizeText);
                $maxRows = $rows > $maxRows ? $rows : $maxRows;
                $rows = $this->pdf->getNumLines(!isset($target->processor->ghz) ? "" : $target->processor->ghz, $this->cellSizeNumber);
                $maxRows = $rows > $maxRows ? $rows : $maxRows;

                $calculatedCellHeight = $maxRows * $this->cellHeight + 1;

                if ($this->numMappedColumns > 0) {
                    $this->pdf->SetFont('', 'B');
                    $this->tempHeight = $this->pdf->MultiCell($this->cellSizeText * $this->numMappedColumns, $calculatedCellHeight, $text, 1, 'R', 1, 0, '', '', true, 0, false, true, $calculatedCellHeight, 'M');
                    $this->pdf->SetFont('');
                }
                $this->tempHeight = $this->pdf->MultiCell($this->cellSizeText, $calculatedCellHeight, !isset($target->manufacturer->name) ? "" : $target->manufacturer->name, 1, 'L', 1, 0, '', '', true, 0, false, true, $calculatedCellHeight, 'M');
                $this->tempHeight = $this->pdf->MultiCell($this->cellSizeText, $calculatedCellHeight, !isset($target->server) ? "" : ($target->server ? $target->server->name : ''), 1, 'L', 1, 0, '', '', true, 0, false, true, $calculatedCellHeight, 'M');
                $this->tempHeight = $this->pdf->MultiCell($this->cellSizeText, $calculatedCellHeight, $target->processor->name, 1, 'L', 1, 0, '', '', true, 0, false, true, $calculatedCellHeight, 'M');
                $this->tempHeight = $this->pdf->MultiCell($this->cellSizeNumber, $calculatedCellHeight, !isset($target->processor->ghz) ? "" : $target->processor->ghz, 1, 'L', 1, 0, '', '', true, 0, false, true, $calculatedCellHeight, 'M');
                $this->pdf->MultiCell($this->cellSizeNumber, $calculatedCellHeight, !isset($target->processor->socket_qty) ? "" : number_format($target->processor->socket_qty), 1, 'L', 1, 0, '', '', true, 0, false, true, $calculatedCellHeight, 'M');
                $this->pdf->MultiCell($this->cellSizeNumber, $calculatedCellHeight, number_format($target->processor->total_cores), 1, 'L', 1, 0, '', '', true, 0, false, true, $calculatedCellHeight, 'M');
                $this->pdf->MultiCell($this->cellSizeText, $calculatedCellHeight, number_format($target->useable_storage, 2), 1, 'L', 1, 0, '', '', true, 0, false, true, $calculatedCellHeight, 'M');
                $this->pdf->MultiCell($this->cellSizeNumber, $calculatedCellHeight, number_format($target->iops, 0), 1, 'L', 1, 0, '', '', true, 0, false, true, $calculatedCellHeight, 'M');

                $this->pdf->MultiCell($this->cellSizeNumber * 2 + (!$this->viewCpm * $this->cellSizeNumber2 * 2), $calculatedCellHeight, number_format(round($target->utilRam)), 1, 'L', 1, 0, '', '', true, 0, false, true, $calculatedCellHeight, 'M');
                if (!$this->environment->isCloud()) {
                    if ($this->viewCpm)
                        $this->pdf->MultiCell($this->cellSizeNumber2 * 2, $calculatedCellHeight, ($this->viewCpm && isset($target->utilRpm)) ? number_format(round($target->utilRpm)) : '', 1, 'L', 1, 0, '', '', true, 0, false, true, $calculatedCellHeight, 'M');
                } else
                    $this->pdf->MultiCell($this->cellSizeNumber2 * 2, $calculatedCellHeight, number_format($target->processor->total_cores), 1, 'L', 1, 0, '', '', true, 0, false, true, $calculatedCellHeight, 'M');
                $this->pdf->Ln();
                //Add blank line
                //$this->pdf->fillColor($color);
                if (count($this->environment->analysis->consolidations) != ($this->consIndex + 1) &&
                    count($this->consolidation->targets) == ($index + 1)) {
                    $this->pdf->MultiCell(190, $this->cellHeight, '', 1, 'L', 1, 0, '', '', true, 0, false, true, $this->cellHeight, 'M');
                    $this->pdf->Ln();
                }
                //Add an extra check to prevent adding a page after the last target of the last consolidation
                if ($this->pdf->GetY() > $this->pageHeight &&
                    !(count($this->environment->analysis->consolidations) == ($this->consIndex + 1) &&
                        count($this->consolidation->targets) == ($index + 1))) {
                    $this->pdf->AddPage();
                }
            }
        }

        return $this;
    }

    /**
     * @return $this
     */
    protected function _drawConvergedAdditionalDetail()
    {
        if ($this->environment->isConverged() && isset($this->environment->analysis->converged) && count($this->environment->analysis->converged)) {
            $this->pdf->Ln();
            $this->pdf->fillColor(array(255, 255, 255));
            $this->pdf->MultiCell(190, $this->headerHeight2, "The consolidation analysis results require one node.  Converged environments require at least two nodes. An additional node was added to satisfy the two node minimum requirement:", 0, 'L', 1, 0, '', '', true, 0, false, true, 0, 'M');
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

            $this->pdf->MultiCell($this->cellSizeText, $this->headerHeight2, 'Useable Storage (TB)', 1, 'L', 1, 0, '', '', true, 0, false, true, $this->headerHeight2, 'T');
            $this->pdf->MultiCell($this->cellSizeNumber * 2 + (!$this->viewCpm * $this->cellSizeNumber2 * 2), $this->headerHeight2, 'RAM (GB)', 1, 'L', 1, 0, '', '', true, 0, false, true, $this->headerHeight2, 'T');
            if ($this->viewCpm)
                $this->pdf->MultiCell($this->cellSizeNumber2 * 2, $this->headerHeight2, 'CPM', 1, 'L', 1, 0, '', '', true, 0, false, true, $this->headerHeight2, 'T');
            $this->pdf->Ln();
            $this->pdf->SetFont('', '');
            $this->pdf->fillColor($this->colorTarget);
            if ($this->pdf->GetY() > $this->pageHeight) {
                $this->pdf->AddPage();
            }
            foreach ($this->environment->analysis->converged as $index => $target) {
                $this->startX = $this->pdf->GetX();
                $this->startY = $this->pdf->GetY();
                $maxRows = 1;
                $text = ' ';
                if ($this->numMappedColumns > 0) {
                    $this->pdf->SetFont('', 'B');
                    $rows = $this->pdf->getNumLines($text, $this->cellSizeText * $this->numMappedColumns);
                    $maxRows = $rows > $maxRows ? $rows : $maxRows;
                    $this->pdf->SetFont('');
                }
                $rows = $this->pdf->getNumLines(!isset($target->manufacturer->name) ? "" : $target->manufacturer->name, $this->cellSizeText);
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
                $this->tempHeight = $this->pdf->MultiCell($this->cellSizeText, $calculatedCellHeight, !isset($target->manufacturer->name) ? "" : $target->manufacturer->name, 1, 'L', 1, 0, '', '', true, 0, false, true, $calculatedCellHeight, 'M');
                $this->tempHeight = $this->pdf->MultiCell($this->cellSizeText2, $calculatedCellHeight, !isset($target->server) ? "" : ($target->server ? $target->server->name : ''), 1, 'L', 1, 0, '', '', true, 0, false, true, $calculatedCellHeight, 'M');
                $this->tempHeight = $this->pdf->MultiCell($this->cellSizeText2, $calculatedCellHeight, $target->processor->name, 1, 'L', 1, 0, '', '', true, 0, false, true, $calculatedCellHeight, 'M');
                $this->tempHeight = $this->pdf->MultiCell($this->cellSizeNumber, $calculatedCellHeight, !isset($target->processor->ghz) ? "" : $target->processor->ghz, 1, 'L', 1, 0, '', '', true, 0, false, true, $calculatedCellHeight, 'M');
                $this->pdf->MultiCell($this->cellSizeNumber, $calculatedCellHeight, !isset($target->processor->socket_qty) ? "" : number_format($target->processor->socket_qty), 1, 'L', 1, 0, '', '', true, 0, false, true, $calculatedCellHeight, 'M');
                $this->pdf->MultiCell($this->cellSizeNumber, $calculatedCellHeight, number_format($target->processor->total_cores), 1, 'L', 1, 0, '', '', true, 0, false, true, $calculatedCellHeight, 'M');
                $this->pdf->MultiCell($this->cellSizeText, $calculatedCellHeight, $target->useable_storage, 1, 'L', 1, 0, '', '', true, 0, false, true, $calculatedCellHeight, 'M');
                $this->pdf->MultiCell($this->cellSizeNumber * 2 + (!$this->viewCpm * $this->cellSizeNumber2 * 2), $calculatedCellHeight, number_format(round($target->utilRam)), 1, 'L', 1, 0, '', '', true, 0, false, true, $calculatedCellHeight, 'M');
                if (!$this->environment->isCloud()) {
                    if ($this->viewCpm)
                        $this->pdf->MultiCell($this->cellSizeNumber2 * 2, $calculatedCellHeight, ($this->viewCpm && isset($target->utilRpm)) ? number_format(round($target->utilRpm)) : '', 1, 'L', 1, 0, '', '', true, 0, false, true, $calculatedCellHeight, 'M');
                } else
                    $this->pdf->MultiCell($this->cellSizeNumber2 * 2, $calculatedCellHeight, number_format($target->processor->total_cores), 1, 'L', 1, 0, '', '', true, 0, false, true, $calculatedCellHeight, 'M');
                $this->pdf->Ln();
                //Add blank line
                //$this->pdf->fillColor($color);
                if (count($this->environment->analysis->consolidations) != ($this->consIndex + 1) &&
                    count($this->consolidation->targets) == ($index + 1)) {
                    $this->pdf->MultiCell(190, $this->cellHeight, '', 1, 'L', 1, 0, '', '', true, 0, false, true, $this->cellHeight, 'M');
                    $this->pdf->Ln();
                }
                //Add an extra check to prevent adding a page after the last target of the last consolidation
                if ($this->pdf->GetY() > $this->pageHeight &&
                    !(count($this->environment->analysis->consolidations) == ($this->consIndex + 1) &&
                        count($this->consolidation->targets) == ($index + 1))) {
                    $this->pdf->AddPage();
                }
            }
        }

        return $this;
    }
}