<?php namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\Api\Project\EmailController;

class CompanyEmailsTest extends Command {


	protected $signature = 'emailtest:companies';
	protected $name = 'emailtest:companies';
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
	    echo "starting\n";
			$ec = new EmailController;
			$ec->createCompanyEmailsTest();
	}

}
