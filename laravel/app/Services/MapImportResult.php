<?php

namespace App\Services;

final class MapImportResult
{
    /**
     * @param  list<string>  $sampleErrors
     */
    public function __construct(
        public readonly int $processed,
        public readonly int $skipped,
        public readonly int $skippedBadColumns = 0,
        public readonly int $skippedExceptions = 0,
        public readonly array $sampleErrors = [],
    ) {}
}
