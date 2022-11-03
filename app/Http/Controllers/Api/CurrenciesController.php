<?php


namespace App\Http\Controllers\Api;


use App\Http\Controllers\Controller;
use App\Services\Currency\CurrenciesListPresenter;
use App\Services\Currency\RatesServiceInterface;

class CurrenciesController extends Controller
{
    /**
     * @param RatesServiceInterface $rates_service
     * @return array
     */
    public function index(RatesServiceInterface $rates_service)
    {
        $keepCurrencies = ['USD', 'CAD', 'EUR', 'GBP', 'CHF', 'NZD', 'AUD', 'JPY'];

        return CurrenciesListPresenter::present($rates_service->getSymbols(), $keepCurrencies);
    }

    /**
     * @param RatesServiceInterface $rates_service
     * @return array
     */
    public function rates(RatesServiceInterface $rates_service)
    {
        return $rates_service->getRates();
    }
}