<?php

namespace App\Services\Analysis\Report\Spreadsheet\Cloud;

class VmConsolidation extends AbstractCloudConsolidation
{
    /**
     * @var array
     */
    protected $spreadsheetTemplate = [
        'path' => '/resources/templates/consolidation/cloud/vm.xlsx',
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
            'vm_servers' => $this->formatValue(round($environment->analysis->totals->existing->vms)),
            'vm_cores' => $this->formatValue($environment->analysis->totals->existing->total_cores),
            'vm_cores_util' => $this->formatValue($environment->analysis->totals->existing->computedCores),
            'vm_ram' => $this->formatValue(round($environment->analysis->totals->existing->ram)),
            'vm_ram_util' => $this->formatValue(round($environment->analysis->totals->existing->computedRam))

        ];
    }

    /**
     * @param Environment $environment
     * @return array
     */
    protected function getTargetTotals($environment)
    {
        return [
            'vm_servers' => $this->formatValue(round($environment->analysis->totals->target->vms)),
            'vm_cores' => $this->formatValue(round($environment->analysis->totals->target->total_cores)),
            'vm_cores_util' => $this->formatValue(round($environment->analysis->totals->target->total_cores)),
            'vm_ram' => $this->formatValue(round($environment->analysis->totals->target->ram)),
            'vm_ram_util' => $this->formatValue(round($environment->analysis->totals->target->utilRam))
        ];
    }
}
