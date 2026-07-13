<?php

namespace App\Livewire;

use Carbon\Carbon;
use App\Models\ActivityLog;
use Livewire\Component;
use Filament\Tables\Table;
use Livewire\Attributes\Title;
use App\Exports\ActivityLogsExport;
use Maatwebsite\Excel\Facades\Excel;
use Filament\Tables\Actions\Action;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Tables\Concerns\InteractsWithTable;
use Illuminate\Support\Facades\Cache;

#[Title('Activity Logs')]
class ListActivityLogs extends Component implements HasForms, HasTable
{
    use InteractsWithTable;
    use InteractsWithForms;

    public function table(Table $table): Table
    {
        return $table
            ->query(ActivityLog::query())
            ->columns([
                TextColumn::make('user_name')
                    ->label('User')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('action')
                    ->label('Action')
                    ->sortable()
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'login' => 'success',
                        'logout' => 'warning',
                        'create' => 'info',
                        'update' => 'primary',
                        'delete', 'destroy' => 'danger',
                        'push' => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('module')
                    ->label('Module')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('description')
                    ->label('Description')
                    ->searchable()
                    ->limit(80)
                    ->tooltip(fn ($record) => $record->description),
                TextColumn::make('app_version')
                    ->label('App Version')
                    ->badge()
                    ->color('gray')
                    ->getStateUsing(fn ($record) => data_get($record->properties, 'app_version'))
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('ip_address')
                    ->label('IP')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Time')
                    ->dateTime('d M Y, h:i A')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('module')
                    ->options(fn () => Cache::remember(
                        'activity_logs_module_options',
                        now()->addHour(),
                        fn () => ActivityLog::distinct()->pluck('module', 'module')->toArray()
                    )),
                
                SelectFilter::make('action')
                    ->options([
                        'login' => 'Login',
                        'logout' => 'Logout',
                        'create' => 'Create',
                        'update' => 'Update',
                        'delete' => 'Delete',
                        'push' => 'Push (Oracle)',
                    ]),

                Filter::make('created_at')
                    ->form([
                        DatePicker::make('from')->label('From')->native(false),
                        DatePicker::make('until')->label('Until')->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['until'], fn ($q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->headerActions([
                Action::make('export')
                    ->label('Export Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action('exportToExcel')
                    ->color('success'),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public function exportToExcel()
    {
        $logs = $this->getFilteredTableQuery()->get();
        return Excel::download(new ActivityLogsExport($logs), 'activity_logs_' . now()->format('Y-m-d') . '.xlsx');
    }

    public function render()
    {
        return view('livewire.list-activity-logs');
    }
}
