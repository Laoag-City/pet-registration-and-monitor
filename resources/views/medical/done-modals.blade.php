@if ($canManage)
    @foreach ($records as $record)
        @if (!$record->schedule_completed_at && $record->next_due_at && $currentRecordIds->contains($record->id))
            <div class="modal fade" id="done-{{ $record->id }}" tabindex="-1" aria-labelledby="done-title-{{ $record->id }}" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
                    <form method="POST" action="{{ route($type.'.done', $record) }}">
                        @csrf
                        <div class="modal-header"><h2 class="modal-title fs-5" id="done-title-{{ $record->id }}">Confirm treatment administered</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                        <div class="modal-body">
                            <p><strong>{{ $record->pet->name }}</strong><br>{{ $type === 'vaccinations' ? $record->vaccine : $record->product }}<br>Scheduled due date: {{ $record->next_due_at->format('M j, Y') }}</p>
                            <p class="small text-muted">Confirm only after treatment was given. This saves a new administration and preserves the previous record.</p>
                            <label for="done-date-{{ $record->id }}" class="form-label">Actual date administered *</label>
                            <input class="form-control mb-3" type="date" id="done-date-{{ $record->id }}" name="administered_at" value="{{ today()->toDateString() }}" min="{{ $record->administered_at->toDateString() }}" max="{{ today()->toDateString() }}" required>
                            <label for="next-date-{{ $record->id }}" class="form-label">Next due date (optional)</label>
                            <input class="form-control mb-3" type="date" id="next-date-{{ $record->id }}" name="next_due_at">
                            <p class="small text-muted">You can also set the next due date and other treatment details using Edit on the new record.</p>
                            <div class="form-check"><input class="form-check-input" type="checkbox" name="confirm_completion" value="1" id="confirm-done-{{ $record->id }}" required><label class="form-check-label" for="confirm-done-{{ $record->id }}">I confirm this treatment was administered.</label></div>
                        </div>
                        <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-brand">Confirm Done</button></div>
                    </form>
                </div></div>
            </div>
        @endif
    @endforeach
@endif
