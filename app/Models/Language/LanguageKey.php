<?php namespace App\Models\Language;

use Illuminate\Database\Eloquent\Model;
use App\Models\Language\LanguageModel;
use App\Models\Language\LanguageText;

/**
 * Description of LanguageKey
 *
 * @author bjones
 */
class LanguageKey extends LanguageModel {
    
    protected $fillable = ['key', 'component_id'];
    public function languageText() {
        $this->belongsTo(LanguageText::class);
    }
    
    public function componentId() {
        $this->belongsTo(Component::class);
    }
    
    public function logName() {
        return $this->key;
    }
}
