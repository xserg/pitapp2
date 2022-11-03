<?php
/**
 *
 */

namespace App\Models\Project;


use App\Services\Analysis\Environment\Target\AbstractTargetAnalyzer;

class AnalysisResult
{
    /**
     * @var Project
     */
    protected $_project;

    /**
     * @var Environment
     */
    protected $_existingEnvironment;

    /**
     * @var Environment
     */
    protected $_bestTargetEnvironment;

    /**
     * @return Project
     */
    public function getProject()
    {
        return $this->_project;
    }

    /**
     * @param Project $project
     * @return AnalysisResult
     */
    public function setProject(Project $project)
    {
        $this->_project = $project;
        return $this;
    }

    /**
     * @param Environment $bestTargetEnvironment
     * @return AnalysisResult
     */
    public function setBestTargetEnvironment(Environment $bestTargetEnvironment)
    {
        $this->_bestTargetEnvironment = $bestTargetEnvironment;
        return $this;
    }

    /**
     * @return Environment
     */
    public function getBestTargetEnvironment()
    {
        return $this->_bestTargetEnvironment;
    }

    /**
     * @param Environment $existingEnvironment
     * @return AnalysisResult
     */
    public function setExistingEnvironment(Environment $existingEnvironment)
    {
        $this->_existingEnvironment = $existingEnvironment;
        return $this;
    }

    /**
     * @return Environment
     */
    public function getExistingEnvironment()
    {
        return $this->_existingEnvironment;
    }
}