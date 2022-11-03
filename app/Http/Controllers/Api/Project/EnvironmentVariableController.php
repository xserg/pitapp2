<?php
namespace App\Http\Controllers\Api\Project;

use App\Http\Controllers\Api\Hardware\InterconnectChassisController;
use App\Http\Controllers\Controller;
use App\Models\Hardware\AmazonServer;
use Illuminate\Support\Facades\Request;
use App\Models\Project\EnvironmentType;
use App\Models\Project\Provider;
use App\Models\Software\SoftwareType;
use App\Http\Controllers\Api\Software\SoftwareController;
use App\Http\Controllers\Api\Hardware\ProcessorController;
use App\Http\Controllers\Api\Hardware\ManufacturerController;
use App\Http\Controllers\Api\Hardware\ServerController;
use App\Models\Project\Cloud\AzureAdsServiceOption;

class EnvironmentVariableController extends Controller {

    protected $activity = 'Environment Variable Management';

    public function getEnvVariablesOld() {
      $environmentTypes = EnvironmentType::all();
      $providers = Provider::with('regions.currencies')->get();
      $environmentTypes = json_encode($environmentTypes->toArray());
      $providers = $providers->toArray();
      $providers = json_encode($providers);

      echo '{"environmentTypes" :' . $environmentTypes;
      echo ', "providers" :' . $providers;

      $sc = new SoftwareController;
      $softwares = $sc->index();
      //Softwares returns a response, we want to take out the headers
      $sIndex = strpos($softwares, '[');
      $softwares = substr($softwares, $sIndex);
      $softwareTypes = SoftwareType::all();
      $softwareTypes = json_encode($softwareTypes->toArray());
      echo ', "softwares": ' . $softwares . ', "softwareTypes": '. $softwareTypes.'}';
    }

    public function getEnvVariables()
    {
        $environmentTypes = EnvironmentType::all();
        $providers = Provider::with([
                'regions.currencies',
                'instanceCategories',
                'OsSoftwares',
            ])
            ->get();
        //* return `azure_ads_service_options` in Azure provider
        $azure = $providers
            ->filter(fn($provider) => $provider->name === Provider::AZURE)
            ->first();
        $azure['ads_service_options'] = AzureAdsServiceOption::all();

        $sc = new SoftwareController;
        $softwares = $sc->index();
        //Softwares returns a response, we want to take out the headers
        $sIndex = strpos($softwares, '[');
        $softwares = json_decode(substr($softwares, $sIndex));
        $softwareTypes = SoftwareType::all();
        $instanceCategories = [];

        return response()->json(
            compact(
                'environmentTypes',
                'providers',
                'softwares',
                'softwareTypes',
                'instanceCategories'
            )
        );
    }

    public function getHardwareVariables() {
        $sc = new ServerController;
        echo '{"servers": ';
        $sc->index();

        $mc = new ManufacturerController;
        echo ', "manufacturers": ';
        $mc->index();

        $pc = new ProcessorController;
        echo ', "processors" :';
        $pc->index();

        $pc = new InterconnectChassisController;
        echo ', "chassises" :';
        $pc->chassisRackList();
        echo '}';
    }

    public function getSoftwareVariables() {
        $sc = new SoftwareController;
        $softwares = $sc->index();
        //Softwares returns a response, we want to take out the headers
        $sIndex = strpos($softwares, '[');
        $softwares = substr($softwares, $sIndex);
        $softwareTypes = SoftwareType::all();
        $softwareTypes = json_encode($softwareTypes->toArray());
        return '{"softwares": ' . $softwares . ', "softwareTypes": '. $softwareTypes.'}';
    }

    public function getAllVariables() {
      $sc = new ServerController;
      echo '{"servers": ';
      $sc->index();

      $mc = new ManufacturerController;
      echo ', "manufacturers": ';
      $mc->index();

      $pc = new ProcessorController;
      echo ', "processors" :';
      $pc->index();

      $environmentTypes = EnvironmentType::all();
      $providers = Provider::with('regions.currencies')->get();
      $environmentTypes = json_encode($environmentTypes->toArray());
      $providers = json_encode($providers->toArray());

      echo ', "environmentTypes" :' . $environmentTypes;
      echo ', "providers" :' . $providers;

      $sc = new SoftwareController;
      $softwares = $sc->index();
      //Softwares returns a response, we want to take out the headers
      $sIndex = strpos($softwares, '[');
      $softwares = substr($softwares, $sIndex);
      $softwareTypes = SoftwareType::all();
      $softwareTypes = json_encode($softwareTypes->toArray());
      echo ', "softwares": ' . $softwares . ', "softwareTypes": '. $softwareTypes.'}';
    }
}
