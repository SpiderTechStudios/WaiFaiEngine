<?php

namespace App\Notifications;

use App\Models\Withdrawal;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WithdrawalOtpNotification extends Notification
{
    public function __construct(
        public readonly string $code,
        public readonly Withdrawal $withdrawal,
        public readonly string $expiresAt,
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
        $amount = number_format((float) $this->withdrawal->amount, 2);
        $net = number_format((float) $this->withdrawal->net_amount, 2);
        $currency = (string) ($this->withdrawal->currency ?: config('platform.currency', 'TZS'));

        return (new MailMessage)
            ->subject('Your WaiFai withdrawal code: '.$this->code)
            ->greeting('Confirm your withdrawal')
            ->line('Use the code below to confirm your withdrawal of '.$currency.' '.$amount.'.')
            ->line('Amount arriving in your mobile money account: '.$currency.' '.$net.'.')
            ->line('Confirmation code: '.$this->code)
            ->line('This code expires at '.$this->expiresAt.'.')
            ->line('If you did not request this withdrawal, ignore this email and change your password.');
    }
}
