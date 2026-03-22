# Tests

## Automated Tests

Run all tests from the project root:

```bash
docker compose exec php php bin/phpunit
```

Run specific test files:

```bash
docker compose exec php php bin/phpunit tests/Controller/AuthControllerTest.php
docker compose exec php php bin/phpunit tests/Controller/EventControllerTest.php
docker compose exec php php bin/phpunit tests/Controller/ReservationControllerTest.php
docker compose exec php php bin/phpunit tests/Service/PasskeyAuthServiceTest.php
```

## Current Tested Areas

- user registration and login
- `/api/auth/me`
- passkey registration options
- public event listing and event details
- admin-only event creation rules
- reservation creation, duplicate protection, listing, ownership, cancellation

## Test Environment Notes

PHPUnit runs in `APP_ENV=test`.

If the test database does not exist yet, create it from the project root:

```bash
docker compose exec db psql -U appuser -d postgres -c "CREATE DATABASE event_reservation_test;"
docker compose exec php php bin/console doctrine:schema:create --env=test
```

This project currently has older migration history mixed with newer entity changes, so the most reliable setup for the test database is:

- create `event_reservation_test`
- build the schema from current entities with `doctrine:schema:create --env=test`

## Manual Browser Tests

Open:

```text
http://localhost:8080/test-auth.html
```

That page can be used to test:

- user registration
- user login
- `/api/auth/me`
- passkey registration flow
- passkey login flow

## Expected Results

- auth controller tests pass
- event controller tests pass
- reservation controller tests pass
- passkey service test passes

If a test fails because of missing JWT keys or passphrase mismatch, regenerate the keypair with your current `.env.local` passphrase:

```bash
docker compose exec php php bin/console lexik:jwt:generate-keypair --overwrite
```
