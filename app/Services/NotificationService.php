<?php

namespace App\Services;

use App\Models\Notification;

class NotificationService
{
	/**
	 * Create a notification for an action.
	 *
	 * If $notifyAll is true, user_id is left null (broadcast — all
	 * project members see it, per the existing model). If false,
	 * it's targeted to $targetUserId only.
	 *
	 * $notifyAll is expected to come straight from the request (the
	 * lead/admin's toggle on the frontend) — this service doesn't
	 * decide that, it just applies it.
	 */
	public static function send(
		int $projectId,
		string $type,
		string $title,
		?string $body,
		?string $actionUrl,
		int $createdBy,
		bool $notifyAll,
		?int $targetUserId = null,
	): Notification {
		return Notification::create([
			'project_id' => $projectId,
			'user_id' => $notifyAll ? null : $targetUserId,
			'created_by' => $createdBy,
			'type' => $type,
			'title' => $title,
			'body' => $body,
			'action_url' => $actionUrl,
		]);
	}
}