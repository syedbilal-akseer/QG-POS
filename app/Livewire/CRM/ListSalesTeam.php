<?php

namespace App\Livewire\CRM;

use App\Models\Role;
use App\Models\User;
use Livewire\Component;
use App\Models\Category;
use App\Models\SalesTeam;
use Filament\Tables\Table;
use Livewire\Attributes\On;
use App\Traits\NotifiesUsers;
use App\Models\TeamAssignment;
use Livewire\Attributes\Title;
use Filament\Tables\Actions\Action;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Tables\Concerns\InteractsWithTable;

#[Title('Sales Team')]
class ListSalesTeam extends Component implements HasForms, HasTable
{
    use InteractsWithTable;
    use InteractsWithForms;
    use NotifiesUsers;

    public $salesTeam;
    public $team_name, $category_id, $members = [];
    public $new_team_name;
    public $new_category_id;
    public $assignments = []; // To hold user and role pairs

    public function addAssignment()
    {
        $this->assignments[] = ['user_id' => null, 'role_id' => null];
    }


    public function removeAssignment($index)
    {
        // Remove the specific assignment
        unset($this->assignments[$index]);

        // Reindex the array to avoid Livewire errors
        $this->assignments = array_values($this->assignments);
    }

    public function addMember()
    {
        $this->members[] = ['user_id' => null, 'role_id' => null];
    }


    public function removeMember($index)
    {
        // Remove the specific assignment
        unset($this->members[$index]);

        // Reindex the array to avoid Livewire errors
        $this->members = array_values($this->members);
    }


