<?php

namespace App\Console\Commands;

use App\Services\GarService;
use Exception;
use Illuminate\Console\Command;

class GarFullExtractCommand extends Command
{
    protected $signature = 'gar:full-extract';

    protected $description = 'Анзип полной выгрузки';

    /**
     * @throws Exception
     */
    public function handle(): void
    {
        GarService::extractFullGar();
    }
}
