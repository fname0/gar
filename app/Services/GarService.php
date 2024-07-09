<?php

namespace App\Services;

use App\Jobs\ImportGar;
use App\Models\Version;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use ZipArchive;
use Illuminate\Support\Facades\DB;

class GarService
{

    private static array $regFileNames = [
        'AS_OBJECT_LEVELS' => '/^AS_OBJECT_LEVELS_\d{8}_[0-9abcdef]{8}-[0-9abcdef]{4}-[0-9abcdef]{4}-[0-9abcdef]{4}-[0-9abcdef]{12}.XML$/i',
        'AS_HOUSE_TYPES' => '/^AS_HOUSE_TYPES_\d{8}_[0-9abcdef]{8}-[0-9abcdef]{4}-[0-9abcdef]{4}-[0-9abcdef]{4}-[0-9abcdef]{12}.XML$/i',
        'AS_ADDHOUSE_TYPES' => '/^AS_ADDHOUSE_TYPES_\d{8}_[0-9abcdef]{8}-[0-9abcdef]{4}-[0-9abcdef]{4}-[0-9abcdef]{4}-[0-9abcdef]{12}.XML$/i',
        'AS_PARAM_TYPES' => '/^AS_PARAM_TYPES_\d{8}_[0-9abcdef]{8}-[0-9abcdef]{4}-[0-9abcdef]{4}-[0-9abcdef]{4}-[0-9abcdef]{12}.XML$/i',
        'AS_HOUSES_PARAMS' => '/^AS_HOUSES_PARAMS_\d{8}_[0-9abcdef]{8}-[0-9abcdef]{4}-[0-9abcdef]{4}-[0-9abcdef]{4}-[0-9abcdef]{12}.XML$/i',
        'AS_ADDR_OBJ' => '/^AS_ADDR_OBJ_\d{8}_[0-9abcdef]{8}-[0-9abcdef]{4}-[0-9abcdef]{4}-[0-9abcdef]{4}-[0-9abcdef]{12}.XML$/i',
        'AS_ADM_HIERARCHY' => '/^AS_ADM_HIERARCHY_\d{8}_[0-9abcdef]{8}-[0-9abcdef]{4}-[0-9abcdef]{4}-[0-9abcdef]{4}-[0-9abcdef]{12}.XML$/i',
        'AS_HOUSES' => '/^AS_HOUSES_\d{8}_[0-9abcdef]{8}-[0-9abcdef]{4}-[0-9abcdef]{4}-[0-9abcdef]{4}-[0-9abcdef]{12}.XML$/i',
    ];

    private static function getCmd(string $downloadUrl): string
    {
        if (config('gar.download_mode') == 'alternate')
            $downloadUrl = str_replace('fias-file', 'fias', $downloadUrl);
        return config('gar.wget_path') . 'wget ' . $downloadUrl . " -O " .
            Storage::path(config('gar.xml_full_zip_file_name')) . " -o " . Storage::path(config('gar.wget_log_file_name'));
    }

    private static function getCmdDif(string $downloadUrl): string
    {
        if (config('gar.download_mode') == 'alternate')
            $downloadUrl = str_replace('fias-file', 'fias', $downloadUrl);
        return config('gar.wget_path') . 'wget ' . $downloadUrl . " -O " .
            Storage::path(config('gar.xml_dif_zip_file_name')) . " -o " . Storage::path(config('gar.wget_log_file_name'));
    }

    private static function initDirectories(): void
    {
        if (!Storage::directoryExists('gar')) Storage::makeDirectory('gar');
    }

    private static function deleteOldFullZipFile(): void
    {
        if (Storage::exists(config('gar.xml_full_zip_file_name')))
            Storage::delete(config('gar.xml_full_zip_file_name'));
    }

    private static function deleteOldDifZipFile(): void
    {
        if (Storage::exists(config('gar.xml_dif_zip_file_name')))
            Storage::delete(config('gar.xml_dif_zip_file_name'));
    }

    public static function downloadFullGarArchive(): bool
    {
        $result = Http::get('https://fias.nalog.ru/WebServices/Public/GetLastDownloadFileInfo');
        if ($result->ok()) {
            $garArray = json_decode($result->body(), true);
            $version = Version::select('version')->latest()->limit(1)->first();
            if (is_null($version) or $version->version < $garArray['VersionId']) {
                GarService::initDirectories();
                GarService::deleteOldFullZipFile();
                $cmd = GarService::getCmd($garArray['GarXMLFullURL']);
                $processResult = Process::forever()->run($cmd);
                return $processResult->successful();
            }
        }
        return false;
    }

    public static function downloadDifGarArchive(string $link): bool
    {
        GarService::initDirectories();
        GarService::deleteOldDifZipFile();
        $cmd = GarService::getCmdDif($link);
        $processResult = Process::forever()->run($cmd);
        return $processResult->successful();
    }

