<?php
/**
 *
 */

namespace App\Services\Analysis\Report\Pdf\Hybrid;

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
        $widthAddition = $this->environment->isConverged() ? 0 : ($this->cellSizeText + $this->cellSizeNumber) / (9 - ((!$this->environment->isCloud() && !$this->viewCpm) ? 2 : 0));
        
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

        $this->pdf->iwMultiCell($this->cellSizeText * 2 + $this->cellSizeText2, $this->headerHeight1, $text, 0, 'L', 0, 0, '', '', true, $this->headerHeight1, false, true, 0, 'M');
        $this->pdf->SetFont('', 'B', 7);
        $this->pdf->fillColor(array(166, 166, 166));
        $this->pdf->MultiCell($this->cellSizeNumber, $this->headerHeight1, 'Physical Servers', 1, 'L', 1, 0, '', '', true, 0, false, true, $this->headerHeight1, 'T');
        $this->pdf->MultiCell($this->cellSizeNumber, $this->headerHeight1, 'Processors', 1, 'L', 1, 0, '', '', true, 0, false, true, $this->headerHeight1, 'T');
        $this->pdf->MultiCell($this->cellSizeNumber, $this->headerHeight1, 'Physical Cores', 1, 'L', 1, 0, '', '', true, 0, false, true, $this->headerHeight1, 'T');
        if ($this->environment->isCloud()) {
            $this->pdf->MultiCell($this->cellSizeNumber2, $this->headerHeight1, 'Physical Cores @ ' . $cpuMatch(), 1, 'L', 1, 0, '', '', true, 0, false, true, $this->headerHeight1, 'T');
        }
        
        if ($this->environment->isConverged()) {
            $this->pdf->MultiCell($this->cellSizeText, $this->headerHeight1, "Useable Storage (TB)", 1, 'L', 1, 0, '', '', true, 0, false, true, $this->headerHeight1, 'T');
            $this->pdf->MultiCell($this->cellSizeNumber, $this->headerHeight1, "IOPs", 1, 'L', 1, 0, '', '', true, 0, false, true, $this->headerHeight1, 'T');
        }
        $this->pdf->MultiCell($this->cellSizeNumber, $this->headerHeight1, 'Physical RAM @ ' . $ramMatch(), 1, 'L', 1, 0, '', '', true, 0, false, true, $this->headerHeight1, 'T');
        
        if (!$this->environment->isCloud()) {
            $this->pdf->MultiCell($this->cellSizeNumber2, $this->headerHeight1, 'Physical CPM @ ' . $cpuMatch(), 1, 'L', 1, 0, '', '', true, 0, false, true, $this->headerHeight1, 'T');
        }
        $this->pdf->MultiCell($this->cellSizeNumber, $this->headerHeight1, 'VM Servers', 1, 'L', 1, 0, '', '', true, 0, false, true, $this->headerHeight1, 'T');
        $this->pdf->MultiCell($this->cellSizeNumber, $this->headerHeight1, 'VM Cores', 1, 'L', 1, 0, '', '', true, 0, false, true, $this->headerHeight1, 'T');
        $this->pdf->MultiCell($this->cellSizeNumber, $this->headerHeight1, 'VM RAM @ ' . $ramMatch(), 1, 'L', 1, 0, '', '', true, 0, false, true, $this->headerHeight1, 'T');
        if (!$this->environment->isCloud()) {
            $this->pdf->MultiCell($this->cellSizeNumber2, $this->headerHeight1, 'VM CPM @ ' . $cpuMatch(), 1, 'L', 1, 0, '', '', true, 0, false, true, $this->headerHeight1, 'T');
        } else if ($this->environment->isCloud()) {
            $this->pdf->MultiCell($this->cellSizeNumber2, $this->headerHeight1, 'VM Cores @ ' . $cpuMatch(), 1, 'L', 1, 0, '', '', true, 0, false, true, $this->headerHeight1, 'T');
        }
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
        if($this->environment->isCloud()) {
            return $this->_drawCloudSummaryExisting();
        } else {
            return $this->_drawPhysicalSummaryExisting();
        }
    }

    /**
     * @return $this
     */
    protected function _drawPhysicalSummaryExisting()
    {
        $totals = &$this->environment->analysis->totals;
        $this->pdf->fillColor(array(166, 166, 166));
        $this->pdf->iwMultiCell($this->cellSizeText + $this->cellSizeText2, $this->cellHeight, 'Existing Environment Totals', 1, 'L', 1, 0, '', '', true, 0, false, true, 0, 'M');
        $this->pdf->fillColor($this->bg1);
        $this->pdf->MultiCell($this->cellSizeNumber, $this->cellHeight, number_format($totals->existing->servers), 1, 'L', 1, 0, '', '', true, 0, false, true, 0, 'M');
        $this->pdf->MultiCell($this->cellSizeNumber, $this->cellHeight, number_format($totals->existing->socket_qty), 1, 'L', 1, 0, '', '', true, 0, false, true, 0, 'M');
        $this->pdf->MultiCell($this->cellSizeNumber, $this->cellHeight, mixed_number($totals->existing->physical_cores), 1, 'L', 1, 0, '', '', true, 0, false, true, 0, 'M');
        if ($this->environment->isConverged()) {
            $this->pdf->MultiCell($this->cellSizeText, $this->cellHeight, number_format($totals->storage->existing, 2), 1, 'L', 1, 0, '', '', true, 0, false, true, 0, 'M');
            $this->pdf->MultiCell($this->cellSizeNumber, $this->cellHeight, number_format($totals->iops->existing, 0), 1, 'L', 1, 0, '', '', true, 0, false, true, 0, 'M');
        }
        $this->pdf->MultiCell($this->cellSizeNumber2, $this->cellHeight, number_format(round($totals->existing->physical_ram), 0), 1, 'L', 1, 0, '', '', true, 0, false, true, 0, 'M');
        $this->pdf->MultiCell($this->cellSizeNumber, $this->cellHeight, number_format(round($totals->existing->physical_rpm), 0), 1, 'L', 1, 0, '', '', true, 0, false, true, 0, 'M');
        $this->pdf->MultiCell($this->cellSizeNumber, $this->cellHeight, number_format($totals->existing->vms), 1, 'L', 1, 0, '', '', true, 0, false, true, 0, 'M');
        $this->pdf->MultiCell($this->cellSizeNumber, $this->cellHeight, floatval(number_format($totals->existing->vm_cores, 1)), 1, 'L', 1, 0, '', '', true, 0, false, true, 0, 'M');
        $this->pdf->MultiCell($this->cellSizeNumber, $this->cellHeight, number_format(round($totals->existing->computedRam), 0), 1, 'L', 1, 0, '', '', true, 0, false, true, 0, 'M');
        $this->pdf->MultiCell($this->cellSizeNumber2, $this->cellHeight, number_format(round($totals->existing->computedRpm), 0), 1, 'L', 1, 0, '', '', true, 0, false, true, 0, 'M');
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
    protected function _drawCloudSummaryExisting()
    {
        $totals = &$this->environment->analysis->totals;
        $this->pdf->fillColor(array(166, 166, 166));
        $this->pdf->iwMultiCell($this->cellSizeText + $this->cellSizeText2, $this->cellHeight, 'Existing Environment Totals', 1, 'L', 1, 0, '', '', true, 0, false, true, 0, 'M');
        $this->pdf->fillColor($this->bg1);
        $this->pdf->MultiCell($this->cellSizeNumber, $this->cellHeight, number_format($totals->existing->servers), 1, 'L', 1, 0, '', '', true, 0, false, true, 0, 'M');
        $this->pdf->MultiCell($this->cellSizeNumber2, $this->cellHeight, mixed_number($totals->existing->socket_qty), 1, 'L', 1, 0, '', '', true, 0, false, true, 0, 'M');
        $this->pdf->MultiCell($this->cellSizeNumber, $this->cellHeight, number_format($totals->existing->physical_cores), 1, 'L', 1, 0, '', '', true, 0, false, true, 0, 'M');
        $this->pdf->MultiCell($this->cellSizeNumber, $this->cellHeight, mixed_number($totals->existing->physicalCoresUtil), 1, 'L', 1, 0, '', '', true, 0, false, true, 0, 'M');
        $this->pdf->MultiCell($this->cellSizeNumber, $this->cellHeight, number_format(round($totals->existing->physicalRamUtil), 0), 1, 'L', 1, 0, '', '', true, 0, false, true, 0, 'M');
        $this->pdf->MultiCell($this->cellSizeNumber, $this->cellHeight, number_format($totals->existing->vms), 1, 'L', 1, 0, '', '', true, 0, false, true, 0, 'M');
        $this->pdf->MultiCell($this->cellSizeNumber, $this->cellHeight, floatval(number_format($totals->existing->vm_cores, 1)), 1, 'L', 1, 0, '', '', true, 0, false, true, 0, 'M');
        $this->pdf->MultiCell($this->cellSizeNumber, $this->cellHeight, number_format(round($totals->existing->computedRam), 0), 1, 'L', 1, 0, '', '', true, 0, false, true, 0, 'M');
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
        if($this->environment->isCloud()) {
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
        $this->pdf->iwMultiCell($this->cellSizeText + $this->cellSizeText2, $this->cellHeight, 'Target Environment Totals', 1, 'L', 1, 0, '', '', true, 0, false, true, 0, 'M');
        $this->pdf->fillColor($this->bg1);
        $this->pdf->MultiCell($this->cellSizeNumber, $this->cellHeight, number_format($totals->target->servers), 1, 'L', 1, 0, '', '', true, 0, false, true, 0, 'M');
        $this->pdf->MultiCell($this->cellSizeNumber2, $this->cellHeight, '', 1, 'L', 1, 0, '', '', true, 0, false, true, 0, 'M');
        $this->pdf->MultiCell($this->cellSizeNumber, $this->cellHeight, '', 1, 'L', 1, 0, '', '', true, 0, false, true, 0, 'M');
        $this->pdf->MultiCell($this->cellSizeNumber, $this->cellHeight, '', 1, 'L', 1, 0, '', '', true, 0, false, true, 0, 'M');
        $this->pdf->MultiCell($this->cellSizeNumber , $this->cellHeight, number_format(round($totals->target->utilRam, 0)), 1, 'L', 1, 0, '', '', true, 0, false, true, 0, 'M');
        $this->pdf->MultiCell($this->cellSizeNumber, $this->cellHeight, number_format($totals->target->vms), 1, 'L', 1, 0, '', '', true, 0, false, true, 0, 'M');
        $this->pdf->MultiCell($this->cellSizeNumber, $this->cellHeight, floatval(number_format($totals->target->physical_cores, 1)), 1, 'L', 1, 0, '', '', true, 0, false, true, 0, 'M');
        $this->pdf->MultiCell($this->cellSizeNumber , $this->cellHeight, number_format($totals->target->utilRam), 1, 'L', 1, 0, '', '', true, 0, false, true, 0, 'M');
        $this->pdf->MultiCell($this->cellSizeNumber2, $this->cellHeight, number_format($totals->target->total_cores, 0), 1, 'L', 1, 0, '', '', true, 0, false, true, 0, 'M');
        $this->pdf->Ln();
        $this->pdf->Ln();

        return $this;
    }

    /**
     * @return $this
     */
    protected function _drawPhysicalSummaryTarget()
    {
        $totals = &$this->environment->analysis->totals;
        $this->pdf->iwMultiCell($this->cellSizeText + $this->cellSizeText2, $this->cellHeight, 'Target Environment Totals', 1, 'L', 1, 0, '', '', true, 0, false, true, 0, 'M');
        $this->pdf->fillColor($this->bg1);
        $this->pdf->MultiCell($this->cellSizeNumber, $this->cellHeight, number_format($totals->target->servers), 1, 'L', 1, 0, '', '', true, 0, false, true, 0, 'M');
        $this->pdf->MultiCell($this->cellSizeNumber, $this->cellHeight, !isset($totals->target->socket_qty)? "" : number_format($totals->target->socket_qty), 1, 'L', 1, 0, '', '', true, 0, false, true, 0, 'M');
        $this->pdf->MultiCell($this->cellSizeNumber, $this->cellHeight, mixed_number($totals->target->physical_cores), 1, 'L', 1, 0, '', '', true, 0, false, true, 0, 'M');
        if ($this->environment->isConverged()) {
            $this->pdf->MultiCell($this->cellSizeText, $this->cellHeight, number_format($totals->storage->targetTotal, 2), 1, 'L', 1, 0, '', '', true, 0, false, true, 0, 'M');
            $this->pdf->MultiCell($this->cellSizeNumber, $this->cellHeight, number_format($totals->iops->targetTotal, 0), 1, 'L', 1, 0, '', '', true, 0, false, true, 0, 'M');
        }
        $this->pdf->MultiCell($this->cellSizeNumber , $this->cellHeight, number_format(round($totals->target->utilRam, 0)), 1, 'L', 1, 0, '', '', true, 0, false, true, 0, 'M');
        $this->pdf->MultiCell($this->cellSizeNumber2 , $this->cellHeight, !isset($totals->target->utilRpm) ? "" : number_format(round($totals->target->utilRpm), 0), 1, 'L', 1, 0, '', '', true, 0, false, true, 0, 'M');
        $this->pdf->MultiCell($this->cellSizeNumber, $this->cellHeight, number_format($totals->target->vms), 1, 'L', 1, 0, '', '', true, 0, false, true, 0, 'M');
        $this->pdf->MultiCell($this->cellSizeNumber, $this->cellHeight, floatval(number_format($totals->target->total_cores, 1)), 1, 'L', 1, 0, '', '', true, 0, false, true, 0, 'M');
        $this->pdf->MultiCell($this->cellSizeNumber , $this->cellHeight, number_format(round(!$this->environment->isCloud() ? $totals->existing->comparisonComputedRam : $totals->target->utilRam, 0)), 1, 'L', 1, 0, '', '', true, 0, false, true, 0, 'M');
        $this->pdf->MultiCell($this->cellSizeNumber2 , $this->cellHeight, !isset($totals->existing->comparisonComputedRpm) ? "" : number_format(round($totals->existing->comparisonComputedRpm), 0), 1, 'L', 1, 0, '', '', true, 0, false, true, 0, 'M');
        $this->pdf->Ln();
        $this->pdf->Ln();

        return $this;
    }
}