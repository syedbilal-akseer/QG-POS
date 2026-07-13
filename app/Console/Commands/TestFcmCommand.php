<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\FcmService;
use Illuminate\Console\Command;

class TestFcmCommand extends Command
{
    protected $signature = 'fcm:test
                            {--token=  : Send to a raw FCM token}
                            {--user=   : Send to user id}
                            {--title=  : Notification title (default: "Test")}
                            {--body=   : Notification body  (default: "Hello from QG POS")}';

    protected $description = 'Send a test push notification via FCM';

    public function handle(FcmService $fcm): int
    {
        $title = $this->option('title') ?: 'Test';
        $body  = $this->option('body')  ?: 'Hello from QG POS';

        if ($t = $this->option('token')) {
            $ok = $fcm->sendToToken($t, $title, $body, ['type' => 'test']);
            $this->{$ok ? 'info' : 'error'}($ok ? 'Sent.' : 'Failed — see log.');
            return $ok ? self::SUCCESS : self::FAILURE;
        }

        if ($id = $this->option('user')) {
            $user = User::find($id);
            if (!$user) {
                $this->error("User {$id} not found");
                return self::FAILURE;
            }
            if (!$user->fcm_token) {
                $this->error("User {$id} has no fcm_token saved");
                return self::FAILURE;
            }
            $ok = $fcm->sendToUser($user, $title, $body, ['type' => 'test']);
            $this->{$ok ? 'info' : 'error'}($ok ? "Sent to {$user->name}." : 'Failed — see log.');
            return $ok ? self::SUCCESS : self::FAILURE;
        }

        $this->error('Pass --token=... or --user=ID');
        return self::FAILURE;
    }
}
