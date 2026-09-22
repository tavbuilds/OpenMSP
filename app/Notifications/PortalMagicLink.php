<?php

namespace App\Notifications;

use App\Support\PlatformSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PortalMagicLink extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $url,
        public int $expiresMinutes = 30,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = $notifiable->name ?? null;
        $platform = PlatformSettings::name();

        return (new MailMessage)
            ->subject(__('Sign in to the customer portal — :platform', ['platform' => $platform]))
            // "Hello there," does not translate; drop the name instead.
            ->greeting(filled($name) ? __('Hello :name,', ['name' => $name]) : __('Hello,'))
            ->line(__('Click the button below to sign in to the customer portal. No password is required.'))
            ->action(__('Open the portal'), $this->url)
            ->line(__('This link is valid for :minutes minutes and can be used only once.', ['minutes' => $this->expiresMinutes]))
            ->line(__('If you did not request this email, you can ignore it.'));
    }
}
