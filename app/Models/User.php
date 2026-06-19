<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'role_type',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
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
        ];
    }

    /**
     * Check if user is admin
     */
    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    /**
     * Check if user is intern (Mahasiswa)
     */
    public function isIntern(): bool
    {
        return $this->hasRole('intern');
    }

    /**
     * Get user role display name
     */
    public function getRoleDisplayName(): string
    {
        if ($this->isAdmin()) return 'Administrator';
        if ($this->isIntern()) return 'Mahasiswa Magang';
        return 'User';
    }

    /**
     * Get dashboard route based on role
     */
    public function getDashboardRoute(): string
    {
        if ($this->isAdmin()) return 'admin.dashboard';
        if ($this->isIntern()) return 'intern.dashboard';
        return 'admin.dashboard';
    }

    /**
     * Get archive route based on role
     */
    public function getArchiveRoute(string $type = 'index'): string
    {
        if ($this->isAdmin()) return "admin.archives.{$type}";
        if ($this->isIntern()) return "intern.archives.{$type}";
        return "admin.archives.{$type}";
    }

    /**
     * Get search route based on role
     */
    public function getSearchRoute(): string
    {
        if ($this->isAdmin()) return 'admin.search.index';
        if ($this->isIntern()) return 'intern.search.index';
        return 'admin.search.index';
    }

    /**
     * Get bulk operations route based on role
     */
    public function getBulkRoute(): string
    {
        if ($this->isAdmin()) return 'admin.bulk.index';
        if ($this->isIntern()) return 'intern.bulk.index';
        return 'admin.bulk.index';
    }

    /**
     * Get export route based on role
     */
    public function getExportRoute(): string
    {
        if ($this->isAdmin()) return 'admin.export.index';
        if ($this->isIntern()) return 'intern.export.index';
        return 'admin.export.index';
    }

    /**
     * Get generate labels route based on role
     */
    public function getGenerateLabelsRoute(): string
    {
        if ($this->isAdmin()) return 'admin.generate-labels.index';
        if ($this->isIntern()) return 'intern.generate-labels.index';
        return 'admin.generate-labels.index';
    }

    /**
     * Get reports route based on role
     */
    public function getReportsRoute(): string
    {
        if ($this->isAdmin()) return 'admin.reports.retention-dashboard';
        if ($this->isIntern()) return 'intern.reports.retention-dashboard';
        return 'admin.reports.retention-dashboard';
    }

    /**
     * Get archives created by this user
     */
    public function archives(): HasMany
    {
        return $this->hasMany(Archive::class, 'created_by');
    }
}
