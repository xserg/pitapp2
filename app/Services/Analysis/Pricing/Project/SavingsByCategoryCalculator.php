<?php
/**
 *
 */

namespace App\Services\Analysis\Pricing\Project;


use App\Services\Currency\CurrencyConverter;
use SVGGraph;
use JangoBrick\SVG\SVGImage;
use App\Models\Project\Environment;
use App\Models\Project\Project;

class SavingsByCategoryCalculator extends AbstractSavingsCalculator
{
    /**
     * @param Project $project
     * @param Environment $existingEnvironment
     * @param Environment $targetEnvironment
     * @return object
     */
    public function calculateSavingsByCategory(Project $project, Environment $existingEnvironment, Environment $targetEnvironment)
    {
        $softwares = $project->softwareByNames;
        $values = [
            [],
            []
        ];

        if (!!$existingEnvironment->total_hardware_maintenance || !!$targetEnvironment->total_hardware_maintenance) {
            $name = $this->savingsCategoryName("Hardware\nMaintenance\nCost");
            $values[0][$name] = $existingEnvironment->total_hardware_maintenance;
            $values[1][$name] = $targetEnvironment->total_hardware_maintenance;
        }
        if (!!$existingEnvironment->total_system_software_maintenance || !!$targetEnvironment->total_system_software_maintenance) {
            $name = $this->savingsCategoryName("System Software\nMaintenance\nCost");
            $values[0][$name] = $existingEnvironment->total_system_software_maintenance;
            $values[1][$name] = $targetEnvironment->total_system_software_maintenance;
        }
        if (!!$existingEnvironment->total_storage_maintenance || !!$targetEnvironment->total_storage_maintenance) {
            $name = $this->savingsCategoryName("Storage\nMaintenance\nCost");
            $values[0][$name] = $existingEnvironment->total_storage_maintenance;
            $values[1][$name] = $targetEnvironment->total_storage_maintenance;
        }
        foreach ($softwares as $software) {
            $supportCostExisting = $this->supportCostNoFeatures($software, $existingEnvironment->id);
            $supportCostTarget = $this->supportCostNoFeatures($software, $targetEnvironment->id);
            $licenseCostExisting = $this->licenseCostNoFeatures($software, $existingEnvironment->id);
            $licenseCostTarget = $this->licenseCostNoFeatures($software, $targetEnvironment->id);
            if ($licenseCostExisting != 0 || $licenseCostTarget != 0) {
                $name = $this->savingsCategoryName($software->name . " License Cost");
                $values[0][$name] = $licenseCostExisting;
                $values[1][$name] = $licenseCostTarget;
            }
            if ($supportCostExisting != 0 || $supportCostTarget != 0) {
                $name = $this->savingsCategoryName($software->name . " Support Cost");
                $values[0][$name] = $supportCostExisting;
                $values[1][$name] = $supportCostTarget;
            }
            foreach ($software->features as $feature) {
                $supportCostExisting = $this->supportCostNoFeatures($feature, $existingEnvironment->id);
                $supportCostTarget = $this->supportCostNoFeatures($feature, $targetEnvironment->id);
                $licenseCostExisting = $this->licenseCostNoFeatures($feature, $existingEnvironment->id);
                $licenseCostTarget = $this->licenseCostNoFeatures($feature, $targetEnvironment->id);
                if ($licenseCostExisting != 0 || $licenseCostTarget != 0) {
                    $name = $this->savingsCategoryName($feature->name . " License Cost");
                    $values[0][$name] = $licenseCostExisting;
                    $values[1][$name] = $licenseCostTarget;
                }
                if ($supportCostExisting != 0 || $supportCostTarget != 0) {
                    $name = $this->savingsCategoryName($feature->name . " Support Cost");
                    $values[0][$name] = $supportCostExisting;
                    $values[1][$name] = $supportCostTarget;
                }
            }
        }


        if (!!$existingEnvironment->total_fte_cost || !!$targetEnvironment->total_fte_cost) {
            $name = $this->savingsCategoryName("FTE\nCost");
            $values[0][$name] = $existingEnvironment->total_fte_cost;
            $values[1][$name] = $targetEnvironment->total_fte_cost;
        }

        if (!!$existingEnvironment->power_cost || !!$targetEnvironment->power_cost) {
            $name = $this->savingsCategoryName("Power/Cooling\nCost");
            $values[0][$name] = $existingEnvironment->power_cost;
            $values[1][$name] = $targetEnvironment->power_cost;
        }

        $maxVal = 0;
        foreach ($values as $value) {
            if ($value && $maxVal < max($value))
                $maxVal = max($value);
        }

        $settings = array(
            "graph_title" => "Costs by Category",
            "graph_title_font_size" => 20,
            "line_dataset" => 1,
            "back_stroke_width" => 0,
            "back_colour" => "none",
            "show_axis_v" => false,
            "show_axis_h" => false,
            "show_grid_v" => false,
            "pad_bottom" => 50,
            "axis_text_callback_y" => function ($v) {
                return $v < 0 ? '-' . CurrencyConverter::convertAndFormat(abs($v)) :  CurrencyConverter::convertAndFormat($v);
            },
            "bar_width" => 20,
            "legend_entries" => array($existingEnvironment->name . ' Cost', $targetEnvironment->name . ' Cost', 'Savings'),
            "stroke_width" => 0,
            "legend_position" => "outer bottom left",
            "legend_columns" => 3,
            "legend_stroke_width" => 0,
            "legend_back_color" => "none",
            "legend_shadow_opacity" => 0,
            "marker_size" => 0,
            "line_stroke_width" => 3,
            'show_data_labels' => true,
            'data_label_type' => 'plain',
            'data_label_position' => 'above',
            'data_label_font_size' => 9,
            'data_label_callback' => function ($dataset, $key, $v) {
                if (abs($v) > 1000000) {
                    $v /= 1000000;
                    return $v < 0 ? '-' . CurrencyConverter::convertAndFormat(abs($v)) . ' M' : CurrencyConverter::convertAndFormat($v) . ' M';
                } else if (abs($v) > 1000) {
                    $v /= 1000;
                    return $v < 0 ? '-' . CurrencyConverter::convertAndFormat(abs($v)) . ' K' : CurrencyConverter::convertAndFormat($v) . ' K';
                }
                return $v < 0 ? '-' . CurrencyConverter::convertAndFormat(abs($v)) : CurrencyConverter::convertAndFormat($v);
            }
        );

        $returnVal = (object)[
            'settings' => $settings,
            'values' => $values
        ];
        return $returnVal;
    }

    public function fetchSavingsByCategoryGraph($values, $settings)
    {
        $colors = [
            'rgb(91,155,213)',
            'rgb(237,125,49)'//,
        ];
        $graph = new SVGGraph(700, 400, $settings);
        $graph->Values($values);
        $graph->Colours($colors);
        return $graph->fetch('GroupedBarGraph');
    }
}