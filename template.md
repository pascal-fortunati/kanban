## JOUR 1 MATIN (9h-12h30)

- **[BDD] Créer la base de données artisan_pro**
  Temps : 0.3h | Priorité : P1
  Détails :
  - Ouvrir phpMyAdmin
  - CREATE DATABASE artisan_pro
  - Encodage UTF-8
  - Commit : "init: database creation"
---
- **[BDD] Créer le schéma complet (8 tables)**
  Temps : 0.5h | Priorité : P1
  Détails :
  - Tables : config, services, realisations, temoignages, contacts, users, stats, pages
  - Types (VARCHAR, TEXT, INT, DECIMAL, DATETIME, ENUM)
  - Clés primaires AUTO_INCREMENT
  - Index sur champs recherchés
  - Timestamps (created_at, updated_at)
  - Relations (si nécessaire)
  - Commit : "feat: complete database schema"
---
- **[BDD] Insérer des données d'exemple (SEED)**
  Temps : 0.3h | Priorité : P1
  Détails :
  - 1 config complète (exemple)
  - Services, réalisations, témoignages, contacts
  - 1 admin (admin/Admin123!)
  - Commit : "seed: sample data"
---
- **[BACKEND] Classe Database (Singleton)**
  Temps : 0.5h | Priorité : P1
  Détails :
  - Fichier : classes/Database.php
  - Pattern Singleton
  - Connexion PDO (ERRMODE_EXCEPTION, FETCH_ASSOC)
  - Méthodes : getInstance(), getConnection()
  - Config : host, dbname, user, password
  - Commit : "feat: Database singleton class"
---
- **[BACKEND] Classe Config (Singleton)**
  Temps : 0.4h | Priorité : P1
  Détails :
  - Fichier : classes/Config.php
  - Récupère la configuration depuis BDD (id=1)
  - Méthodes : get(), update(), uploadLogo()
  - Cache (optionnel)
  - Commit : "feat: Config class"
---
- **[BACKEND] Classe Service (CRUD)**
  Temps : 0.8h | Priorité : P1
  Détails :
  - Fichier : classes/Service.php
  - Propriétés : id, titre, description, tarif, icone, ordre, actif
  - save() (INSERT/UPDATE), delete()
  - static getAll(), getById(), getActive()
  - Requêtes préparées (PDO), validations
  - Commit : "feat: Service CRUD"
---
- **[TEST] Tester la classe Service**
  Temps : 0.2h | Priorité : P2
  Détails :
  - Créer, sauvegarder, récupérer, modifier, supprimer
  - Afficher tous les services
  - Fichier : tests/test_service.php
  - Commit : "test: Service class basic tests"
---

## JOUR 1 APRÈS-MIDI (13h30-17h00)

- **[BACKEND] Classe Realisation (CRUD + Upload)**
  Temps : 0.8h | Priorité : P1
  Détails :
  - Fichier : classes/Realisation.php
  - Propriétés : id, titre, description, image_avant, image_apres, categorie, date_realisation
  - uploadImage() : validations JPEG/PNG, max 5MB, renommage uniqid()
  - Dossier : uploads/realisations/
  - Commit : "feat: Realisation upload"
---
- **[BACKEND] Classe Temoignage (CRUD)**
  Temps : 0.3h | Priorité : P1
  Détails :
  - Fichier : classes/Temoignage.php
  - Propriétés : id, nom_client, avis, note, date, valide
  - getValides(), getRecent(), getMoyenneNotes()
  - Commit : "feat: Temoignage CRUD"
---
- **[BACKEND] Classe Contact (Formulaire + Email)**
  Temps : 0.7h | Priorité : P1
  Détails :
  - Fichier : classes/Contact.php
  - Validation email/téléphone, XSS
  - save() (enregistre + envoie email)
  - Commit : "feat: Contact class with email"
---
- **[BACKEND] Classe User (Authentification)**
  Temps : 0.5h | Priorité : P1
  Détails :
  - Fichier : classes/User.php
  - login/logout/isLoggedIn/getCurrentUser
  - Sécurité : password_hash, session httponly, regenerate ID
  - Commit : "feat: User authentication"
