<?php
namespace App\Http\Controllers\Api\Project;

use App\Exceptions\ConsolidationException;
use App\Http\Controllers\Controller;
use App\Services\Consolidation;
use App\Services\Revenue;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Project\Project;
use App\Models\Project\Environment;
use App\Models\Project\Log;
use App\Models\Hardware\Processor;
use App\Models\Hardware\AmazonServer;
use App\Models\Hardware\SoftwareCost;
use SVGGraph;
use JangoBrick\SVG\SVGImage;
use PhpOffice\PhpWord\PhpWord;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Services\Output\ExcelOutput;

class TargetAnalysisController extends Controller
{
    /**
     * @var string
     */
    protected $activity = 'Analysis';

    /**
     * Run a consolidation target analysis between 2 environments based on their IDs
     * @param $existingId
     * @param $targetId
     * @return \Illuminate\Http\JsonResponse|string
     */
    public function analysis($existingId, $targetId)
    {
        /** @var Consolidation $consolidationService */
        $consolidationService = resolve(Consolidation::class);

        try {
            $consolidation = $consolidationService->consolidate($existingId, $targetId);
        } catch (ConsolidationException $e) {
            return response()->json($e->getData(), 400);
        } catch (\Throwable $e) {
            $exMsg = "Encountered Exception Generating Analysis: " . $e->getMessage();
            if (env('APP_ENV') != 'production') {
                $exMsg .= "\n" . $e->getTraceAsString();
            }

            return response()->json(['message' => $exMsg], 400);
        }

        return $consolidation;
    }
}
