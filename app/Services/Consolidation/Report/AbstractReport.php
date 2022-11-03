<?php


namespace App\Services\Consolidation\Report;


use App\Models\Project\AnalysisResult;

abstract class AbstractReport
{
    abstract public function generate(AnalysisResult $analysisResult);
}