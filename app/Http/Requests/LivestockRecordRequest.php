<?php

namespace App\Http\Requests;

use App\Models\AnimalHealthMonitoringRecord;
use App\Models\LivestockRecord;
use App\Models\RoutineServiceRecord;
use App\Support\LaoagBarangays;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LivestockRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canManageRecords() ?? false;
    }

    public function rules(): array
    {
        $canPreserve = false;
        if ($this->routeIs('staff.animal-health-monitoring.update', 'staff.routine-services.update')) {
            $model = $this->routeIs('staff.animal-health-monitoring.*') ? AnimalHealthMonitoringRecord::class : RoutineServiceRecord::class;
            $record = $model::findOrFail($this->route('record'));
            $canPreserve = ! LaoagBarangays::isOfficial($record->barangay);
        }
        $rules = [
            'visit_date' => ['required', 'date_format:Y-m-d'],
            'barangay' => $canPreserve && $this->boolean('keep_existing_barangay')
                ? ['exclude']
                : ['required', 'string', Rule::in(LaoagBarangays::labels())],
            'keep_existing_barangay' => $canPreserve ? ['sometimes', 'boolean'] : ['prohibited'],
            'farmer_name' => ['required', 'string', 'max:150'],
            'species' => ['required', Rule::in(LivestockRecord::SPECIES)],
            'sex' => ['required', Rule::in(LivestockRecord::SEXES)],
            'age_value' => ['nullable', 'required_with:age_unit', 'numeric', 'decimal:0,2', 'min:0', 'max:99999.99'],
            'age_unit' => ['nullable', 'required_with:age_value', Rule::in(LivestockRecord::AGE_UNITS)],
            'remarks' => ['nullable', 'string', 'max:5000'],
        ];

        if ($this->routeIs('staff.animal-health-monitoring.*')) {
            return $rules + [
                'cases' => ['nullable', 'integer', 'min:0', 'max:2147483647'],
                'clinical_signs' => ['nullable', 'string', 'max:5000'],
                'disorder' => ['nullable', 'string', 'max:255'],
            ];
        }

        return $rules + [
            'activity' => ['required', 'string', 'max:255'],
            'number_of_heads' => ['required', 'integer', 'min:0', 'max:2147483647'],
        ];
    }
}
