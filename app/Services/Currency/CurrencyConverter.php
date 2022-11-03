<?php


namespace App\Services\Currency;


use Illuminate\Support\Facades\Facade;

class CurrencyConverter extends Facade
{
    protected static function getFacadeAccessor()
    {
        return CurrencyConverterService::class;
    }
}