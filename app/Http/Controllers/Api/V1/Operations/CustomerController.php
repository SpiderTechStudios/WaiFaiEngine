<?php

namespace App\Http\Controllers\Api\V1\Operations;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Customer::query()
            ->where('company_id', $this->currentCompany()->id)
            ->with([
                'currentAccessGrant.internetPlan',
                'latestDevice',
                'currentNetworkSession',
            ])
            ->withSum([
                'paymentTransactions as total_spent' => fn ($builder) => $builder->where('status', 'paid'),
            ], 'amount');

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhereHas('devices', fn ($devices) => $devices->where('mac_address', 'like', "%{$search}%"))
                    ->orWhereHas('networkSessions', fn ($sessions) => $sessions->where('mac_address', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        $paginated = $query->latest('id')->paginate($request->integer('per_page', 15));

        return $this->success(
            $this->paginated($paginated, CustomerResource::collection($paginated->items())->resolve()),
            'Customers retrieved',
        );
    }

    public function show(Customer $customer): JsonResponse
    {
        if ($customer->company_id !== $this->currentCompany()->id) {
            abort(404);
        }

        $customer->load([
            'currentAccessGrant.internetPlan',
            'latestDevice',
            'currentNetworkSession',
            'paymentTransactions' => fn ($query) => $query->where('status', 'paid')->latest('id'),
        ])->loadSum([
            'paymentTransactions as total_spent' => fn ($builder) => $builder->where('status', 'paid'),
        ], 'amount');

        return $this->success((new CustomerResource($customer))->resolve(), 'Customer retrieved');
    }
}
