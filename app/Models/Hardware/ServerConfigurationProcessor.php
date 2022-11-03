<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace App\Models\Hardware;
use Illuminate\Database\Eloquent\Model;
use App\Models\Hardware\{Processor, Server};

/**
 * Description of ServerProcessor
 *
 * @author rvalenziano
 */
class ServerConfigurationProcessor extends Model{

    protected $table = 'server_configuration_processors';
    protected $guarded = ['id'];

    //These describe the table relations
    public function processor() {
        return $this->belongsTo(Processor::class, 'processor_id');
    }

    public function server() {
       return $this->belongsTo(Server::class, 'server_id');
    }
}
