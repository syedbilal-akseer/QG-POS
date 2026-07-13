<?php

namespace App\Livewire;

use App\Models\CustomerVisit;
use App\Models\User;
use Carbon\Carbon;
use Livewire\Component;
use Filament\Tables\Table;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Actions\Action;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Title;

#[Title('Customer Visits')]
class ListCustomerVisits extends Component implements HasForms, HasTable
{
    use InteractsWithTable;
    use InteractsWithForms;

    public function table(Table $table): Table
    {
        $query = CustomerVisit::query()->with(['user', 'customer']);

        $currentUser = auth()->user();

        // Apply access control: only admin and nauman_ahmad@quadri-group.com can view all
        if (!$currentUser->isAdmin() && $currentUser->email !== 'nauman_ahmad@quadri-group.com') {
            $query->where('user_id', $currentUser->id);
        }

        return $table
            ->query($query)
            ->columns([
                TextColumn::make('user.name')
                    ->label('Salesperson')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('customer.customer_name')
                    ->label('Customer')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('visit_start_time')
                    ->label('Visit Start')
                    ->dateTime('M d, Y H:i')
                    ->sortable(),
                TextColumn::make('visit_end_time')
                    ->label('Visit End')
                    ->dateTime('M d, Y H:i')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'in_progress' => 'warning',
                        'pending' => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('comments')
                    ->label('Comments')
                    ->limit(50)
                    ->searchable(),
            ])
            ->filters([
                SelectFilter::make('user_id')
                    ->label('Salesperson')
                    ->options(User::where('role', 'salesperson')
                        ->orWhere('role', 'admin')
                        ->pluck('name', 'id'))
                    ->visible(fn() => auth()->user()->isAdmin() || auth()->user()->email === 'nauman_ahmad@quadri-group.com'),

                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'pending' => 'Pending',
                        'in_progress' => 'In Progress',
                        'completed' => 'Completed',
                    ]),

                Filter::make('visit_start_time')
                    ->form([
                        DatePicker::make('from')
                            ->label('Visit Date From')
                            ->native(false),
                        DatePicker::make('until')
                            ->label('Visit Date Until')
                            ->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn(Builder $query, $date): Builder => $query->whereDate('visit_start_time', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn(Builder $query, $date): Builder => $query->whereDate('visit_start_time', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if (!$data['from'] && !$data['until']) {
                            return null;
                        }

                        $from = $data['from'] ? Carbon::parse($data['from'])->toFormattedDateString() : 'N/A';
                        $until = $data['until'] ? Carbon::parse($data['until'])->toFormattedDateString() : 'N/A';

                        return 'Visit Date from ' . $from . ' to ' . $until;
                    }),
            ])
            ->filtersTriggerAction(
                fn(Action $action) => $action
                    ->button()
                    ->label('Filter'),
            )
            ->actions([
                Action::make('view')
                    ->icon('heroicon-m-eye')
                    ->button()
                    ->label('View')
                    ->url(fn (CustomerVisit $record): string => route('customer-visits.show', $record))
                    ->openUrlInNewTab(),
            ])
            ->defaultSort('visit_start_time', 'desc');
    }

    public function render()
    {
        return view('livewire.list-customer-visits');
    }
}
