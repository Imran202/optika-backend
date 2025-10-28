<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;

class User extends Model
{
    use HasFactory, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
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
        'notification_settings',
        'push_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dt' => 'datetime',
            'points' => 'integer',
            'notification_settings' => 'array',
        ];
    }

    /**
     * Get the user's name.
     */
    public function getNameAttribute()
    {
        return $this->username;
    }

    /**
     * Set the user's name.
     */
    public function setNameAttribute($value)
    {
        $this->username = $value;
    }

    /**
     * Get the user's email.
     */
    public function getEmailAttribute()
    {
        return $this->useremail;
    }

    /**
     * Set the user's email.
     */
    public function setEmailAttribute($value)
    {
        $this->useremail = $value;
    }

    /**
     * Get the user's phone number.
     */
    public function getPhoneAttribute()
    {
        return $this->userphone;
    }

    /**
     * Set the user's phone number.
     */
    public function setPhoneAttribute($value)
    {
        $this->userphone = $value;
    }

    /**
     * Scope to find users by phone number.
     */
    public function scopeByPhoneNumber($query, $phoneNumber)
    {
        return $query->where('userphone', $phoneNumber);
    }

    /**
     * Get the user's notifications.
     */
    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    /**
     * Generate a unique RFID number that doesn't exist in the database.
     */
    public static function generateUniqueRfid()
    {
        do {
            $rfid = rand(10000000, 99999999);
        } while (self::where('rfid', $rfid)->exists());

        return $rfid;
    }

    /**
     * Check if user has a valid phone number.
     */
    public function hasValidPhoneNumber()
    {
        return !empty($this->userphone) && $this->userphone !== '/';
    }
}