---
- **[ADMIN] Page login (admin/login.php)**
  Temps : 0.4h | Priorité : P1
  Détails :
  - Formulaire username/password
  - Traitement User::login()
  - CSRF token
  - Commit : "feat: admin login page"
---
- **[ADMIN] Dashboard admin (index.php)**
  Temps : 0.4h | Priorité : P1
  Détails :
  - Protection isLoggedIn
  - Cards statistiques (services, réalisations, contacts, témoignages)
  - Derniers contacts (5)
  - Commit : "feat: admin dashboard"
---

## JOUR 2 MATIN (9h-12h30)

- **[FRONTEND] Structure HTML + Templates**
  Temps : 0.4h | Priorité : P1
  Détails :
  - includes/header.php, footer.php
  - Header : logo, menu, CTA
  - Footer : coordonnées, horaires, liens
  - CSS : variables depuis Config
  - Commit : "feat: frontend header and footer"
---
- **[FRONTEND] Hero Section**
  Temps : 0.3h | Priorité : P1
  Détails :
  - Fichier : index.php / templates/hero.php
  - Image de fond + overlay sombre
  - H1 + slogan (Config)
  - CTA "Demander un devis"
  - Responsive (texte centré mobile)
  - Commit : "feat: hero section"
---
- **[FRONTEND] Section "Mes Services"**
  Temps : 0.4h | Priorité : P1
  Détails :
  - Grid responsive
  - Service::getActive() + cards (icone, titre, extrait, tarif)
  - Lien "En savoir plus"
  - Commit : "feat: services section"
---

## JOUR 2 APRÈS-MIDI (13h30-17h00)

- **[FRONTEND] Section "Réalisations"**
  Temps : 0.5h | Priorité : P1
  Détails :
  - Grid 3 colonnes (6 photos)
  - Realisation::getRecent(6)
  - Lightbox (lib JS ou modale custom)
  - Slider before/after (optionnel)
  - Commit : "feat: portfolio section"
---
- **[FRONTEND] Section "Témoignages"**
  Temps : 0.4h | Priorité : P1
  Détails :
  - Slider 3 témoignages
  - Temoignage::getValides(3)
  - Nom, note, avis
  - Lib JS : Swiper/Slick
  - Commit : "feat: testimonials slider"
---
- **[FRONTEND] Page contact.php**
  Temps : 0.5h | Priorité : P1
  Détails :
  - Formulaire (nom/email/tel/message)
  - Validation client + serveur
  - Messages succès/erreur
  - Coordonnées + Google Maps (iframe)
  - Commit : "feat: contact page"
---
- **[FRONTEND] Menu responsive (burger)**
  Temps : 0.2h | Priorité : P1
  Détails :
  - CSS: burger mobile
  - JS: toggle + animations + fermeture au clic hors menu
  - Commit : "feat: responsive burger menu"
---
- **[SEO] Optimisation SEO on-page**
  Temps : 0.5h | Priorité : P3
  Détails :
  - Meta title/description
  - URLs propres (.htaccess)
  - Sitemap.xml
  - Schema.org LocalBusiness
  - Commit : "feat: SEO on-page"
---
- **[DESIGN] Mode sombre**
  Temps : 0.5h | Priorité : P3
  Détails :
  - Toggle dark/light
  - Variables CSS
  - LocalStorage préférence
  - Frontend ET admin
  - Commit : "feat: dark mode"
---
- **[DOCS] README complet**
  Temps : 0.2h | Priorité : P1
  Détails :
  - Description, installation, utilisation
  - Structure projet, technologies
  - Auteurs, licence
  - Commit : "docs: complete README"
---
- **[TEST] Tests finaux**
  Temps : 0.2h | Priorité : P1
  Détails :
  - Parcourir toutes les fonctionnalités
  - Frontend: navigation, formulaires, responsive
  - Backend: CRUD, login, upload
  - Corrections mineures
  - Commit : "fix: final bug fixes"
---