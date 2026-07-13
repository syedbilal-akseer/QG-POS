<?php

namespace App\Services;

use App\Models\OracleBankMaster;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class BankService
{
    /**
     * Cache duration in minutes.
     */
    const CACHE_DURATION = 60;

    /**
     * Get all active banks.
     *
     * @return Collection
     */
    public function getAllBanks(): Collection
    {
        return Cache::remember('oracle_banks_all', self::CACHE_DURATION, function () {
            try {
                return OracleBankMaster::active()->orderBy('bank_name')->get();
            } catch (\Exception $e) {
                Log::error('Failed to fetch banks from Oracle: ' . $e->getMessage());
                return collect();
            }
        });
    }

    /**
     * Search banks by term.
     *
     * @param string $searchTerm
     * @return Collection
     */
    public function searchBanks(string $searchTerm): Collection
    {
        try {
            return OracleBankMaster::active()
                ->search($searchTerm)
                ->orderBy('bank_name')
                ->limit(50)
                ->get();
        } catch (\Exception $e) {
            Log::error('Failed to search banks: ' . $e->getMessage());
            return collect();
        }
    }

    /**
     * Get bank by ID.
     *
     * @param mixed $bankId
     * @return OracleBankMaster|null
     */
    public function getBankById($bankId): ?OracleBankMaster
    {
        return Cache::remember("oracle_bank_{$bankId}", self::CACHE_DURATION, function () use ($bankId) {
            try {
                return OracleBankMaster::where('bank_id', $bankId)->first();
            } catch (\Exception $e) {
                Log::error("Failed to fetch bank {$bankId}: " . $e->getMessage());
                return null;
            }
        });
    }

    /**
     * Get banks by organization unit.
     *
     * @param int $ouId
     * @return Collection
     */
    public function getBanksByOrgUnit(int $ouId): Collection
    {
        return Cache::remember("oracle_banks_ou_{$ouId}", self::CACHE_DURATION, function () use ($ouId) {
            try {
                return OracleBankMaster::active()
                    ->byOrgUnit($ouId)
                    ->orderBy('bank_name')
                    ->get();
            } catch (\Exception $e) {
                Log::error("Failed to fetch banks for OU {$ouId}: " . $e->getMessage());
                return collect();
            }
        });
    }

    /**
     * Get banks formatted for select dropdown.
     *
     * @param int|null $ouId
     * @return array
     */
    public function getBanksForSelect(?int $ouId = null): array
    {
        $banks = $ouId ? $this->getBanksByOrgUnit($ouId) : $this->getAllBanks();

        return $banks->mapWithKeys(function ($bank) {
            return [$bank->bank_id => $bank->display_name];
        })->toArray();
    }

    /**
     * Validate bank account details.
     *
     * @param string $accountNumber
     * @param string|null $bankCode
     * @return array
     */
    public function validateBankAccount(string $accountNumber, ?string $bankCode = null): array
    {
        try {
            $query = OracleBankMaster::active()->where('account_number', $accountNumber);
            
            if ($bankCode) {
                $query->where('bank_code', $bankCode);
            }

            $bank = $query->first();

            if (!$bank) {
                return [
                    'valid' => false,
                    'message' => 'Bank account not found',
                    'bank' => null,
                ];
            }

            return [
                'valid' => true,
                'message' => 'Bank account is valid',
                'bank' => $bank,
            ];
        } catch (\Exception $e) {
            Log::error('Bank validation failed: ' . $e->getMessage());
            return [
                'valid' => false,
                'message' => 'Validation failed due to system error',
                'bank' => null,
            ];
        }
    }

    /**
     * Clear banks cache.
     *
     * @return bool
     */
    public function clearCache(): bool
    {
        try {
            Cache::forget('oracle_banks_all');
            // Clear all OU-specific caches (this is a simplified approach)
            for ($i = 1; $i <= 100; $i++) {
                Cache::forget("oracle_banks_ou_{$i}");
            }
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to clear banks cache: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Test Oracle connection.
     *
     * @return array
     */
    public function testConnection(): array
    {
        try {
            $count = OracleBankMaster::count();
            return [
                'success' => true,
                'message' => "Successfully connected to Oracle. Found {$count} bank records.",
                'count' => $count,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Oracle connection failed: ' . $e->getMessage(),
                'count' => 0,
            ];
        }
    }
}