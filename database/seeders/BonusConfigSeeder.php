<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\BonusConfig;

class BonusConfigSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        BonusConfig::create([
            'enabled' => true,
            'bonus_points' => 200,
            'bonus_title' => 'Dobrodošli!',
            'bonus_message' => 'Dobili ste 200 poena kao dobrodošlicu!'
        ]);
    }
}
