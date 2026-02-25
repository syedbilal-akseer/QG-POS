<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\PowerBIService;
use Illuminate\Http\Request;

class BIDashboardController extends Controller
{
    protected $powerBIService;

    public function __construct(PowerBIService $powerBIService)
    {
        $this->powerBIService = $powerBIService;
    }

    /**
     * Display the BI Dashboard with embedded Power BI report
     */
    public function index()
    {
        try {
            $user = auth()->user();

            // Determine RLS role based on user location
            $rlsRoles = $this->getUserRlsRoles($user);

            // Determine which email to use for effective identity
            // For salespeople, use their actual email to filter their personal data
            // For managers/admins, use the service account
            $userEmail = $this->getEffectiveIdentityEmail($user);

            // Get embed configuration from Power BI with user-specific RLS roles
            $embedConfig = $this->powerBIService->getEmbedConfig($userEmail, $rlsRoles);

            // Add page information for navigation
            $reportPages = [
                ['id' => '278e096ded9af02300d1', 'name' => 'AR Summary'],
                ['id' => 'fcac9c10c7c6243ca015', 'name' => 'Target vs Acheived'],
                ['id' => '90a836ec7301f06da67d', 'name' => 'Sales 360'],
            ];

            $pageTitle = "Business Intelligence Dashboard";
            $userName = $user->name; // Pass user name for display

            // Determine client-side filters based on user role
            $filters = $this->getUserFilters($user);

            return view('admin.bi-dashboard', compact('embedConfig', 'pageTitle', 'reportPages', 'rlsRoles', 'userName', 'filters'));
        } catch (\Exception $e) {
            // Log the error
            \Log::error('Power BI Dashboard Error: ' . $e->getMessage());

            // Return error view
            return view('admin.bi-dashboard-error', [
                'pageTitle' => 'BI Dashboard Error',
                'error' => 'Failed to load Power BI dashboard. Please contact administrator.'
            ]);
        }
    }

    /**
     * Determine which RLS roles to apply based on user's location and role
     * Everyone uses "National" role - filtering is done by effective identity
     */
    protected function getUserRlsRoles($user): array
    {
        // Everyone uses National role
        // The effective identity (username) determines what they see
        return ['National'];
    }

    /**
     * Get the effective identity for Power BI RLS
     *
     * UPDATED: No longer using Power BI RLS - all users get ALL data from Power BI
     * Filtering is done on the client side (JavaScript) after data is loaded
     */
    protected function getEffectiveIdentityEmail($user): string
    {
        // Return null to disable RLS - all users get all data from Power BI
        // Client-side JavaScript will filter the data based on user role
        return null;
    }

    /**
     * Get client-side filters based on user role
     * These filters will be applied via Power BI JavaScript API
     */
    protected function getUserFilters($user): array
    {
        $filters = [
            'type' => 'none', // none, salesperson, location
            'salespersonName' => null,
            'ouIds' => [],
            'location' => null,
        ];

        // Admin and Sales Head see all data (no filter)
        if ($user->isAdmin() || $user->isSalesHead()) {
            return $filters;
        }

        // CMD KHI sees only Karachi data
        if ($user->isCmdKhi()) {
            $filters['type'] = 'location';
            $filters['location'] = 'KHI';
            $filters['ouIds'] = [102, 103, 104, 105, 106];
            return $filters;
        }

        // CMD LHR sees only Lahore data
        if ($user->isCmdLhr()) {
            $filters['type'] = 'location';
            $filters['location'] = 'LHR';
            $filters['ouIds'] = [108, 109];
            return $filters;
        }

        // Individual salespeople see only their own data
        if ($user->isSalesPerson() || $user->isHOD() || $user->isManager()) {
            $filters['type'] = 'salesperson';
            $filters['salespersonName'] = $user->name; // e.g., "Tajammul Ahmed"
            return $filters;
        }

        // Default: filter by location for other users
        if ($user->isKHIUser()) {
            $filters['type'] = 'location';
            $filters['location'] = 'KHI';
            $filters['ouIds'] = [102, 103, 104, 105, 106];
            return $filters;
        }

        if ($user->isLHRUser()) {
            $filters['type'] = 'location';
            $filters['location'] = 'LHR';
            $filters['ouIds'] = [108, 109];
            return $filters;
        }

        // No match - return no filter (show all)
        return $filters;
    }

    /**
     * API endpoint to refresh embed token
     */
    public function refreshToken()
    {
        try {
            $user = auth()->user();

            // Determine RLS role based on user location and role
            $rlsRoles = $this->getUserRlsRoles($user);

            // Get effective identity email (salespeople use their email, others use service account)
            $userEmail = $this->getEffectiveIdentityEmail($user);

            // Get embed configuration from Power BI with user-specific RLS roles
            $embedConfig = $this->powerBIService->getEmbedConfig($userEmail, $rlsRoles);

            return response()->json([
                'success' => true,
                'data' => $embedConfig
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to refresh token'
            ], 500);
        }
    }

