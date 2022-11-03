<?php namespace App\Http\Middleware;

use Closure;
use Auth;

class SmartMiddleware {

	/**
	 * Handle an incoming request.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @param  \Closure  $next
	 * @return mixed
	 */
	public function handle($request, Closure $next) {
        // Modification rsegura 4/28/2015 - Adding check for if we need to
        // handle Auth or it the server will handle it for use
        if(env("IMPLEMENT_BASIC")) {
            return Auth::onceBasic('username') ?: $next($request);
        }
        
        // If implement basic is false then just handle the request
        return $next($request);
        
	}

}
