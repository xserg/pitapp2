<?php

namespace App\Services\Analysis\Report\Spreadsheet\Cloud;

use App\Models\Hardware\AmazonServer;
use App\Models\Hardware\AmazonStorage;
use App\Models\Hardware\AzureAds;
use App\Models\Hardware\AzureStorage;
use App\Models\Hardware\GoogleStorage;
use App\Models\Hardware\IBMPVSStorage;
use App\Models\Project\Cloud\PaymentOption;
use App\Models\Project\Environment;
use App\Services\Analysis\Report\Spreadsheet\AbstractConsolidation;
use App\Services\Currency\CurrencyConverter;


/**
 * 
 * This class sets the tag variables available in the spreadsheet template
 */
abstract class AbstractCloudConsolidation extends AbstractConsolidation
{
    /**
     * @var array
     */
    protected $spreadsheetTemplate;

    /**
     * @var array Holds headers for spreadsheed consolidation mapping
     */
    protected $consolidationHeaders = [
        'existing' => [
            'header' =>  [
                'label' => 'Existing Environment Details',
                'style' => '<&fill:FFb4c6e7;font-size:13;font-weight:true;&>',
            ],
            'code' => [
                'location',
                'environment_detail',
                'workload_type',
                'serial_number',
                'vm_id',
                'computedCores',
                'computedRam',
                'computedRpm',
            ],
            'subHeader' => [
                'labels' => [
                    'Location',
                    'Environment',
                    'Workload',
                    'Host ID/Serial #',
                    'VM ID',
                    'VM Cores @ Util',
                    'VM RAM (GB) @ Util',
                    'VM CPM @ Util',
                ],
                'style' => '<&fill:FFd9e2f3;&>',
            ],
        ],
        'target' => [
            'header' => [
                'label'=> 'Cloud Solution Detail',
                 'style' => '<&fill:FF8eaadb;font-size:13;font-weight:true;&>',
            ],
            'code' => [// Does not include price codes
                'name',
                'os_name',
                'instance_family',
                'vcpu_qty',
                'ram',
                'cpm',
            ],
            'subHeader' => [
                'labels' => [
                    'Instance Type',
                    'OS/Software',
                    'Category',
                    'vCPU',
                    'RAM (GB)',
                    'CPM',
                    'Price/hr ($)',
                    'Upfront Price ($)',
                    'Annual Price ($)',
                ],
                'style' => '<&fill:FFd9e2f3;&>',
            ],
        ],
    ];

    /**
     * @var int The sum of `useable_storage` properties for each
     * `serverConfiguration` of the existing environment
     */
    protected $useableStorageTotal;

    /** @var Environment The existing environment to use for the consolidation */
    protected $existingEnvironment;

    /**
     * @param Environment $existingEnvironment
     * @param Environment $targetEnvironment
     * @return object
     */
    public function formatConsolidation($existingEnvironment, $targetEnvironment)
    {
        $this->existingEnvironment = $existingEnvironment;
        $formatedConsolidations = $this->formatConsolidationData($targetEnvironment);
        // New format for spreadsheet output.
        $formatedConsolidation = (object)[
            'spreadsheetTitle' => $existingEnvironment->project->title . '-consolidations',
            'worksheetTitle' => $targetEnvironment->name,
            'existingDetails' => $this->getExistingDetails($existingEnvironment),
            'targetDetails' => $this->getTargetDetails($targetEnvironment),
            'consolidations' => $formatedConsolidations,
            'totals' => [
                'existing' => $this->getExistingTotals($targetEnvironment),
                'target' => collect($this->getTargetTotals($targetEnvironment))
                    ->merge(collect(
                        $this->getTotalTargetAnnualPrice($formatedConsolidations['data'])
                    ))
                    ->toArray()
            ],
            'template' => $this->spreadsheetTemplate
        ];

        return $formatedConsolidation;
    }

