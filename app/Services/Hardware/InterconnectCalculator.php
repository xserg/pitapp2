<?php

namespace App\Services\Hardware;

 class InterconnectCalculator {

     public $result;

     public function calculateNeededChassisInterconnect($serverConfigs, $storage = array()) {

         //setup return object
         $this->result = new \stdClass();
         $this->result->interconnect_chassis_list = array();
         $this->result->purchase_cost = 0;
         $this->result->annual_maintenance = 0;

         foreach ($serverConfigs as $config) {
             foreach ($config->targets as $targ) {
                 if (!isset($targ->parent_configuration_id) || !$targ->parent_configuration_id) {
                     $configUnits = 0;
                     if (isset($targ->interconnect_id) && $targ->interconnect_id) {
                         foreach ($targ->configs as $childTarg) {
                            $configUnits += $childTarg->qty;
                         }
                         $this->addToChassisList($targ, $configUnits, $config->servers[0]->location);
                     }
                 }
             }
         }
         foreach ($storage as $targ) {
             if (!$targ->parent_configuration_id) {
                 $configUnits = 0;
                 if (!isset($targ->parent_configuration_id) || isset($targ->interconnect_id) && $targ->interconnect_id) {
                     foreach ($targ->configs as $childTarg) {
                         $configUnits += $childTarg->qty;
                     }
                     $this->addToChassisList($targ, $configUnits, null);
                 }
             }
         }


         //calculate total costs for determined number of chassises
         foreach($this->result->interconnect_chassis_list as &$chassisType) {
             foreach ($chassisType['chassises'] as &$chassis) {
                 $this->result->purchase_cost += round(floatval($chassis->hardware_list_price) * ((100.00 - floatval($chassis->discount)) / 100.00));
                 $this->result->annual_maintenance += round(floatval($chassis->annual_maintenance_list_price) * ((100.00 - floatval($chassis->annual_maintenance_discount)) / 100.00));
             }
         }

         return $this->result;
     }

     private function addToChassisList(&$config, $configUnits, $location) {
         if (!isset($config->interconnect)) {
             return;
         }
         $searchResult = $this->searchForChassis($config->interconnect->id, $location);
         //if no $searchResult, this is the first chassis of this type at this location
         if ($searchResult === null) {

             $chassis = (object) (array) $config->interconnect;
             $chassis->manufacturerName = $config->interconnect->manufacturer->name;
             $chassis->modelName = $config->interconnect->model->name;
             $chassis->usedSpace = $configUnits;
             $chassis->totalSpace = $chassis->nodes_per_unit;

             $this->result->interconnect_chassis_list[] = [
                 'id' => $config->interconnect->id,
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
                 $chassis =  (object) (array)  $config->interconnect;
                 $chassis->manufacturerName = $config->interconnect->manufacturer->name;
                 $chassis->modelName = $config->interconnect->model->name;
                 $chassis->usedSpace = $configUnits;
                 $chassis->totalSpace = $chassis->nodes_per_unit;
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