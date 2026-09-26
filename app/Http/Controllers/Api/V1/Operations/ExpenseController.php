<?php

namespace App\Http\Controllers\Api\V1\Operations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operations\StoreExpenseRequest;
use App\Http\Resources\ExpenseResource;
use App\Models\Expense;
use App\Queries\ExpenseQuery;
use App\Services\ExpenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    public function __construct(private ExpenseService $expenseService) {}

    public function index(Request $request): JsonResponse
    {
        $paginated = ExpenseQuery::apply(
            Expense::query()->where('company_id', $this->currentCompany()->id),
            $request->query(),
        )
            ->with(['expenseType', 'router', 'creator'])
            ->paginate($request->integer('per_page', 15));

        return $this->success(
            $this->paginated($paginated, ExpenseResource::collection($paginated->items())->resolve()),
            'Expenses retrieved',
        );
    }

    public function summary(Request $request): JsonResponse
    {
        $summary = $this->expenseService->summary($this->currentCompany(), $request->query());

        return $this->success($summary, 'Expense summary retrieved');
    }

    public function store(StoreExpenseRequest $request): JsonResponse
    {
        $expense = $this->expenseService->create(
            $this->currentCompany(),
            $request->validated(),
            $request->user(),
        );

        return $this->success((new ExpenseResource($expense))->resolve(), 'Expense recorded', 201);
    }

    public function show(Expense $expense): JsonResponse
    {
        $this->assertCompany($expense->company_id);
        $expense->load(['expenseType', 'router', 'creator', 'updater']);

        return $this->success((new ExpenseResource($expense))->resolve(), 'Expense retrieved');
    }

    public function update(StoreExpenseRequest $request, Expense $expense): JsonResponse
    {
        $this->assertCompany($expense->company_id);
        $expense = $this->expenseService->update($expense, $request->validated(), $request->user());

        return $this->success((new ExpenseResource($expense))->resolve(), 'Expense updated');
    }

    public function destroy(Request $request, Expense $expense): JsonResponse
    {
        $this->assertCompany($expense->company_id);
        $this->expenseService->delete($expense, $request->user());

        return $this->success([], 'Expense deleted');
    }

    private function assertCompany(int $companyId): void
    {
        if ($companyId !== $this->currentCompany()->id) {
            abort(404);
        }
    }
}
