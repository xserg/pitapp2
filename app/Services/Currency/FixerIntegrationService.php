<?php


namespace App\Services\Currency;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;


class FixerIntegrationService implements RatesServiceInterface
{
    protected $base_url;

    protected $api_key;

    protected $base_currency;

    /**
     * FixerIntegrationService constructor.
     */
    public function __construct()
    {
        $this->base_url = env('FIXER_API_URL', 'https://data.fixer.io/api/');
        $this->api_key = env('FIXER_API_KEY', null);
        $this->base_currency = env('FIXER_BASE', 'USD');
    }

    /**
     * @return array
     */
    public function getSymbols(): array
    {
        return Cache::remember('fixer-symbols', now()->addHours(24), function () {
            $data = $this->request('symbols');

            return $data['symbols'];
        });
    }

    /**
     * @return array
     */
    public function getRates(): array
    {
        return Cache::remember('fixer-rates', now()->addHours(24), function () {
            $data = $this->request('latest');

            return $data['rates'];
        });
    }

    /**
     * @param string $path
     * @param array $params
     * @return mixed
     * @throws \Exception
     */
    protected function request(string $path, array $params = [])
    {
        $http_client = new Client(['base_uri' => $this->base_url]);

        $query = array_merge([
            'access_key' => $this->api_key,
        ], $params);

        if (isset($this->base_currency)) {
            $query['base'] = $this->base_currency;
        }

        $response = $http_client->get($path, ['query' => $query]);

        $body = json_decode($response->getBody()->getContents(), true);

        if (!isset($body['success']) || !$body['success']) {
            Log::debug('Fixer Error: ' . json_encode($body));

            throw new \Exception('Fixer error: ' . $body['error']['info']);
        }

        return $body;
    }
}
