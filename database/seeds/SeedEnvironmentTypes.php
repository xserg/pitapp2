<?php

/**
 * Description of SeedEmailSettings
 *
 * @author jdobrowolski
 */

use App\Models\Project\EnvironmentType;
use Illuminate\Database\Seeder;

class SeedEnvironmentTypes extends Seeder {

    public function run() {
        $this->addEnvironmentType(EnvironmentType::ID_COMPUTE, "Compute");
        $this->addEnvironmentType(EnvironmentType::ID_CONVERGED, "Converged");
        $this->addEnvironmentType(EnvironmentType::ID_CLOUD, "Cloud");
        $this->addEnvironmentType(EnvironmentType::ID_COMPUTE_STORAGE, "Compute + Storage");
    }

    private function addEnvironmentType($id, $name) {
        $env = EnvironmentType::find($id);
        if ($env == null) {
            $env = new EnvironmentType();
            $env->id = $id;
        }
        $env->name = $name;
        $env->save();
    }
}
