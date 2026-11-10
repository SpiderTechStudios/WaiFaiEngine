<?php

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$v = App\Models\Voucher::where('code', 'like', '%547237%')->orWhere('note', 'like', '%547237%')->first();
if (! $v) {
    echo "voucher not found\n";
    exit;
}

echo "VOUCHER\n".json_encode($v->toArray(), JSON_PRETTY_PRINT)."\n\n";

$grants = App\Models\AccessGrant::where('voucher_id', $v->id)->get();
echo "GRANTS\n".json_encode($grants->toArray(), JSON_PRETTY_PRINT)."\n\n";

$sessions = App\Models\NetworkSession::where('customer_id', $v->customer_id)
    ->orWhereIn('access_grant_id', $grants->pluck('id'))
    ->get();
echo "SESSIONS\n".json_encode($sessions->toArray(), JSON_PRETTY_PRINT)."\n";
