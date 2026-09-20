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
        $name = $notifiable->name ?? 'there';
        $platform = PlatformSettings::name();

        return (new MailMessage)
            ->subject("Sign in to the customer portal — {$platform}")
            ->greeting("Hello {$name},")
            ->line('Click the button below to sign in to the customer portal. No password is required.')
            ->action('Open the portal', $this->url)
            ->line("This link is valid for {$this->expiresMinutes} minutes and can be used only once.")
            ->line('If you did not request this email, you can ignore it.');
    }
}
