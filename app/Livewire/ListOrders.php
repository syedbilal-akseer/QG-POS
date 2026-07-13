<?php

namespace App\Livewire;

use Carbon\Carbon;
use App\Models\Order;
use Livewire\Component;
use App\Models\OrderType;
use App\Models\Warehouse;
use Filament\Tables\Table;
use App\Traits\LogsActivity;
use App\Traits\NotifiesUsers;
use App\Enums\OrderStatusEnum;
use Livewire\Attributes\Title;
use App\Models\OracleOrderLine;
use App\Models\OracleOrderHeader;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Filament\Tables\Actions\Action;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Contracts\HasForms;
use App\Filament\Exports\OrderExporter;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Notifications\Notification;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Actions\ExportAction;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Illuminate\Database\Eloquent\Builder;
use Filament\Actions\Exports\Models\Export;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use App\Models\User;
use App\Models\Customer;
use App\Models\Transporter;


#[Title('Orders')]
class ListOrders extends Component implements HasForms, HasTable
{
    use InteractsWithTable;
    use InteractsWithForms;
    use NotifiesUsers;
    use LogsActivity;

    /**
     * Lazily-built map of customer ou_id → comma-separated supply-chain
     * user names ("pending with" candidates). Computed once on first
     * access via pendingWithFor() and reused for every row in the table
     * render so we don't issue a query per row.
     */
    private ?array $pendingWithMap = null;

    /**
     * Resolve and cache the per-OU supply-chain "pending with" list. The
     * candidates mirror OrderReceiptNotifier::orderCreated's recipient set:
     * supply-chain users (filtered by their UserOrganization oracle_ou_id),
     * scm-lhr users (hard-coded to Lahore OUs 108/109), and admin users
     * (every OU). Result is keyed by ou_id and shaped as a string of
     * comma-separated names ready to be rendered into the table cell.
     */
    public function pendingWithFor(?string $ouId): string
    {
        if ($ouId === null || $ouId === '') {
            return '—';
        }
        if ($this->pendingWithMap === null) {
            $this->pendingWithMap = $this->buildPendingWithMap();
        }
        $names = $this->pendingWithMap[(string) $ouId] ?? [];
        return empty($names) ? '—' : implode(', ', $names);
    }

    private function buildPendingWithMap(): array
    {
        $map = [];

        // 1) supply-chain users — keyed by each user_organization.oracle_ou_id
        $rows = \Illuminate\Support\Facades\DB::table('users')
            ->join('user_organizations', 'user_organizations.user_id', '=', 'users.id')
            ->where('users.role', 'supply-chain')
            ->whereNotNull('user_organizations.oracle_ou_id')
            ->select('users.name', 'user_organizations.oracle_ou_id')
            ->get();
        foreach ($rows as $row) {
            $map[(string) $row->oracle_ou_id][] = $row->name;
        }

        // 2) scm-lhr users — responsible for Lahore OUs only (108, 109)
        $scmLhrNames = \Illuminate\Support\Facades\DB::table('users')
            ->where('role', 'scm-lhr')
            ->orderBy('name')
            ->pluck('name')
            ->all();
        foreach (['108', '109'] as $ouId) {
            foreach ($scmLhrNames as $name) {
                $map[$ouId][] = $name;
            }
        }

        // De-dup and sort each OU's list
        foreach ($map as $ouId => $names) {
            $map[$ouId] = collect($names)->unique()->sort()->values()->all();
        }
        return $map;
    }

    public $order, $orderDetails;
    public $warehouses = [];
    public $orderItemWarehouses = [];

    /**
     * URL-bound query flag — when ?delayed=1 is on, the table is restricted
     * to orders that were pushed to Oracle BUT took > 24h from creation. Set
     * by the "View Report" link in the TAT stat card.
     */
    public ?string $delayed = null;

    protected $queryString = [
        'delayed' => ['except' => null],
    ];

    /**
     * `true` whenever the delayed-only TAT report should be applied. Reads
     * both the Livewire property AND the raw request — covers the edge case
     * where Livewire's queryString hydration runs AFTER the first table()
     * invocation (which was leaving the constraint un-applied and showing
     * pending orders in a report that's supposed to be pushed-only).
     */
    public function isDelayedReport(): bool
    {
        if ($this->delayed === '1' || $this->delayed === 1) return true;
        return (string) request()->query('delayed', '') === '1';
    }


