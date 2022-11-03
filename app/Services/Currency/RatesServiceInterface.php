<?php


namespace App\Services\Currency;


interface RatesServiceInterface
{
    /**
     * @return array
     */
    public function getSymbols(): array;

    /**
     * @return array ['CURRENCY' => rate, ...]
     */
    public function getRates(): array;
}