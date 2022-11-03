<?php
/**
 *
 */

namespace App\Services;


use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Lang;

class LanguageTranslations
{
    /**
     * @var array
     */
    protected $_translations = [];

    /**
     * @param string $language
     * @return mixed
     */
    public function getTranslations(string $language = 'en')
    {
        if (!isset($this->_translations[$language])) {
            App::setLocale($language);
            $this->_translations[$language] = [];
            foreach($this->getTranslationKeys($language) as $key) {
                $this->_translations[$language][] = Lang::get($key);
            }
        }

        return $this->_translations[$language];
    }

    /**
     * @param $language
     * @return array
     */
    public function getTranslationKeys($language)
    {
        $filePath = resource_path('lang/' . $language);
        $files = @scandir($filePath);

        if (!$files) {
            // If no translation files for given language, just return empty array
            return [];
        }

        $translationKeys = [];

        foreach ($files as $file) {
            if (strstr($file, '.php')) {
                $translationKeys[] = str_replace('.php', '', basename($file));
            }
        }

        return $translationKeys;
    }
}