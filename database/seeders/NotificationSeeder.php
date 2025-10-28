<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Notification;
use App\Models\User;

class NotificationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Kreiraj test notifikacije za sve korisnike
        $users = User::all();
        
        foreach ($users as $user) {
            // Kreiraj 3 test notifikacije za svakog korisnika
            $notifications = [
                [
                    'user_id' => $user->id,
                    'type' => 'appointment',
                    'title' => 'Podsjetnik za termin',
                    'message' => 'Imate zakazan termin sutra u 14:00',
                    'read' => false,
                    'icon' => 'calendar-outline',
                    'color' => '#4ECDC4',
                    'created_at' => now()->subHours(2),
                ],
                [
                    'user_id' => $user->id,
                        'type' => 'loyalty',
                    'title' => 'Novi bodovi dodani',
                    'message' => 'Dodano vam je 50 bodova za vašu posljednju kupovinu',
                    'read' => false,
                    'icon' => 'star-outline',
                    'color' => '#FFD93D',
                    'created_at' => now()->subHours(6),
                ],
                [
                    'user_id' => $user->id,
                    'type' => 'promo',
                    'title' => 'Specijalna ponuda',
                    'message' => '15% popusta na sve okvire ovog vikenda',
                    'read' => false,
                    'icon' => 'gift-outline',
                    'color' => '#FF6B6B',
                    'created_at' => now()->subHours(12),
                ]
            ];

            foreach ($notifications as $notificationData) {
                Notification::create($notificationData);
            }
        }
    }
}