    public function table(Table $table): Table
    {
        return $table
            ->query(SalesTeam::query()->with(['category', 'users']))
            ->columns([
                TextColumn::make('name')
                    ->label('Team Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('category.name')
                    ->label('Category')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('users_count')
                    ->badge()
                    ->label('Members')
                    ->counts('users'),
            ])
            ->filters([
                // Add filters if necessary
            ])
            ->actions([
                Action::make('edit')
                    ->label('Edit')
                    ->icon('heroicon-m-pencil-square')
                    ->button()
                    ->action(fn(SalesTeam $record) => $this->openEditModal($record)),
            ])
            ->bulkActions([
                // Add bulk actions if needed
            ])
            ->defaultSort('created_at', 'desc');
    }

    public function openNewTeamModal()
    {
        $this->resetNewTeamForm();
        $this->addAssignment();
        $this->dispatch('open-modal', 'new_sales_team');
    }

    public function resetNewTeamForm()
    {
        $this->reset('new_team_name', 'new_category_id', 'assignments');
    }

    public function createSalesTeam()
    {
        $this->validate([
            'new_team_name' => 'required|string|max:255|unique:sales_teams,name',
            'new_category_id' => 'required|exists:categories,id',
            'assignments' => 'array|min:1',
            'assignments.*.user_id' => 'required|exists:users,id',
            'assignments.*.role_id' => 'required|exists:roles,id',
        ], [
            'new_team_name.required' => 'The team name is required.',
            'new_team_name.unique' => 'The team name has already been taken.',
            'new_category_id.required' => 'The category is required.',
            'new_category_id.exists' => 'The selected category is invalid.',
            'assignments.*.user_id.required' => 'The user field is required.',
            'assignments.*.role_id.required' => 'The role field is required.',
        ]);

        $errors = [];

        // Check for duplicate user-role pairs
        $seenUserRoles = [];
        foreach ($this->assignments as $index => $assignment) {
            $key = "{$assignment['user_id']}-{$assignment['role_id']}";
            if (in_array($key, $seenUserRoles)) {
                $errors["assignments.{$index}.user_id"] = 'This user is already assigned to the same role.';
            } else {
                $seenUserRoles[] = $key;
            }
        }

        // Check for duplicate users regardless of role
        $seenUsers = [];
        foreach ($this->assignments as $index => $assignment) {
            if (in_array($assignment['user_id'], $seenUsers)) {
                $errors["assignments.{$index}.user_id"] = 'This user is already added to the team.';
            } else {
                $seenUsers[] = $assignment['user_id'];
            }
        }

        // Add errors to the session if any
        if (!empty($errors)) {
            foreach ($errors as $field => $message) {
                $this->addError($field, $message);
            }
            return; // Stop execution if there are errors
        }

        // $data = [
        //     'team_name' => $this->new_team_name,
        //     'category_id' => $this->new_category_id,
        //     'assignments' => $this->assignments,
        // ];

        // dd($data);

        // Create the Sales Team
        $salesTeam = SalesTeam::create([
            'name' => $this->new_team_name,
            'category_id' => $this->new_category_id,
        ]);

        // Assign users and roles
        foreach ($this->assignments as $assignment) {
            TeamAssignment::create([
                'sales_team_id' => $salesTeam->id,
                'user_id' => $assignment['user_id'],
                'role_id' => $assignment['role_id'],
            ]);
        }

        // Notify and reset form
        $this->dispatch('close-modal', 'new_sales_team');
        $this->resetNewTeamForm();
        $this->notifyUser('Sales Team Created', 'Sales team created successfully.');
    }

    public function openEditModal(SalesTeam $salesTeam)
    {
        $this->salesTeam = $salesTeam;
        $this->team_name = $salesTeam->name;
        $this->category_id = $salesTeam->category_id;

        // Fetch the team members and their roles
        $this->members = $salesTeam->teamAssignments->map(function ($assignment) {
            return [
                'user_id' => $assignment->user_id,
                'role_id' => $assignment->role_id,
            ];
        })->toArray();

        // Dispatch an event to the front-end to set selected values
        $this->dispatch('set-selected', [
            'value' => $this->members,
        ]);

        $this->dispatch('open-modal', 'edit_sales_team_modal');
    }


    public function updateSalesTeam()
    {
        $this->validate([
            'team_name' => 'required|string|max:255|unique:sales_teams,name,' . $this->salesTeam->id,
            'category_id' => 'required|exists:categories,id',
            'members' => 'array|min:1',
            'members.*.user_id' => 'required|exists:users,id',
            'members.*.role_id' => 'required|exists:roles,id',
        ], [
            'team_name.required' => 'The team name is required.',
            'team_name.unique' => 'The team name has already been taken.',
            'category_id.required' => 'The category is required.',
            'category_id.exists' => 'The selected category is invalid.',
            'members.*.user_id.required' => 'The user field is required.',
            'members.*.role_id.required' => 'The role field is required.',
        ]);

        $errors = [];

        // Check for duplicate user-role pairs
        $seenUserRoles = [];
        foreach ($this->members as $index => $member) {
            $key = "{$member['user_id']}-{$member['role_id']}";
            if (in_array($key, $seenUserRoles)) {
                $errors["members.{$index}.user_id"] = 'This user is already assigned to the same role.';
            } else {
                $seenUserRoles[] = $key;
            }
        }

        // Check for duplicate users regardless of role
        $seenUsers = [];
        foreach ($this->members as $index => $member) {
            if (in_array($member['user_id'], $seenUsers)) {
                $errors["members.{$index}.user_id"] = 'This user is already added to the team.';
            } else {
                $seenUsers[] = $member['user_id'];
            }
        }

        // Add errors to the session if any
        if (!empty($errors)) {
            foreach ($errors as $field => $message) {
                $this->addError($field, $message);
            }
            return; // Stop execution if there are errors
        }

        // Update the Sales Team
        $this->salesTeam->update([
            'name' => $this->team_name,
            'category_id' => $this->category_id,
        ]);

        // Update members
        $existingAssignments = TeamAssignment::where('sales_team_id', $this->salesTeam->id)->get();
        $newAssignments = collect($this->members);

        // Remove unselected assignments
        $existingAssignments->each(function ($assignment) use ($newAssignments) {
            $exists = $newAssignments->contains(function ($member) use ($assignment) {
                return $member['user_id'] == $assignment->user_id && $member['role_id'] == $assignment->role_id;
            });

            if (!$exists) {
                $assignment->delete();
            }
        });

        // Add or update assignments
        foreach ($this->members as $member) {
            TeamAssignment::updateOrCreate(
                [
                    'sales_team_id' => $this->salesTeam->id,
                    'user_id' => $member['user_id'],
                    'role_id' => $member['role_id'],
                ],
                [
                    'sales_team_id' => $this->salesTeam->id,
                    'user_id' => $member['user_id'],
                    'role_id' => $member['role_id'],
                ]
            );
        }

        // Notify and reset
        $this->dispatch('close-modal', 'edit_sales_team_modal');
        $this->reset(['team_name', 'category_id', 'members']);
        $this->notifyUser('Sales Team Updated', 'Sales team updated successfully.');
    }

    public function render()
    {
        return view('livewire.crm.list-sales-team', [
            'categories' => Category::all()->map(
                fn($category) => [
                    'value' => $category->id,
                    'label' => $category->name,
                ],
            )
                ->toArray(),
            'users' => User::all()->map(
                fn($user) => [
                    'value' => $user->id,
                    'label' => $user->name,
                ],
            )
                ->toArray(),
            'roles' => Role::all()->map(
                fn($role) => [
                    'value' => $role->id,
                    'label' => $role->name,
                ],
            )
                ->toArray(),
        ]);
    }
}
