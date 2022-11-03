<?php


namespace App\Services\Currency;


class CannedRatesService implements RatesServiceInterface
{

    public function getSymbols(): array
    {
        return [
            "AUD" => "AUD Dollar",
            "CAD" => "CAD Dollar",
            "CHF" => "CHF Franc",
            "EUR" => "EUR Euro",
            "GBP" => "GBP Sterling",
            "JPY" => "JPY Yen",
            "NZD" => "NZD Dollar",
            "USD" => "USD Dollar"
        ];
    }

    public function getRates(): array
    {
        // Snapshot of currencies, do not use this in production!
        return [
            "AUD" => 1.287167,
            "CAD" => 1.267635,
            "CHF" => 0.891202,
            "EUR" => 0.824436,
            "GBP" => 0.719665,
            "JPY" => 105.008966,
            "NZD" => 1.38362,
            "USD" => 1
        ];
    }
}