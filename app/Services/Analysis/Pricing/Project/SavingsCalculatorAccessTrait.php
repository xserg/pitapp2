<?php
/**
 *
 */

namespace App\Services\Analysis\Pricing\Project;


trait SavingsCalculatorAccessTrait
{
    /**
     * @return SavingsCalculator
     */
    public function projectSavingsCalculator()
    {
        return resolve(SavingsCalculator::class);
    }

    /**
     * @return SavingsByCategoryCalculator
     */
    public function projectSavingsByCategoryCalculator()
    {
        return resolve(SavingsByCategoryCalculator::class);
    }
}