# Changelog

All notable changes to this package are documented here.

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
