<?php

namespace App\Models;

class AnimalHealthMonitoringRecord extends LivestockRecord
{
    protected $fillable = ['visit_date', 'barangay', 'farmer_name', 'species', 'sex', 'age_value', 'age_unit', 'cases', 'clinical_signs', 'disorder', 'remarks'];
}
