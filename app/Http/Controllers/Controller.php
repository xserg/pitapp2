<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Memcached;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;
    
    /**
     * Returns a Memcached instance for the controller
     * @return Memcached
     */
    public function getMemcached()
    {
      if (!isset($this->memcached)) {
        $this->memcached = new Memcached();
        $this->memcached->addServer('localhost', 11211);
      }
      return $this->memcached;
    }

    /**
     * @param \Illuminate\Http\JsonResponse $response
     * @return $this
     */
    public function makeResponsePublic($response)
    {
        $oneYear = Carbon::now()->addYear();

        return $response->setPublic()
        ->setExpires($oneYear)
        ->setMaxAge(3600 * 24 * 365);
    }

    public function relativeRedirect($path)
    {
        return response('', 302, ['Location' => $path]);
    }
}
