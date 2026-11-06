<?php

namespace App\Http\Controllers;

use App\Models\AnimalHealthMonitoringRecord;

class AnimalHealthMonitoringController extends LivestockRecordController
{
    protected string $model = AnimalHealthMonitoringRecord::class;

    protected string $module = 'animal-health-monitoring';

    protected string $title = 'Animal Health Monitoring';
}
