<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerReceipt;
use App\Models\Order;
use App\Models\SalespersonTarget;
use App\Models\User;
use App\Traits\OracleNlsSession;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Mobile dashboard API.
 *
 *   GET /api/dashboard
 *      ?period=month|year   (default: month)
 *      ?year=2026           (default: current)
 *      ?month=5             (default: current; ignored when period=year)
 *      ?user_id=42          (admin only — view another salesperson)
 *
 * Returns metrics for ONE salesperson (the caller, or the requested user
 * when an admin queries someone else). Admins also get an aggregate roll-up.
 */
class DashboardController extends Controller
{
    use OracleNlsSession;

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'period'    => 'nullable|in:month,year',
            'year'      => 'nullable|integer|min:2020|max:2099',
            'month'     => 'nullable|integer|min:1|max:12',
            'user_id'   => 'nullable|integer|exists:users,id',
            // Opt-in monthly breakdown for the requested year — adds a
            // `months` array (12 rows) to the salesperson block. Costs one
            // extra grouped query per metric; only compute when asked.
            'breakdown' => 'nullable|in:months,none',
        ]);

        $authUser = Auth::user();

        // Decide whose stats to fetch
        $targetUser = $authUser;
        if ($request->filled('user_id') && $authUser->isAdmin()) {
            $targetUser = User::find($request->user_id);
        }

        $period = $request->input('period', 'month');
        $year   = (int) $request->input('year', now()->year);
        $month  = (int) $request->input('month', now()->month);

        [$start, $end] = $this->resolvePeriodRange($period, $year, $month);

        // For admins: the personal "salesperson" card is meaningless (admins
        // don't have a target row in TARGET.xlsx and don't make individual
        // sales). Only build it when the caller is actually a salesperson, or
        // when admin explicitly scoped to another user via ?user_id=.
        $isAdminViewingSelf = $authUser->isAdmin() && ! $request->filled('user_id');

        // Monthly breakdown always ships in the salesperson block now — the
        // mobile chart is a first-class part of the dashboard and the extra
        // cost is 3 grouped queries. `?breakdown=none` opts out for callers
        // that don't render the chart.
        $withMonths = $request->input('breakdown') !== 'none';

        $salespersonStats = $isAdminViewingSelf
            ? null
            : $this->buildSalespersonStats($targetUser, $period, $year, $month, $start, $end, $withMonths);

        // Admins also get the company-wide roll-up + per-salesperson breakdown.
        $aggregate = $authUser->isAdmin()
            ? $this->buildAggregateStats($period, $year, $month, $start, $end)
            : null;

        $salespersonsBreakdown = $authUser->isAdmin()
            ? $this->buildSalespersonsBreakdown($period, $year, $month, $start, $end)
            : null;

        return response()->json([
            'success' => true,
            'status'  => 200,
            'message' => 'Dashboard data retrieved.',
            'data'    => [
                'period'       => $period,
                'year'         => $year,
                'month'        => $month,
                'period_start' => $start->toDateString(),
                'period_end'   => $end->toDateString(),
                'salesperson'  => $salespersonStats,
                'aggregate'    => $aggregate,             // admin-only
                'salespersons' => $salespersonsBreakdown, // admin-only — per-salesperson target vs actual
            ],
        ], 200);
    }

    /**
     * For admins: list every salesperson that has a target row (or a sale)
     * in the period, with target vs actual. Helps admin see who's behind
     * without picking one user at a time.
     */
    protected function buildSalespersonsBreakdown(string $period, int $year, int $month, Carbon $start, Carbon $end): array
    {
        // Pull all target rows for the period.
        $targetQuery = SalespersonTarget::where('year', $year);
        if ($period !== 'year') {
            $targetQuery->where('month', $month);
        }

        $targetRows = $targetQuery->get();

        if ($targetRows->isEmpty()) {
            return [];
        }

        // Group target rows by best-known identity (user_id if linked, else primary_name).
        $grouped = $targetRows->groupBy(fn($t) => $t->user_id ?: ('name:' . $t->primary_name));

        // ── Batch-resolve users in ONE query instead of User::find() per salesperson. ──
        // Previously this method ran ~50 User::find + ~50 Customer + ~50 CustomerReceipt
        // queries per dashboard load. The new flow makes ~4 queries total and computes
        // the OR-match on a single pre-loaded receipt set in PHP. Behavior is identical:
        // the same receipts contribute to the same salesperson totals.
        $userIdsNeeded = [];
        $namesNeeded   = [];
        foreach ($grouped->keys() as $key) {
            if (str_starts_with((string) $key, 'name:')) {
                $namesNeeded[] = substr((string) $key, 5);
            } else {
                $userIdsNeeded[] = $key;
            }
        }

        // orderBy('id') makes "first match wins" deterministic — matches the
        // original User::where(name)->orWhere(oracle_user_name)->first() semantics.
        $users = User::query()
            ->where(function ($q) use ($userIdsNeeded, $namesNeeded) {
                if (! empty($userIdsNeeded)) {
                    $q->orWhereIn('id', $userIdsNeeded);
                }
                if (! empty($namesNeeded)) {
                    $q->orWhereIn('name', $namesNeeded)
                        ->orWhereIn('oracle_user_name', $namesNeeded);
                }
            })
            ->orderBy('id')
            ->get();

        $usersById   = $users->keyBy('id');
        $usersByName = [];
        foreach ($users as $u) {
            // ??= preserves "first row by id ASC" for ambiguous name matches.
            if (! empty($u->name) && ! isset($usersByName[$u->name])) {
                $usersByName[$u->name] = $u;
            }
            if (! empty($u->oracle_user_name) && ! isset($usersByName[$u->oracle_user_name])) {
                $usersByName[$u->oracle_user_name] = $u;
            }
        }

        // ── Batch-load customers per salesperson string. ──
        // Mirrors salespersonCustomerIds(): match customers.salesperson against
        // either user.name or user.oracle_user_name, plucking local PKs.
        $allSalespersonStrings = $users
            ->flatMap(fn($u) => [$u->name, $u->oracle_user_name])
            ->filter()->unique()->values()->all();

        $customersBySalesperson = [];
        if (! empty($allSalespersonStrings)) {
            foreach (Customer::query()
                ->whereIn('salesperson', $allSalespersonStrings)
                ->select('id', 'salesperson')
                ->get() as $c) {
                $customersBySalesperson[$c->salesperson][] = (int) $c->id;
            }
        }

        $userCustomerIds = [];
        foreach ($users as $u) {
            $ids = [];
            if (! empty($u->name) && isset($customersBySalesperson[$u->name])) {
                $ids = array_merge($ids, $customersBySalesperson[$u->name]);
            }
            if (! empty($u->oracle_user_name) && isset($customersBySalesperson[$u->oracle_user_name])) {
                $ids = array_merge($ids, $customersBySalesperson[$u->oracle_user_name]);
            }
            $userCustomerIds[$u->id] = array_values(array_unique($ids));
        }

        // ── Batch-load all candidate receipts in ONE query. ──
        $allUserIds        = $users->pluck('id')->all();
        $allCustomerIdsSet = [];
        foreach ($userCustomerIds as $list) {
            foreach ($list as $cid) {
                $allCustomerIdsSet[$cid] = true;
            }

        }
        $allCustomerIds = array_keys($allCustomerIdsSet);

        $candidateReceipts = collect();
        if (! empty($allUserIds) || ! empty($allCustomerIds)) {
            $candidateReceipts = CustomerReceipt::query()
                ->whereBetween('created_at', [$start, $end])
                ->whereNull('cancelled_at')
                ->where(function ($q) use ($allUserIds, $allCustomerIds) {
                    if (! empty($allUserIds)) {
                        $q->orWhereIn('created_by', $allUserIds);
                    }

                    if (! empty($allCustomerIds)) {
                        $q->orWhereIn('customer_id', $allCustomerIds);
                    }

                })
                ->select('id', 'created_by', 'customer_id', 'receipt_amount')
                ->get();
        }

        // ── Compose the response. ──
        $out = [];
        foreach ($grouped as $key => $rows) {
            $targetPkr = $rows->sum(fn($t) => $t->receipt_target_pkr);

            if (str_starts_with((string) $key, 'name:')) {
                $user = $usersByName[substr((string) $key, 5)] ?? null;
            } else {
                $user = $usersById->get($key);
            }

            // Reproduces the original `WHERE created_by = user.id OR customer_id IN [...]`
            // by scanning the pre-loaded candidate set. A receipt that matches BOTH
            // conditions is counted once (the `if` short-circuits the second check).
            $actual = 0.0;
            if ($user) {
                $myCustomerIdsFlip = array_flip($userCustomerIds[$user->id] ?? []);
                $userIdInt         = (int) $user->id;
                foreach ($candidateReceipts as $cr) {
                    if (((int) $cr->created_by) === $userIdInt
                        || (! empty($myCustomerIdsFlip) && isset($myCustomerIdsFlip[(int) $cr->customer_id]))) {
                        $actual += (float) $cr->receipt_amount;
                    }
                }
            }

            $out[] = [
                'user_id'           => $user?->id,
                'name'              => $user?->name ?? $rows->first()->salesman_name ?? $rows->first()->primary_name,
                'primary_name'      => $rows->first()->primary_name,
                'collection_target' => round($targetPkr, 2),
                'collection_actual' => round($actual, 2),
                'achievement_pct'   => $this->pct($actual, $targetPkr),
            ];
        }

        // Sort by collection_target descending so biggest targets bubble up.
        usort($out, fn($a, $b) => $b['collection_target'] <=> $a['collection_target']);
        return $out;
    }

    // -----------------------------------------------------------------
    // Per-salesperson card
    // -----------------------------------------------------------------

    protected function buildSalespersonStats(?User $user, string $period, int $year, int $month, Carbon $start, Carbon $end, bool $withMonths = false): array
    {
        if (! $user) {
            return $this->emptySalespersonStats();
        }

        // Identify which customers belong to this salesperson (used everywhere).
        $customerIds = $this->salespersonCustomerIds($user);

        // ───── Sales (orders.total_amount, excluding cancelled) ─────
        $salesActual = Order::query()
            ->whereBetween('created_at', [$start, $end])
            ->where('order_status', '!=', \App\Enums\OrderStatusEnum::CANCELED)
            ->where(function ($q) use ($user, $customerIds) {
                $q->where('user_id', $user->id);
                if ($customerIds->isNotEmpty()) {
                    $q->orWhereIn('customer_id', $customerIds);
                }
            })
            ->sum('total_amount');

        // ───── Receipts (collection: cash + cheque amounts) ─────
        $receiptsQuery = CustomerReceipt::query()
            ->whereBetween('created_at', [$start, $end])
            ->whereNull('cancelled_at')
            ->where(function ($q) use ($user, $customerIds) {
                $q->where('created_by', $user->id);
                if ($customerIds->isNotEmpty()) {
                    $q->orWhereIn('customer_id', $customerIds);
                }
            });

        $collectionActual = (clone $receiptsQuery)->sum('receipt_amount');
        $grossCash        = (clone $receiptsQuery)->sum('cash_amount');

        // PDC = OPEN pending cheques across this salesperson's entire customer
        // set (lifetime, not just this period). Reads from receipt_cheques
        // (the normalized cheque table) instead of the legacy
        // customer_receipts.cheque_no/cheque_amount columns — those columns
        // are empty in practice now that receipts can carry multiple cheques
        // and the data lives in receipt_cheques. Scope:
        //   - the salesperson's own receipts OR receipts on his customers
        //   - parent receipt not cancelled
        //   - cheque status = 'pending' (not cleared, not bounced)
        //
        // Outstanding PDC is more actionable than period-bounded PDC because
        // it's the salesperson's true future cash pipeline.
        $pdcQuery = \DB::table('receipt_cheques as rc')
            ->join('customer_receipts as cr', 'cr.id', '=', 'rc.customer_receipt_id')
            ->whereNull('cr.cancelled_at')
            ->where('rc.status', 'pending')
            ->where(function ($q) use ($user, $customerIds) {
                $q->where('cr.created_by', $user->id);
                if ($customerIds->isNotEmpty()) {
                    $q->orWhereIn('cr.customer_id', $customerIds);
                }
            });

        $pdcCount  = (clone $pdcQuery)->count();
        $pdcAmount = (clone $pdcQuery)->sum('rc.cheque_amount');

        // ───── Targets (from TARGET.xlsx import) ─────
        // Both receipt and sales targets now live on the same row; pull both.
        $targetQuery = SalespersonTarget::query()
            ->where('year', $year)
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                    ->orWhere('primary_name', $user->name)
                    ->orWhere('salesman_name', $user->name)
                    ->orWhere('primary_name', $user->oracle_user_name)
                    ->orWhere('salesman_name', $user->oracle_user_name);
            });
        $targetRows = $targetQuery->get();

        Log::info('Dashboard Target Debug', [
            'period'           => $period,
            'month'            => $month,
            'user'             => $user->name,
            'count'            => $targetRows->count(),
            'sales_target_sum' => $targetRows->sum('sales_target_pkr'),
            'rows'             => $targetRows->map(function ($r) {
                return [
                    'id'               => $r->id,
                    'month'            => $r->month,
                    'year'             => $r->year,
                    'user_id'          => $r->user_id,
                    'primary_name'     => $r->primary_name,
                    'salesman_name'    => $r->salesman_name,
                    'sales_target_pkr' => $r->sales_target_pkr,
                ];
            })->values()->all(),
        ]);
        if ($period !== 'year') {
            $targetQuery->where('month', $month);
        }
        $targetRows       = $targetQuery->get();
        $collectionTarget = $targetRows->sum(fn($t) => $t->receipt_target_pkr);
        $salesTarget      = $targetRows->sum(fn($t) => $t->sales_target_pkr);
