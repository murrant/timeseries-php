<?php

namespace TimeseriesPhp\Core\Contracts;

use TimeseriesPhp\Core\Enum\OperationType;

/** TODO What does this class do? not self explanatory */
interface Operation
{
    public function getType(): OperationType;
}
