<div class="card card-light p-3 p-md-4 mb-4">
    <div class="row g-3">
        <div class="col-md-6">
            <div class="record-meta">Owner</div>
            <div class="fw-semibold">{{ $owner?->name ?? $pet->owner_name ?? 'Owner unavailable' }}</div>
            @if ($owner)<div class="record-meta">{{ $owner->email }} @if ($owner->contact_number)&middot; {{ $owner->contact_number }}@endif</div>@endif
        </div>
        @isset($pet)
            <div class="col-md-6">
                <div class="record-meta">Pet</div>
                <div class="fw-semibold">{{ $pet->name }}</div>
                <div class="record-meta">{{ $pet->species }} @if ($pet->breed)&middot; {{ $pet->breed }}@endif @if ($pet->sex)&middot; {{ $pet->sex }}@endif</div>
            </div>
        @endisset
    </div>
</div>
