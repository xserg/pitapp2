<?php namespace App\Http\Middleware;

use App\Models\Project\Environment;
use App\Models\Project\Project;
use App\Models\StandardModule\User;
use Closure;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Support\Facades\Auth;

class EnvironmentGuard {

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

		if ($this->auth->guest())
		{
			if ($request->wantsJson())
			{
				return response('Unauthorized.', 401);
			}
			else
			{
				return redirect()->guest('auth/login');
			}
		}

        $profile = Auth::user();

		$user = \App\Models\UserManagement\User::with('groups')->find($profile->user_id);

		$id = $request->route()->parameter('environment');
        if (!$id) {
            $id = $request->route()->parameter('id');
        }

        $env = Environment::with('project')->find($id);
		if (!$env) {
		    abort(404);
        } else {
            foreach($user->groups as $group) {
                if($group->name == "Admin") {
                    return $next($request);
                }
            }

            if ($user->id != $env->project->user_id) {
                abort(401);
            }
        }


        return $next($request);
	}

}
