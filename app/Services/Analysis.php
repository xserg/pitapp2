<?php
/**
 *
 */

namespace App\Services;


use App\Exceptions\AnalysisException;
use App\Models\Project\AnalysisResult;
use App\Models\Project\Environment;
use App\Models\Project\Project;
use App\Services\Analysis\ProjectAnalyzer;
use App\Services\Analysis\Report\AbstractReport;
use App\Services\Analysis\Report\Pdf;
use App\Services\Analysis\Report\Web;
use App\Services\Analysis\Report\WordDoc;
use App\Services\Analysis\Report\Spreadsheet;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use ImagickException;
use PhpOffice\PhpWord\Exception\Exception;

class Analysis
{
    const REPORT_FORMAT_WEB = 'Web';
    const REPORT_FORMAT_PDF = 'Pdf';
    const REPORT_FORMAT_WORDDOC = 'WordDoc';
    const REPORT_FORMAT_SPREADSHEET = 'Spreadsheet';

    /**
     * @var array
     */
    protected $_reportServices = [
        self::REPORT_FORMAT_WEB => Web::class,
        self::REPORT_FORMAT_PDF => Pdf::class,
        self::REPORT_FORMAT_WORDDOC => WordDoc::class,
        self::REPORT_FORMAT_SPREADSHEET => Spreadsheet::class
    ];

    /**
     * @param Project $project
     * @return AnalysisResult
     */
    public function runAnalysis(Project $project, $targetId)
    {
        ini_set("max_execution_time", 900);
        /** @var Analysis\ProjectAnalyzer $projectAnalyzerService */
        $projectAnalyzerService = resolve(Analysis\ProjectAnalyzer::class);

        return $projectAnalyzerService->analyze($project, $targetId);
    }

    /**
     * @param $projectId
     * @return AnalysisResult
     */
    public function analyze($projectId, $targetId = false)
    {
        $t0 = microtime(true);
        $project = $this->getProjectForAnalysis($projectId);
        Log::info("  Time to Analysis::getProjectForAnalysis: " . (microtime(true) - $t0) * 1000.0 . "ms");
        $t0 = microtime(true);
        $rval = $this->runAnalysis(
            $project,
            $targetId
        );
        Log::info("  Time to Analysis::runAnalysis: " . (microtime(true) - $t0) * 1000.0 . "ms");
        return $rval;
    }

    /**
     * @param AnalysisResult $analysisResult
     * @param $format
     * @return mixed|JsonResponse
     * @throws AnalysisException
     */
    public function generateReport(AnalysisResult $analysisResult, $format)
    {
        $reportServiceClass = $this->_reportServices[$format] ?? false;

        if (!$reportServiceClass) {
            throw new AnalysisException("Invalid report format: {$format}");
        }

        /** @var AbstractReport|Web|Pdf|WordDoc|Spreadsheet $reportService */
        $reportService = resolve($reportServiceClass);

        try {
            return $reportService->generate($analysisResult);
        } catch (ImagickException $e) {
            Log::error($e);
            abort(500, $e->getMessage());
        } catch (Exception $e) {
            Log::error($e);
            abort(500, $e->getMessage());
        }

        return abort(500);
    }

    /**
     * @param $projectId
     * @return Project|array|\Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model|null|\stdClass
     */
    public function getProjectForAnalysis($projectId)
    {
        $project = Project::where("id", $projectId)
            ->with(['environments' => function($query) {
                $query->orderBy('is_existing', 'DESC')->orderBy('id', 'asc');
            }, "user",
                "environments.serverConfigurations",
                "environments.serverConfigurations.manufacturer", "environments.provider",
                "environments.serverConfigurations.chassis",
                "environments.serverConfigurations.chassis.manufacturer",
                "environments.serverConfigurations.chassis.model",
                "environments.serverConfigurations.interconnect",
                "environments.serverConfigurations.interconnect.manufacturer",
                "environments.serverConfigurations.interconnect.model"
            ])
            ->first();

        if (!$project || !$project->id) {
            throw new AnalysisException("Invalid project id!");
        }

        return $project;
    }
}
