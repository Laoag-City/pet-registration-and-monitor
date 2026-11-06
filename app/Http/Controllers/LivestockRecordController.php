<?php

namespace App\Http\Controllers;

use App\Exports\LivestockRecordsExport;
use App\Http\Requests\LivestockRecordRequest;
use App\Support\LaoagBarangays;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

abstract class LivestockRecordController extends Controller
{
    protected string $model;

    protected string $module;

    protected string $title;

    protected function viewData(): array
    {
        return ['module' => $this->module, 'title' => $this->title, 'health' => $this->module === 'animal-health-monitoring', 'barangays' => LaoagBarangays::options()];
    }

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:150'],
            'barangay' => ['nullable', Rule::in(LaoagBarangays::labels())],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', ...($request->filled('date_from') ? ['after_or_equal:date_from'] : [])],
        ]);
        $records = $this->model::query()->filtered($filters)->orderByDesc('visit_date')->orderByDesc('id')->paginate(15)->withQueryString();

        return view('staff.livestock.index', $this->viewData() + compact('records', 'filters'));
    }

    public function create(): View
    {
        return view('staff.livestock.form', $this->viewData() + ['record' => new $this->model]);
    }

    public function store(LivestockRecordRequest $request): RedirectResponse
    {
        $this->model::create($request->validated());

        return redirect()->route('staff.'.$this->module.'.index')->with('success', 'Record added successfully.');
    }

    public function show(string $record): View
    {
        return view('staff.livestock.show', $this->viewData() + ['record' => $this->model::findOrFail($record), 'confirmDelete' => false]);
    }

    public function edit(string $record): View
    {
        return view('staff.livestock.form', $this->viewData() + ['record' => $this->model::findOrFail($record)]);
    }

    public function update(LivestockRecordRequest $request, string $record): RedirectResponse
    {
        // The preservation flag never accepts a replacement historical value from the client.
        $values = $request->safe()->except('keep_existing_barangay');
        \Illuminate\Support\Facades\DB::transaction(function () use ($record, $values) {
            $this->model::whereKey($record)->lockForUpdate()->firstOrFail()->update($values);
        });

        return redirect()->route('staff.'.$this->module.'.index')->with('success', 'Record updated successfully.');
    }

    public function confirmDelete(string $record): View
    {
        return view('staff.livestock.show', $this->viewData() + ['record' => $this->model::findOrFail($record), 'confirmDelete' => true]);
    }

    public function destroy(Request $request, string $record): RedirectResponse
    {
        $request->validate(['confirm_delete' => ['required', 'accepted']]);
        app(\App\Services\RecordDeletion::class)->delete($this->model::findOrFail($record), $request->user());

        return redirect()->route('staff.'.$this->module.'.index')->with('success', 'Record deleted successfully.');
    }

    public function export(): BinaryFileResponse
    {
        return Excel::download(new LivestockRecordsExport($this->model), 'pawid_'.str_replace('-', '_', $this->module).'_'.now('Asia/Manila')->format('Y-m-d').'.xlsx', \Maatwebsite\Excel\Excel::XLSX, ['Cache-Control' => 'private, no-store']);
    }
}