    /**
     * Stats panel numbers. Always reflect the FULL role-scoped set + every
     * active Filament filter (salesperson, pushed-by, customer, etc.) but
     * intentionally IGNORE the delayed-report constraint. Reason: when the
     * report is on, the table is narrowed to delayed-only rows; if the
     * stats followed that constraint too, Pending would show 0 (no pending
     * order can be "late") and TAT% would show 0 of N (every row IS late
     * by definition). The headline stats should describe the wider context
     * the report sits in.
     */
    public function getStatsData(): array
    {
        // Build the base query WITHOUT the delayed-report constraint —
        // applies role-scoping/OU filtering only — then layer Filament's
        // filters on top via the trait helper. Result is "everything the
        // user can see + their picked filters", minus delayed.
        $base = $this->buildBaseScopedQuery();
        $this->applyFiltersToTableQuery($base);

        $total       = (clone $base)->count();
        $pending     = (clone $base)->where('order_status', \App\Enums\OrderStatusEnum::PENDING->value)->count();
        $pushed      = (clone $base)->whereNotNull('oracle_at')->count();

        // TAT = pushed within 24h of creation, as a % of all pushed orders
        // in the filtered set. MySQL's TIMESTAMPDIFF(HOUR, ...) handles the
        // wall-clock delta cleanly without timezone juggling.
        $pushedOnTime = (clone $base)
            ->whereNotNull('oracle_at')
            ->whereRaw('TIMESTAMPDIFF(HOUR, created_at, oracle_at) <= 24')
            ->count();
        $delayed = max(0, $pushed - $pushedOnTime); // pushed but past 24h
        $tatPct  = $pushed > 0 ? round(($pushedOnTime / $pushed) * 100, 1) : 0.0;

        return compact('total', 'pending', 'pushed', 'pushedOnTime', 'delayed', 'tatPct');
    }

    /**
     * Role-scoped base query used by BOTH the table (which then optionally
     * layers the delayed-report constraint on top) and the stats panel
     * (which deliberately doesn't). Mirrors what table() does for OU
     * scoping and view-* role widening.
     */
    protected function buildBaseScopedQuery(): Builder
    {
        $query = Order::query()->with([
            'customer:id,customer_id,customer_name,ou_id',
            'salesperson:id,name',
            'pushedBy:id,name',
        ]);

        $user = auth()->user();
        if (!$user->isAdmin() && !(method_exists($user, 'hasViewAll') && $user->hasViewAll())
            && ($user->canViewOrdersFromLocation() || $user->isScmLhr()
                || (method_exists($user, 'hasAnyViewRole') && $user->hasAnyViewRole()))) {
            $allowedOuIds = method_exists($user, 'getEffectiveOuIds')
                ? $user->getEffectiveOuIds()
                : $user->getAllowedOuIds();
            if (!empty($allowedOuIds)) {
                $query->whereHas('customer', fn ($cq) => $cq->whereIn('ou_id', $allowedOuIds));
            } else {
                $query->where('id', -1);
            }
        }

        return $query;
    }

    /**
     * Toggle the delayed-only TAT report on. Called by the View Report link
     * in the stats panel; updates the URL via the queryString binding so the
     * filter survives full-page reloads / copy-paste.
     */
    public function showTatReport(): void
    {
        $this->delayed = '1';
        $this->resetPage();
    }

    public function clearTatReport(): void
    {
        $this->delayed = null;
        $this->resetPage();
    }

