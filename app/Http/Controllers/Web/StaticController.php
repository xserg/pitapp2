<?php
/**
 *
 */

namespace App\Http\Controllers\Web;


use App\Http\Controllers\Controller;
use App\Services\Deployment;
use Illuminate\Support\Facades\Request;

class StaticController extends Controller
{
    /**
     * Catch all HTTP requests and bootup the correct
     * admin or frontend template
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function __invoke(Deployment $deploymentService)
    {
        if (request()->ajax() || !preg_match('~^/api(/.*)?$~i', request()->getPathInfo())) {
            return abort(404);
        }

        $url = preg_replace("~^/api//?core/images/(.*)~i", "/core/images/$1", $_SERVER["REQUEST_URI"]);
        return \Redirect::to($url);
    }
}