<?php
/**
 *
 */

namespace App\Services\Analysis\Pricing\Environment\Cloud;


use App\Models\Project\Environment;

class BandwidthCalculator
{
    /**
     * @param Environment $environment
     * @return $this
     */
    public function calculateCosts(Environment $environment)
    {
        if (!$environment->cloud_bandwidth) {
            return $this;
        }

        list ($cost, $bandwidths, $bandwidthCosts) = $environment->isAws()
            ? $this->_calculateAwsBandwidth($environment)
            : ($environment->isGoogle() ? $this->_calculateGoogleBandwidth($environment) 
            : ($environment->isIBMPVS() ? $this->_calculateIBMPVSBandwidth($environment) : $this->_calculateAzureBandwidth($environment)));

        $environment->bandwidths = $bandwidths;
        $environment->bandwidthCosts = $bandwidthCosts;
        $environment->network_costs = $cost * 12 * $environment->project->support_years * ($environment->max_utilization / 100.0);
        $environment->network_per_yer = $environment->network_costs / $environment->project->support_years;

        return $this;
    }

    /**
     * @param Environment $environment
     * @return array
     */
    protected function _calculateAwsBandwidth(Environment $environment)
    {
        $cost = 0.00;
        $bandwidths = [];
        $bandwidthCosts = [];
        $usedBandwidth = $environment->cloud_bandwidth;
        if ($usedBandwidth > 0) {
            $cost = 0;
            $environment->networkGbMonth = 0;
            $bandwidths[] = min($usedBandwidth, 1);
            $bandwidthCosts[] = 0;
            $usedBandwidth -= 1;
        }
        if ($usedBandwidth > 0) {
            $cost += min($usedBandwidth, 10 * 1024 - 1) * .09;
            $environment->networkGbMonth = .09;
            $bandwidths[] = min($usedBandwidth, 10 * 1024 - 1);
            $bandwidthCosts[] = .09;
            $usedBandwidth -= (10 * 1024 - 1);
        }
        if ($usedBandwidth > 0) {
            $cost += min($usedBandwidth, 40 * 1024) * .09;
            $environment->networkGbMonth = .09;
            $bandwidths[] = min($usedBandwidth, 40 * 1024);
            $bandwidthCosts[] = .09;
            $usedBandwidth -= 40 * 1024;
        }
        if ($usedBandwidth > 0) {
            $cost += min($usedBandwidth, 300 * 1024) * .07;
            $environment->networkGbMonth = .07;
            $bandwidths[] = min($usedBandwidth, 300 * 1024);
            $bandwidthCosts[] = .07;
            $usedBandwidth -= 300 * 1024;
        }
        if ($usedBandwidth > 0) {
            $cost += $usedBandwidth * .05;
            $environment->networkGbMonth = .05;
            $bandwidths[] = $usedBandwidth;
            $bandwidthCosts[] = .05;
        }

        return [$cost, $bandwidths, $bandwidthCosts];
    }

    /**
     * @param Environment $environment
     * @return array
     */
    protected function _calculateAzureBandwidth(Environment $environment)
    {
        $bandwidths = [];
        $bandwidthCosts = [];
        $cost = 0.00;
        $usedBandwidth = $environment->cloud_bandwidth;
        if ($usedBandwidth > 0) {
            $cost = 0;
            $environment->networkGbMonth = 0;
            $usedBandwidth -= 5;
            $bandwidths[] = min($usedBandwidth, 5);
            $bandwidthCosts[] = 0;
        }
        if ($usedBandwidth > 0) {
            $cost += min($usedBandwidth, 10 * 1024 - 5) * .087;
            $environment->networkGbMonth = .087;
            $bandwidths[] = min($usedBandwidth, 10 * 1024 - 5);
            $bandwidthCosts[] = .087;
            $usedBandwidth -= (10 * 1024 - 5);
        }
        if ($usedBandwidth > 0) {
            $cost += min($usedBandwidth, 40 * 1024) * .083;
            $environment->networkGbMonth = .083;
            $bandwidths[] = min($usedBandwidth, 40 * 1024);
            $bandwidthCosts[] = .083;
            $usedBandwidth -= 40 * 1024;
        }
        if ($usedBandwidth > 0) {
            $cost += min($usedBandwidth, 300 * 1024) * .07;
            $environment->networkGbMonth = .07;
            $bandwidths[] = min($usedBandwidth, 300 * 1024);
            $bandwidthCosts[] = .07;
            $usedBandwidth -= 300 * 1024;
        }
        if ($usedBandwidth > 0) {
            $cost += $usedBandwidth * .05;
            $environment->networkGbMonth = .05;
            $bandwidths[] = $usedBandwidth;
            $bandwidthCosts[] = .05;
        }

        return [$cost, $bandwidths, $bandwidthCosts];
    }
    
    /**
     * @param Environment $environment
     * @return array
     */
    protected function _calculateGoogleBandwidth(Environment $environment)
    {
        $bandwidths = [];
        $bandwidthCosts = [];
        $cost = 0.00;
        $usedBandwidth = $environment->cloud_bandwidth;
        // 0-1 TB	$0.12
        if ($usedBandwidth > 0) {
            $cost += min($usedBandwidth, 1024) * .12;
            $environment->networkGbMonth = .12;
            $bandwidths[] = min($usedBandwidth, 1024);
            $bandwidthCosts[] = .12;
            $usedBandwidth -= 1024;
        }
        // 1-10 TB	$0.11
        if ($usedBandwidth > 0) {
            $cost += min($usedBandwidth, 9 * 1024) * .11;
            $environment->networkGbMonth = .11;
            $bandwidths[] = min($usedBandwidth, 9 * 1024);
            $bandwidthCosts[] = .11;
            $usedBandwidth -= 9 * 1024;
        }
        // 10+ TB	$0.08
        if ($usedBandwidth > 0) {
            $cost += $usedBandwidth * .08;
            $environment->networkGbMonth = .08;
            $bandwidths[] = $usedBandwidth;
            $bandwidthCosts[] = .08;
        }

        return [$cost, $bandwidths, $bandwidthCosts];
    }
    
    /**
     * @param Environment $environment
     * @return array
     */
    protected function _calculateIBMPVSBandwidth(Environment $environment)
    {
        $bandwidths = [$environment->cloud_bandwidth];
        $bandwidthCosts = [0.09];
        $cost = $environment->cloud_bandwidth * 0.09;

        return [$cost, $bandwidths, $bandwidthCosts];
    }
}