<?php
/**
 *
 */

namespace App\Http\Controllers\Api\Project;


use App\Models\Project\AnalysisResult;
use App\Models\Project\PrecisionPDF;
use App\Models\Project\Project;
use App\Models\Project\RevenueReport;
use App\Services\Analysis;
use App\Services\Consolidation\Export\Spreadsheet\ConsolidationExportSpreadsheet;
use App\Services\Consolidation\Report\SpreadSheet;
use App\Services\Currency\CurrencyConverter;
use App\Services\Currency\RatesServiceInterface;
use App\Services\Revenue;
use Auth;
use App\Models\Project\Log;
use PhpOffice\PhpWord\Writer\WriterInterface;
use Illuminate\Support\Facades\Request;
use App\Models\Output\ExcelOutput;

class AnalysisController extends \App\Http\Controllers\Controller
{
    /**
     * Return JSON that can be used to render an online web version
     * of the analysis
     * @param $projectId
     * @return \Illuminate\Http\JsonResponse|mixed
     * @throws \App\Exceptions\AnalysisException
     */
    public function analysis($projectId)
    {
        $t0 = microtime(true);
        $this->_iniSettings();

        $this->setCurrency($projectId);
        \Illuminate\Support\Facades\Log::info("before analyze: " . (microtime(true) - $t0) * 1000.0 . "ms");

        $t1 = microtime(true);

        /** @var AnalysisResult $analysisResult */
        $analysisResult = $this->analysisService()->analyze($projectId);
        \Illuminate\Support\Facades\Log::info("Time to analyze: " . (microtime(true) - $t1) * 1000.0 . "ms");

        /** @var Revenue $revenueService */
        $revenueService = resolve(Revenue::class);

        // Update the revenue
        $t1 = microtime(true);
        $revenueService->updateProjectRevenue($analysisResult->getProject(), $analysisResult->getBestTargetEnvironment());
        \Illuminate\Support\Facades\Log::info("updateProjectRevenue: " . (microtime(true) - $t1) * 1000.0 . "ms");

        if ($revenueService->isReportMode()) {
            return false;
        }

        $t1 = microtime(true);
        $results = $this->analysisService()->generateReport($analysisResult, Analysis::REPORT_FORMAT_WEB);
        \Illuminate\Support\Facades\Log::info("generageReport: " . (microtime(true) - $t1) * 1000.0 . "ms");

        $this->_logCpmQuery();

        $rval = response()->json($results);

        \Illuminate\Support\Facades\Log::info("AnalysisController::analysis: " . (microtime(true) - $t0) * 1000.0 . "ms");

        return $rval;
    }

    public function spreadsheetAnalysis($projectId)
    {
        $this->_iniSettings();

        // Get the target id if individual anallysis
        $targetId = Request::input('targetId');

        /** @var AnalysisResult $analysisResult */
        $t0 = microtime(true);
        $analysisResult = $this->analysisService()->analyze($projectId, $targetId);
        \Illuminate\Support\Facades\Log::info("Time to analyze for spreadsheetAnalysis: " . (microtime(true) - $t0) * 1000.0 . "ms");

        $t0 = microtime(true);
        $results = $this->analysisService()->generateReport($analysisResult, Analysis::REPORT_FORMAT_SPREADSHEET);
        \Illuminate\Support\Facades\Log::info("Time to generateReport for spreadsheetAnalysis: " . (microtime(true) - $t0) * 1000.0 . "ms");
        $t0 = microtime(true);
        $output = new ExcelOutput($results);
        \Illuminate\Support\Facades\Log::info("Time to create ExcelOutput for spreadsheetAnalysis: " . (microtime(true) - $t0) * 1000.0 . "ms");

        return $output->downloadSpreadsheet('Cloud Configuration Mapping');
    }

    public function consolidationAnalysisSpreadsheet($projectId)
    {
        $this->_iniSettings();

        // Get the target id if individual anallysis
        $targetId = Request::input('targetId');

        /** @var AnalysisResult $analysisResult */
        $analysisResult = $this->analysisService()->analyze($projectId, $targetId);

        $spreadsheet = new SpreadSheet();

        $results = $spreadsheet->generate($analysisResult);

        $output = new ExcelOutput($results);

        return $output->downloadSpreadsheet('Consolidation Analysis');
    }

    /**
     * Output the analysis as a PDF
     * @param $projectId
     * @return \Illuminate\Http\JsonResponse|mixed
     * @throws \App\Exceptions\AnalysisException
     */
    public function pdfAnalysis($projectId)
    {
        $this->_iniSettings();

        $this->setCurrency($projectId);

        /** @var AnalysisResult $analysisResult */
        $analysisResult = $this->analysisService()->analyze($projectId);

        /** @var PrecisionPDF $pdf */
        $pdf = $this->analysisService()->generateReport($analysisResult, Analysis::REPORT_FORMAT_PDF);

        $this->_logCpmQuery();

        return $pdf->Output( $analysisResult->getProject()->analysis_name . '.pdf', config('analysis.pdf.output'));
    }

    /**
     * Output the analysis as a word document
     * @param $projectId
     * @return mixed
     * @throws \App\Exceptions\AnalysisException
     */
    public function wordAnalysis($projectId)
    {
        $this->_iniSettings();

        $this->setCurrency($projectId);

        /** @var AnalysisResult $analysisResult */
        $analysisResult = $this->analysisService()->analyze($projectId);

        /** @var WriterInterface $writer */
        $writer = $this->analysisService()->generateReport($analysisResult, Analysis::REPORT_FORMAT_WORDDOC);


        $file = $analysisResult->getProject()->analysis_name . '.docx';

        header('Content-Description: File Transfer');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('Content-Transfer-Encoding: binary');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Expires: 0');

        $this->_logCpmQuery();

        return $writer->save('php://output');
    }

    /**
     * @return Analysis
     */
    public function analysisService()
    {
        return resolve(Analysis::class);
    }

    /**
     * @return $this
     */
    protected function _logCpmQuery()
    {
        /** @var Revenue $revenueService */
        $revenueService = resolve(Revenue::class);

        if ($revenueService->isReportMode()) {
            return $this;
        }

        if(Auth::user()->user->view_cpm == true) {
            Auth::user()->user->ytd_queries++;
            Auth::user()->user->save();

            $log = new Log();
            $log->user_id = Auth::user()->user->id;
            $log->log_type = "cpm_query";
            $log->save();
        }

        return $this;
    }

    /**
     * @return $this
     */
    protected function _iniSettings()
    {
        ini_set('max_execution_time', 450);
        ini_set('max_input_time', 450);
        ini_set('memory_limit', '8G');

        return $this;
    }

    protected function setCurrency($projectId)
    {
        $project = Project::find($projectId);

        if (isset($project)) {
            CurrencyConverter::setDefaultTarget($project->analysis_currency);
        }
    }
}
