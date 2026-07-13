<?php
namespace App\Jobs;

use App\Models\Invoice;
use App\Services\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendWhatsAppInvoiceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $invoice;
    protected $phone;
    protected $url;
    protected $templateName;
    protected $language;

    /**
     * The number of times the job may be attempted.
     */
    public $tries = 5;

    /**
     * The number of seconds the job can run before timing out.
     */
    public $timeout = 900;

    /**
     * Create a new job instance.
     */
    public function __construct(Invoice $invoice, $phone, $url = null, $templateName = 'invoice_ready', $language = null)
    {
        $this->invoice = $invoice;
        $this->phone = $phone;
        $this->url = $url;
        $this->templateName = $templateName;
        $this->language = $language;
    }

    /**
     * Execute the job.
     */
    public function handle(WhatsAppService $whatsappService): void
    {
        // Prevent timeout during long-running uploads or multiple retries
        set_time_limit(0);
        
        try {
            // Check if already sent successfully to avoid duplicates
            if ($this->invoice->whatsapp_status === 'sent') {
                return;
            }

            Log::info('Processing WhatsApp invoice job', [
                'invoice_id' => $this->invoice->id,
                'customer' => $this->invoice->customer_code,
                'phone' => $this->phone,
                'template' => $this->templateName
            ]);

            // Mark as processing
            $this->invoice->update([
                'whatsapp_status' => 'processing',
                'whatsapp_error' => null
            ]);

            $result = $whatsappService->sendInvoice($this->phone, $this->invoice, $this->url, $this->templateName, $this->language);

            if ($result['success']) {
                $this->invoice->update([
                    'whatsapp_sent_at' => now(),
                    'whatsapp_message_id' => $result['document_message_id'] ?? $result['text_message_id'] ?? null,
                    'whatsapp_status' => 'sent',
                    'whatsapp_error' => null
                ]);

                Log::info('Background WhatsApp invoice sent successfully', [
                    'invoice_id' => $this->invoice->id,
                    'customer_code' => $this->invoice->customer_code
                ]);
            } else {
                $this->invoice->update([
                    'whatsapp_status' => 'failed',
                    'whatsapp_error' => $result['error'] ?? 'Unknown Error'
                ]);

                Log::error('Background WhatsApp invoice failed', [
                    'invoice_id' => $this->invoice->id,
                    'customer_code' => $this->invoice->customer_code,
                    'error' => $result['error'] ?? 'Unknown Error'
                ]);
                
                // Throw exception to trigger queue retry if needed
                // But since we have internal retries in WhatsAppService, one job failure means it really failed
            }
        } catch (\Exception $e) {
            Log::error('Exception in SendWhatsAppInvoiceJob', [
                'invoice_id' => $this->invoice->id,
                'error' => $e->getMessage()
            ]);
            
            $this->invoice->update([
                'whatsapp_status' => 'failed',
                'whatsapp_error' => $e->getMessage()
            ]);
            
            // We don't re-throw here because we've already handled the failure 
            // and marked it in our local database. Let the job finish peacefully.
        }
    }
}
