<?php

namespace App\Models;

class RoutineServiceRecord extends LivestockRecord
{
    protected $fillable = ['visit_date', 'barangay', 'farmer_name', 'activity', 'species', 'sex', 'age_value', 'age_unit', 'number_of_heads', 'remarks'];
}
