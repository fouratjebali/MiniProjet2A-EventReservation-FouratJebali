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

- Frontend home page: `http://localhost:8080`
- Event details page: `http://localhost:8080/event.html?id=<event-uuid>`
- My reservations page: `http://localhost:8080/my-reservations.html`
- Admin dashboard: `http://localhost:8080/admin/`
- Admin event management: `http://localhost:8080/admin/event.html`
- Admin event reservations: `http://localhost:8080/admin/reservations.html?eventId=<event-uuid>`
- Manual auth test page: `http://localhost:8080/test-auth.html`
- API base: `http://localhost:8080/api`

The Nginx config now serves [symfony-app/public/index.html](/Users/Fuuurat/Desktop/php-symphony/MiniProjet2A-EventReservation-FouratJebali/symfony-app/public/index.html) on `/`, while `/api/*` still routes to Symfony.

## Seeded Accounts

Fixtures create:

- admin: `admin@event.com` / `admin123`
- users: `user1@test.com` to `user5@test.com` / `user123`

Important:

- the database contains an admin account
- the public API currently exposes user auth routes only
- there is no dedicated public admin login endpoint yet
- admin-only routes still exist at backend level and require a `ROLE_ADMIN` JWT
- the admin frontend exists and is testable, but it currently expects an admin token already stored in the browser

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

## Frontend Testing

Quick manual flow:

1. Open `http://localhost:8080`
2. Register or log in with one of the seeded user accounts
3. Open `http://localhost:8080/api/events` and copy an event UUID
4. Open `http://localhost:8080/event.html?id=<event-uuid>`
5. Create a reservation
6. Open `http://localhost:8080/my-reservations.html` and verify it appears

Important:

- event IDs are UUIDs, not numeric IDs
- `event.html?id=1` will fail because there is no numeric event identifier in the current backend

## Admin Frontend Testing

Current admin pages:

- `http://localhost:8080/admin/`
- `http://localhost:8080/admin/event.html`
- `http://localhost:8080/admin/reservations.html?eventId=<event-uuid>`

Current admin flow:

- the admin dashboard is wired to the existing backend routes
- the events page supports create, edit, image upload, delete, and navigation to reservations per event
- the reservations page is read-oriented and shows reservations, stats, filters, and quick contact actions for one event
- there is still no public admin login page yet

To test the admin interface today, generate a real admin JWT from the `php` container:

```powershell
@'
<?php
require '/var/www/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv('/var/www/.env');

$kernel = new App\Kernel($_SERVER['APP_ENV'] ?? 'dev', (bool) ($_SERVER['APP_DEBUG'] ?? true));
$kernel->boot();

$container = $kernel->getContainer();
$admin = $container->get('doctrine')->getRepository(App\Entity\Admin::class)->findOneBy([
    'email' => 'admin@event.com'
]);

echo $container->get('lexik_jwt_authentication.jwt_manager')->create($admin), PHP_EOL;
'@ | docker compose exec -T php php /dev/stdin
```

Then in browser storage for `http://localhost:8080`, set:

- `jwt_token` = the generated token
- `auth_user` = `{"email":"admin@event.com","roles":["ROLE_ADMIN"]}`

After that, reload `/admin/`.

## Test Status

Backend test suite currently passes with:

```bash
docker compose exec php php bin/phpunit
```

Latest verified result:

- `OK (27 tests, 117 assertions)`

## Notes

- run Symfony, Composer, Doctrine, and PHPUnit commands inside the `php` container
- the project is configured for PostgreSQL, so local host PHP without `pdo_pgsql` will fail on Composer or Doctrine commands
- JWT private and public keys are ignored in [symfony-app/config/jwt/.gitignore](/Users/Fuuurat/Desktop/php-symphony/MiniProjet2A-EventReservation-FouratJebali/symfony-app/config/jwt/.gitignore)
