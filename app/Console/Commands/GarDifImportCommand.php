<?php

namespace App\Console\Commands;

use App\Services\GarService;
use Exception;
use Illuminate\Console\Command;

class GarDifImportCommand extends Command
{
    protected $signature = 'gar:dif-import';

    protected $description = 'Создание задач по импорту обновления';

    /**
     * @throws Exception
     */
    public function handle(): void
    {
        GarService::setDifImportJobs();
    }
}
