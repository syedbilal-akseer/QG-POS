<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PowerBIService
{
    protected $clientId;
    protected $clientSecret;
    protected $tenantId;
    protected $workspaceId;
    protected $reportId;
    protected $tokenUrl;
    protected $apiUrl;
    protected $scope;

    public function __construct()
    {
        $this->clientId = config('powerbi.client_id');
        $this->clientSecret = config('powerbi.client_secret');
        $this->tenantId = config('powerbi.tenant_id');
        $this->workspaceId = config('powerbi.workspace_id');
        $this->reportId = config('powerbi.report_id');
        $this->tokenUrl = config('powerbi.token_url');
        $this->apiUrl = config('powerbi.api_url');
        $this->scope = config('powerbi.scope');
    }

    /**
     * Get Azure AD access token for Power BI API
     */
    public function getAccessToken()
    {
        $cacheKey = 'powerbi_access_token';

        return Cache::remember($cacheKey, config('powerbi.token_cache_duration'), function () {
            try {
                $response = Http::asForm()->post($this->tokenUrl, [
                    'grant_type' => 'client_credentials',
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'scope' => $this->scope,
                ]);

                if ($response->successful()) {
                    return $response->json()['access_token'];
                }

                Log::error('Power BI: Failed to get access token', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);

                throw new \Exception('Failed to get Power BI access token');
            } catch (\Exception $e) {
                Log::error('Power BI: Exception getting access token', [
                    'message' => $e->getMessage()
                ]);
                throw $e;
            }
        });
    }

    /**
     * Get embed token for a specific report
     */
    public function getEmbedToken($username = null, $roles = [])
    {
        try {
            $accessToken = $this->getAccessToken();

            $url = "{$this->apiUrl}/groups/{$this->workspaceId}/reports/{$this->reportId}/GenerateToken";

            // Build request payload
            $payload = [
                'accessLevel' => 'View',
                'allowSaveAs' => false
            ];

            // If username is provided, add effective identity for RLS
            // This is required when using service principals with RLS-enabled reports
            if ($username) {
                // Get dataset IDs from the report
                $reportDetails = $this->getReportDetails();
                $datasetId = $reportDetails['datasetId'] ?? null;

                if (!$datasetId) {
                    Log::error('Power BI: Dataset ID not found in report details');
                    throw new \Exception('Dataset ID not found for RLS configuration');
                }

                $payload['identities'] = [
                    [
                        'username' => $username,
                        'roles' => $roles,
                        'datasets' => [$datasetId] // Specify dataset ID for RLS
                    ]
                ];

                // Log detailed RLS configuration
                Log::info('Power BI: RLS Token Request', [
                    'username' => $username,
                    'roles' => $roles,
                    'dataset_id' => $datasetId,
                    'report_id' => $this->reportId,
                    'payload' => $payload
                ]);
            }

            $response = Http::withToken($accessToken)
                ->post($url, $payload);

            if ($response->successful()) {
                $tokenData = $response->json();

                // Log successful token generation
                Log::info('Power BI: Embed Token Generated Successfully', [
                    'username' => $username,
                    'expiration' => $tokenData['expiration'] ?? 'unknown',
                    'has_identity' => isset($payload['identities'])
                ]);

                return $tokenData;
            }

            Log::error('Power BI: Failed to get embed token', [
                'status' => $response->status(),
                'body' => $response->body(),
                'username' => $username,
                'roles' => $roles
            ]);

            throw new \Exception('Failed to get Power BI embed token');
        } catch (\Exception $e) {
            Log::error('Power BI: Exception getting embed token', [
                'message' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get report details
     */
    public function getReportDetails()
    {
        try {
            $accessToken = $this->getAccessToken();

            $url = "{$this->apiUrl}/groups/{$this->workspaceId}/reports/{$this->reportId}";

            $response = Http::withToken($accessToken)->get($url);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Power BI: Failed to get report details', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            throw new \Exception('Failed to get Power BI report details');
        } catch (\Exception $e) {
            Log::error('Power BI: Exception getting report details', [
                'message' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get embed configuration for the report
     */
    public function getEmbedConfig($username = null, $roles = ['National'])
    {
        // Get embed token (with or without RLS depending on username)
        $embedToken = $this->getEmbedToken($username, $roles);
        $reportDetails = $this->getReportDetails();

        // Log the configuration for debugging
        if ($username) {
            Log::info('Power BI Embed Configuration (With RLS)', [
                'username' => $username,
                'roles' => $roles,
                'report_id' => $this->reportId
            ]);
        } else {
            Log::info('Power BI Embed Configuration (No RLS - All Data)', [
                'filtering' => 'Client-side only',
                'report_id' => $this->reportId
            ]);
        }

        return [
            'type' => 'report',
            'id' => $this->reportId,
            'embedUrl' => $reportDetails['embedUrl'],
            'accessToken' => $embedToken['token'],
            'tokenExpiry' => $embedToken['expiration'],
            'workspaceId' => $this->workspaceId,
            'roles' => $roles, // Include roles in config for frontend reference
        ];
    }
}
