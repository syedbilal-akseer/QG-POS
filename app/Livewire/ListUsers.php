<?php

namespace App\Livewire;

use App\Models\Role;
use App\Models\User;
use App\Enums\RoleEnum;
use Livewire\Component;
use App\Models\Department;
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

    public $user;
    public $name, $email, $role_id, $department_id, $reporting_to, $password, $password_confirmation, $off_days;
    public $new_name, $new_email, $new_role_id, $new_department_id, $new_reporting_to, $new_password, $new_password_confirmation, $new_off_days;

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
                TextColumn::make('department.name')
                    ->label('Department')
                    ->visibleFrom('md')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('role.name')
                    ->label('Role')
                    ->formatStateUsing(fn($state) => ucwords(str_replace('-', ' ', $state)))
                    ->visibleFrom('md')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('manager.name')
                    ->label('Reporting To')
                    ->formatStateUsing(fn($state) => $state ? ucwords($state) : 'None')
                    ->visibleFrom('md')
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
        $this->reset('new_name', 'new_email', 'new_role_id', 'new_department_id', 'new_password', 'new_password_confirmation', 'new_off_days', 'new_reporting_to');
    }

    public function createUser()
    {
        $this->validate([
            'new_name' => 'required|string|max:255',
            'new_email' => 'required|string|email|max:255|unique:users,email',
            'new_role_id' => 'required|exists:roles,id',
            'new_department_id' => 'required|exists:departments,id',
            'new_reporting_to' => 'nullable|exists:users,id',
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
            'new_role_id.required' => 'The role is required.',
            'new_role_id.exists' => 'The selected role is invalid.',
            'new_department_id.required' => 'The department is required.',
            'new_department_id.exists' => 'The selected department is invalid.',
            'new_reporting_to.exists' => 'The selected reporting to is invalid.',
            'new_password.required' => 'The password is required.',
            'new_password.string' => 'The password must be a string.',
            'new_password.min' => 'The password must be at least 8 characters.',
            'new_password_confirmation.same' => 'The password confirmation does not match.',
        ]);

        User::create([
            'name' => $this->new_name,
            'email' => $this->new_email,
            'department_id' => $this->new_department_id,
            'role_id' => $this->new_role_id,
            'reporting_to' => $this->new_reporting_to,
            'password' => bcrypt($this->new_password),
            'off_days' => $this->new_off_days,
        ]);

        $this->dispatch('close-modal', 'new_user');
        $this->notifyUser('User Created', 'User created successfully.');
    }

    public function openEditModal(User $user)
    {
        $this->user = $user;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->role_id = $user->role_id;
        $this->department_id = $user->department_id;
        $this->reporting_to = $user->reporting_to;
        $this->off_days = $user->off_days;
        $this->dispatch('open-modal', 'edit_user_modal');
    }

    public function closeEditModal()
    {
        $this->reset('user', 'name', 'email', 'role_id', 'department_id', 'off_days', 'reporting_to', 'password', 'password_confirmation');
        $this->dispatch('close-modal', 'edit_user_modal');
    }

    public function updateUser()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|max:255|unique:users,email,' . $this->user->id,
            'department_id' => 'required|exists:departments,id',
            'role_id' => 'required|exists:roles,id',
            'reporting_to' => 'nullable|exists:users,id',
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
            'department_id.required' => 'The department is required.',
            'department_id.exists' => 'The selected department is invalid.',
            'role_id.required' => 'The role is required.',
            'role_id.exists' => 'The selected role is invalid.',
            'reporting_to.exists' => 'The selected reporting to is invalid.',
            'password.string' => 'The password must be a string.',
            'password.min' => 'The password must be at least 8 characters.',
            'password_confirmation.same' => 'The password confirmation does not match.',
        ]);

        $user = User::find($this->user->id);

        $data = [
            'name' => $this->name,
            'email' => $this->email,
            'department_id' => $this->department_id,
            'role_id' => $this->role_id,
            'reporting_to' => $this->reporting_to,
            'off_days' => $this->off_days,
        ];

        if ($this->password) {
            $data['password'] = bcrypt($this->password);
        }

        $user->update($data);

        $this->notifyUser('User Updated', 'User Updated successfully.');

        $this->closeEditModal();
    }

    public function render(): View
    {
        return view('livewire.list-users', [
            'user' => $this->user,
            'departments' => Department::all(),
            'roles' => Role::all(),
            'users' => User::where('id', '!=', auth()->id())->get(),
        ]);
    }
}
