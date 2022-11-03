<?php
/**
 * @author *
 */
namespace App\Http\Controllers\Api\Admin;

use App\Services\Revenue;
use Illuminate\Support\Facades\Request;

class RevenueReportController extends \App\Http\Controllers\Controller
{
    public function __invoke()
    {
        $filters = [];

        if (intval(Request::get('provider_id'))) {
            $filters[Revenue::FILTER_PROVIDER_ID] = intval(Request::get('provider_id'));
        }

        if (Request::get('manufacturer_id')) {
            if (Request::get('manufacturer_id') == 'Azure' || Request::get('manufacturer_id') == 'Google' || Request::get('manufacturer_id') == 'IBMPVS' || Request::get('manufacturer_id') == 'AWS') {
                $filters[Revenue::FILTER_CLOUD_PROVIDER] = Request::get('manufacturer_id');
            } else {
                $filters[Revenue::FILTER_MANUFACTURER_ID] = Request::get('manufacturer_id');
            }
        }

        if (Request::get('updated_at')) {
            $start = Request::get('updated_at')['start'] ?? '';
            $end = Request::get('updated_at')['end'] ?? '';
            if (!strtotime($start)) {
                $start = '';
            }
            if (!strtotime($end)) {
                $end = '';
            }
            if ($start || $end) {
                $filters[Revenue::FILTER_MODIFIED_DATE] = [
                    'start' => $start,
                    'end' => $end
                ];
            }
        }

        /** @var Revenue $revenueService */
        $revenueService = resolve(Revenue::class);
        $revenueService->generateFormattedReport($filters);
    }
}