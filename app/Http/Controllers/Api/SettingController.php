<?php

namespace App\Http\Controllers\Api;

use App\Models\Configuration\Setting;
use App\Models\Configuration\ValueType;
use App\Models\StandardModule\Activity;
use App\Models\StandardModule\Component;
use App\Http\Controllers\Api\StandardModule\SmartController;
use Illuminate\Support\Facades\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Validator;

class SettingController extends SmartController {

    private $messages = null;

    protected $activity = 'Configuration Management';
    protected $cached = false;
    protected $model = "App\Models\Configuration\Setting";
    protected $table = 'settings';

    // private method to set the data of the user for the create and update.
    private function setData($setting) {
        // Validate request fields
        $this->validateFields(
            $setting->key,
            $setting->value,
            $setting->component_id,
            $setting->activity_id,
            $setting->value_type_id
        );

        if ($this->messages) {
            return -1;
        }

        $setting->save();

        $newSetting = $setting->reload();

        return $newSetting->id;
    }

    private function validateFields($key, $value, $component_id, $activity_id, $value_type_id) {
        $errors = [];

        // Check if fields exist in $request
        if (!isset($key)) {
            $errors[] = 'key not found in setting';
        }
        if (!isset($value)) {
            $errors[] = 'value not found in setting';
        }
        if (!isset($component_id)) {
            $errors[] = 'component_id not found in setting';
        }
        if (!isset($activity_id)) {
            $errors[] = 'activity_id not found in setting';
        }
        if (!isset($value_type_id)) {
            $errors[] = 'value_type_id not found in setting';
        }

        if (count($errors) > 0) {
            $this->messages = $errors;
            return;
        }

        $validator = Validator::make(
            array (
                'key' => $key,
                'value' => $value,
                'component_id' => $component_id,
                'activity_id' => $activity_id,
                'value_type_id' => $value_type_id
            )
            , array(
                'key' => 'required|max:100',
                'value' => 'required|max:100',
                'component_id' => 'exists:components,id',
                'activity_id' => 'exists:activities,id',
                'value_type_id' => 'exists:value_types,id'
            )
        );

        if($validator->fails()) {
            $this->messages = $validator->messages();
            return;
        }
        else {
            $this->messages = null;
        }


        // Passed all previous validation
        $valueType = ValueType::where('id','=',$value_type_id)->first();
        $this->validateValueType($valueType->label, $value);
    }

    private function validateValueType($type, $value) {
        if (strcasecmp($type, 'boolean') == 0 ) {
            // Validate boolean beign an accepted value
            switch(strtolower($value)) {
                case '1':
                case 'true':
                case '0':
                case 'false':
                    return true;
                default:
                    return false;
            }
        }
        else if (strcasecmp($type, 'unsigned integer') == 0 ) {
            // Validate unsigned integer being made of digits 0-9
            $validator = Validator::make(
                array (
                    'value' => $value
                )
                , array(
                    'value' => 'regex:/^[0-9]*$/'
                )
            );

            if($validator->fails()) {
                $this->messages = $validator->messages();
                return false;
            }
            else {
                return true;
            }
        }
        else if(strcasecmp($type, 'string') == 0){
            //Validate that it is a string
            if(isset($value)){
                return true;
            }
            else
            {
                return false;
            }
        }

        return false;
    }

    /**
     * Creates a new Setting.
     * @return Response
     */
    // TODO: Use and test
    public function createSetting() {
        $request = Request::all();

        // Validate request fields
        $this->validateFields(
            $request->key,
            $request->value,
            $request->component_id,
            $request->activity_id,
            $request->value_type_id
        );

        if(!$this->messages) {

            // Create new setting if key and component_id does not exist
            if (Setting::where('key', '=', $this->key)
                    ->where('component_id', '=', $this->component_id)
                    ->count() == 0
            ) {

                $setting = new Setting();

                $setting->key = $request->key;
                $setting->value = $request->value;
                $setting->component_id = $request->component_id;
                $setting->activity_id = $request->activity_id;
                $setting->value_type_id = $request->value_type_id;

                // Reload for proper id
                $newSetting = $setting->reload();

                /* Send a 200 response signalling that the user creation was successful. */
                return response()->json($newSetting, 200);
            } else {

                /* Otherwise send a response signalling failure. */
                return response()->json("The key and component_id already exists", 409);
            }
        } else {
            return response()->json($this->messages->toArray(), 500);
        }

    }


    /**
     * Store a newly created resource in storage.
     *
     * @return Response
     */
    protected function _store() {
    }

    /**
     * Display the specified resource .
     *
     * @param  int  $component_id
     * @return Response
     */
    protected function _show($id) {
        return response()->json(Setting::where('component_id','=',$id)->get(), 200);
    }

    // Returns data used for setting.list based on component
    protected function getSettingListData($component_id) {
        $settings = Setting::where('component_id','=',$component_id)->get();
        $activities = [];

        foreach ($settings as $setting) {
            $activity = new \stdClass();

            $activity->component_name = Component::find($setting->component_id)->name;
            $activity->activity_name = Activity::find($setting->activity_id)->name;
            $activity->activity_id = $setting->activity_id;

            if (!in_array($activity, $activities, true)) {
                $activities[] = $activity;
            }
        }

        return response()->json($activities, 200);
    }

