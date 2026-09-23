<?php

namespace App\Services;

use App\Models\{DewormingRecord, Pet, User, Vaccination};
use App\Notifications\PetCareReminder;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PetCareReminders
{
    public function today(): CarbonImmutable
    {
        return CarbonImmutable::now(config('app.timezone'))->startOfDay();
    }

    /** Latest administered date wins, then ID. Separate vaccine names retain separate schedules. */
    public function latestRecords(string $model): Builder
    {
        $table = (new $model)->getTable();

        return $model::query()->whereNotExists(function ($query) use ($table, $model) {
            $query->selectRaw('1')->from($table.' as newer')
                ->whereColumn('newer.pet_id', $table.'.pet_id')
                ->when($model === Vaccination::class, fn ($q) => $q->whereRaw('LOWER(TRIM(newer.vaccine)) = LOWER(TRIM(vaccinations.vaccine))'))
                ->where(function ($q) use ($table) {
                    $q->whereColumn('newer.administered_at', '>', $table.'.administered_at')
                        ->orWhere(fn ($tie) => $tie->whereColumn('newer.administered_at', $table.'.administered_at')->whereColumn('newer.id', '>', $table.'.id'));
                });
        });
    }

    public function schedules(User $owner, ?Pet $pet = null): Collection
    {
        if (! $owner->isOwner()) {
            return collect();
        }

        $today = $this->today();
        $schedules = collect();
        foreach (['vaccination' => Vaccination::class, 'deworming' => DewormingRecord::class] as $type => $model) {
            $records = $this->latestRecords($model)
                ->whereHas('pet', fn ($q) => $q->where('owner_email', $owner->email)->when($pet, fn ($q) => $q->whereKey($pet->id)))
                ->whereNotNull('next_due_at')->with('pet')->get();

            foreach ($records as $record) {
                $due = CarbonImmutable::parse($record->next_due_at->toDateString(), config('app.timezone'))->startOfDay();
                $days = (int) $today->diffInDays($due, false);
                $key = hash('sha256', implode(':', [$owner->id, $record->pet_id, $type, $record->id, $due->toDateString()]));
                $schedules->push([
                    'key' => $key, 'pet' => $record->pet, 'record' => $record, 'type' => $type,
                    'treatment' => $type === 'vaccination' ? $record->vaccine : $record->product,
                    'due' => $due, 'days' => $days,
                    'status' => $days < 0 ? 'Overdue' : ($days === 0 ? 'Due Today' : 'Upcoming'),
                ]);
            }
        }

        return $schedules->sortBy(fn ($schedule) => $schedule['due']->toDateString())->values();
    }

    public function stage(int $days): ?array
    {
        // Catch up once when a new/corrected schedule or a missed daily run enters a window.
        return match (true) {
            $days < 0 => ['overdue', 4],
            $days === 0 => ['due-today', 3],
            $days <= 3 => ['3-day', 2],
            $days <= 7 => ['7-day', 1],
            default => null,
        };
    }

    /** Only current schedules for the session owner; old dates/records never appear, even before the next run. */
    public function activeNotifications(User $owner, ?Collection $schedules = null): MorphMany
    {
        $keys = ($schedules ?? $this->schedules($owner))->filter(fn ($s) => $s['days'] <= 7)->pluck('key');

        return $owner->notifications()->where('type', PetCareReminder::class)
            ->whereIn($this->notificationData('notifications', 'schedule_key'), $keys)
            ->whereNotExists(function ($q) {
                // Retain stage history in storage but show only the most urgent generated stage.
                $q->selectRaw('1')->from('notifications as later')
                    ->whereColumn('later.notifiable_id', 'notifications.notifiable_id')
                    ->whereColumn('later.notifiable_type', 'notifications.notifiable_type')
                    ->whereColumn('later.type', 'notifications.type')
                    ->whereColumn($this->notificationData('later', 'schedule_key'), $this->notificationData('notifications', 'schedule_key'))
                    ->whereColumn($this->notificationData('later', 'priority'), '>', $this->notificationData('notifications', 'priority'));
            });
    }

    private function notificationData(string $table, string $key): Expression
    {
        // Laravel's standard notifications.data column is TEXT. PostgreSQL requires an explicit JSON cast.
        // Both identifiers come exclusively from the fixed strings above, never request input.
        return DB::raw(DB::connection()->getDriverName() === 'pgsql'
            ? "({$table}.data::jsonb ->> '{$key}')"
            : "json_extract({$table}.data, '$.{$key}')");
    }

    public function generateForPet(Pet $pet, bool $dryRun = false): int
    {
        return DB::transaction(function () use ($pet, $dryRun) {
            // Serialize with staff corrections. Reads still verify current ownership/date on every request.
            $currentPet = Pet::whereKey($pet->id)->when(! $dryRun, fn ($q) => $q->lockForUpdate())->first();
            $owner = $currentPet?->owner;
            if (! $owner?->isOwner()) {
                return 0;
            }

            $created = 0;
            foreach ($this->schedules($owner, $currentPet) as $schedule) {
                $stage = $this->stage($schedule['days']);
                if (! $stage) {
                    continue;
                }
                $notification = new PetCareReminder($schedule, ...$stage);
                if (! $owner->notifications()->whereKey($notification->id)->exists()) {
                    if (! $dryRun) {
                        $owner->notifyNow($notification);
                    }
                    $created++;
                }
            }

            return $created;
        });
    }

    public function generate(bool $dryRun = false): int
    {
        $created = 0;
        Pet::whereHas('owner', fn ($q) => $q->where('role', User::ROLE_USER))
            ->chunkById(100, function ($pets) use (&$created, $dryRun) {
                foreach ($pets as $pet) {
                    $created += $this->generateForPet($pet, $dryRun);
                }
            });

        return $created;
    }
}
