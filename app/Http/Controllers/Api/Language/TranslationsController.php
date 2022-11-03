<?php
/**
 *
 */

namespace App\Http\Controllers\Api\Language;


use App\Services\LanguageTranslations;
use Carbon\Carbon;

class TranslationsController extends \App\Http\Controllers\Controller
{
    /**
     * @param $language
     * @param LanguageTranslations $languageTranslationsService
     * @return $this
     */
    public function __invoke($language, LanguageTranslations $languageTranslationsService)
    {
        return $this->makeResponsePublic(
            response()->json($languageTranslationsService->getTranslations($language ?: 'en'))
        );
    }
}