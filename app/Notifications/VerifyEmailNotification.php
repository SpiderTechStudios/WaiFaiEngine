<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;

class VerifyEmailNotification extends VerifyEmail
{
    protected function verificationUrl($notifiable): string
    {
        $hash = sha1($notifiable->getEmailForVerification());

        $apiUrl = URL::temporarySignedRoute(
            'api.v1.auth.email.verify',
            Carbon::now()->addMinutes(Config::get('auth.verification.expire', 60)),
            [
                'id' => $notifiable->getKey(),
                'hash' => $hash,
            ],
        );

        $appUrl = rtrim((string) Config::get('app.url'), '/');
        $frontendUrl = rtrim((string) Config::get('app.frontend_url'), '/');

        if ($frontendUrl === '' || $frontendUrl === $appUrl) {
            return $apiUrl;
        }

        // Keep the signed API query (expires + signature) so the frontend page
        // can forward it to the API unchanged; the signature validates there.
        $query = parse_url($apiUrl, PHP_URL_QUERY);

        return $frontendUrl.'/auth/email/verify/'.$notifiable->getKey().'/'.$hash
            .($query ? '?'.$query : '');
    }
}
