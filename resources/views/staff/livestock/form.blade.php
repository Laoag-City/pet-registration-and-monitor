@extends('layouts.app')
@section('title', 'Office of the City Veterinarian | '.$title)
@section('content')
    <a class="small text-decoration-none" href="{{ route('staff.'.$module.'.index') }}">&larr; Back to {{ $title }}</a>
    <h1 class="page-title mt-3 mb-4">{{ $record->exists ? 'Edit' : 'Add' }} {{ $title }} Record</h1>
    <section class="card card-light p-3 p-md-4">
        <p class="record-meta">Fields marked * are required. If age is supplied, select its unit.</p>
        <form method="POST" action="{{ $record->exists ? route('staff.'.$module.'.update', $record) : route('staff.'.$module.'.store') }}" data-prevent-repeat>
            @csrf
            @if($record->exists) @method('PUT') @endif
            <div class="row g-3">
                <div class="col-md-4">
                    <label for="visit_date" class="form-label">Date of Visit *</label>
                    <input class="form-control @error('visit_date') is-invalid @enderror" id="visit_date" name="visit_date" type="date" value="{{ old('visit_date', $record->visit_date?->format('Y-m-d')) }}" required>
                    @error('visit_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    @php
                        $historicalBarangay = $record->exists && !\App\Support\LaoagBarangays::isOfficial($record->barangay);
                        $keepBarangay = $historicalBarangay && old('keep_existing_barangay', '1') === '1';
                    @endphp
                    <label for="barangay" class="form-label">Barangay *</label>
                    <select class="form-select @error('barangay') is-invalid @enderror" id="barangay" name="barangay" @required(!$keepBarangay) @disabled($keepBarangay)>
                        <option value="">Select Barangay</option>
                        @foreach($barangays as $code => $label)
                            <option value="{{ $label }}" data-psgc="{{ $code }}" @selected(old('barangay', $record->barangay) === $label)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('barangay')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    @if($historicalBarangay)
                        <div class="alert alert-warning mt-2 mb-2 small">Barangay needs review. Historical value: <strong>{{ $record->barangay }}</strong>. Select the correct numbered barangay only after verification.</div>
                        <input type="hidden" name="keep_existing_barangay" value="0">
                        <div class="form-check"><input class="form-check-input" id="keep_existing_barangay" name="keep_existing_barangay" type="checkbox" value="1" @checked($keepBarangay) onchange="document.getElementById('barangay').disabled=this.checked; document.getElementById('barangay').required=!this.checked;"><label class="form-check-label small" for="keep_existing_barangay">Keep the existing historical value unchanged</label></div>
                    @endif
                </div>
                @foreach(['farmer_name' => "Farmer's Name"] as $field => $label)
                    <div class="col-md-4"><label for="{{ $field }}" class="form-label">{{ $label }} *</label><input class="form-control @error($field) is-invalid @enderror" id="{{ $field }}" name="{{ $field }}" value="{{ old($field, $record->$field) }}" maxlength="150" required>@error($field)<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                @endforeach
                @unless($health)
                    <div class="col-12"><label for="activity" class="form-label">Activity *</label><input class="form-control @error('activity') is-invalid @enderror" id="activity" name="activity" value="{{ old('activity', $record->activity) }}" maxlength="255" required>@error('activity')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                @endunless
                @foreach(['species' => ['Species', \App\Models\LivestockRecord::SPECIES], 'sex' => ['Sex', \App\Models\LivestockRecord::SEXES]] as $field => [$label, $options])
                    <div class="col-md-6"><label for="{{ $field }}" class="form-label">{{ $label }} *</label><select class="form-select @error($field) is-invalid @enderror" id="{{ $field }}" name="{{ $field }}" required><option value="">Select {{ $label }}</option>@foreach($options as $option)<option value="{{ $option }}" @selected(old($field, $record->$field) === $option)>{{ $option }}</option>@endforeach</select>@error($field)<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                @endforeach
                <div class="col-sm-6 col-md-4"><label for="age_value" class="form-label">Age</label><input class="form-control @error('age_value') is-invalid @enderror" type="number" min="0" max="99999.99" step="0.01" id="age_value" name="age_value" value="{{ old('age_value', $record->age_value) }}">@error('age_value')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-sm-6 col-md-4"><label for="age_unit" class="form-label">Age Unit</label><select class="form-select @error('age_unit') is-invalid @enderror" id="age_unit" name="age_unit"><option value="">Select Unit</option>@foreach(\App\Models\LivestockRecord::AGE_UNITS as $unit)<option value="{{ $unit }}" @selected(old('age_unit', $record->age_unit) === $unit)>{{ ucfirst($unit) }}</option>@endforeach</select>@error('age_unit')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                @php $countField = $health ? 'cases' : 'number_of_heads'; @endphp
                <div class="col-md-4"><label for="{{ $countField }}" class="form-label">{{ $health ? 'Cases' : 'Number of Heads *' }}</label><input class="form-control @error($countField) is-invalid @enderror" type="number" min="0" max="2147483647" step="1" id="{{ $countField }}" name="{{ $countField }}" value="{{ old($countField, $record->$countField) }}" @required(!$health)>@error($countField)<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                @if($health)
                    <div class="col-12"><label for="disorder" class="form-label">Disorder</label><input class="form-control @error('disorder') is-invalid @enderror" id="disorder" name="disorder" maxlength="255" value="{{ old('disorder', $record->disorder) }}">@error('disorder')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                @endif
                @foreach($health ? ['clinical_signs' => 'Clinical Signs', 'remarks' => 'Remarks'] : ['remarks' => 'Remarks'] as $field => $label)
                    <div class="col-12"><label for="{{ $field }}" class="form-label">{{ $label }}</label><textarea class="form-control @error($field) is-invalid @enderror" id="{{ $field }}" name="{{ $field }}" rows="4" maxlength="5000">{{ old($field, $record->$field) }}</textarea>@error($field)<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                @endforeach
            </div>
            <div class="d-flex gap-2 mt-4"><button class="btn btn-brand" type="submit">{{ $record->exists ? 'Save Changes' : 'Save Record' }}</button><a class="btn btn-outline-secondary" href="{{ route('staff.'.$module.'.index') }}">Cancel</a></div>
        </form>
    </section>
@endsection
