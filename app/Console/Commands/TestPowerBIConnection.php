<?php

namespace App\Console\Commands;

use App\Services\PowerBIService;
use Illuminate\Console\Command;

class TestPowerBIConnection extends Command
{
    protected $signature = 'powerbi:test';
    protected $description = 'Test Power BI connection and configuration';

    public function handle()
    {
        $this->info('Testing Power BI Configuration...');
        $this->newLine();

        // Check environment variables
        $this->info('1. Checking Environment Variables:');
        $clientId = config('powerbi.client_id');
        $clientSecret = config('powerbi.client_secret');
        $tenantId = config('powerbi.tenant_id');
        $workspaceId = config('powerbi.workspace_id');
        $reportId = config('powerbi.report_id');

        $this->line("   Client ID: " . ($clientId ? '✓ Set' : '✗ Missing'));
        if ($clientId) {
            $this->line("      Value: {$clientId}");
        }

        $this->line("   Client Secret: " . ($clientSecret ? '✓ Set' : '✗ Missing'));
        if ($clientSecret) {
            // Show only first 10 and last 4 characters for security
            $masked = substr($clientSecret, 0, 10) . '...' . substr($clientSecret, -4);
            $this->line("      Value: {$masked} (length: " . strlen($clientSecret) . " chars)");
        }

        $this->line("   Tenant ID: " . ($tenantId ? '✓ Set' : '✗ Missing'));
        if ($tenantId) {
            $this->line("      Value: {$tenantId}");
        }

        $this->line("   Workspace ID: " . ($workspaceId ? '✓ Set' : '✗ Missing'));
        if ($workspaceId) {
            $this->line("      Value: {$workspaceId}");
        }

        $this->line("   Report ID: " . ($reportId ? '✓ Set' : '✗ Missing'));
        if ($reportId) {
            $this->line("      Value: {$reportId}");
        }
        $this->newLine();

        if (!$clientId || !$clientSecret || !$tenantId) {
            $this->error('Missing required credentials! Please update your .env file.');
            return Command::FAILURE;
        }

        if (!$workspaceId || !$reportId) {
            $this->warn('Workspace ID and Report ID are not set. You need to add these to .env:');
            $this->line('   POWERBI_WORKSPACE_ID=your_workspace_id');
            $this->line('   POWERBI_REPORT_ID=your_report_id');
            $this->newLine();
            $this->info('To find these IDs:');
            $this->line('   1. Go to https://app.powerbi.com');
            $this->line('   2. Open your workspace and report');
            $this->line('   3. Check the URL: https://app.powerbi.com/groups/{WORKSPACE_ID}/reports/{REPORT_ID}');
            return Command::FAILURE;
        }

        // Test Azure AD authentication
        $this->info('2. Testing Azure AD Authentication:');
        try {
            $powerBIService = new PowerBIService();
            $accessToken = $powerBIService->getAccessToken();

            if ($accessToken) {
                $this->line("   ✓ Successfully obtained access token");
                $this->line("   Token length: " . strlen($accessToken) . " characters");
            } else {
                $this->error('   ✗ Failed to obtain access token');
                return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $this->error('   ✗ Authentication failed: ' . $e->getMessage());
            $this->newLine();
            $this->warn('Common issues:');
            $this->line('   - Invalid client credentials');
            $this->line('   - Azure AD app not configured correctly');
            $this->line('   - Client secret expired');
            return Command::FAILURE;
        }
        $this->newLine();

        // List available workspaces
        $this->info('3. Listing Available Workspaces:');
        try {
            $accessToken = $powerBIService->getAccessToken();
            $response = \Illuminate\Support\Facades\Http::withToken($accessToken)
                ->get('https://api.powerbi.com/v1.0/myorg/groups');

            if ($response->successful()) {
                $workspaces = $response->json()['value'] ?? [];
                if (empty($workspaces)) {
                    $this->warn('   No workspaces found. The app may not have access to any workspaces.');
                } else {
                    $this->line("   ✓ Found " . count($workspaces) . " workspace(s):");
                    foreach (array_slice($workspaces, 0, 5) as $workspace) {
                        $this->line("   • {$workspace['name']} (ID: {$workspace['id']})");
                    }
                    if (count($workspaces) > 5) {
                        $this->line("   ... and " . (count($workspaces) - 5) . " more");
                    }
                }
            }
        } catch (\Exception $e) {
            $this->warn('   Could not list workspaces: ' . $e->getMessage());
        }
        $this->newLine();

        // Test report access
        $this->info('4. Testing Report Access:');
        try {
            // Make direct API call to get detailed error
            $accessToken = $powerBIService->getAccessToken();
            $workspaceId = config('powerbi.workspace_id');
            $reportId = config('powerbi.report_id');
            $url = "https://api.powerbi.com/v1.0/myorg/groups/{$workspaceId}/reports/{$reportId}";

            $response = \Illuminate\Support\Facades\Http::withToken($accessToken)->get($url);

            if ($response->successful()) {
                $reportDetails = $response->json();
                $this->line("   ✓ Successfully retrieved report details");
                $this->line("   Report Name: " . ($reportDetails['name'] ?? 'N/A'));
                $this->line("   Web URL: " . ($reportDetails['webUrl'] ?? 'N/A'));
            } else {
                $this->error('   ✗ Failed to get report');
                $this->line("   HTTP Status: " . $response->status());
                $this->line("   URL: {$url}");

                $errorBody = $response->json();
                if (isset($errorBody['error'])) {
                    $this->line("   Error Code: " . ($errorBody['error']['code'] ?? 'N/A'));
                    $this->line("   Error Message: " . ($errorBody['error']['message'] ?? 'N/A'));
                }

                $this->newLine();
                $this->warn('Possible issues:');
                $this->line('   - Report ID is incorrect');
                $this->line('   - Report does not exist in the workspace');
                $this->line('   - Workspace ID is incorrect');
                $this->line('   - Azure AD app lacks "Report.Read.All" permission');
                $this->line('   - Admin consent not granted for the app');
                return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $this->error('   ✗ Failed to get report: ' . $e->getMessage());
            $this->newLine();
            $this->warn('Possible issues:');
            $this->line('   - Report ID is incorrect');
            $this->line('   - Report does not exist in the workspace');
            $this->line('   - Azure AD app lacks permissions to read the report');
            return Command::FAILURE;
        }
        $this->newLine();

        // Test embed token generation
        $this->info('5. Testing Embed Token Generation:');
        try {
            // Make direct API call to see detailed error
            $accessToken = $powerBIService->getAccessToken();
            $url = "https://api.powerbi.com/v1.0/myorg/groups/{$workspaceId}/reports/{$reportId}/GenerateToken";

            // First try without effective identity
            $this->line("   Attempting without effective identity...");
            $response = \Illuminate\Support\Facades\Http::withToken($accessToken)
                ->post($url, [
                    'accessLevel' => 'View',
                    'allowSaveAs' => false
                ]);

            // If that fails with "requires effective identity" error, try with identity
            if (!$response->successful()) {
                $errorBody = $response->json();
                if (isset($errorBody['error']['message']) &&
                    str_contains($errorBody['error']['message'], 'requires effective identity')) {

                    $this->warn("   Report requires effective identity (RLS enabled or DirectQuery dataset)");

                    // Extract dataset ID from error message
                    $datasetId = null;
                    if (preg_match('/dataset ([a-f0-9\-]{36})/', $errorBody['error']['message'], $matches)) {
                        $datasetId = $matches[1];
                        $this->line("   Detected dataset ID: {$datasetId}");
                    }

                    // Try to get available roles from the dataset
                    $roles = [];
                    if ($datasetId) {
                        $this->line("   Checking dataset for RLS roles...");
                        $rolesUrl = "https://api.powerbi.com/v1.0/myorg/groups/{$workspaceId}/datasets/{$datasetId}";
                        $datasetResponse = \Illuminate\Support\Facades\Http::withToken($accessToken)->get($rolesUrl);

                        if ($datasetResponse->successful()) {
                            $datasetInfo = $datasetResponse->json();
                            $this->line("   Dataset: " . ($datasetInfo['name'] ?? 'Unknown'));

                            // Try to get roles (may not be available via API)
                            $rolesUrl2 = "https://api.powerbi.com/v1.0/myorg/groups/{$workspaceId}/datasets/{$datasetId}/roles";
                            $rolesResponse = \Illuminate\Support\Facades\Http::withToken($accessToken)->get($rolesUrl2);

                            if ($rolesResponse->successful()) {
                                $rolesData = $rolesResponse->json();
                                if (isset($rolesData['value']) && !empty($rolesData['value'])) {
                                    $this->line("   Available RLS roles:");
                                    foreach ($rolesData['value'] as $role) {
                                        $this->line("      • " . $role['name']);
                                        $roles[] = $role['name'];
                                    }
                                }
                            }
                        }
                    }

                    // If we found roles, use the first one; otherwise ask user
                    if (empty($roles)) {
                        $this->newLine();
                        $this->warn("   Could not retrieve RLS roles from API.");
                        $this->line("   Please check your Power BI dataset for configured RLS roles:");
                        $this->line("   1. Go to Power BI Service → Workspace 'MASTER'");
                        $this->line("   2. Find dataset 'QGBI' → Click ⋮ → Security");
                        $this->line("   3. Note the role name(s) listed");
                        $this->newLine();

                        // Try with a common role name as a test
                        $testRoles = ['Admin']; // Common default role
                        $this->line("   Attempting with common role name: 'Admin'...");
                    } else {
                        $testRoles = [$roles[0]];
                        $this->line("   Retrying with effective identity and role: " . implode(', ', $testRoles) . "...");
                    }

                    // Try with a test identity, dataset ID, and roles
                    $response = \Illuminate\Support\Facades\Http::withToken($accessToken)
                        ->post($url, [
                            'accessLevel' => 'View',
                            'allowSaveAs' => false,
                            'identities' => [
                                [
                                    'username' => 'test@example.com', // Test username
                                    'roles' => $testRoles, // RLS roles
                                    'datasets' => $datasetId ? [$datasetId] : [] // Apply to specific dataset
                                ]
                            ]
                        ]);
                }
            }

            if ($response->successful()) {
                $embedToken = $response->json();
                $this->line("   ✓ Successfully generated embed token");
                $this->line("   Token expires at: " . ($embedToken['expiration'] ?? 'N/A'));

                // Check if we needed effective identity
                if (isset($errorBody) && isset($errorBody['error']['message']) &&
                    str_contains($errorBody['error']['message'], 'requires effective identity')) {
                    $this->newLine();
                    $this->info("   ℹ Note: This report requires effective identity (RLS or DirectQuery)");
                    $this->line("   You must pass user email when calling getEmbedToken() in your code.");
                }
            } else {
                $this->error('   ✗ Failed to generate embed token');
                $this->line("   HTTP Status: " . $response->status());
                $this->line("   URL: {$url}");

                $errorBody = $response->json();
                if (isset($errorBody['error'])) {
                    $this->line("   Error Code: " . ($errorBody['error']['code'] ?? 'N/A'));
                    $this->line("   Error Message: " . ($errorBody['error']['message'] ?? 'N/A'));
                }

                $this->newLine();
                $this->warn('Possible issues:');
                $this->line('   - Service principal needs to be added with "Admin" or "Member" role (not Viewer)');
                $this->line('   - "Allow service principals to use read-only admin APIs" might need to be enabled');
                $this->line('   - Try using user delegated flow instead of service principal for embedding');
                return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $this->error('   ✗ Failed to generate embed token: ' . $e->getMessage());
            $this->newLine();
            $this->warn('Possible issues:');
            $this->line('   - Azure AD app lacks proper permissions');
            $this->line('   - Service principal is not added to the workspace');
            return Command::FAILURE;
        }
        $this->newLine();

        // All tests passed
        $this->info('✅ All tests passed! Power BI configuration is correct.');
        $this->newLine();
        $this->line('You can now access the dashboard at: /app/admin/bi-dashboard');

        return Command::SUCCESS;
    }
}
