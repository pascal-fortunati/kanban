# 📋 Kanban Étudiants — PHP MVC + Intégration GitHub

> Application Kanban collaborative pour étudiants avec intégration GitHub, développée en PHP avec architecture MVC

[![PHP](https://img.shields.io/badge/PHP-8.1+-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/MySQL-4479A1?logo=mysql&logoColor=white)](https://www.mysql.com/)
[![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-38B2AC?logo=tailwind-css&logoColor=white)](https://tailwindcss.com/)
[![PWA](https://img.shields.io/badge/PWA-Ready-5A0FC8?logo=pwa&logoColor=white)](https://web.dev/progressive-web-apps/)

## ✨ Fonctionnalités

### 🎯 Kanban Board
- **4 colonnes** : Todo, In Progress, Review, Done
- **Drag & Drop** HTML5 avec mise à jour instantanée
- **Gestion des tâches** : création, édition, suppression, déplacement
- **Priorités et labels** personnalisables

### 🔗 Intégration GitHub
- Authentification OAuth GitHub
- Création et gestion de repositories
- **Commits automatiques** sur événements (création, déplacement, complétion)
- Synchronisation bidirectionnelle
- Historique des commits

### 🔔 Notifications en Temps Réel
- Système de polling (10s)
- Badge de notifications
- Panneau dédié avec toasts
- Suivi des événements importants

### 🎨 Interface Moderne
- **Thème Dark/Light** avec persistance localStorage
- Design responsive (Tailwind CSS)
- PWA avec cache offline
- Service Worker pour performances optimales

### 👨‍🏫 Dashboard Formateur
- Statistiques en temps réel
- Gestion des étudiants
- Diffusion de templates de tâches
- Historique et monitoring des commits

## 🚀 Installation

### Prérequis

- PHP 8.1+ avec extensions `pdo_mysql` et `openssl`
- MySQL / MariaDB
- Serveur web avec réécriture d'URL (Apache `mod_rewrite` ou Nginx)

### Étape 1 : Cloner le projet

```bash
git clone https://github.com/votre-username/kanban-etudiants.git
cd kanban-etudiants
```

### Étape 2 : Configuration de la base de données

```bash
# Créer la base de données
mysql -u root -p -e "CREATE DATABASE kanban CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Importer le schéma
mysql -u root -p kanban < database/schema.sql
```

### Étape 3 : Configuration

Éditez `config/database.php` :

```php
return [
    'host' => 'localhost',
    'port' => '3306',
    'dbname' => 'kanban',
    'username' => 'root',
    'password' => 'votre_password',
    'charset' => 'utf8mb4'
];
```

### Étape 4 : Variables d'environnement

Créez un fichier `.env` ou définissez les variables :

```bash
GITHUB_CLIENT_ID=votre_client_id
GITHUB_CLIENT_SECRET=votre_client_secret
APP_KEY=votre_cle_de_chiffrement_32_caracteres
FORCE_HTTPS=1  # Optionnel
```

### Étape 5 : Configuration OAuth GitHub

1. Accédez à [GitHub Developer Settings](https://github.com/settings/developers)
2. Créez une nouvelle OAuth App
3. Configurez :
   - **Homepage URL** : `http://localhost/kanban`
   - **Callback URL** : `http://localhost/kanban/public/github/callback`
4. Récupérez votre `Client ID` et `Client Secret`

### Étape 6 : Configuration serveur

**Apache** : Assurez-vous que le document root pointe vers `/public`

```apache
<VirtualHost *:80>
    ServerName kanban.local
    DocumentRoot /var/www/kanban/public
    
    <Directory /var/www/kanban/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

**Nginx** :

```nginx
server {
    listen 80;
    server_name kanban.local;
    root /var/www/kanban/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
    }
}
```

## 📁 Structure du Projet

```
kanban-etudiants/
├── config/              # Configuration (BDD, GitHub)
├── controllers/         # Contrôleurs MVC
├── models/             # Modèles (User, Task, Repository...)
├── views/              # Vues et templates
├── public/             # Point d'entrée et assets
│   ├── assets/         # CSS, JS, images
│   ├── index.php       # Bootstrap de l'application
│   ├── sw.js           # Service Worker
│   └── manifest.webmanifest
├── core/               # Router et middlewares
├── services/           # Services (GitHub Client)
└── database/           # Schéma SQL
```

## 🎮 Utilisation

### Pour les Étudiants

1. **Inscription/Connexion** : `/auth/register` ou `/auth/login`
2. **Lier GitHub** : Accédez à votre profil → "Connecter GitHub"
3. **Créer un repository** : Depuis le profil, créez votre repo de suivi
4. **Activer le repo** : Sélectionnez le repo actif pour les commits auto
5. **Utiliser le Kanban** : Créez, déplacez et gérez vos tâches

### Pour les Formateurs

1. **Accéder au Dashboard** : `/dashboard`
2. **Consulter les statistiques** : Vue d'ensemble temps réel
3. **Gérer les étudiants** : Liste complète avec leurs repos
4. **Diffuser des templates** : Créez et partagez des modèles de tâches
5. **Monitorer l'activité** : Historique des commits et actions

## 🔒 Sécurité

- ✅ Protection CSRF avec tokens
- ✅ Sessions sécurisées avec régénération d'ID
- ✅ Hachage des mots de passe (bcrypt)
- ✅ Rate limiting contre le brute force
- ✅ Protection XSS (`htmlspecialchars`)
- ✅ Requêtes préparées PDO (SQL injection)
- ✅ Chiffrement des tokens GitHub (AES-256)

## 🛠️ Stack Technique

### Backend
- **PHP 8.1+** avec architecture MVC
- **PDO MySQL** pour la base de données
- **Autoload PSR-4** personnalisé

### Frontend
- **Tailwind CSS** (CDN)
- **Font Awesome 6** pour les icônes
- **Highlight.js** pour la coloration syntaxique
- **SweetAlert2** pour les modales

### PWA
- Service Worker avec stratégies de cache
- Manifest pour l'installation
- Support offline

## 📊 Schéma de Base de Données

### Tables principales

- **users** : Utilisateurs (étudiants/formateurs)
- **repositories** : Repositories GitHub liés
- **tasks** : Tâches du Kanban
- **commits** : Historique des commits
- **notifications** : Système de notifications

Voir `database/schema.sql` pour le schéma complet.

## 🔄 API Routes

### Authentification
```
GET  /auth/login
POST /auth/doLogin
GET  /auth/register
POST /auth/doRegister
GET  /auth/logout
```

### Kanban (Authentifié)
```
GET  /kanban
POST /kanban/create
POST /kanban/move
POST /kanban/update
POST /kanban/delete
GET  /kanban/task/{id}
```

### GitHub
```
GET  /github/authenticate
GET  /github/callback
POST /github/createRepository
POST /github/deleteRepository
POST /github/syncRepositories
GET  /github/getCommits
```

### Dashboard (Formateur)
```
GET  /dashboard
GET  /dashboard/getStats
POST /dashboard/broadcastTemplate
GET  /dashboard/getCommits
```

## 🎨 Personnalisation

### Thème
Le thème est automatiquement persisté dans `localStorage`. Les utilisateurs peuvent basculer entre mode clair et sombre via le bouton dans la barre de navigation.

### PWA
Pour personnaliser l'apparence de la PWA, éditez :
- `public/manifest.webmanifest` : Nom, couleurs, icônes
- `public/sw.js` : Stratégies de cache

## 🐛 Dépannage

### Problème : Erreur 404 sur toutes les routes
**Solution** : Vérifiez que `mod_rewrite` est activé et que `.htaccess` est présent dans `/public`

### Problème : OAuth GitHub échoue
**Solution** : Vérifiez que les URLs de callback correspondent exactement dans les settings GitHub

### Problème : Les commits automatiques ne fonctionnent pas
**Solution** : Assurez-vous que `APP_KEY` est défini et que le token GitHub est valide

## 📝 Licence

Projet académique — Tous droits réservés

## 🤝 Contribution

Les contributions sont les bienvenues ! N'hésitez pas à ouvrir une issue ou une pull request.

---

**Développé Par Pascal avec ❤️ pour les étudiants**