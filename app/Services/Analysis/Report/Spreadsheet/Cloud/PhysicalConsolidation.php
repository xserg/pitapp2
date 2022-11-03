<?php

namespace App\Services\Analysis\Report\Spreadsheet\Cloud;

class PhysicalConsolidation extends AbstractCloudConsolidation
{
    /**
     * @var array
     */
    protected $spreadsheetTemplate = [
        'path' => '/resources/templates/consolidation/cloud/physical.xlsx',
        // Range must contain template data
        'range' => [
            'rowCount' => 200,
            'endColumn' => 'Z'
        ],
        'columnSize' => 19,
        'zoomScale' => 71
    ];

    /**
     * @param Environment $environment
     * @return array
     */
    protected function getExistingTotals($environment)
    {
        return [
            'servers' => $this->formatValue(round($environment->analysis->totals->existing->servers)),
            'cores' => $this->formatValue(round($environment->analysis->totals->existing->total_cores)),
            'ram' => $this->formatValue(round($environment->analysis->totals->existing->ram)),
            'ram_util' => $this->formatValue(round($environment->analysis->totals->existing->computedRam)),
        ];
    }

    /**
     * @param Environment $environment
     * @return array
     */
    protected function getTargetTotals($environment)
    {
        return [
            'servers' => $this->formatValue(round($environment->analysis->totals->existing->servers)),
            'cores' => $this->formatValue(round($environment->analysis->totals->target->total_cores)),
            'ram' => $this->formatValue(round($environment->analysis->totals->target->ram)),
            'ram_util' => $this->formatValue(round($environment->analysis->totals->target->utilRam))
        ];
    }
}
