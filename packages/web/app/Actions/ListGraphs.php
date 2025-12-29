<?php

namespace App\Actions;

use TimeseriesPhp\Core\Contracts\GraphRepository;
use TimeseriesPhp\Core\Exceptions\GraphNotFoundException;
use TimeseriesPhp\Core\Exceptions\InvalidGraphDefinitionException;
use TimeseriesPhp\Core\Graph\GraphDefinition;

class ListGraphs
{
    public function __construct(
        private readonly GraphRepository $graphRepository,
    ) {}

    /**
     * @return array<string, GraphDefinition>
     *
     * @throws GraphNotFoundException
     * @throws InvalidGraphDefinitionException
     */
    public function execute(): array
    {
        $graphList = $this->graphRepository->list();
        $defs = array_map($this->graphRepository->load(...), $graphList);

        return array_combine($graphList, $defs);
    }
}