    /**
     * @param Environment $environment
     * @return array
     */
    protected function getExistingTotals($environment)
    {
        return [
            'physical_servers' => $this->formatValue(round($environment->analysis->totals->existing->servers)),
            'physical_cores' => $this->formatValue(round($environment->analysis->totals->existing->physical_cores)),
            'physical_ram' => $this->formatValue(round($environment->analysis->totals->existing->physical_ram)),
            'vm_servers' => $this->formatValue(round($environment->analysis->totals->existing->vms)),
            'vm_cores' => $this->formatValue($environment->analysis->totals->existing->vm_cores),
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
            'physical_servers' => $this->formatValue(round($environment->analysis->totals->target->servers)),
            'physical_cores' => $this->formatValue(round($environment->analysis->totals->target->physical_cores)),
            'vm_servers' => $this->formatValue(round($environment->analysis->totals->target->vms)),
            'vm_cores' => $this->formatValue(round($environment->analysis->totals->target->total_cores)),
            'vm_cores_util' => $this->formatValue(round($environment->analysis->totals->target->total_cores)),
            'vm_ram' => $this->formatValue(round($environment->analysis->totals->target->ram)),
            'vm_ram_util' => $this->formatValue(round($environment->analysis->totals->target->utilRam))
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

    /**
     * @param Environment $environment
     * @return array
     */
    protected function getTargetDetails($environment)
    {
        $details = [
            'name' => $environment->name,
            'provider' => $environment->provider->name,
            'updated_at' => (string)$environment->updated_at,
            'region' => $environment->region->name,
            'payment_option' => $this->getPaymentOptionById($environment)['name'],
            'discount_rate' => $environment->discount_rate?: 0,
            'storage_type' => $this->getStorageTypeById($environment)['name']?: 'N/A',
            'storage_capacity' => $this->formatValue($environment->useable_storage),
            'storage_cost' => $this->formatValue($environment->storage_purchase_price, 0, true),
            'cloud_bandwidth' => $this->formatValue($environment->cloud_bandwidth),
            'network_costs' => $this->formatValue($environment->network_costs, 0, true)
        ];

        return $details;
    }

    /**
     * @param mixed $value
     * @param bool $isMoney
     * @return string
     */
    protected function formatValue($value, $decimals = 0, $isMoney = false ) {
        if ($isMoney) {
            if ($value) {
                // Remove dollar sign if exists
                $value = (float)str_replace('$', '', $value);
                // Set number in money format
                return CurrencyConverter::convertAndFormat($value, null, $decimals);
            } else {
                return 'N/A';
            }
        }
        return $value ? number_format($value, $decimals, '.', ',') : 'N/A';
    }

    /**
     * @param array $consolidation
     * @return array
     */
    protected function getTotalTargetAnnualPrice($consolidation)
    {
        $price = 0;

        foreach($consolidation as $server) {
            // Transform string in money format to integer
            // Remove thousands comma and dollar sign
            $price += (int)str_replace('$', '', str_replace(',', '',last($server)));
        }

        return ['annual_price' => $this->formatValue($price, 0, true)];
    }

    /**
     * @param Environment $targetEnvironment
     * @return array
     */
    protected function getStorageTypeById($targetEnvironment)
    {
        if ($targetEnvironment->isAzure()) {
            return AzureStorage::getAzureStorageTypeById($targetEnvironment->cloud_storage_type);
        } else if ($targetEnvironment->isGoogle()) {
            return GoogleStorage::getGoogleStorageTypeById($targetEnvironment->cloud_storage_type);
        } else if ($targetEnvironment->isIBMPVS()) {
            return IBMPVSStorage::getIBMPVSStorageTypeById($targetEnvironment->cloud_storage_type);
        } else {
            return AmazonStorage::getAmazonStorageTypeById($targetEnvironment->cloud_storage_type);
        }
    }

    /**
     * @param Environment $targetEnvironment
     * @return array
     */
    protected function getPaymentOptionById($targetEnvironment)
    {
        if ($targetEnvironment->isAzure()) {
            return AmazonServer::getAzurePaymentOptionById($targetEnvironment->payment_option_id);
        } else if ($targetEnvironment->isGoogle()) {
            return AmazonServer::getGooglePaymentOptionById($targetEnvironment->payment_option_id);
        } else if ($targetEnvironment->isIBMPVS()) {
            return AmazonServer::getIBMPVSPaymentOptionById($targetEnvironment->payment_option_id);
        } else {
            return AmazonServer::getAmazonPaymentOptionById($targetEnvironment->payment_option_id);
        }
    }

    /**
     * @param Environment $environment
     * @return array
     */
    protected function formatConsolidationData($environment)
    {
        $this->addUseableStorageColumn();
        
        $formatedConsolidations = [];
        $consolidations = json_decode(json_encode($environment->analysis->consolidations), true);
        $tmpTargets = [];
        $finalColumns = []; // Array for the final columns indexes
        $consolidation_server = null;

        foreach($consolidations as $consolidation) {
            $tmp = [];

            // Get largest array to loop through
            $largestArray = count($consolidation['servers']) >= count($consolidation['targets'])? $consolidation['servers'] : $consolidation['targets'];

            foreach($largestArray as $index => $server) {
                $instanceName = $consolidation['targets'][$index]['id'];
                // Set target for all same targets
                if (!isset($tmpTargets[$instanceName])) {
                    $tmpTargets[$instanceName] = $consolidation['targets'][$index];
                }

                if (isset($consolidation['servers'][$index])) {
                    $consolidation_server = $consolidation['servers'][$index];
                }

                // Loop through existing header codes and get the values
                foreach($this->consolidationHeaders['existing']['code'] as $i => $code) {
                    if (isset($consolidation_server)) {
                        $tmp[] = (string)$this->defaultArrayValue($consolidation_server, $code, 'N/A');
                        
                        // If the value exists add the column index to the final columns array
                        if (!in_array($i, $finalColumns) && $tmp[$i] !== 'N/A') {
                            $finalColumns[] = $i;
                        }
                    } else if (isset($lastServer)) {
                        $tmp[] = (string)$this->defaultArrayValue($lastServer, $code, 'N/A');
                    }
                }

                // Loop through target headers codes and get the values
                foreach($this->consolidationHeaders['target']['code'] as $i => $code) {
                    $tmp[] = (string)$this->defaultArrayValue($tmpTargets[$instanceName], $code, 'N/A');
                    $headerIndex = $i + count($this->consolidationHeaders['existing']['code']);
                    if (!isset($tmp[$headerIndex])) continue;
                    // If the value exists add the column index to the final columns array
                    if (!in_array($headerIndex, $finalColumns) && $tmp[$headerIndex] !== 'N/A') {
                        $finalColumns[] = $headerIndex;
                    }
                }

                // Get the appropriate prices depending on the payment option and instance type
                if ($tmpTargets[$instanceName]['instance_type'] === 'Azure') {
                    $prices = $this->getTargetAzurePrices($tmpTargets[$instanceName]);
                } else if ($tmpTargets[$instanceName]['instance_type'] === 'Google') {
                    $prices = $this->getTargetGooglePrices($tmpTargets[$instanceName]);
                } else if ($tmpTargets[$instanceName]['instance_type'] === 'IBMPVS') {
                    $prices = $this->getTargetIBMPVSPrices($tmpTargets[$instanceName]);
                } else if ($tmpTargets[$instanceName]['instance_type'] === AzureAds::INSTANCE_TYPE_ADS) {
                    $prices = $this->getTargetAzureADSPrices($tmpTargets[$instanceName]);

                } else {
                    $prices = $this->getTargetAwsPrices($tmpTargets[$instanceName]);
                }

                // Add the prices to the consolidation array
                foreach($prices as $i => $price) {
                    $tmp[] = (string)$price;
                    $headerIndex = $i + count($this->consolidationHeaders['existing']['code']) + count($this->consolidationHeaders['target']['code']);
                    // If the value exists add the column index to the final columns array
                    if (!in_array($headerIndex, $finalColumns) && $price !== 'N/A') {
                        $finalColumns[] = $headerIndex;
                    }
                }

                // Add the consolidation
                $formatedConsolidations['data'][] = $tmp;
            }
        }

        // Remove all the columns without data
        $this->removeEmptyColumns($formatedConsolidations, $finalColumns);

        // Set the consolidation headers
        $this->setConsolidationHeaders($formatedConsolidations, $finalColumns);

        // Set consolidation totals
        $this->setConsolidationTotals($formatedConsolidations, $environment);

        return $formatedConsolidations;
    }

    /**
     * Removes the consolidation columns that ar not part of the finalColumns array
     * @param array $formatedConsolidations
     * @param array $finalColumns
     */
    protected function removeEmptyColumns(&$formatedConsolidations, $finalColumns)
    {
        foreach($formatedConsolidations['data'] as $i => $consolidation) {
            foreach($consolidation as $y => $column) {
                if (!in_array($y, $finalColumns)) {
                    unset($formatedConsolidations['data'][$i][$y]);
                }
            }
        }
    }

    /**
     * @param array $formatedConsolidations
     * @param array $finalColumns
     */
    protected function setConsolidationTotals(&$formatedConsolidations, $targetEnvironment)
    {
        $existingEnvHeaders = $this->consolidationHeaders['existing'];
        $targetEnvHeaders = $this->consolidationHeaders['target'];

        // Set the array length to be the length of a consolidation row
        $rowLength = count($formatedConsolidations['sub_headers']);
        // Create a new array.
        $array = [];
        for ($i = 0; $i < $rowLength; $i++) {
            $array[] = '';
        }

        $formatedConsolidations['totals'] = $array;

        // TODO find better way to get header indexes
        $vCPUHeader = $targetEnvHeaders['subHeader']['style'] . 'vCPU';
        $ramHeader = $targetEnvHeaders['subHeader']['style'] . 'RAM (GB)';
        $cpmHeader = $targetEnvHeaders['subHeader']['style'] . 'CPM';

        // Add the data
        $collection = collect($formatedConsolidations['sub_headers']);

        /* add "total" row for target env */
        $formatedConsolidations['totals'][$collection->search($vCPUHeader) - 1] = 'TOTALS';
        $formatedConsolidations['totals'][$collection->search($vCPUHeader)] =  $targetEnvironment->analysis->totals->target->total_cores;
        $formatedConsolidations['totals'][$collection->search($ramHeader)] =  $this->formatValue($targetEnvironment->analysis->totals->target->ram);
        $formatedConsolidations['totals'][$collection->search($cpmHeader)] =  $targetEnvironment->analysis->totals->target->rpm ?: '';
        $formatedConsolidations['totals'][$rowLength - 1] =  $this->getTotalTargetAnnualPrice($formatedConsolidations['data']);

        /* add Useable Storage sum to spreadsheet if set in consolidation headers */
        if (in_array('useable_storage', $existingEnvHeaders['code'])) {
            // "<&fill:FFd9e2f3;&>Useable Storage (GB)"
            $useableStorageHeader = $existingEnvHeaders['subHeader']['style'] . 'Useable Storage (GB)';
            
            /* add "total" row for existing env */
            $formatedConsolidations['totals'][$collection->search($useableStorageHeader) - 4] = 'TOTALS';
            $formatedConsolidations['totals'][$collection->search($useableStorageHeader)]
                = $this->useableStorageTotal;
        }
    }

    /**
     * Add the Column headers that are part of the finalColumns array
     * @param array $formatedConsolidations
     * @param array $finalColumns
     */
    protected function setConsolidationHeaders(&$formatedConsolidations, $finalColumns)
    {
        $subHeaders = [];
        $headers = [];
        $colIndex = 0;
        // Loop through the consolidation headers
        foreach ($this->consolidationHeaders as $type => $header) {
            // Loop through the consolidation header labels
            foreach($header['subHeader']['labels'] as $index => $label) {
                // Check if header label is part of the final columns to show
                if (in_array($colIndex, $finalColumns)) {
                    $subHeaders[] = $header['subHeader']['style'] . $label;
                    $headers[$type][] = $header['header']['style'];
                }
                $colIndex++;
            }
        }

        // Set the main header labels in the right cell
        $headers['existing'][count($headers['existing']) / 2] .= $this->consolidationHeaders['existing']['header']['label'];
        $headers['target'][count($headers['target']) / 2] .= $this->consolidationHeaders['target']['header']['label'];

        // Merge both main headers into one row
        $headers = collect($headers['existing'])->merge(collect($headers['target']))->toArray();

        $formatedConsolidations['headers'] = $headers;
        $formatedConsolidations['sub_headers'] = $subHeaders;
    }

    /**
     * Returns the value at the specified index of a given array
     * 
     * If that index is not in the array, the `$default` argument is returned.
     * 
     * @param array $array
     * @param string $index
     * @param string $default
     * 
     * @return string
     */
    protected function defaultArrayValue($array, $index, $default) {
        return isset($array[$index]) && $array[$index] !== ''
            ? $array[$index]
            : $default;
    }

    /**
     * @param array $server
     * @return array
     */
    protected function getTargetAwsPrices($server)
    {
        $discount = isset($server['discountRate']) ? (100 - $server['discountRate']) /100 : 1;
        switch($server['payment_option']['id']) {
            case 1: // ON Demand
                return [
                    $this->formatValue($server['onDemandHourly'] * $discount, 4, true),
                    'N/A',
                    $this->formatValue($server['onDemandHourly'] * $discount * 8760, 0, true)
                ];
            case 2: // 1 Yr No Upfront
            case 5:
            case 8:
            case 11:
                return [
                    $this->formatValue($server['upfrontHourly'] * $discount, 4, true),
                    'N/A',
                    $this->formatValue($server['upfrontHourly'] * $discount * 8760, 0, true)
                ];
            case 3: // Partial Upfront
            case 6:
            case 9:
            case 12:
                return [
                    $this->formatValue($server['upfrontHourly'] * $discount, 4, true),
                    $this->formatValue($server['upfront'] * $discount, 0, true),
                    $this->formatValue($server['upfront'] * $discount + $server['upfrontHourly'] * $discount * 8760, 0, true)
                ];
            case 4: // all Upfront
            case 7:
            case 10:
            case 13:
                return [
                    'N/A',
                    $this->formatValue($server['upfront'] * $discount, 0, true),
                    $this->formatValue($server['upfront'] * $discount, 0, true)
                ];

        }
    }

    /**
     * Get prices for a given Azure server
     *  
     * @param array $server
     * @return array
     */
    protected function getTargetAzurePrices($server)
    {
        $discount = isset($server['discountRate']) ? (100 - $server['discountRate']) /100 : 1;
        // switch($server['payment_option']['id']) {
        switch($server['payment_option']['name']) {
            // case 2: // Pay As You Go With Azure Hybrid Benefit
            case PaymentOption::AZURE_PAY_AS_YOU_GO_AHB:
            // case 1: // Pay As You
            case PaymentOption::AZURE_PAY_AS_YOU_GO:
                return [
                    $this->formatValue($server['onDemandHourly'] * $discount, 4, true),
                    'N/A',
                    $this->formatValue($server['onDemandHourly'] * $discount * 8760, 0, true)
                ];
            // case 3: // One Year Reserved
            case PaymentOption::AZURE_ONE_YEAR_RESERVED:
            // case 6: // One Year Reserved With Azure Hybrid Benefit
            case PaymentOption::AZURE_ONE_YEAR_RESERVED_AHB:
                return [
                    $this->formatValue($server['upfrontHourly'] * $discount, 4, true),
                    'N/A',
                    $this->formatValue($server['upfrontHourly'] * $discount * 8760, 0, true)
                ];
            // case 5: // 3 Year Reserved With Azure Hybrid Benefit
            case PaymentOption::AZURE_THREE_YEAR_RESERVED_AHB:
            // case 4: // Three Year Reserved
            case PaymentOption::AZURE_THREE_YEAR_RESERVED:
                return [
                    $this->formatValue($server['upfront3Hourly'] * $discount, 4, true),
                    'N/A',
                    $this->formatValue($server['upfront3Hourly'] * $discount * 8760, 0, true)
                ];
        }
    }

    /**
     * @param array $server
     * @return array
     */
    protected function getTargetAzureADSPrices($server)
    {
        $discount = isset($server['discountRate']) ? (100 - $server['discountRate']) /100 : 1;

        switch($server['payment_option']['name']) {
            case PaymentOption::AZURE_PAY_AS_YOU_GO_AHB:
            case PaymentOption::AZURE_PAY_AS_YOU_GO:
                return [
                    $this->formatValue($server['onDemandHourly'] * $discount, 4, true),
                    'N/A',
                    $this->formatValue($server['onDemandHourly'] * $discount * 8760, 0, true)
                ];
            case PaymentOption::AZURE_ONE_YEAR_RESERVED:
            case PaymentOption::AZURE_ONE_YEAR_RESERVED_AHB:
                return [
                    $this->formatValue($server['upfrontHourly'] * $discount, 4, true),
                    'N/A',
                    $this->formatValue($server['upfrontHourly'] * $discount * 8760, 0, true)
                ];
            case PaymentOption::AZURE_THREE_YEAR_RESERVED_AHB:
            case PaymentOption::AZURE_THREE_YEAR_RESERVED:
                return [
                    $this->formatValue($server['upfront3Hourly'] * $discount, 4, true),
                    'N/A',
                    $this->formatValue($server['upfront3Hourly'] * $discount * 8760, 0, true)
                ];
        }
    }

    /**
     * @param array $server
     * @return array
     */
    protected function getTargetGooglePrices($server)
    {
        $discount = isset($server['discountRate']) ? (100 - $server['discountRate']) /100 : 1;
        switch($server['payment_option']['id']) {
            case 1: // On demand
                return [
                    $this->formatValue($server['onDemandHourly'] * $discount, 4, true),
                    'N/A',
                    $this->formatValue($server['onDemandHourly'] * $discount * 8760, 0, true)
                ];
            case 2: // 1 year commitment
                return [
                    $this->formatValue($server['upfrontHourly'] * $discount, 4, true),
                    'N/A',
                    $this->formatValue($server['upfrontHourly'] * $discount * 8760, 0, true)
                ];
            case 3: // 3 year commitment
                return [
                    $this->formatValue($server['upfront3Hourly'] * $discount, 4, true),
                    'N/A',
                    $this->formatValue($server['upfront3Hourly'] * $discount * 8760, 0, true)
                ];
        }
    }

    /**
     * @param array $server
     * @return array
     */
    protected function getTargetIBMPVSPrices($server)
    {
        $discount = isset($server['discountRate']) ? (100 - $server['discountRate']) /100 : 1;
        switch($server['payment_option']['id']) {
            case 1: // On demand
                return [
                    $this->formatValue($server['onDemandHourly'] * $discount, 4, true),
                    'N/A',
                    $this->formatValue($server['onDemandHourly'] * $discount * 8760, 0, true)
                ];
        }
    }

    /**
     * Add `Useable Storage (GB)` column to spreadsheet
     * 
     * This method also calculates and sets the `useableStorageTotal`.
     * 
     * The `Useable Storage (GB)` is added only if the existing environment's
     * `serverConfigurations`' `useable_storage` sum is greater than 0.
     * 
     * Note: `useable_storage` field is set for a given `ServerConfiguration` "mostly"
     * when the environment servers are loaded from RVTools spreadsheet.
     * 
     * @return void
     */
    protected function addUseableStorageColumn(): void
    {
        $useableStorageSum = 0;

        foreach ($this->existingEnvironment->serverConfigurations as $config) {
            if ($config->useable_storage) {
                $useableStorageSum += $config->useable_storage;
            }
        }

        if ($useableStorageSum > 0) {
            $this->consolidationHeaders['existing']['code'][] = 'useable_storage';
            $this->consolidationHeaders['existing']['subHeader']['labels'][] = 'Useable Storage (GB)';
        }

        $this->useableStorageTotal = $useableStorageSum;
    }
}
