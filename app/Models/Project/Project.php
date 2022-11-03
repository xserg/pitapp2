<?php namespace App\Models\Project;

use App\Services\Deployment;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use App\Models\UserManagement\User;
use App\Models\Project\Environment;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Class Project
 * @package App\Models\Project
 * @property int $support_years
 * @property string $comparison_type
 * @property Environment[] $environments
 * @property array $softwares
 * @property array $softwareByNames
 * @property array $softwareMap
 * @property int $existingCount
 * @property float $cagr
 * @property string $analysis_name
 * @property string $created_at
 * @property string $updated_at
 * @property int $last_analysis_checksum
 */
class Project extends Model
{
    const COMPARISON_TYPE_EXISTING_VS_TARGET = 0;
    const COMPARISON_TYPE_NEW_VS_NEW = 1;

    const LARGE_ANALYSIS_START = 1000;

    protected $table = 'projects';
    protected $guarded = ['id'];

    /**
     * @var array
     */
    protected $appends = ['largeAnalysis'];

    /**
     * @var \stdClass|null
     */
    protected $_bestCloudTargetSummary;

    /**
     * @var Environment|null
     */
    protected $_bestTargetEnvironment;

    //region Relationships

    //These describe the table relations
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function environments(): HasMany
    {
        return $this->hasMany(Environment::class);
    }

    //endregion

    //region Custom Attributes

    public function getLocalLogoAttribute()
    {
        return str_replace('images/', 'storage/', $this->logo);
    }

    //endregion

    /**
     * @return bool
     */
    public function isNewVsNew()
    {
        return $this->comparison_type == self::COMPARISON_TYPE_NEW_VS_NEW;
    }

    /**
     * @return Environment
     */
    public function getExistingEnvironment($withRelations = [])
    {
        /** @var Environment $environment */
        if ($this->relationLoaded("environments")) {
            return ($this->isNewVsNew() ? $this->environments : $this->environments->where('is_existing', 1))
                ->first();
        } else {
            return ($this->isNewVsNew() ? $this->environments() : $this->environments()->where('is_existing', 1))
                ->with($withRelations)
                ->first();
        }
    }

    /**
     * @return Environment
     */
    public function getTargetEnvironmentById($id)
    {
        return $this->environments->where('id', $id)->first();
    }

    /**
     * @return Environment[]
     */
    public function getTargetEnvironments()
    {
        if ($this->isNewVsNew()) {
            $environments = $this->environments->all();
            array_shift($environments);
            return $environments;
        }

        return $this->environments->where('is_existing', 0)->all();
    }

    /**
     * @param \stdClass $bestCloudTargetSummary
     * @return Project
     */
    public function setBestCloudTargetSummary(\stdClass $bestCloudTargetSummary)
    {
        $this->_bestCloudTargetSummary = $bestCloudTargetSummary;
        return $this;
    }

    /**
     * @return \stdClass|null
     */
    public function getBestCloudTargetSummary()
    {
        return $this->_bestCloudTargetSummary;
    }

    /**
     * @param Environment $bestTargetEnvironment
     * @return Project
     */
    public function setBestTargetEnvironment(Environment $bestTargetEnvironment)
    {
        $this->_bestTargetEnvironment = $bestTargetEnvironment;
        return $this;
    }

    /**
     * @return Environment|null
     */
    public function getBestTargetEnvironment()
    {
        return $this->_bestTargetEnvironment;
    }

    /**
     * @return bool
     */
    public function isStale()
    {
        if (config('analysis.skip_stale_project_check')) {
            return false;
        }
        $date = $this->updated_at ?: $this->created_at;
        if (!$date) {
            return true;
        }
        $dateTime = strtotime($date);
        /** @var Deployment $deployment */
        $deployment = resolve(Deployment::class);
        return $dateTime < strtotime($deployment->getLastDeployment());
    }

    /**
     * @return bool
     */
    public function getLargeAnalysisAttribute()
    {
        $count = $this->getExistingEnvironment()->serverConfigurations()->count();
        if ($count < self::LARGE_ANALYSIS_START) {
            return false;
        }
        if ($count < self::LARGE_ANALYSIS_START * 2) {
            return 2;
        } else if ($count < self::LARGE_ANALYSIS_START * 3) {
            return 4;
        } else {
            return 15;
        }
    }
}
