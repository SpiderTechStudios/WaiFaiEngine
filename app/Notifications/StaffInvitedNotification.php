<?php

namespace App\Notifications;

use App\Models\Company;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Password;

class StaffInvitedNotification extends Notification
{
    use Queueable;

    public function __construct(public Company $company, public User $inviter) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $token = Password::broker()->createToken($notifiable);

        return (new MailMessage)
            ->subject('You have been invited to '.$this->company->name)
            ->line($this->inviter->name.' invited you to join '.$this->company->name.'.')
            ->line('Use this token with the password reset endpoint to set your password.')
            ->line('Token: '.$token);
    }
}
