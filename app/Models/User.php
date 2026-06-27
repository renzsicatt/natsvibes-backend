<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'phone', 'date_of_birth', 'role', 'status', 'password', 'last_login_at', 'suspended_until', 'banned_at', 'deletion_requested_at'])]
#[Hidden(['password', 'remember_token', 'admin_mfa_secret'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'date_of_birth' => 'date',
            'last_login_at' => 'datetime',
            'suspended_until' => 'datetime',
            'banned_at' => 'datetime',
            'deletion_requested_at' => 'datetime',
            'admin_mfa_secret' => 'encrypted',
            'admin_mfa_confirmed_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the profile associated with the user.
     */
    public function profile()
    {
        return $this->hasOne(Profile::class);
    }

    public function trustedContacts()
    {
        return $this->hasMany(TrustedContact::class);
    }

    public function deviceTokens()
    {
        return $this->hasMany(DeviceToken::class);
    }

    public function notificationPreference()
    {
        return $this->hasOne(NotificationPreference::class);
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, ['admin', 'super_admin'], true);
    }

    public function canHost(): bool
    {
        return in_array($this->role, ['host', 'admin', 'super_admin'], true)
            && $this->status === 'active'
            && $this->profile?->host_verification_status === 'approved';
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->status === 'active' && $this->isAdmin();
    }
}
