<?php


namespace App\Services\Consolidation\Report\Spreadsheet;


class HybridConsolidation extends AbstractConsolidation
{
    /**
     * @var array
     */
    protected $spreadsheetTemplate = [
        'path' => '/resources/templates/consolidation-analysis/physical_vm.xlsx',
        // Range must contain template data
        'range' => [
            'rowCount' => 200,
            'endColumn' => 'K'
        ],
        'columnSize' => 19,
        'zoomScale' => 71
    ];

    /**
     * @param $analysis
     * @return array
     */
    protected function getExistingTotals($analysis)
    {
        $existing_totals = $analysis->totals->existing;

        return [
            // Physical
            'servers' => $this->formatValue($existing_totals->servers), // Servers
            'socketQty' => $this->formatValue($existing_totals->socket_qty), // Processors
            'physical_cores' =>  $this->formatValue($existing_totals->physical_cores), // Total Cores,
            'ram' => $this->formatValue($this->getDataByPath($existing_totals, ['physical_ram'])), // RAM (GB) @ Util
            'rpm' => $this->formatValue($existing_totals->rpm), // CPM @ 100%

            // VM
            'vms' => $this->formatValue($existing_totals->vms), // VMs
            'vm_cores' => $this->formatValue($existing_totals->vm_cores), // Total cores
            'computedRam' => $this->formatValue($existing_totals->computedRam), // RAM (GB) @ Util
            'computedRpm' => $this->formatValue($existing_totals->computedRpm), // CPM @ Util
        ];
    }

    /**
     * @param $analysis
     * @return array
     */
    protected function getTargetTotals($analysis)
    {
        $target_totals = $analysis->totals->target;
        $existing_totals = $analysis->totals->existing;

        return [
            // Physical
            'servers' => $this->formatValue($target_totals->servers), // Servers
            'socket_qty' => $this->formatValue($target_totals->socket_qty), // Processors
            'physical_cores' =>  $this->formatValue($target_totals->physical_cores), // Total Cores,
            'ram' => $this->formatValue($target_totals->utilRam), // RAM (GB) @ Util
            'rpm' => $this->formatValue($target_totals->utilRpm), // CPM @ 100%

            // VM
            'vms' => $this->formatValue($target_totals->vms), // VMs
            'vm_cores' => $this->formatValue($target_totals->total_cores), // Total cores
            'computedRam' => $this->formatValue($existing_totals->comparisonComputedRam), // RAM (GB) @ Util
            'computedRpm' => $this->formatValue($existing_totals->comparisonComputedRpm), // CPM @ Util
        ];
    }

    /**
     * @param $analysis
     * @return array
     */
    protected function getFormattedConsolidations($analysis)
    {
        $rows = [];
        $host_id = 0;
        $data_map = [
            'group' => '', // Migration Group
            'host_id' => '', // Host ID/Serial #
            'vm_id' => ['vm_id'], // VM Id
            'location' => ['location'], // Location
            'environment' => ['environment_name'], // Environment
            'workload' => ['workload_type'], // Workload
            'vm_cores' => ['vm_cores'], // Old VM Cores
            'new__vm_cores' => ['comparison_server', 'vm_cores'], // New VM Cores
            'computedRpm' => ['computedRpm'], // Old VM CPM @ Util
            'newComputedRpm'=> ['comparison_server', 'computedRpm'], // New VM CPM
            'computedRam' => ['computedRam'], // VM RAM @ Util
        ];

        $format_data_map = [
            'newComputedRpm' => [],
            'computedRam' => [],
        ];

        $sub_totals_label_style = '<&fill:FFeaf0dd;font-size:12;font-weight:true;&>';
        $sub_totals_style = '<&fill:FFeaf0dd;&>';
        $consolidation_label_style = '<&font-size:12;font-weight:true;&>';

        foreach ($analysis->consolidations as $group => $consolidation) {
            foreach ($consolidation->servers as $server) {
                $host_id += 1;

                $data_map['group'] = $group + 1;
                $data_map['host_id'] = $host_id;

                $data = $this->getDataByPathAsMap($server, $data_map, 'N/A');

                $rows[] = $this->formatValuesByMap($format_data_map, $data);
            }

            $sub_totals = [
                $sub_totals_label_style . 'Sub Totals',
                $sub_totals_style . $this->formatValue($this->getDataByPath($consolidation, ['computedRpmTotal'])),
                $sub_totals_style . $this->formatValue($this->getDataByPath($consolidation, ['comparisonComputedRpmTotal'])),
                $sub_totals_style . $this->formatValue($this->getDataByPath($consolidation, ['computedRamTotal'])),
            ];

            $rows[] = array_merge(
                array_fill(0, count($data_map) - count($sub_totals), null),
                $sub_totals
            );

            $rows[] = [];

            $consolidation_headers = $this->getConsolidationHeaders();

            $rows[] = array_merge(
                array_fill(0, count($data_map) - count($consolidation_headers), null),
                $consolidation_headers
            );

            foreach ($consolidation->targets as $index => $target) {
                $row = array_merge(
                    array_fill(0, count($data_map) - count($consolidation_headers), null),
                    [
                        $this->getManufacturer($target),
                        $this->getDataByPath($target, ['server', 'name'], 'N/A'),
                        $this->getDataByPath($target, ['processor', 'name'], 'N/A'),
                        $this->getDataByPath($target, ['processor', 'ghz'], 'N/A'),
                        $this->getDataByPath($target, ['processor', 'socket_qty'], 'N/A'),
                        $this->getDataByPath($target, ['processor', 'total_cores'], 'N/A'),
                        $this->formatValue($this->getDataByPath($target, ['utilRpm'], 'N/A')),
                        $this->formatValue($this->getDataByPath($target, ['utilRam'])),
                    ]
                );

                if ($index === 0) {
                    $row[2] = $consolidation_label_style . 'Consolidation Target';
                }

                $rows[] = $row;
            }

            $rows[] = [];
        }

        return ['data' => $rows];
    }

    /**
     * @return array
     */
    protected function getConsolidationHeaders()
    {
        return collect([
            'Manufacturer',
            'Model',
            'Processor Type',
            'GHz',
            'Processors',
            'Total Cores',
            'CPM',
            'RAM',
        ])->map(function($value) {
            return '<&fill:FFc3d69b;&>' . $value;
        })->toArray();
    }
}