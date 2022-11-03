<?php
/**
 *
 */

namespace App\Services\Analysis\Report;


use App\Models\Project\Environment;
use App\Services\Currency\CurrencyConverter;
use App\Services\Filesystems;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Request;
use App\Models\UserManagement\User;
use App\Models\Project\PrecisionPDF;
use JangoBrick\SVG\SVGImage;
use Illuminate\Support\Str;
use App\Models\Project\AnalysisResult;

class Pdf extends AbstractReport
{
    /**
     * Generate a PDF version of the output
     * @param AnalysisResult $analysisResult
     * @return PrecisionPDF
     * @throws \App\Exceptions\AnalysisException
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function generate(AnalysisResult $analysisResult)
    {
        $project = $analysisResult->getProject();
        //require_once('tcpdf_include.php');

        $bestTarget = $bestTargetEnvironment = $analysisResult->getBestTargetEnvironment();

        $bestTargetAnalysisIndex = $project->environments->search(function($item) use ($bestTarget){
            return $item->id === $bestTarget->id;
        });

        if ($bestTargetAnalysisIndex !== false) {
            $bestTargetAnalysis = $project->environments[$bestTargetAnalysisIndex];
        }

        $isShowCPMColumn = $bestTargetAnalysisIndex !== false
            && property_exists($bestTargetAnalysis->analysis->totals->target, 'cpm_performance_increase')
            && !($bestTarget->isCloud() && !$bestTarget->isIBMPVS());

        $pdf = new PrecisionPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

        // set document information
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor('Smart Software Solutions, Inc');
        $pdf->SetTitle('TCO Assessment');
        $pdf->SetSubject('TCO Assessment');
        $pdf->SetKeywords('TCO, PDF, Assessment');
        $pdf->SetLineStyle(array('width' => 0.1));
        // set default header data
        $pdf->SetHeaderData(null, 0, 'TCO Assessment', '');
        //$pdf->SetPrintHeader(false);
        $pdf->setFooterData(array(0, 0, 0), array(0, 0, 0));
        $pdf->project = $project;
        // set header and footer fonts
        $pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
        $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

        // set default monospaced font
        $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

        // set margins
        //$pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
        $pdf->SetMargins(10, PDF_MARGIN_TOP, 10);
        $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
        $pdf->SetFooterMargin(PDF_MARGIN_FOOTER + 15);
        $pdf->setCellPaddings(1, 1, 1, 1);
        //$pdf->setCellHeightRatio(1.5);
        // set auto page breaks
        $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

        // set image scale factor
        $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

        // set some language-dependent strings (optional)
        if (@file_exists(dirname(__FILE__) . '/lang/eng.php')) {
            require_once(dirname(__FILE__) . '/lang/eng.php');
            $pdf->setLanguageArray($l);
        }

        // ---------------------------------------------------------

        // set default font subsetting mode
        $pdf->setFontSubsetting(true);

        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(true);

        if (config('app.debug')) {
            logger('PDF initialized');
        }
        $pdf->addPage();
        $html =
            '<style>html {font-family: arial;}</style>
        <h1 style="text-align: center; padding-bottom:0px; color:#808080; font-size:22pt">Total Cost of Ownership (TCO) Assessment</h1>
        <table style="border-top: 1px solid #000000; width:100%"><tr><td></td></tr></table>
        <div style="text-align: center;">
        <br/><br/>
        <i><div><u>Prepared for: </u></div> ';
        $extraHeight = 0;
        $largeImage = false;
        if ($project->logo != "" && Filesystems::imagesFilesystem()->exists($project->logo)) {
            $localPath = $this->copyToLocal($project);
            $size = getimagesize($localPath);
            //width / height
            if ($size[0] !== 0 && $size[1] !== 0) {
                $ratio = $size[0] / $size[1];
            } else {
                $ratio = 1;
            }
            if ($ratio < 1)
                $extraHeight = 40;
            else if ($ratio >= 6)
                $extraHeight = 0;
            else {
                $extraHeight = (6 - $ratio) * 8;
            }
            $totalHeight = $size[1] <= 80 ? $size[1] : (40 + $extraHeight);
            //$path = $project->logo;
            $path = str_after($localPath, 'public/');
            $html .= '<div style="display: block; text-align: center;">';
            $url = 'http://localhost/storage/' . $path;
            logger('Asset url '. $url);
            $html .= '<img src="' . $url . '" height="' . $totalHeight . '">';
            $html .= ' </div>';
            if ($totalHeight >= 60)
                $largeImage = true;
        } else {
            //$path = public_path() . '/images/logos/precisionLogo.png';
            $html .= ' <div style="font-size: 18pt;"><strong>' . $project->customer_name . '</strong></div> ';
        }

        if (!$largeImage) {
            $html .= ' <br/>';
        }
        $html .= ' <br/><br/><br/>
        <div><u>Project Name:</u></div>
        <div style="font-size: 18pt;"><strong>' . $project->title . '</strong></div>
        <br/><br/><br/><br/>
        <div><u>Provided by:</u></i></div> ';


        $user = User::find($project->user_id);

        $imagePath = 'images/logos/precisionLogo.png';
        if ($user->image != "" && Filesystems::imagesFilesystem()->exists($user->image)) {
            $imagePath = $user->image;
        }
        $localPath = $this->copyLocal($imagePath);
        $size = getimagesize($localPath);
        //width / height
        if ($size[0] !== 0 && $size[1] !== 0) {
            $ratio = $size[0] / $size[1];
        } else {
            $ratio = 1;
        }
        if ($ratio < 1)
            $extraHeight = 40;
        else if ($ratio >= 6)
            $extraHeight = 0;
        else {
            $extraHeight = (6 - $ratio) * 8;
        }
        $totalHeight = $size[1] <= 80 ? $size[1] : (40 + $extraHeight);
        
        $path = str_replace('images/', 'storage/', $imagePath);
        $html .= '<div style="display: block; text-align: center;">';
        $url = 'http://localhost/' . str_replace(' ', '%20', $path);
        logger('PDF user image url ' . $url);
        $html .= '<img src="'.$url.'" height="' . $totalHeight . '">';
        $html .= ' </div>';
        $tz = "America/Chicago";
        $timestamp = time();
        $dt = new \DateTime("now", new \DateTimeZone($tz));
        $dt->setTimestamp($timestamp);
        if ($totalHeight < 60) {
            $html .= '<br/>';
        }
        $html .= '<br/>
        <div style="text-align: center; color:#0000ff"><i>' . $dt->format('m/d/Y') . '</i></div></div>
        <br/> <br/> <br/>';
        $pdf->writeHTMLCell(0, 0, '', '', $html, 0, 1, 0, true, '', true);

        $pdf->logo = $user->image;

        $pdf->setPrintHeader(true);

        // Set font
        $pdf->SetFont('helvetica', '', 11, '', true);


        // Add a page
        // This method has several options, check the source code documentation for more information.
        $pdf->AddPage();
        $pdf->setPrintFooter(true);
        $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
        // set text shadow effect
        $pdf->setTextShadow(array('enabled' => false, 'depth_w' => 0.2, 'depth_h' => 0.2, 'color' => array(196, 196, 196), 'opacity' => 1, 'blend_mode' => 'Normal'));

        // Set some content to print
        $existingEnvironment = $analysisResult->getExistingEnvironment();
        $bestTarget = $bestTargetEnvironment = $analysisResult->getBestTargetEnvironment();
        $existingConfigs = $existingEnvironment->serverConfigurations;
        $targetConfigs = $bestTarget->serverConfigurations;

        $html = '<h1 style="font-weight: 500">Executive Summary</h1>
        <table style="border-top: 1px solid #000000; width:100%"><tr><td></td></tr></table>
        <p>This TCO analysis was performed by analyzing ' . $project->customer_name . '\'s ' . $existingEnvironment->name . ' infrastructure outlined below:</p>
        <br/>';

        // Print text using writeHTMLCell()
        $pdf->writeHTMLCell(0, 0, '', '', $html, 0, 1, 0, true, '', true);
        $existing =
            '<div><u>Existing Infrastructure</u></div>';
        $totalQty = 0;
        foreach ($existingConfigs as $ex) {
            if ($existingEnvironment->environmentType->name == "Converged") {
                if ($ex->is_converged)
                    $totalQty += $ex->qty;
            } else {
                $totalQty += $ex->qty;
            }
        }
        $existing .= "<br/>{$existingEnvironment->name} Servers";
        $target =
            '<p><u>Target Infrastructure(s)</u></p>';
        foreach ($project->environments as $index => $env) {
            if ($index != 0) {
                $target .= sprintf("<br/>%s", $env->converged_cloud_type == 'ibm' ? $env->name : "{$env->name} " . ($env->vms ? "VMs" : "Servers"));
            }
        }
        $pdf->MultiCell(81, 5, $existing, 0, 'R', 0, 0, '', '', true, 1, true);
        $pdf->MultiCell(30, 5, '<div>vs.</div><br/>vs.', 0, 'C', 0, 0, '', '', true, 0, true);
        $pdf->MultiCell(81, 5, $target, 0, 'L', 0, 0, '', '', true, 1, true);
        $pdf->Ln();
        //$pdf->Ln();
        $totalSavings = round($existingEnvironment->total_cost - $bestTarget->total_cost);
        $savings = $existingEnvironment->total_cost ? (($existingEnvironment->total_cost - $bestTarget->total_cost) / $existingEnvironment->total_cost * 100) :
            (100);
        $savings = number_format(round($savings), 0);
        // $string = '<br/><br/><div>It is projected that ' . $project->customer_name . ' can reduce its Existing ' . $project->support_years . '-year '
        //     . $existingEnvironment->name . ' TCO up to <b>$' . $totalSavings . '</b> and reduce its Existing ' .
        //     $project->support_years . '-year average operating expense up to <b>' . $savings . '%</b> by migrating to a(n) <i>' . $bestTarget->name . '</i> solution.</div><br/><br/>';
        $string = sprintf('<br /><br />%s will receive the estimated financial benefit below by migrating to a(n) <i>%s</i> solution.', $project->customer_name, $bestTarget->name);
        $string .= '<br /><br /><table style="width:100%;border-collapse: collapse" cellpadding="5">';

        $columnWidth = $isShowCPMColumn ? '16.66%' : '20%';

        $headerColumns = collect([
            ['<td style="width:' . $columnWidth . ';border: 1px solid black;text-align: center">' . $project->support_years . '-year TCO Reduction</td>'],
            ['<td style="width:' . $columnWidth . ';border: 1px solid black;text-align: center">Expense Reduction</td>'],
            ['<td style="width:' . $columnWidth . ';border: 1px solid black;text-align: center">ROI</td>'],
            ['<td style="width:' . $columnWidth . ';border: 1px solid black;text-align: center">Payback Period</td>'],
            ['<td style="width:' . $columnWidth . ';border: 1px solid black;text-align: center">CPU Performance Increase</td>', !$isShowCPMColumn],
            ['<td style="width:' . $columnWidth . ';border: 1px solid black;text-align: center">RAM (GB) Capacity Increase</td>'],
        ]);

        $string .= sprintf('<tr>%s</tr>', $this->processColumnsCollection($headerColumns));

        $paybackPeriod = $existingEnvironment->is_existing && $bestTarget->investment/($existingEnvironment->total_cost - $bestTarget->total_cost) > 0 ?
            round(($bestTarget->investment/($existingEnvironment->total_cost - $bestTarget->total_cost))*($project->support_years*12), 1) . ' months' : 'N/A';

        $performanceIncrease = $isShowCPMColumn
            ? $bestTargetAnalysis->analysis->totals->target->cpm_performance_increase
            : 'N/A';

        $ramCapacityIncrease = $bestTargetAnalysisIndex !== false
            ? $bestTargetAnalysis->analysis->totals->target->ram_capacity_increase
            : 'N/A';

        $columns = collect([
            ['<td style="border: 1px solid black;text-align: center"><b>' . CurrencyConverter::convertAndFormat($totalSavings) . '</b></td>'],
            ['<td style="border: 1px solid black;text-align: center"><b>' . $savings . '%</b></td>'],
            ['<td style="border: 1px solid black;text-align: center"><b>' . $bestTargetEnvironment->roi . '</b></td>'],
            ['<td style="border: 1px solid black;text-align: center"><b>' . $paybackPeriod . '</b></td>'],
            ['<td style="border: 1px solid black;text-align: center"><b>' . $performanceIncrease . '</b></td>', !$isShowCPMColumn],
            ['<td style="border: 1px solid black;text-align: center"><b>' . $ramCapacityIncrease . '</b></td>'],
        ]);

        $string .= sprintf('<tr>%s</tr>', $this->processColumnsCollection($columns));

        $string .= '</table><br />';
        $pdf->writeHTMLCell(0, 0, '', '', $string, 0, 1, 0, true, '', true);
        //$pdf->Ln();

        $savingsResult = $this->projectSavingsCalculator()->calculateSavings($project, $existingEnvironment, $bestTargetEnvironment);

        $savingsGraph = $this->projectSavingsCalculator()->fetchSavingsGraph(
            $savingsResult->values,
            $savingsResult->settings
        );

        $pdf->ImageSVG('@' . $savingsGraph, '', '', $w = '200', $h = '114', $link = '', $align = '', $palign = '', $border = 0, $fitonpage = true);

        // ---------------------------------------------------------

        $pdf->AddPage();
        $html =
            '<h1 style="font-weight: 500">' . $project->support_years . '-year Costs by Category</h1>
        <table style="border-top: 1px solid #000000; width:100%"><tr><td></td></tr></table>
        <br/>';
        $pdf->writeHTMLCell(0, 0, '', '', $html, 0, 1, 0, true, '', true);
        $pdf->SetFont('', 'B', '10');
        $startX = $pdf->GetX();
        $startY = $pdf->GetY();
        $singleLine = 5;
        $height = 0;
        //$height = $this->MultiCell(54, $singleLine, $name, 0, 'L', 0, 0);

        $saving_headers = ['Category', ($existingEnvironment->is_existing ? "Existing " : "") . $existingEnvironment->name . ' Cost',
            $bestTarget->name . ' Cost', 'Savings', '% Reduction'];
        for ($i = 0; $i < count($saving_headers); ++$i) {
            if ($i == 0)
                $tempHeight = $pdf->MultiCell(62, 20, $saving_headers[$i], 0, 'C', 0, 0);
            else
                $tempHeight = $pdf->MultiCell(32, 20, $saving_headers[$i], 0, 'C', 0, 0);
            $height = $tempHeight > $height ? $tempHeight : $height;
        }
        $pdf->SetXY($startX, $startY);
        $pdf->MultiCell(62, $height * $singleLine, '', 1, 'L', 0, 0);
        $pdf->MultiCell(32, $height * $singleLine, '', 1, 'C', 0, 0);
        $pdf->MultiCell(32, $height * $singleLine, '', 1, 'C', 0, 0);
        $pdf->MultiCell(32, $height * $singleLine, '', 1, 'C', 0, 0);
        $pdf->MultiCell(32, $height * $singleLine, '', 1, 'C', 0, 0);

        $pdf->Ln();
        $pdf->SetFont('', '');

        $exist = $existingEnvironment->total_hardware_maintenance;
        $targ = $bestTarget->total_hardware_maintenance;

        if (intval($exist) || intval($targ)) {
            $pdf->printSavingsTableLine($exist, $targ, 'Hardware Maintenance Cost');
        }

        $exist = $existingEnvironment->total_system_software_maintenance;
        $targ = $bestTarget->total_system_software_maintenance;
        if (intval($exist) || intval($targ)) {
            $pdf->printSavingsTableLine($exist, $targ, 'System Software Maintenance Cost');
        }

        if (property_exists($bestTarget->analysis, 'interchassisResult')
            && count($bestTarget->analysis->interchassisResult->interconnect_chassis_list) > 0
        ) {
            $targ = $bestTarget->analysis->interchassisResult->annual_maintenance * $project->support_years;
            $pdf->printSavingsTableLine(0, $targ, 'Interconnect/Chassis Maintenance Cost');
        }

        $exist = $existingEnvironment->total_storage_maintenance;
        $targ = $bestTarget->total_storage_maintenance;
        if (intval($exist) || intval($targ)) {
            $pdf->printSavingsTableLine($exist, $targ, 'Storage Maintenance Cost');
        }

        foreach ($project->softwareByNames as $s) {
            $found = false;
            $printLicense = false;
            $printSupport = false;
            foreach ($s->envs as $env) {
                if ($env->id == $existingEnvironment->id || $env->id == $bestTarget->id) {
                    $found = true;
                    if ($env->supportCost != 0)
                        $printSupport = true;
                    if ($env->licenseCost && !$env->ignoreLicense)
                        $printLicense = true;
                }
            }
            if (!$found)
                continue;

            if ($printLicense) {
                $exist = $this->softwareLicenseForEnvironment($s, $existingEnvironment->id, false);
                $targ = $this->softwareLicenseForEnvironment($s, $bestTarget->id, false);
                $pdf->printSavingsTableLine($exist, $targ, $s->name . ' License Cost');
            }
            if ($printSupport || Str::contains($s->name, 'Hyper-V')) {
                $exist = $this->supportForEnvironment($s, $existingEnvironment->id, false);
                $targ = $this->supportForEnvironment($s, $bestTarget->id, false);
                $pdf->printSavingsTableLine($exist, $targ, $s->name . ' Support Cost');
            }
            foreach ($s->features as $f) {
                $found = false;
                $printLicense = false;
                $printSupport = false;
                foreach ($f->envs as $env) {
                    if ($env->id == $existingEnvironment->id || $env->id == $bestTarget->id) {
                        $found = true;
                        if ($env->supportCost != 0)
                            $printSupport = true;
                        if ($env->licenseCost && !$env->ignoreLicense)
                            $printLicense = true;
                    }
                }
                if (!$found)
                    continue;

                if ($printLicense) {
                    $exist = $this->softwareLicenseForEnvironment($f, $existingEnvironment->id, false);
                    $targ = $this->softwareLicenseForEnvironment($f, $bestTarget->id, false);
                    $pdf->printSavingsTableLine($exist, $targ, $f->name . ' License Cost');
                }
                if ($printSupport) {
                    $exist = $this->supportForEnvironment($f, $existingEnvironment->id, false);
                    $targ = $this->supportForEnvironment($f, $bestTarget->id, false);
                    $pdf->printSavingsTableLine($exist, $targ, $f->name . ' Support Cost');
                }
            }
        }

        $exist = $existingEnvironment->total_fte_cost;
        $targ = $bestTarget->total_fte_cost;

        if (floatval($exist) || floatval($targ)) {
            $pdf->printSavingsTableLine($exist, $targ, 'FTE Cost');
        }

        $exist = $existingEnvironment->power_cost;
        $targ = $bestTarget->power_cost;

        if ($exist || $targ) {
            $pdf->printSavingsTableLine($exist, $targ, 'Power/Cooling Cost');
        }

        $pdf->writeHTMLCell(0, 0, '', '', "<br/>", 0, 1, 0, true, '', true);

        $savingsByCategoryResult = $this->projectSavingsByCategoryCalculator()->calculateSavingsByCategory($project, $existingEnvironment, $bestTargetEnvironment);

        $savingsByCategoryGraph = $this->projectSavingsByCategoryCalculator()->fetchSavingsByCategoryGraph(
            $savingsByCategoryResult->values,
            $savingsByCategoryResult->settings
        );
        $pdf->ImageSVG('@' . $savingsByCategoryGraph, '', '', $w = '200', $h = '114', $link = '', $align = '', $palign = '', $border = 0, $fitonpage = true);


        $pdf->AddPage();
        $html =
            '<h1 style="font-weight: 500">' . $project->support_years . '-year TCO Details</h1>
        <table style="border-top: 1px solid #000000; width:100%"><tr><td></td></tr></table>
        <br/>';
        $pdf->writeHTMLCell(0, 0, '', '', $html, 0, 1, 0, true, '', true);
        $pdf->SetFont('', 'B', '10');
        $startX = $pdf->GetX();
        $startY = $pdf->GetY();
        $singleLine = 5;
        $height = 0;
        $firstColumnWidth = 60;
        $tco_headers = ['', ($existingEnvironment->is_existing ? "Existing " : "") . $existingEnvironment->name . ' Environment'];
        foreach ($project->environments as $index => $environment) {
            if ($index !== 0) {
                $tco_headers[] = $environment->name . ' Environment';
            }
        }
        $colWidth = 130.0 / count($project->environments);

        for ($i = 0; $i < count($tco_headers); ++$i) {
            $tempHeight = $pdf->MultiCell($i == 0 ? $firstColumnWidth : $colWidth, 20, $tco_headers[$i], 0, 'C', 0, 0);
            $height = $tempHeight > $height ? $tempHeight : $height;
        }
        $pdf->SetXY($startX, $startY);
        for ($i = 0; $i < count($tco_headers); ++$i) {
            $pdf->MultiCell($i == 0 ? $firstColumnWidth : $colWidth, $height * $singleLine + 1.5, '', 1, 'L', 0, 0);
        }

        $pdf->SetFont('', '');
        $pdf->Ln();

        $singleRow = 5;
        $text = 'Servers';
        if ($this->hasConverged($project->environments)) {
            $text .= '/Nodes';
        }
        if ($this->hasCloud($project->environments)) {
            $text .= '/Instances';
        }
        $pdf->MultiCell($firstColumnWidth, $singleRow, $text, 1, 'L', 0, 0);
        foreach ($project->environments as $index => $environment) {
            $pdf->MultiCell($colWidth, $singleRow, $index != 0 ?
                            ($environment->analysis->totals->target->servers && $environment->converged_cloud_type != 'ibm' ? number_format($environment->analysis->totals->target->servers) : 'N/A') :
                            number_format($project->environments[1]->analysis->totals->existing->servers), 1, 'C', 0, 0);
        }
        $pdf->Ln();

        if ($existingEnvironment->isPhysicalVm()) {
            $pdf->MultiCell($firstColumnWidth, $singleRow, 'VMs', 1, 'L', 0, 0);
            foreach ($project->environments as $index => $environment) {
                $pdf->MultiCell($colWidth, $singleRow, $index != 0 ? number_format($environment->analysis->totals->target->vms, 0) : number_format($project->environments[1]->analysis->totals->existing->vms, 0), 1, 'C', 0, 0);
            }
            $pdf->Ln();
            $pdf->MultiCell($firstColumnWidth, $singleRow, 'Physical Cores', 1, 'L', 0, 0);
            foreach ($project->environments as $index => $environment) {
                $pdf->MultiCell($colWidth, $singleRow, $index != 0 ? number_format($environment->analysis->totals->target->physical_cores, 0) : number_format($project->environments[1]->analysis->totals->existing->physical_cores, 0), 1, 'C', 0, 0);
            }
            $pdf->Ln();
            $pdf->MultiCell($firstColumnWidth, $singleRow, 'Virtual Cores', 1, 'L', 0, 0);
            foreach ($project->environments as $index => $environment) {
                $pdf->MultiCell($colWidth, $singleRow, $index != 0 ? number_format($environment->analysis->totals->existing->comparisonCores, 0) : number_format($project->environments[1]->analysis->totals->existing->vm_cores, 0), 1, 'C', 0, 0);
            }
            $pdf->Ln();
        } else {
            $pdf->MultiCell($firstColumnWidth, $singleRow, 'Cores/vCPUs', 1, 'L', 0, 0);
            foreach ($project->environments as $index => $environment) {
                $pdf->MultiCell($colWidth, $singleRow, $index != 0 ? number_format($environment->analysis->totals->target->total_cores, 0) : number_format($project->environments[1]->analysis->totals->existing->total_cores, 0), 1, 'C', 0, 0);
            }
            $pdf->Ln();
        }

        if (!$existingEnvironment->isVm()) {
            $pdf->MultiCell($firstColumnWidth, $singleRow, 'Processors', 1, 'L', 0, 0);
            foreach ($project->environments as $index => $environment) {
                $pdf->MultiCell($colWidth, $singleRow, $index != 0 ?
                                (!isset($environment->analysis->totals->target->socket_qty) || $environment->converged_cloud_type == 'ibm' ? "N/A" : number_format($environment->analysis->totals->target->socket_qty, 0)) :
                                number_format($project->environments[1]->analysis->totals->existing->socket_qty, 0), 1, 'C', 0, 0);
            }
            $pdf->Ln();

            $pdf->MultiCell($firstColumnWidth, 16.5, 'Compute Performance Metric (CPM) – Existing Env. @ ' . ($project->environments[1]->analysis->totals->existing->cpuMatch ? ($existingEnvironment->cpu_utilization . '% ') : '') . 'CPU Utilization', 1, 'L', 0, 0);
            foreach ($project->environments as $index => $environment) {

                $txt = 'N/A';
                if ($index !== 0 && property_exists($environment->analysis->totals->target, 'utilRpm')) {
                    if (isset($environment->analysis->totals->target->rpm)) {
                        $txt = number_format(round($environment->analysis->totals->target->utilRpm));
                    }
                } else {
                    if (isset($environment->existing_environment_type) && ($environment->existing_environment_type === 'physical_servers_vm')) {
                        $txt = number_format(round($project->environments[1]->analysis->totals->existing->physical_rpm));
                    } else {
                        $txt = number_format(round($project->environments[1]->analysis->totals->existing->computedRpm));
                    }

                    if ($environment->isIBMPVS()) {
                        $txt = $environment->analysis->totals->target->rpm ? number_format(round($environment->analysis->totals->target->rpm)) : 'N/A';
                    }
                }

                $pdf->MultiCell($colWidth, 16.5, $txt, 1, 'C', 0, 0);
            }
            $pdf->Ln();
        }

        $pdf->MultiCell($firstColumnWidth, $singleRow, 'Total Memory (GB)', 1, 'L', 0, 0);
        foreach ($project->environments as $index => $environment) {
            $pdf->MultiCell($colWidth, $singleRow, $index != 0 ?
                number_format(round($environment->analysis->totals->target->utilRam), 0) :
                number_format(round($project->environments[1]->analysis->totals->existing->computedRam), 0), 1, 'C', 0, 0);
        }
        $pdf->Ln();

        $vmNa = function(Environment $environment, $text) use ($existingEnvironment) {
            if ($existingEnvironment->isVm() && $existingEnvironment->id == $environment->id) {
                return 'N/A';
            }

            return $text;
        };

        $startX = $pdf->GetX();
        $startY = $pdf->GetY();
        $height = $pdf->MultiCell($firstColumnWidth, $singleRow, 'CPU Architecture', 0, 'L', 0, 0);
        foreach ($project->environments as $environment) {
            $tempHeight = $pdf->MultiCell($colWidth, $singleRow, $vmNa($environment, $environment->cpu_architecture), 0, 'C', 0, 0);
            $height = $tempHeight > $height ? $tempHeight : $height;
        }
        $pdf->SetXY($startX, $startY);
        $pdf->MultiCell($firstColumnWidth, $height * $singleRow + 1, '', 1, 'L', 0, 0);
        foreach ($project->environments as $pEnv) {
            $pdf->MultiCell($colWidth, $height * $singleRow + 1, '', 1, 'C', 0, 0);
        }
        $pdf->Ln();

        $pdf->MultiCell($firstColumnWidth, $singleRow, 'Processor Speed (GHz)', 1, 'L', 0, 0);
        foreach ($project->environments as $environment) {
            $pdf->MultiCell($colWidth, $singleRow, $vmNa($environment, $environment->ghz), 1, 'C', 0, 0);
        }
        $pdf->Ln();

        if ($project->environments[0]->total_storage) {
            $pdf->MultiCell($firstColumnWidth, $singleRow, 'Useable Storage (TB)', 1, 'L', 0, 0);
            foreach ($project->environments as $environment) {
                $pdf->MultiCell($colWidth, $singleRow, number_format(round($environment->useable_storage), 0), 1, 'C', 0, 0);
            }
            $pdf->Ln();
        }

        $pdf->tcoCostRow('Hardware/Lease Purchase Price', $colWidth, $singleRow, 'purchase_price', true, $project->environments);
        $pdf->tcoCostRow('Hardware Maintenance (' . $project->support_years . '-year)', $colWidth, $singleRow, 'total_hardware_maintenance', false, $project->environments);
        $pdf->tcoCostRow('Usage Price (' . $project->support_years . '-year)', $colWidth, $singleRow, 'total_hardware_usage', false, $project->environments);
        $pdf->tcoCostRow('System Software Purchase Price', $colWidth, $singleRow, 'system_software_purchase_price', true, $project->environments);
        $pdf->tcoCostRow('System Software Maintenance (' . $project->support_years . '-year)', $colWidth, $singleRow * 2 + 1, 'total_system_software_maintenance', false, $project->environments);
        $pdf->tcoChassisRow('Interconnect/Chassis Purchase Price', $colWidth, $singleRow, 'purchase_cost', false, $project->environments, 1);
        $pdf->tcoChassisRow('Interconnect/Chassis Maintenance (' . $project->support_years . '-year)', $colWidth, $singleRow * 2 + 1, 'annual_maintenance', false, $project->environments, $project->support_years);
        $pdf->tcoCostRow('Storage Cost', $colWidth, $singleRow, 'storage_purchase_price', true, $project->environments);
        $pdf->tcoCostRow('Storage Maintenance (' . $project->support_years . '-year)', $colWidth, $singleRow, 'total_storage_maintenance', false, $project->environments);
        $pdf->tcoCostRow('Network Costs (' . $project->support_years . '-year)', $colWidth, $singleRow, 'network_costs', false, $project->environments);

        $pageHeight = 260;
        foreach ($project->softwareByNames as $s) {
            $found = false;
            $printLicense = false;
            $printSupport = false;
            foreach ($s->envs as $env) {
                foreach ($project->environments as $pEnv) {
                    if ($env->id == $pEnv->id) {
                        $found = true;
                    }
                    if ($env->supportCost != 0)
                        $printSupport = true;
                    if ($env->licenseCost && !$env->ignoreLicense)
                        $printLicense = true;
                }
            }
            if (!$found)
                continue;

            if ($printLicense) {
                if ($pdf->GetY() > $pageHeight) {
                    $pdf->AddPage();
                }
                $startX = $pdf->GetX();
                $startY = $pdf->GetY();
                $height = $pdf->MultiCell($firstColumnWidth, $singleRow, $s->name . ' License Cost', 0, 'L', 0, 0);
                foreach ($project->environments as $pEnv) {
                    $cost = $this->softwareLicenseForEnvironment($s, $pEnv->id, true);
                    $pdf->MultiCell($colWidth, $singleRow, $cost, 0, 'C', 0, 0);
                }
                $pdf->SetXY($startX, $startY);
                $pdf->MultiCell($firstColumnWidth, $height * $singleRow + 1, '', 1, 'L', 0, 0);
                foreach ($project->environments as $pEnv) {
                    $pdf->MultiCell($colWidth, $height * $singleRow + 1, '', 1, 'C', 0, 0);
                }
                $pdf->Ln();
            }
            if ($printSupport || Str::contains($s->name, 'Hyper-V')) {
                if ($pdf->GetY() > $pageHeight) {
                    $pdf->AddPage();
                }
                $startX = $pdf->GetX();
                $startY = $pdf->GetY();
                $height = $pdf->MultiCell($firstColumnWidth, $singleRow, $s->name . ' Support Cost (' . $project->support_years . '-year)', 0, 'L', 0, 0);
                foreach ($project->environments as $pEnv) {
                    $cost = $this->supportForEnvironment($s, $pEnv->id, true);
                    $pdf->MultiCell($colWidth, $singleRow, $cost, 0, 'C', 0, 0);
                }
                $pdf->SetXY($startX, $startY);
                $pdf->MultiCell($firstColumnWidth, $height * $singleRow + 1, '', 1, 'L', 0, 0);
                foreach ($project->environments as $pEnv) {
                    $pdf->MultiCell($colWidth, $height * $singleRow + 1, '', 1, 'C', 0, 0);
                }
                $pdf->Ln();
            }
            foreach ($s->features as $f) {
                $found = false;
                $printLicense = false;
                $printSupport = false;
                foreach ($f->envs as $env) {
                    foreach ($project->environments as $pEnv) {
                        if ($env->id == $pEnv->id) {
                            $found = true;
                        }
                        if ($env->supportCost != 0)
                            $printSupport = true;
                        if ($env->licenseCost && !$env->ignoreLicense)
                            $printLicense = true;
                    }
                }
                if (!$found)
                    continue;

                if ($printLicense) {
                    if ($pdf->GetY() > $pageHeight) {
                        $pdf->AddPage();
                    }
                    $startX = $pdf->GetX();
                    $startY = $pdf->GetY();
                    $height = $pdf->MultiCell($firstColumnWidth, $singleRow, $f->name . ' License Cost', 0, 'L', 0, 0);
                    foreach ($project->environments as $pEnv) {
                        $cost = $this->softwareLicenseForEnvironment($f, $pEnv->id, true);
                        $pdf->MultiCell($colWidth, $singleRow, $cost, 0, 'C', 0, 0);
                    }
                    $pdf->SetXY($startX, $startY);
                    $pdf->MultiCell($firstColumnWidth, $height * $singleRow + 1, '', 1, 'L', 0, 0);
                    foreach ($project->environments as $pEnv) {
                        $pdf->MultiCell($colWidth, $height * $singleRow + 1, '', 1, 'C', 0, 0);
                    }
                    $pdf->Ln();
                }
                if ($printSupport) {
                    if ($pdf->GetY() > $pageHeight) {
                        $pdf->AddPage();
                    }
                    $startX = $pdf->GetX();
                    $startY = $pdf->GetY();
                    $height = $pdf->MultiCell($firstColumnWidth, $singleRow, $f->name . ' Support Cost (' . $project->support_years . '-year)', 0, 'L', 0, 0);
                    foreach ($project->environments as $pEnv) {
                        $cost = $this->supportForEnvironment($f, $pEnv->id, true);
                        $pdf->MultiCell($colWidth, $singleRow, $cost, 0, 'C', 0, 0);
                    }
                    $pdf->SetXY($startX, $startY);
                    $pdf->MultiCell($firstColumnWidth, $height * $singleRow + 1, '', 1, 'L', 0, 0);
                    foreach ($project->environments as $pEnv) {
                        $pdf->MultiCell($colWidth, $height * $singleRow + 1, '', 1, 'C', 0, 0);
                    }
                    $pdf->Ln();
                }
            }
        }

        $pdf->tcoCostRow('Implementation/Migration Services', $colWidth, $singleRow, 'migration_services', true, $project->environments);
        $pdf->tcoCostRow('FTE Cost (' . $project->support_years . '-year)', $colWidth, $singleRow, 'total_fte_cost', false, $project->environments);
        $pdf->tcoCostRow('Remaining Deprecation Cost', $colWidth, $singleRow, 'remaining_deprecation', true, $project->environments);
        $pdf->tcoCostRow('Power/Cooling Cost (' . $project->support_years . '-year)', $colWidth, $singleRow, 'power_cost', false, $project->environments);

        $pdf->SetFont('', 'B', '10');
        $pdf->MultiCell($firstColumnWidth, $singleRow, 'Total ' . $project->support_years . '-year Costs', 1, 'C', 0, 0);
        foreach ($project->environments as $environment) {
            $pdf->MultiCell($colWidth, $singleRow, CurrencyConverter::convertAndFormat(round($environment->total_cost)), 1, 'C', 0, 0);
        }
        $pdf->Ln();

        $pdf->AddPage();
        $pdf->SetFont('', '', '10');
        $html =
            '<h1 style="font-weight: 500">Environment Details</h1>
        <table style="border-top: 1px solid #000000; width:100%"><tr><td></td></tr></table>';
        //<br/>';
        $pdf->writeHTMLCell(0, 0, '', '', $html, 0, 1, 0, true, '', true);
        $html = '';
        foreach ($project->environments as $environment) {
            if ($environment->environmentType->name == "Converged") {
                $html .= '<div><i><u>' . $environment->name . ' Node Detail</u></i></div>
                          <br/>
                          <style>.node-details th, .node-details td {
                              border: 1px solid black;
                              text-align: center;
                              font-size: 8pt;
                          }</style>';
                foreach ($environment->groupedNodes as $group) {
                    $html .= '<table class="node-details" cellpadding="5">
                              <tr>
                                  <td style="width: 7.3%;">Node Qty</td>
                                  <td style="width: 10.4%;">Proc Type</td>
                                  <td style="width: 5.2%;">GHz</td>
                                  <td style="width: 8.2%;"># of Procs</td>
                                  <td style="width: 8.2%;">Cores / Proc</td>
                                  <td style="width: 7.2%;">Total Cores</td>
                                  <td style="width: 6.3%;">RAM (GB)</td>
                                  <td style="width: 8.5%;">Storage Type</td>
                                  <td style="width: 8.3%;">Drive Capacity (TB)</td>
                                  <td style="width: 7.3%;">Drive Qty</td>
                                  <td style="width: 7.3%;">Raw Storage (TB)</td>
                                  <td style="width: 7.3%;">Useable Storage (TB)</td>
                                  <td style="width: 8.0%;">IOPS</td>
                              </tr>';
                    foreach ($group as $config) {
                        if (!$config->is_converged) {
                            $totalCores = $config->processor->socket_qty * $config->processor->core_qty;
                            $storageType = $this->mapStorageType($config->storage_type);
                            $html .= "<tr>
                                <td>{$config->qty}</td>
                                <td>{$config->processor->name}</td>
                                <td>{$config->processor->ghz}</td>
                                <td>" . number_format($config->processor->socket_qty) . "</td>
                                <td>" . number_format($config->processor->core_qty) . "</td>
                                <td>" . number_format($totalCores) . "</td>
                                <td>{$config->ram}</td>
                                <td>{$storageType}</td>
                                <td>{$config->drive_size}</td>
                                <td>{$config->drive_qty}</td>
                                <td>{$config->raw_storage}</td>
                                <td>{$config->useable_storage}</td>
                                <td>{$config->iops}</td>
                            </tr>";
                        }
                    }
                    $html .= "</table><br/><br/>";
                }
            }
        }
        //$pdf->writeHTMLCell(0, 0, '', '', $html, 0, 1, 0, true, '', true);
        if ($html !== '' ) {
            $pdf->writeHTML($html, true, false, true, false, '');
            $pdf->SetFont('', '', '10');
        }

        $html = '';
        foreach ($project->environments as $environment) {

            if (isset($environment->analysis) && isset($environment->analysis->interchassisResult)
                && count($environment->analysis->interchassisResult->interconnect_chassis_list) > 0) {
                $html .= '<div><i><u>' . $environment->name . ' Interconnect/Chassis Detail</u></i></div>
                          <br/>
                          <style>.node-details th, .node-details td {
                              border: 1px solid black;
                              text-align: center;
                              font-size: 8pt;
                          }</style>';
                if ($environment->environmentType->name == "Converged") {
                    $html .= '<table class="node-details" cellpadding="5">
                              <tr>
                                  <td style="width: 16.7%;">Qty</td>
                                  <td style="width: 16.7%;">Location</td>
                                  <td style="width: 16.7%;">Manufacturer</td>
                                  <td style="width: 16.7%;">Model</td>
                                  <td style="width: 16.7%;">Servers/Interconnect</td>
                                  <td style="width: 16.7%;">Description</td>
                              </tr>';
                } else {
                    $html .= '<table class="node-details" cellpadding="5">
                              <tr>
                                  <td style="width: 14.2%;">Qty</td>
                                  <td style="width: 14.2%;">Location</td>
                                  <td style="width: 14.2%;">Manufacturer</td>
                                  <td style="width: 14.2%;">Model</td>
                                  <td style="width: 14.2%;">RUs/Chassis</td>
                                  <td style="width: 14.2%;">Servers/Chassis</td>
                                  <td style="width: 14.2%;">Description</td>
                              </tr>';
                }
                foreach ($environment->analysis->interchassisResult->interconnect_chassis_list as $ic) {
                    if ($environment->environmentType->name == "Converged") {
                        $html .= "<tr>
                                <td>{$ic['qty']}</td>
                                <td>" . ($ic['location'] ?: "Any") . "</td>
                                <td>" . $ic['chassises'][0]->manufacturerName . "</td>
                                <td>{$ic['chassises'][0]->modelName}</td>
                                <td>{$ic['chassises'][0]->totalSpace}</td>
                                <td>{$ic['chassises'][0]->description}</td>
                            </tr>";
                    } else {
                        $html .= "<tr>
                                <td>{$ic['qty']}</td>
                                <td>" . ($ic['location'] ?: "Any") . "</td>
                                <td>" . $ic['chassises'][0]->manufacturerName . "</td>
                                <td>{$ic['chassises'][0]->modelName}</td>
                                <td>" . ($ic['chassises'][0]->rack_units ?: 'N/A') . "</td>
                                <td>" . ($ic['chassises'][0]->nodes_per_unit ?: 'N/A') . "</td>
                                <td>{$ic['chassises'][0]->description}</td>
                            </tr>";
                    }

                }
                $html .= "</table><br/><br/>";
            }
        }

        //$pdf->writeHTMLCell(0, 0, '', '', $html, 0, 1, 0, true, '', true);
        if ($html !== '') {
            $pdf->writeHTML($html, true, false, true, false, '');
            $pdf->SetFont('', '', '10');
        }

        $html = '';
        foreach ($project->softwareByNames as $s) {
            $found = false;
            $printLicense = false;
            $printSupport = false;
            foreach ($s->envs as $env) {
                foreach ($project->environments as $pEnv) {
                    if ($env->id == $pEnv->id) {
                        $found = true;
                    }
                    if ($env->supportCost != 0)
                        $printSupport = true;
                    if ($env->licenseCost && !$env->ignoreLicense)
                        $printLicense = true;
                }
            }
            if (!$found)
                continue;
            if ($printLicense) {
                $html .= '<table style="width: 100%; padding:5px"><tr><td colspan="2"><u><i>' . $s->name . ' License Cost = ' . $s->licenseFormula . '</i></u></td></tr>';
                foreach ($s->envs as $e) {
                    $html .= '<tr><td style="width:6%"></td><td style="width:94%"><div><b>' . $e->env . '</b> ' . $e->softwareName . ' License Cost = ' . $e->licenseFormula . '</div></td></tr>';
                }
                $html .= '</table><div style="height:5px"></div>';
            }
            if ($printSupport) {
                $html .= '<table style="width: 100%; padding:5px"><tr><td colspan="2"><u><i>' . $s->name . ' Support Cost = ' . $s->supportFormula . '</i></u></td></tr>';
                foreach ($s->envs as $e) {
                    $html .= '<tr><td style="width:6%"></td><td style="width:94%"><div><b>' . $e->env . '</b> ' . $e->softwareName . ' Support Cost = ' . $e->supportFormula . '</div></td></tr>';
                }
                $html .= '</table><div style="height:5px"></div>';
            }
            foreach ($s->features as $f) {
                if ($printLicense) {
                    $html .= '<table style="width: 100%; padding:5px"><tr><td colspan="2"><u><i>' . $f->name . ' License Cost = ' . $f->licenseFormula . '</i></u></td></tr>';
                    foreach ($f->envs as $e) {
                        $html .= '<tr><td style="width:6%"></td><td style="width:94%"><div><b>' . $e->env . '</b> ' . $e->featureName . ' License Cost = ' . $e->licenseFormula . '</div></td></tr>';
                    }
                    $html .= '</table><div style="height:5px"></div>';
                }
                if ($printSupport) {
                    $html .= '<table style="width: 100%; padding:5px"><tr><td colspan="2"><u><i>' . $f->name . ' Support Cost = ' . $f->supportFormula . '</i></u></td></tr>';
                    foreach ($f->envs as $e) {
                        $html .= '<tr><td style="width:6%"></td><td style="width:94%"><div><b>' . $e->env . '</b> ' . $e->featureName . ' Support Cost = ' . $e->supportFormula . '</div></td></tr>';
                    }
                    $html .= '</table><div style="height:5px"></div>';
                }
            }
        }

        $showPowerCooling = false;
        foreach ($project->environments as $e) {
            if ($e->power_cost) {
                $showPowerCooling = true;
                break;
            }
        }

        if ($showPowerCooling) {
            $html .= '<table style="width: 100%; padding:5px"><tr><td colspan="2"><u><i>Compute Power/Cooling Cost = Max power consumption (kW) * metered cost per kWH * 24 hrs/day * 30 days/mo * 12 mo/yr * # of years</i></u></td></tr>';
            foreach ($project->environments as $e) {
                if ($e->environmentType->name != "Cloud") {
                    $html .= '<tr><td style="width:6%"></td><td style="width:94%"><div><b>' . $e->name . '</b> Power/Cooling Cost = ' . $e->power_cost_formula . '</div></td></tr>';
                }
            }
            $html .= '</table>';
        }

        $showRaw = false;
        foreach ($project->environments as $e) {
            if ($e->raw_storage) {
                $showRaw = true;
                break;
            }
        }
        if ($showRaw) {
            $html .= '<div style="height:5px"></div><table style="width: 100%; padding:5px"><tr><td colspan="2"><u><i>Storage Power/Cooling Cost = Power (($/kWh * kWh/yr/drive) + Cooling ($/kWh * kWh/yr/drive)) * 1TB drive factor * # of Raw TBs * # of years for analysis</i></u></td></tr>';
            foreach ($project->environments as $e) {
                if ($e->environmentType->name != "Cloud" && $e->raw_storage) {
                    $html .= '<tr><td style="width:6%"></td><td style="width:94%"><div><b>' . $e->name . '</b> ' . $e->storage_power_cost_formula . '</div></td></tr>';
                }
            }
            $html .= '</table>';
        }

        if ($existingEnvironment->network_costs) {
            $html .= '<div style="height:5px"></div><table style="width: 100%; padding:5px"><tr><td colspan="2"><u><i>Existing Environment/Target Networking = 40% * AWS/Azure/Google/IBM PVS Outbound Network Cost (whatever is higher) or Annual Manual Amount</i></u></td></tr>';
            foreach ($project->environments as $e) {
                if ($e->environmentType->name != "Cloud") {
                    $html .= '<tr><td style="width:6%"></td><td style="width:94%"><div><b>' . $e->name . '</b> ';
                    if (!$e->network_overhead) {
                        $html .= 'Networking = 40% * ' . CurrencyConverter::convertAndFormat($e->max_network) . ' = ' . CurrencyConverter::convertAndFormat($e->network_costs) . '</div></td></tr>';
                    } else {
                        $html .= 'Networking = ' . CurrencyConverter::convertAndFormat($e->network_overhead) . ' * ' . $project->support_years . " years = " . CurrencyConverter::convertAndFormat($e->network_costs) . '</div></td></tr>';
                    }
                }
            }
            $html .= '</table>';
        }

        // Start AWS
        if ($this->hasCloudProvider($project->environments, 'AWS')) {
            $html .= '<br/>';
            $html .= "<div><strong><u>" . $this->awsString($project) . "</u></strong></div>";
            if ($this->hasStorage($project->environments)) {
                $html .= '<br/>';

                // All storage types except EBS Provisioned IOPS SSD
                if (!$this->hasAWSStorageType($project->environments, [3, 7, 9, 8])) {
                    $html .= '<table style="width: 100%; padding:5px"><tr><td colspan="2"><u><i>Amazon Storage Cost = ((Price per GB-Month * discount) * provisioned storage (GB)) * Utilization * 12 mo/yr * # of years</i></u></td></tr>';
                }

                if ($this->hasAWSStorageType($project->environments, [9, 8])) {
                    $html .= '<table style="width: 100%; padding:5px"><tr><td colspan="2"><u><i>Amazon Storage Cost = ((Price per GB-Month * discount) * provisioned storage (GB)) + (Price per I/O rate * I/O rate)) * Utilization * 12 mo/yr * # of years</i></u></td></tr>';
                }

                if (!$this->hasAWSStorageType($project->environments, [3, 7])) {
                    foreach ($project->environments as $e) {
                        if (!$this->envHasCloudProvider($e, 'AWS')) continue;
                        if (!$this->hasAWSStorageType($project->environments, [9, 8])) {
                            $html .= '<tr><td style="width:6%"></td><td style="width:94%"><div><b>' . $e->name . '</b> '. $e->storageType . ' Cost = ((' . CurrencyConverter::convertAndFormat($e->gbMonthPrice) . '/GB/month * ' . ($e->discount_rate? $e->discount_rate : 0) . '%) *'. number_format(($e->total_storage * 1000)) . ' GB) * ' . $e->max_utilization . '% * 12 mo/yr * ' . $project->support_years . ' years = ' . CurrencyConverter::convertAndFormat($e->storage_purchase_price) . '</div></td></tr>';
                        }
                        if ($this->hasAWSStorageType($project->environments, [9, 8])) {
                            $html .= '<tr><td style="width:6%"></td><td style="width:94%"><div><b>' . $e->name . '</b> '. $e->storageType . ' Cost = ((' . CurrencyConverter::convertAndFormat($e->gbMonthPrice) . '/GB/month * ' . ($e->discount_rate? $e->discount_rate : 0) . '%) *' . number_format(($e->total_storage * 1000)) . ' GB) + (' . $e->ioRatePrice . ' * '. $e->io_rate . ')) * ' . $e->max_utilization . '% * 12 mo/yr * ' . $project->support_years . ' years = ' . CurrencyConverter::convertAndFormat($e->storage_purchase_price) . '</div></td></tr>';
                        }
                        if (intval($existingEnvironment->iops)) {
                            $html .= '<tr><td style="width:6%"></td><td style="width:94%"><div>' . $e->storageType . ' IOPS (3 IOPS/GB * ' . number_format($e->total_storage * 1000, 0) . ' GB) = ' . number_format($e->totalIops, 0) . ' IOPS' . '</div></td></tr>';
                            if ($e->iopsDeficit == 0) {
                                $html .= '<tr><td style="width:6%"></td><td style="width:94%"><div>'
                                    . 'IOPS constraint met. ' . number_format($e->totalIops, 0) . ' IOPS - ' . number_format($existingEnvironment->iops, 0) . ' IOPS = ' . number_format($e->iopsSurplus, 0) . ' IOPS Surplus'
                                    . '</div></td></tr>';
                            }
                            if ($e->iopsDeficit > 0) {
                                $html .= '<tr><td style="width:6%"></td><td style="width:94%"><div>'
                                    . 'IOPS deficit = ' . number_format($existingEnvironment->iops, 0) . ' IOPS - ' . number_format($e->totalIops, 0) . ' IOPS = ' . number_format($e->iopsDeficit, 0) . ' IOPS'
                                    . '</div></td></tr>';
                                $html .= '<tr><td style="width:6%"></td><td style="width:94%"><div>'
                                    . 'Additional storage required to meet IOPS constraint:'
                                    . '</div></td></tr>';
                                $html .= '<tr><td style="width:6%"></td><td style="width:94%"><div>'
                                    . 'Amazon EBS General Purpose Cost for additional IOPS = '
                                    . '(' . CurrencyConverter::convertAndFormat($e->gbMonthPrice) . '/GB/month * (' . number_format($e->iopsDeficit, 0) . ' IOPS / ' . number_format($e->iops_per_gb, 0) . ' IOPS per GB = ' . number_format($e->iopsGbNeeded, 0) . ' GB) * ' . $e->max_utilization . '% * 12 mo/yr * ' . $project->support_years . ' years = ' . CurrencyConverter::convertAndFormat($e->iops_purchase_price)
                                    . '</div></td></tr>';
                                $html .= '<tr><td style="width:6%"></td><td style="width:94%"><div>'
                                    . 'TOTAL 3 year Storage Cost = ' . CurrencyConverter::convertAndFormat($e->storage_purchase_price) . ' + ' . CurrencyConverter::convertAndFormat($e->iops_purchase_price) . '= ' . CurrencyConverter::convertAndFormat($e->storage_purchase_price + $e->iops_purchase_price)
                                    . '</div></td></tr>';
                            }
                        }
                    }
                    $html .= '</table><br/>';
                }



                // Storage type EBS Provisioned IOPS SSD
                if ($this->hasAWSStorageType($project->environments, [3, 7])) {
                    $html .= '<table style="width: 100%; padding:5px"><tr><td colspan="2"><u><i>Amazon Storage Cost = ((Price per GB-Month * discount) * provisioned storage (GB)) + (Price per provisioned IOPS * Provisioned IOPS)) * Utilization * 12 mo/yr * # of years</i></u></td></tr>';
                    foreach ($project->environments as $e) {
                        if ($this->envHasCloudProvider($e, "AWS")) {
                            $html .= '<tr><td style="width:6%"></td><td style="width:94%"><div><b>' . $e->name . '</b>' . ' Amazon ' . $e->storageType . ' Cost = '
                                . '((' . CurrencyConverter::convertAndFormat($e->gbMonthPrice) . '/GB/month * ' . number_format($e->total_storage * 1000, 0) . ' GB)'
                                . ' + (' . CurrencyConverter::convertAndFormat($e->iopsMonthPrice) . ' * ' . number_format($e->provisioned_iops, 0) . ' IOPS)) * '
                                . $e->max_utilization . '% * 12 mo/yr * ' . $project->support_years . ' years ' .
                                '= ' . CurrencyConverter::convertAndFormat($e->storage_purchase_price) . '</div></td></tr>';
                        }
                    }
                    $html .= '</table><br/>';
                }

                // Storage business level cost
                foreach ($project->environments as $e) {
                    if ($this->envHasCloudProvider($e, "AWS")) {
                        $html .= '<table style="width: 100%; padding:5px"><tr><td><strong><u>' . htmlspecialchars($e->name) . ' Storage Business Level Support Cost</u></strong></td></tr>';
                        if ($e->cloud_storage_type != 3 && $e->cloud_storage_type != 7) {
                            $html .= "<tr><td>" . "Storage Monthly Cost = ((" . CurrencyConverter::convertAndFormat($e->gbMonthPrice) . "/GB/month * " . ($e->discount_rate? $e->discount_rate : 0) . "%) * " . number_format($e->total_storage * 1000 + $e->iopsGbNeeded) . " GB) * " . $e->max_utilization . "% = " . CurrencyConverter::convertAndFormat($e->monthly_storage_purchase + $e->monthly_iops_purchase) . "</td></tr>";

                        } else {
                            $html .= "<tr><td>" . 'Storage Monthly Cost = ((('
                                . CurrencyConverter::convertAndFormat($e->gbMonthPrice). '/GB/month * ' . ($e->discount_rate? $e->discount_rate : 0) . '%) * ' . number_format($e->total_storage * 1000) . ' GB) + (' . CurrencyConverter::convertAndFormat($e->iopsMonthPrice) . ' * ' . number_format($e->provisioned_iops, 0) . ' IOPS)) * '
                                . $e->max_utilization . '% = ' . CurrencyConverter::convertAndFormat($e->monthly_storage_purchase) . "</td></tr>";
                        }
                        $html .= "<tr><td>10% of monthly AWS usage for the first $0-$10k" . $this->printTieredSupport($e->storage_maintenance_tiered, 0) . "</td></tr>";
                        $html .= "<tr><td>7% of monthly AWS usage from $10k-$80k" . $this->printTieredSupport($e->storage_maintenance_tiered, 1) . "</td></tr>";
                        $html .= "<tr><td>5% of monthly AWS usage from $80k-$250k" . $this->printTieredSupport($e->storage_maintenance_tiered, 2) . "</td></tr>";
                        $html .= "<tr><td>3% of monthly AWS usage over $250k" . $this->printTieredSupport($e->storage_maintenance_tiered, 3) . "</td></tr>";
                        $html .= "<tr><td>" . htmlspecialchars($e->name) . " Storage Business Level Support per Month = " . CurrencyConverter::convertAndFormat($e->monthly_storage_maintenance) . "</td></tr>";
                        $html .= "<tr><td><i>" . htmlspecialchars($e->name) . " Storage Business Level Support ({$project->support_years} years) = " . CurrencyConverter::convertAndFormat($e->total_storage_maintenance) . "</i></td></tr></table>";
                    }
                }
            }
        }

        // Outbound network cost
        if ($this->hasCloudProvider($project->environments, 'AWS') && $this->hasBandwidth($project->environments, 'AWS')) {
            $html .= '<div style="height:5px"></div><table style="width: 100%; padding:5px"><tr><td colspan="2"><u><i>AWS Outbound Network Cost = (Price per GB/Month * Outbound Bandwidth (GB)) * Utilization * 12 mo/yr * # of years</i></u></td></tr>';
            foreach ($project->environments as $e) {
                if ($this->envHasCloudProvider($e, 'AWS') && $e->cloud_bandwidth) {
                    $bandwidth = "";
                    foreach ($e->bandwidths as $index => $b) {
                        $bandwidth .= "(" . CurrencyConverter::convertAndFormat($e->bandwidthCosts[$index]) . "/GB/month * " . number_format($b) . " GB)";
                        if ($index != count($e->bandwidths) - 1) {
                            $bandwidth .= " + ";
                        }
                    }
                    $html .= '<tr><td style="width:6%"></td><td style="width:94%"><div><b>' . $e->name . '</b> AWS Outbound Network Cost = (' . $bandwidth . ') * ' . $e->max_utilization . '% * 12 mo/yr * ' . $project->support_years . ' years = ' . CurrencyConverter::convertAndFormat($e->network_costs) . '</div></td></tr>';
                }
            }
            $html .= '</table>';
        }

        foreach ($project->environments as $index => $environment) {
            if ($environment->consolidationMap && $this->envHasCloudProvider($environment, "AWS") && $environment->isShowSupportCost()) {
                foreach ($environment->consolidationMap as $server) {
                    $html .= '<table style="width: 100%; padding:5px">';

                    if ($server->instance_type == "EC2") {
                        $html .= "<tr><td><strong><u>{$server->cloudServerDescription}</u></strong></td></tr>";
                    }
                    if ($server->instance_type == "RDS") {
                        $liName = $this->mapLIName($server->database_li_name);
                        $html .= "<tr><td><strong><u>{$environment->name} - {$server->payment_option['name']} - {$liName} Cost</u></strong></td></tr>";
                    }

                    // On Demand
                    if (
                        $server->onDemandHourly
                        && $this->isPaymentOption($server, "On-Demand")
                        && $environment->isShowSupportCost()
                    ) {
                        // Cost
                        $html .= "<tr><td>{$server->name} Total Cost = hourly cost * discount * 8,760 hrs/yr * {$project->support_years} years * # of instances</td></tr>";
                        $html .= "<tr><td><i>" . CurrencyConverter::convertAndFormat($server->onDemandHourly, null, 4) . "/hr * ". ($environment->discount_rate? $e->discount_rate : 0) . "% * 8,760 hrs/yr * " . ($environment->discount_rate? $e->discount_rate : 0) . "% * " . $project->support_years . " years * {$server->instances} = " . CurrencyConverter::convertAndFormat($server->onDemandTotal) . "</i></td></tr>";
                        // Business Cost
                        $html .= "<tr><td><strong><u>{$environment->name} {$server->payment_option['name']} Business Level Support Cost</u></strong></td></tr>";
                        $html .= "<tr><td>On-Demand Monthly Cost = " . CurrencyConverter::convertAndFormat($server->onDemandTotal) . " / " . $project->support_years * 12 . " months = " . CurrencyConverter::convertAndFormat($server->onDemandPerMonth) . "</td></tr>";
                        $html .= "<tr><td>10% of monthly AWS usage for the first $0-$10k" . $this->printTieredSupport($server->onDemandSupportTiers, 0) . "</td></tr>";
                        $html .= "<tr><td>7% of monthly AWS usage from $10k-$80k" . $this->printTieredSupport($server->onDemandSupportTiers, 1) . "</td></tr>";
                        $html .= "<tr><td>5% of monthly AWS usage from $80k-$250k" . $this->printTieredSupport($server->onDemandSupportTiers, 2) . "</td></tr>";
                        $html .= "<tr><td>3% of monthly AWS usage over $250k" . $this->printTieredSupport($server->onDemandSupportTiers, 3) . "</td></tr>";
                        $html .= "<tr><td>AWS Business Level Support per Month = " . CurrencyConverter::convertAndFormat($server->onDemandSupportPerMonth) . "</td></tr>";
                        $html .= "<tr><td><i>AWS Business Level Support ({$project->support_years} years) = " . CurrencyConverter::convertAndFormat($server->onDemandSupportTotal) . "</i></td></tr>";
                    }

                    // Partial Upfront
                    if (
                        $server->upfrontHourly
                        && $this->isPaymentOption($server, "Partial Upfront")
                        && $environment->isShowSupportCost()
                    ) {
                        // Cost

                        // 1 yr Partial Up Front
                        if ($this->isPaymentOption($server, "1 Yr Partial Upfront")) {
                            $html .= "<tr><td>" . $server->name . " Total Cost = ((Upfront Cost * discount) + (hourly cost * discount * 8,760 hrs/yr) * " . $project->support_years . " years) * # of instances</td></tr>";
                            $html .= "<tr><td><i>((" . CurrencyConverter::convertAndFormat($server->upfront) . " * " . ($environment->discount_rate? $e->discount_rate : 0) . "%) + (" . $server->upfrontHourly . "/hr * " . ($environment->discount_rate? $e->discount_rate : 0) . "% * 8,760 hrs/yr) *  " . $project->support_years . " years) * " . $server->instances . " = " . CurrencyConverter::convertAndFormat($server->calculatedUpfrontTotal) . "</i></td></tr>";
                        }

                        // 3 Yr Partial Up Front
                        if ($this->isPaymentOption($server, "3 Yr Partial Upfront")) {
                            $html .= "<tr><td>" . $server->name . " Total Cost = (Upfront Cost * discount + (hourly cost * discount * 8,760 hrs/yr * 3 years)) * # of instances</td></tr>";
                            $html .= "<tr><td><i>(" . CurrencyConverter::convertAndFormat($server->upfront) . " * " . ($environment->discount_rate? $e->discount_rate : 0) . "% + (" . $server->upfrontHourly . "/hr * " . ($environment->discount_rate? $e->discount_rate : 0) . "% * 8,760 hrs/yr *  3 years)) * " . $server->instances . " = " . CurrencyConverter::convertAndFormat($server->calculatedUpfrontTotal) . "</i></td></tr>";
                        }

                        // Business Cost
                        $html .= "<tr><td><strong><u>{$environment->name} {$server->payment_option['name']} Business Level Support Cost</u></strong></td></tr>";
                        $html .= "<tr><td><u>Upfront Cost = (Upfront Cost * discount) * # of Instances = (" . CurrencyConverter::convertAndFormat($server->upfront) . " * " . ($environment->discount_rate? $e->discount_rate : 0) . "%) * " . $server->instances . " = " . CurrencyConverter::convertAndFormat($server->calculatedUpfront) . "</u></td></tr>";
                        $html .= "<tr><td>10% of upfront AWS usage for the first $0-$10k" . $this->printTieredSupport($server->upfrontSupportTiers, 0) . "</td></tr>";
                        $html .= "<tr><td>7% of upfront AWS usage from $10k-$80k" . $this->printTieredSupport($server->upfrontSupportTiers, 1) . "</td></tr>";
                        $html .= "<tr><td>5% of upfront AWS usage from $80k-$250k" . $this->printTieredSupport($server->upfrontSupportTiers, 2) . "</td></tr>";
                        $html .= "<tr><td>3% of upfront AWS usage over $250k" . $this->printTieredSupport($server->upfrontSupportTiers, 3) . "</td></tr>";
                        $html .= "<tr><td><i>Upfront Business Level Support = " . CurrencyConverter::convertAndFormat($server->upfrontSupport) . "</i></td></tr>";
                        $html .= "<tr><td><u>Monthly Cost = ((hourly cost * discount) * 8,760 hrs/yr / 12 mos/yr) * # of instances = ((" . CurrencyConverter::convertAndFormat($server->upfrontHourly) . " * " . ($environment->discount_rate? $e->discount_rate : 0) . "%) * 8,760 hrs/yr / 12 mos) * " . $server->instances . " instances = " . CurrencyConverter::convertAndFormat(round($server->calculatedUpfrontPerYear / 12)) . "</u></td></tr>";
                        $html .= "<tr><td>10% of upfront AWS usage for the first $0-$10k" . $this->printTieredSupport($server->upfrontMonthlySupportTiers, 0) . "</td></tr>";
                        $html .= "<tr><td>7% of upfront AWS usage from $10k-$80k" . $this->printTieredSupport($server->upfrontMonthlySupportTiers, 1) . "</td></tr>";
                        $html .= "<tr><td>5% of upfront AWS usage from $80k-$250k" . $this->printTieredSupport($server->upfrontMonthlySupportTiers, 2) . "</td></tr>";
                        $html .= "<tr><td>3% of upfront AWS usage over $250k" . $this->printTieredSupport($server->upfrontMonthlySupportTiers, 3) . "</td></tr>";
                        $html .= "<tr><td>AWS Business Level Support per Month = " . CurrencyConverter::convertAndFormat($server->upfrontMonthlySupport) . "/mo</td></tr>";
                        $html .= "<tr><td><i>AWS Business Level Support (" . $project->support_years . " years) = " . CurrencyConverter::convertAndFormat($server->upfrontBusinessSupport) . "</i></td></tr>";
                        $html .= "<tr><td><i>TOTAL " . $project->support_years . " year Business Level Support = " . CurrencyConverter::convertAndFormat($server->upfrontSupport) . " + " . CurrencyConverter::convertAndFormat($server->upfrontBusinessSupport) . " = " . CurrencyConverter::convertAndFormat($server->upfrontTotalSupport) . "</i></td></tr>";
                    }

                    // All Upfront
                    if (
                        $server->upfront
                        && $this->isPaymentOption($server, "All Upfront")
                        && $environment->isShowSupportCost()
                    ) {
                        // Cost

                        // 1 Yr All Upfront
                        if ($this->isPaymentOption($server, "1 Yr All Upfront")) {
                            $html .= "<tr><td>" . $server->name . " Total Cost = upfront cost * discount * " . $project->support_years . " years * # of instances</td></tr>";
                            $html .= "<tr><td><i>" . CurrencyConverter::convertAndFormat($server->upfront) . " * " . ($environment->discount_rate? $e->discount_rate : 0) . "% * " . $project->support_years . " years * " . $server->instances . " = " . CurrencyConverter::convertAndFormat($server->calculatedUpfrontTotal) . "</i></td></tr>";
                        }

                        // 3 Yr All Upfront
                        if ($this->isPaymentOption($server, "3 Yr All Upfront")) {
                            $html .= "<tr><td>" . $server->name . " Total Cost = upfront cost * discount * # of instances</td></tr>";
                            $html .= "<tr><td><i>" . CurrencyConverter::convertAndFormat($server->upfront) . " * " . ($environment->discount_rate? $e->discount_rate : 0) . "% * " . $server->instances . " = " . CurrencyConverter::convertAndFormat($server->calculatedUpfrontTotal) . "</i></td></tr>";
                        }

                        // Business Cost
                        $html .= "<tr><td><strong><u>{$environment->name} {$server->payment_option['name']} Business Level Support Cost</u></strong></td></tr>";
                        $html .= "<tr><td><u>Upfront Cost = ((Upfront Cost * discount) * # of Instances) = (" . CurrencyConverter::convertAndFormat($server->upfront) . " * " . ($environment->discount_rate? $e->discount_rate : 0) . "%) * " . $server->instances . " = " . CurrencyConverter::convertAndFormat($server->calculatedUpfront) . "</u></td></tr>";
                        $html .= "<tr><td>10% of upfront AWS usage for the first $0-$10k" . $this->printTieredSupport($server->upfrontSupportTiers, 0) . "</td></tr>";
                        $html .= "<tr><td>7% of upfront AWS usage from $10k-$80k" . $this->printTieredSupport($server->upfrontSupportTiers, 1) . "</td></tr>";
                        $html .= "<tr><td>5% of upfront AWS usage from $80k-$250k" . $this->printTieredSupport($server->upfrontSupportTiers, 2) . "</td></tr>";
                        $html .= "<tr><td>3% of upfront AWS usage over $250k" . $this->printTieredSupport($server->upfrontSupportTiers, 3) . "</td></tr>";
                        $html .= "<tr><td><i>Upfront Business Level Support = " . CurrencyConverter::convertAndFormat($server->upfrontSupport) . "</i></td></tr>";
                    }

                    // No Upfront
                    if (
                        $server->upfrontHourly
                        && $this->isPaymentOption($server, "No Upfront")
                        && $environment->isShowSupportCost()
                    ) {
                        // Cost

                        // 1 Yr No Upfront
                        if ($this->isPaymentOption($server, "1 Yr No Upfront")) {
                            $html .= "<tr><td>" . $server->name . " Total Cost = hourly cost * 8,760 hrs/yr * discount * " . $project->support_years . " years * # of instances</td></tr>";
                            $html .= "<tr><td><i>" . CurrencyConverter::convertAndFormat($server->upfrontHourly, null, 4) . "/hr * " . ($environment->discount_rate? $e->discount_rate : 0) . "% * 8,760 hrs/yr * " . $project->support_years . " years * " . $server->instances . " = " . CurrencyConverter::convertAndFormat($server->calculatedUpfrontTotal) . "</i></td></tr>";
                        }

                        // 3 Yr No Upfront
                        if ($this->isPaymentOption($server, "3 Yr No Upfront")) {
                            $html .= "<tr><td>" . $server->name . " Total Cost = hourly cost * 8,760 hrs/yr * discount * 3 years * # of instances</td></tr>";
                            $html .= "<tr><td><i>" . CurrencyConverter::convertAndFormat($server->upfrontHourly, null, 4) . "/hr * " . ($environment->discount_rate? $e->discount_rate : 0) . "% * 8,760 hrs/yr * 3 years * " . $server->instances . " = " . CurrencyConverter::convertAndFormat($server->calculatedUpfrontTotal) . "</i></td></tr>";
                        }

                        // Business Cost
                        $html .= "<tr><td><strong><u>{$environment->name} {$server->payment_option['name']} Business Level Support Cost</u></strong></td></tr>";
                        $html .= "<tr><td><u>Monthly Cost = ((hourly cost * discount) * 8,760 hrs/yr / 12 mos/yr) * # of instances = ((" . CurrencyConverter::convertAndFormat($server->upfrontHourly) . " * " . ($environment->discount_rate? $e->discount_rate : 0) . "%) * 8,760 hrs/yr / 12 mos) * " . $server->instances . " instances = " . CurrencyConverter::convertAndFormat(round($server->calculatedUpfrontPerYear / 12)) . "</u></td></tr>";
                        $html .= "<tr><td>10% of upfront AWS usage for the first $0-$10k" . $this->printTieredSupport($server->upfrontMonthlySupportTiers, 0) . "</td></tr>";
                        $html .= "<tr><td>7% of upfront AWS usage from $10k-$80k" . $this->printTieredSupport($server->upfrontMonthlySupportTiers, 1) . "</td></tr>";
                        $html .= "<tr><td>5% of upfront AWS usage from $80k-$250k" . $this->printTieredSupport($server->upfrontMonthlySupportTiers, 2) . "</td></tr>";
                        $html .= "<tr><td>3% of upfront AWS usage over $250k" . $this->printTieredSupport($server->upfrontMonthlySupportTiers, 3) . "</td></tr>";
                        $html .= "<tr><td>AWS Business Level Support per Month = " . CurrencyConverter::convertAndFormat($server->upfrontMonthlySupport) . "/mo</td></tr>";
                        $html .= "<tr><td><i>AWS Business Level Support (" . $project->support_years . " years) = " . CurrencyConverter::convertAndFormat($server->upfrontBusinessSupport) . "</i></td></tr>";
                    }

                    $html .= "</table><br>";
                }

                if (count($environment->consolidationMap) > 1 && $environment->isShowSupportCost()) {
                    if ($this->isPaymentOption($server, "On-Demand")) {
                        $html .= '<table style="width: 100%; padding:5px"><tr><td><b><u>' . $environment->name . ' ' . $environment->payment_option['name'] . ' Total Cost</u></b></td></tr>';
                        $text = "{$environment->payment_option['name']} Purchase Cost = ";
                        foreach ($environment->consolidationMap as $index => $server) {
                            $text .= CurrencyConverter::convertAndFormat($server->onDemandTotal) . " ";
                            if ($index != count($environment->consolidationMap) - 1) {
                                $text .= "+ ";
                            }
                        }
                        $text .= "= " . CurrencyConverter::convertAndFormat($environment->onDemandPurchase);
                        $html .= "<tr><td>{$text}</td></tr>";
                        $text = "{$environment->payment_option['name']} Support Cost = ";
                        foreach ($environment->consolidationMap as $index => $server) {
                            $text .= CurrencyConverter::convertAndFormat($server->onDemandSupportTotal) . " ";
                            if ($index != count($environment->consolidationMap) - 1) {
                                $text .= "+ ";
                            }
                        }
                        $text .= "= " . CurrencyConverter::convertAndFormat($environment->onDemandMaintenance);
                        $html .= "<tr><td>{$text}</td></tr></table>";
                    } else {
                        $html .= '<table style="width: 100%; padding:5px"><tr><td><b><u>' . $environment->name . ' ' . $environment->payment_option["name"]. ' Total Cost</u></b></td></tr>';
                        $text = "{$environment->payment_option['name']} Purchase Cost = ";
                        foreach ($environment->consolidationMap as $index => $server) {
                            $text .= CurrencyConverter::convertAndFormat($server->calculatedUpfrontTotal ?? 0.00) . " ";
                            if ($index != count($environment->consolidationMap) - 1) {
                                $text .= "+ ";
                            }
                        }
                        $text .= "= " . CurrencyConverter::convertAndFormat($environment->upfrontPurchase);
                        $html .= "<tr><td>{$text}</td></tr>";
                        $text = "{$environment->payment_option['name']} Support Cost = ";
                        foreach ($environment->consolidationMap as $index => $server) {
                            $text .= CurrencyConverter::convertAndFormat($server->upfrontTotalSupport ?? 0.00) . " ";
                            if ($index != count($environment->consolidationMap) - 1) {
                                $text .= "+ ";
                            }
                        }
                        $text .= "= " . CurrencyConverter::convertAndFormat($environment->upfrontMaintenance);
                        $html .= "<tr><td>{$text}</td></tr></table>";
                    }
                }
                $html .= "<br>";
            }

            if ($this->envHasCloudProvider($environment, "AWS") && $environment->isShowSupportCost(Environment::CLOUD_SUPPORT_COSTS_CUSTOM)) {
                $html .= '<br/><table style="width: 100%; padding:5px"><tr><td><strong><u>Custom Support Cost</u></strong></td></tr>';
                $html .= "<tr><td>" . CurrencyConverter::convertAndFormat($environment->total_hardware_maintenance_per_year) ."/year * {$project->support_years} years = " . CurrencyConverter::convertAndFormat($environment->total_hardware_maintenance_per_year * $project->support_years) . "</td></tr></table>";
            }
        }

        // Start Azure
        if ($this->hasCloudProvider($project->environments, 'Azure')) {
            $html .= '<br/><br/>';

            $html .= '<div><strong><u>MS Azure – Virtual Machines, Storage and Professional Direct Support Costs (# of years) - ' . $this->getCloudEnvironmentPaymentOption($project->environments, 'Azure') . ' Instances</u></strong></div>';
            if ($this->hasStorage($project->environments)) {
                $html .= '<br/>';

                $hasAdsStorage = false;
                $hasAzureStorage = false;
                foreach($project->environments as $e) {
                    if ($e->environmentType->name == "Cloud" && $this->envHasCloudProvider($e, "Azure") && !$this->cloudHelper()->environmentHasAds($e)) {
                        $hasAzureStorage = true;
                    } else if ($e->environmentType->name == "Cloud" && $this->envHasCloudProvider($e, "Azure") && $this->cloudHelper()->environmentHasAds($e)) {
                        $hasAdsStorage = true;
                    }
                }
                if ($hasAzureStorage) {
                    $html .= '<table style="width: 100%; padding:5px"><tr><td colspan="2"><u><i>';
                    $html .= 'Azure Storage Cost = ((Price per Month at Disk Size * discount) * number disks) * Utilization * 12 mo/yr * # of years';
                    $html .= '</i></u></td></tr>';
                    foreach ($project->environments as $e) {
                        if ($this->envHasCloudProvider($e, "Azure") && !$this->cloudHelper()->environmentHasAds($e)) {
                            $html .= '<tr><td style="width:6%"></td><td style="width:94%"><div><b>' . $e->name . '</b> ' . $e->name . ' ' . $e->storageType . ' Cost = ((' . CurrencyConverter::convertAndFormat($e->monthPrice) . '/' . $e->diskSize . 'GB Disk/month * ' . ($e->discount_rate? $e->discount_rate : 0) . '%) * '. $e->storageDisks . ' Disks) * ' . $e->max_utilization . '% * 12 mo/yr * ' . $project->support_years . ' years = ' . CurrencyConverter::convertAndFormat($e->storage_purchase_price) . '</div></td></tr>';
                            if (intval($existingEnvironment->iops)) {
                                $html .= '<tr><td style="width:6%"></td><td style="width:94%"><div>'
                                    . 'Azure ' . $e->storageType . ' (' . number_format($e->storageDisks, 2) . ' disks * ' . number_format($e->iopsPerDisk, 0) . ' IOPS per disk) = ' . number_format($e->totalIops, 0) . ' IOPS'
                                    . '</div></td></tr>';
                                if ($e->iopsSurplus > 0) {
                                    $html .= '<tr><td style="width:6%"></td><td style="width:94%"><div>'
                                        . 'IOPS constraint met. ' . number_format($e->totalIops, 0) . ' IOPS - ' . number_format($existingEnvironment->iops, 0)
                                        . ' IOPS = ' . number_format($e->iopsSurplus, 0) . ' IOPS surplus.'
                                        . '</div></td></tr>';
                                }

                                if ($e->iopsDeficit > 0) {
                                    $html .= '<tr><td style="width:6%"></td><td style="width:94%"><div>'
                                        . 'IOPS deficit = ' . number_format($existingEnvironment->iops, 0) . ' IOPS - ' . number_format($e->totalIops, 0)
                                        . ' IOPS = ' . number_format($e->iopsDeficit, 0) . ' IOPS'
                                        . '</div></td></tr>';
                                    $html .= '<tr><td style="width:6%"></td><td style="width:94%"><div>'
                                        . 'Additional storage required to meet IOPS constraint:'
                                        . '</div></td></tr>';
                                    $html .= '<tr><td style="width:6%"></td><td style="width:94%"><div>'
                                        . $e->storageType . ' Cost for additional IOPS = (' . CurrencyConverter::convertAndFormat($e->monthPrice) . ' disk/month * ' . number_format($e->iopsDeficit, 0) . ' IOPS / '
                                        . number_format($e->iopsPerDisk, 0) . ' IOPS/disk) = ' . number_format($e->iopsDisksNeeded, 2) . ' disks) * '
                                        . $e->max_utilization . '% * 12 mo/yr * ' . $project->support_years . ' years = ' . CurrencyConverter::convertAndFormat($e->iops_purchase_price)
                                        . '</div></td></tr>';
                                    $html .= '<tr><td style="width:6%"></td><td style="width:94%"><div>'
                                        . 'TOTAL 3 year Storage Cost = ' . CurrencyConverter::convertAndFormat($e->storage_purchase_price) . ' + ' . CurrencyConverter::convertAndFormat($e->iops_purchase_price) . '= ' . CurrencyConverter::convertAndFormat($e->storage_purchase_price + $e->iops_purchase_price)
                                        . '</div></td></tr>';
                                }
                            }
                        }
                    }
                    $html .= '</table>';
                }
                if ($hasAdsStorage) {
                    $html .= '<table style="width: 100%; padding:5px"><tr><td colspan="2"><u><i>';
                    $html .= 'Azure Database Service Storage Cost';
                    $html .= '</i></u></td></tr>';
                    foreach ($project->environments as $e) {
                        if ($e->environmentType->name == "Cloud" && $this->envHasCloudProvider($e, "Azure") && $this->cloudHelper()->environmentHasAds($e)) {

                            $hasIncludedStorage = $e->ads_free_storage && floatval($e->ads_free_storage) > 0;

                            /**
                             * Master ADS storage formula
                             * @param Environment $_e
                             * @return string
                             */
                            $printStorageCost = function($_e) use ($project) {
                                return rformat($_e->ads_additional_storage) . " GB"
                                    . " * {" . CurrencyConverter::convertAndFormat($_e->monthPrice) . "}/GB month"
                                    . " * {$_e->max_utilization}% Utilization"
                                    . " * 12 mo/yr * {$project->support_years} years"
                                    . " = " . CurrencyConverter::convertAndFormat($_e->storage_purchase_price);
                            };

