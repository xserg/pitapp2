<?php
/**
 *
 */
namespace App\Services\Analysis\Pricing\Environment\Cloud;
use App\Models\Hardware\AmazonServer;
use App\Models\Hardware\AzureAds;
use App\Models\Project\Cloud\PaymentOption;
use App\Models\Project\Environment;
use App\Services\Analysis\PricingAccessTrait;
class InstanceCalculator
{
    /**
     * @param array $consolidationMap
     * @param Environment $targetEnvironment
     * @param Environment $existingEnvironment
     * @return $this
     */
    public function calculateCosts($consolidationMap, Environment $targetEnvironment, Environment $existingEnvironment)
    {
        $isAzure = $this->cloudHelper()->environmentIsAzure($targetEnvironment);
        $isGoogle = $this->cloudHelper()->environmentIsGoogle($targetEnvironment);
        $isIBMPVS = $this->cloudHelper()->environmentIsIBMPVS($targetEnvironment);
        /** @var \stdClass $target */
        foreach($consolidationMap as &$target) {
            list ($onDemandHourly, $upfrontHourly, $reserved3Year, $upfront) =
                $isAzure
                    ? $this->calculateTargetAzureCosts($target, $targetEnvironment)
                    : ($isGoogle ? $this->calculateTargetGoogleCosts($target, $targetEnvironment) 
                    : ($isIBMPVS ? $this->calculateTargetIBMPVSCosts($target, $targetEnvironment)
                    : $this->calculateTargetAwsCosts($target, $targetEnvironment)));
            $target->onDemandHourly = $onDemandHourly ? $onDemandHourly->price_per_unit : null;
            $target->upfrontHourly = $upfrontHourly ? $upfrontHourly->price_per_unit : null;
            $target->upfront3Hourly = $reserved3Year ? $reserved3Year->price_per_unit : null;
            $target->upfront = $upfront? $upfront->price_per_unit : null;
        }
        
        return $this;
    }

    /**
     * @param \stdClass $target
     * @param int $paymentOptionId
     * @return array
     */
    public function calculateTargetAwsCosts(&$target, $targetEnvironment)
    {
        $query = AmazonServer::where('name', '=', $target->name)
            ->where('instance_type', '=', $target->instance_type)
            ->where('location', '=', $targetEnvironment->region->name)
            ->where('ram', '=', $target->ram)
            ->where('vcpu_qty', '=', $target->vcpu_qty)
            ->where('deployment_option', '=', $target->deployment_option)
            ->where('pre_installed_sw', '=', $target->pre_installed_sw);

        $upfront = clone $query;
        $upfrontHourly = clone $query;
        $onDemandHourly = clone $query;

        $paymentOption = AmazonServer::getAmazonPaymentOptionById($targetEnvironment->payment_option_id);

        $upfront
            ->where('purchase_option', '=', $paymentOption['purchase_option'])
            ->where('offering_class', '=', $paymentOption['offering_class'])
            ->where('lease_contract_length', '=', $paymentOption['lease_contract_length'])
            ->where('price_unit', '=', 'Quantity');

        $upfrontHourly
            ->where('purchase_option', '=', $paymentOption['purchase_option'])
            ->where('offering_class', '=', $paymentOption['offering_class'])
            ->where('lease_contract_length', '=', $paymentOption['lease_contract_length'])
            ->where('price_unit', '=', 'Hrs');
        
        $onDemandHourly
                ->where('term_type', '=', 'OnDemand')
                ->where('price_unit', '=', 'Hrs');
    

        if ($target->instance_type == AmazonServer::INSTANCE_TYPE_RDS) {
            if($target->database_engine == AmazonServer::DATABASE_ENGINE_AMAZON_AURORA) {
                $target->database_engine = AmazonServer::DATABASE_ENGINE_AURORA_MYSQL;
            }
            $upfront = $upfront->where('database_engine', '=', $target->database_engine)
                ->where('database_edition', '=', $target->database_edition)
                ->where('license_model', $target->license_model)
                ->first();
            $upfrontHourly = $upfrontHourly->where('database_engine', '=', $target->database_engine)
                ->where('database_edition', '=', $target->database_edition)
                ->where('license_model', $target->license_model)
                ->first();
            $onDemandHourly = $onDemandHourly->where('database_engine', '=', $target->database_engine)
                ->where('database_edition', '=', $target->database_edition)
                ->where('license_model', $target->license_model)
                ->first();
        } else {
            $upfront = $upfront->where('os_name', '=', $target->os_name)->first();
            $upfrontHourly = $upfrontHourly->where('os_name', '=', $target->os_name)->first();
            $onDemandHourly = $onDemandHourly->where('os_name', '=', $target->os_name)->first();
        }
        
        return [$onDemandHourly, $upfrontHourly, null, $upfront];
    }
    
