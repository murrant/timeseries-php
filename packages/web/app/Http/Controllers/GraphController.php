<?php

namespace App\Http\Controllers;

use App\Actions\FetchGraphData;
use App\Actions\ListGraphs;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use TimeseriesPhp\Core\Graph\GraphDefinition;

class GraphController
{
    public function index(ListGraphs $graphListAction): JsonResponse
    {
        $defs = $graphListAction->execute();

        return response()->json(array_map(fn (GraphDefinition $def) => $def->toArray(), $defs));
    }

    public function show(string $graph, Request $request, FetchGraphData $graphDataAction): JsonResponse
    {
        $data = $graphDataAction->execute($graph, $request->input('host'), $request->input('ifName'));

        return response()->json($data);
    }
}
