<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use TimeseriesPhp\Core\Graph\GraphService;
use TimeseriesPhp\Core\Time\TimeRange;

class GraphController
{
    public function show(string $graph, Request $request, GraphService $graphs): JsonResponse
    {
        $definition = $graphs->load($graph);

        $definition = $definition->withVariables([
            'host' => $request->string('host'),
        ]);

        $result = $graphs->render(
            $definition,
            TimeRange::lastMinutes(60)
        );

        return response()->json($result);
    }
}
