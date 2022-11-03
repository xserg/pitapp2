<?php namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\Api\Project\UserProjectController;

class ResetUsers extends Command {


	protected $signature = 'reset:users';
	protected $name = 'reset:users';
	protected $description = 'Resets the YTD query and login counts for all users';

	/**
	 * Create a new command instance.
	 *
	 * @return void
	 */
	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * Execute the command.
	 *
	 * @return void
	 */
	public function handle()
	{
			UserProjectController::resetUserYTD();
	}

}
