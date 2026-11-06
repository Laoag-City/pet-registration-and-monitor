# Laoag barangay options

Source: Philippine Statistics Authority, Philippine Standard Geographic Code:
https://psa.gov.ph/classification/psgc/barangays/0102812000

Retrieved 2026-11-03. The reference table identifies 80 barangays as of 31 July
2025 for City of Laoag, PSGC `0102812000`. `App\Support\LaoagBarangays` contains
all 80 official code/label pairs. Codes remain 10-character strings. Options
are sorted by barangay number and letter suffix, not by code or place name.
PSA spellings are retained, including `Bry.` for 18 and 48-A and the missing
comma in `Bgy. No. 32-C La Paz East`. Similar names are separate options.

Both livestock modules use this local source for Add, Edit, validation, and
the barangay filter. Opening forms never calls an external API.

## Storage and historical records

The existing `barangay` text columns continue to store human-readable labels.
No schema changes, migrations, bulk updates, or code conversion are needed.
PSGC codes are retained in the option source and HTML `data-psgc` attributes.
Existing list/detail pages and Excel exports continue to display stored text.

Historical text that is not an exact official label is flagged on the list and
edit form. No name-only or fuzzy matching is performed; for example San Pedro
can refer to different numbered barangays. The edit form offers a checked
"Keep the existing historical value unchanged" control outside the 80-option
dropdown. It permits editing other fields without replacing the stored value.
The server excludes any submitted barangay when that control is used, and
only allows it for an existing historical record. New records always require
an approved selection. To correct a historical record, staff must uncheck the
control and explicitly select a verified official barangay.

The dropdown filter matches the exact official label. Free-text search remains
available to find historical values requiring review. Excel continues to export
all records, preserving historical text alongside official labels.

## Verification

`php artisan test --compact --filter=LaoagBarangayTest`

The focused tests build isolated SQLite in-memory fixtures directly, without
invoking any migration or db command. They cover all 80 codes and numbered
options, natural ordering, both modules' Add/Edit/filter dropdowns, validation,
old input, historical preservation, XLSX output, and authorization.

No deployment migration, package installation, or frontend build is required.