Log::info('MONTH TARGET', [
    'request_month' => $month,
    'sales_target' => $salesTarget,
    'months_returned' => $targetRows->pluck('month')->all(),
]);
        // ───── Customers' credit limit + Account Receivable (AR) ─────
        // AR for a salesperson = SUM of `customer_balance` across every
        // customer linked to them. customer_balance is the per-customer
        // outstanding owed and is the column the Oracle customer sync
        // refreshes, so this stays in line with what the Finance team
        // sees in EBS. Snapshot value — no date filter.
        if ($customerIds->isNotEmpty()) {
            $creditLimit = (float) Customer::whereIn('id', $customerIds)
                ->sum('overall_credit_limit');

            $accountReceivable = (float) Customer::whereIn('id', $customerIds)
                ->sum('customer_balance');

            $creditTotals = (object) [
                'credit_limit'       => $creditLimit,
                'account_receivable' => $accountReceivable,
            ];
        } else {
            $creditTotals = (object) ['credit_limit' => 0, 'account_receivable' => 0];
        }

        // Day-to-month (default) and day-to-year breakdowns for the
        // progress-bar UI. These use the new Oracle views (cleared
        // collections + net sales) and account for the "shortfall rolls
        // forward" target rule.
        $breakdown = $this->buildPeriodBreakdown($user, $customerIds, $year, $month);

        return [
            'name'               => $user->name,
            'email'              => $user->email,
            'profile_photo'      => $user->profile_photo
                ? (str_starts_with($user->profile_photo, 'http') ? $user->profile_photo : asset('storage/' . $user->profile_photo))
                : null,

            'sales'              => [
                'target'          => round($salesTarget, 2),
                'actual'          => round((float) $salesActual, 2),
                'achievement_pct' => $this->pct($salesActual, $salesTarget),
            ],

            'collection'         => [
                'target'          => round($collectionTarget, 2),
                'actual'          => round((float) $collectionActual, 2),
                'achievement_pct' => $this->pct($collectionActual, $collectionTarget),
                'gross_cash'      => round((float) $grossCash, 2),
                'pdc_count'       => (int) $pdcCount,
                'pdc_amount'      => round((float) $pdcAmount, 2),
            ],

            'credit_limit_total' => round((float) $creditTotals->credit_limit, 2),
            'account_receivable' => round((float) $creditTotals->account_receivable, 2),

            // New: per-period progress, default view in `day_to_month`.
            'default_view'       => 'day_to_month',
            'day_to_month'       => $breakdown['day_to_month'],
            'day_to_year'        => $breakdown['day_to_year'],

            // Per-calendar-month roll-up for the requested year. Only computed
            // when the caller passed ?breakdown=months so the default payload
            // doesn't pay the extra queries.
            'months'             => $withMonths
                ? $this->buildMonthlyBreakdown($user, $customerIds, $year)
                : null,
        ];
    }

    /**
     * Twelve rows (Jan–Dec of $year), each carrying that month's sales +
     * collection target/actual and achievement %. Uses local orders +
     * customer_receipts for actuals (same source the single-month card uses),
     * matched against SalespersonTarget rows by month.
     */
    protected function buildMonthlyBreakdown(User $user, $customerIds, int $year): array
    {
        $yearStart = Carbon::create($year, 1, 1)->startOfDay();
        $yearEnd   = Carbon::create($year, 12, 31)->endOfDay();

        // Sales actual per month → [month => sum].
        $salesByMonth = Order::query()
            ->selectRaw('MONTH(created_at) as m, SUM(total_amount) as amt')
            ->whereBetween('created_at', [$yearStart, $yearEnd])
            ->where('order_status', '!=', \App\Enums\OrderStatusEnum::CANCELED)
            ->where(function ($q) use ($user, $customerIds) {
                $q->where('user_id', $user->id);
                if ($customerIds->isNotEmpty()) {
                    $q->orWhereIn('customer_id', $customerIds);
                }
            })
            ->groupBy('m')
            ->pluck('amt', 'm');

        // Collection actual per month → [month => sum].
        $collectionByMonth = CustomerReceipt::query()
            ->selectRaw('MONTH(created_at) as m, SUM(receipt_amount) as amt')
            ->whereBetween('created_at', [$yearStart, $yearEnd])
            ->whereNull('cancelled_at')
            ->where(function ($q) use ($user, $customerIds) {
                $q->where('created_by', $user->id);
                if ($customerIds->isNotEmpty()) {
                    $q->orWhereIn('customer_id', $customerIds);
                }
            })
            ->groupBy('m')
            ->pluck('amt', 'm');

        // Targets — all 12 rows for this year at once. Same identity match
        // (user_id OR primary_name OR salesman_name) the single-month path uses.
        $targetRows = SalespersonTarget::query()
            ->where('year', $year)
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                    ->orWhere('primary_name', $user->name)
                    ->orWhere('salesman_name', $user->name)
                    ->orWhere('primary_name', $user->oracle_user_name)
                    ->orWhere('salesman_name', $user->oracle_user_name);
            })
            ->get()
            ->groupBy('month');

        $labels = [1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4  => 'Apr', 5  => 'May', 6  => 'Jun',
            7            => 'Jul', 8 => 'Aug', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec'];

        $rows = [];
        for ($m = 1; $m <= 12; $m++) {
            $rowsForMonth  = $targetRows->get($m, collect());
            $salesTgt      = (float) $rowsForMonth->sum(fn($t) => $t->sales_target_pkr);
            $collectionTgt = (float) $rowsForMonth->sum(fn($t) => $t->receipt_target_pkr);

            $salesAct      = (float) ($salesByMonth[$m] ?? 0);
            $collectionAct = (float) ($collectionByMonth[$m] ?? 0);

            $rows[] = [
                'month'      => $m,
                'label'      => $labels[$m],
                'sales'      => [
                    'target'          => round($salesTgt, 2),
                    'actual'          => round($salesAct, 2),
                    'achievement_pct' => $this->pct($salesAct, $salesTgt),
                ],
                'collection' => [
                    'target'          => round($collectionTgt, 2),
                    'actual'          => round($collectionAct, 2),
                    'achievement_pct' => $this->pct($collectionAct, $collectionTgt),
                ],
            ];
        }

        return $rows;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Day-to-month / Day-to-year breakdown (uses Oracle monthly views)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Build day-to-month (current month + carryforward shortfall) and
     * day-to-year (cumulative Jan→current month) progress data for
     * both sales and collection.
     *
     * Sales actuals come from APPS.QG_POS_CUST_MONTHLY_SALES_V (net sales).
     * Collection actuals come from APPS.QG_POS_CUST_MONTHLY_COLL_V (cleared
     * receipts only, not raw receipts).
     *
     * Carryforward rule: if the cumulative target for prior months in this
     * year exceeded the cumulative actual, the shortfall is added to the
     * current month's target so the progress bar reflects the catch-up.
     */
    protected function buildPeriodBreakdown(User $user, $customerIds, int $year, int $month): array
    {
        // Targets by month (from SalespersonTarget.xlsx import). Returns
        // ['receipt' => [m=>amt], 'sales' => [m=>amt]] so the sales and
        // collection blocks each use their own target column.
        $monthlyTargets = $this->fetchMonthlyTargets($user, $year);

        // Actuals by month (Jan..currentMonth) sourced from the SAME local
        // tables the top-level salesperson.sales.actual / collection.actual
        // headline numbers use, so the DtM/DtY block agrees with the
        // headline figures. The Oracle monthly views
        // (APPS.QG_POS_CUST_MONTHLY_SALES_V / _COLL_V) lag behind real-time
        // orders/receipts by a nightly concurrent program, which was making
        // the breakdown report 0 for the current month even when the
        // portal had 20M+ already booked — and dragged carryforward up to
        // 250M for collection because earlier months in the view were
        // also still 0 against their real targets.
        $monthlySalesActuals      = $this->fetchMonthlySalesActualsLocal($user, $customerIds, $year, $month);
        $monthlyCollectionActuals = $this->fetchMonthlyCollectionActualsLocal($user, $customerIds, $year, $month);

        $remainingWorkingDays = $this->workingDaysRemainingInMonth($year, $month);

        // DtM (current month + carryforward from earlier months in same year).
        // Sales uses sales targets; collection uses receipt targets.
        $dtmSales      = $this->breakdownForMonth($monthlyTargets['sales'], $monthlySalesActuals, $year, $month, $remainingWorkingDays);
        $dtmCollection = $this->breakdownForMonth($monthlyTargets['receipt'], $monthlyCollectionActuals, $year, $month, $remainingWorkingDays);

        // DtY (cumulative Jan → current month)
        $dtySales      = $this->breakdownForYear($monthlyTargets['sales'], $monthlySalesActuals, $year, $month);
        $dtyCollection = $this->breakdownForYear($monthlyTargets['receipt'], $monthlyCollectionActuals, $year, $month);

        return [
            'day_to_month' => [
                'period_label'           => Carbon::create($year, $month, 1)->format('M Y'),
                'remaining_working_days' => $remainingWorkingDays,
                'working_day_rule'       => 'Mon–Sat (Sun excluded)',
                'sales'                  => $dtmSales,
                'collection'             => $dtmCollection,
            ],
            'day_to_year'  => [
                'period_label'  => "Jan – " . Carbon::create($year, $month, 1)->format('M Y'),
                'through_month' => $month,
                'sales'         => $dtySales,
                'collection'    => $dtyCollection,
            ],
        ];
    }

    /**
     * Returns target sums by month, split into receipt and sales.
     *   [
     *     'receipt' => [1 => target_pkr, 2 => ..., ..., 12 => ...],
     *     'sales'   => [1 => target_pkr, 2 => ..., ..., 12 => ...],
     *   ]
     *
     * Each side reads from its own SalespersonTarget column. Months with no
     * row default to 0. Multiple matching rows in the same month are summed.
     */
    protected function fetchMonthlyTargets(User $user, int $year): array
    {
        $rows = SalespersonTarget::query()
            ->where('year', $year)
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                    ->orWhere('primary_name', $user->name)
                    ->orWhere('salesman_name', $user->name)
                    ->orWhere('primary_name', $user->oracle_user_name)
                    ->orWhere('salesman_name', $user->oracle_user_name);
            })
            ->get();
        Log::info('Monthly Targets Debug', [
            'user' => $user->name,
            'year' => $year,
            'rows' => $rows->map(function ($r) {
                return [
                    'id'               => $r->id,
                    'month'            => $r->month,
                    'sales_target_pkr' => $r->sales_target_pkr,
                ];
            })->values()->all(),
        ]);
        $receiptByMonth = array_fill(1, 12, 0.0);
        $salesByMonth   = array_fill(1, 12, 0.0);
        foreach ($rows as $r) {
            $m = (int) $r->month;
            if ($m < 1 || $m > 12) {
                continue;
            }

            $receiptByMonth[$m] += (float) $r->receipt_target_pkr;
            $salesByMonth[$m]   += (float) $r->sales_target_pkr;
        }

        return ['receipt' => $receiptByMonth, 'sales' => $salesByMonth];
    }

    /**
     * Returns [1 => actual, 2 => actual, ...] for Jan..thru month, filtered
     * by the user's Oracle customer_id set. Reads from one of the two
     * monthly Oracle views (sales vs collection).
     *
     * Defensive against varying column-name casing the oci8 driver may
     * produce — uses each model's static valueOf() helper to look up
     * `customer_id`/`customer_number`, `year`/`period_year`,
     * `month`/`period_month`, and the amount under several names.
     */
    protected function fetchMonthlyActuals(string $modelClass, array $oracleCustomerIds, int $year, int $thruMonth, bool $isSales): array
    {
        $byMonth = array_fill(1, 12, 0.0);

        if (empty($oracleCustomerIds)) {
            return $byMonth;
        }

        try {
            // Set Oracle NLS so date parsing doesn't blow up on view-side dates.
            $this->setOracleNlsSession();

            $amountCandidates = $isSales
                ? ['net_amount', 'sales_amount', 'amount', 'net_sales']
                : ['coll_amount', 'collection_amount', 'amount', 'cleared_amount'];

            // Pull a wide row set — both casing variants of the customer column.
            $rows = $modelClass::query()
                ->where(function ($q) use ($oracleCustomerIds) {
                    $q->orWhereIn('customer_id', $oracleCustomerIds)
                        ->orWhereIn('customer_number', $oracleCustomerIds);
                })
                ->get();

            foreach ($rows as $row) {
                $rowYear  = (int) $modelClass::valueOf($row, ['year', 'period_year'], 0);
                $rowMonth = (int) $modelClass::valueOf($row, ['month', 'period_month'], 0);

                if ($rowYear === 0 || $rowMonth === 0) {
                    // Some views expose a period_date instead of separate cols.
                    $period = $modelClass::valueOf($row, ['period_date', 'period']);
                    if ($period) {
                        try {
                            $parsed   = Carbon::parse($period);
                            $rowYear  = $parsed->year;
                            $rowMonth = $parsed->month;
                        } catch (\Throwable $e) {}
                    }
                }

                if ($rowYear !== $year) {
                    continue;
                }

                if ($rowMonth < 1 || $rowMonth > 12) {
                    continue;
                }

                if ($rowMonth > $thruMonth) {
                    continue;
                }

                $byMonth[$rowMonth] += (float) $modelClass::valueOf($row, $amountCandidates, 0);
            }
        } catch (\Throwable $e) {
            Log::error('Oracle monthly view query failed', [
                'model'     => $modelClass,
                'year'      => $year,
                'customers' => count($oracleCustomerIds),
                'error'     => $e->getMessage(),
            ]);
        }

        return $byMonth;
    }

    /**
     * Local sales actuals by month for Jan..$thruMonth of $year — mirrors
     * the headline salesperson.sales.actual query (sum of orders.total_amount
     * excluding cancelled, scoped to the salesperson's own orders OR their
     * assigned customers). Used by the DtM/DtY breakdown so it reads from
     * the same data the headline does.
     */
    protected function fetchMonthlySalesActualsLocal(User $user, $customerIds, int $year, int $thruMonth): array
    {
        $byMonth = array_fill(1, 12, 0.0);

        $rows = \DB::table('orders')
            ->selectRaw('MONTH(created_at) AS m, COALESCE(SUM(total_amount), 0) AS amt')
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', '<=', $thruMonth)
            ->where('order_status', '!=', \App\Enums\OrderStatusEnum::CANCELED->value)
            ->where(function ($q) use ($user, $customerIds) {
                $q->where('user_id', $user->id);
                if ($customerIds && $customerIds->isNotEmpty()) {
                    $q->orWhereIn('customer_id', $customerIds);
                }
            })
            ->groupByRaw('MONTH(created_at)')
            ->get();

        foreach ($rows as $r) {
            $m = (int) $r->m;
            if ($m >= 1 && $m <= 12) {
                $byMonth[$m] = (float) $r->amt;
            }
        }

        return $byMonth;
    }

    /**
     * Local collection actuals by month — mirrors the headline
     * salesperson.collection.actual query (sum of customer_receipts.receipt_amount
     * excluding cancelled, scoped to the salesperson's receipts OR their
     * customers).
     */
    protected function fetchMonthlyCollectionActualsLocal(User $user, $customerIds, int $year, int $thruMonth): array
    {
        $byMonth = array_fill(1, 12, 0.0);

        $rows = \DB::table('customer_receipts')
            ->selectRaw('MONTH(created_at) AS m, COALESCE(SUM(receipt_amount), 0) AS amt')
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', '<=', $thruMonth)
            ->whereNull('cancelled_at')
            ->where(function ($q) use ($user, $customerIds) {
                $q->where('created_by', $user->id);
                if ($customerIds && $customerIds->isNotEmpty()) {
                    $q->orWhereIn('customer_id', $customerIds);
                }
            })
            ->groupByRaw('MONTH(created_at)')
            ->get();

        foreach ($rows as $r) {
            $m = (int) $r->m;
            if ($m >= 1 && $m <= 12) {
                $byMonth[$m] = (float) $r->amt;
            }
        }

        return $byMonth;
    }

    /**
     * Build the DtM block:
     *   - target for THIS month
     *   - +carryforward from earlier months in same year (shortfall rolls forward)
     *   - achieved this month
     *   - remaining = (target + carryforward) - achieved
     */
    protected function breakdownForMonth(array $monthlyTargets, array $monthlyActuals, int $year, int $month, int $remainingWorkingDays): array
    {
        $originalTarget = (float) ($monthlyTargets[$month] ?? 0);

        // Cumulative target & actual for Jan..(month-1) — anything not yet
        // delivered rolls forward into this month.
        $priorTarget = 0.0;
        $priorActual = 0.0;
        for ($m = 1; $m < $month; $m++) {
            $priorTarget += (float) ($monthlyTargets[$m] ?? 0);
            $priorActual += (float) ($monthlyActuals[$m] ?? 0);
        }
        $carryforward = max(0.0, $priorTarget - $priorActual);

        $effectiveTarget = $originalTarget + $carryforward;
        $achieved        = (float) ($monthlyActuals[$month] ?? 0);
        $remaining       = max(0.0, $effectiveTarget - $achieved);

        return [
            'target'                 => round($effectiveTarget, 2),
            'original_target'        => round($originalTarget, 2),
            'carryforward'           => round($carryforward, 2),
            'achieved'               => round($achieved, 2),
            'remaining'              => round($remaining, 2),
            'achievement_pct'        => $this->pct($achieved, $effectiveTarget),
            'remaining_working_days' => $remainingWorkingDays,
        ];
    }

    /**
     * Build the DtY block: cumulative Jan→current month.
     * No carryforward concept here — the year-to-date already aggregates
     * everything, so shortfall is implicit in (target - achieved).
     */
    protected function breakdownForYear(array $monthlyTargets, array $monthlyActuals, int $year, int $thruMonth): array
    {
        $target = 0.0;
        $actual = 0.0;
        for ($m = 1; $m <= $thruMonth; $m++) {
            $target += (float) ($monthlyTargets[$m] ?? 0);
            $actual += (float) ($monthlyActuals[$m] ?? 0);
        }
        $remaining = max(0.0, $target - $actual);

        return [
            'target'          => round($target, 2),
            'achieved'        => round($actual, 2),
            'remaining'       => round($remaining, 2),
            'achievement_pct' => $this->pct($actual, $target),
        ];
    }

    /**
     * Working days remaining in the given month — counts today onward,
     * excluding Sundays (Pakistan standard Mon–Sat work week).
     */
    protected function workingDaysRemainingInMonth(int $year, int $month): int
    {
        $today      = now()->startOfDay();
        $monthStart = Carbon::create($year, $month, 1)->startOfDay();
        $monthEnd   = (clone $monthStart)->endOfMonth()->startOfDay();

        // If the requested month is in the past, no remaining days.
        if ($today->greaterThan($monthEnd)) {
            return 0;
        }

        $cursor = $today->greaterThan($monthStart) ? $today->copy() : $monthStart->copy();
        $count  = 0;
        while ($cursor->lessThanOrEqualTo($monthEnd)) {
            // dayOfWeek: 0 = Sun, 1 = Mon ... 6 = Sat
            if ($cursor->dayOfWeek !== Carbon::SUNDAY) {
                $count++;
            }
            $cursor->addDay();
        }
        return $count;
    }

    /**
     * Aggregate roll-up — for admin users.
     */
    protected function buildAggregateStats(string $period, int $year, int $month, Carbon $start, Carbon $end): array
    {
        // YTD bounds (always Jan 1 → now of the given year)
        $ytdStart = Carbon::create($year, 1, 1)->startOfDay();
        $ytdEnd   = Carbon::create($year, 12, 31)->endOfDay();
        if (now()->year == $year && now()->lessThan($ytdEnd)) {
            $ytdEnd = now()->endOfDay();
        }

        // MTD bounds (1st of current/requested month → end of that month)
        $mtdStart = $start;
        $mtdEnd   = $end;

        // Total target for the year — sum of monthly receipt targets across ALL
        // salespersons, normalised to PKR (each row's `unit` is honoured).
        $yearTarget = SalespersonTarget::where('year', $year)
            ->get()
            ->sum(fn($t) => $t->receipt_target_pkr);

        // Sales aggregates
        $salesYtd = (float) Order::whereBetween('created_at', [$ytdStart, $ytdEnd])
            ->where('order_status', '!=', \App\Enums\OrderStatusEnum::CANCELED)
            ->sum('total_amount');

        $salesMtd = (float) Order::whereBetween('created_at', [$mtdStart, $mtdEnd])
            ->where('order_status', '!=', \App\Enums\OrderStatusEnum::CANCELED)
            ->sum('total_amount');

        // Month-to-month sales for the requested year (12 buckets)
        $monthly = Order::query()
            ->selectRaw('MONTH(created_at) AS m, COALESCE(SUM(total_amount),0) AS total')
            ->whereYear('created_at', $year)
            ->where('order_status', '!=', \App\Enums\OrderStatusEnum::CANCELED)
            ->groupBy('m')
            ->pluck('total', 'm');

        $monthly = collect(range(1, 12))->map(fn($m) => [
            'month'      => $m,
            'month_name' => Carbon::create()->month($m)->format('M'),
            'sales'      => round((float) ($monthly[$m] ?? 0), 2),
        ])->all();

        return [
            'year_target'          => round($yearTarget, 2),
            'sales_year_to_date'   => round($salesYtd, 2),
            'sales_month_to_date'  => round($salesMtd, 2),
            'ytd_achievement_pct'  => $this->pct($salesYtd, $yearTarget),
            'mtd_achievement_pct'  => $this->pct($salesMtd, $yearTarget / 12),
            'month_to_month_sales' => $monthly,
        ];
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    protected function resolvePeriodRange(string $period, int $year, int $month): array
    {
        if ($period === 'year') {
            return [
                Carbon::create($year, 1, 1)->startOfDay(),
                Carbon::create($year, 12, 31)->endOfDay(),
            ];
        }
        $start = Carbon::create($year, $month, 1)->startOfDay();
        return [$start, $start->copy()->endOfMonth()];
    }

    /**
     * Customers assigned to this salesperson — matched by `salesperson` column
     * (the existing pattern across the codebase).
     */
    protected function salespersonCustomerIds(User $user)
    {
        return Customer::query()
            ->where(function ($q) use ($user) {
                $q->where('salesperson', $user->name)
                    ->orWhere('salesperson', $user->oracle_user_name);
            })
            ->pluck('id');
    }

    protected function pct(float | int | null $actual, float | int | null $target): float
    {
        $target = (float) $target;
        if ($target <= 0) {
            return 0.0;
        }

        return round(((float) $actual / $target) * 100, 2);
    }

    protected function emptySalespersonStats(): array
    {
        $emptyDtm = [
            'target'                 => 0, 'original_target' => 0, 'carryforward'    => 0,
            'achieved'               => 0, 'remaining'       => 0, 'achievement_pct' => 0,
            'remaining_working_days' => 0,
        ];
        $emptyDty = ['target' => 0, 'achieved' => 0, 'remaining' => 0, 'achievement_pct' => 0];

        return [
            'name'               => null, 'email' => null, 'profile_photo' => null,
            'sales'              => ['target' => 0, 'actual' => 0, 'achievement_pct' => 0],
            'collection'         => [
                'target'     => 0, 'actual'    => 0, 'achievement_pct' => 0,
                'gross_cash' => 0, 'pdc_count' => 0, 'pdc_amount'      => 0,
            ],
            'credit_limit_total' => 0,
            'account_receivable' => 0,

            'default_view'       => 'day_to_month',
            'day_to_month'       => [
                'period_label'           => now()->format('M Y'),
                'remaining_working_days' => 0,
                'working_day_rule'       => 'Mon–Sat (Sun excluded)',
                'sales'                  => $emptyDtm,
                'collection'             => $emptyDtm,
            ],
            'day_to_year'        => [
                'period_label'  => 'Jan – ' . now()->format('M Y'),
                'through_month' => now()->month,
                'sales'         => $emptyDty,
                'collection'    => $emptyDty,
            ],
        ];
    }
}
