# Medical schedule completion and pet species

## Done actions

The existing medical rows represent administrations. Their `next_due_at` is a
following schedule, so Done completes that schedule rather than relabelling the
original administration. Staff/admin users see Done only for a current schedule
with a due date and no recorded completion.

The Bootstrap confirmation modal shows pet, treatment and due date, asks for the
actual administration date (defaults to today), and requires confirmation. An
optional next due date belongs to the new administration. The old row retains
its administration, due date, notes and other details. A separate administration
is created; its clinician/weight/notes are not copied from the earlier visit.
Use Edit on the new record to enter those details or its next due date.

Both operations commit together under the same pet lock used by the existing
medical workflows. Eligibility is rechecked after locking. Already completed,
superseded, unscheduled and archived-pet records cannot be completed. Actual
dates cannot precede the earlier administration or be in the future. An entered
following due date must be later than the actual administration date.

Routes (existing auth, auth.session and role:staff,admin middleware):
- POST /staff/vaccinations/{vaccination}/done — vaccinations.done
- POST /staff/deworming/{deworming}/done — deworming.done

Completed schedules keep their Completed badge as time passes. The shared
reminder query excludes them, while the new administration's next due date is
tracked normally. Old notification history stays stored but inactive. Dashboard
attention counts reuse this query. No future obligation is automatically completed.

Excel retains all previous columns and appends Schedule Completion Status and
Actual Completion Date. Legacy rows without explicit confirmation show Not
recorded (or No schedule), not an invented completion status. Existing archived
pet export visibility remains unchanged.

## Pet species

Register/Edit use the shared `staff.pet-species` partial and `PetSpecies` helper.
Canine and Feline save directly; Others requires Specify Species and stores that
text. Arbitrary dropdown submissions are rejected. Historical Dog/Canine and
Cat/Feline values select Canine and Feline respectively; other historical values
select Others and fill the original text. Opening a form never writes to storage.
Explicitly saving the form stores the submitted selection. Existing dynamic
species filters/analytics and QR displays continue using stored species values.
Livestock forms and their six approved species are untouched.

## Files

Created:
- app/Http/Controllers/MedicalCompletionController.php
- app/Support/PetSpecies.php
- resources/views/medical/done-button.blade.php
- resources/views/medical/done-modals.blade.php
- resources/views/staff/pet-species.blade.php
- database/migrations/2026_11_04_000001_add_schedule_completion_to_medical_records.php
- tests/Feature/MedicalCompletionTest.php
- tests/Feature/PetSpeciesSelectionTest.php
- docs/medical-completion-and-pet-species.md

Modified:
- routes/web.php
- app/Models/Vaccination.php and app/Models/DewormingRecord.php
- app/Services/PetCareReminders.php
- app/Http/Controllers/StaffController.php
- app/Http/Requests/UpdatePetRequest.php
- app/Exports/MedicalRecordsExport.php, VaccinationsExport.php, DewormingRecordsExport.php
- resources/views/vaccinations.blade.php and deworming.blade.php
- resources/views/staff/register-pet.blade.php and edit-pet.blade.php
- tests/Feature/MedicalExportTest.php, PetManagementTest.php,
  MicrochipRemovalTest.php, SystemAuditTest.php (updated HTTP species payloads)

## Deployment and verification

The local additive migration has been applied. Other deployments must apply it
before using the updated reminder queries:

```sh
php artisan migrate --force --path=database/migrations/2026_11_04_000001_add_schedule_completion_to_medical_records.php
```

No new package or frontend build is required. Run `php artisan test --compact`;
tests use the existing guarded SQLite in-memory database. New tests cover
completion, repeat submissions, date checks, roles, archived pets, history,
future reminders, dashboard counts, exports, and species selection/editing.
