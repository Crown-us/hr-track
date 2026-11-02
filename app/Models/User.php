<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    public const ROLES = ['admin' => 'Admin', 'hr_staff' => 'Staf HR', 'viewer' => 'Pembaca', 'employee' => 'Pegawai', 'supervisor' => 'Atasan'];

    protected $attributes = ['is_active' => true, 'version' => 1, 'auth_version' => 1];

    public function employee()
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }

    public function home(): string
    {
        return in_array($this->role, ['employee', 'supervisor'], true) ? '/me' : '/hr';
    }

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean', 'version' => 'integer', 'auth_version' => 'integer',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
