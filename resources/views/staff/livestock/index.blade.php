@extends('layouts.app')
@section('title', 'PawID | '.$title)
@section('content')
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-3 mb-4">
        <div><div class="page-kicker">Livestock field services</div><h1 class="page-title mb-1">{{ $title }}</h1><p class="text-muted mb-0">Manage livestock visits and field-service records.</p></div>
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-outline-success btn-sm" href="{{ route('staff.'.$module.'.export') }}" title="Download all records, regardless of the filters below"><i class="bi bi-file-earmark-excel me-1" aria-hidden="true"></i>Download Excel — All Records</a>
            <a class="btn btn-brand btn-sm" href="{{ route('staff.'.$module.'.create') }}"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Add Record</a>
        </div>
    </div>
    <form method="GET" action="{{ route('staff.'.$module.'.index') }}" class="card card-light p-3 mb-4">
        <div class="row g-3 align-items-end">
            <div class="col-md-4"><label for="barangay" class="form-label">Barangay</label><select class="form-select" id="barangay" name="barangay"><option value="">Select Barangay</option>@foreach($barangays as $code => $label)<option value="{{ $label }}" data-psgc="{{ $code }}" @selected(($filters['barangay'] ?? '') === $label)>{{ $label }}</option>@endforeach</select></div>
            <div class="col-md-4"><label for="q" class="form-label">Search</label><input class="form-control" id="q" name="q" maxlength="150" value="{{ $filters['q'] ?? '' }}" placeholder="Farmer, barangay, or species"></div>
            <div class="col-sm-6 col-md-2"><label for="date_from" class="form-label">Visit from</label><input class="form-control" id="date_from" name="date_from" type="date" value="{{ $filters['date_from'] ?? '' }}"></div>
            <div class="col-sm-6 col-md-2"><label for="date_to" class="form-label">Visit through</label><input class="form-control" id="date_to" name="date_to" type="date" value="{{ $filters['date_to'] ?? '' }}"></div>
            <div class="col-md-4 d-flex gap-2"><button class="btn btn-brand" type="submit">Apply Filters</button><a class="btn btn-outline-secondary" href="{{ route('staff.'.$module.'.index') }}">Clear</a></div>
        </div>
        <p class="record-meta mb-0 mt-3">Filters apply to this table. Excel downloads always include all records.</p>
    </form>
    <section class="card card-light overflow-hidden">
        <div class="p-3 border-bottom"><h2 class="h6 mb-0">{{ number_format($records->total()) }} {{ Str::plural('record', $records->total()) }}</h2></div>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead><tr><th>Date of Visit</th><th>Barangay</th><th>Farmer's Name</th>@unless($health)<th>Activity</th>@endunless<th>Species</th><th>Sex</th><th>Age</th><th>{{ $health ? 'Cases' : 'Number of Heads' }}</th><th>Actions</th></tr></thead>
                <tbody>
                    @forelse ($records as $record)
                        <tr>
                            <td class="text-nowrap">{{ $record->visit_date->format('M j, Y') }}</td><td>{{ $record->barangay }}@unless(\App\Support\LaoagBarangays::isOfficial($record->barangay))<span class="d-block small text-warning-emphasis">Barangay needs review</span>@endunless</td><td class="record-name">{{ $record->farmer_name }}</td>
                            @unless($health)<td>{{ $record->activity }}</td>@endunless
                            <td>{{ $record->species }}</td><td>{{ $record->sex }}</td><td class="text-nowrap">{{ $record->ageLabel() ?: 'Not recorded' }}</td><td>{{ ($health ? $record->cases : $record->number_of_heads) ?? 'Not recorded' }}</td>
                            <td><div class="d-flex gap-2">
                                <a class="btn btn-outline-secondary btn-sm" href="{{ route('staff.'.$module.'.show', $record) }}">View</a>
                                <a class="btn btn-outline-secondary btn-sm" href="{{ route('staff.'.$module.'.edit', $record) }}">Edit</a>
                                <a class="btn btn-outline-danger btn-sm" href="{{ route('staff.'.$module.'.delete', $record) }}">Delete</a>
                            </div></td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ $health ? 9 : 10 }}" class="text-center text-muted py-5">No records found. Add a record or adjust your filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($records->hasPages())<div class="p-3 border-top">{{ $records->links('pagination::bootstrap-5') }}</div>@endif
    </section>
@endsection