    public function table(Table $table): Table
    {
        // Pass a CLOSURE (not a fixed Builder) so Filament re-evaluates the
        // base query on every render. With a Builder, the previously-built
        // instance was being reused across renders — the delayed-report
        // constraint set on a NEW render's $this wasn't taking effect, so
        // the Clear/banner UI said "filter active" while the rows below
        // were the unfiltered set. The closure closes over $this and the
        // constraint always reflects the current request.
        return $table
            ->query(function () {
                $query = $this->buildBaseScopedQuery();

                // Delayed-TAT report mode: restrict to orders that were
                // pushed but crossed the 24h SLA. Driven by ?delayed=1 set
                // by the "View Report" link on the TAT stat card.
                if ($this->isDelayedReport()) {
                    $query->whereNotNull('oracle_at')
                          ->whereRaw('TIMESTAMPDIFF(HOUR, created_at, oracle_at) > 24');
                }

                return $query;
            })
            
            ->columns([
                TextColumn::make('order_number')
                    ->label('Order Number')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('customer.customer_name')
                    ->label('Customer Name')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('salesperson.name')
                    ->label('Salesperson')
                    ->sortable()
                    ->searchable()
                    ->visible(fn() => auth()->user()->isAdmin() || auth()->user()->isSupplyChain() || auth()->user()->isScmLhr() || auth()->user()->isCmdKhi() || auth()->user()->isCmdLhr() || auth()->user()->isSalesHead()),
                TextColumn::make('order_status')
                    ->label('Order Status')
                    ->badge()
                    ->color(fn($state): string => OrderStatusEnum::color(
                        $state instanceof OrderStatusEnum ? $state->value : (string) $state
                    ))
                    ->formatStateUsing(fn($state) => $state instanceof OrderStatusEnum
                        ? $state->name()
                        : (OrderStatusEnum::tryFrom((string) $state)?->name() ?? $state)
                    )
                    ->sortable()
                    ->searchable(),

                // "Pending With" — comma-separated supply-chain user names
                // responsible for this order's OU. Empty for any non-pending
                // status because once the order is synced/cancelled there's
                // nobody it's "pending with" any more.
                TextColumn::make('pending_with')
                    ->label('Pending With')
                    ->state(fn (Order $record): string => $record->order_status === OrderStatusEnum::PENDING
                        ? $this->pendingWithFor(optional($record->customer)->ou_id)
                        : '—')
                    ->wrap()
                    ->toggleable()
                    ->visible(fn () => auth()->user()->isAdmin()
                        || auth()->user()->isSupplyChain() || auth()->user()->isScmLhr()
                        || auth()->user()->isCmdKhi() || auth()->user()->isCmdLhr()
                        || auth()->user()->isSalesHead()),

                // "Pending Since" — how long the order has been waiting to be
                // pushed. Carbon::diffForHumans gives "5 hours ago" / "2 days
                // ago" / etc. Shown only for pending orders for the same
                // reason as above.
                TextColumn::make('pending_since')
                    ->label('Pending Since')
                    ->state(fn (Order $record): string => $record->order_status === OrderStatusEnum::PENDING
                        ? optional($record->created_at)->diffForHumans() ?? '—'
                        : '—')
                    ->tooltip(fn (Order $record): ?string => $record->order_status === OrderStatusEnum::PENDING
                        ? optional($record->created_at)->format('M d, Y, g:i a')
                        : null)
                    ->toggleable()
                    ->visible(fn () => auth()->user()->isAdmin()
                        || auth()->user()->isSupplyChain() || auth()->user()->isScmLhr()
                        || auth()->user()->isCmdKhi() || auth()->user()->isCmdLhr()
                        || auth()->user()->isSalesHead()),

                TextColumn::make('pushedBy.name')
                    ->label('Pushed to Oracle By')
                    ->sortable()
                    ->searchable()
                    ->default('N/A')
                    ->visible(fn() => auth()->user()->isAdmin() || auth()->user()->isSupplyChain() || auth()->user()->isScmLhr() || auth()->user()->isCmdKhi() || auth()->user()->isCmdLhr() || auth()->user()->isSalesHead()),

                // Delay column — only visible when the TAT delayed-only
                // report is active so the table stays uncluttered for the
                // default view. Computed in PHP from created_at/oracle_at
                // (both Carbon).
                TextColumn::make('tat_delay')
                    ->label('Delay')
                    ->state(function (Order $record) {
                        if (!$record->oracle_at || !$record->created_at) return '—';
                        // Carbon's diffInHours returns a float in newer
                        // versions ("50.2161111111111h" in the UI). Round
                        // down to a whole hour and render "Nd Hh" once we
                        // cross a day so 196h reads as "8d 4h".
                        $hours = (int) floor($record->created_at->diffInHours($record->oracle_at));
                        if ($hours < 24) {
                            return $hours . 'h';
                        }
                        $days = intdiv($hours, 24);
                        $rem  = $hours % 24;
                        return $rem === 0 ? "{$days}d" : "{$days}d {$rem}h";
                    })
                    ->badge()
                    ->color(fn (Order $record): string => $record->oracle_at && $record->created_at
                        && (int) floor($record->created_at->diffInHours($record->oracle_at)) > 24 ? 'danger' : 'success')
                    ->visible(fn () => $this->isDelayedReport()),
                TextColumn::make('created_at')
                    ->visibleFrom('md')
                    ->label('Order Date')
                    ->dateTime('F j, Y, g:i a')
                    ->sortable(),
            ])
            ->filters([
                // ── Status ─────────────────────────────────────────────────────
                SelectFilter::make('order_status')
                    ->label('Status')
                    ->options(OrderStatusEnum::asArray())
                    ->attribute('order_status')
                    ->multiple()
                    ->placeholder('Any status'),

                // ── Customer ───────────────────────────────────────────────────
                // Filament's SelectFilter applies the WHERE clause natively
                // against the named attribute, removing the form-key / column
                // indirection that broke the earlier custom Filter::make
                // implementations (the filter would record state and show an
                // indicator chip but never actually constrain the query).
                //
                // The value here is customers.customer_id (Oracle id, VARCHAR)
                // because Order::customer() joins on
                //   orders.customer_id === customers.customer_id
                // — comparing to customers.id (local PK) would never match.
                SelectFilter::make('customer_id')
                    ->label('Customer')
                    ->placeholder('All customers')
                    ->attribute('customer_id')
                    ->options(fn () => Customer::query()
                        ->whereNotNull('customer_name')
                        ->whereNotNull('customer_id')
                        ->orderBy('customer_name')
                        ->limit(2000)
                        ->pluck('customer_name', 'customer_id')
                        ->toArray())
                    ->searchable()
                    ->preload(),

                // ── Salesperson (placed the order) ─────────────────────────────
                SelectFilter::make('user_id')
                    ->label('Salesperson')
                    ->placeholder('All salespeople')
                    ->attribute('user_id')
                    ->options(fn () => Cache::remember(
                        'list_orders_salesperson_filter_options',
                        now()->addMinutes(30),
                        fn () => User::query()
                            ->whereIn('id', Order::query()->whereNotNull('user_id')->distinct()->pluck('user_id'))
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->toArray()
                    ))
                    ->searchable()
                    ->preload(),

                // ── Pushed By (whoever pushed it to Oracle) ────────────────────
                SelectFilter::make('pushed_by')
                    ->label('Pushed By')
                    ->placeholder('Anyone')
                    ->attribute('pushed_by')
                    ->options(fn () => Cache::remember(
                        'list_orders_pushed_by_filter_options',
                        now()->addMinutes(30),
                        fn () => User::query()
                            ->whereIn('id', Order::query()->whereNotNull('pushed_by')->distinct()->pluck('pushed_by'))
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->toArray()
                    ))
                    ->searchable()
                    ->preload(),

                // ── Pushed to Oracle? ──────────────────────────────────────────
                TernaryFilter::make('oracle_at')
                    ->label('Pushed to Oracle')
                    ->placeholder('Any')
                    ->trueLabel('Pushed to Oracle')
                    ->falseLabel('Not yet pushed')
                    ->queries(
                        true:  fn (Builder $q) => $q->whereNotNull('oracle_at'),
                        false: fn (Builder $q) => $q->whereNull('oracle_at'),
                        blank: fn (Builder $q) => $q,
                    ),

                // ── Transporter ────────────────────────────────────────────────
                SelectFilter::make('transporter_id')
                    ->label('Transporter')
                    ->placeholder('Any transporter')
                    ->options(fn () => Transporter::query()
                        ->orderBy('description')
                        ->pluck('description', 'id')
                        ->toArray())
                    ->searchable(),

                // ── Order number (exact / partial match) ───────────────────────
                Filter::make('order_number')
                    ->form([
                        TextInput::make('order_number')
                            ->label('Order Number')
                            ->placeholder('e.g. 20260501'),
                    ])
                    ->query(fn (Builder $q, array $data): Builder =>
                        $q->when($data['order_number'] ?? null, fn ($q, $n) => $q->where('order_number', 'like', "%{$n}%"))
                    )
                    ->indicateUsing(fn (array $data) =>
                        $data['order_number'] ? 'Order #' . $data['order_number'] : null
                    ),

                // ── Order date range ───────────────────────────────────────────
                Filter::make('created_at')
                    ->label('Order Date Between')
                    ->form([
                        DatePicker::make('created_from')->label('Order Date From')->native(false),
                        DatePicker::make('created_until')->label('Order Date Until')->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn(Builder $q, $date): Builder => $q->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn(Builder $q, $date): Builder => $q->whereDate('created_at', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if (!$data['created_from'] && !$data['created_until']) return null;
                        $from  = $data['created_from']  ? Carbon::parse($data['created_from'])->toFormattedDateString()  : 'N/A';
                        $until = $data['created_until'] ? Carbon::parse($data['created_until'])->toFormattedDateString() : 'N/A';
                        return 'Order Date from ' . $from . ' to ' . $until;
                    }),

                // ── Pushed date range (when entered to Oracle) ─────────────────
                Filter::make('oracle_pushed_range')
                    ->label('Pushed Date Between')
                    ->form([
                        DatePicker::make('pushed_from')->label('Pushed From')->native(false),
                        DatePicker::make('pushed_until')->label('Pushed Until')->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['pushed_from'],
                                fn(Builder $q, $date): Builder => $q->whereDate('oracle_at', '>=', $date),
                            )
                            ->when(
                                $data['pushed_until'],
                                fn(Builder $q, $date): Builder => $q->whereDate('oracle_at', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if (!$data['pushed_from'] && !$data['pushed_until']) return null;
                        $from  = $data['pushed_from']  ? Carbon::parse($data['pushed_from'])->toFormattedDateString()  : 'N/A';
                        $until = $data['pushed_until'] ? Carbon::parse($data['pushed_until'])->toFormattedDateString() : 'N/A';
                        return 'Pushed from ' . $from . ' to ' . $until;
                    }),
            ], layout: \Filament\Tables\Enums\FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(3)
            ->filtersTriggerAction(
                fn(Action $action) => $action
                    ->button()
                    ->label('Filter'),
            )
            ->headerActions([
                ExportAction::make()
                    ->exporter(OrderExporter::class)
                    ->fileName(fn(Export $export): string => "orders-{$export->getKey()}")
                    ->formats([
                        ExportFormat::Xlsx,
                        ExportFormat::Csv,
                    ])
            ])
            ->actions([
                // View opens a dedicated /app/orders/{id} page in a new tab,
                // mirroring the receipts pattern (Admin\ReceiptController::show
                // + admin.receipts.show). Each order has its own URL, fully
                // shareable, no modal.
                Action::make('view')
                    ->button()
                    ->icon(fn(Order $record) => $record->oracle_at
                        ? 'heroicon-m-check-circle'
                        : 'heroicon-m-eye')
                    ->label(fn(Order $record) => $record->oracle_at
                        ? 'View (Pushed to Oracle)'
                        : 'View')
                    ->color(fn(Order $record) => $record->oracle_at ? 'success' : 'primary')
                    ->url(fn(Order $record) => route('orders.show', $record))
                    ->openUrlInNewTab(),

                Action::make('delete')
                    ->button()
                    ->icon('heroicon-m-trash')
                    ->label('Delete')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Delete Order')
                    ->modalDescription('Are you sure you want to delete this order? This action cannot be undone.')
                    ->modalSubmitActionLabel('Yes, Delete')
                    ->action(fn(Order $record) => $this->deleteOrder($record))
                    // Visibility:
                    //   • admin only — no other role can see this button.
                    //   • hide when order_status = cancelled — preserve the
                    //     cancelled record for audit instead of letting it be
                    //     wiped.
                    //   • hide when oracle_at is set — the server already
                    //     refuses to delete already-pushed orders, so removing
                    //     the button avoids a confusing "click → error" UX.
                    ->visible(fn(Order $record) => auth()->user()->isAdmin()
                        && $record->order_status !== OrderStatusEnum::CANCELED
                        && $record->oracle_at === null),
            ])
            ->bulkActions([
                // Add any bulk actions if needed
            ])
            ->deferLoading()
            ->defaultSort('created_at', 'desc');
    }

    protected function applySearchToTableQuery(Builder $query): Builder
    {
        $this->applyColumnSearchesToTableQuery($query);

        if (filled($search = $this->getTableSearch())) {
            $searchTerm = '%' . $search . '%';

            $query->where(function ($query) use ($searchTerm) {
                // Search in Order fields
                $query->where('order_number', 'like', $searchTerm)
                    ->orWhere('order_status', 'like', $searchTerm)

                    // Search in related Customer fields
                    ->orWhereHas('customer', function ($q) use ($searchTerm) {
                        $q->where('customer_name', 'like', $searchTerm)
                            ->orWhere('customer_number', 'like', $searchTerm)
                            ->orWhere('customer_id', 'like', $searchTerm)
                            ->orWhere('city', 'like', $searchTerm)
                            ->orWhere('area', 'like', $searchTerm)
                            ->orWhere('contact_number', 'like', $searchTerm)
                            ->orWhere('email_address', 'like', $searchTerm);
                    })

                    // Search in Salesperson
                    ->orWhereHas('salesperson', function ($q) use ($searchTerm) {
                        $q->where('name', 'like', $searchTerm);
                    })

                    // Search in related Warehouse fields
                    ->orWhereHas('orderItems', function ($q) use ($searchTerm) {
                        $q->whereHas('warehouse', function ($wq) use ($searchTerm) {
                            $wq->where('organization_code', 'like', $searchTerm)
                               ->orWhere('organization_id', 'like', $searchTerm);
                        });
                    })

                    // Search in related OrderItem fields
                    ->orWhereHas('orderItems', function ($q) use ($searchTerm) {
                        $q->whereHas('item', function ($q) use ($searchTerm) {
                            $q->where('item_description', 'like', $searchTerm)
                                ->orWhere('item_code', 'like', $searchTerm);
                        });
                    });
            });
        }

        return $query;
    }

    public function openDetailModal(Order $order)
    {
        // Load the order along with its order items, item details, customer,
        // salesperson, transporter, and (if cancelled) the user who cancelled
        // it so the modal can show the cancellation context inline.
        $this->order = $order->load(['orderItems.item', 'customer', 'salesperson', 'transporter', 'cancelledBy']);

        // Fetch warehouses based on the customer's ou_id, fallback to all if none match
        $warehouses = Warehouse::where('ou', $this->order->customer->ou_id)->get();
        if ($warehouses->isEmpty()) {
            $warehouses = Warehouse::all();
        }
        
        // Add default "Select Warehouse" option
        $warehouseOptions = collect([['value' => '', 'label' => 'Select Warehouse']]);
        
        // Transform the warehouse data into the format expected by the select component (value and label)
        $warehouseData = $warehouses->map(function ($warehouse) {
            return [
                'value' => $warehouse->organization_id,
                'label' => $warehouse->organization_code . ' (' . $warehouse->organization_id . ')',
            ];
        });
        
        $this->warehouses = $warehouseOptions->merge($warehouseData)->values()->toArray();

        // Initialize the orderItemWarehouses array with existing warehouse IDs or null
        $this->orderItemWarehouses = $this->order->orderItems->mapWithKeys(function ($item, $index) {
            return [$index => $item->warehouse_id ?? null];
        })->toArray();

        // Dispatch the event to open the order detail modal
        $this->dispatch('open-modal', 'order_detail');
    }

    public function openSyncDetailsModal(Order $order)
    {
        // Load order items along with the sync history, item details, customer, and salesperson
        $this->orderDetails = $order->load(['orderItems.syncHistory', 'orderItems.item', 'customer', 'salesperson']);
        // Dispatch to open the modal
        $this->dispatch('open-modal', 'order_sync_details');
    }

    public function closeDetailModal()
    {
        $this->reset('order');
        $this->dispatch('close');
    }

    public function enterOrderToOracle()
    {
        // Sales-head users have view-only access — block the action server-side
        // even though the UI hides the button.
        if (auth()->user()->isSalesHead()) {
            $this->notifyUser('Access Denied', 'View-only role — cannot push to Oracle.', 'danger');
            return;
        }

        // Validate warehouses are selected
        $this->validate([
            'orderItemWarehouses.*' => 'required',
        ], [
            'orderItemWarehouses.*.required' => 'Warehouse must be selected for order item.',
        ]);

        // Pre-flight idempotency check against the in-memory order. Catches
        // the common case where the UI didn't refresh after a successful
        // push in another tab — saves a DB round-trip and lock acquisition.
        if ($this->order && $this->order->oracle_at !== null) {
            $this->notifyUser(
                'Already Pushed',
                'This order was already entered to Oracle on ' . $this->order->oracle_at->format('d M Y H:i') . '.',
                'warning'
            );
            $this->reset('order');
            $this->dispatch('close');
            return;
        }

        $orderId = $this->order->id;

        try {
            // Outer MySQL transaction with row-level lock on the local order.
            // Any concurrent push attempt on the same order ID blocks here until
            // this transaction commits/rolls back — at which point the second
            // request reads the updated `oracle_at` and aborts cleanly. This is
            // the cross-tab / cross-browser double-push defence.
            $order = \DB::transaction(function () use ($orderId) {
                $lockedOrder = Order::lockForUpdate()->find($orderId);

                if (!$lockedOrder) {
                    throw new \Exception('Order no longer exists.');
                }

                if ($lockedOrder->oracle_at !== null) {
                    // Race condition hit: another request just finished pushing.
                    // Throw a typed exception we can recognise in the outer catch
                    // and surface as a warning rather than a generic error.
                    throw new \RuntimeException('ALREADY_PUSHED:' . $lockedOrder->oracle_at->toIso8601String());
                }

                // Re-load relations on the locked instance so the rest of the
                // flow (which reads $this->order->customer etc.) sees the same
                // data the lock acquired.
                $lockedOrder->load(['customer', 'orderItems.item']);
                $this->order = $lockedOrder;

                return DB::connection('oracle')->transaction(function () {
                // Log customer data being used for Oracle sync
                \Log::info('Starting Oracle Order Sync', [
                    'order_number' => $this->order->order_number,
                    'order_id' => $this->order->id,
                    'customer' => [
                        'customer_id' => $this->order->customer->customer_id,
                        'customer_name' => $this->order->customer->customer_name,
                        'customer_number' => $this->order->customer->customer_number,
                        'ou_id' => $this->order->customer->ou_id,
                        'ou_name' => $this->order->customer->ou_name,
                        'price_list_id' => $this->order->customer->price_list_id,
                        'price_list_name' => $this->order->customer->price_list_name,
                        'customer_site_id' => $this->order->customer->customer_site_id,
                    ],
                    'total_items' => $this->order->orderItems->count(),
                    'total_amount' => $this->order->total_amount,
                ]);

                // Validate customer has required fields
                if (!$this->order->customer->price_list_id) {
                    throw new \Exception("Customer {$this->order->customer->customer_name} is missing price_list_id. Please sync customer data from Oracle.");
                }

                // Fetch the relevant order type and line type based on ou_id
                $oracleOrderType = OrderType::where('org_id', $this->order->customer->ou_id)->first();

                if (!$oracleOrderType) {
                    throw new \Exception("Order type or line type not found for org_id: {$this->order->customer->ou_id}");
                }

                // Prepare Oracle Order Header data
                $orderHeaderData = [
                    'order_source_id' => 1001,
                    'orig_sys_document_ref' => $this->order->order_number,
                    'org_id' => $this->order->customer->ou_id, // Customer OU ID
                    'sold_from_org_id' => $this->order->customer->ou_id, // Customer OU ID
                    'ordered_date' => Carbon::now(),
                    'order_type_id' => $oracleOrderType->order_type_id,
                    'sold_to_org_id' => $this->order->customer->customer_id,
                    'price_list_id' => $this->order->customer->price_list_id, // Add price_list_id to header
                    'payment_term_id' => 1004,
                    'operation_code' => 'INSERT',
                    'created_by' => 0,
                    'creation_date' => Carbon::now(),
                    'last_updated_by' => 0,
                    'last_update_date' => Carbon::now(),
                    'customer_po_number' => $this->order->order_number,
                    'ship_to_org_id' => $this->order->customer->customer_site_id,
                    'BOOKED_FLAG' => 'N',
                ];

                // Log the order header data being sent to Oracle
                \Log::info('Oracle Order Header Data', [
                    'order_number' => $this->order->order_number,
                    'customer_id' => $this->order->customer->customer_id,
                    'customer_name' => $this->order->customer->customer_name,
                    'header_data' => $orderHeaderData
                ]);

                // Create Oracle Order Header
                $oracleOrderHeader = OracleOrderHeader::create($orderHeaderData);

                // Create Oracle Order Lines
                foreach ($this->order->orderItems as $index => $orderItem) {
                    $selectedWarehouseId = $this->orderItemWarehouses[$index] ?? null;

                    // Update the local order item with the selected warehouse
                    $orderItem->update(['warehouse_id' => $selectedWarehouseId]);

                    // unit_list_price and unit_selling_price are taken DIRECTLY
                    // from the order line's stored snapshot — whatever the
                    // user confirmed at order-place time is what goes to
                    // Oracle. We previously tried refetching the live rate
                    // from item_prices and recomputing, but that silently
                    // overrode the user-confirmed price whenever the master
                    // price list shifted between placement and push (e.g.
                    // 17,700 → 17,697 → wrong billing). Source of truth =
                    // the stored row.
                    //
                    // unit_selling_price derivation:
                    //   • cap_price set  → use cap_price directly (manual override).
                    //   • otherwise      → sub_total / quantity (already
                    //                      includes the line discount the
                    //                      user agreed to). Capped between 0
                    //                      and the stored unit_list_price as
                    //                      a sanity guard.
                    $unitListPrice = round((float) $orderItem->price, 2);

                    if ($orderItem->cap_price !== null) {
                        $unitSellingPrice = round((float) $orderItem->cap_price, 2);
                    } else {
                        $unitSellingPrice = $orderItem->quantity > 0
                            ? round((float) $orderItem->sub_total / $orderItem->quantity, 2)
                            : $unitListPrice;
                        $unitSellingPrice = (float) max(0, min($unitListPrice, $unitSellingPrice));
                    }

                    // calculate_price_flag semantics in OE_LINES_IFACE_ALL:
                    //   'P' (Partial) — Oracle's pricing engine keeps the list
                    //                   price but RE-RUNS modifiers/adjustments.
                    //                   With no matching OE_PRICE_ADJS_IFACE row
                    //                   the engine sees no discount and resets
                    //                   our unit_selling_price back to the list
                    //                   price — that's why a 16,635 line was
                    //                   landing in Oracle at 17,700.
                    //   'N' (Freeze)  — Oracle accepts our unit_selling_price
                    //                   as-is. No modifier re-eval.
                    //
                    // So:
                    //   • Lines WITH an override (cap_price set OR an actual
                    //     discount on the order_item) → 'N' so the override
                    //     is honoured.
                    //   • Lines without an override → 'P' so Oracle can still
                    //     auto-apply standing modifiers / tax.
                    $hasOverride = $orderItem->cap_price !== null
                        || (float) $orderItem->discount > 0
                        || $unitSellingPrice < $unitListPrice;
                    $calculatePriceFlag = $hasOverride ? 'N' : 'P';

                    // Prepare Oracle Order Line data
                    $orderLineData = [
                        'order_source_id' => 1001,
                        // 'order_source' => "POS",
                        'orig_sys_document_ref' => $this->order->order_number,
                        'orig_sys_line_ref' => "{$this->order->order_number}-" . ($index + 1),
                        'line_number' => ($index + 1),
                        'inventory_item_id' => $orderItem->inventory_item_id,
                        'ordered_quantity' => $orderItem->quantity,
                        'unit_selling_price' => $unitSellingPrice,
                        'unit_list_price' => $unitListPrice, // Original price or cap price
                        'calculate_price_flag' => $calculatePriceFlag,
                        'ship_from_org_id' => $selectedWarehouseId,
                        'org_id' => $this->order->customer->ou_id, // Customer OU ID
                        'price_list_id' => $this->order->customer->price_list_id,
                        'payment_term_id' => 1004,
                        'created_by' => 0,
                        'creation_date' => Carbon::now(),
                        'last_updated_by' => 0,
                        'last_update_date' => Carbon::now(),
                        'line_type_id' => $oracleOrderType->line_type_id,
                        'order_quantity_uom' => $orderItem->uom,
                        'operation_code' => 'INSERT',
                    ];

                    // Log the order line data being sent to Oracle.
                    \Log::info('Oracle Order Line Data', [
                        'order_number'         => $this->order->order_number,
                        'line_number'          => ($index + 1),
                        'item_code'            => $orderItem->item->item_code ?? 'N/A',
                        'item_description'     => $orderItem->item->item_description ?? 'N/A',
                        'price_list_id'        => $this->order->customer->price_list_id,
                        'price_list_name'      => $this->order->customer->price_list_name,
                        'quantity'             => $orderItem->quantity,
                        'unit_list_price'      => $unitListPrice,
                        'cap_price'            => $orderItem->cap_price,
                        'line_sub_total'       => $orderItem->sub_total,
                        'line_discount'        => $orderItem->discount,
                        'unit_selling_price'   => $unitSellingPrice,
                        'calculate_price_flag' => $calculatePriceFlag,
                        'price_override_path'  => $hasOverride ? 'frozen (N)' : 'auto-priced (P)',
                        'line_data'            => $orderLineData,
                    ]);

                    OracleOrderLine::create($orderLineData);
                }

                // Update the order's oracle_at timestamp to mark it as successfully entered into Oracle.
                // This update happens while the MySQL row lock is still held — so any concurrent
                // request waiting on the lock will see the new oracle_at as soon as the transaction
                // commits and bail out with ALREADY_PUSHED.
                $this->order->update([
                    'oracle_at' => now(),
                    'order_status' => OrderStatusEnum::ENTERED,
                    'pushed_by' => auth()->id()
                ]);

                return $oracleOrderHeader;
                });
            });

            if ($order) {
                \Log::info('Oracle Order Sync Completed Successfully', [
                    'order_number' => $this->order->order_number,
                    'order_id' => $this->order->id,
                    'oracle_header_id' => $order->header_id ?? null,
                    'pushed_by' => auth()->id(),
                ]);

                // Push notification to the salesperson who placed the order
                app(\App\Services\OrderReceiptNotifier::class)->orderPushedToOracle($this->order);

                $this->reset('order');
                $this->dispatch('close');
                $this->notifyUser('Order Entered', 'Order entered to Oracle successfully.');
            } else {
                throw new \Exception('Order insertion failed.');
            }
        } catch (\RuntimeException $e) {
            // ALREADY_PUSHED race-condition trip. Don't log as an error and
            // don't show a scary "An error occurred" toast — this is the
            // expected behaviour when two tabs/browsers race.
            if (str_starts_with($e->getMessage(), 'ALREADY_PUSHED')) {
                $when = trim(substr($e->getMessage(), strlen('ALREADY_PUSHED:'))) ?: null;
                \Log::info('Order push to Oracle skipped — already pushed by a concurrent request', [
                    'order_id'       => $this->order->id ?? null,
                    'order_number'   => $this->order->order_number ?? null,
                    'already_at'     => $when,
                    'attempted_by'   => auth()->id(),
                ]);
                $msg = $when
                    ? 'This order was already pushed to Oracle on ' . \Carbon\Carbon::parse($when)->format('d M Y H:i') . '.'
                    : 'This order has already been pushed to Oracle.';
                $this->notifyUser('Already Pushed', $msg, 'warning');
                $this->reset('order');
                $this->dispatch('close');
                return;
            }
            // Other RuntimeExceptions still get the generic treatment.
            \Log::error('Order Oracle Sync Error: ' . $e->getMessage(), [
                'order_id' => $this->order->id ?? null,
                'order_number' => $this->order->order_number ?? null,
                'trace' => $e->getTraceAsString(),
            ]);
            $this->notifyUser('Error', 'An error occurred: ' . $e->getMessage(), 'danger');
        } catch (\Exception $e) {
            \Log::error('Order Oracle Sync Error: ' . $e->getMessage(), [
                'order_id' => $this->order->id ?? null,
                'order_number' => $this->order->order_number ?? null,
                'trace' => $e->getTraceAsString()
            ]);
            $this->notifyUser('Error', 'An error occurred: ' . $e->getMessage(), 'danger');
        }
        // $this->reset('order');
        // $this->notifyUser('Feature Limitation', 'This feature will not work on cPanel.', 'danger');
        // $this->dispatch('close');
    }

    public function deleteOrder(Order $order)
    {
        try {
            // Check if user is admin
            if (!auth()->user()->isAdmin()) {
                $this->notifyUser('Unauthorized', 'You do not have permission to delete orders.', 'danger');
                return;
            }

            // Check if order has been synced to Oracle
            if ($order->oracle_at !== null) {
                $this->notifyUser('Cannot Delete', 'This order has already been sent to Oracle and cannot be deleted.', 'warning');
                return;
            }

            $orderNumber = $order->order_number;

            // Delete the order (order items will be cascade deleted if FK is set up)
            $order->delete();

            // Log the deletion
            \Log::info('Order Deleted', [
                'order_number' => $orderNumber,
                'order_id' => $order->id,
                'deleted_by' => auth()->id(),
                'deleted_by_name' => auth()->user()->name,
            ]);

            $this->notifyUser('Order Deleted', "Order #{$orderNumber} has been deleted successfully.");
        } catch (\Exception $e) {
            \Log::error('Order Deletion Error: ' . $e->getMessage(), [
                'order_id' => $order->id ?? null,
                'order_number' => $order->order_number ?? null,
                'trace' => $e->getTraceAsString()
            ]);
            $this->notifyUser('Error', 'An error occurred while deleting the order: ' . $e->getMessage(), 'danger');
        }
    }

    public function render()
    {
        $pageTitle = "Orders List";
        return view('livewire.list-orders', compact('pageTitle'));
    }
}
