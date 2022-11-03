<?php

namespace App\Models\Project\Environment;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Analysis
 * @package App\Models\Project\Environment
 * @property string $target_analysis
 * @property int $sequence
 * @property int $environment_id
 */
class Analysis extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'environment_analyses';

    /**
     * @var array
     */
    protected $fillable = ['target_analysis', 'sequence', 'environment_id'];

    /**
     * @var bool
     */
    public $timestamps = false;
}
