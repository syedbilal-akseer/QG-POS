<?php

namespace App\Livewire\CRM\Manage;

use Closure;
use Filament\Forms\Get;
use Livewire\Component;
use Filament\Tables\Table;
use App\Traits\NotifiesUsers;
use App\Models\TeamAssignment;
use Livewire\Attributes\Title;
use App\Models\MonthlyTourPlan;
use Filament\Tables\Actions\Action;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Tables\Concerns\InteractsWithTable;
use Illuminate\Support\Facades\Log;

#[Title('Manage Monthly Tour Plan')]
class MonthlyTourPlanApproval extends Component implements HasForms, HasTable
{
    use InteractsWithTable;
    use InteractsWithForms;
    use NotifiesUsers;

    public $confirmation = '';

    // Define the table directly
    public function table(Table $table): Table
    {
        $user = auth()->user();
    
        // Check if the user is the Line Manager or HOD for the salesperson
        $query = MonthlyTourPlan::query()->where('status', 'pending');
    
        // Get all the plans that the user can manage
        if ($user->isManager()) {
            // User is a Line Manager: Can approve plans awaiting Line Manager approval
            $query->where('line_manager_approval', false)
                ->whereHas('salesperson', function ($q) use ($user) {
                    $q->where('reporting_to', $user->id); // The salesperson reports to this user
                });
        }
    
        if ($user->isHOD() || $user->isAdmin()) {
            // User is an HOD or Admin: Can approve plans awaiting HOD approval, but only after Line Manager approval
            $query->orWhere(function ($subQuery) use ($user) {
                $subQuery->where('line_manager_approval', true)
                    ->where('hod_approval', false)
                    ->whereHas('salesperson', function ($q) use ($user) {
                        $q->where('department_id', $user->department_id); // Salesperson must be in the same department
                    });
            });
        }
    
        return $table->query($query)
            ->columns([
                TextColumn::make('month')
                    ->label('Month')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->formatStateUsing(fn($state) => ucwords($state))
                    ->searchable()
                    ->sortable(),
            ])
            ->actions([
                Action::make('view')
                    ->label('View Details')
                    ->icon('heroicon-o-eye')
                    ->button()
                    ->action(function (MonthlyTourPlan $record) {
                        $this->redirectRoute('monthlyTourPlans.planDetails', ['monthlyTourPlan' => $record]);
                    })
                    ->color('violet'),
    
                    Action::make('approve')
                    ->label('Approve')
                    ->action(function (MonthlyTourPlan $record, array $data) use ($user) {
                        // Debug the data being passed
                          // Check if confirmation field is in the data
                
                          if (trim($data['confirmation']) === 'confirmed') {
                            // Proceed with the approval process only if the confirmation value is 'confirmed'
                            if ($user->isManager() && !$record->line_manager_approval) {
                                

                                $record->update([
                                    'line_manager_approval' => true,
                                    'status' => 'approved',
                                ]);
                                
                                Log::info('After update');
                
                                // Notify the Line Manager (real-time)
                                $this->notify('Success', 'Monthly Tour Plan approved successfully.');
                
                                // Notify the salesperson (database only)
                                $this->notify(
                                    'Tour Plan Approved',
                                    'Your Monthly Tour Plan has been approved by the Line Manager.',
                                    'success',
                                    true,
                                    $record->salesperson
                                );
                            } elseif (($user->isHOD() || $user->isAdmin()) && $record->line_manager_approval && !$record->hod_approval) {
                                Log::info('Checking before update');
                                $record->update([
                                    'hod_approval' => true,
                                    'status' => 'approved',
                                ]);
                                Log::info('After update');
                
                                // Notify the HOD (real-time)
                                $this->notify('Success', 'Monthly Tour Plan approved successfully.');
                
                                // Notify the salesperson (database only)
                                $this->notify(
                                    'Tour Plan Approved',
                                    'Your Monthly Tour Plan has been approved by the HOD.',
                                    'success',
                                    true,
                                    $record->salesperson
                                );
                            }
                        }
                    })
                    ->color('primary')
                    ->button()
                    ->icon('heroicon-o-check')
                    ->visible(fn($record) => $record->status === 'pending' && !$user->isSalesPerson()) // Hide if SalesPerson
                    ->requiresConfirmation()
                    ->modalIcon('heroicon-o-question-mark-circle')
                    ->modalHeading('Approve Monthly Tour Plan')
                    ->modalDescription('Are you sure you want to approve this Monthly Tour Plan?')
                    ->form([  // Add input field for confirmation
                        TextInput::make('confirmation')
                            ->label('Type "confirmed" to approve')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Enter confirmed here')
                            ->rules([
                                fn(): Closure => function (string $attribute, $value, Closure $fail) {
                                    if ($value !== 'confirmed') {
                                        $fail('You must type "confirmed" to approve the plan.');
                                    }
                                }
                            ])
                            ->live(),
                    ])
                    ->modalSubmitActionLabel('Approve'),
                
    
                Action::make('reject')
                    ->label('Reject')
                    ->color('danger')
                    ->button()
                    ->icon('heroicon-o-x-mark')
                    ->visible(fn($record) => $record->status === 'pending' && !$user->isSalesPerson()) // Hide if SalesPerson
                    ->form([
                        Textarea::make('rejection_reason')
                            ->label('Rejection Reason')
                            ->required()
                            ->placeholder('Enter rejection reason here')
                            ->maxLength(255),
                    ])
                    ->action(function (array $data, MonthlyTourPlan $record) use ($user) {
                        // Update the status and rejection reason of the plan
                        $record->update([
                            'status' => 'rejected',
                            'rejection_reason' => $data['rejection_reason'],
                        ]);
    
                        // Notify the rejecting user (real-time)
                        $this->notify('Success', 'Tour Plan Rejected Successfully');
    
                        // Notify the salesperson (database)
                        $this->notify(
                            'Tour Plan Rejected',
                            "Your Monthly Tour Plan has been rejected by {$user->role->name}.",
                            'danger',
                            true,
                            $record->salesperson
                        );
                    })
                    ->requiresConfirmation()
                    ->modalIcon('heroicon-o-x-mark')
                    ->modalHeading('Reject Monthly Tour Plan')
                    ->modalDescription('Are you sure you want to reject this Monthly Tour Plan?')
                    ->modalSubmitActionLabel('Reject'),

                    Action::make('edit')
                    ->label('Edit')
                    ->color('primary')
                    ->button()
                    ->icon('heroicon-o-pencil')
                    ->visible(fn($record) => $user->isSalesPerson()) // Visible only if SalesPerson
                    ->action(function (MonthlyTourPlan $record) {
                        $this->redirectRoute('monthlyTourPlans.edit', ['monthlyTourPlan' => $record]);
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }
    

    public function render()
    {
        $pageTitle = 'Manage Monthly Tour Plan';  // Set the page title
        return view('livewire.crm.manage.monthly-tour-plan-approval', compact('pageTitle'));
    }
}
