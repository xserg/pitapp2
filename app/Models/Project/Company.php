<?php namespace App\Models\Project;

use Illuminate\Database\Eloquent\Model;
use App\Models\UserManagement\User;

class Company extends Model {

    protected $table = 'companies';

    protected $guarded = ['id'];

    protected $appends = [
        'analyses_intervals',
        'totalAnalysisUsage',
        'totalAnalysisTargetsUsage'
    ];

    const ANALYSES_INTERVAL_ONE_TIME = null;
    const ANALYSES_INTERVAL_WEEK = 'week';
    const ANALYSES_INTERVAL_MONTH = 'month';
    const ANALYSES_INTERVAL_YEAR = 'year';

    //These describe the table relations
    public function users() {
        return $this->hasMany(User::class);
    }

    /**
     * @return array
     */
    public function getAnalysesIntervalsAttribute()
    {
        return [
            'One Time' => self::ANALYSES_INTERVAL_ONE_TIME,
            'Week' => self::ANALYSES_INTERVAL_WEEK,
            'Month' => self::ANALYSES_INTERVAL_MONTH,
            'Year' => self::ANALYSES_INTERVAL_YEAR,
        ];
    }

    /**
     * @return int
     */
    public function getTotalAnalysisUsageAttribute(): int
    {
        return $this->users->sum('analysis_counter');
    }

    /**
     * @return int
     */
    public function getTotalAnalysisTargetsUsageAttribute(): int
    {
        return $this->users->sum('analysis_target_counter');
    }
}
