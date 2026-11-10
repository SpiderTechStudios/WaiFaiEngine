<?php

namespace App\Http\Controllers\Api\V1\Operations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operations\ConsumeVoucherRequest;
use App\Http\Requests\Operations\ExportVouchersWordRequest;
use App\Http\Requests\Operations\StoreVoucherRequest;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\VoucherResource;
use App\Models\RevenueRecord;
use App\Models\Voucher;
use App\Services\VoucherService;
use App\Services\VoucherWordExporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

class VoucherController extends Controller
{
    public function __construct(private VoucherService $voucherService) {}

    public function index(Request $request): JsonResponse
    {
        $companyId = $this->currentCompany()->id;

        $this->expireOverdue($companyId);

        $paginated = Voucher::query()
            ->with(['router', 'internetPlan', 'latestGrant.customer'])
            ->where('company_id', $companyId)
            ->when($request->filled('status'), function ($query) use ($request) {
                $status = $request->string('status')->toString();

                if ($status === Voucher::STATUS_EXPIRED) {
                    $query->where(function ($inner) {
                        $inner->where('status', Voucher::STATUS_EXPIRED)
                            ->orWhere(function ($used) {
                                $used->where('uses_count', '>', 0)
                                    ->whereHas('latestGrant', function ($grant) {
                                        $grant->where(function ($access) {
                                            $access->where('status', 'expired')
                                                ->orWhere(function ($window) {
                                                    $window->whereNotNull('expires_at')
                                                        ->where('expires_at', '<=', now());
                                                });
                                        });
                                    });
                            });
                    });
                } elseif ($status === Voucher::STATUS_ACTIVE) {
                    $query->where('status', Voucher::STATUS_ACTIVE)
                        ->where(function ($inner) {
                            $inner->where('uses_count', 0)
                                ->orWhereHas('latestGrant', function ($grant) {
                                    $grant->where('status', 'active')
                                        ->where(function ($window) {
                                            $window->whereNull('expires_at')
                                                ->orWhere('expires_at', '>', now());
                                        });
                                });
                        });
                } else {
                    $query->where('status', $status);
                }
            })
            ->when(
                in_array($request->input('usage'), ['used', 'unused'], true),
                fn ($query) => $request->input('usage') === 'used'
                    ? $query->where('uses_count', '>', 0)
                    : $query->where('uses_count', 0),
            )
            ->when($request->filled('router_id'), fn ($query) => $query->where('network_device_id', $request->integer('router_id')))
            ->when($request->filled('package_id'), fn ($query) => $query->where('internet_plan_id', $request->integer('package_id')))
            ->when($request->filled('used_from') || $request->filled('used_to'), function ($query) use ($request) {
                $timezone = $this->currentCompany()->reportingTimezone();
                $from = $this->dateBoundary($request->input('used_from'), $timezone, true);
                $to = $this->dateBoundary($request->input('used_to'), $timezone, false);

                $query->whereHas('latestGrant', function ($grant) use ($from, $to) {
                    if ($from) {
                        $grant->where('created_at', '>=', $from);
                    }
                    if ($to) {
                        $grant->where('created_at', '<=', $to);
                    }
                });
            })
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = trim((string) $request->input('search'));
                $like = '%'.$term.'%';
                $digits = preg_replace('/\D+/', '', $term) ?: '';
                $phoneTerms = [];

                if (strlen($digits) >= 3) {
                    $phoneTerms[] = '%'.$digits.'%';
                    if (str_starts_with($digits, '0')) {
                        $phoneTerms[] = '%255'.substr($digits, 1).'%';
                    } elseif (str_starts_with($digits, '255')) {
                        $phoneTerms[] = '%0'.substr($digits, 3).'%';
                    }
                }

                $query->where(function ($builder) use ($like, $phoneTerms) {
                    $builder->where('code', 'like', $like)
                        ->orWhere('note', 'like', $like)
                        ->orWhereHas('internetPlan', fn ($plan) => $plan->where('name', 'like', $like))
                        ->orWhereHas('latestGrant.customer', function ($customer) use ($like, $phoneTerms) {
                            $customer->where('name', 'like', $like);

                            foreach ($phoneTerms as $phoneTerm) {
                                $customer->orWhere('phone', 'like', $phoneTerm);
                            }
                        });
                });
            })
            ->latest('id')
            ->paginate($request->integer('per_page', 15));

        return $this->success(
            $this->paginated($paginated, VoucherResource::collection($paginated->items())->resolve()),
            'Vouchers retrieved',
        );
    }

    public function summary(): JsonResponse
    {
        $companyId = $this->currentCompany()->id;

        $this->expireOverdue($companyId);

        $query = Voucher::query()->where('company_id', $companyId);

        $total = (clone $query)->count();
        $used = (clone $query)->where('uses_count', '>', 0)->count();
        $unused = (clone $query)->where('uses_count', 0)->count();
        $available = (clone $query)
            ->where('status', Voucher::STATUS_ACTIVE)
            ->where('uses_count', 0)
            ->count();
        $expired = (clone $query)->where(function ($inner) {
            $inner->where('status', Voucher::STATUS_EXPIRED)
                ->orWhere(function ($used) {
                    $used->where('uses_count', '>', 0)
                        ->whereHas('latestGrant', function ($grant) {
                            $grant->where(function ($access) {
                                $access->where('status', 'expired')
                                    ->orWhere(function ($window) {
                                        $window->whereNotNull('expires_at')
                                            ->where('expires_at', '<=', now());
                                    });
                            });
                        });
                });
        })->count();
        $revoked = (clone $query)->where('status', Voucher::STATUS_REVOKED)->count();
        $soldValue = (float) RevenueRecord::query()
            ->where('company_id', $companyId)
            ->where('source', 'voucher')
            ->sum('amount');

        return $this->success([
            'total' => $total,
            'used' => $used,
            'unused' => $unused,
            'available' => $available,
            'expired' => $expired,
            'revoked' => $revoked,
            'sold_value' => $soldValue,
            'currency' => (string) config('platform.currency', 'TZS'),
        ], 'Voucher summary');
    }

    public function store(StoreVoucherRequest $request): JsonResponse
    {
        $vouchers = $this->voucherService->create(
            $this->currentCompany(),
            $request->validated(),
            $request->user(),
        );

        return $this->success([
            'quantity' => $vouchers->count(),
            'items' => VoucherResource::collection($vouchers)->resolve(),
        ], 'Vouchers created', 201);
    }

    public function exportWord(ExportVouchersWordRequest $request, VoucherWordExporter $exporter): Response
    {
        $data = $request->validated();

        $query = Voucher::query()
            ->with(['router', 'internetPlan'])
            ->where('company_id', $this->currentCompany()->id);

        if (! empty($data['ids'])) {
            $query->whereIn('id', $data['ids']);
        }

        if (! empty($data['status'])) {
            $query->where('status', $data['status']);
        }

        if (! empty($data['usage'])) {
            $data['usage'] === 'used'
                ? $query->where('uses_count', '>', 0)
                : $query->where('uses_count', 0);
        }

        if (! empty($data['router_id'])) {
            $query->where('network_device_id', $data['router_id']);
        }

        if (! empty($data['package_id'])) {
            $query->where('internet_plan_id', $data['package_id']);
        }

        if (! empty($data['search'])) {
            $term = '%'.$data['search'].'%';

            $query->where(function ($builder) use ($term) {
                $builder->where('code', 'like', $term)
                    ->orWhere('note', 'like', $term)
                    ->orWhereHas('internetPlan', fn ($plan) => $plan->where('name', 'like', $term));
            });
        }

        $vouchers = $query->orderBy('id')->limit(5000)->get();

        if ($vouchers->isEmpty()) {
            abort(404, 'No vouchers to export.');
        }

        $binary = $exporter->export($this->currentCompany(), $vouchers);

        return response($binary, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'Content-Disposition' => 'attachment; filename="'.$exporter->filename().'"',
        ]);
    }

    public function revoke(Request $request, Voucher $voucher): JsonResponse
    {
        $this->assertCompany($voucher->company_id);

        $voucher = $this->voucherService->revoke($voucher, $request->user());

        return $this->success((new VoucherResource($voucher))->resolve(), 'Voucher revoked');
    }

    public function consume(ConsumeVoucherRequest $request, Voucher $voucher): JsonResponse
    {
        $this->assertCompany($voucher->company_id);

        $result = $this->voucherService->consume($voucher, $request->validated(), $request->user());

        return $this->success([
            'voucher' => (new VoucherResource($result['voucher']))->resolve(),
            'customer' => (new CustomerResource($result['customer']))->resolve(),
            'access_grant' => [
                'id' => $result['access_grant']->id,
                'status' => $result['access_grant']->status,
                'starts_at' => $result['access_grant']->starts_at,
                'expires_at' => $result['access_grant']->expires_at,
                'source' => $result['access_grant']->source,
            ],
        ], 'Voucher consumed');
    }

    private function dateBoundary(mixed $value, string $timezone, bool $start): ?Carbon
    {
        $date = (string) $value;

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return null;
        }

        return Carbon::parse($date, $timezone)
            ->{$start ? 'startOfDay' : 'endOfDay'}()
            ->utc();
    }

    private function expireOverdue(int $companyId): void
    {
        Voucher::query()
            ->where('company_id', $companyId)
            ->where('status', Voucher::STATUS_ACTIVE)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->update(['status' => Voucher::STATUS_EXPIRED]);
    }

    private function assertCompany(int $companyId): void
    {
        if ($companyId !== $this->currentCompany()->id) {
            abort(404);
        }
    }
}
