<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Company;
use App\Models\Device;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CartService
{
    public function current(Company $company, User $user): Cart
    {
        return Cart::query()->firstOrCreate(
            [
                'company_id' => $company->id,
                'status' => Cart::STATUS_OPEN,
            ],
            ['user_id' => $user->id],
        )->load(['items.device.brand', 'items.device.category']);
    }

    public function add(Company $company, User $user, int $deviceId, int $quantity): Cart
    {
        $device = $this->purchasableDevice($deviceId);
        $this->assertQuantity($device, $quantity);

        return DB::transaction(function () use ($company, $user, $device, $quantity) {
            $cart = $this->current($company, $user);
            $item = $cart->items()->where('device_id', $device->id)->first();
            $nextQuantity = $quantity + (int) ($item?->quantity ?? 0);
            $this->assertQuantity($device, $nextQuantity);

            $cart->items()->updateOrCreate(
                ['device_id' => $device->id],
                [
                    'quantity' => $nextQuantity,
                    'unit_price' => $device->price,
                ],
            );

            return $this->current($company, $user);
        });
    }

    public function updateQuantity(Company $company, CartItem $item, int $quantity): Cart
    {
        $this->assertCompanyItem($company, $item);
        $device = $this->purchasableDevice($item->device_id);
        $this->assertQuantity($device, $quantity);

        $item->forceFill([
            'quantity' => $quantity,
            'unit_price' => $device->price,
        ])->save();

        return $this->current($company, $item->cart->user);
    }

    public function remove(Company $company, CartItem $item): Cart
    {
        $this->assertCompanyItem($company, $item);
        $cart = $item->cart;
        $item->delete();

        return $this->current($company, $cart->user);
    }

    public function clear(Company $company, User $user): Cart
    {
        $cart = $this->current($company, $user);
        $cart->items()->delete();

        return $this->current($company, $user);
    }

    public function purchasableDevice(int $deviceId): Device
    {
        $device = Device::query()->whereKey($deviceId)->first();

        if (! $device || ! $device->is_active || $device->price === null) {
            throw ValidationException::withMessages([
                'device_id' => ['This device is not available for purchase.'],
            ]);
        }

        return $device;
    }

    public function assertQuantity(Device $device, int $quantity): void
    {
        if ($quantity < 1) {
            throw ValidationException::withMessages([
                'quantity' => ['Quantity must be at least 1.'],
            ]);
        }

        if ($quantity > (int) $device->stock_quantity) {
            throw ValidationException::withMessages([
                'quantity' => ['Only '.$device->stock_quantity.' units are available.'],
            ]);
        }
    }

    private function assertCompanyItem(Company $company, CartItem $item): void
    {
        $item->loadMissing('cart');

        if ((int) $item->cart->company_id !== (int) $company->id) {
            abort(404, 'Cart item not found.');
        }
    }
}
