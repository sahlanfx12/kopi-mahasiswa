# Kopi Mahasiswa

Kopi Mahasiswa is a PHP web application for managing coffee shop operations. The project includes administration, customer, cashier, supplier, and owner workflows, along with a five-role AI assistant.

## Technology

- PHP 8.3 is used by the GitHub Actions workflow.
- MySQL is accessed through PHP MySQLi.
- The application also uses browser-based HTML, CSS, and JavaScript assets.
- PHPUnit 10.5 is used for the isolated unit test suite.

## Requirements

- PHP 8.3 with `mysqli`, `curl`, `openssl`, and `mbstring` enabled.
- MySQL or a compatible MySQL server.
- Composer 2.

The PHPUnit development dependency also requires the PHP extensions declared by PHPUnit; the CI workflow enables the XML-related extensions it needs.

## Local database configuration

1. Copy `config.example.php` to `config.php`.
2. Edit `config.php` locally and replace all placeholders with the local database host, username, password, and database name. The schema and migration scripts expect the database name `kopi_mahasiswa`.
3. Ensure the local MySQL connection settings used by `migrate.php` and `migrate_manager.php` have permission to create and update that database.
4. From the project root, run `php migrate.php` to create the database schema, then run `php migrate_manager.php` to add shared permissions, activity, and notification tables used by the application. Both scripts are CLI-only.
5. Apply the SQL files under `database/migrations/` only when their changes are not already present, in filename/date order.
6. Run `php setup_owner.php` from a terminal and create the first Owner using a new name, email, and password. Then sign in with that Owner account.

Do not commit `config.php`, local environment files, cookie files, or credentials. The provided `config.php` is intentionally excluded by `.gitignore`; `config.example.php` contains placeholders only.

The root-level migration scripts execute schema changes when run and must not be exposed as public web endpoints. `setup_owner.php` is also CLI-only; it creates an Owner only when no Owner exists and does not change existing accounts. No demo user accounts or default passwords are installed.

## Composer and unit tests

Install the Composer dependencies and run the unit suite from the project root:

```sh
composer install
composer test
```

The test suite is configured by `phpunit.xml` and currently constrains PHPUnit to `10.5.*`. Unit tests use synthetic input and do not require a database or external API.

There is currently no committed `composer.lock`. Until a lockfile is generated and committed, dependency resolution may select different compatible transitive versions between installations.

## GitHub Actions

`.github/workflows/ci.yml` runs the unit tests on pushes, pull requests, and manual dispatch. It uses Ubuntu, PHP 8.3, Composer 2, and verifies the PHPUnit 10.5 version before running `composer test`.

The current workflow resolves dependencies without a lockfile. CI therefore runs the test suite but is not yet fully reproducible. Once network access permits Composer to create a valid lockfile, commit it and update the workflow to use `composer install`.

## Publishing and credentials

Do not publish real API keys, database credentials, OTP or encryption secrets, access tokens, or authentication cookies. Keep local values in ignored configuration or environment files and use GitHub Actions Secrets for credentials required by a workflow. Review backups, uploaded files, and manual database utilities before publishing.
