<?php
/**
 *
 */

namespace App\Http\Controllers\Api;


class DeploymentKeyController extends \App\Http\Controllers\Controller
{

    public function __invoke()
    {
        $deployment = resolve(\App\Services\Deployment::class);

        return response()->json([
            'key' => $deployment->getDeploymentKey(),
            'env' => \App::environment('local') ? 'local' : 'production'
        ], 200);
    }
}