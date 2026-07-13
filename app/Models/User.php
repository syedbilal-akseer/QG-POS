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
        'additional_roles',
        'off_days',
        'department_id',
        'role_id',
        'reporting_to',
        'supply_chain_user_id',
        'account_user_id',
        'oracle_user_id',
        'oracle_user_name',
        'assigned_salespeople',
        'lat',
        'lng',
        'address',
        'fcm_token',
        'fcm_token_updated_at',
    ];

    protected $casts = [
        'additional_roles'      => 'array',
        'fcm_token_updated_at'  => 'datetime',
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

    public function locationHistories()
    {
        return $this->hasMany(UserLocationHistory::class)->orderByDesc('created_at');
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


    public function getRoleName(): ?string
    {
        // First try the string role column (newer approach)
        if (!empty($this->attributes['role'])) {
            return $this->attributes['role'];
        }
        
        // Fall back to role relationship if role_id exists
        if ($this->role_id) {
            return $this->role?->name;
        }
        
        return null;
    }

    /**
     * Get the role name attribute.
     */
    public function getRoleNameAttribute(): ?string
    {
        return $this->getRoleName();
    }

    public function isAdmin(): bool
    {
        $roleName = $this->getRoleName();
        return $roleName === 'admin';
    }

    public function isInvoiceManager(): bool
    {
        $roleName = $this->getRoleName();
        return $roleName === 'invoice-manager';
    }

    public function isDirector(): bool
    {
        return $this->getRoleName() === 'director';
    }

    /** Either of the two CMD roles — used by Vendors AP approval middleware. */
    public function isCmd(): bool
    {
        $r = $this->getRoleName();
        return $r === 'cmd-khi' || $r === 'cmd-lhr';
    }

    // ════════════════════════════════════════════════════════════════════
    //  MULTI-ROLE HELPERS (additional_roles JSON column)
    // ════════════════════════════════════════════════════════════════════

    /**
     * The list of EXTRA roles this user has, on top of the primary `role`.
     * Returns an empty array if none have been assigned.
     */
    public function getAdditionalRoles(): array
    {
        $raw = $this->additional_roles;
        if (is_string($raw)) {
            $raw = json_decode($raw, true) ?: [];
        }
        return is_array($raw) ? array_values(array_filter($raw)) : [];
    }

    /**
     * Returns the primary role + additional roles as one combined list.
     */
    public function getAllRoles(): array
    {
        $primary = $this->getRoleName();
        $merged  = $this->getAdditionalRoles();
        if ($primary) {
            $merged[] = $primary;
        }
        return array_values(array_unique($merged));
    }

    /**
     * True if the user has ANY of the supplied roles (primary OR additional).
     */
    public function hasAnyRole(array|string $roles): bool
    {
        $roles = (array) $roles;
        $userRoles = $this->getAllRoles();
        foreach ($roles as $role) {
            if (in_array($role, $userRoles, true)) return true;
        }
        return false;
    }

    /**
     * Convenience: does the user hold a specific additional role?
     */
    public function hasAdditionalRole(string $role): bool
    {
        return in_array($role, $this->getAdditionalRoles(), true);
    }

    // ── View-only roles ────────────────────────────────────────────────
    // 'view-khi' / 'view-lhr' / 'view-all' grant READ access to dashboards,
    // orders, receipts, and customer data scoped to the given location.
    // They never grant edit/push permissions.

    public function hasViewAll(): bool      { return $this->hasAdditionalRole('view-all')      || $this->isAdmin(); }
    public function hasViewKhi(): bool      { return $this->hasViewAll() || $this->hasAdditionalRole('view-khi'); }
    public function hasViewLhr(): bool      { return $this->hasViewAll() || $this->hasAdditionalRole('view-lhr'); }
    public function hasAnyViewRole(): bool  { return $this->hasViewKhi() || $this->hasViewLhr(); }

    /**
     * OUs the user can VIEW (via additional view roles). Returns empty array
     * when the user has no view role at all.
     *
     * Karachi OUs: 102-106
     * Lahore OUs:  108-109
     */
    public function getViewableOuIds(): array
    {
        $khi = [102, 103, 104, 105, 106];
        $lhr = [108, 109];

        if ($this->hasViewAll()) return array_merge($khi, $lhr);

        $ous = [];
        if ($this->hasViewKhi()) $ous = array_merge($ous, $khi);
        if ($this->hasViewLhr()) $ous = array_merge($ous, $lhr);
        return $ous;
    }

    /**
     * Combined OUs the user can see — adds view-role OUs to whatever
     * getAllowedOuIds() already returns (so primary-role OUs aren't lost).
     * Use this everywhere a read-only query needs to honour view roles.
     */
    public function getEffectiveOuIds(): array
    {
        $primary = method_exists($this, 'getAllowedOuIds') ? $this->getAllowedOuIds() : [];
        return array_values(array_unique(array_merge($primary, $this->getViewableOuIds())));
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
        // Email overrides — these named users are treated as sales-head for
        // routing/sidebar/access purposes regardless of their actual role
        // (which may be 'user' or 'sales-head' in different environments).
        if (in_array(strtolower($this->email ?? ''), [
            'masood_qamar@quadri-group.com',
            'nauman_ahmad@quadri-group.com',
            'm_asim@quadri-group.com',
            'muhammad_fahim@quadri-group.com',
        ], true)) {
            return true;
        }

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

    public function isInventoryManager(): bool
    {
        $roleName = $this->getRoleName();
        return $roleName === 'inventory-manager';
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
        // Specific email overrides — these users should NOT see receipts at all,
        // regardless of role.
        $email = strtolower($this->email ?? '');
        $receiptsBlockedEmails = [
            'muhammad_fahim@quadri-group.com', // Fahim — orders only
        ];
        if (in_array($email, $receiptsBlockedEmails, true)) {
            return false;
        }

        // Sales-head gets view-only access to receipts (handled by route + UI gates).
        return $this->isAdmin() || $this->isCmdKhi() || $this->isCmdLhr() || $this->isSalesHead();
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

        // Email-based overrides for the four sales team leads.
        $ouOverride = $this->salesLeadEmailOuOverride();
        if ($ouOverride !== null) {
            return $ouOverride;
        }

        // Get OU IDs from Oracle organizations for other roles
        return $this->getOracleOrganizations();
    }

    /**
     * Returns the OU list for the four named sales-head leads, or null if
     * the current user isn't one of them. Centralises the email→OU mapping
     * used by both getAllowedOuIds() and getAllowedReceiptOuIds().
     */
    protected function salesLeadEmailOuOverride(): ?array
    {
        $email = strtolower($this->email ?? '');

        return match ($email) {
            'masood_qamar@quadri-group.com'   => [102, 103, 104, 105, 106, 108, 109], // all
            'muhammad_fahim@quadri-group.com' => [102, 103, 104, 105, 106, 108, 109], // all (overall view)
            'nauman_ahmad@quadri-group.com'   => [102, 103, 104, 105, 106],            // Karachi only
            'm_asim@quadri-group.com'         => [108, 109],                            // Lahore only
            default                            => null,
        };
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

        // Email-based overrides for the four sales team leads — these guarantee
        // location scope without depending on UserOrganization being populated.
        $ouOverride = $this->salesLeadEmailOuOverride();
        if ($ouOverride !== null) {
            return $ouOverride;
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
