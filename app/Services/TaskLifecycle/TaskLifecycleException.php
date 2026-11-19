<?php

namespace App\Services\TaskLifecycle;

use Exception;
use Illuminate\Http\JsonResponse;

/**
 * Thrown when a task action is rejected by the lifecycle engine — either the
 * acting user isn't allowed to perform it, or the transition itself is invalid.
 * Laravel calls render() automatically, so callers can just let it bubble up.
 */
class TaskLifecycleException extends Exception
{
    public function __construct(string $message, private readonly int $status = 403)
    {
        parent::__construct($message);
    }

    public static function forbidden(string $message): self
    {
        return new self($message, 403);
    }

    public static function invalid(string $message): self
    {
        return new self($message, 422);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], $this->status);
    }
}
