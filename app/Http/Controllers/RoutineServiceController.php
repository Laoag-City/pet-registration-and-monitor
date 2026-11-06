<?php

namespace App\Http\Controllers;

use App\Models\RoutineServiceRecord;

class RoutineServiceController extends LivestockRecordController
{
    protected string $model = RoutineServiceRecord::class;

    protected string $module = 'routine-services';

    protected string $title = 'Routine Services';
}
