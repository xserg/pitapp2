<?php
/**
 *
 */

namespace App\Services\Analysis\Report\Pdf\Hybrid;
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

            $this->_drawConsolidationDetailTargetHeader();

            foreach ($this->environment->analysis->storage as $index => $target) {
                $this->_drawConsolidationDetailTarget($index, $target, $this->bg1);
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

            $this->_drawConsolidationDetailTargetHeader();

            foreach ($this->environment->analysis->iops as $index => $target) {
                $this->_drawConsolidationDetailTarget($index, $target, $this->bg1);
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

            $this->_drawConsolidationDetailTargetHeader();

            foreach ($this->environment->analysis->converged as $index => $target) {
                $this->_drawConsolidationDetailTarget($index, $target, $this->bg1);
            }
        }

        return $this;
    }
}