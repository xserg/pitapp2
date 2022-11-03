<?php
namespace App\Http\Controllers\Api\Admin;

use App\Services\CsvImportService;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Storage;

class CsvImportController extends \App\Http\Controllers\Controller
{
    const FILENAME_OPTIMAL_TARGET = 'optimal-targets.csv';

    /**
     * Upload optimal target csv to the appropriate directory
     *
     * @return \Illuminate\Http\Response
     */
    public function uploadOptimalTarget()
    {
        $redirectUrl = '/pricing/import-optimal-target';
        $redirectUrlQuery = '?status=ok';

        if (!Request::hasFile('import_file') || !Request::file('import_file')->isValid()) {
            $redirectUrlQuery = '?status=error&message='
                . urlencode('There was an error processing your file');

            return $this->relativeRedirect($redirectUrl . $redirectUrlQuery);
        }

        $file = Request::file('import_file');

        if ($file->getClientOriginalExtension() != 'csv') {
            $redirectUrlQuery = '?status=error&message='
                . urlencode('File must be a csv');

            return $this->relativeRedirect($redirectUrl . $redirectUrlQuery);
        }
        
        $file->move(
            Storage::path(CsvImportService::IMPORT_DIR),
            self::FILENAME_OPTIMAL_TARGET
        );
        
        return $this->relativeRedirect($redirectUrl . $redirectUrlQuery);
    }
}
