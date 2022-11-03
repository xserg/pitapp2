<?php
/**
 *
 */

namespace App\Services\Analysis\Report\Pdf\Physical;

use App\Models\Project\Environment;
use App\Models\Project\PrecisionPDF;
use Auth;

/**
 * Trait ConsolidationSummaryTrait
 * Encapsulates the drawing of the PDF Consolidation Header
 * @package App\Services\Analysis\Report\Pdf
 * @property $viewCmp
 * @property PrecisionPDF $pdf
 * @property Environment $environment
 * @property Environment $existingEnvironment
 * @property $header, $displayWork, $displayLoc, $displayEnv, $noDetails, $numMappedColumns, $cellSizeNumber, $cellSizeNumber2, $cellSizeText, $cellSizeText2, $headerHeight1, $headerHeight2, $num_headers, $cellHeight, $pageHeight, $bg1, $bg2, $bg1Target, $bg2Target;
 */
trait ConsolidationSummaryTrait
{
    /**
     * @return $this
     */
    protected function _drawSummaryHeaders()
    {
        $this->pdf->SetFont('', 'B', 11);
        $text = 'Summary';

        $this->pdf->MultiCell($this->cellSizeText * $this->numMappedColumns + $this->cellSizeText + $this->cellSizeText2, $this->headerHeight1, $text, 0, 'L', 0, 0, '', '', true, $this->headerHeight1, false, true, 0, 'M');
        $this->pdf->SetFont('', 'B', 7);
        $this->pdf->fillColor(array(166, 166, 166));
        $this->pdf->MultiCell($this->cellSizeNumber, $this->headerHeight1, 'Servers', 1, 'L', 1, 0, '', '', true, 0, false, true, $this->headerHeight1, 'T');
        for ($i = 7; $i < $this->num_headers; ++$i) {
            switch ($i) {
                case 7:
                case 8:
                case 9:
                    if ($this->environment->isConverged() && $i == 9) {
                        $this->pdf->MultiCell($this->cellSizeText, $this->headerHeight1, "Useable Storage (TB)", 1, 'L', 1, 0, '', '', true, 0, false, true, $this->headerHeight1, 'T');
                        $this->pdf->MultiCell($this->cellSizeNumber, $this->headerHeight1, "IOPs", 1, 'L', 1, 0, '', '', true, 0, false, true, $this->headerHeight1, 'T');
                    }
                    $this->pdf->MultiCell($this->cellSizeNumber, $this->headerHeight1, $this->header[$i], 1, 'L', 1, 0, '', '', true, 0, false, true, $this->headerHeight1, 'T');
                    break;
                case 10:
                    $this->pdf->MultiCell($this->cellSizeNumber, $this->headerHeight1, $this->environment->analysis->totals->existing->ramMatch ? $this->header[$i] : "RAM @ Util", 1, 'L', 1, 0, '', '', true, 0, false, true, $this->headerHeight1, 'T');
                    break;
                case 11:
                    if ($this->environment->isCloud())
                        $this->pdf->MultiCell($this->cellSizeNumber2, $this->headerHeight1, "Cores @ 100%", 1, 'L', 1, 0, '', '', true, 0, false, true, $this->headerHeight1, 'T');
                    else
                        $this->pdf->MultiCell($this->cellSizeNumber2, $this->headerHeight1, $this->header[$i], 1, 'L', 1, 0, '', '', true, 0, false, true, $this->headerHeight1, 'T');
                    break;
                case 12:
                    if ($this->environment->isCloud())
                        $this->pdf->MultiCell($this->cellSizeNumber2, $this->headerHeight1, "Cores @ " . $this->existingEnvironment->cpu_utilization . "%", 1, 'L', 1, 0, '', '', true, 0, false, true, $this->headerHeight1, 'T');
                    else
                        $this->pdf->MultiCell($this->cellSizeNumber2, $this->headerHeight1, $this->environment->analysis->totals->existing->cpuMatch ? $this->header[$i] : "CPM @ Util", 1, 'L', 1, 0, '', '', true, 0, false, true, $this->headerHeight1, 'T');
                    break;
                default:
                    $this->pdf->MultiCell($this->cellSizeText, $this->headerHeight1, $this->header[$i], 1, 'L', 1, 0, '', '', true, 0, false, true, $this->headerHeight1, 'T');
            }
        }
        $this->pdf->SetFont('', '');
        $this->pdf->Ln();
        if ($this->numMappedColumns > 0) {
            $this->pdf->MultiCell($this->cellSizeText * $this->numMappedColumns, $this->cellHeight, '', 0, 'R', 0, 0, '', '', true, 0, false, true, 0, 'M');
        }

        return $this;
    }

