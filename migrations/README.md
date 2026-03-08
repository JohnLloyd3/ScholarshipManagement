Migrations README

This folder contains SQL migration files and a simple runner.

Apply migrations:

From project root run:

php scripts/apply_migrations.php

This script executes all `.sql` files in the `migrations/` directory in alphabetical order.

Notes:
- The runner is intentionally simple for local XAMPP use. Review SQL before running on production.
- For production, use proper backup and migration tooling (phinx, Laravel migrations, Flyway, etc.).
- Additional migrations may be added for future schema changes.