                            if ($hasIncludedStorage) {
                                // Need to factor in some degree of free storage
                                $rows = [
                                    "Azure Database Service Included Storage  = ". rformat($e->ads_free_storage). " GB/month"
                                ];

                                if ($e->ads_additional_storage && floatval($e->ads_additional_storage) > 0) {
                                    // Need to charge extra
                                    $rows[] = "Azure Database Service Additional Storage = " . rformat($e->ads_total_storage) . " GB - " . rformat($e->ads_free_storage) . " GB = " . rformat($e->ads_additional_storage) . " GB";
                                    $rows[] = "Azure Database Service Additional Storage Cost = " . $printStorageCost($e);
                                } else {
                                    // Included storage covers cost
                                    $rows[] = "Storage constraint met. " . rformat($e->ads_free_storage) . ' GB - ' . rformat($e->ads_total_storage) . ' GB = ' . rformat($e->ads_storage_surplus) . ' GB Storage Surplus.';
                                    $rows[] = "Azure Database Service Additional Storage Cost = " . CurrencyConverter::convertAndFormat(0);
                                }
                            } else {
                                // Just charge cost per GB Month
                                $rows = [
                                    "Azure Database Service Storage Cost = " . $printStorageCost($e)
                                ];
                            }

                            $rows = collect(["<strong>$e->name</strong>"])->merge(collect($rows))->toArray();

