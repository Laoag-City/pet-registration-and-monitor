<?php

namespace App\Services;

use App\Models\{DewormingRecord, Pet, User, Vaccination};
use Illuminate\Support\Facades\DB;

class StaffDashboardAnalytics
{
    public function __construct(private PetCareReminders $reminders) {}

    public function data(User $user): array
    {
        $today = $this->reminders->today();
        $yearStart = $today->startOfYear();
        $monthExpression = DB::connection()->getDriverName() === 'sqlite'
            ? "CAST(strftime('%m', created_at) AS INTEGER)"
            : 'EXTRACT(MONTH FROM created_at)';
        $monthly = Pet::query()->where('created_at', '>=', $yearStart)
            ->where('created_at', '<', $yearStart->addYear())
            ->selectRaw($monthExpression.' AS month, COUNT(*) AS total')
            ->groupByRaw($monthExpression)->pluck('total', 'month');
        $registrations = collect(range(1, 12))->map(fn ($month) => (int) ($monthly[$month] ?? 0))->all();
        $petCount = Pet::count();
        $species = Pet::query()->selectRaw("COALESCE(NULLIF(LOWER(TRIM(species)), ''), 'unspecified') AS species_name, COUNT(*) AS total")
            ->groupByRaw("COALESCE(NULLIF(LOWER(TRIM(species)), ''), 'unspecified')")
            ->orderByDesc('total')->orderBy('species_name')->get()
            ->map(fn ($row) => ['label' => ucfirst($row->species_name), 'count' => (int) $row->total,
                'percentage' => $petCount ? round($row->total / $petCount * 100, 1) : 0])->all();

        $attention = collect();
        $statuses = [];
        foreach (['vaccination' => Vaccination::class, 'deworming' => DewormingRecord::class] as $type => $model) {
            $counts = ['Up to Date' => 0, 'Due Soon' => 0, 'Due Today' => 0, 'Overdue' => 0];
            $unscheduled = 0;
            // Reuse reminder supersession rules, including independent vaccine schedules.
            $records = $this->reminders->latestRecords($model)->whereHas('pet')
                ->select(['id', 'pet_id', 'next_due_at'])->with('pet:id,name')->get();
            foreach ($records as $record) {
                if (! $record->next_due_at) {
                    $unscheduled++;
                    continue;
                }
                $due = $record->next_due_at->toImmutable()->startOfDay();
                $days = (int) $today->diffInDays($due, false);
                $stage = $this->reminders->stage($days);
                $status = match ($stage[0] ?? null) {
                    'overdue' => 'Overdue', 'due-today' => 'Due Today',
                    '3-day', '7-day' => 'Due Soon', default => 'Up to Date',
                };
                $counts[$status]++;
                if ($stage) {
                    $attention->push(['pet' => $record->pet, 'type' => ucfirst($type), 'due' => $due,
                        'status' => $status, 'priority' => match ($status) { 'Overdue' => 0, 'Due Today' => 1, default => 2 }]);
                }
            }
            $total = array_sum($counts);
            $statuses[$type] = ['counts' => $counts, 'total' => $total, 'unscheduled' => $unscheduled,
                'percentage' => $total ? (int) round($counts['Up to Date'] / $total * 100) : null];
        }

        return compact('user', 'petCount', 'registrations', 'species', 'statuses') + [
            'userCount' => User::where('role', User::ROLE_USER)->count(),
            'vaccinationCount' => Vaccination::whereHas('pet')->count(), 'dewormingCount' => DewormingRecord::whereHas('pet')->count(),
            'year' => $today->year, 'thisMonthCount' => $registrations[$today->month - 1],
            'yearCount' => array_sum($registrations),
            'attentionCount' => $attention->pluck('pet.id')->unique()->count(),
            'attention' => $attention->sortBy([['priority', 'asc'], ['due', 'asc'], ['pet.id', 'asc']])->take(5)->values(),
        ];
    }
}
