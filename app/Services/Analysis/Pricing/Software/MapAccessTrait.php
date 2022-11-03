<?php
/**
 *
 */

namespace App\Services\Analysis\Pricing\Software;


trait MapAccessTrait
{
    /**
     * @return Map
     */
    public function softwareMap()
    {
        return resolve(Map::class);
    }
}