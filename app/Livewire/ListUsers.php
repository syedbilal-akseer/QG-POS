<?php

namespace App\Livewire;

use App\Models\Role;
use App\Models\User;
use App\Models\UserOrganization;
use App\Enums\RoleEnum;
use Livewire\Component;
use App\Models\Department;
use Illuminate\Support\Facades\DB;
use Filament\Tables\Table;
use App\Rules\StrongPassword;
use App\Traits\NotifiesUsers;
use Livewire\Attributes\Title;
use Filament\Tables\Actions\Action;
use Illuminate\Contracts\View\View;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Columns\ImageColumn;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Tables\Concerns\InteractsWithTable;


#[Title('Users')]
class ListUsers extends Component implements HasForms, HasTable
{
    use InteractsWithTable;
    use InteractsWithForms;
    use NotifiesUsers;

    public $user, $userId;
    public $name, $email, $role, $reporting_to, $password, $password_confirmation, $off_days;
    public $new_name, $new_email, $new_role, $new_reporting_to, $new_password, $new_password_confirmation, $new_off_days;
    public $supply_chain_user_id, $account_user_id, $oracle_user_id, $oracle_user_name, $selected_organizations = [];
    public $new_supply_chain_user_id, $new_account_user_id, $new_oracle_user_id, $new_oracle_user_name, $new_selected_organizations = [];
    public $assigned_salespeople = [], $new_assigned_salespeople = [];

    public function table(Table $table): Table
    {
        return $table
            ->query(User::query()->with(['role', 'department']))
            ->columns([
                ImageColumn::make('profile_photo')
                    ->label('Image')
                    ->circular()
                    ->defaultImageUrl(url('placeholder.png')),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('role')
                    ->label('Role')
                    ->formatStateUsing(fn($state) => ucwords(str_replace('-', ' ', $state ?: 'No Role')))
                    ->visibleFrom('md')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('manager.name')
                    ->label('Reporting To')
                    ->formatStateUsing(fn($state) => $state ? ucwords($state) : 'None')
                    ->visibleFrom('md')
                    ->sortable(),
                TextColumn::make('oracle_user_name')
                    ->label('Oracle User')
                    ->formatStateUsing(fn($state) => $state ?: 'Not Mapped')
                    ->visibleFrom('lg')
                    ->sortable(),
            ])
            ->filters([
                // Add any specific filters if needed
            ])
            ->actions([
                Action::make('edit')
                    ->label('Edit')
                    ->icon('heroicon-m-pencil-square')
                    ->button()
                    ->action(fn(User $record) => $this->openEditModal($record))
            ])
            ->bulkActions([
                // Add any bulk actions if needed
            ])
            ->defaultSort('created_at', 'desc');
    }

    public function openNewUserModal()
    {
        $this->resetNewUserForm();
        $this->dispatch('open-modal', 'new_user');
    }

    public function resetNewUserForm()
    {
        $this->reset('new_name', 'new_email', 'new_role', 'new_password', 'new_password_confirmation', 'new_off_days', 'new_reporting_to', 'new_supply_chain_user_id', 'new_account_user_id', 'new_oracle_user_id', 'new_oracle_user_name', 'new_selected_organizations', 'new_assigned_salespeople');
    }

    public function createUser()
    {
        $this->validate([
            'new_name' => 'required|string|max:255',
            'new_email' => 'required|string|email|max:255|unique:users,email',
            'new_role' => 'required|string|in:admin,user,supply-chain,sales-head,price-uploads,cmd-khi,cmd-lhr,scm-lhr,hod,line-manager,account-user',
            'new_reporting_to' => 'nullable|exists:users,id',
            'new_supply_chain_user_id' => 'nullable|exists:users,id',
            'new_account_user_id' => 'nullable|exists:users,id',
            'new_oracle_user_id' => 'nullable|string|max:50',
            'new_oracle_user_name' => 'nullable|string|max:100',
            'new_password' => ['required', 'string', 'min:8', new StrongPassword],
            'new_password_confirmation' => ['same:new_password', 'required', new StrongPassword],
        ], [
            'new_name.required' => 'The name is required.',
            'new_name.string' => 'The name must be a string.',
            'new_name.max' => 'The name may not be greater than 255 characters.',
            'new_email.required' => 'The email is required.',
            'new_email.string' => 'The email must be a string.',
            'new_email.email' => 'The email must be a valid email address.',
            'new_email.max' => 'The email may not be greater than 255 characters.',
            'new_email.unique' => 'The email has already been taken.',
            'new_role.required' => 'The role is required.',
            'new_role.in' => 'The selected role is invalid.',
            'new_reporting_to.exists' => 'The selected reporting to is invalid.',
            'new_supply_chain_user_id.exists' => 'The selected supply chain user is invalid.',
            'new_account_user_id.exists' => 'The selected account user is invalid.',
            'new_password.required' => 'The password is required.',
            'new_password.string' => 'The password must be a string.',
            'new_password.min' => 'The password must be at least 8 characters.',
            'new_password_confirmation.same' => 'The password confirmation does not match.',
        ]);

        $user = User::create([
            'name' => $this->new_name,
            'email' => $this->new_email,
            'role' => $this->new_role,
            'reporting_to' => $this->new_reporting_to,
            'supply_chain_user_id' => $this->new_supply_chain_user_id,
            'account_user_id' => $this->new_account_user_id,
            'oracle_user_id' => $this->new_oracle_user_id,
            'oracle_user_name' => $this->new_oracle_user_name,
            'password' => bcrypt($this->new_password),
            'off_days' => $this->new_off_days,
            'assigned_salespeople' => !empty($this->new_assigned_salespeople) ? $this->new_assigned_salespeople : null,
        ]);

        // Sync organizations if selected
        if (!empty($this->new_selected_organizations)) {
            $this->syncUserOrganizations($user->id, $this->new_selected_organizations);
        }

        $this->dispatch('close-modal', 'new_user');
        $this->notifyUser('User Created', 'User created successfully.');
    }

