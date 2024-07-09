<?php

namespace App\Console\Commands;

use App\Services\GarService;
use Exception;
use Illuminate\Console\Command;

class GarFullImportCommand extends Command
{
    protected $signature = 'gar:full-import';

    protected $description = 'Создание задач по импорту полной выгрузки';

    /**
     * @throws Exception
     */
    public function handle(): void
    {
        GarService::setImportJobs();
    }
}
