# Changelog

All notable changes to this package are documented here.

## Unreleased

- Mutation testing now counts uncovered code (`--with-uncovered`, template mutator settings): overall MSI 95% against `minMsi` 94. Added tests for the migration schema (columns, unique and lookup indexes, `down()`), the connection-mismatch guard, rethrown integrity errors in `attach()` and `sync()`, and the `sync()` added count. No src changes.

## 0.2.0 — 2026-10-28

- `Attachments::sync()`: replace a role's file set in one transaction —
  "exactly one invoice per order" as a single call instead of attach-new +
  detach-old that can crash between them. #7

## 0.1.0 — 2026-08-14

- Add tenant-scoped polymorphic file attachments, roles, migrations and Yii3 wiring.
