# Application Web de Gestion de Réservations d'Événements

## 📝 Description
Application Symfony permettant aux utilisateurs de réserver des événements en ligne avec authentification sécurisée JWT + Passkeys.

## 👥 Équipe
- **Nom**: Fourat Jebali
- **Classe**: GRP 3 / SS GRP 2
- **Année**: 2025-2026

## 🛠 Technologies
- **Backend**: Symfony 7.0
- **Authentification**: JWT + WebAuthn (Passkeys)
- **Base de données**: PostgreSQL 15
- **Containerisation**: Docker
- **Frontend**: JavaScript Vanilla + HTML/CSS

# 📦 Installation

### Prérequis
- Docker & Docker Compose
- Git

### Étapes

1. **Cloner le projet**
```bash
   git clone https://github.com/VOTRE_USERNAME/MiniProjet2A-EventReservation-VotreNom.git
   cd MiniProjet2A-EventReservation-VotreNom
```

2. **Lancer Docker**
```bash
   docker compose up -d
```

3. **Installer les dépendances**
```bash
   docker compose exec php composer install
```

4. **Créer la base de données**
```bash
   docker compose exec php php bin/console doctrine:migrations:migrate
   docker compose exec php php bin/console doctrine:fixtures:load
```

5. **Générer les clés JWT**
```bash
   docker compose exec php php bin/console lexik:jwt:generate-keypair
```

6. **Accéder à l'application**
   - Frontend: http://localhost:8080
   - Admin: admin@event.com / admin123

### 🔐 Authentification

## JWT (JSON Web Tokens)
- **Access Token TTL**: 1 heure
- **Refresh Token TTL**: 30 jours
- **Algorithme**: RS256 (RSA 4096 bits)

## Passkeys (WebAuthn/FIDO2)
- Authentification biométrique sans mot de passe
- Support des clés de sécurité matérielles
- Résistant au phishing

## Configuration

1. **Générer les clés JWT** (déjà fait pendant l'installation):
```bash
   mkdir -p config/jwt
   openssl genpkey -out config/jwt/private.pem -aes256 -algorithm rsa -pkeyopt rsa_keygen_bits:4096
   openssl pkey -in config/jwt/private.pem -out config/jwt/public.pem -pubout
```

2. **Configurer les variables d'environnement**:
   Créer `.env.local` avec:
```env
   JWT_PASSPHRASE=VotrePassphraseSecure
```

3. **Appliquer les migrations**:
```bash
   docker-compose exec php php bin/console doctrine:migrations:migrate
```

## 🔌 API Endpoints

### Authentification

#### Registration classique
```http
POST /api/auth/register
Content-Type: application/json

{
  "email": "user@example.com",
  "password": "SecurePass123!"
}
```

#### Login classique
```http
POST /api/auth/login
Content-Type: application/json

{
  "email": "user@example.com",
  "password": "SecurePass123!"
}
```

#### Informations utilisateur
```http
GET /api/auth/me
Authorization: Bearer {token}
```

#### Refresh token
```http
POST /api/token/refresh
Content-Type: application/json

{
  "refresh_token": "{refresh_token}"
}
```

#### Logout
```http
POST /api/auth/logout
Authorization: Bearer {token}
Content-Type: application/json

{
  "refresh_token": "{refresh_token}"
}
```

### Passkeys (WebAuthn)

#### Enregistrer une passkey - Options
```http
POST /api/auth/passkey/register/options
Content-Type: application/json

{
  "email": "user@example.com"
}
```

#### Enregistrer une passkey - Vérification
```http
POST /api/auth/passkey/register/verify
Content-Type: application/json

{
  "email": "user@example.com",
  "credential": {credential_object}
}
```

#### Login avec passkey - Options
```http
POST /api/auth/passkey/login/options
```

#### Login avec passkey - Vérification
```http
POST /api/auth/passkey/login/verify
Content-Type: application/json

{
  "credential": {credential_object}
}
```

## 🧪 Tests

### Tests unitaires
```bash
# Tous les tests
docker-compose exec php php bin/phpunit

# Tests spécifiques
docker-compose exec php php bin/phpunit tests/Controller/AuthControllerTest.php
docker-compose exec php php bin/phpunit tests/Service/PasskeyAuthServiceTest.php
```

### Tests manuels
Accéder à http://localhost:8080/test-auth.html pour tester l'authentification dans le navigateur.


## 🚀 Lancement
(À compléter)

## 📊 Progression
- [x] Setup projet
- [x] Configuration Docker
- [x] Entités & Base de données
- [x] Authentification JWT/Passkeys
- [x] API Authentication (Register, Login, Passkeys)
- [ ] CRUD Événements
- [ ] Interface utilisateur
- [ ] Tests

## 📚 Ressources

- [JWT RFC 7519](https://datatracker.ietf.org/doc/html/rfc7519)
- [WebAuthn Level 2](https://www.w3.org/TR/webauthn-2/)
- [LexikJWT Documentation](https://github.com/lexik/LexikJWTAuthenticationBundle)
- [WebAuthn Symfony Bundle](https://github.com/web-auth/symfony-bundle)