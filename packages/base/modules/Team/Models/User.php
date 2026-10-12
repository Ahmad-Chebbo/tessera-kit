<?php

namespace App\Modules\Team\Models;

use App\Modules\Team\Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Contracts\Permission;
use Spatie\Permission\Traits\HasRoles;
use Tessera\Core\Contracts\PlanLimits;
use Tessera\Engine\ModuleRegistry;

#[UseFactory(UserFactory::class)]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    use HasRoles {
        hasPermissionTo as protected spatieHasPermissionTo;
        checkPermissionTo as protected spatieCheckPermissionTo;
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'company_id',
        'branch_id',
        'is_active',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /**
     * A permission of a module the company's plan leaves out is never granted, whatever the role says.
     */
    public function hasPermissionTo($permission, ?string $guardName = null): bool
    {
        return $this->moduleAllows($permission) && $this->spatieHasPermissionTo($permission, $guardName);
    }

    public function checkPermissionTo($permission, ?string $guardName = null): bool
    {
        return $this->moduleAllows($permission) && $this->spatieCheckPermissionTo($permission, $guardName);
    }

    private function moduleAllows(mixed $permission): bool
    {
        $name = $permission instanceof Permission ? $permission->name : $permission;
        $module = is_string($name) ? app(ModuleRegistry::class)->featureForPermission($name) : null;

        if ($module === null || $this->isSuperAdmin()) {
            return true;
        }

        return app(PlanLimits::class)->hasModule($this->company_id, $module);
    }

    public function employeeProfile(): HasOne
    {
        return $this->hasOne(EmployeeProfile::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Check if the user has the "super admin" role.
     */
    public function isSuperAdmin(): bool
    {
        return $this->hasRole('Admin');
    }
}
