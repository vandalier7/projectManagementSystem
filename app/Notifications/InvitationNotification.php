<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected string $plaintextToken,
        protected string $email,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = rtrim(config('app.frontend_url'), '/') . '/signup?token=' . $this->plaintextToken
            . '&email=' . urlencode($this->email);

        return (new MailMessage)
            ->subject('You\'ve been invited to ProjectDashboard')
            ->greeting('Welcome!')
            ->line('An admin has created an account for you on ProjectDashboard.')
            ->action('Complete Your Account', $url)
            ->line('This link expires in 48 hours. If you weren\'t expecting this invite, you can ignore this email.');
    }

    /**
     * Route notifications for the mail channel.
     * Since Invitation isn't a Notifiable model, we notify via Notification::route() — see controller.
     */
}