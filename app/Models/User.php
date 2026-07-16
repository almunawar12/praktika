<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RoleName;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

/**
 * Datetime attributes resolve to CarbonImmutable because AppServiceProvider calls
 * `Date::use(CarbonImmutable::class)`.
 *
 * @property string $id
 * @property string $name
 * @property string $email
 * @property CarbonImmutable|null $email_verified_at
 * @property string $password
 * @property string|null $phone
 * @property string|null $avatar_path
 * @property bool $is_active
 * @property CarbonImmutable|null $last_login_at
 * @property string|null $last_login_ip
 * @property string|null $remember_token
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read Collection<int, Role> $roles
 */
#[Fillable(['name', 'email', 'password', 'phone', 'avatar_path', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasRoles;
    use HasUuids;
    use LogsActivity;
    use Notifiable;
    use SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Audit trail configuration.
     *
     * `password` and `remember_token` are deliberately absent — the activity log
     * is readable by admins, so secrets must never reach it.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('user')
            ->logOnly(['name', 'email', 'phone', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * @return HasOne<Student, $this>
     */
    public function student(): HasOne
    {
        return $this->hasOne(Student::class);
    }

    /**
     * @return HasOne<Teacher, $this>
     */
    public function teacher(): HasOne
    {
        return $this->hasOne(Teacher::class);
    }

    /**
     * The single role assigned to this user, if any.
     *
     * Praktika assigns exactly one role per user; `roles` stays a relation
     * because Spatie models it that way.
     */
    public function roleName(): ?RoleName
    {
        $name = $this->roles->first()?->name;

        return $name === null ? null : RoleName::tryFrom($name);
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(RoleName::SuperAdmin->value);
    }

    public function isAdminPkl(): bool
    {
        return $this->hasRole(RoleName::AdminPkl->value);
    }

    public function isGuruPembimbing(): bool
    {
        return $this->hasRole(RoleName::GuruPembimbing->value);
    }

    public function isPembimbingIndustri(): bool
    {
        return $this->hasRole(RoleName::PembimbingIndustri->value);
    }

    public function isSiswa(): bool
    {
        return $this->hasRole(RoleName::Siswa->value);
    }
}