    public function openEditModal(User $user)
    {
        $this->user = $user;
        $this->userId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->role = $user->role;
        $this->reporting_to = $user->reporting_to;
        $this->supply_chain_user_id = $user->supply_chain_user_id;
        $this->account_user_id = $user->account_user_id;
        $this->oracle_user_id = $user->oracle_user_id;
        $this->oracle_user_name = $user->oracle_user_name;
        $this->off_days = $user->off_days;
        $this->assigned_salespeople = $user->assigned_salespeople ?? [];

        // Load user's current organizations
        $this->selected_organizations = $user->userOrganizations()
            ->where('is_active', true)
            ->pluck('oracle_organization_code')
            ->toArray();

        $this->dispatch('open-modal', 'edit_user_modal');
    }

    public function closeEditModal()
    {
        $this->reset('user', 'userId', 'name', 'email', 'role', 'off_days', 'reporting_to', 'supply_chain_user_id', 'account_user_id', 'oracle_user_id', 'oracle_user_name', 'selected_organizations', 'assigned_salespeople', 'password', 'password_confirmation');
        $this->dispatch('close-modal', 'edit_user_modal');
    }

    public function updateUser()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|max:255|unique:users,email,' . $this->userId,
            'role' => 'required|string|in:admin,user,supply-chain,sales-head,price-uploads,cmd-khi,cmd-lhr,scm-lhr,hod,line-manager,account-user',
            'reporting_to' => 'nullable|exists:users,id',
            'supply_chain_user_id' => 'nullable|exists:users,id',
            'account_user_id' => 'nullable|exists:users,id',
            'oracle_user_id' => 'nullable|string|max:50',
            'oracle_user_name' => 'nullable|string|max:100',
            'password' => ['nullable', 'string', 'min:8', new StrongPassword],
            'password_confirmation' => ['same:password', 'nullable', new StrongPassword],
        ], [
            'name.required' => 'The name is required.',
            'name.string' => 'The name must be a string.',
            'name.max' => 'The name may not be greater than 255 characters.',
            'email.required' => 'The email is required.',
            'email.string' => 'The email must be a string.',
            'email.email' => 'The email must be a valid email address.',
            'email.max' => 'The email may not be greater than 255 characters.',
            'email.unique' => 'The email has already been taken.',
            'role.required' => 'The role is required.',
            'role.in' => 'The selected role is invalid.',
            'reporting_to.exists' => 'The selected reporting to is invalid.',
            'supply_chain_user_id.exists' => 'The selected supply chain user is invalid.',
            'account_user_id.exists' => 'The selected account user is invalid.',
            'password.string' => 'The password must be a string.',
            'password.min' => 'The password must be at least 8 characters.',
            'password_confirmation.same' => 'The password confirmation does not match.',
        ]);

        $user = User::findOrFail($this->userId);

        $data = [
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'reporting_to' => $this->reporting_to,
            'supply_chain_user_id' => $this->supply_chain_user_id,
            'account_user_id' => $this->account_user_id,
            'oracle_user_id' => $this->oracle_user_id,
            'oracle_user_name' => $this->oracle_user_name,
            'off_days' => $this->off_days,
            'assigned_salespeople' => !empty($this->assigned_salespeople) ? $this->assigned_salespeople : null,
        ];

        if ($this->password) {
            $data['password'] = bcrypt($this->password);
        }

        $user->update($data);

        // Sync organizations if selected
        if (!empty($this->selected_organizations)) {
            $this->syncUserOrganizations($user->id, $this->selected_organizations);
        } else {
            // If no organizations selected, deactivate all existing ones
            UserOrganization::where('user_id', $user->id)->update(['is_active' => false]);
        }

        $this->notifyUser('User Updated', 'User Updated successfully.');

        $this->closeEditModal();
    }

    public function getSupplyChainUsers()
    {
        return User::whereHas('role', function ($query) {
            $query->where('name', 'supply-chain');
        })->where('id', '!=', auth()->id())->get();
    }

    public function getAccountUsers()
    {
        return User::whereHas('role', function ($query) {
            $query->where('name', 'account-user');
        })->where('id', '!=', auth()->id())->get();
    }

    public function getSalespeople()
    {
        return User::where('role', 'user')
            ->orderBy('name')
            ->get();
    }

    public function render(): View
    {
        return view('livewire.list-users', [
            'user' => $this->user ?? ($this->userId ? User::find($this->userId) : null),
            'roles' => $this->getAvailableRoles(),
            'users' => User::where('id', '!=', auth()->id())->get(),
            'supplyChainUsers' => $this->getSupplyChainUsers(),
            'accountUsers' => $this->getAccountUsers(),
            'salespeople' => $this->getSalespeople(),
            'availableOrganizations' => $this->getAvailableOrganizations(),
        ]);
    }

    /**
     * Get available roles for selection
     */
    public function getAvailableRoles()
    {
        return [
            'admin' => 'Admin',
            'user' => 'User', 
            'supply-chain' => 'Supply Chain',
            'sales-head' => 'Sales Head',
            'price-uploads' => 'Price Uploads',
            'cmd-khi' => 'CMD-KHI',
            'cmd-lhr' => 'CMD-LHR',
            'scm-lhr' => 'SCM-LHR',
            'hod' => 'HOD',
            'line-manager' => 'Line Manager',
            'account-user' => 'Account User',
        ];
    }

    /**
     * Get available Oracle organizations for selection
     */
    public function getAvailableOrganizations()
    {
        try {
            // Get unique organizations from Oracle warehouses table
            return DB::connection('oracle')
                ->table('apps.qg_pos_warehouses')
                ->select('organization_code', 'organization_name', 'ou')
                ->whereNotNull('organization_code')
                ->whereNotNull('ou')
                ->distinct()
                ->orderBy('organization_name')
                ->get()
                ->map(function ($org) {
                    return [
                        'code' => $org->organization_code,
                        'name' => $org->organization_name,
                        'ou_id' => $org->ou,
                        'display' => "{$org->organization_code} - {$org->organization_name} (OU: {$org->ou})"
                    ];
                })->toArray();
        } catch (\Exception $e) {
            // Fallback to existing organizations in MySQL if Oracle fails
            return UserOrganization::select('oracle_organization_code', 'oracle_organization_name', 'oracle_ou_id')
                ->whereNotNull('oracle_organization_code')
                ->distinct()
                ->get()
                ->map(function ($org) {
                    return [
                        'code' => $org->oracle_organization_code,
                        'name' => $org->oracle_organization_name,
                        'ou_id' => $org->oracle_ou_id,
                        'display' => "{$org->oracle_organization_code} - {$org->oracle_organization_name} (OU: {$org->oracle_ou_id})"
                    ];
                })->toArray();
        }
    }

    /**
     * Sync users from Oracle QG_SHIPPING_USERS
     */
    public function syncOracleUsers()
    {
        try {
            // Call the sync command
            \Illuminate\Support\Facades\Artisan::call('sync:oracle-users');
            
            // Get the command output
            $output = \Illuminate\Support\Facades\Artisan::output();
            
            $this->notifyUser('Oracle Sync Complete', 'Oracle users have been synchronized successfully.');
            
            // Refresh the page data
            $this->dispatch('$refresh');
            
        } catch (\Exception $e) {
            $this->notifyUser('Oracle Sync Failed', 'Failed to sync Oracle users: ' . $e->getMessage(), 'error');
        }
    }

    /**
     * Sync user organizations after user creation/update
     */
    private function syncUserOrganizations($userId, $selectedOrganizations)
    {
        if (empty($selectedOrganizations)) {
            return;
        }

        // Deactivate existing organizations
        UserOrganization::where('user_id', $userId)->update(['is_active' => false]);

        // Add selected organizations
        foreach ($selectedOrganizations as $orgCode) {
            // Find organization details from available organizations
            $availableOrgs = $this->getAvailableOrganizations();
            $orgDetails = collect($availableOrgs)->firstWhere('code', $orgCode);
            
            if ($orgDetails) {
                UserOrganization::updateOrCreate(
                    [
                        'user_id' => $userId,
                        'oracle_organization_code' => $orgCode,
                    ],
                    [
                        'oracle_organization_name' => $orgDetails['name'],
                        'oracle_ou_id' => $orgDetails['ou_id'],
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}
