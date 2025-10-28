<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $primaryKey = 'transcation_id';
    public $timestamps = false;
    
    protected $fillable = [
        'rfid',
        'user',
        'poslovnica',
        'points',
        'action',
        'vrsta'
    ];

    protected $casts = [
        'points' => 'integer',
        'rfid' => 'integer'
    ];
}
