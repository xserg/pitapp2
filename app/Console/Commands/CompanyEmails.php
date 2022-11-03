<?php namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\Api\Project\EmailController;

class CompanyEmails extends Command {


	protected $signature = 'email:companies';
	protected $name = 'email:companies';
	protected $description = 'Sends emails to companies who have access that will expire at certain intervals';

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
			$ec = new EmailController;
			$ec->createCompanyEmails();
	}

}
