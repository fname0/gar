<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Exception;
use App\Services\GarService;
use Illuminate\Support\Facades\Storage;
use DateTime;

class GarUpdate extends Command
{
    protected $signature = 'gar:update';

    protected $description = 'Обновление выгрузки';

    /**
     * @throws Exception
     */
    public function handle()
    {
        $updateDates = [];

        $myfile = fopen(Storage::path(config('gar.unzip_full_path').'/version.txt'), "r") or die("Не получилось открыть version.txt =(");
        $readFile = explode("\n", fread($myfile,filesize(Storage::path(config('gar.unzip_full_path').'/version.txt'))))[0];
        $date = new DateTime(explode(".", $readFile)[0]."-".explode(".", $readFile)[1]."-".explode(".", $readFile)[2]);
        fclose($myfile);

        while ($date != new DateTime('today')) {
            $date->modify('+1 day');
            $file_headers = @get_headers('https://fias-file.nalog.ru/downloads/'.$date->format('Y.m.d').'/gar_delta_xml.zip');
            $updateDate = $date->format('Y.m.d');
            if($file_headers && $file_headers[0] !== 'HTTP/1.1 404 Not Found') {

                if (config('gar.ask_to_update'))
                {
                    if (readline('Обновление за '.$updateDate.' найдено, введите "y", чтобы скачать:') != 'y')
                    {
                        return;
                    }
                }
                else
                {
                    echo "Обновление за $updateDate найдено, скачивается\n";
                }

                $ok = false;
                if (GarService::downloadDifGarArchive('https://fias-file.nalog.ru/downloads/'.$updateDate.'/gar_delta_xml.zip'))
                {
                    echo "Обновление скачано, применяется\n";
                    if (GarService::extractDifGar())
                    {
                        GarService::setDifImportJobs();
                        $this->call('gar:start-workers');
                        if (GarService::update())
                        {
                            $myfile = fopen(Storage::path(config('gar.unzip_full_path').'/version.txt'), "w") or die("Не получилось открыть version.txt =(\nПолный путь до файла: ".Storage::path(config('gar.unzip_full_path')."/version.txt"));
                            fwrite($myfile, $updateDate);
                            fclose($myfile);
                            $ok = true;
                        }
                    }
                }
                if ($ok)
                {
                    echo "Обновление за $updateDate выполнено\n";
                }
                else
                {
                    echo "Не получилось выполнить обновление за $updateDate =(\n";
                    return;
                }
            }
        }

        echo "Все обновления применены\n";
    }
}
