<?php namespace App\Models\Language;

use Illuminate\Database\Eloquent\Model;
use App\Models\Language\{LanguageModel, LanguageText};
use App\Models\StandardModule\User;

/**
 * App\Models\Language\Language
 *
 * @property int $id
 * @property string $name
 * @property string $abbreviation
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property string|null $deleted_at
 * @property-read \App\Models\Language\LanguageText $languageText
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\StandardModule\User[] $users
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Language\Language whereAbbreviation($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Language\Language whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Language\Language whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Language\Language whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Language\Language whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Language\Language whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class Language extends LanguageModel {
    
    /**
     * The database table that the model is using.
     * @var type 
     */
    protected $fillable = ['name', 'abbreviation'];
    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];
    
    public function languageText() {
        return $this->belongsTo(LanguageText::class);
    }
    
    public function users() {
        return $this->hasMany(User::class);
    }
    
    public function logName() {
        return $this->name;
    }
}