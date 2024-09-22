<?php

namespace App\Livewire;

use App\Models\User;
use App\Enums\RoleEnum;
use Livewire\Component;
use Filament\Tables\Table;
use App\Rules\StrongPassword;
use App\Traits\NotifiesUsers;
use Filament\Tables\Actions\Action;
use Illuminate\Contracts\View\View;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Columns\ImageColumn;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Tables\Concerns\InteractsWithTable;

class ListUsers extends Component implements HasForms, HasTable
{
    use InteractsWithTable;
    use InteractsWithForms;
    use NotifiesUsers;

    public $user;
    public $name, $email, $role, $password, $password_confirmation;
    public $new_name, $new_email, $new_role, $new_password, $new_password_confirmation;

    public function table(Table $table): Table
    {
        return $table
            ->query(User::query())
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
                    ->visibleFrom('md')
                    ->formatStateUsing(fn($state) => $state->name())
                    ->searchable()
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
                    ->label('Edit User')
                    ->action(fn(User $record) => $this->openEditModal($record))
            ])
            ->bulkActions([
                // Add any bulk actions if needed
            ]);
    }

    public function openNewUserModal()
    {
        $this->resetNewUserForm('new_name', 'new_email', 'new_role', 'new_password', 'new_password_confirmation');
        $this->dispatch('open-modal', 'new_user');
    }

    public function resetNewUserForm()
    {
        $this->reset('new_name', 'new_email', 'new_role');
    }

    public function createUser()
    {
        $this->validate([
            'new_name' => 'required|string|max:255',
            'new_email' => 'required|string|email|max:255|unique:users,email',
            'new_role' => 'required',
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
            'new_password.required' => 'The password is required.',
            'new_password_confirmation.required' => 'The confirm password is required.',
            'new_password_confirmation.same' => 'The password confirmation does not match.',
        ]);

        User::create([
            'name' => $this->new_name,
            'email' => $this->new_email,
            'role' => RoleEnum::from($this->new_role),
            'password' => bcrypt($this->new_password),
        ]);

        $this->dispatch('close-modal', 'new_user');
        $this->notifyUser('User Created', 'User created successfully.');
    }

    public function openEditModal(User $user)
    {
        $this->user = $user;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->role = $user->role->value;
        $this->dispatch('open-modal', 'edit_user_modal');
    }

    public function closeEditModal()
    {
        $this->reset('user', 'name', 'email', 'role');
        $this->dispatch('close-modal', 'edit_user_modal');
    }

    public function updateUser()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $this->user['id'],
            'role' => 'required',
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
            'password_confirmation.same' => 'The password confirmation does not match.',
        ]);

        $user = User::find($this->user['id']);

        // Prepare the data to update
        $data = [
            'name' => $this->name,
            'email' => $this->email,
            'role' => RoleEnum::from($this->role),
        ];

        // Only update the password if provided
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
        ]);
    }
}
