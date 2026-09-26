<?php

namespace App\Http\Controllers\Api\V1\Operations;

use App\Http\Controllers\Controller;
use App\Http\Resources\ExpenseTypeResource;
use App\Models\ExpenseType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExpenseTypeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $types = ExpenseType::query()
            ->where('is_active', true)
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        return $this->success(
            ExpenseTypeResource::collection($types)->resolve(),
            'Expense types retrieved',
        );
    }
}
