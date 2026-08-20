<?php

namespace App\Livewire\Pos;

use App\Models\Order;
use App\Models\WMS\GatePass;
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
use Filament\Tables\Actions\CreateAction;
use Filament\Forms\Components\Select;

/**
 * Create + track gate passes. Anchor document is a Sales Order (Order model)
 * — see Scan & Sync Roadmap §Open questions #4 for what other movement
 * types (stock transfer, cross-dock) should anchor to; not built yet.
 * Security validates a pass separately, in GatePassValidate — that screen
 * only needs the qr_token, not this management table.
 */
#[Title('Gate Passes')]
class GatePassManager extends Component implements HasForms, HasTable
{
    use InteractsWithTable;
    use InteractsWithForms;

    public function table(Table $table): Table
    {
        return $table
            ->query(GatePass::query()->with(['order.customer', 'createdBy', 'validatedBy']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('gate_pass_number')
                    ->label('Gate Pass #')
                    ->fontFamily('mono')
                    ->weight('bold')
                    ->searchable(),
                TextColumn::make('order.order_number')
                    ->label('Order #')
                    ->placeholder('—'),
                TextColumn::make('order.customer.customer_name')
                    ->label('Customer')
                    ->placeholder('—')
                    ->limit(30),
                TextColumn::make('type')->badge(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'validated' => 'success',
                        'cancelled' => 'danger',
                        default => 'warning',
                    }),
                TextColumn::make('createdBy.name')->label('Created By'),
                TextColumn::make('created_at')->label('Created')->dateTime('d M Y, h:i A')->sortable(),
                TextColumn::make('validated_at')->label('Validated')->dateTime('d M Y, h:i A')->placeholder('—'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->form([
                        Select::make('order_id')
                            ->label('Order')
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search): array => Order::where('order_number', 'like', "%{$search}%")
                                ->with('customer:id,customer_name')
                                ->limit(30)
                                ->get()
                                ->mapWithKeys(fn ($o) => [$o->id => "{$o->order_number} — " . ($o->customer?->customer_name ?? 'Unknown')])
                                ->toArray())
                            ->getOptionLabelUsing(function ($value): ?string {
                                $o = Order::with('customer:id,customer_name')->find($value);
                                return $o ? "{$o->order_number} — " . ($o->customer?->customer_name ?? 'Unknown') : null;
                            })
                            ->required(),
                        Select::make('type')
                            ->options(['dispatch' => 'Dispatch', 'transfer' => 'Transfer'])
                            ->default('dispatch')
                            ->required(),
                    ])
                    ->using(function (array $data) {
                        return GatePass::create([
                            'gate_pass_number' => GatePass::generateNumber(),
                            'qr_token' => GatePass::generateToken(),
                            'order_id' => $data['order_id'],
                            'type' => $data['type'],
                            'status' => GatePass::STATUS_PENDING,
                            'created_by' => auth()->id(),
                        ]);
                    })
                    ->modalHeading('Create Gate Pass'),
            ])
            ->actions([
                Action::make('print')
                    ->label('Print')
                    ->icon('heroicon-o-printer')
                    ->url(fn (GatePass $record) => route('pos.gate-pass.print', $record))
                    ->openUrlInNewTab(),
                Action::make('cancel')
                    ->label('Cancel')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (GatePass $record) => $record->status === GatePass::STATUS_PENDING)
                    ->requiresConfirmation()
                    ->action(fn (GatePass $record) => $record->update(['status' => GatePass::STATUS_CANCELLED])),
            ]);
    }

    public function render(): View
    {
        return view('livewire.pos.gate-pass-manager');
    }
}
