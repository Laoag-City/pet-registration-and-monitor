@extends('layouts.app')
@section('title', 'Office of the City Veterinarian | '.$title)
@section('content')
    <a class="small text-decoration-none" href="{{ route('staff.'.$module.'.index') }}">&larr; Back to {{ $title }}</a>
    <h1 class="page-title mt-3 mb-4">{{ $confirmDelete ? 'Delete' : 'View' }} {{ $title }} Record</h1>
    <section class="card card-light p-3 p-md-4">
        <dl class="row g-3 mb-4">
            <div class="col-md-4"><dt class="detail-label">Date of Visit</dt><dd>{{ $record->visit_date->format('M j, Y') }}</dd></div>
            @php
                $fields = ['barangay' => 'Barangay', 'farmer_name' => "Farmer's Name"];
                if (!$health) $fields['activity'] = 'Activity';
                $fields += ['species' => 'Species', 'sex' => 'Sex'];
                $fields += $health ? ['cases' => 'Cases', 'clinical_signs' => 'Clinical Signs', 'disorder' => 'Disorder'] : ['number_of_heads' => 'Number of Heads'];
                $fields['remarks'] = 'Remarks';
            @endphp
            @foreach($fields as $field => $label)
                <div class="{{ in_array($field, ['clinical_signs', 'remarks']) ? 'col-12' : 'col-md-4' }}"><dt class="detail-label">{{ $label }}</dt><dd class="mb-0" style="white-space:pre-wrap;overflow-wrap:anywhere">{{ $record->$field ?? 'Not recorded' }}</dd></div>
            @endforeach
            <div class="col-md-4"><dt class="detail-label">Age</dt><dd>{{ $record->ageLabel() ?: 'Not recorded' }}</dd></div>
        </dl>
        @if($confirmDelete)
            <div class="alert alert-warning">This removes only this livestock record from active lists and exports. The record is retained for audit.</div>
            <form method="POST" action="{{ route('staff.'.$module.'.destroy', $record) }}">
                @csrf @method('DELETE')
                <div class="form-check mb-3"><input type="checkbox" class="form-check-input @error('confirm_delete') is-invalid @enderror" id="confirm_delete" name="confirm_delete" value="1" required><label class="form-check-label" for="confirm_delete">I confirm deletion of this record for {{ $record->farmer_name }}.</label>@error('confirm_delete')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <button class="btn btn-danger" type="submit">Delete Record</button><a class="btn btn-outline-secondary ms-2" href="{{ route('staff.'.$module.'.show', $record) }}">Cancel</a>
            </form>
        @else
            <a class="btn btn-brand" href="{{ route('staff.'.$module.'.edit', $record) }}">Edit Record</a><a class="btn btn-outline-danger ms-2" href="{{ route('staff.'.$module.'.delete', $record) }}">Delete Record</a>
        @endif
    </section>
@endsection
