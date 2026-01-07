<?php

namespace App\Http\Controllers;

use App\Actions\FetchPortPacketsData;
use App\Actions\FetchPortTrafficData;
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

    public function show(string $graph, Request $request): JsonResponse
    {
        if ($graph === 'host_port_bandwidth') {
            $data = app(FetchPortTrafficData::class)->execute($request->input('host'), $request->input('ifName'));
        } elseif ($graph === 'host_port_packets') {
            $data = app(FetchPortPacketsData::class)->execute($request->input('host'), $request->input('ifName'));
        } else {
            abort(404);
        }

        return response()->json($data);
    }
}
