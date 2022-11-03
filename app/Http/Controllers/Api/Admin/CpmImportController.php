<?php
namespace App\Http\Controllers\Api\Admin;

use App\Services\CpmImport;
use Illuminate\Support\Facades\Request;
use App\Console\Commands\Import\Cpm;

class CpmImportController extends \App\Http\Controllers\Controller
{
    public function __invoke(CpmImport $cpmImport)
    {
        if (Request::hasFile('import_file') && Request::file('import_file')->isValid()) {

            if (Request::file('import_file')->getClientOriginalExtension() != 'csv') {
                return $this->relativeRedirect('/processor/import?status=error&message='.urlencode('File must be a csv'));
            }
            
            $file = Request::file('import_file');
            $file->move(base_path() . Cpm::FILE_PATH, Cpm::FILE_NAME);
            
            return $this->relativeRedirect('/processor/import?status=ok');

        }

        return $this->relativeRedirect('/processor/import?status=error&message='.urlencode("There was an error processing your file"));
    }
}