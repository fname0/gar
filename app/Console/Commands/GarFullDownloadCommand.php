<?php

namespace App\Console\Commands;

use App\Services\GarService;
use Exception;
use Illuminate\Console\Command;

class GarFullDownloadCommand extends Command
{
    protected $signature = 'gar:full-download';

    protected $description = 'Скачать последнюю полную выгрузку';

    public function handle(): void
    {
        GarService::downloadFullGarArchive();
    }
}
