<?php

namespace TimeseriesPhp\Core\Contracts;

use TimeseriesPhp\Core\Enum\QueryType;

interface Query
{
    public function type(): QueryType;
}
