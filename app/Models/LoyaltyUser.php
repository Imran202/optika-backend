<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoyaltyUser extends Model
{
    use HasFactory;

    protected $table = 'users';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'username',
        'useremail', 
        'userphone',
        'dt',
        'rfid',
        'points',
        'count',
        'dioptrija',
        'dsph',
        'dcyl',
        'daxa',
        'lsph',
        'lcyl',
        'laxa',
        'ldadd',
        'bonus_status',
    ];

    protected $casts = [
        'dt' => 'datetime',
        'rfid' => 'integer',
        'points' => 'integer',
        'count' => 'integer',
        'dioptrija' => 'integer',
        'bonus_status' => 'integer',
    ];
}
