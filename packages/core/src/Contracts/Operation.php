<?php

namespace TimeseriesPhp\Core\Contracts;

use TimeseriesPhp\Core\Enum\OperationType;

interface Operation
{
    public function getType(): OperationType;
}