    /**
     * @param $target
     * @param Environment $targetEnvironmnent
     * @param Environment $existingEnvironment
     * @return array
     */
    public function calculateTargetAzureCosts(&$target, Environment $targetEnvironment)
    {
        $paymentOptionId = property_exists($target, 'computedPaymentOptionId')
            ? $target->computedPaymentOptionId
            : $targetEnvironment->payment_option_id;

        // This variable is set to true if the hybrid option does no exist
        $target->hybridDowngrade = false;

        $onDemandHourly = null;
        $reserved1Year = null;
        $reserved3Year = null;

        $paymentOption = $target->instance_type === AzureAds::INSTANCE_TYPE_ADS
            ? AmazonServer::getAzurePaymentOptionById($paymentOptionId, true)
            : AmazonServer::getAzurePaymentOptionById($paymentOptionId);

        if ($target->instance_type === AzureAds::INSTANCE_TYPE_ADS) {
            $azureQuery = AzureAds::where('name', '=', $target->name)
                ->where('region', '=', trim($targetEnvironment->region->name))
                ->where('ram', '=', $target->ram)
                ->where('vcpu_qty', '=', $target->vcpu_qty)
                ->where('service_type', $target->service_type)
                ->where('database_type', $target->database_type)
                ->where('category', $target->category)
                ->where('term_type', '=', $paymentOption['term_type'])
                ->where('term_length', '=', $paymentOption['lease_contract_length']);
        } else {
            $azureQuery = AmazonServer::where('name', '=', $target->name)
                ->where('instance_type', '=', "Azure")
                ->where('location', '=', $targetEnvironment->region->name)
                ->where('ram', '=', $target->ram)
                ->where('vcpu_qty', '=', $target->vcpu_qty)
                ->where('software_type', '=', $target->software_type)
                ->where('instance_family', '=', $target->instance_family)
                ->where('term_type', '=', $paymentOption['term_type'])
                ->where('lease_contract_length', '=', $paymentOption['lease_contract_length']);
        }

        // switch ($paymentOptionId) {
        switch ($paymentOption['name']) {
            //case 2: // Pay As You Go With Azure Hybrid Benefit
            case PaymentOption::AZURE_PAY_AS_YOU_GO_AHB:
                payAsYouGoWithAHB:
                $onDemandHourly = clone $azureQuery;
                $onDemandHourly = $onDemandHourly->where('is_hybrid', '=', 1)->first();

                // If no Hybrid version exists goto non Hybrid
                if (!$onDemandHourly) {
                    $target->hybridDowngrade = true;
                    goto payAsYouGo;
                }

                $target->include_hybrid = true;

                break;
            // case 1: // Pay As You
            case PaymentOption::AZURE_PAY_AS_YOU_GO:
                payAsYouGo:
                $onDemandHourly = $azureQuery->first();

                break;
            // case 6: // 1 Year Reserved With Azure Hybrid Benefit
            case PaymentOption::AZURE_ONE_YEAR_RESERVED_AHB:
                $reserved1Year = clone $azureQuery;
                $reserved1Year = $reserved1Year->where('is_hybrid', '=', 1)->first();

                // If no Hybrid version exists goto non Hybrid
                if (!$reserved1Year) {
                    $target->hybridDowngrade = true;
                    goto oneYearReserved;
                }

                $target->include_hybrid = true;

                break;
            // case 3: // One Year Reserved
            case PaymentOption::AZURE_ONE_YEAR_RESERVED:
                oneYearReserved:;
                $reserved1Year = clone $azureQuery;
                $reserved1Year = $reserved1Year->first();

                if (!$reserved1Year) {
                    $target->hybridDowngrade = true;
                    goto payAsYouGoWithAHB;
                }

                break;
            // case 5: // 3 Year Reserved With Azure Hybrid Benefit
            case PaymentOption::AZURE_THREE_YEAR_RESERVED_AHB:
                $reserved3Year = clone $azureQuery;
                $reserved3Year = $reserved3Year->where('is_hybrid', '=', 1)->first();

                // If no Hybrid version exists goto non Hybrid
                if (!$reserved3Year) {
                    $target->hybridDowngrade = true;
                    goto threeYearReserved;
                }

                $target->include_hybrid = true;
                
                break;
            // case 4: // Three Year Reserved
            case PaymentOption::AZURE_THREE_YEAR_RESERVED:
                threeYearReserved:
                $reserved3Year = clone $azureQuery;
                $reserved3Year = $reserved3Year->first();

                if (!$reserved3Year) {
                    $target->hybridDowngrade = true;
                    goto payAsYouGoWithAHB;
                }

                break;
        }
        
        $azureCosts = [$onDemandHourly, $reserved1Year, $reserved3Year, null];

        return $azureCosts;
    }
    
