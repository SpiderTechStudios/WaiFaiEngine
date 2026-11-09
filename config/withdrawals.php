<?php

return [
    /*
    | Withdrawal confirmation OTP.
    */
    'otp_ttl_minutes' => (int) env('WITHDRAWAL_OTP_TTL_MINUTES', 10),
    'otp_max_attempts' => (int) env('WITHDRAWAL_OTP_MAX_ATTEMPTS', 5),
    'otp_resend_seconds' => (int) env('WITHDRAWAL_OTP_RESEND_SECONDS', 60),

    /*
    | Minimum net amount (after platform + provider fees) that can be paid out.
    */
    'minimum_net_amount' => (float) env('WITHDRAWAL_MINIMUM_NET_AMOUNT', 1),
];
