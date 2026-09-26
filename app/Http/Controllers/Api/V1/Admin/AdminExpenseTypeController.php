<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminStoreExpenseTypeRequest;
use App\Http\Resources\ExpenseTypeResource;
use App\Models\ExpenseType;
use App\Services\ExpenseTypeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminExpenseTypeController extends Controller
{
    public function __construct(private ExpenseTypeService $expenseTypeService) {}

    public function index(Request $request): JsonResponse
    {
        $paginated = ExpenseType::query()
            ->when($request->filled('category'), fn ($query) => $query->where('category', $request->string('category')))
            ->when($request->has('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = '%'.$request->string('search').'%';
                $query->where(fn ($inner) => $inner->where('name', 'like', $term)->orWhere('description', 'like', $term));
            })
            ->orderBy('category')
            ->orderBy('name')
            ->paginate($request->integer('per_page', 15));

        return $this->success(
            $this->paginated($paginated, ExpenseTypeResource::collection($paginated->items())->resolve()),
            'Expense types retrieved',
        );
    }

    public function store(AdminStoreExpenseTypeRequest $request): JsonResponse
    {
        $type = $this->expenseTypeService->create($request->validated(), $request->user());

        return $this->success((new ExpenseTypeResource($type))->resolve(), 'Expense type created', 201);
    }

    public function show(ExpenseType $expenseType): JsonResponse
    {
        return $this->success((new ExpenseTypeResource($expenseType))->resolve(), 'Expense type retrieved');
    }

    public function update(AdminStoreExpenseTypeRequest $request, ExpenseType $expenseType): JsonResponse
    {
        $type = $this->expenseTypeService->update($expenseType, $request->validated(), $request->user());

        return $this->success((new ExpenseTypeResource($type))->resolve(), 'Expense type updated');
    }

    public function destroy(Request $request, ExpenseType $expenseType): JsonResponse
    {
        $this->expenseTypeService->delete($expenseType, $request->user());

        return $this->success([], 'Expense type deleted');
    }

    public function activate(Request $request, ExpenseType $expenseType): JsonResponse
    {
        $type = $this->expenseTypeService->setActive($expenseType, true, $request->user());

        return $this->success((new ExpenseTypeResource($type))->resolve(), 'Expense type activated');
    }

    public function deactivate(Request $request, ExpenseType $expenseType): JsonResponse
    {
        $type = $this->expenseTypeService->setActive($expenseType, false, $request->user());

        return $this->success((new ExpenseTypeResource($type))->resolve(), 'Expense type deactivated');
    }
}