    public static function extractFullGar(): bool
    {
        $zip = new ZipArchive();
        $status = $zip->open(Storage::path(config('gar.xml_full_zip_file_name')));
        if ($status !== true) return false;
        else {
            self::deleteOldUnzipFiles();
            self::makeUnzipDirectory();
            $extractArray = self::getExtractFiles($zip);
            if (count($extractArray) != 0)
                $zip->extractTo(Storage::path(config('gar.unzip_full_path')), $extractArray);

        }
        $zip->close();
        return true;
    }

    public static function extractDifGar(): bool
    {
        $zip = new ZipArchive();
        $status = $zip->open(Storage::path(config('gar.xml_dif_zip_file_name')));
        if ($status !== true) return false;
        else {
            self::deleteOldDifUnzipFiles();
            self::makeDifUnzipDirectory();
            $extractArray = self::getExtractFiles($zip);
            if (count($extractArray) != 0)
                $zip->extractTo(Storage::path(config('gar.unzip_dif_path')), $extractArray);
        }
        $zip->close();
        return true;
    }

    private static function getExtractFiles(ZipArchive $zip): array
    {
        $extractArray = [];
        for ($i = 0; $i < $zip->count(); $i++) {
            $fileName = $zip->getNameIndex($i);
            $dir = dirname($fileName);
            if (($dir == '.' or in_array($dir, config('gar.region_code'))) and (self::verifyFileName($fileName) or $fileName=='version.txt'))
                $extractArray[] = $fileName;
        }
        return $extractArray;
    }

    private static function deleteOldUnzipFiles(): void
    {
        if (Storage::directoryExists(config('gar.unzip_full_path')))
            Storage::deleteDirectory(config('gar.unzip_full_path'));
    }

    private static function makeUnzipDirectory(): void
    {
        if (!Storage::directoryExists(config('gar.unzip_full_path'))) {
            Storage::makeDirectory(config('gar.unzip_full_path'));
        }
    }

    private static function deleteOldDifUnzipFiles(): void
    {
        if (Storage::directoryExists(config('gar.unzip_dif_path')))
            Storage::deleteDirectory(config('gar.unzip_dif_path'));
    }

    private static function makeDifUnzipDirectory(): void
    {
        if (!Storage::directoryExists(config('gar.unzip_dif_path'))) {
            Storage::makeDirectory(config('gar.unzip_dif_path'));
        }
    }

    private static function verifyFileName(string $fileName): bool
    {
        foreach (self::$regFileNames as $regFileName) {
            if (preg_match($regFileName, basename($fileName))) return true;
        }
        return false;
    }

    public static function setImportJobs(): void
    {
        foreach (config('gar.region_code') as $regionCode)
        {
            // HouseParam
            self::setJob('AS_HOUSES_PARAMS', 'PARAM', Gar\HouseParam::class, $regionCode);

            // AdmHierarchy
            self::setJob('AS_ADM_HIERARCHY', 'ITEM', Gar\AdmHierarchy::class, $regionCode);

            // House
            self::setJob('AS_HOUSES', 'HOUSE', Gar\House::class, $regionCode);

            // AddrObj
            self::setJob('AS_ADDR_OBJ', 'OBJECT', Gar\AddrObj::class, $regionCode);
        }
        // ParamType
        self::setJob('AS_PARAM_TYPES', 'PARAMTYPE', Gar\ParamType::class, '0');

        //ObjectLevels
        self::setJob('AS_OBJECT_LEVELS', 'OBJECTLEVEL', Gar\ObjectLevel::class, '0');

        // HouseTypes
        self::setJob('AS_HOUSE_TYPES', 'HOUSETYPE', Gar\HouseType::class, '0');

        // HouseAddTypes
        self::setJob('AS_ADDHOUSE_TYPES', 'HOUSETYPE', Gar\HouseAddType::class, '0');
    }

    private static function setJob(string $keyFileName, string $nodeName, string $serviceName, string $regionCode): void
    {
        $fileName = self::getFileName(self::$regFileNames[$keyFileName], $regionCode);
        if (Storage::fileExists($fileName)) ImportGar::dispatch($fileName, $nodeName, $serviceName, $regionCode);
    }

    private static function getFileName(string $filePattern, string $regionCode): string
    {
        if ($regionCode == "0") { $files = Storage::files(config('gar.unzip_full_path'), true); }
        else { $files = Storage::files(config('gar.unzip_full_path').'/'.$regionCode, true); }
        foreach ($files as $file) {
            if (preg_match($filePattern, basename($file))) return $file;
        }
        return '';
    }

    public static function setDifImportJobs(): void
    {
        foreach (config('gar.region_code') as $regionCode)
        {
            // HouseParam
            self::setDifJob('AS_HOUSES_PARAMS', 'PARAM', Gar\DifHouseParam::class, $regionCode);

            // AdmHierarchy
            self::setDifJob('AS_ADM_HIERARCHY', 'ITEM', Gar\DifAdmHierarchy::class, $regionCode);

            // House
            self::setDifJob('AS_HOUSES', 'HOUSE', Gar\DifHouse::class, $regionCode);

            // AddrObj
            self::setDifJob('AS_ADDR_OBJ', 'OBJECT', Gar\DifAddrObj::class, $regionCode);
        }
        // ParamType
        self::setDifJob('AS_PARAM_TYPES', 'PARAMTYPE', Gar\DifParamType::class, '0');

        //ObjectLevels
        self::setDifJob('AS_OBJECT_LEVELS', 'OBJECTLEVEL', Gar\DifObjectLevel::class, '0');

        // HouseTypes
        self::setDifJob('AS_HOUSE_TYPES', 'HOUSETYPE', Gar\DifHouseType::class, '0');

        // HouseAddTypes
        self::setDifJob('AS_ADDHOUSE_TYPES', 'HOUSETYPE', Gar\DifHouseAddType::class, '0');
    }

