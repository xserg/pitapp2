<?php
namespace App\Models\Hardware;

use Illuminate\Database\Eloquent\Model;

/**
 * An OptimalTarget processor
 * 
 * @package App\Models\Hardware
 * 
 * @property Processor $processor
 * @property int $processor_model
 * @property int $ram
 * @property int $total_server_cost
 */
class OptimalTarget extends Model {
    protected $table = 'optimal_targets';
    protected $fillable = [
        'processor_id',
        'processor_model',
        'ram',
        'total_server_cost',
    ];

    public function processor() {
        return $this->belongsTo(Processor::class);
    }

    public function processor_type() {
        return $this->processor->name;
    }

    public function ghz() {
        return $this->processor->ghz;
    }

    public function num_of_processors() {
        return $this->processor->socket_qty;
    }

    public function cores_per_processor() {
        return $this->processor->core_qty;
    }

    public function total_cores() {
        return $this->num_of_processors() * $this->cores_per_processor();
    }

    public function cpm_value() {
        return $this->processor->rpm;
    }

}
