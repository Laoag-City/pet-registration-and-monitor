# Retired-field removal audit

Microchip functionality is absent from active PawID code and UI. The existing PostgreSQL column and historical values are preserved. No production records, schema, migration history, or seed data were changed by this task.

## Original reference inventory

Case-insensitive searches covered the full working directory, including hidden/ignored files, dependencies, generated assets, and storage. Git's internal object database was excluded. Searching `microchip` also matches all requested underscore, capitalization, and multiword variants.

| Original location | References found | Resolution |
| --- | --- | --- |
| `app/Models/Pet.php:17` | `$hidden = ['microchip']` | Removed the named retired-field dependency; replaced with supported-field serialization allowlist. |
| `database/migrations/2026_09_07_000001_add_owner_fields_to_pets_table.php:11` | Column ordering after the historical field | Retained unchanged for migration history. |
| `database/migrations/2026_09_08_000000_add_photo_path_to_pets_table.php:11` | Column ordering after the historical field | Retained unchanged for migration history. |
| `tests/Feature/MicrochipRemovalTest.php` | Class/test names, deliberately supplied legacy input, old stored values, schema and serialization assertions, UI/PDF absence assertions | Retained and expanded as backward-compatibility/removal regression tests. These are not production functionality. |
| `tests/Feature/PublicQrScanTest.php` | Legacy fixture attribute/value and the list of data forbidden in public output | Retained as a disclosure regression test. |
| `bootstrap/care-readonly.php:35,58,65,70` | Legacy-column existence check and negative HTML/QR checks | Retained: CLI-only read-only verification, not an application endpoint. |
| `PAWID-COMBINED-IMPLEMENTATION-2026-09-20.md` | Historical feature-removal report, model behavior, test descriptions, and retained-data notes | Retained as historical documentation; this audit supersedes its description of the model's old `$hidden` setting. |
| `PET-MANAGEMENT-IMPLEMENTATION.md` | Protected-field and verification notes | Retained as historical documentation. |
| `STAFF-DASHBOARD-IMPLEMENTATION.md` | Note that the retired field was not added to the dashboard | Retained as historical documentation. |
| `WORKFLOW-IMPLEMENTATION-2026-09-18.md` | Public QR privacy description | Retained as historical documentation. |
| `.phpunit.result.cache` | Test names | Generated test-run metadata; retained. |
| `storage/logs/care-live-checks.json`, `storage/logs/care-baseline.json` | Legacy schema/absence audit results | Audit history retained. Running the existing read-only audit refreshes its current results file. |
| `storage/framework/views/efb01d65ffa723e878f1b3bd3aec648a.php` | Stale compiled template | Removed through `php artisan view:clear`; no reference remains in compiled views. |

No other matching files were found, including in vendor/node dependencies or public assets. This report itself is an additional documentation reference, not active functionality.

## Implementation

- Modified `app/Models/Pet.php`, `tests/Feature/MicrochipRemovalTest.php`, and `tests/Feature/PublicQrScanTest.php`; added this report. Cleared compiled views.
- Pet serialization now explicitly allows supported pet fields, timestamps, and owner/vaccination/deworming relationships. Obsolete storage attributes remain excluded without application code knowing their names. Mass assignment still uses the existing supported-field whitelist.
- Registration, editing, pet details, My Pets, All Pets, search, validation, controllers, vaccination/deworming workflows, QR, and dashboards already had no active references; these were inspected rather than unnecessarily rewritten.
- Regression tests now cover medical selection forms and the staff dashboard as well as existing pet forms, owner pages, medical lists, QR and PDF output. Added a serialization test checking that supported relationships survive and unknown obsolete attributes are excluded.
- An existing working-tree edit had already removed the original column definition from `2026_09_07_000000_create_pets_table.php` before this task. That edit was left untouched. Compatibility tests explicitly simulate the nullable legacy column only in SQLite `:memory:` when necessary. This tests existing installations while allowing registration/edit tests to run against the current clean schema without that column. No old migration was edited by this task.

## Database and verification

The live column is exactly **`public.pets.microchip`**, type `character varying`, nullable, with no default. Its existing `pets_microchip_unique` index remains. PostgreSQL permits multiple null values under this index; it does not require a value for registration. No migration or column drop was necessary or performed.

- Full automated suite: **136 passed, 1,739 assertions**. The existing TestCase refuses any connection other than isolated SQLite `:memory:` before test writes/migrations.
- Existing live PostgreSQL read-only care audit: **zero failed checks**. Registration/edit pages for staff and admin, owner pages, reminders, and public/authenticated QR output rendered without the retired feature.
- PHP changes passed the suite; diff whitespace checks passed.
- Final full-directory search found no references in `app/`, `resources/`, `routes/`, `public/`, factories, or seeders. Remaining matches are exactly the retained migration, compatibility-test, audit, and documentation/generated-history categories listed above. No active feature depends on the column.
- No real pet, user, medical record, QR reference, notification, password, or PostgreSQL configuration was altered. No seeders or destructive database commands ran.

The feature is removed from active functionality, not physically erased from historical storage. Browser visual inspection was unavailable; rendered-page assertions and live HTTP checks verified the UI output.
