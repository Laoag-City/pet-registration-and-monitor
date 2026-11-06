<?php

namespace App\Http\Controllers;

use App\Models\{Vaccination, DewormingRecord};
use App\Services\RecordDeletion;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class MedicalDeletionController extends Controller
{
    public function vaccination(Vaccination $vaccination): View
    {
        return $this->confirmation($vaccination, 'vaccinations');
    }

    public function deworming(DewormingRecord $deworming): View
    {
        return $this->confirmation($deworming, 'deworming');
    }

    private function confirmation(Vaccination|DewormingRecord $record, string $type): View
    {
        abort_unless(auth()->user()?->canManageRecords(), 403);
        $record->load(['pet' => fn ($query) => $query->withTrashed()]);
        return view('medical.delete', compact('record', 'type'));
    }

    public function destroyVaccination(Request $request, Vaccination $vaccination): RedirectResponse
    {
        return $this->destroy($request, $vaccination, 'vaccinations');
    }

    public function destroyDeworming(Request $request, DewormingRecord $deworming): RedirectResponse
    {
        return $this->destroy($request, $deworming, 'deworming');
    }

    private function destroy(Request $request, Vaccination|DewormingRecord $record, string $type): RedirectResponse
    {
        abort_unless($request->user()?->canManageRecords(), 403);
        $request->validate(['confirm_delete' => ['required', 'accepted']]);
        app(RecordDeletion::class)->delete($record, $request->user());
        return redirect()->route($type.'.index')->with('success', 'Record deleted from active records. The pet, owner and retained history are preserved.');
    }
}
