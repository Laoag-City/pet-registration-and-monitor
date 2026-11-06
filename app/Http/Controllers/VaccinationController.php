<?php

namespace App\Http\Controllers;

use App\Exports\VaccinationsExport;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use App\Models\Pet;
use App\Models\Vaccination;
use App\Http\Controllers\Concerns\MedicalRecordWorkflow;
use App\Http\Requests\MedicalRecordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class VaccinationController extends Controller
{
    use MedicalRecordWorkflow;

    public function export(): BinaryFileResponse
    {
        return Excel::download(new VaccinationsExport, 'pawid_vaccination_records_'.now('Asia/Manila')->format('Y-m-d').'.xlsx', \Maatwebsite\Excel\Excel::XLSX, [
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function index(): View
    {
        $user = auth()->user();
        $pets = Pet::query()
            ->when(! $user->canManageRecords(), fn ($q) => $q->where('owner_email', $user->email))
            ->orderBy('name')
            ->get();

        $records = Vaccination::query()
            ->with('pet')
            ->whereIn('pet_id', $pets->pluck('id'))
            ->latest('administered_at')
            ->get();

        $currentRecordIds = app(\App\Services\PetCareReminders::class)->latestRecords(Vaccination::class)
            ->whereIn('pet_id', $pets->pluck('id'))->pluck('id');

        $scheduledRecords = app(\App\Services\PetCareReminders::class)->scheduledRecords(Vaccination::class)
            ->whereIn('pet_id', $pets->pluck('id'))->with('pet')
            ->orderBy('next_due_at')->orderBy('id')->get();

        return view('vaccinations', [
            'pets' => $pets,
            'records' => $records,
            'scheduledRecords' => $scheduledRecords,
            'currentRecordIds' => $currentRecordIds,
            'stats' => ['total' => $records->count()] + $this->stats($scheduledRecords),
        ]);
    }

    public function create(Request $request): View
    {
        return $this->createMedicalRecord($request, 'vaccinations');
    }

    public function review(MedicalRecordRequest $request): View
    {
        return $this->reviewMedicalRecord($request, 'vaccinations');
    }

    public function store(MedicalRecordRequest $request): RedirectResponse
    {
        $values = $request->validated();
        DB::transaction(function () use ($values): void {
            $pet = $this->medicalRecordPet($values);
            Pet::whereKey($pet->id)->lockForUpdate()->firstOrFail();
            $pet->vaccinations()->create(Arr::except($values, ['owner_id', 'pet_id']));
            app(\App\Services\PetCareReminders::class)->generateForPet($pet);
        });

        return redirect()->route('vaccinations.index')->with('success', 'Vaccination record saved.');
    }

    public function edit(Vaccination $vaccination): View
    {
        $vaccination->load('pet.owner');
        abort_unless($vaccination->pet, 404);

        return view('medical.form', [
            'type' => 'vaccinations', 'record' => $vaccination,
            'pet' => $vaccination->pet, 'owner' => $vaccination->pet->owner,
            'values' => $vaccination->attributesToArray(),
        ]);
    }

    public function update(MedicalRecordRequest $request, Vaccination $vaccination): RedirectResponse
    {
        $request->validate(['audit_reason' => ['nullable', 'string', 'max:500']]);
        DB::transaction(function () use ($request, $vaccination): void {
            $pet = Pet::whereKey($vaccination->pet_id)->lockForUpdate()->firstOrFail();
            $current = Vaccination::whereKey($vaccination->id)->lockForUpdate()->firstOrFail();
            app(\App\Services\RecordAudit::class)->update($current, Arr::except($request->validated(), ['owner_id', 'pet_id']), $request->user(), $request->input('audit_reason'));
            app(\App\Services\PetCareReminders::class)->generateForPet($pet);
        });

        return redirect()->route('vaccinations.index')->with('success', 'Vaccination record updated successfully.');
    }

    private function stats(Collection $records): array
    {
        // Only scheduled obligations contribute to due-status counts; history remains unfiltered.
        $records = $records->whereNotNull('next_due_at');
        $today = today();
        $dueSoon = $today->copy()->addDays(30);
        $overdue = $records->filter(fn (Vaccination $record) => $record->next_due_at?->isBefore($today))->count();
        $upcoming = $records->filter(fn (Vaccination $record) => $record->next_due_at?->betweenIncluded($today, $dueSoon))->count();

        return ['total' => $records->count(), 'current' => $records->count() - $overdue - $upcoming, 'dueSoon' => $upcoming, 'overdue' => $overdue];
    }
}
