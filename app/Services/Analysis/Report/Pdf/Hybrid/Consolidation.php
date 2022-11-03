<?php
/**
 *
 */

namespace App\Services\Analysis\Report\Pdf\Hybrid;


use App\Models\Project\Environment;
use App\Models\Project\PrecisionPDF;
use Auth;

class Consolidation
{
    use ConsolidationSummaryTrait;
    use ConsolidationConvergedTrait;
    use ConsolidationDetailTrait;

    /**
     * @var PrecisionPDF
     */
    protected $pdf;

    /**
     * @var Environment
     */
    protected $environment;

    /**
     * @var Environment
     */
    protected $existingEnvironment;
    
    /**
     * @var 
     */
    protected $viewCpm, $header, $displayWork, $displayLoc, $displayEnv, $noDetails, $numMappedColumns, $cellSizeNumber, $cellSizeNumber2, $cellSizeText, $cellSizeText2, $headerHeight1, $headerHeight2, $num_headers, $cellHeight, $pageHeight, $bg1, $bg2, $bg1Target, $bg2Target, $fill, $flip, $colorTarget, $startX, $startY, $height, $tempHeight, $consolidation, $consIndex;

    /**
     * Draw a given Environment's consolidation detail table on a PDF document
     *
     * @param PrecisionPDF $pdf
     * @param $header
     * @param Environment $environment
     * @param Environment $existingEnvironment
     */
    public function drawConsolidation(PrecisionPDF $pdf, Environment $environment, Environment $existingEnvironment)
    {
        $this->_setInitialVariables($pdf, $environment, $existingEnvironment);
        $this->_drawConsolidationHeader();

        // Draw the various parts of the table
        $this->_drawSummaryHeaders()
            ->_drawSummaryExisting()
            ->_drawSummaryTarget();

        $this->pdf->__widthAddition = 0;

        $this
            ->_drawConsolidationsDetail()
            ->_drawStorageConsolidationDetail()
            ->_drawIopsConsolidationDetail()
            ->_drawConvergedAdditionalDetail()
        ;

        $this->pdf->__widthAddition = 0;

        return $this;
    }
    
    /**
     * @param PrecisionPDF $pdf
     * @param Environment $environment
     * @return string
     */
    protected function _drawConsolidationHeader()
    {
        $this->pdf->AddPage();
        $this->pdf->SetFont('', '', 12);
        $html =
            '<h1 style="font-weight: 500">' . $this->environment->name . ' Consolidation Analysis</h1>
            <table style="border-top: 1px solid #000000; width:100%"><tr><td></td></tr></table>
            <br/>';

        $this->pdf->writeHTMLCell(0, 0, '', '', $html, 0, 1, 0, true, '', true);
        $this->pdf->SetFont('', '', 7);


        $this->pdf->SetDrawColor(0, 0, 0);
        $this->pdf->SetLineWidth(0.1);
        $this->pdf->SetFont('', 'B');
        $this->pdf->fillColor($this->bg1);

        return $html;
    }

    /**
     * @param PrecisionPDF $pdf
     * @param $header
     * @param Environment $environment
     * @param Environment $existingEnvironment
     * @return $this
     */
    protected function _setInitialVariables(PrecisionPDF $pdf, Environment $environment, Environment $existingEnvironment)
    {
        $this->header = ['Location', 'Environment', 'Workload', 'VM ID', 'Old VM Total Cores', 'New VM Total Cores', 'VM RAM @ ' . $existingEnvironment->ram_utilization . '%', 'Old VM CPM @ ' . $existingEnvironment->cpu_utilization . '%',
            'New VM CPM @ ' . $existingEnvironment->cpu_utilization . '%'];

        $this->pdf = $pdf;
        $this->environment = $environment;
        $this->viewCpm = Auth::user()->user->view_cpm || $this->environment->isCloud();
        $this->existingEnvironment = $existingEnvironment;
        $this->displayWork = $this->displayLoc = $this->displayEnv = false;
        foreach ($this->environment->analysis->consolidations as $this->consolidation) {
            foreach ($this->consolidation->servers as $server) {
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

        $this->cellSizeNumber = 190 / (18);
        $this->cellSizeNumber2 = 190 / 17;
        $remainingSpace = (190 - $this->cellSizeNumber * (5) - $this->cellSizeNumber2 * 2);
        $textBlockSize = $remainingSpace / (8 + 2 * 2 + 2);
        $this->cellSizeText = $textBlockSize * 2;
        $this->cellSizeText2 = $textBlockSize * 3;

        $this->headerHeight1 = $this->environment->isConverged() ? 11 : 11;
        $this->headerHeight2 = $this->environment->isConverged() ? 11 : 8;
        $this->num_headers = count($this->header);
        $this->cellHeight = 3.55;
        $this->pageHeight = 260;

        $this->bg1 = array(238, 236, 225);
        $this->bg2 = array(250, 191, 143);
        $this->bg1Target = array(218, 216, 205);
        $this->bg2Target = array(230, 171, 123);

        // Data
        $this->fill = 0;
        $this->flip = false;

        return $this;
    }
}