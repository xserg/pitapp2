<?php
/**
 *
 */

namespace App\Services\Analysis\Pricing\Project;


use App\Models\Project\Environment;
use App\Models\Project\Project;
use App\Services\Currency\CurrencyConverter;
use SVGGraph;
use JangoBrick\SVG\SVGImage;

class SavingsCalculator extends AbstractSavingsCalculator
{
    /**
     * @param Project $project
     * @param Environment $existingEnvironment
     * @param Environment $targetEnvironment
     * @return object
     */
    public function calculateSavings(Project $project, Environment $existingEnvironment, Environment $targetEnvironment)
    {
        $savingDollars = [];
        $savingPercent = [];
        $savingDollars["Total (" . $existingEnvironment->project->support_years . " YR)\nOverall"] = $existingEnvironment->total_cost - $targetEnvironment->total_cost;
        $savingPercent["Total (" . $existingEnvironment->project->support_years . " YR)\nOverall"] = (1 - ($targetEnvironment->total_cost / ($existingEnvironment->total_cost ?: 1))) * 100;
        $targetEnvironmentPerYear = 0;
        $existingEnvironmentPerYear = 0;
        $licenseCostExisting = 0;
        $licenseCostTarget = 0;
        foreach ($project->softwares as $software) {
            $totalSupportExisting = $this->softwareSupportForEnvironment($software, $existingEnvironment->id);
            $totalSupportTarget = $this->softwareSupportForEnvironment($software, $targetEnvironment->id);
            $targetEnvironmentPerYear += $totalSupportTarget / $project->support_years;
            $existingEnvironmentPerYear += $totalSupportExisting / $project->support_years;
            $licenseCostExisting += $this->licenseForEnvironment($software, $existingEnvironment->id);
            $licenseCostTarget += $this->licenseForEnvironment($software, $targetEnvironment->id);
        }
        $targetEnvironmentPerYear += $targetEnvironment->power_cost_per_year + $targetEnvironment->storage_maintenance + $targetEnvironment->total_hardware_maintenance_per_year + $targetEnvironment->total_system_software_maintenance_per_year + $targetEnvironment->network_per_yer;
        $existingEnvironmentPerYear += $existingEnvironment->power_cost_per_year + $existingEnvironment->storage_maintenance + $existingEnvironment->total_hardware_maintenance_per_year + $existingEnvironment->total_system_software_maintenance_per_year + $existingEnvironment->network_per_yer;

        $firstYearTarget = $licenseCostTarget + $targetEnvironmentPerYear + $targetEnvironment->fte_qty * $targetEnvironment->fte_salary + $targetEnvironment->purchase_price + $targetEnvironment->migration_services + $targetEnvironment->storage_purchase_price;
        $firstYearExisting = $licenseCostExisting + $existingEnvironmentPerYear + $existingEnvironment->fte_qty * $existingEnvironment->fte_salary;
        if (!$existingEnvironment->is_existing) {
            $firstYearExisting += $existingEnvironment->purchase_price + $existingEnvironment->migration_services + $existingEnvironment->system_software_purchase_price;
        }

        // Subtract out the warranty for the first year
        if ($targetEnvironment->total_hardware_warranty_per_year && isset($targetEnvironment->total_hardware_warranty_per_year[1])) {
            $firstYearTarget -= max(0, $targetEnvironment->total_hardware_warranty_per_year[1]);
        }

        if (!$existingEnvironment->is_existing && $existingEnvironment->total_hardware_warranty_per_year && isset($existingEnvironment->total_hardware_warranty_per_year[1])) {
            $firstYearExisting -= max(0, $existingEnvironment->total_hardware_warranty_per_year[1]);
        }

        $savingDollars["Year 1\nTotal"] = $firstYearExisting - $firstYearTarget;
        $savingPercent["Year 1\nTotal"] = (1.0 - ($firstYearTarget / ($firstYearExisting ?: 1))) * 100.0;
        for ($i = 1; $i < $existingEnvironment->project->support_years; ++$i) {
            $existingEnvironmentThisYear = $existingEnvironmentPerYear + $existingEnvironment->fte_qty * $existingEnvironment->fte_salary;
            $targetEnvironmentThisYear = $targetEnvironmentPerYear + $targetEnvironment->fte_qty * $targetEnvironment->fte_salary;

            if ($targetEnvironment->total_hardware_warranty_per_year && isset($targetEnvironment->total_hardware_warranty_per_year[$i + 1])) {
                $targetEnvironmentThisYear -= max(0, $targetEnvironment->total_hardware_warranty_per_year[$i + 1]);
            }

            if (!$existingEnvironment->is_existing && $existingEnvironment->total_hardware_warranty_per_year && isset($existingEnvironment->total_hardware_warranty_per_year[$i + 1])) {
                $existingEnvironmentThisYear -= max(0, $existingEnvironment->total_hardware_warranty_per_year[$i + 1]);
            }

            $savingDollars["Year " . ($i + 1) . "\nTotal"] = $existingEnvironmentThisYear - $targetEnvironmentThisYear;
            $savingPercent["Year " . ($i + 1) . "\nTotal"] = (1.0 - ($targetEnvironmentThisYear / ($existingEnvironmentThisYear ?: 1))) * 100.0;
        }

        $settings = [
            "graph_title" => $targetEnvironment->name . " Total Savings",
            "graph_title_font_size" => 20,
            "line_dataset" => 1,
            "dataset_axis" => [0, 1],
            "back_stroke_width" => 0,
            "back_colour" => "none",
            "show_axis_v" => false,
            "show_axis_h" => false,
            "axis_text_callback_y" => [
                function ($v) {
                    return $v < 0 ? '-' . CurrencyConverter::convertAndFormat(abs($v)) : CurrencyConverter::convertAndFormat($v);
                }, function ($v) {
                    return $v . '%';
                }],
            "show_grid_v" => false,
            "pad_bottom" => 60,
            "bar_width" => 20,
            "legend_entries" => [
                'Total Savings',
                'Total Savings (%)'
            ],
            "stroke_width" => 0,
            "legend_position" => "outer bottom 300 0",
            "legend_stroke_width" => 0,
            "legend_back_color" => "none",
            "legend_shadow_opacity" => 0,
            "marker_size" => 0,
            "line_stroke_width" => 3,
            'axis_text_space_h' => 10,
            'show_data_labels' => [true, false],
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
        ];

        $returnVal = (object)[
            'settings' => $settings,
            'values' => [
                $savingDollars,
                $savingPercent
            ]
        ];

        return $returnVal;
    }

    /**
     * @param $values
     * @param $settings
     * @return mixed
     */
    public function fetchSavingsGraph($values, $settings)
    {
        $colors = array(
            'rgb(91,155,213)',
            'rgb(237,125,49)',
            'rgb(165,165,165)'
        );
        $graph = new SVGGraph(700, 400, $settings);
        $graph->Values($values);
        $graph->Colours($colors);
        return $graph->fetch('BarAndLineGraph');
    }
}