<?php

namespace App\Jobs;

use App\Models\CustomerLedger;
use App\Services\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendWhatsAppLedgerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $ledger;

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

    public function __construct(CustomerLedger $ledger, $phone, $url = null, $templateName = 'invoice_ready', $language = null)
    {
        $this->ledger = $ledger;
        $this->phone = $phone;
        $this->url = $url;
        $this->templateName = $templateName;
        $this->language = $language;
    }

    public function handle(WhatsAppService $whatsappService): void
    {
        set_time_limit(0);

        try {
            if ($this->ledger->whatsapp_status === 'sent') {
                return;
            }

            Log::info('Processing WhatsApp ledger job', [
                'ledger_id' => $this->ledger->id,
                'customer' => $this->ledger->customer_code,
                'phone' => $this->phone,
                'template' => $this->templateName,
            ]);

            $this->ledger->update([
                'whatsapp_status' => 'processing',
                'whatsapp_error' => null,
            ]);

            $result = $whatsappService->sendLedger($this->phone, $this->ledger, $this->url, $this->templateName, $this->language);

            if ($result['success']) {
                $this->ledger->update([
                    'whatsapp_sent_at' => now(),
                    'whatsapp_message_id' => $result['document_message_id'] ?? $result['text_message_id'] ?? null,
                    'whatsapp_status' => 'sent',
                    'whatsapp_error' => null,
                ]);

                Log::info('Background WhatsApp ledger sent successfully', [
                    'ledger_id' => $this->ledger->id,
                    'customer_code' => $this->ledger->customer_code,
                ]);
            } else {
                $this->ledger->update([
                    'whatsapp_status' => 'failed',
                    'whatsapp_error' => $result['error'] ?? 'Unknown Error',
                ]);

                Log::error('Background WhatsApp ledger failed', [
                    'ledger_id' => $this->ledger->id,
                    'customer_code' => $this->ledger->customer_code,
                    'error' => $result['error'] ?? 'Unknown Error',
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Exception in SendWhatsAppLedgerJob', [
                'ledger_id' => $this->ledger->id,
                'error' => $e->getMessage(),
            ]);

            $this->ledger->update([
                'whatsapp_status' => 'failed',
                'whatsapp_error' => $e->getMessage(),
            ]);
        }
    }
}
