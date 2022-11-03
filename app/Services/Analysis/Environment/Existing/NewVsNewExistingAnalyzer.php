<?php
/**
 *
 */

namespace App\Services\Analysis\Environment\Existing;


use App\Models\Project\Environment;

class NewVsNewExistingAnalyzer extends AbstractExistingAnalyzer
{
    /**
     * @param Environment $existingEnvironment
     * @return $this
     */
    public function afterCalculateCosts(Environment $existingEnvironment)
    {
        parent::afterCalculateCosts($existingEnvironment);

        return $this->setServerConfigurationData($existingEnvironment);
    }

    /**
     * @param Environment $environment
     * @return $this|AbstractTargetAnalyzer
     */
    public function setServerConfigurationData(Environment $environment)
    {
        if ($environment->isConverged()) {
            $this->setConvergedServerConfigurationData($environment);
        }

        return $this;
    }
}