<?php

namespace App\Http\Controllers;

use App\Enums\RoleEnum;
use App\Models\Announcement;
use App\Models\User;
use App\Services\FcmService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Admin-side push announcements.
 *
 * Flow:
 *   1. Admin opens /admin/announcements → list of past sends.
 *   2. Clicks "New" → fills title + body, picks audience (Everyone / by role).
 *   3. Submit persists the row and immediately fires FcmService::sendToUsers
 *      against the matching user set; the FCM call itself runs after the
 *      response so the admin's request returns quickly.
 *   4. Recipient count + sent_at are stamped on the row.
 */
class AnnouncementController extends Controller
{
    public function index(Request $request)
    {
        $announcements = Announcement::with('creator:id,name')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.announcements.index', compact('announcements'));
    }

    public function create()
    {
        return view('admin.announcements.create', [
            'roles' => RoleEnum::asArray(),
        ]);
    }

    public function store(Request $request, FcmService $fcm)
    {
        $data = $request->validate([
            'title'        => 'required|string|max:200',
            'body'         => 'required|string|max:2000',
            'target_type'  => 'required|in:all,role',
            'target_value' => 'nullable|string|max:64|required_if:target_type,role',
        ]);

        // Resolve recipients — only users with a non-null fcm_token are
        // pushable. Anything outside that set is just a no-op.
        $userQuery = User::query()->whereNotNull('fcm_token');
        if ($data['target_type'] === 'role') {
            $userQuery->where('role', $data['target_value']);
        }
        $users = $userQuery->get(['id', 'fcm_token']);

        $announcement = Announcement::create([
            'title'           => trim($data['title']),
            'body'            => trim($data['body']),
            'target_type'     => $data['target_type'],
            'target_value'    => $data['target_type'] === 'role' ? $data['target_value'] : null,
            'created_by'      => auth()->id(),
            'sent_at'         => now(),
            'recipient_count' => $users->count(),
        ]);

        // Fire FCM. sendToUsers dispatches the HTTP round-trip after the
        // current response so the admin sees an instant redirect.
        if ($users->isNotEmpty()) {
            try {
                $fcm->sendToUsers(
                    $users,
                    $announcement->title,
                    $announcement->body,
                    [
                        // Data payload the mobile app can use to deep-link
                        // into an in-app "Announcements" tab.
                        'type'            => 'announcement',
                        'announcement_id' => (string) $announcement->id,
                    ]
                );
            } catch (\Throwable $e) {
                Log::error('Announcement FCM dispatch failed', [
                    'announcement_id' => $announcement->id,
                    'error'           => $e->getMessage(),
                ]);
            }
        }

        notify("Announcement sent to {$users->count()} user(s).", 'success');
        return redirect()->route('announcements.index');
    }

    public function show(Announcement $announcement)
    {
        $announcement->load('creator:id,name');
        return view('admin.announcements.show', compact('announcement'));
    }

    public function destroy(Announcement $announcement)
    {
        $announcement->delete();
        notify('Announcement record deleted (does not recall delivered notifications).', 'success');
        return redirect()->route('announcements.index');
    }
}
