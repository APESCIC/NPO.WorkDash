# Demo data

The demo data is fictional and only for the Laragon preview. `database/seeders/DemoSeeder.php` uses Faker in `en_GB` with a fixed seed (`20261109`), so every run gives the same records. Every email address uses the reserved `example.test` domain, and a test enforces it.

| Command | What it does |
| --- | --- |
| `php artisan demo:seed` | Loads the demo data into the local database |
| `php artisan demo:reset` | Empties the local database (`migrate:fresh`), then loads the demo data |

Both refuse unless `APP_ENV=local` and the database is on `127.0.0.1` or `localhost`. Never seed real personal data.

## What it creates

- Three tenants: Demo Animal Rescue (fake), Demo Wildlife Trust (fake) and Demo Reptile Sanctuary (fake).
- The demo login and twelve more users (`demo-user-01@example.test` to `demo-user-12@example.test`), all verified.

## Test logins

| Login | Password |
| --- | --- |
| `demo@example.test` | `password` |
| `demo-user-01@example.test` to `demo-user-12@example.test` | `password` |
