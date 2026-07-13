<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\WhatsAppService;

class CheckWhatsAppConfig extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'whatsapp:check-config';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check WhatsApp Business API configuration';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking WhatsApp Business API Configuration...');
        $this->newLine();

        $whatsappService = new WhatsAppService();
        $config = $whatsappService->verifyConfiguration();

        if ($config['configured']) {
            $this->info('✅ WhatsApp Business API is properly configured!');
            
            // Test basic connectivity
            $this->info('📡 Testing API connectivity...');
            
            if ($this->confirm('Do you want to send a test hello_world template message to 923338123171?')) {
                $result = $whatsappService->testConnection();
                
                if ($result['success']) {
                    $this->info('✅ Test message sent successfully!');
                    $this->line("Message ID: {$result['message_id']}");
                } else {
                    $this->error('❌ Test message failed!');
                    $this->line("Error: {$result['error']}");
                }
            }
            
        } else {
            $this->error('❌ WhatsApp Business API configuration is incomplete!');
            $this->newLine();
            
            $this->error('Missing configuration:');
            foreach ($config['issues'] as $issue) {
                $this->line("  • {$issue}");
            }
            
            $this->newLine();
            $this->info('📋 Setup Instructions:');
            $this->line('1. Go to Facebook Developer Console (developers.facebook.com)');
            $this->line('2. Create a new app or use existing one');
            $this->line('3. Add WhatsApp Business API product');
            $this->line('4. Get your credentials and add them to .env file:');
            $this->line('   - WHATSAPP_ACCESS_TOKEN');
            $this->line('   - WHATSAPP_PHONE_NUMBER_ID');
            $this->line('   - WHATSAPP_BUSINESS_ACCOUNT_ID');
            $this->newLine();
            $this->line('5. Update your .env file with the credentials');
            $this->line('6. Run this command again to verify');
        }

        $this->newLine();
        $this->info('Current configuration values:');
        $this->table(
            ['Setting', 'Value', 'Status'],
            [
                ['API URL', config('whatsapp.api_url'), '✅'],
                ['Access Token', config('whatsapp.access_token') ? 'Set (***' . substr(config('whatsapp.access_token'), -4) . ')' : 'Not set', config('whatsapp.access_token') ? '✅' : '❌'],
                ['Phone Number ID', config('whatsapp.phone_number_id') ?: 'Not set', config('whatsapp.phone_number_id') ? '✅' : '❌'],
                ['Business Account ID', config('whatsapp.business_account_id') ?: 'Not set', config('whatsapp.business_account_id') ? '✅' : '❌'],
                ['Max File Size', config('whatsapp.max_file_size', 100) . ' MB', '✅'],
                ['Timeout', config('whatsapp.timeout', 30) . ' seconds', '✅'],
                ['Logging Enabled', config('whatsapp.logging.enabled', true) ? 'Yes' : 'No', '✅'],
            ]
        );

        return $config['configured'] ? 0 : 1;
    }
}