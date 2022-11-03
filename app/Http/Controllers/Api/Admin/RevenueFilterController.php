<?php
/**
 * @author *
 */
namespace App\Http\Controllers\Api\Admin;

use App\Models\Hardware\Manufacturer;
use App\Models\Project\Company;
use App\Services\Revenue;

class RevenueFilterController extends \App\Http\Controllers\Controller
{
    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function __invoke()
    {
        $companies = Company::getQuery()->orderBy('name')->get();

        $providers = [];
        foreach($companies as $company) {
            $providers[] = [
                'id' => $company->id,
                'name' => $company->name
            ];
        }

        $manufacturerResult = Manufacturer::groupBy('name')->get();
        $manufacturers = [];

        foreach ($manufacturerResult as $manu) {
            $manufacturers[] = [
                'id' => $manu->id,
                'name' => $manu->name
            ];
        }

        $manufacturers[] = [
            'id' => 'AWS',
            'name' => 'AWS'
        ];

        $manufacturers[] = [
            'id' => 'Azure',
            'name' => 'Azure'
        ];
        
        $manufacturers[] = [
            'id' => 'Google',
            'name' => 'Google'
        ];
        
        $manufacturers[] = [
            'id' => 'IBMPVS',
            'name' => 'IBM PVS'
        ];

        usort($manufacturers, function($a, $b) {
            return strcmp($a['name'], $b['name']);
        });

        $data = [
            'providers' => $providers,
            'manufacturers' => $manufacturers
        ];

        return response()->json($data);
    }
}