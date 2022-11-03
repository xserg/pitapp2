<?php
/**
 *
 */

namespace App\Services\Analysis\Report\Pdf\Vm;

use App\Models\Project\Environment;
use App\Models\Project\PrecisionPDF;
use Auth;

/**
 * Trait ConsolidationSummaryTrait
 * Encapsulates the drawing of the PDF Consolidation Header
 * @package App\Services\Analysis\Report\Pdf
 * @property $viewCpm
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
        $widthAddition = (($this->cellSizeNumber * 3) + $this->cellSizeText + $this->cellSizeNumber) / 6;
        $this->pdf->__widthAddition = $widthAddition;
        $totals = &$this->environment->analysis->totals;
         $cpuMatch = function() use ($totals) {
            return ($totals->existing->cpuMatch ?? false)  ? round($this->existingEnvironment->getCpuUtilization(),0) . '%' : 'Util';
        };
        $ramMatch = function() use ($totals) {
            return ($totals->existing->ramMatch ?? false)  ? round($this->existingEnvironment->getRamUtilization(),0) . '%' : 'Util';
        };
        $this->pdf->SetFont('', 'B', 11);
        $text = 'Summary';

        $this->pdf->iwMultiCell($this->cellSizeText + $this->cellSizeText + $this->cellSizeText2, $this->headerHeight1, $text, 0, 'L', 0, 0, '', '', true, $this->headerHeight1, false, true, 0, 'M');
        $this->pdf->SetFont('', 'B', 7);
        $this->pdf->fillColor(array(166, 166, 166));
        $this->pdf->MultiCell($this->cellSizeNumber, $this->headerHeight1, 'VM Servers', 1, 'L', 1, 0, '', '', true, 0, false, true, $this->headerHeight1, 'T');
        $this->pdf->MultiCell($this->cellSizeNumber, $this->headerHeight1, 'VM Total Cores', 1, 'L', 1, 0, '', '', true, 0, false, true, $this->headerHeight1, 'T');
        $this->pdf->MultiCell($this->cellSizeNumber, $this->headerHeight1, 'VM RAM @ 100%', 1, 'L', 1, 0, '', '', true, 0, false, true, $this->headerHeight1, 'T');
        $this->pdf->MultiCell($this->cellSizeNumber, $this->headerHeight1, 'VM RAM @ ' . $ramMatch(), 1, 'L', 1, 0, '', '', true, 0, false, true, $this->headerHeight1, 'T');
        $this->pdf->MultiCell($this->cellSizeNumber2, $this->headerHeight1, 'VM Cores @ 100%', 1, 'L', 1, 0, '', '', true, 0, false, true, $this->headerHeight1, 'T');
        $this->pdf->MultiCell($this->cellSizeNumber2, $this->headerHeight1, 'VM Cores @ ' . $cpuMatch(), 1, 'L', 1, 0, '', '', true, 0, false, true, $this->headerHeight1, 'T');
        $this->pdf->SetFont('', '');
        $this->pdf->Ln();
        if ($this->numMappedColumns > 0) {
            $this->pdf->iwMultiCell($this->cellSizeText, $this->cellHeight, '', 0, 'R', 0, 0, '', '', true, 0, false, true, 0, 'M');
        }

        return $this;
    }

    /**
     * @return $this
     */
    protected function _drawSummaryExisting()
    {
        $totals = &$this->environment->analysis->totals;
        $this->pdf->fillColor(array(166, 166, 166));
        $this->pdf->iwMultiCell($this->cellSizeText + $this->cellSizeText2, $this->cellHeight, 'Existing Environment Totals', 1, 'L', 1, 0, '', '', true, 0, false, true, 0, 'M');
        $this->pdf->fillColor($this->bg1);
        $this->pdf->MultiCell($this->cellSizeNumber, $this->cellHeight, number_format($totals->existing->vms), 1, 'L', 1, 0, '', '', true, 0, false, true, 0, 'M');
        $this->pdf->MultiCell($this->cellSizeNumber, $this->cellHeight, mixed_number($totals->existing->total_cores), 1, 'L', 1, 0, '', '', true, 0, false, true, 0, 'M');
        $this->pdf->MultiCell($this->cellSizeNumber, $this->cellHeight, number_format(round($totals->existing->ram), 0), 1, 'L', 1, 0, '', '', true, 0, false, true, 0, 'M');
        $this->pdf->MultiCell($this->cellSizeNumber, $this->cellHeight, number_format(round($totals->existing->computedRam), 0), 1, 'L', 1, 0, '', '', true, 0, false, true, 0, 'M');
        $this->pdf->MultiCell($this->cellSizeNumber2, $this->cellHeight, mixed_number($totals->existing->cores), 1, 'L', 1, 0, '', '', true, 0, false, true, 0, 'M');
        $this->pdf->MultiCell($this->cellSizeNumber2, $this->cellHeight, mixed_number($totals->existing->computedCores), 1, 'L', 1, 0, '', '', true, 0, false, true, 0, 'M');
        $this->pdf->Ln();

        if ($this->numMappedColumns > 0) {
            $this->pdf->iwMultiCell($this->cellSizeText, $this->cellHeight, '', 0, 'R', 0, 0, '', '', true, 0, false, true, 0, 'M');
        }
        $this->pdf->fillColor(array(166, 166, 166));

        return $this;
    }

    /**
     * @return $this
     */
    protected function _drawSummaryTarget()
    {
        $totals = &$this->environment->analysis->totals;
        $this->pdf->iwMultiCell($this->cellSizeText + $this->cellSizeText2, $this->cellHeight, 'Target Environment Totals', 1, 'L', 1, 0, '', '', true, 0, false, true, 0, 'M');
        $this->pdf->fillColor($this->bg1);
        $this->pdf->MultiCell($this->cellSizeNumber, $this->cellHeight, number_format($totals->target->vms), 1, 'L', 1, 0, '', '', true, 0, false, true, 0, 'M');
        $this->pdf->MultiCell($this->cellSizeNumber, $this->cellHeight, number_format($totals->target->total_cores), 1, 'L', 1, 0, '', '', true, 0, false, true, 0, 'M');
        $this->pdf->MultiCell($this->cellSizeNumber, $this->cellHeight, number_format(round($totals->target->utilRam, 0)), 1, 'L', 1, 0, '', '', true, 0, false, true, 0, 'M');
        $this->pdf->MultiCell($this->cellSizeNumber, $this->cellHeight, number_format(round(!$this->environment->isCloud() ? $totals->existing->comparisonComputedRam : $totals->target->utilRam, 0)), 1, 'L', 1, 0, '', '', true, 0, false, true, 0, 'M');
        $this->pdf->MultiCell($this->cellSizeNumber2, $this->cellHeight, mixed_number($totals->target->total_cores, 0), 1, 'L', 1, 0, '', '', true, 0, false, true, 0, 'M');
        $this->pdf->MultiCell($this->cellSizeNumber2, $this->cellHeight, number_format($totals->target->total_cores, 0), 1, 'L', 1, 0, '', '', true, 0, false, true, 0, 'M');
        $this->pdf->Ln();
        $this->pdf->Ln();

        return $this;
    }
}