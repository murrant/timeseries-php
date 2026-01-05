<?php

declare(strict_types=1);

namespace TimeseriesPhp\Driver\Null;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use TimeseriesPhp\Core\Contracts\CompiledQuery;
use TimeseriesPhp\Core\Contracts\Query;
use TimeseriesPhp\Core\Contracts\QueryCompiler;

final readonly class NullCompiler implements QueryCompiler
{
    public function __construct(
        private LoggerInterface $logger = new NullLogger,
    ) {}

    public function compile(Query $query): CompiledQuery
    {
        $this->logger->info("Received query compilation request for query"); // TODO describe query somehow

        return new class implements CompiledQuery {};
    }
}
