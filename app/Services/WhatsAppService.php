<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class WhatsAppService
{
    private $baseUrl;

    private $accessToken;

    private $phoneNumberId;

    private $businessAccountId;

    private $appId;

    public function __construct()
    {
        $this->baseUrl = config('whatsapp.api_url', 'https://graph.facebook.com/v22.0');
        $this->accessToken = config('whatsapp.access_token');
        $this->phoneNumberId = config('whatsapp.phone_number_id');
        $this->businessAccountId = config('whatsapp.business_account_id');
        $this->appId = config('whatsapp.app_id');
    }

    /**
     * Send a text message via WhatsApp Business API
     */
    public function sendTextMessage($to, $message)
    {
        try {
            $to = $this->formatPhoneNumber($to);

            if (! $to) {
                throw new \Exception('Invalid phone number format');
            }

            $response = Http::timeout(120)->connectTimeout(60)->withHeaders([
                'Authorization' => 'Bearer '.$this->accessToken,
                'Content-Type' => 'application/json',
            ])->withOptions(['force_ip_resolve' => 'v4'])->post("{$this->baseUrl}/{$this->phoneNumberId}/messages", [
                'messaging_product' => 'whatsapp',
                'to' => $to,
                'type' => 'text',
                'text' => [
                    'body' => $message,
                ],
            ]);

            $responseData = $response->json();

            if ($response->successful()) {
                Log::info('WhatsApp message sent successfully', [
                    'to' => $to,
                    'message_id' => $responseData['messages'][0]['id'] ?? null,
                    'response' => $responseData,
                ]);

                return [
                    'success' => true,
                    'message_id' => $responseData['messages'][0]['id'] ?? null,
                    'data' => $responseData,
                ];
            } else {
                Log::error('WhatsApp API error', [
                    'to' => $to,
                    'status' => $response->status(),
                    'response' => $responseData,
                ]);

                return [
                    'success' => false,
                    'error' => $responseData['error']['message'] ?? 'Unknown error',
                    'error_code' => $responseData['error']['code'] ?? null,
                ];
            }

        } catch (\Exception $e) {
            Log::error('WhatsApp service error', [
                'to' => $to,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Send a document via WhatsApp Business API
     */
    public function sendDocument($to, $documentPath, $filename, $caption = '')
    {
        try {
            $to = $this->formatPhoneNumber($to);

            if (! $to) {
                throw new \Exception('Invalid phone number format');
            }

            // First, upload the document to get media ID
            $mediaId = $this->uploadMedia($documentPath, 'document');

            if (! $mediaId) {
                throw new \Exception('Failed to upload document to WhatsApp');
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->accessToken,
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/{$this->phoneNumberId}/messages", [
                'messaging_product' => 'whatsapp',
                'to' => $to,
                'type' => 'document',
                'document' => [
                    'id' => $mediaId,
                    'filename' => $filename,
                    'caption' => $caption,
                ],
            ]);

            $responseData = $response->json();

            if ($response->successful()) {
                Log::info('WhatsApp document sent successfully', [
                    'to' => $to,
                    'filename' => $filename,
                    'media_id' => $mediaId,
                    'message_id' => $responseData['messages'][0]['id'] ?? null,
                    'response' => $responseData,
                ]);

                return [
                    'success' => true,
                    'message_id' => $responseData['messages'][0]['id'] ?? null,
                    'media_id' => $mediaId,
                    'data' => $responseData,
                ];
            } else {
                Log::error('WhatsApp document API error', [
                    'to' => $to,
                    'filename' => $filename,
                    'status' => $response->status(),
                    'response' => $responseData,
                ]);

                return [
                    'success' => false,
                    'error' => $responseData['error']['message'] ?? 'Unknown error',
                    'error_code' => $responseData['error']['code'] ?? null,
                ];
            }

        } catch (\Exception $e) {
            Log::error('WhatsApp document service error', [
                'to' => $to,
                'filename' => $filename,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Send invoice template message using receipt_ready
     */
    public function sendInvoiceTemplate($to, $invoice)
    {
        $startDateStr = $invoice->start_date ? $invoice->start_date->format('d-M-Y') : ($invoice->invoice_date ? $invoice->invoice_date->format('d-M-Y') : date('d-M-Y'));
        $endDateStr = $invoice->end_date ? $invoice->end_date->format('d-M-Y') : $startDateStr;

        $parameters = [
            'body' => [
                $invoice->customer_name ?: 'Valued Customer',
                $startDateStr.' to '.$endDateStr,
            ],
        ];

        return $this->sendTemplateMessage($to, 'invoice_ready', 'en_US', $parameters);
    }

    /**
     * Send invoice via WhatsApp with PDF attachment using a specific template
     */
    public function sendInvoice($to, $invoice, $invoiceUrl = null, $templateName = 'invoice_ready', $language = null)
    {
        try {
            // Auto-detect language if not provided
            if (! $language) {
                if ($templateName === 'invoice_urdu') {
                    $language = 'ur';
                } else {
                    $language = 'en_US';
                }
            }

            $headerParams = [];
            $mediaId = null;
            $pdfSent = false;
            $documentMessageId = null;

            // Upload PDF if exists to use in Template Header
            if ($invoice->pdf_path && Storage::disk('local')->exists($invoice->pdf_path)) {
                $pdfPath = Storage::disk('local')->path($invoice->pdf_path);

                // Construct a clean filename for the WhatsApp document
                $cleanName = preg_replace('/[^A-Za-z0-9]/', '_', $invoice->customer_name);
                $filename = "Invoice_{$cleanName}_{$invoice->invoice_number}.pdf";

                // Upload Media
                $mediaId = $this->uploadMedia($pdfPath, 'document');

                if ($mediaId) {
                    $headerParams[] = [
                        'type' => 'document',
                        'document' => [
                            'id' => $mediaId,
                            'filename' => $filename,
                        ],
                    ];
                    $pdfSent = true;
                } else {
                    Log::warning('Failed to upload invoice PDF for template header', ['invoice_id' => $invoice->id]);
                }
            }

            // Prepare Parameters
            // English (invoice_ready):  {{1}} Customer Name, {{2}} "StartDate to EndDate"
            // Urdu    (invoice_urdu):   {{1}} Customer Name, {{2}} StartDate, {{3}} EndDate
            //                          Template text: {{2}} سے {{3}}
            $startDateStr = $invoice->start_date ? $invoice->start_date->format('d-M-Y') : ($invoice->invoice_date ? $invoice->invoice_date->format('d-M-Y') : date('d-M-Y'));
            $endDateStr = $invoice->end_date ? $invoice->end_date->format('d-M-Y') : $startDateStr;

            if ($templateName === 'invoice_urdu') {
                // Urdu template: {{1}} Customer Name, {{2}} Start date, {{3}} End date, {{4}} Customer Code
                $parameters = [
                    'body' => [
                        $invoice->customer_name ?: 'Valued Customer',
                        $startDateStr,
                        $endDateStr,
                        $invoice->customer_code ?: '-',
                    ],
                ];
            } else {
                // English template: {{1}} Customer Name, {{2}} Date range, {{3}} Customer Code
                $parameters = [
                    'body' => [
                        $invoice->customer_name ?: 'Valued Customer',
                        $startDateStr.' to '.$endDateStr,
                        $invoice->customer_code ?: '-',
                    ],
                ];
            }

            if (! empty($headerParams)) {
                $parameters['header'] = $headerParams;
            }

            // Send Template
            // We only try to send if we have a Media ID or Handle
            if ($mediaId) {
                $textResult = $this->sendTemplateMessage($to, $templateName, $language, $parameters);
                $templateUsed = $templateName;
            } else {
                Log::warning('No media ID generated, skipping template send', [
                    'customer_code' => $invoice->customer_code,
                    'template' => $templateName,
                ]);
                $textResult = ['success' => false, 'error' => 'Media upload failed'];
                $templateUsed = 'none';
            }

            if ($textResult['success']) {
                $documentMessageId = $textResult['message_id'];
            }

            // Log invoice details for reference
            Log::info('Invoice sent via WhatsApp (Template)', [
                'customer_name' => $invoice->customer_name,
                'invoice_number' => $invoice->invoice_number,
                'customer_code' => $invoice->customer_code,
                'template_used' => $templateUsed,
                'language' => $language,
                'phone' => $to,
                'pdf_included' => $pdfSent,
            ]);

            return [
                'success' => $textResult['success'],
                'text_message_id' => $textResult['message_id'] ?? null,
                'document_message_id' => $documentMessageId,
                'pdf_sent' => $pdfSent && $textResult['success'],
                'template_used' => $templateUsed,
                'message' => $textResult['success'] ? 'Invoice sent successfully!' : 'Failed to send invoice',
                'error' => $textResult['error'] ?? null,
            ];

        } catch (\Exception $e) {
            Log::error('WhatsApp invoice service error', [
                'invoice_id' => $invoice->id,
                'to' => $to,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Send a customer ledger PDF via WhatsApp with a PDF attachment, reusing
     * the same approved templates as sendInvoice() (no ledger-specific
     * template is approved yet). $ledger needs: pdf_path, customer_name,
     * customer_code, period_from, period_to, id.
     */
    public function sendLedger($to, $ledger, $ledgerUrl = null, $templateName = 'invoice_ready', $language = null)
    {
        try {
            if (! $language) {
                $language = $templateName === 'invoice_urdu' ? 'ur' : 'en_US';
            }

            $headerParams = [];
            $mediaId = null;
            $pdfSent = false;
            $documentMessageId = null;

            if ($ledger->pdf_path && Storage::disk('local')->exists($ledger->pdf_path)) {
                $pdfPath = Storage::disk('local')->path($ledger->pdf_path);

                $cleanName = preg_replace('/[^A-Za-z0-9]/', '_', $ledger->customer_name);
                $periodFrom = $ledger->period_from ? $ledger->period_from->format('Ymd') : 'na';
                $periodTo = $ledger->period_to ? $ledger->period_to->format('Ymd') : 'na';
                $filename = "Ledger_{$cleanName}_{$periodFrom}_{$periodTo}.pdf";

                $mediaId = $this->uploadMedia($pdfPath, 'document');

                if ($mediaId) {
                    $headerParams[] = [
                        'type' => 'document',
                        'document' => [
                            'id' => $mediaId,
                            'filename' => $filename,
                        ],
                    ];
                    $pdfSent = true;
                } else {
                    Log::warning('Failed to upload ledger PDF for template header', ['ledger_id' => $ledger->id]);
                }
            }

            $startDateStr = $ledger->period_from ? $ledger->period_from->format('d-M-Y') : date('d-M-Y');
            $endDateStr = $ledger->period_to ? $ledger->period_to->format('d-M-Y') : $startDateStr;

            if ($templateName === 'invoice_urdu') {
                $parameters = [
                    'body' => [
                        $ledger->customer_name ?: 'Valued Customer',
                        $startDateStr,
                        $endDateStr,
                        $ledger->customer_code ?: '-',
                    ],
                ];
            } else {
                $parameters = [
                    'body' => [
                        $ledger->customer_name ?: 'Valued Customer',
                        $startDateStr.' to '.$endDateStr,
                        $ledger->customer_code ?: '-',
                    ],
                ];
            }

            if (! empty($headerParams)) {
                $parameters['header'] = $headerParams;
            }

            if ($mediaId) {
                $textResult = $this->sendTemplateMessage($to, $templateName, $language, $parameters);
                $templateUsed = $templateName;
            } else {
                Log::warning('No media ID generated, skipping ledger template send', [
                    'customer_code' => $ledger->customer_code,
                    'template' => $templateName,
                ]);
                $textResult = ['success' => false, 'error' => 'Media upload failed'];
                $templateUsed = 'none';
            }

            if ($textResult['success']) {
                $documentMessageId = $textResult['message_id'];
            }

            Log::info('Ledger sent via WhatsApp (Template)', [
                'customer_name' => $ledger->customer_name,
                'customer_code' => $ledger->customer_code,
                'template_used' => $templateUsed,
                'language' => $language,
                'phone' => $to,
                'pdf_included' => $pdfSent,
            ]);

            return [
                'success' => $textResult['success'],
                'text_message_id' => $textResult['message_id'] ?? null,
                'document_message_id' => $documentMessageId,
                'pdf_sent' => $pdfSent && $textResult['success'],
                'template_used' => $templateUsed,
                'message' => $textResult['success'] ? 'Ledger sent successfully!' : 'Failed to send ledger',
                'error' => $textResult['error'] ?? null,
            ];
        } catch (\Exception $e) {
            Log::error('WhatsApp ledger service error', [
                'ledger_id' => $ledger->id,
                'to' => $to,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Upload media to WhatsApp Business API to get a permanent Media ID
     */
    private function uploadMedia($filePath, $type = 'document')
    {
        $maxRetries = 3;
        $retryCount = 0;
        $id = null;
        $lastError = null;

        while ($retryCount < $maxRetries) {
            try {
                if (! file_exists($filePath)) {
                    Log::error('WhatsApp media upload failed - file not found', ['path' => $filePath]);

                    return null;
                }

                $mimeType = mime_content_type($filePath) ?: 'application/pdf';

                Log::info('Uploading media to WhatsApp (Standard API)', [
                    'path' => $filePath,
                    'mime' => $mimeType,
                    'attempt' => $retryCount + 1,
                ]);

                // Max-generosity timeout for heavy media (5 minutes)
                // Using long-shot retries and disabling expect headers for Windows/XAMPP stability
                $response = Http::timeout(300)
                    ->connectTimeout(60)
                    ->withoutVerifying()
                    ->withHeaders([
                        'Authorization' => 'Bearer '.$this->accessToken,
                    ])
                    ->withOptions([
                        'force_ip_resolve' => 'v4',
                        'expect' => false, // Correct way to disable 'Expect: 100-continue' and fix 417 error
                        'curl' => [
                            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                        ],
                    ])
                    ->attach('file', fopen($filePath, 'r'), basename($filePath), ['Content-Type' => $mimeType])
                    ->attach('messaging_product', 'whatsapp')
                    ->post("{$this->baseUrl}/{$this->phoneNumberId}/media");

                $responseData = $response->json();

                if ($response->successful()) {
                    // Return either the ID or the Handle, whichever Meta provided
                    $id = $responseData['id'] ?? $responseData['h'] ?? null;
                    if ($id) {
                        Log::info('WhatsApp Media capture successful', ['media_id' => $id, 'attempt' => $retryCount + 1]);

                        return $id;
                    }
                }

                $lastError = [
                    'status' => $response->status(),
                    'response' => $responseData,
                ];

                Log::warning('WhatsApp media upload attempt failed', array_merge($lastError, ['attempt' => $retryCount + 1]));

                // If it's a 408 Timeout or 5xx, we should definitely retry
                if (! in_array($response->status(), [408, 500, 502, 503, 504])) {
                    // For other errors, maybe check if it's worth retrying
                    // But for now, let's retry all transient-looking errors
                }

            } catch (\Exception $e) {
                $lastError = ['error' => $e->getMessage()];
                Log::error('WhatsApp media upload exception', array_merge($lastError, ['attempt' => $retryCount + 1]));
            }

            $retryCount++;
            if ($retryCount < $maxRetries) {
                $delay = $retryCount * 2; // 2s, 4s, 6s...
                sleep($delay);
            }
        }

        Log::error('WhatsApp media upload failed after all retries', $lastError ?: []);

        return null;
    }

    /**
     * Create formatted invoice message
     */
    private function createInvoiceMessage($invoice)
    {
        $startDateStr = $invoice->start_date ? $invoice->start_date->format('d-M-Y') : ($invoice->invoice_date ? $invoice->invoice_date->format('d-M-Y') : date('d-M-Y'));
        $endDateStr = $invoice->end_date ? $invoice->end_date->format('d-M-Y') : $startDateStr;
        $date = $startDateStr.' to '.$endDateStr;

        $message = "Assalam o Alaikum !!!\n\n";
        $message .= 'Dear *'.($invoice->customer_name ?: 'Customer')."*,\n\n";
        $message .= "Your invoice(s) for *$date* is/are ready. Please find the details in the attachment.\n";
        $message .= "If you have any questions, feel free to reply to this message.\n\n";
        $message .= "Best regards,\n";
        $message .= '*Quadri Group*';

        return $message;
    }

    /**
     * Format phone number for WhatsApp API
     */
    public function formatPhoneNumber($phone)
    {
        // Remove all non-digit characters
        $cleanPhone = preg_replace('/\D/', '', $phone);

        if (empty($cleanPhone)) {
            return null;
        }

        // Handle Pakistani numbers
        if (preg_match('/^92(\d{10})$/', $cleanPhone)) {
            return $cleanPhone; // Already in correct format: 92XXXXXXXXXX
        }

        if (preg_match('/^03(\d{9})$/', $cleanPhone)) {
            return '92'.$cleanPhone; // Convert 03XXXXXXXXX to 9203XXXXXXXXX
        }

        if (preg_match('/^021(\d{7,8})$/', $cleanPhone) || preg_match('/^042(\d{7,8})$/', $cleanPhone)) {
            return '92'.$cleanPhone; // Convert landline to 92XXXXXXXXXX
        }

        // For international numbers, ensure they start with country code
        if (strlen($cleanPhone) >= 10 && strlen($cleanPhone) <= 15) {
            // If it doesn't start with a country code, assume Pakistan
            if (! preg_match('/^(1|7|20|27|30|31|32|33|34|36|39|40|41|43|44|45|46|47|48|49|51|52|53|54|55|56|57|58|60|61|62|63|64|65|66|81|82|84|86|90|91|92|93|94|95|98)/', $cleanPhone)) {
                return '92'.$cleanPhone;
            }

            return $cleanPhone;
        }

        return null; // Invalid format
    }

    /**
     * Send a template message (like hello_world)
     */
    public function sendTemplateMessage($to, $templateName = 'hello_world', $language = 'en_US', $parameters = [])
    {
        try {
            $originalPhone = $to;
            $to = $this->formatPhoneNumber($to);

            if (! $to) {
                Log::error('WhatsApp phone number format failed', [
                    'original' => $originalPhone,
                    'formatted' => $to,
                ]);
                throw new \Exception('Invalid phone number format');
            }

            Log::info('WhatsApp phone number formatted', [
                'original' => $originalPhone,
                'formatted' => $to,
                'template' => $templateName,
            ]);

            $templateData = [
                'name' => $templateName,
                'language' => [
                    'code' => $language,
                ],
            ];

            // Add parameters if provided
            if (! empty($parameters)) {
                $components = [];

                // Header parameters
                if (isset($parameters['header']) && ! empty($parameters['header'])) {
                    $headerParams = [];
                    foreach ($parameters['header'] as $param) {
                        if (is_array($param) && isset($param['type'])) {
                            // If it's a media type, we need to decide if it's an ID or a Link
                            $type = $param['type'];
                            $mediaValue = $param[$type]['id'] ?? null;

                            // If the value starts with 4: (Handle), Meta expects it in the 'link' field for some versions
                            // but usually numeric IDs go in 'id'.
                            if ($mediaValue && strpos($mediaValue, '4:') === 0) {
                                $param[$type]['link'] = $mediaValue;
                                unset($param[$type]['id']);
                            }

                            $headerParams[] = $param;
                        } else {
                            // Default to text
                            $headerParams[] = ['type' => 'text', 'text' => $param];
                        }
                    }

                    $components[] = [
                        'type' => 'header',
                        'parameters' => $headerParams,
                    ];
                }

                // Body parameters
                if (isset($parameters['body']) && ! empty($parameters['body'])) {
                    $bodyParams = [];
                    foreach ($parameters['body'] as $key => $value) {
                        // Meta drops body parameters with empty/whitespace-only text,
                        // which then triggers (#132000) "number of params" mismatch.
                        // Always send a non-empty placeholder.
                        $text = trim((string) $value);
                        if ($text === '') {
                            $text = '-';
                        }
                        $paramObj = ['type' => 'text', 'text' => $text];
                        // If key is a string, it's a named parameter
                        if (is_string($key)) {
                            $paramObj['parameter_name'] = $key;
                        }
                        $bodyParams[] = $paramObj;
                    }

                    $components[] = [
                        'type' => 'body',
                        'parameters' => $bodyParams,
                    ];
                }

                // Button parameters (for dynamic URLs)
                if (isset($parameters['button']) && ! empty($parameters['button'])) {
                    foreach ($parameters['button'] as $index => $button) {
                        if ($button['type'] === 'url' && isset($button['url'])) {
                            $components[] = [
                                'type' => 'button',
                                'sub_type' => 'url',
                                'index' => (string) $index,  // Button index (0-based)
                                'parameters' => [
                                    [
                                        'type' => 'text',
                                        'text' => $button['url'],  // Dynamic URL parameter
                                    ],
                                ],
                            ];
                        }
                    }
                }

                if (! empty($components)) {
                    $templateData['components'] = $components;
                }
            }

            $response = Http::timeout(180)
                ->connectTimeout(60)
                ->withoutVerifying()
                ->withOptions(['force_ip_resolve' => 'v4'])
                ->withHeaders([
                    'Authorization' => 'Bearer '.$this->accessToken,
                    'Content-Type' => 'application/json',
                ])->post("{$this->baseUrl}/{$this->phoneNumberId}/messages", [
                    'messaging_product' => 'whatsapp',
                    'to' => $to,
                    'type' => 'template',
                    'template' => $templateData,
                ]);

            $responseData = $response->json();

            if ($response->successful()) {
                Log::info('WhatsApp template message sent successfully', [
                    'to' => $to,
                    'template' => $templateName,
                    'message_id' => $responseData['messages'][0]['id'] ?? null,
                    'response' => $responseData,
                ]);

                return [
                    'success' => true,
                    'message_id' => $responseData['messages'][0]['id'] ?? null,
                    'data' => $responseData,
                ];
            } else {
                Log::error('WhatsApp template API error', [
                    'to' => $to,
                    'template' => $templateName,
                    'status' => $response->status(),
                    'response' => $responseData,
                ]);

                return [
                    'success' => false,
                    'error' => $responseData['error']['message'] ?? 'Unknown error',
                    'error_code' => $responseData['error']['code'] ?? null,
                ];
            }

        } catch (\Exception $e) {
            Log::error('WhatsApp template service error', [
                'to' => $to,
                'template' => $templateName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Test connection with hello_world template
     */
    public function testConnection($testPhoneNumber = '923338123171')
    {
        return $this->sendTemplateMessage($testPhoneNumber, 'hello_world', 'en_US');
    }

    /**
     * Get available templates from WhatsApp Business API (with caching)
     */
    public function getAvailableTemplates()
    {
        try {
            // Try to get templates from cache first (4 hours)
            $cacheKey = 'whatsapp_message_templates_'.$this->businessAccountId;

            if (Cache::has($cacheKey)) {
                return [
                    'success' => true,
                    'templates' => Cache::get($cacheKey),
                    'source' => 'cache',
                ];
            }

            // Fetch from API with increased total and connection timeouts
            $response = Http::timeout(120)->connectTimeout(60)->withHeaders([
                'Authorization' => 'Bearer '.$this->accessToken,
                'Content-Type' => 'application/json',
            ])->withOptions(['force_ip_resolve' => 'v4'])->get("{$this->baseUrl}/{$this->businessAccountId}/message_templates");

            $responseData = $response->json();

            if ($response->successful()) {
                $templates = $responseData['data'] ?? [];

                // Cache for 4 hours to avoid hitting the API frequently
                Cache::put($cacheKey, $templates, now()->addHours(4));

                Log::info('WhatsApp templates fetched and cached successfully');

                return [
                    'success' => true,
                    'templates' => $templates,
                    'source' => 'api',
                ];
            } else {
                Log::error('Failed to fetch WhatsApp templates', ['response' => $responseData]);

                // Fallback to minimal hardcoded templates if API fails
                return $this->getFallbackTemplates('API failed: '.($responseData['error']['message'] ?? 'Unknown'));
            }
        } catch (\Exception $e) {
            Log::error('Exception getting WhatsApp templates', ['error' => $e->getMessage()]);

            return $this->getFallbackTemplates('Exception: '.$e->getMessage());
        }
    }

    /**
     * Fallback templates in case of API failure
     */
    private function getFallbackTemplates($error = null)
    {
        Log::warning('Using hardcoded fallback templates due to API issue: '.$error);

        return [
            'success' => true, // Still return true so UI can show the hardcoded ones
            'templates' => [
                ['name' => 'invoice_ready', 'language' => 'en_US', 'status' => 'APPROVED'],
                ['name' => 'invoice_urdu', 'language' => 'ur', 'status' => 'APPROVED'],
            ],
            'source' => 'fallback',
            'error_message' => $error,
        ];
    }

    /**
     * Verify WhatsApp Business API configuration
     */
    public function verifyConfiguration()
    {
        $issues = [];

        if (! $this->accessToken) {
            $issues[] = 'WhatsApp access token not configured';
        }

        if (! $this->phoneNumberId) {
            $issues[] = 'WhatsApp phone number ID not configured';
        }

        if (! $this->businessAccountId) {
            $issues[] = 'WhatsApp business account ID not configured';
        }

        return [
            'configured' => empty($issues),
            'issues' => $issues,
        ];
    }
}
