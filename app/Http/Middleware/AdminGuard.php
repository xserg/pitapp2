<?php namespace App\Http\Middleware;

use App\Models\Project\Environment;
use App\Models\Project\Project;
use App\Models\StandardModule\User;
use Closure;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Support\Facades\Auth;

class AdminGuard {

	/**
	 * The Guard implementation.
	 *
	 * @var Guard
	 */
	protected $auth;

	/**
	 * Create a new filter instance.
	 *
	 * @param  Guard  $auth
	 * @return void
	 */
	public function __construct(Guard $auth)
	{
		$this->auth = $auth;
	}

	/**
	 * Handle an incoming request.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @param  \Closure  $next
	 * @return mixed
	 */
	public function handle($request, Closure $next)
	{

        $profile = Auth::user();

		$user = \App\Models\UserManagement\User::with('groups')->find($profile->user_id);


        foreach($user->groups as $group) {
            if($group->name == "Admin") {
                return $next($request);
            }
        }

        abort(401);
	}

}
