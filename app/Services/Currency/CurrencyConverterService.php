<?php


namespace App\Services\Currency;


use NumberFormatter;

class CurrencyConverterService
{
    protected $fixer_service;

    protected $rates;

    protected $default_target_currency;

    /**
     * CurrencyFormatterService constructor.
     * @param RatesServiceInterface $fixer_service
     */
    public function __construct(RatesServiceInterface $fixer_service)
    {
        $this->fixer_service = $fixer_service;
        $this->rates = $fixer_service->getRates();
        $this->default_target_currency = 'USD';
    }

    /**
     * @param string $target_currency
     * @param $value
     * @return float|int
     * @throws \Exception
     */
    public function convert($value, $target_currency = null, $fractions = 0)
    {
        $currency = $this->getCurrency($target_currency);

        if (isset($target_currency)) {
            $this->checkIsExists($target_currency);
        }

        return round($value * $this->rates[$currency], $fractions);
    }

    /**
     * @param $value
     * @param string $target_currency
     * @param int $fractions
     * @return string
     * @throws \Exception
     */
    public function format($value, $target_currency = null, $fractions = 0): string
    {
        $currency = $this->getCurrency($target_currency);

        if (!empty($target_currency)) {
            $this->checkIsExists($currency);
        }

        $currency_formatter = new NumberFormatter('en-US', NumberFormatter::CURRENCY);

        $currency_formatter->setAttribute(NumberFormatter::FRACTION_DIGITS, $fractions);

        $result = $currency_formatter->formatCurrency($value, $currency);

        if (!$fractions) {
            $result = preg_replace('/\.0+$/', '', $result);
        }

        return $result;
    }

    /**
     * @param $value
     * @param string|null $target_currency
     * @return string
     * @throws \Exception
     */
    public function convertAndFormat($value, $target_currency = null, $fractions = 0): string
    {
        return $this->format(
            $this->convert($value, $target_currency, $fractions),
            $target_currency,
            $fractions
        );
    }

    /**
     * @param string $currency
     * @return CurrencyConverterService
     * @throws \Exception
     */
    public function setDefaultTarget($currency): CurrencyConverterService
    {
        $currency = $this->getCurrency($currency);

        $this->checkIsExists($currency);

        $this->default_target_currency = $currency;

        return $this;
    }

    /**
     * @param string $currency
     * @throws \Exception
     */
    protected function checkIsExists($currency)
    {
        if (!key_exists($currency, $this->rates)) {
            throw new \Exception('Currency ' . $currency . ' doesn\'t present in rates list');
        }
    }

    /**
     * @param $name
     * @return string
     */
    protected function getCurrency($name): string
    {
        return !empty($name) ? $name : $this->default_target_currency;
    }
}