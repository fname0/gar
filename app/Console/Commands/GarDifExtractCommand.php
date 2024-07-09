<?php

namespace App\Console\Commands;

use App\Services\GarService;
use Exception;
use Illuminate\Console\Command;

class GarDifExtractCommand extends Command
{
    protected $signature = 'gar:dif-extract';

    protected $description = 'Анзип обновления';

    /**
     * @throws Exception
     */
    public function handle(): void
    {
        GarService::extractDifGar();
    }
}