    private static function setDifJob(string $keyFileName, string $nodeName, string $serviceName, string $regionCode): void
    {
        $fileName = self::getDifFileName(self::$regFileNames[$keyFileName], $regionCode);
        // if (Storage::fileExists($fileName)) echo(Storage::path($fileName).' -> '.$regionCode."\n");
        if (Storage::fileExists($fileName)) ImportGar::dispatch($fileName, $nodeName, $serviceName, $regionCode);
    }

    private static function getDifFileName(string $filePattern, string $regionCode): string
    {
        if ($regionCode == "0") { $files = Storage::files(config('gar.unzip_dif_path'), true); }
        else { $files = Storage::files(config('gar.unzip_dif_path').'/'.$regionCode, true); }
        foreach ($files as $file) {
            if (preg_match($filePattern, basename($file))) return $file;
        }
        return '';
    }

    public static function update(): bool
    {
        foreach (config('gar.region_code') as $regionCode)
        {
            // --- addr_objs ---
            $tableName = 'addr_objs_'.$regionCode;
            $select = DB::select("SELECT * FROM gar.dif_{$tableName} EXCEPT SELECT * FROM gar.{$tableName}");
            foreach ($select as $data) {
                DB::table("gar.{$tableName}")->where('id', $data->id)->delete();
                DB::table("gar.{$tableName}")->insert(get_object_vars($data));
            }
            echo("{$tableName} обновлено \n");

            // --- adm_hierarchies ---
            $tableName = 'adm_hierarchies_'.$regionCode;
            $select = DB::select("SELECT * FROM gar.dif_{$tableName} EXCEPT SELECT * FROM gar.{$tableName}");
            foreach ($select as $data) {
                DB::table("gar.{$tableName}")->where('id', $data->id)->delete();
                DB::table("gar.{$tableName}")->insert(get_object_vars($data));
            }
            echo("{$tableName} обновлено \n");

            // --- house_add_types ---
            $tableName = 'house_add_types';
            $select = DB::select("SELECT * FROM gar.dif_{$tableName} EXCEPT SELECT * FROM gar.{$tableName}");
            foreach ($select as $data) {
                DB::table("gar.{$tableName}")->where('id', $data->id)->delete();
                DB::table("gar.{$tableName}")->insert(get_object_vars($data));
            }
            echo("{$tableName} обновлено \n");

            // --- house_params ---
            $tableName = 'house_params_'.$regionCode;
            $select = DB::select("SELECT * FROM gar.dif_{$tableName} EXCEPT SELECT * FROM gar.{$tableName}");
            foreach ($select as $data) {
                DB::table("gar.{$tableName}")->where('id', $data->id)->delete();
                DB::table("gar.{$tableName}")->insert(get_object_vars($data));
            }
            echo("{$tableName} обновлено \n");

            // --- house_types ---
            $tableName = 'house_types';
            $select = DB::select("SELECT * FROM gar.dif_{$tableName} EXCEPT SELECT * FROM gar.{$tableName}");
            foreach ($select as $data) {
                DB::table("gar.{$tableName}")->where('id', $data->id)->delete();
                DB::table("gar.{$tableName}")->insert(get_object_vars($data));
            }
            echo("{$tableName} обновлено \n");

            // --- houses ---
            $tableName = 'houses_'.$regionCode;
            $select = DB::select("SELECT * FROM gar.dif_{$tableName} EXCEPT SELECT * FROM gar.{$tableName}");
            foreach ($select as $data) {
                DB::table("gar.{$tableName}")->where('id', $data->id)->delete();
                DB::table("gar.{$tableName}")->insert(get_object_vars($data));
            }
            echo("{$tableName} обновлено \n");

            // --- object_levels ---
            $tableName = 'object_levels';
            $select = DB::select("SELECT * FROM gar.dif_{$tableName} EXCEPT SELECT * FROM gar.{$tableName}");
            foreach ($select as $data) {
                DB::table("gar.{$tableName}")->where('id', $data->id)->delete();
                DB::table("gar.{$tableName}")->insert(get_object_vars($data));
            }
            echo("{$tableName} обновлено \n");

            // --- param_types ---
            $tableName = 'param_types';
            $select = DB::select("SELECT * FROM gar.dif_{$tableName} EXCEPT SELECT * FROM gar.{$tableName}");
            foreach ($select as $data) {
                DB::table("gar.{$tableName}")->where('id', $data->id)->delete();
                DB::table("gar.{$tableName}")->insert(get_object_vars($data));
            }
            echo("{$tableName} обновлено \n");
        }
        return true;
    }
}
