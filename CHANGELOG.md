# Changelog

All notable changes to this package are documented here.

## Unreleased

- `Attachments::sync()`: replace a role's file set in one transaction —
  "exactly one invoice per order" as a single call instead of attach-new +
  detach-old that can crash between them. #7

## 0.1.0 — 2026-08-14

- Add tenant-scoped polymorphic file attachments, roles, migrations and Yii3 wiring.
