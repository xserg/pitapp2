<?php
/**
 *
 */

namespace App\Http\Controllers\Api;

use Illuminate\Support\Facades\App;

class CacheController extends \App\Http\Controllers\Controller
{
    /**
     * @return \Illuminate\Http\RedirectResponse
     */
    public function __invoke()
    {
        $uri = str_replace('/api', '', request()->getUri());
        // $uri = preg_replace("~^http:~", isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on' ? "https:" : "http:", $uri);
        $uri = preg_replace("~^http:~", $this->getRequestProtocol(), $uri);

        header('HTTP/1.1 301 Moved Permanently');
        header('Location: ' . $uri);
        exit();
    }

    /**
     * Return the Request scheme
     *
     * @return string
     */
    private function getRequestProtocol()
    {
        /* always return insecure scheme for local env */
        return App::environment('local') ? 'http:' : 'https:';
        
        // doesn't seem to work because of proxy
        // return request()->isSecure() ? 'https:' : 'http:';
    }
}
