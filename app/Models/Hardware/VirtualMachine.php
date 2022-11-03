<?php
namespace App\Models\Hardware;

use Illuminate\Database\Eloquent\Model;

class VirtualMachine extends Model {
    protected $table = 'hardware_virtual_machines';
    protected $guarded = ['id'];

    public function servers() {
        return $this->belongsToMany(Server::class, 'server_hardware_virtual_machines');
    }
}
