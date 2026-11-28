# Changelog

All notable changes to this package are documented here.

## 0.6.0 — 2026-11-28

Pre-1.0 review fixes (#20).

- **Changed:** requires `yiisoft/db-migration` `^2.1.1`: 2.1.0 does not report how many rows `MigrationBuilder::delete()` removed, and the FK migration's documented "Deleted N rows" line comes from it.

- **Changed:** requires `rasuvaeff/yii3-filestorage` `^0.10` and `rasuvaeff/yii3-filestorage-db` `^0.4`. No API change here; built and tested against both (Unit, Integration on SQLite, MySQL 8.4, MariaDB 11.8, PostgreSQL 17).
- **Changed (security):** a bound `FileScopeProviderInterface` returning `null` makes every `Attachments` method throw `Rasuvaeff\Yii3FilestorageDb\Exception\MissingScopeException`. Before, it fell back to the single-tenant scope key `''`, and `attach()`'s file lookup ran without a tenant predicate, so a file of another tenant could be linked.
- **Fixed:** on MySQL/MariaDB the default collation compared `scope_id`, `owner_type`, `owner_id` and `role` case- and accent-insensitively: owner `Ab` saw and `detachOwner()` removed the links of owner `aB`, and the unique key refused `Ab`'s first link, which `attach()` reported as "already linked". New migration `M261128000000MakeFilestorageAttachmentKeysCaseSensitive` switches the four columns to a binary NO PAD collation — `utf8mb4_0900_bin` on MySQL 8.0.17+, `utf8mb4_nopad_bin` on MariaDB, a `RuntimeException` on older MySQL — so case, accents and trailing spaces count (`utf8mb4_bin` is PAD SPACE: owner `42 ` would have been owner `42`, a match MySQL 8's NO PAD default never made). No-op on SQLite/PostgreSQL. Run `migrate:up` after upgrading; on MySQL/MariaDB it rebuilds the table and blocks writes meanwhile — see `UPGRADE.md`.
- `ExactCollation` (`@internal`) picks the collation; the migration only asks the server for `VERSION()` and whether `utf8mb4_0900_bin` exists.
- `forOwner()` reads the linked files with one scoped `DbRepository::findMany()` query instead of one `find()` per link; `sync()` checks the visibility of the whole set the same way (the first invisible file is still the one named).
- Docs: MariaDB 10.5+ creates the file foreign key from the table migration's column-level `REFERENCES` (only MySQL ignores it); README EN/RU, llms.txt, the skill and `M261127000000AddFilestorageAttachmentFileForeignKey`'s docblock said both ignore it. The migration already found the key and did nothing there.
- CI: the `database-integration` matrix gained a `mariadb` leg (`mariadb:11.8`, check name `mariadb integration`); `CrossDatabaseMigrationTest` passes there and checks owners, owner types and roles differing in a trailing space.
- Docs: `M261127000000AddFilestorageAttachmentFileForeignKey` (0.5.0) deletes dangling links before adding the constraint, prints the count (`Deleted N rows`) and cannot be atomic on MySQL — a failed `ALTER` keeps the deletion. Now stated in README EN/RU, llms.txt and `UPGRADE.md`; a test pins the printed count.
- `extra.branch-alias.dev-master` is `0.6.x-dev`.

## 0.5.0 — 2026-11-27

- **Changed:** requires `rasuvaeff/yii3-filestorage` `^0.9` and `rasuvaeff/yii3-filestorage-db` `^0.3`, so the package installs on the family's 0.9 line (core's 0.9.0 notes list the interface changes for store, repository and key-generator implementers; the attachment API is unchanged). This release no longer installs with core 0.8; stay on 0.4.0 for that.
- Run `migrate:up` after upgrading: `yii3-filestorage-db` 0.3.0 adds `M261127000000AddFilestorageFileStorePathIndex`. The test schema, `MigrationTest`, `CrossDatabaseMigrationTest` and `examples/attachments.php` now apply it with the other `-db` migrations.
- `extra.branch-alias.dev-master` is `0.5.x-dev` (was a stale `0.1.x-dev`).
- `.github/CODEOWNERS` added from the template.
- CI: the backward-compatibility step tolerates a report across a pre-1.0 minor declared in `CHANGELOG.md`, as the template does.

## 0.4.0 — 2026-11-27

- **Fixed:** on MySQL/MariaDB removing a file left its attachment links behind. They parse the table migration's column-level `REFERENCES ... ON DELETE CASCADE` and ignore it, so no foreign key existed. The new migration `M261127000000AddFilestorageAttachmentFileForeignKey` adds it wherever the table has none: it first **deletes links whose file no longer exists** (the migration output prints the count), then adds the cascading constraint. On SQLite and PostgreSQL, which already have the constraint, it does nothing. #17
- Requires `rasuvaeff/yii3-filestorage-db` `^0.2.1`, which fixes deduplicated removes on MySQL and PostgreSQL.
- `CrossDatabaseMigrationTest` runs the migrations and a file removal through core on MySQL 8.4 and PostgreSQL in a new ungated `database-integration` CI job.

## 0.3.1 — 2026-11-27

- Docs: the agent skill printed the `Attachments` class name with doubled backslashes.

## 0.3.0 — 2026-11-27

- **Changed:** requires `rasuvaeff/yii3-filestorage` `^0.8.2` and `rasuvaeff/yii3-filestorage-db` `^0.2`, so the whole family installs on one line of the core. This release no longer installs with core 0.1; stay on 0.2.1 for those.

## 0.2.1 — 2026-11-19

- The agent skill now has front matter (`name`, `description`), so skill loaders can discover it (#12).
- `make test-integration`, which the README and `AGENTS.md` already referenced, now exists.
- Mutation testing now counts uncovered code (`--with-uncovered`, template mutator settings): overall MSI 95% against `minMsi` 94. Added tests for the migration schema (columns, unique and lookup indexes, `down()`), the connection-mismatch guard, rethrown integrity errors in `attach()` and `sync()`, and the `sync()` added count. No src changes.

## 0.2.0 — 2026-10-28

- `Attachments::sync()`: replace a role's file set in one transaction —
  "exactly one invoice per order" as a single call instead of attach-new +
  detach-old that can crash between them. #7

## 0.1.0 — 2026-08-14

- Add tenant-scoped polymorphic file attachments, roles, migrations and Yii3 wiring.
