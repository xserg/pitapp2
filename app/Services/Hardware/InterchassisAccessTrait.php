<?php
/**
 *
 */

namespace App\Services\Hardware;


trait InterchassisAccessTrait
{
    /**
     * @return InterconnectCalculator
     */
    public function interconnectCalculator()
    {
        return resolve(InterconnectCalculator::class);
    }

    /**
     * @return ChassisCalculator
     */
    public function chassisCalculator()
    {
        return resolve(ChassisCalculator::class);
    }
}