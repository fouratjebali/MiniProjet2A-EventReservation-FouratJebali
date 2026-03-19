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
   docker-compose up -d
```

3. **Installer les dépendances**
```bash
   docker-compose exec php composer install
```

4. **Créer la base de données**
```bash
   docker-compose exec php php bin/console doctrine:migrations:migrate
   docker-compose exec php php bin/console doctrine:fixtures:load
```

5. **Accéder à l'application**
   - Frontend: http://localhost:8080
   - Admin: admin@event.com / admin123

## 🚀 Lancement
(À compléter)

## 📊 Progression
- [x] Setup projet
- [ ] Configuration Docker
- [ ] Entités & Base de données
- [ ] Authentification JWT/Passkeys
- [ ] CRUD Événements
- [ ] Interface utilisateur
- [ ] Tests