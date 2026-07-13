<?php

namespace App\Livewire;

use App\Models\User;
use App\Models\UserLocationHistory;
use Livewire\Component;
use Filament\Tables\Table;
use Livewire\Attributes\Title;
use Filament\Tables\Actions\Action;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Columns\ImageColumn;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

#[Title('User Locations')]
class ListUserLocations extends Component implements HasForms, HasTable
{
    use InteractsWithTable;
    use InteractsWithForms;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                User::query()
                    ->where(function ($q) {
                        $q->whereNotNull('lat')
                          ->orWhereNotNull('lng')
                          ->orWhereNotNull('address')
                          ->orWhereHas('locationHistories');
                    })
                    ->withCount('locationHistories')
            )
            ->columns([
                ImageColumn::make('profile_photo')
                    ->label('Image')
                    ->circular()
                    ->defaultImageUrl(url('placeholder.png')),
                TextColumn::make('name')
                    ->label('User Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable()
                    ->visibleFrom('lg'),
                TextColumn::make('role')
                    ->label('Role')
                    ->formatStateUsing(fn($state) => ucwords(str_replace('-', ' ', $state ?: 'No Role')))
                    ->sortable(),
                TextColumn::make('address')
                    ->label('Current Address')
                    ->searchable()
                    ->wrap()
                    ->limit(50),
                TextColumn::make('lat')
                    ->label('Latitude')
                    ->sortable(),
                TextColumn::make('lng')
                    ->label('Longitude')
                    ->sortable(),
                TextColumn::make('location_histories_count')
                    ->label('History')
                    ->badge()
                    ->color('info')
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                // ── User picker ────────────────────────────────────────────────
                Filter::make('user')
                    ->form([
                        Select::make('user_id')
                            ->label('User')
                            ->placeholder('All users')
                            ->options(fn () => User::query()
                                ->whereNotNull('name')
                                ->orderBy('name')
                                ->limit(500)
                                ->pluck('name', 'id')
                                ->toArray())
                            ->searchable()
                            ->preload(),
                    ])
                    ->query(fn (Builder $query, array $data): Builder =>
                        $query->when($data['user_id'] ?? null, fn ($q, $id) => $q->where('id', $id))
                    )
                    ->indicateUsing(function (array $data): ?string {
                        if (!($data['user_id'] ?? null)) return null;
                        $name = User::whereKey($data['user_id'])->value('name');
                        return $name ? "User: {$name}" : null;
                    }),

                // ── Role ───────────────────────────────────────────────────────
                SelectFilter::make('role')
                    ->options([
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
                        'invoice-manager' => 'Invoice Manager',
                        'inventory-manager' => 'Inventory Manager',
                    ])
                    ->multiple(),

                // ── Address / city search ──────────────────────────────────────
                Filter::make('location')
                    ->form([
                        TextInput::make('address')
                            ->label('Address contains')
                            ->placeholder('e.g. Karachi, DHA, Main Boulevard'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder =>
                        $query->when($data['address'] ?? null, fn ($q, $term) => $q->where(function ($q) use ($term) {
                            $q->where('address', 'like', "%{$term}%")
                              ->orWhereHas('locationHistories', fn ($h) => $h->where('address', 'like', "%{$term}%"));
                        }))
                    )
                    ->indicateUsing(fn (array $data) => $data['address']
                        ? 'Address: "' . $data['address'] . '"'
                        : null),

                // ── Last updated date range ────────────────────────────────────
                Filter::make('last_updated')
                    ->label('Last Updated Between')
                    ->form([
                        DatePicker::make('from')->label('From')->native(false),
                        DatePicker::make('until')->label('Until')->native(false),
                    ])
                    ->query(fn (Builder $query, array $data): Builder =>
                        $query
                            ->when($data['from']  ?? null, fn ($q, $d) => $q->whereDate('updated_at', '>=', $d))
                            ->when($data['until'] ?? null, fn ($q, $d) => $q->whereDate('updated_at', '<=', $d))
                    )
                    ->indicateUsing(function (array $data): array {
                        $tags = [];
                        if ($data['from'])  $tags[] = 'Updated from ' . $data['from'];
                        if ($data['until']) $tags[] = 'until ' . $data['until'];
                        return $tags;
                    }),

                // ── History date range (filters users who logged ANY location in window) ──
                Filter::make('history_window')
                    ->label('Logged Location Between')
                    ->form([
                        DatePicker::make('from')->label('From')->native(false),
                        DatePicker::make('until')->label('Until')->native(false),
                    ])
                    ->query(fn (Builder $query, array $data): Builder =>
                        $query->when(
                            ($data['from'] ?? null) || ($data['until'] ?? null),
                            fn ($q) => $q->whereHas('locationHistories', function ($h) use ($data) {
                                if ($data['from'])  $h->whereDate('created_at', '>=', $data['from']);
                                if ($data['until']) $h->whereDate('created_at', '<=', $data['until']);
                            })
                        )
                    )
                    ->indicateUsing(function (array $data): array {
                        $tags = [];
                        if ($data['from'])  $tags[] = 'History from ' . $data['from'];
                        if ($data['until']) $tags[] = 'until ' . $data['until'];
                        return $tags;
                    }),

                // ── Has multiple distinct locations? ───────────────────────────
                TernaryFilter::make('has_multiple_locations')
                    ->label('Multiple Locations')
                    ->placeholder('Any')
                    ->trueLabel('Has multiple locations')
                    ->falseLabel('Only one location')
                    ->queries(
                        true:  fn (Builder $q)  => $q->has('locationHistories', '>=', 2),
                        false: fn (Builder $q)  => $q->has('locationHistories', '<', 2),
                        blank: fn (Builder $q)  => $q,
                    ),

                // ── Has GPS coordinates at all? ────────────────────────────────
                TernaryFilter::make('has_coordinates')
                    ->label('Has Current Coordinates')
                    ->placeholder('Any')
                    ->trueLabel('With lat/lng')
                    ->falseLabel('Without lat/lng')
                    ->queries(
                        true:  fn (Builder $q) => $q->whereNotNull('lat')->whereNotNull('lng'),
                        false: fn (Builder $q) => $q->whereNull('lat')->orWhereNull('lng'),
                        blank: fn (Builder $q) => $q,
                    ),
            ], layout: \Filament\Tables\Enums\FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(3)
            ->actions([
                Action::make('view_on_map')
                    ->label('Map')
                    ->icon('heroicon-m-map-pin')
                    ->color('info')
                    ->url(fn (User $record): string => "https://www.google.com/maps/search/?api=1&query={$record->lat},{$record->lng}")
                    ->openUrlInNewTab()
                    ->visible(fn (User $record): bool => !empty($record->lat) && !empty($record->lng)),

                Action::make('view_history')
                    ->label('History')
                    ->icon('heroicon-m-clock')
                    ->color('warning')
                    ->modalHeading(fn (User $record) => "Location History — {$record->name}")
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalWidth('5xl')
                    ->modalContent(function (User $record) {
                        $histories = UserLocationHistory::where('user_id', $record->id)
                            ->orderByDesc('created_at')
                            ->limit(200)
                            ->get();

                        return view('livewire.user-location-history-modal', [
                            'user'      => $record,
                            'histories' => $histories,
                        ]);
                    })
                    ->visible(fn (User $record): bool => $record->location_histories_count > 0),
            ])
            ->defaultSort('updated_at', 'desc');
    }

    public function render(): View
    {
        return view('livewire.list-user-locations');
    }
}
