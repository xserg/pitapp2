<?php

namespace App\Http\Controllers\Api\Language;

/**
 * Description of ComponentLanguageSeederController
 *
 * @author bjones
 */
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Bus;
use App\Http\Controllers\Controller;
use App\Console\Commands\Queued\UpdateLanguages;
use App\Console\Commands\Queued\Publish;
use App\Models\Language\Language;
use App\Models\Language\LanguageText;

class LanguageController extends Controller {

    protected $activity = 'Language Management';

    /**
     * Returns all languages current in the database.
     * @return type
     */
    public function index() {
        $language = Language::all();
        return response()->json($language);
    }

    /**
     * Stores a language, language key, and language text based on the input
     * from the request.
     */
    public function store() {

        $english_texts = Request::input('english_texts');
        $languages = Request::input('languages');
        $target_lang_texts = Request::input('target_lang_texts');

        foreach ($english_texts as $lang_text) {
            $this->store_language_text($lang_text);
        }

        foreach ($target_lang_texts as $lang_text) {
            $this->store_language_text($lang_text);
        }

        foreach ($languages as $language) {
            $this->store_language($language);
        }

        $this->dispatch(new UpdateLanguages());
    }

    /**
     * Stores a new language if the database doesn't already contain the
     * language.
     * @param type $language
     */
    private function store_language($language) {
        Language::updateOrCreate(array(
            'name' => $language['name']
        ), array(
            'abbreviation' => $language['abbreviation']
        ));
    }

    /**
     * Stores a new language text if the database doesn't already contain the
     * language text.  Updates the language text if it already exists in the
     * database.
     * @param type $lang_text
     */
    private function store_language_text($lang_text) {

        //die();
        try {
            LanguageText::updateOrCreate(
                array(
                    'language_id' => $lang_text['language_id'],
                    'language_key' => $lang_text['language_key']
                ), array(
                    'content' => $lang_text['content']
                )
            );
        } catch (Exception $exc) {
            print_r("hello");
            print_r($exc->getMessage());
        }
    }

    public function getKeys($id) {
        //$keys = DB::table('language_texts')->where('language_id', '=', $id)->get();

        $keys = DB::table('language_texts')
                ->join('languages', 'languages.id', '=', 'language_texts.language_id')
                ->where('languages.abbreviation', '=', $id)
                ->select('language_texts.*')
                ->get();
        $newKeys = array();
        foreach ($keys as $key) {
            $newKeys[$key->language_key] = $key->content;
        }
        return response()->json($newKeys, 200);
    }

    public function getKeysWithEnglish($id) {
        $query = json_decode(Request::input('query'));

        $keys = DB::table('language_keys as lk')
                ->leftJoin(DB::raw("(select s.* from language_texts s join languages l on l.id = s.language_id where l.abbreviation = "
                                  .  DB::connection()->getPdo()->quote($id) .") as selected"),'selected.language_key' ,'=', 'lk.key')
                ->join("language_texts as english", "english.language_key", "=", "lk.key")
                ->where("english.language_id", "=", 1)
                ->select("selected.*", "english.content as englishTranslation", "lk.key as languageKey", 'lk.component_id');

        if(isset($query->languageKey)) {
            $keys = $keys->where("lk.key", "LIKE", '%' . $query->languageKey . '%');
        }
        if(isset($query->component_id)) {
            $keys = $keys->where("lk.component_id", "=", $query->component_id);
        }

        $keys = $keys->get();

        return response()->json($keys, 200);
    }

    public function publish() {
        $this->dispatch(new UpdateLanguages());
        $this->dispatch(new Publish());
    }

    public function destroy($id) {

    }

    public function show($id) {

    }

    public function update($id) {

    }

}
