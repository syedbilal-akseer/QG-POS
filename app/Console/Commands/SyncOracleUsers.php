<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\UserOrganization;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncOracleUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:oracle-users';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync users from Oracle QG_SHIPPING_USERS and QG_ALL_USERS to MySQL database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting Oracle users sync...');

        DB::transaction(function () {
            $syncedUsers = 0;
            $syncedOrganizations = 0;

            // 1. Sync QG_SHIPPING_USERS (supply-chain role)
            $this->info('Syncing QG_SHIPPING_USERS (supply-chain users)...');
            $this->syncShippingUsers($syncedUsers, $syncedOrganizations);

            // 2. Sync QG_ALL_USERS (salesperson role)
            $this->info('Syncing QG_ALL_USERS (salesperson users)...');
            $this->syncAllUsers($syncedUsers, $syncedOrganizations);

            $this->info("Sync completed!");
            $this->info("New users created: {$syncedUsers}");
            $this->info("New organization relationships: {$syncedOrganizations}");
        });

        return Command::SUCCESS;
    }

    /**
     * Sync QG_SHIPPING_USERS (supply-chain role)
     */
    private function syncShippingUsers(int &$syncedUsers, int &$syncedOrganizations)
    {
        // Fetch all users from Oracle QG_SHIPPING_USERS with warehouse OU mapping
        $oracleUsers = DB::connection('oracle')
            ->table('apps.qg_shipping_users as u')
            ->leftJoin('apps.qg_pos_warehouses as w', 'u.organization_code', '=', 'w.organization_code')
            ->select(
                'u.user_id', 
                'u.user_name', 
                'u.organization_code', 
                'u.organization_name',
                'w.ou as ou_id'
            )
            ->get();

        $this->info("Found {$oracleUsers->count()} shipping user records to process.");

        foreach ($oracleUsers as $oracleUser) {
            $normalizedOracleName = $this->normalizeName($oracleUser->user_name);
            
            $generatedEmail = strtolower(str_replace(['.', ' '], '_', $normalizedOracleName)) . '@quadri-group.com';
            
            $existingUser = User::where(function($query) use ($oracleUser, $normalizedOracleName, $generatedEmail) {
                $query->where('oracle_user_id', $oracleUser->user_id)
                      ->orWhere('oracle_user_name', $oracleUser->user_name)
                      ->orWhere('name', $normalizedOracleName)
                      ->orWhere('email', $generatedEmail);
            })->first();

            if ($existingUser) {
                // Protected roles that should not be overwritten by Oracle sync
                $protectedRoles = ['price-uploads', 'cmd-khi', 'cmd-lhr', 'scm-lhr', 'account-user', 'sales-head', 'admin'];

                // Only update role if user doesn't have a protected role
                $updateData = [
                    'oracle_user_id' => $oracleUser->user_id,
                    'oracle_user_name' => $oracleUser->user_name,
                ];

                if (!in_array($existingUser->role, $protectedRoles)) {
                    $updateData['role'] = 'supply-chain';
                }

                $existingUser->update($updateData);
                $user = $existingUser;

                if (in_array($existingUser->role, $protectedRoles)) {
                    $this->line("Updated shipping user (preserved role '{$existingUser->role}'): {$user->name} -> Oracle ID: {$oracleUser->user_id}");
                } else {
                    $this->line("Updated shipping user: {$user->name} -> Oracle ID: {$oracleUser->user_id}");
                }
            } else {
                $user = User::create([
                    'name' => $normalizedOracleName,
                    'email' => $generatedEmail,
                    'password' => bcrypt('Hello@123'),
                    'role' => 'supply-chain',
                    'oracle_user_id' => $oracleUser->user_id,
                    'oracle_user_name' => $oracleUser->user_name,
                ]);
                $this->line("Created shipping user: {$user->name} (Oracle ID: {$oracleUser->user_id})");
                $syncedUsers++;
            }

            // Sync organization relationship
            $userOrganization = UserOrganization::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'oracle_organization_code' => $oracleUser->organization_code,
                ],
                [
                    'oracle_organization_name' => $oracleUser->organization_name,
                    'oracle_ou_id' => $oracleUser->ou_id,
                    'is_active' => true,
                ]
            );

            if ($userOrganization->wasRecentlyCreated) {
                $syncedOrganizations++;
                $this->line("  -> Added organization: {$oracleUser->organization_code} ({$oracleUser->organization_name})");
            }
        }
    }

    /**
     * Sync QG_ALL_USERS (salesperson role)  
     */
    private function syncAllUsers(int &$syncedUsers, int &$syncedOrganizations)
    {
        // Fetch all users from Oracle QG_ALL_USERS
        $oracleUsers = DB::connection('oracle')
            ->table('apps.qg_all_users')
            ->select('user_name')
            ->distinct()
            ->whereNotNull('user_name')
            ->get();

        $this->info("Found {$oracleUsers->count()} salesperson user records to process.");

        foreach ($oracleUsers as $oracleUser) {
            $normalizedOracleName = $this->normalizeName($oracleUser->user_name);
            
            $generatedEmail = strtolower(str_replace(['.', ' '], '_', $normalizedOracleName)) . '@quadri-group.com';
            
            $existingUser = User::where(function($query) use ($oracleUser, $normalizedOracleName, $generatedEmail) {
                $query->where('oracle_user_name', $oracleUser->user_name)
                      ->orWhere('name', $normalizedOracleName)
                      ->orWhere('email', $generatedEmail);
            })->first();

            if ($existingUser) {
                // Protected roles that should not be overwritten by Oracle sync
                $protectedRoles = ['price-uploads', 'cmd-khi', 'cmd-lhr', 'scm-lhr', 'account-user', 'sales-head', 'admin'];

                // Only update role if user doesn't have a protected role
                $updateData = [
                    'oracle_user_name' => $oracleUser->user_name,
                ];

                if (!in_array($existingUser->role, $protectedRoles)) {
                    $updateData['role'] = 'user';
                }

                $existingUser->update($updateData);
                $user = $existingUser;

                if (in_array($existingUser->role, $protectedRoles)) {
                    $this->line("Updated salesperson (preserved role '{$existingUser->role}'): {$user->name} -> Oracle Name: {$oracleUser->user_name}");
                } else {
                    $this->line("Updated salesperson: {$user->name} -> Oracle Name: {$oracleUser->user_name}");
                }
            } else {
                $user = User::create([
                    'name' => $normalizedOracleName,
                    'email' => $generatedEmail,
                    'password' => bcrypt('Hello@123'),
                    'role' => 'user',
                    'oracle_user_name' => $oracleUser->user_name,
                ]);
                $this->line("Created salesperson: {$user->name} (Oracle Name: {$oracleUser->user_name})");
                $syncedUsers++;
            }

            // For salespeople, no need to map organizations
            // They will be filtered by salesperson column and price_list_id from their customers
            $this->line("  -> Salesperson user mapped (no organization mapping needed)");
        }
    }

    /**
     * Normalize Oracle user name to match existing user names
     * Handle format differences like "ABDUL.REHMAN" -> "Abdul Rehman"
     */
    private function normalizeName(string $oracleName): string
    {
        // Convert dots to spaces and title case
        $normalized = str_replace('.', ' ', $oracleName);
        $normalized = ucwords(strtolower($normalized));
        
        return $normalized;
    }
}