<?php

namespace App\Http\Controllers;

use App\Models\{Vaccination, DewormingRecord, AnimalHealthMonitoringRecord, RoutineServiceRecord, Pet};
use App\Services\{RecordAudit, PetCareReminders};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordArchiveController extends Controller
{
    public const TYPES = [
        'vaccinations' => Vaccination::class,
        'deworming' => DewormingRecord::class,
        'animal-health-monitoring' => AnimalHealthMonitoringRecord::class,
        'routine-services' => RoutineServiceRecord::class,
    ];

    private function model(string $type): string
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
        abort_unless(isset(self::TYPES[$type]), 404);
        return self::TYPES[$type];
    }

    public function index(string $type = 'vaccinations')
    {
        $model = $this->model($type);
        $medical = in_array($type, ['vaccinations', 'deworming'], true);
        $records = $model::onlyTrashed()->when($medical, fn ($q) => $q->with(['pet' => fn ($p) => $p->withTrashed()]))
            ->orderByDesc('deleted_at')->orderByDesc('id')->paginate(15);
        return view('admin.record-archive', compact('records', 'type', 'medical'));
    }

    public function restore(Request $request, string $type, string $record)
    {
        $model = $this->model($type);
        $values = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500'], 'confirm_restore' => ['required', 'accepted']]);
        DB::transaction(function () use ($model, $record, $request, $values) {
            $row = $model::onlyTrashed()->findOrFail($record);
            $medical = $row instanceof Vaccination || $row instanceof DewormingRecord;
            $pet = $medical ? Pet::withTrashed()->whereKey($row->pet_id)->lockForUpdate()->firstOrFail() : null;
            if ($pet?->trashed()) {
                throw ValidationException::withMessages(['restore' => 'This pet is archived. Medical record recovery is blocked until an administrator reviews the archived pet. This screen cannot restore pets.']);
            }
            $row = $model::onlyTrashed()->whereKey($record)->lockForUpdate()->firstOrFail();
            $row->restore();
            app(RecordAudit::class)->write($row, $request->user(), 'restored', $values['reason']);
            if ($pet) app(PetCareReminders::class)->generateForPet($pet);
        });
        return redirect()->route('admin.record-archive', $type)->with('success', 'Record restored. Original treatment dates and completion status were preserved.');
    }

    public function audit()
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
        $columns = ['actor_id', 'record_table', 'record_id', 'action', 'created_at'];
        $deleted = DB::table('record_deletion_audits')->select($columns)->selectRaw('NULL as reason, NULL as changes');
        $events = DB::query()->fromSub(DB::table('record_change_audits')->select([...$columns, 'reason', 'changes'])->unionAll($deleted), 'events')
            ->orderByDesc('created_at')->orderByDesc('record_id')->paginate(30);
        return view('admin.record-audit', compact('events'));
    }
}
