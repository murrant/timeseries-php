<?php

namespace App\Filament\Widgets;

use App\Actions\ListGraphs;
use Filament\Widgets\Widget;
use TimeseriesPhp\Core\Exceptions\GraphNotFoundException;
use TimeseriesPhp\Core\Exceptions\InvalidGraphDefinitionException;
use TimeseriesPhp\Core\Graph\GraphDefinition;

class GraphListWidget extends Widget
{
    protected string $view = 'filament.widgets.graph-list-widget';

    protected int|string|array $columnSpan = 'full';

    /**
     * @return array<string, GraphDefinition>
     *
     * @throws GraphNotFoundException
     * @throws InvalidGraphDefinitionException
     */
    public function getGraphs(): array
    {
        $graphListAction = app()->make(ListGraphs::class);

        return $graphListAction->execute();
    }
}