    /**
     * @return $this
     */
    protected function _drawSummaryExisting()
    {
        $this->pdf->fillColor(array(166, 166, 166));
        $this->pdf->MultiCell($this->cellSizeText + $this->cellSizeText2, $this->cellHeight, 'Existing Environment Totals', 1, 'L', 1, 0, '', '', true, 0, false, true, 0, 'M');
        $this->pdf->fillColor($this->bg1);
        $this->pdf->MultiCell($this->cellSizeNumber, $this->cellHeight, number_format($this->environment->analysis->totals->existing->servers), 1, 'L', 1, 0, '', '', true, 0, false, true, 0, 'M');
        $this->pdf->MultiCell($this->cellSizeNumber, $this->cellHeight, number_format($this->environment->analysis->totals->existing->socket_qty), 1, 'L', 1, 0, '', '', true, 0, false, true, 0, 'M');
        $this->pdf->MultiCell($this->cellSizeNumber, $this->cellHeight, number_format($this->environment->analysis->totals->existing->total_cores), 1, 'L', 1, 0, '', '', true, 0, false, true, 0, 'M');
        if ($this->environment->isConverged()) {
            $this->pdf->MultiCell($this->cellSizeText, $this->cellHeight, number_format(@$this->environment->analysis->totals->storage->existing, 2), 1, 'L', 1, 0, '', '', true, 0, false, true, 0, 'M');
            $this->pdf->MultiCell($this->cellSizeNumber, $this->cellHeight, number_format(@$this->environment->analysis->totals->iops->existing, 0), 1, 'L', 1, 0, '', '', true, 0, false, true, 0, 'M');
        }
        $this->pdf->MultiCell($this->cellSizeNumber, $this->cellHeight, number_format(round($this->environment->analysis->totals->existing->ram), 0), 1, 'L', 1, 0, '', '', true, 0, false, true, 0, 'M');
        $this->pdf->MultiCell($this->cellSizeNumber, $this->cellHeight, number_format(round($this->environment->analysis->totals->existing->computedRam), 0), 1, 'L', 1, 0, '', '', true, 0, false, true, 0, 'M');
        if (!$this->environment->isCloud()) {
            $this->pdf->MultiCell($this->cellSizeNumber2, $this->cellHeight, number_format(round($this->environment->analysis->totals->existing->rpm), 0), 1, 'L', 1, 0, '', '', true, 0, false, true, 0, 'M');
            $this->pdf->MultiCell($this->cellSizeNumber2, $this->cellHeight, number_format(round($this->environment->analysis->totals->existing->computedRpm), 0), 1, 'L', 1, 0, '', '', true, 0, false, true, 0, 'M');
        } else {
            $this->pdf->MultiCell($this->cellSizeNumber2, $this->cellHeight, number_format(round($this->environment->analysis->totals->existing->cores), 0), 1, 'L', 1, 0, '', '', true, 0, false, true, 0, 'M');
            $this->pdf->MultiCell($this->cellSizeNumber2, $this->cellHeight, number_format(round($this->environment->analysis->totals->existing->computedCores), 0), 1, 'L', 1, 0, '', '', true, 0, false, true, 0, 'M');
        }
        $this->pdf->Ln();

        if ($this->numMappedColumns > 0) {
            $this->pdf->MultiCell($this->cellSizeText * $this->numMappedColumns, $this->cellHeight, '', 0, 'R', 0, 0, '', '', true, 0, false, true, 0, 'M');
        }
        $this->pdf->fillColor(array(166, 166, 166));

        return $this;
    }

    /**
     * @return $this
     */
    protected function _drawSummaryTarget()
    {
        $this->pdf->MultiCell($this->cellSizeText + $this->cellSizeText2, $this->cellHeight, 'Target Environment Totals', 1, 'L', 1, 0, '', '', true, 0, false, true, 0, 'M');
        $this->pdf->fillColor($this->bg1);
        $this->pdf->MultiCell($this->cellSizeNumber, $this->cellHeight, number_format($this->environment->analysis->totals->target->servers), 1, 'L', 1, 0, '', '', true, 0, false, true, 0, 'M');
        $this->pdf->MultiCell($this->cellSizeNumber, $this->cellHeight, !isset($this->environment->analysis->totals->target->socket_qty) ? "" : number_format($this->environment->analysis->totals->target->socket_qty), 1, 'L', 1, 0, '', '', true, 0, false, true, 0, 'M');
        $this->pdf->MultiCell($this->cellSizeNumber, $this->cellHeight, number_format($this->environment->analysis->totals->target->total_cores), 1, 'L', 1, 0, '', '', true, 0, false, true, 0, 'M');
        if ($this->environment->isConverged()) {
            $this->pdf->MultiCell($this->cellSizeText, $this->cellHeight, number_format(@$this->environment->analysis->totals->storage->targetTotal, 2), 1, 'L', 1, 0, '', '', true, 0, false, true, 0, 'M');
            $this->pdf->MultiCell($this->cellSizeNumber, $this->cellHeight, number_format(@$this->environment->analysis->totals->iops->targetTotal, 0), 1, 'L', 1, 0, '', '', true, 0, false, true, 0, 'M');
        }
        $this->pdf->MultiCell($this->cellSizeNumber * 2, $this->cellHeight, number_format(round($this->environment->analysis->totals->target->utilRam, 0)), 1, 'L', 1, 0, '', '', true, 0, false, true, 0, 'M');
        if (!$this->environment->isCloud()) {
            $this->pdf->MultiCell($this->cellSizeNumber2 * 2, $this->cellHeight, !isset($this->environment->analysis->totals->target->utilRpm) ? "" : number_format(round($this->environment->analysis->totals->target->utilRpm), 0), 1, 'L', 1, 0, '', '', true, 0, false, true, 0, 'M');
        } else {
            $this->pdf->MultiCell($this->cellSizeNumber2 * 2, $this->cellHeight, number_format($this->environment->analysis->totals->target->total_cores, 0), 1, 'L', 1, 0, '', '', true, 0, false, true, 0, 'M');
        }
        $this->pdf->Ln();
        $this->pdf->Ln();

        return $this;
    }
}