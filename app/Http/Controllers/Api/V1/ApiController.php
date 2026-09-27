<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

abstract class ApiController extends Controller
{
    /**
     * Page size from ?per_page, bounded by config('school.api.max_per_page').
     */
    protected function perPage(Request $request): int
    {
        $default = (int) config('school.api.per_page', 15);
        $max = (int) config('school.api.max_per_page', 100);

        return max(1, min($max, $request->integer('per_page', $default)));
    }
}
