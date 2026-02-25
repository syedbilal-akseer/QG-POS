<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class WhatsAppService
{
    private $baseUrl;
    private $accessToken;
    private $phoneNumberId;
    private $businessAccountId;

    public function __construct()
    {
        $this->baseUrl = config('whatsapp.api_url', 'https://graph.facebook.com/v18.0');
        $this->accessToken = config('whatsapp.access_token');
        $this->phoneNumberId = config('whatsapp.phone_number_id');
        $this->businessAccountId = config('whatsapp.business_account_id');
    }

    /**
     * Send a text message via WhatsApp Business API
     */
    public function sendTextMessage($to, $message)
    {
        try {
            $to = $this->formatPhoneNumber($to);

            if (!$to) {
                throw new \Exception('Invalid phone number format');
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/{$this->phoneNumberId}/messages", [
                        'messaging_product' => 'whatsapp',
                        'to' => $to,
                        'type' => 'text',
                        'text' => [
                            'body' => $message
                        ]
                    ]);

            $responseData = $response->json();

            if ($response->successful()) {
                Log::info('WhatsApp message sent successfully', [
                    'to' => $to,
                    'message_id' => $responseData['messages'][0]['id'] ?? null,
                    'response' => $responseData
                ]);

                return [
                    'success' => true,
                    'message_id' => $responseData['messages'][0]['id'] ?? null,
                    'data' => $responseData
                ];
            } else {
                Log::error('WhatsApp API error', [
                    'to' => $to,
                    'status' => $response->status(),
                    'response' => $responseData
                ]);

                return [
                    'success' => false,
                    'error' => $responseData['error']['message'] ?? 'Unknown error',
                    'error_code' => $responseData['error']['code'] ?? null
                ];
            }

        } catch (\Exception $e) {
            Log::error('WhatsApp service error', [
                'to' => $to,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
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

            if (!$to) {
                throw new \Exception('Invalid phone number format');
            }

            // First, upload the document to get media ID
            $mediaId = $this->uploadMedia($documentPath, 'document');

            if (!$mediaId) {
                throw new \Exception('Failed to upload document to WhatsApp');
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/{$this->phoneNumberId}/messages", [
                        'messaging_product' => 'whatsapp',
                        'to' => $to,
                        'type' => 'document',
                        'document' => [
                            'id' => $mediaId,
                            'filename' => $filename,
                            'caption' => $caption
                        ]
                    ]);

            $responseData = $response->json();

            if ($response->successful()) {
                Log::info('WhatsApp document sent successfully', [
                    'to' => $to,
                    'filename' => $filename,
                    'media_id' => $mediaId,
                    'message_id' => $responseData['messages'][0]['id'] ?? null,
                    'response' => $responseData
                ]);

                return [
                    'success' => true,
                    'message_id' => $responseData['messages'][0]['id'] ?? null,
                    'media_id' => $mediaId,
                    'data' => $responseData
                ];
            } else {
                Log::error('WhatsApp document API error', [
                    'to' => $to,
                    'filename' => $filename,
                    'status' => $response->status(),
                    'response' => $responseData
                ]);

                return [
                    'success' => false,
                    'error' => $responseData['error']['message'] ?? 'Unknown error',
                    'error_code' => $responseData['error']['code'] ?? null
                ];
            }

        } catch (\Exception $e) {
            Log::error('WhatsApp document service error', [
                'to' => $to,
                'filename' => $filename,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Send invoice template message using receipt_ready
     */
    public function sendInvoiceTemplate($to, $invoice)
    {
        $parameters = [
            'body' => [
                $invoice->customer_name ?: 'Valued Customer',
                'Invoice #' . ($invoice->invoice_number ?: 'N/A')
            ]
        ];

        // Try receipt_ready first, if it doesn't exist, the error will be logged
        return $this->sendTemplateMessage($to, 'receipt_ready', 'en_US', $parameters);
    }

    /**
     * Send invoice via WhatsApp with PDF attachment
     */
    public function sendInvoice($to, $invoice, $invoiceUrl = null)
    {
        try {
            $headerParams = [];
            $mediaId = null;
            $pdfSent = false;
            $documentMessageId = null;

            // Upload PDF if exists to use in Template Header
            if ($invoice->pdf_path && Storage::disk('local')->exists($invoice->pdf_path)) {
                $pdfPath = Storage::disk('local')->path($invoice->pdf_path);
                $filename = "Invoice_{$invoice->customer_code}_{$invoice->invoice_number}.pdf";

                // Upload Media
                $mediaId = $this->uploadMedia($pdfPath, 'application/pdf');

                if ($mediaId) {
                    $headerParams[] = [
                        'type' => 'document',
                        'document' => [
                            'id' => $mediaId,
                            'filename' => $filename
                        ]
                    ];
                    $pdfSent = true;
                } else {
                    Log::warning('Failed to upload invoice PDF for template header', ['invoice_id' => $invoice->id]);
                }
            }

            // Prepare Parameters
            $parameters = [
                'body' => [
                    'customer_name' => $invoice->customer_name ?: 'Valued Customer',
                    'invoice_number' => $invoice->invoice_number ?: 'N/A'
                ]
            ];

            if (!empty($headerParams)) {
                $parameters['header'] = $headerParams;
            }

            // Send Template
            // Only try invoice_ready if we successfully uploaded the media document
            if ($mediaId) {
                $textResult = $this->sendTemplateMessage($to, 'invoice_ready', 'en', $parameters);
                $templateUsed = 'invoice_ready';

                // If invoice_ready fails, try fallback
                if (!$textResult['success'] && isset($textResult['error_code']) && in_array($textResult['error_code'], [132001, 100, 132012])) {
                    Log::warning('invoice_ready template failed despite media, falling back to hello_world', [
                        'customer_code' => $invoice->customer_code,
                        'error' => $textResult['error']
                    ]);
                    $mediaId = null; // Mark as failed mechanism
                }
            }

            // Fallback if media upload failed OR invoice_ready failed above
            if (!$mediaId) {
                // We either didn't have media, or the template with media failed.
                Log::info('Sending fallback hello_world template (media missing or previous send failed)', ['phone' => $to]);
                $textResult = $this->sendTemplateMessage($to, 'hello_world', 'en_US');
                $templateUsed = 'hello_world';
                $pdfSent = false;
            }

            if ($textResult['success']) {
                if ($templateUsed === 'invoice_ready') {
                    $documentMessageId = $textResult['message_id'];
                }
            }

            // Log invoice details for reference
            Log::info('Invoice sent via WhatsApp', [
                'customer_name' => $invoice->customer_name,
                'invoice_number' => $invoice->invoice_number,
                'customer_code' => $invoice->customer_code,
                'amount' => $invoice->total_amount,
                'template_used' => $templateUsed,
                'phone' => $to,
                'pdf_included' => $pdfSent
            ]);

            return [
                'success' => $textResult['success'],
                'text_message_id' => $textResult['message_id'] ?? null,
                'document_message_id' => $documentMessageId,
                'pdf_sent' => $pdfSent && $textResult['success'],
                'template_used' => $templateUsed,
                'message' => $textResult['success'] ? 'Invoice sent successfully via WhatsApp!' : 'Failed to send invoice',
                'error' => $textResult['error'] ?? null
            ];

        } catch (\Exception $e) {
            Log::error('WhatsApp invoice service error', [
                'invoice_id' => $invoice->id,
                'to' => $to,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Upload media to WhatsApp Business API
     */
    private function uploadMedia($filePath, $type = 'document')
    {
        try {
            if (!file_exists($filePath)) {
                Log::error('WhatsApp media upload failed - file not found', ['path' => $filePath]);
                return null;
            }

            $fileSize = filesize($filePath);
            $mimeType = mime_content_type($filePath) ?: 'application/pdf'; // Default to pdf if detection fails

            Log::info('Starting WhatsApp media upload', [
                'path' => $filePath,
                'size_bytes' => $fileSize,
                'mime_type' => $mimeType,
                'requested_type' => $type
            ]);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->accessToken,
            ])->timeout(180)
                ->connectTimeout(60)
                ->withOptions(['force_ip_resolve' => 'v4']) // Force IPv4 to avoid IPv6 timeouts
                ->withoutVerifying() // Fix for XAMPP SSL issues
                ->attach('file', fopen($filePath, 'r'), basename($filePath), ['Content-Type' => $mimeType])
                ->post("{$this->baseUrl}/{$this->phoneNumberId}/media", [
                    'messaging_product' => 'whatsapp',
                    'type' => $mimeType,
                ]);

            $responseData = $response->json();

            if ($response->successful()) {
                return $responseData['id'] ?? null;
            }

            Log::error('WhatsApp media upload error', [
                'file_path' => $filePath,
                'status' => $response->status(),
                'response' => $responseData
            ]);

            return null;

        } catch (\Exception $e) {
            Log::error('WhatsApp media upload exception', [
                'file_path' => $filePath,
                'error' => $e->getMessage()
            ]);

            return null;
        }
    }

    /**
     * Create formatted invoice message
     */
    private function createInvoiceMessage($invoice)
    {
        $message = "🧾 *Invoice from QG Distributors*\n\n";
        $message .= "Hello *{$invoice->customer_name}*,\n\n";
        $message .= "Here are your invoice details:\n\n";
        $message .= "📋 *Customer Code:* {$invoice->customer_code}\n";

        if ($invoice->invoice_number) {
            $message .= "📄 *Invoice Number:* {$invoice->invoice_number}\n";
        }

        if ($invoice->total_amount) {
            $message .= "💰 *Amount:* " . number_format($invoice->total_amount, 2) . "\n";
        }

        if ($invoice->customer_phone) {
            $message .= "📞 *Contact:* {$invoice->customer_phone}\n";
        }

        $message .= "\n📎 Please find your invoice PDF attached above.\n\n";
        $message .= "Thank you for your business!\n\n";
        $message .= "*QG Distributors*\n";
        $message .= "📧 Contact us for any queries";

        return $message;
    }

    /**
     * Format phone number for WhatsApp API
     */
    private function formatPhoneNumber($phone)
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
            return '92' . $cleanPhone; // Convert 03XXXXXXXXX to 9203XXXXXXXXX
        }

        if (preg_match('/^021(\d{7,8})$/', $cleanPhone) || preg_match('/^042(\d{7,8})$/', $cleanPhone)) {
            return '92' . $cleanPhone; // Convert landline to 92XXXXXXXXXX
        }

        // For international numbers, ensure they start with country code
        if (strlen($cleanPhone) >= 10 && strlen($cleanPhone) <= 15) {
            // If it doesn't start with a country code, assume Pakistan
            if (!preg_match('/^(1|7|20|27|30|31|32|33|34|36|39|40|41|43|44|45|46|47|48|49|51|52|53|54|55|56|57|58|60|61|62|63|64|65|66|81|82|84|86|90|91|92|93|94|95|98)/', $cleanPhone)) {
                return '92' . $cleanPhone;
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

            if (!$to) {
                Log::error('WhatsApp phone number format failed', [
                    'original' => $originalPhone,
                    'formatted' => $to
                ]);
                throw new \Exception('Invalid phone number format');
            }

            Log::info('WhatsApp phone number formatted', [
                'original' => $originalPhone,
                'formatted' => $to,
                'template' => $templateName
            ]);

            $templateData = [
                'name' => $templateName,
                'language' => [
                    'code' => $language
                ]
            ];

            // Add parameters if provided
            if (!empty($parameters)) {
                $components = [];

                // Header parameters
                if (isset($parameters['header']) && !empty($parameters['header'])) {
                    $headerParams = [];
                    foreach ($parameters['header'] as $param) {
                        if (is_array($param) && isset($param['type'])) {
                            // Handle media types (document, image, video)
                            $headerParams[] = $param;
                        } else {
                            // Default to text
                            $headerParams[] = ['type' => 'text', 'text' => $param];
                        }
                    }

                    $components[] = [
                        'type' => 'header',
                        'parameters' => $headerParams
                    ];
                }

                // Body parameters
                if (isset($parameters['body']) && !empty($parameters['body'])) {
                    $bodyParams = [];
                    foreach ($parameters['body'] as $key => $value) {
                        $paramObj = ['type' => 'text', 'text' => $value];
                        // If key is a string, it's a named parameter
                        if (is_string($key)) {
                            $paramObj['parameter_name'] = $key;
                        }
                        $bodyParams[] = $paramObj;
                    }

                    $components[] = [
                        'type' => 'body',
                        'parameters' => $bodyParams
                    ];
                }

                // Button parameters (for dynamic URLs)
                if (isset($parameters['button']) && !empty($parameters['button'])) {
                    foreach ($parameters['button'] as $index => $button) {
                        if ($button['type'] === 'url' && isset($button['url'])) {
                            $components[] = [
                                'type' => 'button',
                                'sub_type' => 'url',
                                'index' => (string) $index,  // Button index (0-based)
                                'parameters' => [
                                    [
                                        'type' => 'text',
                                        'text' => $button['url']  // Dynamic URL parameter
                                    ]
                                ]
                            ];
                        }
                    }
                }

                if (!empty($components)) {
                    $templateData['components'] = $components;
                }
            }

            $response = Http::timeout(180)
                ->connectTimeout(60)
                ->withoutVerifying()
                ->withOptions(['force_ip_resolve' => 'v4'])
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->accessToken,
                    'Content-Type' => 'application/json',
                ])->post("{$this->baseUrl}/{$this->phoneNumberId}/messages", [
                        'messaging_product' => 'whatsapp',
                        'to' => $to,
                        'type' => 'template',
                        'template' => $templateData
                    ]);

            $responseData = $response->json();

            if ($response->successful()) {
                Log::info('WhatsApp template message sent successfully', [
                    'to' => $to,
                    'template' => $templateName,
                    'message_id' => $responseData['messages'][0]['id'] ?? null,
                    'response' => $responseData
                ]);

                return [
                    'success' => true,
                    'message_id' => $responseData['messages'][0]['id'] ?? null,
                    'data' => $responseData
                ];
            } else {
                Log::error('WhatsApp template API error', [
                    'to' => $to,
                    'template' => $templateName,
                    'status' => $response->status(),
                    'response' => $responseData
                ]);

                return [
                    'success' => false,
                    'error' => $responseData['error']['message'] ?? 'Unknown error',
                    'error_code' => $responseData['error']['code'] ?? null
                ];
            }

        } catch (\Exception $e) {
            Log::error('WhatsApp template service error', [
                'to' => $to,
                'template' => $templateName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
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
     * Get available templates from WhatsApp Business API
     */
    public function getAvailableTemplates()
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Content-Type' => 'application/json',
            ])->get("{$this->baseUrl}/{$this->businessAccountId}/message_templates");

            $responseData = $response->json();

            if ($response->successful()) {
                Log::info('Available WhatsApp templates', ['templates' => $responseData]);
                return [
                    'success' => true,
                    'templates' => $responseData['data'] ?? []
                ];
            } else {
                Log::error('Failed to get templates', ['response' => $responseData]);
                return [
                    'success' => false,
                    'error' => $responseData['error']['message'] ?? 'Unknown error'
                ];
            }
        } catch (\Exception $e) {
            Log::error('Exception getting templates', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Verify WhatsApp Business API configuration
     */
    public function verifyConfiguration()
    {
        $issues = [];

        if (!$this->accessToken) {
            $issues[] = 'WhatsApp access token not configured';
        }

        if (!$this->phoneNumberId) {
            $issues[] = 'WhatsApp phone number ID not configured';
        }

        if (!$this->businessAccountId) {
            $issues[] = 'WhatsApp business account ID not configured';
        }

        return [
            'configured' => empty($issues),
            'issues' => $issues
        ];
    }
}