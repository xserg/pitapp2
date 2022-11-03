<?php namespace App\Models\Language;


/**
 * Description of LanguageText
 *
 * @author bjones
 * @property int $id
 * @property int $language_id
 * @property string $language_key
 * @property string $content
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property string|null $deleted_at
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Language\LanguageText whereContent($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Language\LanguageText whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Language\LanguageText whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Language\LanguageText whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Language\LanguageText whereLanguageId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Language\LanguageText whereLanguageKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Language\LanguageText whereUpdatedAt($value)
 * @mixin \Eloquent
 */

use Illuminate\Database\Eloquent\Model;
use App\Models\Language\LanguageModel;
use App\Models\Language\LanguageKey;
use App\Models\Language\Language;

class LanguageText extends LanguageModel{
    protected $fillable = ['language_id', 'language_key', 'content'];
    
    /**
     * The database table that the model is using.
     * @var type 
     */    
    protected function languageKey() {
        return $this->hasOne(LanguageKey::class, 'language_key');
    }
    
    protected function language() {
        return $this->hasOne(Language::class, 'language_id');
    }
    
    public function logName() {
        return $this->language_id . " - " . $this->language_key;
    }
}
