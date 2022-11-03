<?php namespace App\Models\Project;

use Illuminate\Database\Eloquent\Model;
use App\Models\Project\Currency;
use App\Models\Project\Provider;


class Region extends Model{

    protected $table = 'regions';

    protected $guarded = ['id'];

    protected $attributes = [
        /** @var array PaymentOption names available for this region */
        'available_payment_options' => '[]',
    ];
    
    protected $casts = [
        'available_payment_options' => 'array',
    ];
    
    //These describe the table relations
    public function currencies() {
        return $this->belongsToMany(Currency::class, 'region_currency');
    }
    
    public function providers() {
        return $this->belongsToMany(Provider::class);
    }
}
