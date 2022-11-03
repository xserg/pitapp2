<?php


namespace App\Services\Currency;


class CurrenciesListPresenter
{
    /**
     * @param array $currencies
     * @param array $keep
     * @return array
     */
    public static function present(array $currencies, array $keep = [])
    {
        $collection = collect($currencies);

        $collection = empty($keep) ? $collection : $collection->only($keep);

        return $collection->map(function($name, $code) {
            $segments = explode(' ', $name);

            // Get only currency code and name
            return $code . ' ' . array_pop($segments);
        })->toArray();
    }
}