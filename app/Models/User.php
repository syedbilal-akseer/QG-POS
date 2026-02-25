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
        'reporting_to',
        'supply_chain_user_id',
        'account_user_id',
        'oracle_user_id',
        'oracle_user_name',
        'assigned_salespeople'
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
            'assigned_salespeople' => 'json',
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

    public function customerVisits()
    {
        return $this->hasMany(CustomerVisit::class, 'user_id');
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

    public function supplyChainUser()
    {
        return $this->belongsTo(User::class, 'supply_chain_user_id');
    }

    public function accountUser()
    {
        return $this->belongsTo(User::class, 'account_user_id');
    }


    protected function getRoleName(): ?string
    {
        // First try the string role column (newer approach)
        if (!empty($this->attributes['role'])) {
            return $this->attributes['role'];
        }
        
        // Fall back to role relationship if role_id exists
        if ($this->role_id && $this->relationLoaded('role') && $this->getRelation('role')) {
            return $this->getRelation('role')->name;
        }
        
        // Try to load role relationship if role_id exists but not loaded
        if ($this->role_id) {
            try {
                $roleModel = $this->role()->first();
                return $roleModel?->name;
            } catch (\Exception $e) {
                return null;
            }
        }
        
        return null;
    }

    public function isAdmin(): bool
    {
        $roleName = $this->getRoleName();
        return $roleName === 'admin';
    }

    public function isSupplyChain(): bool
    {
        $roleName = $this->getRoleName();
        return $roleName === 'supply-chain';
    }

    public function isSalesPerson(): bool
    {
        $roleName = $this->getRoleName();
        return $roleName === 'user';
    }

    public function isHOD(): bool
    {
        $roleName = $this->getRoleName();
        return $roleName === 'hod';
    }

    public function isManager(): bool
    {
        $roleName = $this->getRoleName();
        return $roleName === 'line-manager';
    }

    public function isAccountUser(): bool
    {
        $roleName = $this->getRoleName();
        return $roleName === 'account-user';
    }

    public function isSalesHead(): bool
    {
        $roleName = $this->getRoleName();
        return $roleName === 'sales-head';
    }

    public function isPriceUploads(): bool
    {
        $roleName = $this->getRoleName();
        return $roleName === 'price-uploads';
    }

    public function isCmdKhi(): bool
    {
        $roleName = $this->getRoleName();
        return $roleName === 'cmd-khi';
    }

    public function isCmdLhr(): bool
    {
        $roleName = $this->getRoleName();
        return $roleName === 'cmd-lhr';
    }

    public function isScmLhr(): bool
    {
        $roleName = $this->getRoleName();
        return $roleName === 'scm-lhr';
    }

    public function canAccessReceipts(): bool
    {
        return $this->isAdmin() || $this->isCmdKhi() || $this->isCmdLhr();
    }

    /**
     * Get allowed OU IDs for receipts based on CMD role.
     */
    public function getAllowedReceiptOuIds(): array
    {
        if ($this->isAdmin()) {
            return [102, 103, 104, 105, 106, 108, 109]; // All OU IDs
        }

        if ($this->isCmdKhi()) {
            return [102, 103, 104, 105, 106]; // KHI organization OU IDs
        }

        if ($this->isCmdLhr()) {
            return [108, 109]; // LHR organization OU IDs
        }

        // Get OU IDs from Oracle organizations for other roles
        return $this->getOracleOrganizations();
    }

    /**
     * Get allowed OU IDs based on user's Oracle organizations
     */
    public function getAllowedOuIds(): array
    {
        if ($this->isAdmin()) {
            return [102, 103, 104, 105, 106, 108, 109]; // All OU IDs
        }

        // SCM-LHR role gets Lahore organization access
        if ($this->isScmLhr()) {
            return [108, 109]; // LHR organization OU IDs
        }

        // Get OU IDs from Oracle organizations
        return $this->getOracleOrganizations();
    }

    /**
     * Check if user is from LHR location (based on Oracle organizations)
     */
    public function isLHRUser(): bool
    {
        if ($this->isAdmin()) {
            return false; // Admin doesn't belong to specific location
        }
        
        $userOrgs = $this->getOracleOrganizations();
        $lhrOrgs = [108, 109]; // LHR organization OU IDs
        
        return !empty(array_intersect($userOrgs, $lhrOrgs));
    }

    /**
     * Check if user is from KHI location (based on Oracle organizations)  
     */
    public function isKHIUser(): bool
    {
        if ($this->isAdmin()) {
            return false; // Admin doesn't belong to specific location
        }
        
        $userOrgs = $this->getOracleOrganizations();
        $khiOrgs = [102, 103, 104, 105, 106]; // KHI organization OU IDs
        
        return !empty(array_intersect($userOrgs, $khiOrgs));
    }

    /**
     * Check if user can view orders based on Oracle organizations
     */
    public function canViewOrdersFromLocation(): bool
    {
        return $this->isAdmin() || $this->isSupplyChain() || !empty($this->getOracleOrganizations());
    }

    /**
     * Get the user's Oracle organization relationships.
     */
    public function userOrganizations()
    {
        return $this->hasMany(UserOrganization::class);
    }

    /**
     * Get active Oracle organizations for this user.
     */
    public function activeOrganizations()
    {
        return $this->userOrganizations()->active();
    }

    /**
     * Get Oracle OU_IDs that this user has access to.
     */
    public function getOracleOrganizations(): array
    {
        if ($this->isAdmin()) {
            return []; // Empty array means access to all organizations
        }

        return $this->activeOrganizations()
            ->whereNotNull('oracle_ou_id')
            ->pluck('oracle_ou_id')
            ->toArray();
    }

    /**
     * Check if user has access to a specific Oracle organization by OU_ID.
     */
    public function hasOracleAccess($ouId): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        return $this->activeOrganizations()
            ->where('oracle_ou_id', $ouId)
            ->exists();
    }

    /**
     * Check if this user is mapped to Oracle.
     */
    public function isOracleMapped(): bool
    {
        return !empty($this->oracle_user_id);
    }

    /**
     * Get the user's Oracle display name.
     */
    public function getOracleDisplayNameAttribute(): string
    {
        if ($this->oracle_user_name) {
            return "{$this->name} (Oracle: {$this->oracle_user_name})";
        }
        
        return $this->name;
    }

    /**
     * Sync this user's organizations from Oracle.
     */
    public function syncOracleOrganizations(array $organizationData): void
    {
        if (!$this->isOracleMapped()) {
            return;
        }

        // Mark all existing as inactive first
        $this->userOrganizations()->update(['is_active' => false]);

        // Add/update organizations from Oracle data
        foreach ($organizationData as $orgData) {
            UserOrganization::updateOrCreate(
                [
                    'user_id' => $this->id,
                    'oracle_organization_code' => $orgData['code'],
                ],
                [
                    'oracle_organization_name' => $orgData['name'] ?? null,
                    'is_active' => true,
                ]
            );
        }
    }

    /**
     * Get assigned salesperson IDs for CMD users.
     * Returns null if "All" salespeople are assigned (no filtering needed).
     * Returns array of IDs if specific salespeople are assigned.
     */
    public function getAssignedSalespeopleIds(): ?array
    {
        if (!$this->isCmdKhi() && !$this->isCmdLhr()) {
            return null;
        }

        // If assigned_salespeople is null or empty, it means "All"
        if (empty($this->assigned_salespeople)) {
            return null;
        }

        return $this->assigned_salespeople;
    }

    /**
     * Check if this CMD user has "All" salespeople access.
     */
    public function hasAllSalespeopleAccess(): bool
    {
        if (!$this->isCmdKhi() && !$this->isCmdLhr()) {
            return false;
        }

        return empty($this->assigned_salespeople);
    }
}
