<?php namespace App\Models\Project;

use App\Models\Hardware\Manufacturer;
use Illuminate\Database\Eloquent\Model;

/**
 * Class RevenueReport
 * @package App\Models\Project
 * @property int $company_id
 * @property int $project_id
 * @property int $user_id
 * @property string $customer
 * @property int $environment_id
 */
class RevenueReport extends Model{

    protected $table = 'revenue_report';
    protected $guarded = ['id'];

    //These describe the table relations
    public function company() {
        return $this->belongsTo(Company::class);
    }

    public function manufacturer() {
        return $this->belongsTo(Manufacturer::class);
    }

    public function project() {
        return $this->belongsTo(Project::class);
    }

    public function environment() {
        return $this->belongsTo(Environment::class);
    }
}
