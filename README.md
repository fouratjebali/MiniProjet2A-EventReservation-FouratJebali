# Event Reservation API

Symfony 7.4 API for event reservation with:

- JWT authentication
- refresh tokens
- WebAuthn / passkeys
- event management
- reservation management
- PostgreSQL + Docker

## Stack

- Symfony 7.4
- PHP 8.2+
- PostgreSQL 15
- Docker Compose
- LexikJWTAuthenticationBundle
- Gesdinet JWT Refresh Token Bundle
- WebAuthn Symfony Bundle
- VichUploaderBundle
- LiipImagineBundle

## Run The Project

From the project root:

```bash
docker compose up --build -d
docker compose exec php composer install
```

## Environment

Set your real JWT passphrase in:

- [symfony-app/.env.local](/Users/Fuuurat/Desktop/php-symphony/MiniProjet2A-EventReservation-FouratJebali/symfony-app/.env.local)

Example:

```env
JWT_PASSPHRASE=your_real_secure_passphrase_here
```

Then generate the JWT keypair:

```bash
docker compose exec php php bin/console lexik:jwt:generate-keypair --overwrite
docker compose exec php php bin/console cache:clear
```

## Database Setup

Development database:

```bash
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec php php bin/console doctrine:fixtures:load --no-interaction
```

This loads sample data including:

- 1 admin
- 5 users
- 8 events
- 15 reservations

## Access

- App / public files: `http://localhost:8080`
- Manual auth test page: `http://localhost:8080/test-auth.html`

## Seeded Accounts

Fixtures create:

- admin: `admin@event.com` / `admin123`
- users: `user1@test.com` to `user5@test.com` / `user123`

Important:

- the database contains an admin account
- the public API currently exposes user auth routes only
- there is no dedicated public admin login endpoint yet
- admin-only routes still exist at backend level and require a `ROLE_ADMIN` JWT

## Main API Areas

- auth: `/api/auth/*`
- events: `/api/events`
- reservations: `/api/reservations`

See:

- [API_DOCUMENTATION.md](/Users/Fuuurat/Desktop/php-symphony/MiniProjet2A-EventReservation-FouratJebali/API_DOCUMENTATION.md)
- [TESTS.md](/Users/Fuuurat/Desktop/php-symphony/MiniProjet2A-EventReservation-FouratJebali/TESTS.md)

## Useful Commands

```bash
docker compose exec php php bin/console cache:clear
docker compose exec php php bin/console doctrine:migrations:status
docker compose exec php php bin/phpunit
```

## Notes

- run Symfony, Composer, Doctrine, and PHPUnit commands inside the `php` container
- the project is configured for PostgreSQL, so local host PHP without `pdo_pgsql` will fail on Composer or Doctrine commands
- JWT private and public keys are ignored in [symfony-app/config/jwt/.gitignore](/Users/Fuuurat/Desktop/php-symphony/MiniProjet2A-EventReservation-FouratJebali/symfony-app/config/jwt/.gitignore)
