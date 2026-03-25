# API Documentation

## Base URL

```text
http://localhost:8080/api
```

## Authentication

Protected endpoints require:

```text
Authorization: Bearer {token}
```

## Important Auth Notes

- `User` accounts must verify their email after registration before they can log in.
- Admin-only event and reservation endpoints are protected by `ROLE_ADMIN`.
- The API now exposes a dedicated admin login endpoint.

## Auth Endpoints

### Register

```http
POST /api/auth/register
Content-Type: application/json
```

```json
{
  "email": "user@example.com",
  "password": "SecurePass123!"
}
```

Success response:

```json
{
  "success": true,
  "message": "Compte cree. Verifiez votre boite email avant de vous connecter.",
  "verification_sent": true,
  "user": {
    "id": "uuid",
    "email": "user@example.com",
    "roles": ["ROLE_USER"],
    "is_verified": false
  },
  "verification_url": "http://localhost:8080/api/auth/verify/email?..."
}
```

Notes:

- no JWT is returned at registration time anymore
- `verification_url` is provided only outside production to make local testing easier

### Login

```http
POST /api/auth/login
Content-Type: application/json
```

```json
{
  "email": "user@example.com",
  "password": "SecurePass123!"
}
```

If the account is not verified yet, the API returns `403 Forbidden` with:

```json
{
  "error": "Veuillez verifier votre adresse email avant de vous connecter",
  "is_verified": false
}
```

### Verify Email

```http
GET /api/auth/verify/email?expires=...&id=...&signature=...&token=...
```

This endpoint validates the signed verification link and redirects the browser to:

- `/?verified=success`
- `/?verified=already`
- `/?verified=failed`
- `/?verified=invalid`
- `/?verified=missing`

### Admin Login

```http
POST /api/auth/admin/login
Content-Type: application/json
```

```json
{
  "email": "admin@event.com",
  "password": "admin123"
}
```

### Current User

```http
GET /api/auth/me
Authorization: Bearer {token}
```

### Logout

```http
POST /api/auth/logout
Authorization: Bearer {token}
Content-Type: application/json
```

```json
{
  "refresh_token": "refresh-token"
}
```

### Refresh Token

```http
POST /api/token/refresh
Content-Type: application/json
```

```json
{
  "refresh_token": "refresh-token"
}
```

## Passkey Endpoints

### Passkey Register Options

```http
POST /api/auth/passkey/register/options
Content-Type: application/json
```

```json
{
  "email": "user@example.com"
}
```

### Passkey Register Verify

```http
POST /api/auth/passkey/register/verify
Content-Type: application/json
```

```json
{
  "email": "user@example.com",
  "credential": {}
}
```

### Passkey Login Options

```http
POST /api/auth/passkey/login/options
```

### Passkey Login Verify

```http
POST /api/auth/passkey/login/verify
Content-Type: application/json
```

```json
{
  "credential": {}
}
```

## Event Endpoints

### List Events

Public endpoint:

```http
GET /api/events
```

Supported query parameters:

- `page`
- `limit`
- `upcoming=true|false`
- `available=true|false`

Response shape:

```json
{
  "events": [
    {
      "id": "uuid",
      "title": "Conference Tech 2026",
      "description": "Description...",
      "date": "2026-04-15 18:00:00",
      "location": "Sousse",
      "seats": 150,
      "available_seats": 120,
      "image": null,
      "is_available": true,
      "created_at": "2026-03-22 10:00:00",
      "reservations_count": 30
    }
  ],
  "pagination": {
    "page": 1,
    "limit": 10,
    "total": 8,
    "pages": 1
  }
}
```

### Show One Event

Public endpoint:

```http
GET /api/events/{id}
```

### Create Event

Admin only:

```http
POST /api/events
Authorization: Bearer {admin_token}
Content-Type: application/json
```

```json
{
  "title": "New Event",
  "description": "Long enough description for validation.",
  "date": "2026-04-15 18:00:00",
  "location": "Tunis",
  "seats": 200
}
```

## Reservation Endpoints

### Create Reservation

Authenticated user only:

```http
POST /api/reservations
Authorization: Bearer {token}
Content-Type: application/json
```

```json
{
  "event_id": "uuid",
  "name": "John Doe",
  "email": "john@example.com",
  "phone": "+21612345678"
}
```

Success response:

```json
{
  "success": true,
  "message": "Reservation creee avec succes",
  "reservation": {},
  "email_notification_sent": true,
  "warning": null
}
```

Notes:

- a confirmation email is sent after reservation creation
- if email delivery fails, the reservation still stays created and `email_notification_sent` becomes `false`

### Cancel Reservation

Authenticated owner only:

```http
POST /api/reservations/{id}/cancel
Authorization: Bearer {token}
```

Success response:

```json
{
  "success": true,
  "message": "Reservation annulee avec succes",
  "reservation": {},
  "email_notification_sent": true,
  "warning": null
}
```

Notes:

- a cancellation email is sent after reservation cancellation
- if email delivery fails, the cancellation still succeeds and `email_notification_sent` becomes `false`

### Update Event

Admin only:

```http
PUT /api/events/{id}
Authorization: Bearer {admin_token}
Content-Type: application/json
```

Partial updates also work with `PATCH` and the controller also accepts `POST` on the same route.

### Upload Event Image

Admin only:

```http
POST /api/events/{id}/upload-image
Authorization: Bearer {admin_token}
Content-Type: multipart/form-data
```

Accepted file field names:

- `imageFile`
- `image`

Accepted formats:

- JPEG
- PNG
- WebP

### Delete Event

Admin only:

```http
DELETE /api/events/{id}
Authorization: Bearer {admin_token}
```

If the event still has reservations, deletion returns `409 Conflict`.

## Reservation Endpoints

### Create Reservation

Authenticated user only:

```http
POST /api/reservations
Authorization: Bearer {user_token}
Content-Type: application/json
```

```json
{
  "event_id": "uuid",
  "name": "Jean Dupont",
  "email": "jean@example.com",
  "phone": "+21612345678"
}
```

Validation logic:

- event must exist
- event must still be available
- event must not be in the past
- the same user cannot keep two confirmed reservations for the same event

### My Reservations

Authenticated user only:

```http
GET /api/reservations/my-reservations
Authorization: Bearer {user_token}
```

### Show Reservation

Authenticated user only:

```http
GET /api/reservations/{id}
Authorization: Bearer {user_token}
```

Users can only view their own reservations.

### Cancel Reservation

Authenticated user only:

```http
POST /api/reservations/{id}/cancel
Authorization: Bearer {user_token}
```

The controller also accepts `PATCH`.

### Reservations For One Event

Admin only:

```http
GET /api/reservations/event/{eventId}
Authorization: Bearer {admin_token}
```

Response shape:

```json
{
  "event": {
    "id": "uuid",
    "title": "Conference Tech 2026"
  },
  "reservations": [],
  "stats": {
    "total": 0,
    "confirmed": 0,
    "cancelled": 0
  }
}
```
