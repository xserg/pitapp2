<?php
/**
 *
 */

namespace App\Http\Controllers\Web;


use App\Http\Controllers\Controller;
use App\Services\Deployment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PrecisionController extends Controller
{
    const ADMIN_LAYOUT = 'layouts/admin';
    const FRONTEND_LAYOUT = 'layouts/frontend';
    const NEW_FRONTEND_LAYOUT = 'layouts/frontend-v2';

    /**
     * @param Deployment $deploymentService
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View|void
     */
    public function __invoke(Deployment $deploymentService)
    {
        header("Cache-Control: no-cache, no-store, must-revalidate");
        header("Pragma: no-cache");
        header("Expires: 0");

        if (request()->ajax() || preg_match('~^/api(/.*)?$~i', request()->getPathInfo())) {
            return abort(404);
        }

        if (env('UI_V2', false)) {
            return $this->newFrontendManager();
        }

        return $this->frontendManager($deploymentService);
    }

    /**
     * @param Deployment $deploymentService
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View|void
     */
    public function frontendManager(Deployment $deploymentService)
    {
        $host = request()->getHttpHost(); // returns dev.site.com
        // Also check for local development admin site
        $forwardHost = request()->header('X-Forwarded-Host');
        $isAdmin = strstr(strtolower($host), 'admin') || $forwardHost == 'localhost:8081';

        $layout = $isAdmin ? self::ADMIN_LAYOUT : self::FRONTEND_LAYOUT;

        if (!Auth::check() && strpos(request()->url(), 'login') === false) {
            if (
                !Auth::check()
                && strpos(request()->url(), 'login') === false
                && strpos(request()->url(), 'registration') === false
            ) {
                // Relative path redirect
                return $this->relativeRedirect('/auth/login');
            }
        }

        return view($layout, [
            'isAdmin' => $isAdmin,
            'isFrontend' => !$isAdmin,
            'jsConfig' => [
                'deployment_key' => $deploymentService->getDeploymentKey(),
                'deployment_last' => $deploymentService->getLastDeployment()
            ]
        ]);
    }

    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View|void
     */
    public function newFrontendManager()
    {
        return view(self::NEW_FRONTEND_LAYOUT);
    }
}