<?php

namespace App\Services\Gar;

class DifObjectLevel extends CommonGar
{

    protected function getTableName(): string
    {
        return 'gar.dif_object_levels';
    }

    protected function canProcessed(array $inputArray): bool
    {
        return true;
    }

    protected function getKeysArray(): array
    {
        return [
            'LEVEL' => 'id',
            'NAME' => 'name',
            'SHORTNAME' => 'short_name',
            'UPDATEDATE' => 'update_date',
            'STARTDATE' => 'start_date',
            'ENDDATE' => 'end_date',
            'ISACTIVE' => 'is_active',
        ];
    }
}
