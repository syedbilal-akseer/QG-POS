<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\RoleEnum;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'off_days',
        'department_id',
        'role_id',
        'reporting_to'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'off_days' => 'json',
        ];
    }

    public function setOffDaysAttribute($value)
    {
        $this->attributes['off_days'] = json_encode($value);
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'user_id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'reporting_to');
    }

    public function subordinates()
    {
        return $this->hasMany(User::class, 'reporting_to');
    }

    public function isAdmin(): bool
    {
        return $this->role->name === 'admin';
    }

    public function isSupplyChain(): bool
    {
        return $this->role->name === 'supply-chain';
    }

    public function isSalesPerson(): bool
    {
        return $this->role->name === 'user';
    }

    public function isHOD(): bool
    {
        return $this->role->name === 'hod';
    }

    public function isManager(): bool
    {
        return $this->role->name === 'line-manager';
    }
}
