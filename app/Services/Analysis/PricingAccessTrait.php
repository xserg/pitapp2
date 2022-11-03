<?php
/**
 *
 */

namespace App\Services\Analysis;


trait PricingAccessTrait
{
    /**
     * @return Pricing
     */
    public function pricingService()
    {
        return resolve(Pricing::class);
    }
}