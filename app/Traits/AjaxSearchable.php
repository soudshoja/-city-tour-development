<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

trait AjaxSearchable
{
    protected function ajaxSearch(
        Request $request,
        $query,
        array $allowedColumns,
        string $orderBy = 'name',
        int $limit = 20
    ): JsonResponse {
        $searchTerm = $request->input('search', '');
        $requestedColumns = $request->input('columns', '');

        $requestedColumns = $requestedColumns
            ? array_filter(array_map('trim', explode(',', $requestedColumns)))
            : [];

        $columns = !empty($requestedColumns)
            ? array_values(array_intersect($requestedColumns, $allowedColumns))
            : $allowedColumns;

        if (empty($columns)) {
            $columns = $allowedColumns;
        }

        if ($searchTerm) {
            $query->where(function ($q) use ($searchTerm, $columns) {
                foreach ($columns as $column) {
                    $q->orWhere($column, 'LIKE', '%' . $searchTerm . '%');
                }
            });
        }

        $selectColumns = array_unique(array_merge(['id'], $columns));

        $results = $query
            ->select($selectColumns)
            ->orderBy($orderBy, 'asc')
            ->limit($limit)
            ->get();

        return response()->json($results);
    }
}
