<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\NotificationRead;
use App\Models\ProjectMember;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $projectIds = $user->isAdmin()
            ? null
            : $this->visibleProjectIds($user->id);

        $notifications = Notification::with(['project', 'creator'])
            ->forUser($user->id, $projectIds)
            ->limit(100)
            ->get()
            ->map(fn ($n) => [
                'id' => $n->id,
                'project_id' => $n->project_id,
                'project_name' => $n->project?->name,
                'type' => $n->type,
                'title' => $n->title,
                'body' => $n->body,
                'action_url' => $n->action_url,
                'created_at' => $n->created_at,
                'read_at' => $n->reads->first()?->read_at,
            ]);

        $unreadCount = Notification::forUser($user->id, $projectIds)
            ->unreadForUser($user->id)
            ->count();

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
        ]);
    }

    public function markRead(Request $request, int $id)
    {
        $user = $request->user();

        $notification = Notification::findOrFail($id);

        if (!$this->isVisibleTo($notification, $user)) {
            abort(404);
        }

        NotificationRead::updateOrCreate(
            ['notification_id' => $notification->id, 'user_id' => $user->id],
            ['read_at' => now()]
        );

        return response()->json(['status' => 'ok']);
    }

    public function markAllRead(Request $request)
    {
        $user = $request->user();

        $projectIds = $user->isAdmin() ? null : $this->visibleProjectIds($user->id);

        $unreadIds = Notification::forUser($user->id, $projectIds)
            ->unreadForUser($user->id)
            ->pluck('id');

        if ($unreadIds->isEmpty()) {
            return response()->json(['status' => 'ok', 'marked' => 0]);
        }

        $now = now();
        $rows = $unreadIds->map(fn ($notificationId) => [
            'notification_id' => $notificationId,
            'user_id' => $user->id,
            'read_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ])->toArray();

        NotificationRead::upsert($rows, ['notification_id', 'user_id'], ['read_at', 'updated_at']);

        return response()->json(['status' => 'ok', 'marked' => count($rows)]);
    }

    private function visibleProjectIds(int $userId): array
    {
        return ProjectMember::where('user_id', $userId)
            ->pluck('project_id')
            ->push(1)
            ->unique()
            ->toArray();
    }

    private function isVisibleTo(Notification $notification, $user): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        $projectIds = $this->visibleProjectIds($user->id);

        return in_array($notification->project_id, $projectIds)
            && ($notification->user_id === null || $notification->user_id === $user->id);
    }
}