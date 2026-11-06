@extends('layouts.app')
@section('title', 'PawID | Record Audit')
@section('content')
<h1 class="page-title">Record Audit</h1>
<p class="text-muted">Restricted record changes and recovery history. Actor IDs remain available even if an account is later removed. Earlier edits were not retroactively recorded.</p>
<a class="btn btn-outline-secondary mb-3" href="{{ route('admin.record-archive') }}">Record Archive</a>
<div class="table-responsive card card-light"><table class="table mb-0"><thead><tr><th>Time</th><th>Actor ID</th><th>Record</th><th>Action</th><th>Details</th></tr></thead><tbody>
@forelse($events as $event)
<tr><td>{{ $event->created_at }}</td><td>{{ $event->actor_id }}</td><td>{{ $event->record_table }} #{{ $event->record_id }}</td><td>{{ $event->action }}</td><td>{{ $event->reason ?? 'Reason not recorded' }}
@if($event->changes)<details><summary>View changes</summary><pre style="white-space:pre-wrap;max-width:40rem">{{ json_encode(json_decode($event->changes), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></details>@endif
</td></tr>
@empty<tr><td colspan="5">No audit events yet.</td></tr>@endforelse
</tbody></table></div>
{{ $events->links() }}
@endsection
