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

## Email Delivery

By default, development email is sent to Mailpit:

- inbox: `http://localhost:8025`

If you want real Gmail delivery instead, set `MAILER_DSN` in
[symfony-app/.env.local](/Users/Fuuurat/Desktop/php-symphony/MiniProjet2A-EventReservation-FouratJebali/symfony-app/.env.local)
with a Gmail app password:

```env
MAILER_DSN=smtp://your.email%40gmail.com:your16charapppassword@smtp.gmail.com:587?encryption=tls&auth_mode=login
```

Important:

- use a Gmail `App Password`, not your normal Google password
- Gmail app passwords require Google `2-Step Verification`
- `%40` is the URL-encoded form of `@`

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
- Admin login: `http://localhost:8080/admin/`
- Admin dashboard: `http://localhost:8080/admin/dashboard.html`
- Admin event management: `http://localhost:8080/admin/event.html`
- Admin event reservations: `http://localhost:8080/admin/reservations.html?eventId=<event-uuid>`
- Manual auth test page: `http://localhost:8080/test-auth.html`
- Mailpit inbox: `http://localhost:8025`
- API base: `http://localhost:8080/api`

The Nginx config now serves [symfony-app/public/index.html](/Users/Fuuurat/Desktop/php-symphony/MiniProjet2A-EventReservation-FouratJebali/symfony-app/public/index.html) on `/`, while `/api/*` still routes to Symfony.

## Seeded Accounts

Fixtures create:

- admin: `admin@event.com` / `admin123`
- users: `user1@test.com` to `user5@test.com` / `user123`

Important:

- the database contains an admin account
- users must verify their email after registration before they can log in
- reservation confirmation and cancellation emails are sent through the configured mailer
- the project now exposes a dedicated admin login endpoint and admin login page
- admin-only routes still exist at backend level and require a `ROLE_ADMIN` JWT
- Mailpit is available locally to inspect verification emails during development

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
2. Register a new user
3. Open `http://localhost:8025` and click the verification email
4. Log in with the verified account
5. Open `http://localhost:8080/api/events` and copy an event UUID
6. Open `http://localhost:8080/event.html?id=<event-uuid>`
7. Create a reservation
8. Open `http://localhost:8025` and verify the reservation confirmation email
9. Open `http://localhost:8080/my-reservations.html` and verify it appears
10. Cancel the reservation
11. Open `http://localhost:8025` and verify the cancellation email

Important:

- event IDs are UUIDs, not numeric IDs
- `event.html?id=1` will fail because there is no numeric event identifier in the current backend

## Admin Frontend Testing

Current admin pages:

- `http://localhost:8080/admin/`
- `http://localhost:8080/admin/dashboard.html`
- `http://localhost:8080/admin/event.html`
- `http://localhost:8080/admin/reservations.html?eventId=<event-uuid>`

Current admin flow:

- `/admin/` is now a real admin login page
- the admin dashboard is wired to the existing backend routes
- the events page supports create, edit, image upload, delete, and navigation to reservations per event
- the reservations page is read-oriented and shows reservations, stats, filters, and quick contact actions for one event
- the admin session is handled by the frontend login form and stored in browser storage

## Test Status

Backend test suite currently passes with:

```bash
docker compose exec php php bin/phpunit
```

Latest verified result:

- `OK (29 tests, 143 assertions)`

Current note:

- the full suite is green, with 2 existing VichUploader deprecation notices coming from legacy annotation classes in the event upload mapping

## Notes

- run Symfony, Composer, Doctrine, and PHPUnit commands inside the `php` container
- the project is configured for PostgreSQL, so local host PHP without `pdo_pgsql` will fail on Composer or Doctrine commands
- JWT private and public keys are ignored in [symfony-app/config/jwt/.gitignore](/Users/Fuuurat/Desktop/php-symphony/MiniProjet2A-EventReservation-FouratJebali/symfony-app/config/jwt/.gitignore)

## Screenshots

### User Interface

#### Main Page

![Main page](screenshots/mainPage.png)

#### Registration

![Registration](screenshots/inscription.png)

#### Login

![Login](screenshots/connexion.png)

#### Reservation Form

![Reservation form](screenshots/formulaireReservation.png)

#### Reservation Success

![Reservation success](screenshots/reservation.png)

#### Email Verification Sent

![Email verification sent](screenshots/emailConfirmationenvoyee.png)

### Admin Interface

#### Admin Login

![Admin login](screenshots/adminMainPage.png)

#### Admin Dashboard

![Admin dashboard](screenshots/dashboardadmin.png)

#### Event Management

![Event management](screenshots/gestionevent.png)

#### Reservations Per Event

![Reservations per event](screenshots/listeReservationParevent.png)

