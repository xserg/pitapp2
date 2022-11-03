<?php

namespace App\Http\Controllers\Api\Language;

use App\Models\Language\Language;
use App\Models\Language\LanguageKey;
use App\Models\Language\LanguageText;
use App\Console\Commands\Queued\UpdateLanguages;
use App\Http\Controllers\Api\StandardModule\SmartController;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Event;

/**
 * Description of LanguageKeyController
 *
 * @author bjones
 */
class LanguageKeyController extends SmartController {
    
    protected $activity = 'Language Management';

    /**
     * Returns all languages current in the database.
     * @return type
     */    
    protected function _index() {
        $language_key = LanguageKey::all();
        return response()->json($language_key->toArray());
    }

    protected function _destroy($id) {
        
    }

    protected function _show($id) {
        
    }
    
    /**
     * Saves a new key in the database and fires a language updated event.
     */
    protected function _store() {
        
        /* Save a new language key. */
        $language_key = new LanguageKey();
        $language_key->key = Request::input('key');
        $language_key->component_id = Request::input('component_id');
        $language_key->save();
        
        $value = new LanguageText();
        $value->language_id = Language::where('abbreviation', '=', 'en')->first()->id;
        $value->language_key = $language_key->key;
        $value->content = Request::input('english');
        $value->save();
        
        /* Fire an event signaling that the database has been updated. */
        $this->dispatch(new UpdateLanguages());
    }

    protected function _update($id) {
        
    }

}
