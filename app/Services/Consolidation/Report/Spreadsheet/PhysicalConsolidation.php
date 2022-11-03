<?php


namespace App\Services\Consolidation\Report\Spreadsheet;


class PhysicalConsolidation extends AbstractConsolidation
{
    /**
     * @var array
     */
    protected $spreadsheetTemplate = [
        'path' => '/resources/templates/consolidation-analysis/physical.xlsx',
        // Range must contain template data
        'range' => [
            'rowCount' => 200,
            'endColumn' => 'M'
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
            'servers' => $existing_totals->servers, // Servers
            'socket_qty' => $existing_totals->socket_qty, // Processors
            'total_cores' => $this->formatValue($existing_totals->total_cores), // Total Cores
            'ram' => $this->formatValue($existing_totals->ram), // RAM (GB) @ Util
            'computedRam' => $this->formatValue($existing_totals->computedRam), // RAM @ Util
            'rpm' => $this->formatValue($existing_totals->rpm), // CPM @ 100%
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
        $cpuUtilization = data_get($analysis, 'totals.existing.cpuUtilization');

        return [
            'servers' => $target_totals->servers, // Servers
            'socket_qty' => $target_totals->socket_qty, // Processors
            'total_cores' => $this->formatValue($target_totals->total_cores), // Total Cores
            'ram' => $this->formatValue($target_totals->utilRam), // RAM (GB) @ Util
            'computedRam' => $this->formatValue($target_totals->utilRam), // $this->formatValue($target_totals->computedRam), // RAM @ Util
            'rpm' => $this->formatValue($target_totals->utilRpm), // CPM @ 100%
            'computedRpm' => $this->formatValue($target_totals->utilRpm * ($cpuUtilization / 100)) // $this->formatValue($target_totals->computedRpm), // CPM @ Util
        ];
    }

    /**
     * @param $analysis
     * @return array
     */
    protected function getFormattedConsolidations($analysis)
    {
        $cpuUtilization = data_get($analysis, 'totals.existing.cpuUtilization');
        $rows = [];
        $host_id = 0;
        $data_map = [
            'group' => '', // Consolidation Group
            'host_id' => '', // Host ID/Serial #
            'location' => ['location'], // Location
            'environment' => ['environment_name'], // Environment
            'workload' => ['workload_type'], // Workload
            'manufacturer' => ['manufacturer', 'name'], // Manufacturer
            'model' => ['processor', 'model_name'], // Model
            'processor_type' => ['processor', 'name'], // Processor Type
            'ghz'=> ['processor', 'ghz'], // GHz
            'processor_qty' => ['processor', 'socket_qty'], // Processor QTY
            'total_cores' => ['processor', 'total_cores'], // Total cores
            'ram' => ['baseRam'], // RAM (GB) @ Util
            'rpm' => ['baseRpm'], // CPM @ Util
        ];

        $format_data_map = [
            'ram' => [],
            'rpm' => [],
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

                if ($data['rpm']) {
                    $data['rpm'] *= ($cpuUtilization / 100);
                }

                $rows[] = $this->formatValuesByMap($format_data_map, $data);
            }

            $rpmTotal = $this->getDataByPath($consolidation, ['rpmTotal']);
            $rpmTotal = ($rpmTotal) ? $rpmTotal * ($cpuUtilization / 100) : $rpmTotal;
            $sub_totals = [
                $sub_totals_label_style . 'Sub Totals',
                $sub_totals_style . $this->formatValue($this->getDataByPath($consolidation, ['ramTotal'])),
                $sub_totals_style . $this->formatValue($rpmTotal),
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
                        $this->getDataByPath($target, ['processor', 'model_name'], 'N/A'),
                        $this->getDataByPath($target, ['processor', 'name'], 'N/A'),
                        $this->getDataByPath($target, ['processor', 'ghz'], 'N/A'),
                        $this->getDataByPath($target, ['processor', 'socket_qty'], 'N/A'),
                        $this->getDataByPath($target, ['processor', 'total_cores'], 'N/A'),
                        $this->formatValue($this->getDataByPath($target, ['utilRam'])),
                        $this->formatValue($this->getDataByPath($target, ['utilRpm'], 'N/A')),
                    ]
                );

                if ($index === 0) {
                    $row[4] = $consolidation_label_style . 'Consolidation Target';
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
            'RAM',
            'CPM',
        ])->map(function($value) {
            return '<&fill:FFc3d69b;&>' . $value;
        })->toArray();
    }

    /**
     * @param $target
     * @return string|null
     */
    protected function getManufacturer($target)
    {
        if (!property_exists($target, 'instance_type')) {
            return $this->getDataByPath($target, ['manufacturer' ,'name'], 'N/A');
        }

        switch ($target->instance_type) {
            case 'EC2':
            case 'RDS':
                return 'AWS';
            case 'Azure':
                return 'Azure';
            case 'ADS':
                return $target->database_type;
            case 'Google':
                return 'Google';
            case 'IBMPVS':
                return 'IBM PVS';
            default:
                return 'N/A';
        }
    }
}
