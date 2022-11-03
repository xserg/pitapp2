<?php

namespace App\Services\Hardware;

 class ChassisCalculator extends AbstractInterchassisCalculator {

    public $result;

    public function calculateNeededChassisInterconnect($serverConfigs) {

        //setup return object
        $this->result = new \stdClass();
        $this->result->interconnect_chassis_list = array();
        $this->result->purchase_cost = 0;
        $this->result->annual_maintenance = 0;

        $newTargets = array();
        foreach ($serverConfigs as $config) {
            foreach ($config->targets as $targ) {
                if (isset($targ->chassis) && $targ->chassis) {
                    $targ->parent_location = $config->servers[0]->location;
                }
            }

            $newTargets = collect($newTargets)->merge(collect($config->targets))->toArray();
        }

        usort($newTargets, function($a, $b) {
                        //null locations go last
            if (isset($a->parent_location) && !$a->parent_location && $b->parent_location) {
                return 1;
            }
            return $a->rack_units - $b->rack_units;
        });


            foreach ($newTargets as $targ) {
                if (isset($targ->chassis) && $targ->chassis) {
                    //total_qty is how many of that server we are dealing with
                    for ($i = 1; $i <= $targ->qty; $i++) {
                        $this->addToChassisList($targ, $targ->parent_location);
                    }
                }
            }

        //calculate total costs for determined number of chassises
        foreach($this->result->interconnect_chassis_list as &$chassisType) {
            foreach ($chassisType['chassises'] as &$chassis) {
                $this->result->purchase_cost += round((float)$chassis->hardware_list_price * ((float)(100 - $chassis->discount) / 100));
                $this->result->annual_maintenance += round((float)$chassis->annual_maintenance_list_price * ((float)(100 - $chassis->annual_maintenance_discount) / 100));
            }
        }

        return $this->result;
    }

    private function addToChassisList(&$config, $location) {

        //setup unit variables for server size, chassis size, and unit type
        $chassisUnitType = ($config->chassis->rack_units ? 'rack_units' : 'nodes_per_unit');
        $configUnits = ($config->chassis->rack_units ? $config->rack_units : 1);

        //if using rack_units, ignore servers that are too large for their rack
        if ($chassisUnitType == 'rack_units') {
            if ($config->rack_units > $config->chassis->rack_units) {
                return;
            }
        }
        $searchResult = $this->searchForChassis($config->chassis->id, $location);
        //if no $searchResult, this is the first chassis of this type at this location
        if ($searchResult === null) {

            $chassis =  (object) (array) $config->chassis;
            $chassis->manufacturerName = $config->chassis->manufacturer->name;
            $chassis->modelName = $config->chassis->model->name;
            $chassis->usedSpace = $configUnits;
            $chassis->totalSpace = $chassis->$chassisUnitType;

            $this->result->interconnect_chassis_list[] = [
                'id' => $config->chassis->id,
                'chassises' => [$chassis],
                'location' => $location,
                'qty' => 1
            ];

        // this chassisType already exists in this location, so try to place this server in a free slot
        } else {
            $foundSpace = false;
            //searching for free slots
            foreach($this->result->interconnect_chassis_list[$searchResult]['chassises'] as &$existChassis) {
                if ($existChassis->totalSpace - $existChassis->usedSpace >= $configUnits) {
                    $existChassis->usedSpace += $configUnits;
                    $foundSpace = true;
                    break;
                }
            }

            //no free slot was found, add a new chassis of this type
            if ($foundSpace == false) {
                $chassis =  (object) (array) $config->chassis;
                $chassis->manufacturerName = $config->chassis->manufacturer->name;
                $chassis->modelName = $config->chassis->model->name;
                $chassis->usedSpace = $configUnits;
                $chassis->totalSpace = $chassis->$chassisUnitType;
                $this->result->interconnect_chassis_list[$searchResult]['qty']++;
                $this->result->interconnect_chassis_list[$searchResult]['chassises'][] = $chassis;
            }
        }
    }

    private function searchForChassis($id, $location) {

         foreach ($this->result->interconnect_chassis_list as $key => $val) {
             if ($val['id'] == $id && ($val['location'] == $location || !$location)) {
                 return $key;
             }
         }
         return null;
     }
}