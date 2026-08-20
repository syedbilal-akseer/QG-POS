<?php

namespace App\Livewire\Pos;

use App\Models\WMS\CycleCount;
use Livewire\Component;
use Filament\Tables\Table;
use Livewire\Attributes\Title;
use Illuminate\Contracts\View\View;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Actions\Action;

#[Title('Cycle Count History')]
class CycleCountHistory extends Component implements HasForms, HasTable
{
    use InteractsWithTable;
    use InteractsWithForms;

    public function table(Table $table): Table
    {
        return $table
            ->query(CycleCount::query()->with(['location', 'countedBy', 'reviewedBy', 'lines']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('location.location_code')
                    ->label('Location')
                    ->fontFamily('mono')
                    ->weight('bold'),
                TextColumn::make('countedBy.name')
                    ->label('Counted By'),
                TextColumn::make('created_at')
                    ->label('Counted At')
                    ->dateTime('d M Y, h:i A')
                    ->sortable(),
                TextColumn::make('lines_count')
                    ->label('Items')
                    ->counts('lines'),
                TextColumn::make('variance_count')
                    ->label('Variances')
                    ->getStateUsing(fn (CycleCount $record) => $record->lines->filter(fn ($l) => (float) $l->variance !== 0.0)->count())
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'success'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => $state === 'reviewed' ? 'success' : 'warning'),
                TextColumn::make('reviewedBy.name')
                    ->label('Reviewed By')
                    ->placeholder('—'),
            ])
            ->actions([
                Action::make('viewLines')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->modalHeading(fn (CycleCount $record) => "Cycle Count — {$record->location->location_code}")
                    ->modalContent(fn (CycleCount $record) => view('livewire.pos.partials.cycle-count-lines', ['record' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
                Action::make('review')
                    ->label('Mark Reviewed')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (CycleCount $record) => $record->status === 'open')
                    ->requiresConfirmation()
                    ->action(function (CycleCount $record) {
                        $record->update([
                            'status' => 'reviewed',
                            'reviewed_by' => auth()->id(),
                            'reviewed_at' => now(),
                        ]);
                        notify('Cycle count reviewed', "{$record->location->location_code} marked reviewed.", 'success');
                    }),
            ]);
    }

    public function render(): View
    {
        return view('livewire.pos.cycle-count-history');
    }
}
