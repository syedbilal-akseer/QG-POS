<?php

namespace App\Livewire\Dashboard;

use App\Services\SalespersonLeaderboardService;
use Carbon\Carbon;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Dashboard "Top Salespeople" leaderboard — orders + receipts, per location
 * (Karachi/Lahore). Each of the 4 tables paginates independently (Livewire
 * supports multiple named paginators per component out of the box).
 */
class SalesLeaderboard extends Component
{
    use WithPagination;

    public array $access = [];
    public bool $detail = true;
    public string $startDate;
    public string $endDate;
    public array $salespersonIds = [];

    protected int $perPage = 10;

    protected array $locationDefs = [
        'khi' => [
            'title' => 'Karachi',
            'ous' => SalespersonLeaderboardService::KHI_OUS,
            'orders_color' => [
                'badge_class' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
                'count_class' => 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
            ],
            'receipts_color' => [
                'badge_class' => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300',
                'count_class' => 'bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-300',
            ],
        ],
        'lhr' => [
            'title' => 'Lahore',
            'ous' => SalespersonLeaderboardService::LHR_OUS,
            'orders_color' => [
                'badge_class' => 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300',
                'count_class' => 'bg-purple-50 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300',
            ],
            'receipts_color' => [
                'badge_class' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
                'count_class' => 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
            ],
        ],
    ];

    public function mount(array $access, bool $detail, string $startDate, string $endDate, array $salespersonIds = [])
    {
        $this->access = $access;
        $this->detail = $detail;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->salespersonIds = $salespersonIds;
    }

    public function render()
    {
        $service = app(SalespersonLeaderboardService::class);

        $start = Carbon::parse($this->startDate)->startOfDay();
        $end = Carbon::parse($this->endDate)->endOfDay();

        $ids = $service->resolveLocationUserIds($this->salespersonIds);
        $userIds = [
            'khi' => ['users' => $ids['khiUserIds'], 'overrides' => $ids['khiOverrideUserIds']],
            'lhr' => ['users' => $ids['lhrUserIds'], 'overrides' => $ids['lhrOverrideUserIds']],
        ];

        $locationRows = [];
        foreach ($this->locationDefs as $locKey => $loc) {
            $cards = [];
            $locUsers = $userIds[$locKey]['users'];
            $locOverrides = $userIds[$locKey]['overrides'];

            if ($this->access['orders'][$locKey] ?? false) {
                $cards[] = [
                    'unit' => 'orders',
                    'rows' => $this->detail
                        ? $service->paginateOrders($loc['ous'], $locUsers, $locOverrides, $start, $end, $this->perPage, $locKey . 'OrdersPage')
                        : null,
                    'overall' => $service->totalOrders($loc['ous'], $locUsers, $locOverrides, $start, $end),
                    'badge_class' => $loc['orders_color']['badge_class'],
                    'count_class' => $loc['orders_color']['count_class'],
                ];
            }

            if ($this->access['receipts'][$locKey] ?? false) {
                $cards[] = [
                    'unit' => 'receipts',
                    'rows' => $this->detail
                        ? $service->paginateReceipts($loc['ous'], $locUsers, $locOverrides, $start, $end, $this->perPage, $locKey . 'ReceiptsPage')
                        : null,
                    'overall' => $service->totalReceipts($loc['ous'], $locUsers, $locOverrides, $start, $end),
                    'badge_class' => $loc['receipts_color']['badge_class'],
                    'count_class' => $loc['receipts_color']['count_class'],
                ];
            }

            if (!empty($cards)) {
                $locationRows[] = [
                    'title' => $loc['title'],
                    'cards' => $cards,
                ];
            }
        }

        return view('livewire.dashboard.sales-leaderboard', [
            'locationRows' => $locationRows,
            'detail' => $this->detail,
        ]);
    }
}
