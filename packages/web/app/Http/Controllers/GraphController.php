<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use TimeseriesPhp\Core\Graph\GraphService;
use TimeseriesPhp\Core\Graph\VariableBinding;
use TimeseriesPhp\Core\Time\TimeRange;

class GraphController
{
    public function show(string $graph, Request $request, GraphService $graphs): JsonResponse
    {
        $result = $graphs->render(
            $graphs->load($graph),
            TimeRange::lastMinutes(60),
            [
                new VariableBinding('host', $request->input('host')),
                new VariableBinding('ifName', $request->input('ifName')),
            ],
        );

        return response()->json($result);
    }
}
