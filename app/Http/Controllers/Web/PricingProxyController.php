<?php

namespace App\Http\Controllers\Web;


use App\Http\Controllers\Controller;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Response;

class PricingProxyController extends Controller
{
    /**
     * @throws GuzzleException
     */
    public function getAzurePricingPage(Request $request, $azurePath) {
        $client = new Client(['base_uri' => 'https://azure.microsoft.com/']);
        $resp = $client->request('GET', $azurePath);
        $response = Response::make($resp->getBody(), $resp->getStatusCode());
        $response->header('Content-Type', $resp->getHeaderLine('Content-Type'));
        return $response;
    }
}
