<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Support\CompanyContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

abstract class Controller
{
    public static function errror($data = [], $message = 'Something went wrong', $code = 500)
    {
        return static::error($data, $message, $code);
    }

    public static function error($data = [], $message = 'Something went wrong', $code = 500)
    {
        $response = [
            'status' => false,
            'code' => $code,
            'message' => $message,
        ];

        if (! empty($data)) {
            $response['data'] = $data;
        }

        return response()->json($response, $code);
    }

    public static function success($data = [], $message = 'Success', $code = 200)
    {
        return response()->json([
            'status' => true,
            'code' => $code,
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    protected function currentCompany(): Company
    {
        $company = app(CompanyContext::class)->company;

        if (! $company) {
            abort(403, 'No active company is selected for this session.');
        }

        return $company;
    }

    /**
     * @return array{items: mixed, meta: array<string, int>}
     */
    protected function paginated(LengthAwarePaginator $paginator, mixed $items): array
    {
        return [
            'items' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
        ];
    }
}