    /**
     * Save Power BI report structure to file
     */
    public function saveReportStructure(Request $request)
    {
        try {
            $reportData = $request->all();
            $user = auth()->user();

            // Add user info to the report
            $reportData['user'] = [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role ?? 'unknown',
            ];

            // Create filename with timestamp
            $filename = 'powerbi_structure_' . date('Y-m-d_His') . '.json';
            $filepath = storage_path('app/powerbi/' . $filename);

            // Create directory if it doesn't exist
            if (!file_exists(storage_path('app/powerbi'))) {
                mkdir(storage_path('app/powerbi'), 0755, true);
            }

            // Save as JSON
            file_put_contents($filepath, json_encode($reportData, JSON_PRETTY_PRINT));

            // Also create a readable text version
            $txtFilename = 'powerbi_structure_' . date('Y-m-d_His') . '.txt';
            $txtFilepath = storage_path('app/powerbi/' . $txtFilename);

            $txtContent = "POWER BI REPORT STRUCTURE\n";
            $txtContent .= "Generated: " . date('Y-m-d H:i:s') . "\n";
            $txtContent .= "User: {$user->name} ({$user->email})\n";
            $txtContent .= "User Filter Type: " . ($reportData['userFilter']['type'] ?? 'none') . "\n";
            $txtContent .= str_repeat("=", 80) . "\n\n";

            if (isset($reportData['pages'])) {
                foreach ($reportData['pages'] as $index => $page) {
                    $txtContent .= "PAGE " . ($index + 1) . ": {$page['displayName']}\n";
                    $txtContent .= "  Name: {$page['name']}\n";
                    $txtContent .= "  Active: " . ($page['isActive'] ? 'Yes' : 'No') . "\n";
                    $txtContent .= "  Visuals: " . count($page['visuals']) . "\n";

                    if (!empty($page['filters']) && is_array($page['filters'])) {
                        $txtContent .= "  Page Filters:\n";
                        foreach ($page['filters'] as $filter) {
                            if (is_array($filter) && isset($filter['target'])) {
                                $txtContent .= "    - Table: {$filter['target']['table']}, Column: {$filter['target']['column']}\n";
                            }
                        }
                    }

                    $txtContent .= "\n";
                }
            }

            file_put_contents($txtFilepath, $txtContent);

            \Log::info('Power BI structure saved', [
                'user' => $user->name,
                'json_file' => $filename,
                'txt_file' => $txtFilename
            ]);

            return response()->json([
                'success' => true,
                'file' => $filename,
                'txt_file' => $txtFilename,
                'path' => $filepath,
                'download_url' => url('/admin/bi-dashboard/download-structure/' . $filename),
                'txt_download_url' => url('/admin/bi-dashboard/download-structure/' . $txtFilename)
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to save Power BI structure: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to save structure: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download saved Power BI structure file
     */
    public function downloadStructure($filename)
    {
        $filepath = storage_path('app/powerbi/' . $filename);

        if (!file_exists($filepath)) {
            abort(404, 'File not found');
        }

        return response()->download($filepath);
    }

    /**
     * Show diagnostic page for Power BI RLS configuration
     */
    public function diagnostic()
    {
        $user = auth()->user();

        // Get RLS configuration
        $rlsRoles = $this->getUserRlsRoles($user);
        $effectiveIdentity = $this->getEffectiveIdentityEmail($user);
        $roleName = $user->getRoleName() ?? 'unknown';

        // Read recent logs related to Power BI
        $recentLogs = [];
        $logFile = storage_path('logs/laravel.log');
        if (file_exists($logFile)) {
            $logs = file($logFile);
            $logs = array_reverse($logs); // Most recent first

            $count = 0;
            foreach ($logs as $log) {
                if (str_contains($log, 'Power BI') && $count < 50) {
                    $recentLogs[] = $log;
                    $count++;
                }
            }
        }

        return view('admin.bi-dashboard-diagnostic', [
            'pageTitle' => 'Power BI RLS Diagnostic',
            'user' => $user,
            'rlsRoles' => $rlsRoles,
            'effectiveIdentity' => $effectiveIdentity,
            'roleName' => $roleName,
            'recentLogs' => $recentLogs
        ]);
    }

    /**
     * Clear Power BI cache
     */
    public function clearCache()
    {
        try {
            \Cache::forget('powerbi_access_token');

            \Log::info('Power BI cache cleared', [
                'user' => auth()->user()->name,
                'timestamp' => now()
            ]);

            return redirect()
                ->route('admin.bi-dashboard.diagnostic')
                ->with('success', 'Power BI cache cleared successfully. Next dashboard load will fetch fresh token.');

        } catch (\Exception $e) {
            \Log::error('Failed to clear Power BI cache: ' . $e->getMessage());

            return redirect()
                ->route('admin.bi-dashboard.diagnostic')
                ->with('error', 'Failed to clear Power BI cache: ' . $e->getMessage());
        }
    }
}
