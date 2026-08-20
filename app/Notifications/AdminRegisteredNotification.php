<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminRegisteredNotification extends Notification
{
    use Queueable;

    public function __construct(
        public User $inviter,
        public string $accountType,
        #[\SensitiveParameter]
        public string $password,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $roleLabel = $this->accountType === 'superadmin' ? 'superadministrator' : 'platform administrator';

        return (new MailMessage)
            ->subject('Your WaiFai '.$roleLabel.' account')
            ->line($this->inviter->name.' created a '.$roleLabel.' account for you.')
            ->line('Email: '.$notifiable->email)
            ->line('Temporary password: '.$this->password)
            ->line('Sign in with these credentials and change your password after logging in.');
    }
}
