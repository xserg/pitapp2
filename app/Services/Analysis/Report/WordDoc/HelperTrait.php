<?php
/**
 *
 */

namespace App\Services\Analysis\Report\WordDoc;

use App\Models\Project\Environment;
use App\Services\Currency\CurrencyConverter;
use JangoBrick\SVG\SVGImage;
use Illuminate\Support\Facades\Auth;
use App\Services\Analysis\Report\WordDoc;

trait HelperTrait
{
    /**
     * @param $exist
     * @param $targ
     * @param $name
     * @param $table
     */
    public function addSavingsRowWord($exist, $targ, $name, $table)
    {
        $diff = $exist - $targ;
        //if($exist == 0 && $diff == 0 && $targ == 0)
        //    return;
        $table->addRow();
        $centered = 'centeredSingle';
        $tableStyle = array('borderSize' => 6);
        $table->addCell(3500, $tableStyle)->addText(htmlspecialchars($name), array(), 'singleSpace');
        $table->addCell(1800, $tableStyle)->addText($exist == 0 ? 'N/A' : CurrencyConverter::convertAndFormat(round($exist)), array(), $centered);
        $table->addCell(1800, $tableStyle)->addText($targ == 0 ? 'N/A' : CurrencyConverter::convertAndFormat(round($targ)), array(), $centered);
        if ($exist == 0 && $targ > 0) {
            $table->addCell(1800, $tableStyle)->addText('(' . CurrencyConverter::convertAndFormat(abs(round($exist - $targ))) . ')', array(), $centered);
            $table->addCell(1800, $tableStyle)->addText('N/A', array(), $centered);
        } elseif ($diff < 0) {
            $table->addCell(1800, $tableStyle)->addText('(' . CurrencyConverter::convertAndFormat(abs(round($exist - $targ))) . ')', array(), $centered);
            $table->addCell(1800, $tableStyle)->addText('(' . abs(round($exist ? (round((1 - $targ / $exist) * 100)) : 100)) . '%)', array(), $centered);
        } else if ($targ == 0 && $exist == 0) {
            $table->addCell(1800, $tableStyle)->addText('N/A', array(), $centered);
            $table->addCell(1800, $tableStyle)->addText('N/A', array(), $centered);
        } else {
            $table->addCell(1800, $tableStyle)->addText(CurrencyConverter::convertAndFormat(round($exist - $targ)), array(), $centered);
            $table->addCell(1800, $tableStyle)->addText(round($exist ? (round((1 - $targ / $exist) * 100)) : 100) . "%", array(), $centered);
        }
    }

    /**
     * @param $table
     * @param $title
     * @param $width
     * @param $var
     * @param $ignoreExisting
     * @param $environments
     */
    public function addTcoTableRow($table, $title, $width, $var, $ignoreExisting, $environments)
    {
        $hasValue = false;
        foreach ($environments as $environment) {
            if ($environment->is_existing && $ignoreExisting)
                continue;
            if ($environment[$var] != 0) {
                $hasValue = true;
                break;
            }
        }
        //If there is no value in any environment, we can ignore this line.
        if (!$hasValue)
            return;
        $firstWidth = 2700;
        $table->addRow();
        $table->addCell($firstWidth, array('borderSize' => 6))->addText($title, array(), 'singleSpace');
        foreach ($environments as $environment) {
            if ($var == 'storage_purchase_price') {
                $table->addCell($width, array('borderSize' => 6))->addText((($environment->is_existing && $ignoreExisting) || $environment[$var] == 0) ? "N/A" : CurrencyConverter::convertAndFormat(round($environment['storage_purchase_price'] + $environment['iops_purchase_price'])), array(), 'centeredSingle');
            } else {
                $table->addCell($width, array('borderSize' => 6))->addText((($environment->is_existing && $ignoreExisting) || $environment[$var] == 0) ? "N/A" : CurrencyConverter::convertAndFormat(round($environment[$var])), array(), 'centeredSingle');
            }
        }
    }

    /**
     * @param $table
     * @param $title
     * @param $width
     * @param $var
     * @param $ignoreExisting
     * @param $environments
     * @param int $support_years
     */
    public function tcoChassisTableRow($table, $title, $width, $var, $ignoreExisting, $environments, $support_years = 1)
    {

        $hasValue = false;
        foreach ($environments as $environment) {
            if (isset($environment->analysis) && isset($environment->analysis->interchassisResult) && count($environment->analysis->interchassisResult->interconnect_chassis_list) > 0) {
                $hasValue = true;
                break;
            }
        }

        //If there is no value in any environment, we can ignore this line.
        if (!$hasValue)
            return;
        $firstWidth = 2700;
        $table->addRow();
        $table->addCell($firstWidth, array('borderSize' => 6))->addText($title, array(), 'singleSpace');
        foreach ($environments as $environment) {
            if (isset($environment->analysis) && isset($environment->analysis->interchassisResult) && count($environment->analysis->interchassisResult->interconnect_chassis_list) > 0) {
                $table->addCell($width, array('borderSize' => 6))->addText((($environment->is_existing && $ignoreExisting) || $environment->analysis->interchassisResult->$var == 0) ? "N/A" : CurrencyConverter::convertAndFormat(round($environment->analysis->interchassisResult->$var * $support_years)), array(), 'centeredSingle');
            } else {
                $table->addCell($width, array('borderSize' => 6))->addText("N/A", array(), 'centeredSingle');
            }
        }
    }

    /**
     * @param $header
     * @param Environment[] $environments
     * @param $section
     * @return HelperTrait
     */
    public function wordResultTables($header, $environments, $section)
    {
        /**
         * @var int $index
         * @var Environment $environment
         */
        foreach ($environments as $index => $environment) {
            if ($environment->is_existing || $index == 0 || $environment->isCloud()) {
                continue;
            }

            /** @var WordDoc\PhysicalConsolidation|WordDoc\HybridConsolidation|WordDoc\VmConsolidation $wordDocConsolidation */
            $wordDocConsolidation = $this->wordDocConsolidation($environments[0]->existing_environment_type);

            $wordDocConsolidation->drawConsolidation($section, $header, $environment, $environments[0]);
        }

        return $this;
    }
}

