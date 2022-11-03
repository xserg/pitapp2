<?php namespace App\Models\Hardware;

use Illuminate\Database\Eloquent\Model;
use App\Models\Hardware\Server;

/**
 * Class Manufacturer
 * @package App\Models\Hardware
 * @property string $name
 * @property array $processor_models
 * @property  int $id
 */
class Manufacturer extends Model{

    protected $table = 'manufacturers';
    protected $guarded = ['id'];

    protected $attributes = [
        /** @var array A list of manufacturer's available processor models */
        'processor_models' => '[]',
    ];
    
    protected $casts = [
        'processor_models' => 'array',
    ];

    //One manufacturer can have many servers
    public function servers() {
        return $this->hasMany(Server::class);
    }
}
