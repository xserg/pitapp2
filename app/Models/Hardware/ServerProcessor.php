<?php
namespace App\Models\Hardware;

use Illuminate\Database\Eloquent\Model;
use App\Models\Hardware\{Processor, Server};

class ServerProcessor extends Model {
    protected $table = 'server_processors';
    protected $guarded = ['id'];


    public function processors() {
        return $this->hasMany(Processor::class);
    }

    public function servers() {
        return $this->hasMany(Server::class);
    }

}
