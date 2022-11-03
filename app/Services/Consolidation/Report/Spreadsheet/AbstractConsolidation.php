<?php


namespace App\Services\Consolidation\Report\Spreadsheet;


use App\Models\Project\Environment;
use App\Services\Consolidation\Report\HasDataMapping;

abstract class AbstractConsolidation
{
    use HasDataMapping;

    public function formatConsolidation(Environment $existingEnvironment, Environment $targetEnvironment)
    {
        $analysis = json_decode(json_encode($targetEnvironment->analysis));

        $formattedConsolidations = $this->getFormattedConsolidations($analysis);

        // New format for spreadsheet output.
        return (object)[
            'spreadsheetTitle' => $existingEnvironment->project->title . '-consolidations',
            'worksheetTitle' => $targetEnvironment->name,
            'existingDetails' => $this->getExistingDetails($existingEnvironment),
            'targetDetails' => $this->getTargetDetails($targetEnvironment),
            'consolidations' => $formattedConsolidations,
            'template' => $this->spreadsheetTemplate,
            'totals' => [
                'existing' => $this->getExistingTotals($analysis),
                'target' => $this->getTargetTotals($analysis)
            ]
        ];
    }

    /**
     * @param Environment $environment
     * @return array
     */
    protected function getExistingDetails($environment)
    {
        return [];
    }

    /**
     * @param Environment $environment
     * @return array
     */
    protected function getTargetDetails($environment)
    {
        $details = [
            'name' => $environment->name,
            'updated_at' => (string)$environment->updated_at,
        ];

        return $details;
    }

    protected function formatValue($value, $decimals = 0, $isMoney = false ) {
        if ($isMoney) {
            if ($value) {
                // Remove dollar sign if exists
                $value = (float)str_replace('$', '', $value);
                // Set number in money format
                return '$' . number_format($value, $decimals, '.', ',');
            } else {
                return 'N/A';
            }
        }
        return $value  || is_numeric($value) ? ' ' . number_format($value, $decimals) : 'N/A';
    }

    protected function formatValuesByMap($map, $data, $method = 'formatValue')
    {
        foreach ($map as $key => $map_params) {
            $params = array_merge([$data[$key]], $map_params);

            $data[$key] = call_user_func_array([$this, $method], $params);
        }

        return $data;
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