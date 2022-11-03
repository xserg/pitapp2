<?php namespace App\Models\Hardware;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use App\Models\Hardware\{Manufacturer, VirtualMachine, Processor};

/**
 * Class Server
 * @package App\Models\Hardware
 *
 * @property Collection $processors
 * @property Manufacturer $manufacturer
 * @property int $manufacturer_id
 * @property string $name
 */
class Server extends Model{

    protected $table = 'servers';
    protected $guarded = ['id'];

    //These describe the table relations
    public function manufacturer() {
        return $this->belongsTo(Manufacturer::class, 'manufacturer_id');
    }

    public function virtualMachines() {
        return $this->belongsTo(VirtualMachine::class, 'server_hardware_virtual_machines');
    }

    public function processors() {
        return $this->belongsToMany(Processor::class, 'server_processors');
    }
}
