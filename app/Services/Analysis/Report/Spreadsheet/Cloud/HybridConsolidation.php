<?php

namespace App\Services\Analysis\Report\Spreadsheet\Cloud;

class HybridConsolidation extends AbstractCloudConsolidation
{
    /**
     * @var array
     */
    protected $spreadsheetTemplate = [
        'path' => '/resources/templates/consolidation/cloud/hybrid.xlsx',
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
    protected function getTargetTotals($environment)
    {
        return [
            'physical_servers' => $this->formatValue(round($environment->analysis->totals->target->servers)),
            'physical_cores' => $this->formatValue(round($environment->analysis->totals->target->physical_cores)),
            'vm_servers' => $this->formatValue(round($environment->analysis->totals->target->vms)),
            'vm_cores' => $this->formatValue(round($environment->analysis->totals->target->total_cores)),
            'vm_cores_util' => $this->formatValue(round($environment->analysis->totals->target->total_cores)),
            'vm_ram' => $this->formatValue(round($environment->analysis->totals->target->ram)),
            'vm_ram_util' => $this->formatValue(round($environment->analysis->totals->target->utilRam)),
            'physical_ram' => 'N/A'
        ];
    }

    /**
     * @param Environment $environment
     * @return array
     */
    protected function getExistingDetails($environment)
    {
        $details = [
            'storage_capacity' => $this->formatValue($environment->useable_storage),
            'storage_type' => $environment->driveType?: 'N/A',
            'storage_cost' => $this->formatValue(round($environment->storage_maintenance) * $environment->project->support_years, 0, true),
            'network_cost' => $this->formatValue(round($environment->network_costs), 0, true),
            'bandwidth' => 'N/A',
            'physical_cores' => round($environment->physical_cores)
        ];

        return $details;
    }
}
