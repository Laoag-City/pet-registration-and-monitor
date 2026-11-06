# Medical export completion fix — 5 November 2026

## Root cause

Done inserts a new administration and marks the source record's `schedule_completed_at`. Both are stored in the same medical table. The source also retains its previous administration details and next-due obligation, so overwriting it would lose original history. The previous XLSX query selected every active record for active pets, including both source and completed representation. No SQL join multiplied rows.

## Implementation

Done already writes an atomic `record_change_audits` event with `record_table`, source `record_id`, action `schedule_completed`, and target `changes.administration_id`. These stable IDs are now the authoritative completion relationship for exports. No schema changes, migration, guessed backfill or data cleanup is needed.

`MedicalCompletionLinks` verifies one-to-one links, existing endpoints, same pet, a source completion marker and forward record-ID order. Malformed, missing, conflicting and cross-pet links are not used to hide records. Names and dates never establish identity. Soft-deleted endpoints are checked for relationship provenance but remain excluded by the normal export soft-delete scope.

The export excludes verified source representations at query level, retaining their linked completed administrations. It preserves every linked completed dose: for A → B → C it exports B and C, not just C. A completed administration can carry a future due date without its future treatment being marked Done. Standalone/unlinked doses remain separate even if pet, treatment and dates match exactly. Deleting a completed administration does not revive its superseded source in the report; restoring it makes that completed row visible again.

All 14 columns remain. A linked completed row displays `Completed` and its own actual administration date in completion columns, including when next due is blank. Existing descending administration-date/ID sorting, active-pet filtering, XLSX formatting, text binding and access controls remain. Existing exports are full reports with no request-level search filters; this change does not introduce or remove any such filters.

Done continues using a database transaction and the existing pet-then-record locks. It now also refuses a source that already has a completion audit, even if its completion marker was accidentally cleared. Source details remain in medical history; the new administration and source completion flag remain separate. No future dates are generated automatically.

## Existing records (read-only inspection)

- Deworming: verified source **3 → administration 4**. Record 3 is now excluded from Excel, and record 4 remains Completed with its actual administration date and optional next due.
- Vaccination: source IDs **6 and 9** contain completion markers but no authoritative completion audit links. IDs **7 and 10** are same-date administration candidates. The group is ambiguous: do not assume 6 → 7 or 9 → 10 based on proximity, name or date. All remain stored and exported until an authorized review establishes exact provenance.
- No existing medical record or audit was changed by this fix. No migration or destructive database command was run. `bootstrap/audit-medical-completion-links.php` reports only identifiers and link status in a PostgreSQL read-only transaction.

## Files changed for this fix

- `app/Services/MedicalCompletionLinks.php` (new)
- `app/Exports/MedicalRecordsExport.php`
- `app/Exports/VaccinationsExport.php`
- `app/Exports/DewormingRecordsExport.php`
- `app/Http/Controllers/MedicalCompletionController.php`
- `tests/Feature/MedicalCompletionTest.php`
- `tests/Feature/MedicalExportCompletionTest.php` (new)
- `bootstrap/audit-medical-completion-links.php` (new)
- This document.

## Verification

Regression coverage exercises real XLSX downloads for both modules: one linked completed row, actual date/status, optional next due, repeated Done, successive completions, identical standalone doses, unlinked history, future pending records, sorting, Staff/Admin export access, owner denial, invalid/conflicting links, deletion and restoration. Full-suite results are recorded after execution below.

- Full Laravel suite: **209 passed, 0 failed, 0 skipped; 3,220 assertions** (isolated SQLite memory database).
- PostgreSQL read-only HTTP smoke: **74 checks passed, 0 failed**, including dashboards and QR access.
- PostgreSQL export query/mapping verification: deworming exports ID 4 only, with completion columns `Completed | Nov 5, 2026 | Completed`; source ID 3 is excluded. Ambiguous vaccination records remain visible.
- PHP syntax checks and `git diff --check` passed (existing line-ending notices only).
