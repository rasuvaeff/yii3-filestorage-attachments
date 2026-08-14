# Examples

The package is intentionally small. The example shows the service calls that
belong in an application service or an Active Record lifecycle hook.

SQLite enforces the attachment foreign key only when the connection enables
`PRAGMA foreign_keys = ON`; the example does this for its own connection, and
an application must do the same in its connection setup.

Install development dependencies and run from the package checkout:

```bash
composer install
php examples/attachments.php
```
