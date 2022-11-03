<?php
/**
 *
 */

namespace App\Services;


use App\Http\Controllers\Api\Project\AnalysisController;
use App\Models\Hardware\Manufacturer;
use App\Models\Project\Environment;
use App\Models\Project\Project;
use App\Models\Project\Provider;
use App\Models\Project\RevenueReport;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class Revenue
{
    const MODE_REPORT = 'report';
    const MODE_ANALYZE = 'analyze';

    const FILTER_PROVIDER_ID = 'provider_id';
    const FILTER_MODIFIED_DATE = 'modified_date';
    const FILTER_CLOUD_PROVIDER = 'cloud_provider';
    const FILTER_MANUFACTURER_ID = 'manufacturer_id';

    /**
     * @var string
     */
    protected $_mode = self::MODE_ANALYZE;

    /**
     * @var array
     */
    protected $_totalMap = [
        'num_analyses' => 'Number of Analyses',
        'product_revenue' => "Product Revenue",
        'maintenance_revenue' => "Maintenance Revenue",
        'manufacturer_revenue' => "Total Manufacturer Revenue",
        'migration_revenue' => "Implementation & Migration Services Revenue",
        'total_revenue' => "Total Product & Services Revenue"
    ];

    /**
     * @var array
     */
    protected $_currencyMap = [
        'product_revenue',
        'maintenance_revenue',
        'manufacturer_revenue',
        'migration_revenue',
        'total_revenue'
    ];

    /**
     * @var array
     */
    protected $_reportColumns = [
        'Date' => 'updated_at',
        'Provider Name' => 'provider',
        'Username' => 'username',
        'Customer Name' => 'customer',
        'Manufacturer Name' => 'manufacturer_name',
        'Number of Analyses' => 'num_analyses',
        'Product Revenue' => 'product_revenue',
        'Maintenance Revenue' => 'maintenance_revenue',
        'Total Manufacturer Revenue' => 'manufacturer_revenue',
        'Implementation & Migration Services Revenue' => 'migration_revenue',
        'Total Product & Services Revenue' => 'total_revenue',
        'Description' => 'description',
    ];

    public function __construct()
    {
    }

    /**
     * @return $this
     */
    public function setReportMode()
    {
        $this->_mode = self::MODE_REPORT;
        return $this;
    }

    /**
     * @return $this
     */
    public function setAnalyzeMode()
    {
        $this->_mode = self::MODE_ANALYZE;
        return $this;
    }

    /**
     * @return bool
     */
    public function isReportMode()
    {
        return $this->_mode == self::MODE_REPORT;
    }

    /**
     * @param array $filters
     * @throws \Exception
     */
    public function generateFormattedReport(array $filters)
    {
        $this->createCsv($this->getFormattedReportData($filters), [
            'filename' => 'RevenueReport_' . date("Y-m-d_H:i:s") . ".csv"
        ]);
    }

    /**
     * @param array $filters
     * @param Command|null $command
     * @return array
     */
    public function getFormattedReportData(array $filters, Command $command = null)
    {
        setlocale(LC_MONETARY, 'en_US.UTF-8');
        $buckets = $this->generateReportBuckets($filters, $command);
        $collection = collect($this->_reportColumns);
        $keys = $collection->keys();
        $formattedData = [
            $keys->all()
        ];

        $emptyRow = [];
        for($i = 0; $i < count($this->_reportColumns); $i++) {
            $emptyRow[] = '';
        }

        $grandTotals = [];

        foreach($buckets as $providerId => $providerData) {
            foreach($providerData['rows'] as $row) {
                $rowData = [];
                foreach($this->_reportColumns as $columnLabel => $columnIndex) {
                    $value = $row[$columnIndex];
                    if (in_array($columnIndex, $this->_currencyMap)) {
                        $value = round($value);
                    }
                    $rowData[] = $value;

                }
                $formattedData[] = $rowData;
            }
            $totalData = ['', '', '', '', 'SUBTOTALS'];
            foreach($providerData['totals'] as $totalCode => $totalValue) {
                if (in_array($totalCode, $this->_currencyMap)) {
                    $totalData[] = round($totalValue);
                } else {
                    $totalData[] = $totalValue;
                }
                if (!isset($grandTotals[$totalCode])) {
                    $grandTotals[$totalCode] = 0.00;
                }
                $grandTotals[$totalCode] += $totalValue;
            }
            $formattedData[] = $totalData;
            $formattedData[] = $emptyRow;
        }

        $grandTotalData = ['', '', '', '', 'TOTALS'];
        foreach($grandTotals as $totalCode => $totalValue) {
            if (in_array($totalCode, $this->_currencyMap)) {
                $grandTotalData[] = round($totalValue);
            } else {
                $grandTotalData[] = $totalValue;
            }
        }

        $formattedData[] = $grandTotalData;

        return $formattedData;
    }

    /**
     * @param array $filters
     * @param Command|null $command
     * @return array
     */
    public function generateReportBuckets(array $filters, Command $command = null)
    {
        /*$providerCollection = Provider::all();
        $providers = [];
        foreach($providerCollection as $provider) {
            $providers[$provider->id] = $provider->name;
        }*/

        $manufacturerCollection = Manufacturer::all();
        $manufacturers = [];
        foreach($manufacturerCollection as $manufacturer) {
            $manufacturers[$manufacturer->id] = $manufacturer->name;
        }

        $report = DB::table('revenue_report')
            ->join('companies', 'companies.id', '=', 'revenue_report.company_id')
            ->join('projects', 'revenue_report.project_id', '=', 'projects.id')
            ->join('environments', 'revenue_report.environment_id', '=', 'environments.id')
            ->join('users', 'users.id', '=', 'revenue_report.user_id')
            ->select([
                'revenue_report.*',
                'companies.name',
                'companies.id',
                'projects.updated_at',
                DB::raw('CONCAT(users.firstName, \' \', users.lastName) as username'),
                'environments.environment_type',
                'projects.description'])
            ->orderBy('companies.name');


        foreach($filters as $filter_key => $value) {
            switch($filter_key) {
                case self::FILTER_CLOUD_PROVIDER:
                    $report->where('revenue_report.cloud_provider', $value);
                    break;
                case self::FILTER_MANUFACTURER_ID:
                    $report->join('manufacturers', 'revenue_report.manufacturer_id', '=', 'manufacturers.id')
                        ->where('manufacturers.name', $value);
                    break;
                case self::FILTER_MODIFIED_DATE:
                    $start = $value['start'];
                    $end = $value['end'];
                    if ($start) {
                        $start = date("Y-m-d H:i:s", strtotime($start));
                    }
                    if ($end) {
                        $end = date("Y-m-d H:i:s", strtotime($end));
                    }
                    if ($start && $end) {
                        $report->whereBetween('projects.updated_at', [$start, $end]);
                    } else if ($start) {
                        $report->where('projects.updated_at', '>=', $start);
                    } else {
                        $report->where("projects.updated_at", "<=", $end);
                    }
                    break;
                case self::FILTER_PROVIDER_ID:
                    $report->where('companies.id', $value);
                    break;
            }
        }


        $groupedResults = [];

        $report->chunk(200, function ($revenueChunk) use (&$groupedResults, &$manufacturers, &$providers) {
            foreach($revenueChunk as $revenueData) {
                $revenueData = (array)$revenueData;
                $provider = $revenueData['provider'];
                if (!$provider) {
                    return true;
                }
                if (!($groupedResults[$provider] ?? false)) {
                    $groupedResults[$provider] = [
                        'totals' => [],
                        'rows' => []
                    ];
                }

                // Static
                $revenueData['num_analyses'] = 1;

                // Format this
                $revenueData['updated_at'] = date('n/j/Y', strtotime($revenueData['updated_at']));

                // This field is dynamic
                $revenueData['manufacturer_name'] = $revenueData['environment_type'] == 3 && $revenueData['cloud_provider']
                    ? $revenueData['cloud_provider']
                    : $manufacturers[$revenueData['manufacturer_id']];

                // This is a composite total
                $revenueData['manufacturer_revenue'] = $revenueData['product_revenue'] + $revenueData['maintenance_revenue'];

                // Little hack to get total revenue on a per-row level
                $revenueData['total_revenue'] = 0.00;
                $fields = $this->_totalMap;
                unset($fields['total_revenue']);
                $collection = collect($fields);
                $keys = $collection->keys();
                foreach ($keys->all() as $subtotal) {
                    switch ($subtotal) {
                        case 'manufacturer_revenue':
                            // do nothing
                            break;
                        default:
                            $revenueData['total_revenue'] += $revenueData[$subtotal] ?? 0.00;
                            break;
                    }
                }

                $groupedResults[$provider]['rows'][] = $revenueData;

                // Now total everything by group
                foreach ($this->_totalMap as $totalCode => $totalLabel) {
                    if (!isset($groupedResults[$provider]['totals'][$totalCode])) {
                        $groupedResults[$provider]['totals'][$totalCode] = 0.00;
                    }
                    switch ($totalCode) {
                        default:
                            $groupedResults[$provider]['totals'][$totalCode] += $revenueData[$totalCode] ?? 0.00;
                            break;
                    }
                }
            }
        });

        return $groupedResults;
    }

    /**
     * @param null $command
     * @return $this
     */
    public function regenerate(\Illuminate\Console\Command $command = null)
    {
        /** @var Project $project */
        foreach(Project::get() as $project) {
            if ($command) {
                $command->info("Regenerating project: {$project->id} - {$project->title}");
            }
            try {
                DB::beginTransaction();
                $this->analyze($project->id);
                DB::commit();
            } catch (\Throwable $e) {
                echo "Project: {$project->id} - Encountered error: {$e->getMessage()}\n{$e->getTraceAsString()}";
                DB::rollBack();
                // Do nothing if the analysis has an error
                // transaction will prevent anything from getting written
            }
        }

        return $this;
    }

    /**
     * @param $projectId
     * @return $this
     */
    public function analyze($projectId)
    {
        // Change modes
        $currentMode = $this->_mode;
        $this->setReportMode();

        /** @var AnalysisController $analysisController */
        $analysisController = resolve(AnalysisController::class);
        $analysisController->analysis($projectId);

        $this->_mode = $currentMode;

        return $this;
    }

    public function createCsv($data, $options = [])
    {
        if (empty($data)) {
            $data = [];
        }

        $saveToDisk = isset($options['save_to_disk']);
        $path = ($saveToDisk) ? $options['save_to_disk'] : 'php://output';

        if (($handle = fopen($path, 'w')) === false) {
            throw new \Exception('Unable to write to ' . $path);
        }

        if ($saveToDisk === false) {
            $filename = (isset($options['filename']))
                ? $options['filename']
                : 'Export' . date('YmdHis') . '.' . ($options['file_extension'] ?? 'csv');
            header("Pragma: public");
            header("Expires: 0");
            header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
            header("Cache-Control: private", false);
            header("Content-Type: " . ($options['content_type'] ?? "text/csv"));
            header("Content-Disposition: attachment; filename={$filename}");
            header("Content-Transfer-Encoding: binary");
        }

        ini_set("memory_limit", ($options['memory_limit'] ?? '2G'));
        foreach ($data as $k => $v) {
            $collection = collect($v);
            fputcsv($handle, $collection->values()->all(), $options['delimiter'] ?? ",");
        }

        fclose($handle);
        return $this;
    }

    /**
     * @param Project $project
     * @param Environment $bestTargetEnvironment
     * @return $this
     */
    public function updateProjectRevenue(Project $project, Environment $bestTargetEnvironment)
    {
        /** @var RevenueReport $report */
        $report = RevenueReport::firstOrNew(['project_id' => $project->id]);
        $report->company_id = $project->user->company_id;
        $report->provider = $project->provider;
        $report->customer = $project->customer_name;
        $report->user_id = $project->user_id;
        $report->environment_id = $bestTargetEnvironment->id;

        if($bestTargetEnvironment->environment_type_id != 3) {
            $report->manufacturer_id = $bestTargetEnvironment->serverConfigurations[0]->manufacturer_id;
        } else {
            $report->cloud_provider = $bestTargetEnvironment->provider()->first()->name;
        }

        //default product revenue
        $report->product_revenue = $bestTargetEnvironment->system_software_purchase_price +  $bestTargetEnvironment->purchase_price +  $bestTargetEnvironment->storage_purchase_price;

        // include chassis/interconnect if it exists
        if (isset($bestTargetEnvironment->analysis) && isset($bestTargetEnvironment->analysis->interchassisResult)) {
            $report->product_revenue += $bestTargetEnvironment->analysis->interchassisResult->purchase_cost;
        }

        //default maintenance revenue
        $report->maintenance_revenue = $bestTargetEnvironment->total_system_software_maintenance + $bestTargetEnvironment->total_hardware_maintenance + $bestTargetEnvironment->total_storage_maintenance;

        //include chassis/interconnect if it exists
        if (isset($bestTargetEnvironment->analysis) && isset($bestTargetEnvironment->analysis->interchassisResult)) {
            $report->maintenance_revenue += $bestTargetEnvironment->analysis->interchassisResult->annual_maintenance * $project->support_years;
        }

        $report->migration_revenue = $bestTargetEnvironment->migration_services;

        $report->save();

        return $this;
    }
}