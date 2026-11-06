@if (!$record->schedule_completed_at && $record->next_due_at && $currentRecordIds->contains($record->id))
    <button type="button" class="btn btn-outline-success btn-sm ms-1" data-bs-toggle="modal" data-bs-target="#done-{{ $record->id }}">Done<span class="visually-hidden"> {{ $record->pet->name }}</span></button>
@endif
