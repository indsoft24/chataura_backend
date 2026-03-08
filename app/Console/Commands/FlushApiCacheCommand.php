<?php

namespace App\Console\Commands;

use App\Services\ApiCacheService;
use Illuminate\Console\Command;

/**
 * Flush API static/catalog cache so admin updates (countries, FAQ, themes, packages, gifts) propagate.
 * Safe to run on schedule (e.g. every 30–60 min) or after admin edits.
 */
class FlushApiCacheCommand extends Command
{
    protected $signature = 'cache:flush-api
                            {--force : Skip confirmation when run interactively}';

    protected $description = 'Flush API static and catalog cache (countries, languages, FAQ, themes, packages, gifts)';

    public function handle(ApiCacheService $apiCache): int
    {
        if (!$this->option('force') && $this->confirm('Flush all API static/catalog cache?', true)) {
            $apiCache->flushStatic();
            $this->info('API cache flushed.');
        } elseif ($this->option('force')) {
            $apiCache->flushStatic();
            $this->info('API cache flushed.');
        }

        return self::SUCCESS;
    }
}