    // Return setting data for setting.single
    protected function getSettingData($activity_id) {
        $settings = Setting::where('activity_id','=',$activity_id)->get();
        foreach ($settings as $setting) {
            $component = Component::find($setting->component_id);
            $valueType = ValueType::find($setting->value_type_id);

            $setting->component_created_at = $component->created_at;
            $setting->component_updated_at = $component->updated_at;
            $setting->type = $valueType->label;

            if (strcmp($valueType->label, 'boolean') === 0) {
                $setting->input_type = 'checkbox';
            }
            else {
                $setting->input_type = 'text';
            }

        }
        return response()->json($settings, 200);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int  $id
     * @return Response
     */
    protected function _update($id) {

        $setting = Setting::find($id);

        if ($this->setData($setting) < 0) {
            return response()->json($this->messages, 500);
        }

        return response()->json($setting->key . $setting->component_id . " saved");
    }

    // Added to accept a list of Setting objects.
    protected function bulkUpdate() {
        $request = Request::all();

        $settings = [];

        foreach ($request as $request) {

            $settingObj = new \stdClass();

            $settingObj->id = $request['id'];
            $settingObj->key = $request['key'];
            $settingObj->value = $request['value'];
            $settingObj->component_id = $request['component_id'];
            $settingObj->activity_id = $request['activity_id'];
            $settingObj->value_type_id = $request['value_type_id'];
            $settingObj->error_id = 'error_' . $request['id'];

            $settings[] = $settingObj;
        }

        //$settings = $request->data;
        $results = new \stdClass();
        $responseCode = 200;

        // Verify each exists
        foreach ($settings as $setting) {
            $key = $setting->error_id;

            $settingFound = Setting::find($setting->id);

            if (!$settingFound) {
                return response()->json(['Unable to find setting for id ' . $setting->id], 500);
            }

            $this->validateFields(
                $setting->key,
                $setting->value,
                $setting->component_id,
                $setting->activity_id,
                $setting->value_type_id
            );

            if ($this->messages) {
                $results->$key = "Error validating settings " . $setting->key . $setting->component_id;
                $responseCode = 500;
            }
        }

        if ($responseCode === 200) {
            $elasticSettings = false;
            // Start saving after initial validation.
            foreach ($settings as $setting) {
                $key = $setting->error_id;

                $settingFound = Setting::find($setting->id);

                $settingFound->key = $setting->key;
                $settingFound->value = $setting->value;
                $settingFound->component_id = $setting->component_id;
                $settingFound->activity_id = $setting->activity_id;
                $settingFound->value_type_id = $setting->value_type_id;

                if($settingFound->key === "Foundation.StandardModule_ElasticServerPath"){
                    $elasticSettings = true;
                }

                if ($this->setData($settingFound) < 0) {
                    $results->$key = "Error saving settings " . $setting->key . $setting->component_id;
                    $responseCode = 500;
                }
                else {
                    $results->$key = $setting->key . $setting->component_id . " saved";
                }
            }
            //This will handle deep setting changes in the env for elastic settings
            if($elasticSettings){
                $this->deepElasticUpdate();
            }
        }

        return response()->json([$results], $responseCode);
    }

    protected function deepElasticUpdate(){
        $useElastic = Setting::where("key", "=", "Foundation.StandardModule_UseElastic")->first();
        $elasticPath = Setting::where("key", "=", "Foundation.StandardModule_ElasticServerPath")->first();
        $elasticPort = Setting::where("key", "=", "Foundation.StandardModule_ElasticPort")->first();

        $original = file_get_contents(base_path()."/.env");
        $exploded = explode("\n", $original);
        $newFile = [];
        $foundPath = false;
        $foundPort = false;
        foreach($exploded as $line){
            if($line === "ELASTIC_HOST=".env("ELASTIC_HOST")){
                $foundPath = true;
                if($useElastic->value && $elasticPath->value !== ""){
                    $newFile[] = "ELASTIC_HOST=".$elasticPath->value;
                }
            }
            elseif($line === "ELASTIC_PORT=".env("ELASTIC_PORT"))
            {
                $foundPort = true;
                if($useElastic->value && $elasticPort->value)
                {
                    $newFile[] = "ELASTIC_PORT=".$elasticPort->value;
                }
            }
            else
            {
                $newFile[] = $line;
            }
        }
        if(!$foundPath && $useElastic->value && $elasticPath->value !== ""){
            $newFile[] = "ELASTIC_HOST=".$elasticPath->value;
        }
        if(!$foundPort && $useElastic->value && $elasticPort->value){
            $newFile[] = "ELASTIC_PORT=".$elasticPort->value;
        }
        $imploded = implode("\n", $newFile);
        file_put_contents(base_path()."/.env", $imploded);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    protected function _destroy($id) {

    }

}
