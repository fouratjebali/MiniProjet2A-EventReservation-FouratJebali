# Tests Manuels - Authentification

## Prérequis
- Docker containers en cours d'exécution
- Navigateur 

## Tests à effectuer

### 1. Registration Classique
**Endpoint:** `POST /api/auth/register`

**Payload:**
```json
{
  "email": "test@example.com",
  "password": "SecurePass123!"
}
```

**Résultat attendu:** HTTP 201, avec `token`, `refresh_token`, et `user`

### 2. Login Classique
**Endpoint:** `POST /api/auth/login`

**Payload:**
```json
{
  "email": "test@example.com",
  "password": "SecurePass123!"
}
```

**Résultat attendu:** HTTP 200, avec tokens

### 3. API Protégée
**Endpoint:** `GET /api/auth/me`

**Headers:**
```
Authorization: Bearer {token}
```

**Résultat attendu:** HTTP 200, avec informations utilisateur

### 4. Refresh Token
**Endpoint:** `POST /api/token/refresh`

**Payload:**
```json
{
  "refresh_token": "{refresh_token}"
}
```

**Résultat attendu:** HTTP 200, nouveau `token`

### 5. Logout
**Endpoint:** `POST /api/auth/logout`

**Payload:**
```json
{
  "refresh_token": "{refresh_token}"
}
```

**Résultat attendu:** HTTP 200, confirmation

## Tests WebAuthn (selon support navigateur)

### 6. Passkey Registration
**Endpoints:**
1. `POST /api/auth/passkey/register/options`
2. `POST /api/auth/passkey/register/verify`

### 7. Passkey Login
**Endpoints:**
1. `POST /api/auth/passkey/login/options`
2. `POST /api/auth/passkey/login/verify`

## Résultats

| Test | Status | Notes |
|------|--------|-------|
| Register classique | ✅ | |
| Login classique | ✅ | |
| /me endpoint | ✅ | |
| Refresh token | ✅ | |
| Logout | ✅ | |
| Passkey register | ⏳ | Selon support |
| Passkey login | ⏳ | Selon support |