<?php

namespace App\Http\Controllers\Web;

use App\Enums\TripStatus;
use App\Http\Resources\TripResource;
use App\Services\TripQuery;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HistoryController extends WebController
{
    public function index(Request $request, TripQuery $trips): Response
    {
        $filters = $request->validate(['scope' => ['sometimes', 'nullable', 'string', 'max:36']]);
        $scope = $filters['scope'] ?? null;

        return Inertia::render('history/index', [
            'trips' => $this->props(TripResource::collection(
                $trips->forUser($this->actor($request), $scope, TripStatus::Completed)->limit(100)->get()
            )),
            'filters' => ['scope' => $scope],
        ]);
    }
}