    /**
     * @param $target
     * @param Environment $targetEnvironmnent
     * @param Environment $existingEnvironment
     * @return array
     */
    public function calculateTargetGoogleCosts(&$target, Environment $targetEnvironment)
    {
        $paymentOptionId = $targetEnvironment->payment_option_id;

        // This variable is set to true if the hybrid option does no exist
        $target->hybridDowngrade = false;

        $onDemandHourly = null;
        $reserved1Year = null;
        $reserved3Year = null;

        $paymentOption = AmazonServer::getGooglePaymentOptionById($paymentOptionId);

        $googleQuery = AmazonServer::where('name', '=', $target->name)
            ->where('instance_type', '=', "Google")
            ->where('location', '=', $targetEnvironment->region->name)
            ->where('ram', '=', $target->ram)
            ->where('vcpu_qty', '=', $target->vcpu_qty)
            ->where('software_type', '=', isset($target->database_li_name) && $target->database_li_name ? $target->database_li_name : $target->software_type)
            ->where('instance_family', '=', $target->instance_family)
            ->where('term_type', '=', $paymentOption['term_type'])
            ->where('lease_contract_length', '=', $paymentOption['lease_contract_length']);

        switch ($paymentOptionId) {
            case 1: // On demand
                payAsYouGo:
                $onDemandHourly = $googleQuery->first();
                break;
            case 2: // 1 year commitment
                oneYearReserved:
                $reserved1Year = $googleQuery->first();
                break;
            case 3: // 3 year commitment
                threeYearReserved:
                $reserved3Year = $googleQuery->first();
                break;
        }
        return [$onDemandHourly, $reserved1Year, $reserved3Year, null];
    }
    
    /**
     * @param $target
     * @param Environment $targetEnvironmnent
     * @param Environment $existingEnvironment
     * @return array
     */
    public function calculateTargetIBMPVSCosts(&$target, Environment $targetEnvironment)
    {
      $paymentOptionId = $targetEnvironment->payment_option_id;

      // This variable is set to true if the hybrid option does no exist
      $target->hybridDowngrade = false;

      $onDemandHourly = null;
      $reserved1Year = null;
      $reserved3Year = null;

      $paymentOption = AmazonServer::getIBMPVSPaymentOptionById($paymentOptionId);

      $ibmpvsQuery = AmazonServer::where('name', '=', $target->name)
          ->where('instance_type', '=', "IBMPVS")
          ->where('location', 'LIKE', '%'. trim($targetEnvironment->region->name) . '%')
          ->where('ram', '=', $target->ram)
          ->where('vcpu_qty', '=', $target->vcpu_qty)
          ->where('software_type', '=', isset($target->database_li_name) && $target->database_li_name ? $target->database_li_name : $target->software_type)
          ->where('instance_family', '=', $target->instance_family)
          ->where('term_type', '=', $paymentOption['term_type']);

      switch ($paymentOptionId) {
          case 1: // On demand
              payAsYouGo:
              $onDemandHourly = $ibmpvsQuery->first();
              break;
          case 2: // 1 year commitment
              oneYearReserved:
              $reserved1Year = $ibmpvsQuery->first();
              break;
          case 3: // 3 year commitment
              threeYearReserved:
              $reserved3Year = $ibmpvsQuery->first();
              break;
      }
      return [$onDemandHourly, $reserved1Year, $reserved3Year, null];
    }

    /**
     * @return \App\Helpers\Analysis\Cloud
     */
    public function cloudHelper()
    {
        return resolve(\App\Helpers\Analysis\Cloud::class);
    }
}