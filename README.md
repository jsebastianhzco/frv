# FRV Maintenance

Mobile-first maintenance tracking for Forest Ridge Villas. PHP, MySQL, PDO,
plain HTML/CSS, and optional vanilla JavaScript. English interface, no login,
no build step, no framework, and no Composer dependencies.

## Installation

Requires PHP 8.1+ with PDO MySQL, MySQL 8.0.16+ or MariaDB 10.6+, InnoDB,
utf8mb4, and writable PHP session/temp directories. Enable PHP ZIP for XLSX export.

1. Point the hosting document root at `public/`, **not the repository root**.
2. For a **new, empty database**, import `database/schema.sql` with phpMyAdmin or
   `mysql -u YOUR_USER -p YOUR_DATABASE < database/schema.sql`.
3. Copy `config/database.example.php` to `config/database.php`, then configure
   connection details and the property timezone. The real file is ignored.
4. Add the property's real buildings and apartments to MySQL. No invented property
   data ships with the schema. Building codes and apartment units within a building
   must be unique. HVAC is built in; `departments` is reserved for future modules.
5. Open the site. For local development: `php -S 127.0.0.1:8080 -t public`.

### Existing production database

This repository initially contained only a README, with no prior schema or MVP.
**Do not treat the fresh-install schema as an upgrade migration.** Follow
`database/README.md`: back up, inspect table definitions, and test a schema-specific
additive migration on a restored copy. `CREATE TABLE IF NOT EXISTS` does not repair
incompatible existing tables. No production database was inspected or modified.

## Technician workflow

Home → HVAC → Building → Apartment → Equipment.

Selecting an apartment submits a protected POST that automatically ensures
Central A/C and Mini Split 1 exist, then redirects to equipment. No extra setup tap.
Apartment row locks and unique equipment keys make initialization idempotent and
serialize concurrent additions. “+ Add Mini Split” uses the highest existing number
plus one; Central A/C always has number 1. New equipment starts as Unknown.

Equipment shows status, maintenance dates, and pending work immediately. Expand
Edit Equipment for name, separate line-set/drain statuses, pending work, and summary
dates. Names are editable; the type and number remain visible for identification.
Central A/C also supports Not Applicable for line-set/drain status.

Add Maintenance Record defaults to today. Each event is appended permanently.
Deep Cleaning, Preventive Maintenance, and Filter Change update the matching summary
date in the same transaction. Backdated events do not move a newer summary backwards;
technicians may manually correct or clear dates. History sorts by service date and
insertion ID, newest first. Dates must be valid and not future.

Session CSRF tokens, single-use form tokens, POST/redirect/GET, and unique history
request tokens prevent accidental repeat submissions. Independent intentional forms
still create independent events. Equipment versions reject stale edits instead of
overwriting another technician's work. Validation errors retain typed values;
a stale edit must be reloaded before saving. Text limits are 100 characters for names
and 10,000 for notes/pending work. Timestamps are stored/exported in UTC and displayed
in the configured property timezone; service dates are property calendar dates.

## Export

Export HVAC Data on the building screen downloads a real `.xlsx` with **HVAC
Equipment** and **Maintenance History** worksheets, frozen headers and filters.
All initialized equipment and history are included in one database snapshot.
Unopened/uninitialized apartments have no equipment records to export. Cells are
explicit text, preserving codes and ISO dates and preventing formulas from running.
Export never initializes equipment or mutates records.

Without PHP ZIP, the export screen links to both Excel-compatible UTF-8 CSV files.
Formula prefixes in CSV are neutralized. Export generation lives in
`src/ExportService.php`; no third-party spreadsheet package is required. It loads
the property's data into memory; add streaming if history becomes very large.

## Structure and extension

```text
public/                 HTTP entry points and assets; hosting document root
src/Database.php        PDO configuration
src/HvacRepository.php  Queries, transactions, equipment/history operations
src/Options.php         Status/type catalogs and validation
src/ExportService.php   Workbook and CSV generation
src/Support.php         Escaping, links, CSRF and form helpers
src/bootstrap.php       Startup and generic errors
views/                  Server-rendered pages
config/                 Example settings; ignored local database.php
database/               Fresh schema and existing-database guidance
tests/                  Dependency-free validation/integration tests
```

Add statuses and maintenance categories in `Options.php`; no database ENUM migration
is needed. Map summary-updating categories in `Options::SUMMARY` only when the
corresponding column exists. Equipment IDs allow future photos, serial numbers, or
inventory without changing apartment identity. Those features and other departments
are outside the current scope.

## Deployment and validation

- Use HTTPS. There is deliberately no authentication: anyone who can reach the
  application can view/export records and make changes. Deploy within the intended
  internal network/access boundary.
- Use a runtime database account with SELECT, INSERT, and UPDATE; use a separate
  account for schema setup. Never commit credentials or expose `config/` over HTTP.
- Keep display_errors disabled, logs private, MySQL backed up, and InnoDB enabled.
  User-facing errors omit queries, credentials, and stack traces.
- No destructive SQL, automatic production migration, equipment deletion, or
  history editing/deletion is provided.

Run syntax validation on every PHP file and `php tests/run.php`. For integration,
create an **empty disposable** database named `frv_test_*`, set `FRV_TEST_DATABASE`,
`FRV_TEST_HOST`, `FRV_TEST_PORT`, `FRV_TEST_USER`, and `FRV_TEST_PASSWORD`, then run
`php tests/integration.php`. It refuses non-test names and nonempty databases,
creates test fixtures, and leaves them for inspection. Never use production.
The CI workflow runs against MySQL 8.
