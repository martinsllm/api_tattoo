<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class PendingEmailChangeNotification extends Notification
{
    use Queueable;

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = URL::temporarySignedRoute(
            'verification.verify-change',
            now()->addMinutes(60),
            [
                'id' => $notifiable->id,
                'hash' => sha1($notifiable->pending_email),
            ]
        );

        return (new MailMessage)
            ->subject('Confirme seu novo e-mail — API Tattoo')
            ->greeting('Olá!')
            ->line('Recebemos uma solicitação para alterar o e-mail da sua conta.')
            ->line('Clique no botão abaixo para confirmar o novo endereço.')
            ->action('Confirmar novo e-mail', $url)
            ->line('Se você não solicitou esta alteração, ignore este e-mail.')
            ->salutation('Atenciosamente, API Tattoo');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [];
    }
}
