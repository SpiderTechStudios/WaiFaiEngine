<?php

namespace App\Http\Controllers\Api\V1\Operations;

use App\Http\Controllers\Controller;
use App\Http\Resources\ExpenseResource;
use App\Models\Expense;
use App\Models\NetworkDevice;
use App\Queries\ExpenseQuery;
use App\Services\ExpenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RouterExpenseController extends Controller
{
    public function __construct(private ExpenseService $expenseService) {}

    public function index(Request $request, NetworkDevice $router): JsonResponse
    {
        if ($router->company_id !== $this->currentCompany()->id || $router->type !== 'router') {
            abort(404);
        }

        $report = $this->expenseService->routerReport($this->currentCompany(), $router, $request->query());

        $paginated = ExpenseQuery::apply(
            Expense::query()
                ->where('company_id', $this->currentCompany()->id)
                ->where('router_id', $router->id),
            $request->query(),
        )
            ->with(['expenseType', 'creator'])
            ->paginate($request->integer('per_page', 15));

        return $this->success([
            'router' => [
                'id' => $router->id,
                'name' => $router->name,
            ],
            'summary' => $report['summary'],
            'breakdown' => $report['breakdown'],
            'expenses' => ExpenseResource::collection($paginated->items())->resolve(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'total' => $paginated->total(),
            ],
        ], 'Router expenses retrieved');
    }
}
