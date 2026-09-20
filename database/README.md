# Existing-data upgrade

`schema.sql` is a non-destructive **fresh-install baseline**, not a universal migration.
It has no DROP, TRUNCATE, DELETE, property fixtures, or automatic data conversion.
Foreign keys restrict deleting referenced apartments/equipment and protect history.

For an empty database, execute all of `schema.sql` once. Reapplying it does not erase
rows, but does not update existing definitions either.

For a populated database:

1. Export a full backup including structure, and verify restoration.
2. On a restored staging copy run `SHOW TABLES` and `SHOW CREATE TABLE` for buildings,
   apartments, departments, hvac_units, and hvac_maintenance_history when present.
   Compare with `schema.sql` before executing DDL.
3. Preserve building/apartment IDs. Match signedness and types of foreign keys.
   Audit duplicate apartment and equipment identities before adding unique keys;
   do not automatically delete duplicates.
4. If HVAC tables are absent and parent definitions match, execute the two HVAC
   CREATE statements from `schema.sql`. Create any missing parent tables first in
   schema order. For existing HVAC tables, prepare additive ALTER statements for
   their actual definitions, including `version` and unique `request_token`.
   Existing history requires distinct backfilled tokens before enforcing NOT NULL.
5. For apartment-level legacy HVAC data, agree on equipment mapping before copying.
   Do not guess whether a record belongs to Central A/C or Mini Split 1. Preserve
   original records and reconcile counts/dates.
6. Test defaults, saves, history, concurrent additions, and exports on staging.
   Apply reviewed migrations during a maintenance window using a migration account.
   MySQL DDL may auto-commit; a transaction is not a backup.

No legacy table definitions were present in this repository. An executable legacy
migration requires those definitions first. The application never changes schema.
