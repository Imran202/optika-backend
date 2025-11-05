<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BonusConfig extends Model
{
    protected $table = 'bonus_config';
    
    protected $fillable = [
        'enabled',
        'bonus_points',
        'bonus_title',
        'bonus_message'
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'bonus_points' => 'integer'
    ];

    public static function getConfig()
    {
        $config = self::first();
        if (!$config) {
            $config = self::create([
                'enabled' => true,
                'bonus_points' => 200,
                'bonus_title' => 'Dobrodošli!',
                'bonus_message' => 'Dobili ste 20 KM kao dobrodošlicu!'
            ]);
        }
        return $config;
    }
}