                            foreach($rows as $row) {
                                $html .= '<tr><td style="width:6%;"></td><td style="width:94%;"><div>' . $row . '</div></td></tr>';
                            }
                        }
                    }
                    $html .= '</table>';
                }
            }
        }

        if ($this->hasCloudProvider($project->environments, 'Azure') && $this->hasBandwidth($project->environments, 'Azure')) {
            $html .= '<div style="height:5px"></div><table style="width: 100%; padding:5px"><tr><td colspan="2"><u><i>Azure Outbound Network Cost = (Price per GB/Month * Outbound Bandwidth (GB)) * Utilization * 12 mo/yr * # of years</i></u></td></tr>';
            foreach ($project->environments as $e) {
                if ($this->envHasCloudProvider($e, 'Azure') && $e->cloud_bandwidth) {
                    $bandwidth = "";
                    foreach ($e->bandwidths as $index => $b) {
                        $bandwidth .= "(" . CurrencyConverter::convertAndFormat($e->bandwidthCosts[$index]) . "/GB/month * " . number_format($b) . " GB)";
                        if ($index != count($e->bandwidths) - 1) {
                            $bandwidth .= " + ";
                        }
                    }
                    $html .= '<tr><td style="width:6%"></td><td style="width:94%"><div><b>' . $e->name . '</b> Azure Outbound Network Cost = (' . $bandwidth . ') * ' . $e->max_utilization . '% * 12 mo/yr * ' . $project->support_years . ' years = ' . CurrencyConverter::convertAndFormat($e->network_costs) . '</div></td></tr>';
                }
            }
            $html .= '</table><br/>';
        }

        foreach ($project->environments as $index => $environment) {
            $discountText = ($environment->discount_rate? $e->discount_rate : 0) . "%";
            if ($environment->consolidationMap && $this->envHasCloudProvider($environment, "Azure")) {
                foreach ($environment->consolidationMap as $server) {
                    $serverName = $server->instance_type == 'ADS' ? $server->ads_description : $server->cloudServerDescription;
                    if ($server->onDemandHourly) {
                        $html .= '<br/><table style="width: 100%; padding:5px"><tr><td>' . "<strong><u>{$server->cloudServerDescription}</u></strong></td></tr>";
                        $html .= "<tr><td>{$serverName} Total Cost = ((hourly cost * discount) * 8,760 hrs/yr * {$project->support_years} years * utilization) * # of instances</td></tr>";
                        $html .= "<tr><td><i>((" . CurrencyConverter::convertAndFormat($server->onDemandHourly, null, 4) . "/hr * " . $discountText . ") * 8,760 hrs/yr * " . $project->support_years . " years * {$environment->max_utilization}%) * {$server->instances} = " . CurrencyConverter::convertAndFormat($server->onDemandTotal) . "</i></td></tr></table>";
                    }
                    if ($server->upfrontHourly) {
                        $html .= '<br/><table style="width: 100%; padding:5px"><tr><td>' . "<strong><u>{$server->cloudServerDescription}</u></strong></td></tr>";
                        $html .= "<tr><td>" . $serverName . " Total Cost = ((hourly cost * discount) * 8,760 hrs/yr * " . $project->support_years . " years) * # of instances</td></tr>";
                        $html .= "<tr><td><i>((" . $server->upfrontHourly . "/hr * " . $discountText . ") * 8,760 hrs/yr * " . $project->support_years . " years) * " . $server->instances . " = " . CurrencyConverter::convertAndFormat($server->calculatedUpfrontTotal) . "</i></td></tr></table>";
                    }
                    if ($server->upfront3Hourly) {
                        $html .= '<br/><table style="width: 100%; padding:5px"><tr><td>' . "<strong><u>{$server->cloudServerDescription}</u></strong></td></tr>";
                        $html .= "<tr><td>" . $serverName . " Total Cost = ((hourly cost * discount) * 8,760 hrs/yr * " . $project->support_years . " years) * # of instances</td></tr>";
                        $html .= "<tr><td><i>((" . $server->upfront3Hourly . "/hr * " . $discountText . ") * 8,760 hrs/yr * " . $project->support_years . " years) * " . $server->instances . " = " . CurrencyConverter::convertAndFormat($server->calculatedUpfront3Total) . "</i></td></tr></table>";
                    }
                }

                if ($environment->isShowSupportCost()) {
                    $html .= '<br/><table style="width: 100%; padding:5px"><tr><td><strong><u>Azure Professional Direct Support Cost</u></strong></td></tr>';
                    $html .= "<tr><td>(" . CurrencyConverter::convertAndFormat(1000) ."/month * " . ($environment->discount_rate? $e->discount_rate : 0) . "%) * 12 months/year * {$project->support_years} years = " . CurrencyConverter::convertAndFormat(1000 * 12 * $project->support_years) . "</td></tr></table>";
                }

                if ($environment->isShowSupportCost(Environment::CLOUD_SUPPORT_COSTS_CUSTOM)) {
                    $html .= '<br/><table style="width: 100%; padding:5px"><tr><td><strong><u>Azure Professional Direct Custom Support Cost</u></strong></td></tr>';
                    $html .= "<tr><td>" . CurrencyConverter::convertAndFormat($environment->total_hardware_maintenance_per_year) ."/year * {$project->support_years} years = " . CurrencyConverter::convertAndFormat($environment->total_hardware_maintenance_per_year * $project->support_years) . "</td></tr></table>";
                }

                if (count($environment->consolidationMap) > 1 && $environment->isShowSupportCost()) {
                    $purchaseOption = str_replace(' With Azure Hybrid Benefit', '', $environment['payment_option']['name']);
                    $html .= '<br/><table style="width: 100%; padding:5px"><tr><td><b><u>' . $environment->name . ' ' . $purchaseOption . ' Total Cost</u></b></td></tr>';
                    $text = "{$purchaseOption} Purchase Cost = ";
                    foreach ($environment->consolidationMap as $index => $server) {
                        if (isset($server->onDemandTotal)) {
                            $text .= CurrencyConverter::convertAndFormat($server->onDemandTotal) . " ";
                            if ($index != count($environment->consolidationMap) - 1) {
                                $text .= "+ ";
                            }
                        } else if (isset($server->calculatedUpfrontTotal)) {
                            $text .= CurrencyConverter::convertAndFormat($server->calculatedUpfrontTotal) . " ";
                            if ($index != count($environment->consolidationMap) - 1) {
                                $text .= "+ ";
                            }
                        } else if (isset($server->calculatedUpfront3Total)) {
                            $text .= CurrencyConverter::convertAndFormat($server->calculatedUpfront3Total) . " ";
                            if ($index != count($environment->consolidationMap) - 1) {
                                $text .= "+ ";
                            }
                        }
                    }
                    if ($environment->onDemandPurchase) {
                        $text .= "= " . CurrencyConverter::convertAndFormat($environment->onDemandPurchase);
                    } else if ($environment->upfrontPurchase) {
                        $text .= "= " . CurrencyConverter::convertAndFormat($environment->upfrontPurchase);
                    } else if ($environment->upfront3Purchase) {
                        $text .= "= " . CurrencyConverter::convertAndFormat($environment->upfront3Purchase);
                    }
                    $html .= "<tr><td>{$text}</td></tr>";
                }
                if (count($project->environments) != $index + 1) {
                    $html .= "</br>";
                }
            }
        }

        // Start Google
        if ($this->hasCloudProvider($project->environments, 'Google')) {
            $html .= '<br/><br/>';

            $html .= '<div><strong><u>Google Cloud Platform – Compute Engine, Storage and Production Role-Based Support (# of years) - ' . $this->getCloudEnvironmentPaymentOption($project->environments, 'Google') . ' Instances</u></strong></div>';
            if ($this->hasStorage($project->environments)) {
                $html .= '<br/>';

                $hasGoogleStorage = false;
                foreach($project->environments as $e) {
                    if ($e->environmentType->name == "Cloud" && $this->envHasCloudProvider($e, "Google")) {
                        $hasGoogleStorage = true;
                    }
                }
                if ($hasGoogleStorage) {
                    $html .= '<table style="width: 100%; padding:5px"><tr><td colspan="2"><u><i>';
                    $html .= 'Google Storage Cost = ((Price per GB-Month * discount) * provisioned storage (GB)) * Utilization * 12 mo/yr * # of years';
                    $html .= '</i></u></td></tr>';
                    foreach ($project->environments as $e) {
                        if ($this->envHasCloudProvider($e, "Google")) {
                            $html .= '<tr><td style="width:6%"></td><td style="width:94%"><div><b>' . $e->name . '</b> ' . $e->name . ' ' . $e->storageType . ' Cost = ((' . CurrencyConverter::convertAndFormat($e->gbMonthPrice) . '/GB/month * ' . ($e->discount_rate? $e->discount_rate : 0) . '%) * '. number_format($e->total_storage * 1000, 0) . ' GB) * ' . $e->max_utilization . '% * 12 mo/yr * ' . $project->support_years . ' years = ' . CurrencyConverter::convertAndFormat($e->storage_purchase_price) . '</div></td></tr>';

                            if (intval($existingEnvironment->iops)) {
                                $html .= '<tr><td style="width:6%"></td><td style="width:94%"><div>'
                                    . 'Google ' . $e->storageType . ' (' . number_format($e->storageDisks, 2) . ' disks * ' . number_format($e->iopsPerDisk, 0) . ' IOPS per disk) = ' . number_format($e->totalIops, 0) . ' IOPS'
                                    . '</div></td></tr>';
                                if ($e->iopsSurplus > 0) {
                                    $html .= '<tr><td style="width:6%"></td><td style="width:94%"><div>'
                                        . 'IOPS constraint met. ' . number_format($e->totalIops, 0) . ' IOPS - ' . number_format($existingEnvironment->iops, 0)
                                        . ' IOPS = ' . number_format($e->iopsSurplus, 0) . ' IOPS surplus.'
                                        . '</div></td></tr>';
                                }

                                if ($e->iopsDeficit > 0) {
                                    $html .= '<tr><td style="width:6%"></td><td style="width:94%"><div>'
                                        . 'IOPS deficit = ' . number_format($existingEnvironment->iops, 0) . ' IOPS - ' . number_format($e->totalIops, 0)
                                        . ' IOPS = ' . number_format($e->iopsDeficit, 0) . ' IOPS'
                                        . '</div></td></tr>';
                                    $html .= '<tr><td style="width:6%"></td><td style="width:94%"><div>'
                                        . 'Additional storage required to meet IOPS constraint:'
                                        . '</div></td></tr>';
                                    $html .= '<tr><td style="width:6%"></td><td style="width:94%"><div>'
                                        . $e->storageType . ' Cost for additional IOPS = (' . CurrencyConverter::convertAndFormat($e->monthPrice) . ' disk/month * ' . number_format($e->iopsDeficit, 0) . ' IOPS / '
                                        . number_format($e->iopsPerDisk, 0) . ' IOPS/disk) = ' . number_format($e->iopsDisksNeeded, 2) . ' disks) * '
                                        . $e->max_utilization . '% * 12 mo/yr * ' . $project->support_years . ' years = ' . CurrencyConverter::convertAndFormat($e->iops_purchase_price)
                                        . '</div></td></tr>';
                                    $html .= '<tr><td style="width:6%"></td><td style="width:94%"><div>'
                                        . 'TOTAL 3 year Storage Cost = ' . CurrencyConverter::convertAndFormat($e->storage_purchase_price, 0) . ' + ' . CurrencyConverter::convertAndFormat($e->iops_purchase_price) . '= ' . CurrencyConverter::convertAndFormat($e->storage_purchase_price + $e->iops_purchase_price)
                                        . '</div></td></tr>';
                                }
                            }
                        }
                    }
                    $html .= '</table>';
                }
            }
        }

        if ($this->hasCloudProvider($project->environments, 'Google') && $this->hasBandwidth($project->environments, 'Google')) {
            $html .= '<div style="height:5px"></div><table style="width: 100%; padding:5px"><tr><td colspan="2"><u><i>Google Outbound Network Cost = (Price per GB/Month * Outbound Bandwidth (GB)) * Utilization * 12 mo/yr * # of years</i></u></td></tr>';
            foreach ($project->environments as $e) {
                if ($this->envHasCloudProvider($e, 'Google') && $e->cloud_bandwidth) {
                    $bandwidth = "";
                    foreach ($e->bandwidths as $index => $b) {
                        $bandwidth .= "(" . CurrencyConverter::convertAndFormat($e->bandwidthCosts[$index]) . "/GB/month * " . number_format($b) . " GB)";
                        if ($index != count($e->bandwidths) - 1) {
                            $bandwidth .= " + ";
                        }
                    }
                    $html .= '<tr><td style="width:6%"></td><td style="width:94%"><div><b>' . $e->name . '</b> Google Outbound Network Cost = (' . $bandwidth . ') * ' . $e->max_utilization . '% * 12 mo/yr * ' . $project->support_years . ' years = ' . CurrencyConverter::convertAndFormat($e->network_costs) . '</div></td></tr>';
                }
            }
            $html .= '</table><br/>';
        }

        foreach ($project->environments as $index => $environment) {
            $discountText = ($environment->discount_rate? $e->discount_rate : 0) . "%";
            if ($environment->consolidationMap && $this->envHasCloudProvider($environment, "Google")) {
                foreach ($environment->consolidationMap as $server) {
                    $serverName = $server->cloudServerDescription;
                    if ($server->onDemandHourly) {
                        $html .= '<br/><table style="width: 100%; padding:5px"><tr><td>' . "<strong><u>{$server->cloudServerDescription}</u></strong></td></tr>";
                        $html .= "<tr><td>{$serverName} Total Cost = ((hourly cost * discount) * 8,760 hrs/yr * {$project->support_years} years * utilization) * # of instances</td></tr>";
                        $html .= "<tr><td><i>((" . CurrencyConverter::convertAndFormat($server->onDemandHourly, null, 4) . "/hr * " . $discountText . ") * 8,760 hrs/yr * " . $project->support_years . " years * {$environment->max_utilization}%) * {$server->instances} = " . CurrencyConverter::convertAndFormat($server->onDemandTotal) . "</i></td></tr></table>";
                    }
                    if ($server->upfrontHourly) {
                        $html .= '<br/><table style="width: 100%; padding:5px"><tr><td>' . "<strong><u>{$server->cloudServerDescription}</u></strong></td></tr>";
                        $html .= "<tr><td>" . $serverName . " Total Cost = ((hourly cost * discount) * 8,760 hrs/yr * " . $project->support_years . " years) * # of instances</td></tr>";
                        $html .= "<tr><td><i>((" . $server->upfrontHourly . "/hr * " . $discountText . ") * 8,760 hrs/yr * " . $project->support_years . " years) * " . $server->instances . " = " . CurrencyConverter::convertAndFormat($server->calculatedUpfrontTotal) . "</i></td></tr></table>";
                    }
                    if ($server->upfront3Hourly) {
                        $html .= '<br/><table style="width: 100%; padding:5px"><tr><td>' . "<strong><u>{$server->cloudServerDescription}</u></strong></td></tr>";
                        $html .= "<tr><td>" . $serverName . " Total Cost = ((hourly cost * discount) * 8,760 hrs/yr * " . $project->support_years . " years) * # of instances</td></tr>";
                        $html .= "<tr><td><i>((" . $server->upfront3Hourly . "/hr * " . $discountText . ") * 8,760 hrs/yr * " . $project->support_years . " years) * " . $server->instances . " = " . CurrencyConverter::convertAndFormat($server->calculatedUpfront3Total) . "</i></td></tr></table>";
                    }
                }

                if ($environment->isShowSupportCost()) {
                    $html .= '<br/><table style="width: 100%; padding:5px"><tr><td><strong><u>Google Production Support Cost</u></strong></td></tr>';
                    $html .= "<tr><td>(" . CurrencyConverter::convertAndFormat(250) . "/month * " . ($environment->discount_rate? $e->discount_rate : 0) . "%) * 12 months/year * users [1 assumed] * {$project->support_years} years = " . CurrencyConverter::convertAndFormat(250 * 12 * $project->support_years) . "</td></tr></table>";
                }

                if ($environment->isShowSupportCost(Environment::CLOUD_SUPPORT_COSTS_CUSTOM)) {
                    $html .= '<br/><table style="width: 100%; padding:5px"><tr><td><strong><u>Google Production Support Cost</u></strong></td></tr>';
                    $html .= "<tr><td>" . CurrencyConverter::convertAndFormat($environment->total_hardware_maintenance_per_year) . "/year * users [1 assumed] * {$project->support_years} years = " . CurrencyConverter::convertAndFormat($environment->total_hardware_maintenance_per_year * $project->support_years) . "</td></tr></table>";
                }

                if (count($environment->consolidationMap) > 1 && $environment->isShowSupportCost()) {
                    $purchaseOption = $environment['payment_option']['name'];
                    $html .= '<br/><table style="width: 100%; padding:5px"><tr><td><b><u>' . $environment->name . ' ' . $purchaseOption . ' Total Cost</u></b></td></tr>';
                    $text = "{$purchaseOption} Purchase Cost = ";
                    foreach ($environment->consolidationMap as $index => $server) {
                        if (isset($server->onDemandTotal)) {
                            $text .= CurrencyConverter::convertAndFormat($server->onDemandTotal) . " ";
                            if ($index != count($environment->consolidationMap) - 1) {
                                $text .= "+ ";
                            }
                        } else if (isset($server->calculatedUpfrontTotal)) {
                            $text .= CurrencyConverter::convertAndFormat($server->calculatedUpfrontTotal) . " ";
                            if ($index != count($environment->consolidationMap) - 1) {
                                $text .= "+ ";
                            }
                        } else if (isset($server->calculatedUpfront3Total)) {
                            $text .= CurrencyConverter::convertAndFormat($server->calculatedUpfront3Total) . " ";
                            if ($index != count($environment->consolidationMap) - 1) {
                                $text .= "+ ";
                            }
                        }
                    }
                    if ($environment->onDemandPurchase) {
                        $text .= "= " . CurrencyConverter::convertAndFormat($environment->onDemandPurchase);
                    } else if ($environment->upfrontPurchase) {
                        $text .= "= " . CurrencyConverter::convertAndFormat($environment->upfrontPurchase);
                    } else if ($environment->upfront3Purchase) {
                        $text .= "= " . CurrencyConverter::convertAndFormat($environment->upfront3Purchase);
                    }
                    $html .= "<tr><td>{$text}</td></tr>";
                }
                if (count($project->environments) != $index + 1) {
                    $html .= "</br>";
                }
            }
        }


        // Start IBM PVS
        if ($this->hasCloudProvider($project->environments, 'IBMPVS')) {
            $html .= '<br/><br/>';

            $html .= '<div><strong><u>IBM PVS – Compute and Storage (# of years) - ' . $this->getCloudEnvironmentPaymentOption($project->environments, 'IBMPVS') . ' Instances</u></strong></div>';
            if ($this->hasStorage($project->environments)) {
                $html .= '<br/>';

                $hasIBMPVSStorage = false;
                foreach($project->environments as $e) {
                    if ($e->environmentType->name == "Cloud" && $this->envHasCloudProvider($e, "IBMPVS")) {
                        $hasIBMPVSStorage = true;
                    }
                }
                if ($hasIBMPVSStorage) {
                    $html .= '<table style="width: 100%; padding:5px"><tr><td colspan="2"><u><i>';
                    $html .= 'IBM PVS Storage Cost = ((Price per GB-Month * discount) * provisioned storage (GB)) * 12 mo/yr * # of years';
                    $html .= '</i></u></td></tr>';
                    foreach ($project->environments as $e) {
                        if ($this->envHasCloudProvider($e, "IBMPVS")) {
                            $html .= '<tr><td style="width:6%"></td><td style="width:94%"><div><b>' . $e->name . '</b> ' . $e->name . ' ' . $e->storageType . ' Cost = ((' . CurrencyConverter::convertAndFormat($e->gbMonthPrice) . '/GB/month * ' . ($e->discount_rate? $e->discount_rate : 0) . '%) * '. number_format($e->total_storage * 1000, 0) . ' GB) * 12 mo/yr * ' . $project->support_years . ' years = ' . CurrencyConverter::convertAndFormat($e->storage_purchase_price) . '</div></td></tr>';
                        }
                    }
                    $html .= '</table>';
                }
            }
        }

        if ($this->hasCloudProvider($project->environments, 'IBMPVS') && $this->hasBandwidth($project->environments, 'IBMPVS')) {
            $html .= '<div style="height:5px"></div><table style="width: 100%; padding:5px"><tr><td colspan="2"><u><i>IBM PVS Outbound Network Cost = (Price per GB/Month * Outbound Bandwidth (GB)) * 12 mo/yr * # of years</i></u></td></tr>';
            foreach ($project->environments as $e) {
                if ($this->envHasCloudProvider($e, 'IBMPVS') && $e->cloud_bandwidth) {
                    $bandwidth = "";
                    foreach ($e->bandwidths as $index => $b) {
                        $bandwidth .= "(" . CurrencyConverter::convertAndFormat($e->bandwidthCosts[$index]) . "/GB/month * " . number_format($b) . " GB)";
                        if ($index != count($e->bandwidths) - 1) {
                            $bandwidth .= " + ";
                        }
                    }
                    $html .= '<tr><td style="width:6%"></td><td style="width:94%"><div><b>' . $e->name . '</b> IBM PVS Outbound Network Cost = (' . $bandwidth . ') * ' . $e->max_utilization . '% * 12 mo/yr * ' . $project->support_years . ' years = ' . CurrencyConverter::convertAndFormat($e->network_costs) . '</div></td></tr>';
                }
            }
            $html .= '</table><br/>';
        }

        foreach ($project->environments as $index => $environment) {
            $discountText = ($environment->discount_rate? $e->discount_rate : 0) . "%";
            if ($environment->consolidationMap && $this->envHasCloudProvider($environment, "IBMPVS")) {
                foreach ($environment->consolidationMap as $server) {
                    $serverName = $server->cloudServerDescription;
                    if ($server->onDemandHourly && $environment->isShowSupportCost()) {
                        $html .= '<br/><table style="width: 100%; padding:5px"><tr><td>' . "<strong><u>{$server->cloudServerDescription}</u></strong></td></tr>";
                        $html .= "<tr><td>{$serverName} Total Cost = ((hourly cost * discount) * 8,760 hrs/yr * {$project->support_years} years * utilization) * # of instances</td></tr>";
                        $html .= "<tr><td><i>((" . CurrencyConverter::convertAndFormat($server->onDemandHourly, null, 4) . "/hr * " . $discountText . ") * 8,760 hrs/yr * " . $project->support_years . " years * {$environment->max_utilization}%) * {$server->instances} = " . CurrencyConverter::convertAndFormat($server->onDemandTotal) . "</i></td></tr></table>";
                    }
                }
                if (count($environment->consolidationMap) > 1) {
                    $purchaseOption = $environment['payment_option']['name'];
                    $html .= '<br/><table style="width: 100%; padding:5px"><tr><td><b><u>' . $environment->name . ' ' . $purchaseOption . ' Total Cost</u></b></td></tr>';
                    $text = "{$purchaseOption} Purchase Cost = ";
                    foreach ($environment->consolidationMap as $index => $server) {
                        if (isset($server->onDemandTotal)) {
                            $text .= CurrencyConverter::convertAndFormat($server->onDemandTotal) . " ";
                            if ($index != count($environment->consolidationMap) - 1) {
                                $text .= "+ ";
                            }
                        }
                    }
                    if ($environment->onDemandPurchase) {
                        $text .= "= " . CurrencyConverter::convertAndFormat($environment->onDemandPurchase);
                    }
                    $html .= "<tr><td>{$text}</td></tr>";
                }
                if (count($project->environments) != $index + 1) {
                    $html .= "</br>";
                }
            }
        }

        $pdf->writeHTMLCell(0, 0, '', '', $html, 0, 1, 0, true, '', true);

        $pdf->Ln();

        if (Request::input('target')) {
            $pdf->resultTables($project->environments);
        }

        // Close and output PDF document
        return $pdf;
    }

    /**
     * @return \App\Helpers\Analysis\Cloud
     */
    public function cloudHelper()
    {
        return resolve(\App\Helpers\Analysis\Cloud::class);
    }

    public function processColumnsCollection(Collection $columns): string
    {
        return $columns->filter(function ($column) {
            if (array_key_exists(1, $column)) {
                return !$column[1];
            }

            return true;
        })->map(function ($column) {
            return $column[0];
        })->join('');
    }
}
