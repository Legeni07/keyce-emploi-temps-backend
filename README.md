# 🎓 Keyce Informatique — Backend API REST (PHP Natif)
### Gestion des Emplois du Temps Hebdomadaires

> **Backend fourni aux étudiants** pour tester leurs projets React.js  
> Cours : Développement Frontend React | Classe B2 | 2025-2026  
> Examinateur : BOGNI-DANCHI T.

---

## 📋 Description

Ce dépôt contient le **backend PHP natif** (sans framework) fournissant une **API REST complète** pour l'application de gestion des emplois du temps de Keyce Informatique.

Votre mission en tant qu'étudiant est de **développer uniquement le frontend React.js** qui consomme cette API.

---

## 🚀 Installation Rapide

### Prérequis

| Outil | Version minimale |
|-------|----------------|
| PHP | 8.1+ |
| MySQL / MariaDB | 5.7+ / 10.4+ |
| Apache (mod_rewrite) ou Nginx | Toute version récente |
| Composer | Optionnel (aucune dépendance) |

### Étapes d'installation

```bash
# 1. Cloner le dépôt
git clone https://github.com/keyce-informatique/keyce-emploi-temps-backend.git
cd keyce-emploi-temps-backend

# 2. Configurer l'environnement
cp .env.php.example .env.php
# Éditer .env.php avec vos identifiants MySQL

# 3. Créer la base de données et importer les données
mysql -u root -p < sql/keyce_emploi_temps.sql

# 4. Configurer votre serveur web (voir section ci-dessous)

# 5. Tester l'API
curl http://localhost/keyce-backend/api/
```

### Configuration Apache (XAMPP / WAMP / LAMP)

Placez le dossier dans `htdocs/` (XAMPP) ou `www/` (WAMP) :

```
htdocs/
└── keyce-backend/      ← ce dossier
    ├── .htaccess
    ├── index.php
    └── ...
```

Activez `mod_rewrite` dans `httpd.conf` :
```apache
LoadModule rewrite_module modules/mod_rewrite.so
```

Assurez-vous que votre VirtualHost ou `.htaccess` autorise `AllowOverride All`.

**URL de base :** `http://localhost/keyce-backend/`

### Configuration Nginx

```nginx
server {
    listen 80;
    server_name localhost;
    root /var/www/keyce-backend;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Bloquer l'accès aux dossiers sensibles
    location ~ ^/(config|middleware|utils|sql) {
        deny all;
    }
}
```

---

## 🔑 Comptes de Démonstration

| Rôle | Email | Mot de passe |
|------|-------|-------------|
| **Administrateur** | admin@keyce.cm | `Keyce2025!` |
| **Responsable pédagogique** | responsable@keyce.cm | `Keyce2025!` |
| **Enseignant 1** (Bogni-Danchi) | enseignant1@keyce.cm | `Keyce2025!` |
| **Enseignant 2** (Kamga) | enseignant2@keyce.cm | `Keyce2025!` |
| **Étudiant IABD B2** | etudiant1@keyce.cm | `Keyce2025!` |
| **Étudiant DEV B2** | etudiant2@keyce.cm | `Keyce2025!` |

> ⚠️ Ces comptes sont **uniquement pour les tests**. Ne les utilisez jamais en production.

---

## 📡 Documentation des Endpoints

### Authentification

| Méthode | URL | Description | Auth |
|---------|-----|-------------|------|
| `POST` | `/api/auth/login` | Connexion — retourne JWT | Non |
| `GET` | `/api/auth/me` | Profil utilisateur connecté | JWT |

**Exemple de connexion :**
```bash
curl -X POST http://localhost/keyce-backend/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@keyce.cm","password":"Keyce2025!"}'
```

**Réponse :**
```json
{
  "success": true,
  "message": "Connexion réussie. Bienvenue Thomas !",
  "data": {
    "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
    "token_type": "Bearer",
    "expires_in": 86400,
    "user": {
      "id": 1,
      "nom": "Administrateur",
      "prenom": "Système",
      "email": "admin@keyce.cm",
      "role": "admin",
      "ref_id": null
    }
  }
}
```

**Utilisation du token dans les requêtes suivantes :**
```bash
curl http://localhost/keyce-backend/api/filieres \
  -H "Authorization: Bearer eyJ0eXAi..."
```

---

### Filières

| Méthode | URL | Description | Rôle |
|---------|-----|-------------|------|
| `GET` | `/api/filieres` | Liste paginée | Tous |
| `GET` | `/api/filieres?search=IABD&niveau=B2` | Filtrage | Tous |
| `POST` | `/api/filieres` | Créer | admin, responsable |
| `PUT` | `/api/filieres?id=1` | Modifier | admin, responsable |
| `DELETE` | `/api/filieres?id=1` | Supprimer | admin |

**Paramètres GET :** `search`, `niveau` (B1/B2/B3/M1/M2), `page`, `per_page`

---

### Classes

| Méthode | URL | Description |
|---------|-----|-------------|
| `GET` | `/api/classes?filiere_id=1` | Classes d'une filière |
| `POST` | `/api/classes` | Créer une classe |
| `PUT` | `/api/classes?id=1` | Modifier |
| `DELETE` | `/api/classes?id=1` | Supprimer |

---

### Matières

| Méthode | URL | Description |
|---------|-----|-------------|
| `GET` | `/api/matieres?filiere_id=1&type_cours=TP` | Filtrées |
| `POST` | `/api/matieres` | Créer |
| `PUT` | `/api/matieres?id=1` | Modifier |
| `DELETE` | `/api/matieres?id=1` | Supprimer |

---

### Enseignants

| Méthode | URL | Description |
|---------|-----|-------------|
| `GET` | `/api/enseignants?statut=Permanent` | Filtrés par statut |
| `POST` | `/api/enseignants` | Créer |
| `PUT` | `/api/enseignants?id=1` | Modifier |
| `DELETE` | `/api/enseignants?id=1` | Supprimer |

---

### Salles

| Méthode | URL | Description |
|---------|-----|-------------|
| `GET` | `/api/salles?type_salle=TP/Labo&disponible=1` | Salles disponibles |
| `POST` | `/api/salles` | Créer |
| `PUT` | `/api/salles?id=1` | Modifier |
| `DELETE` | `/api/salles?id=1` | Supprimer |

---

### Créneaux (⭐ Endpoint principal)

| Méthode | URL | Description |
|---------|-----|-------------|
| `GET` | `/api/creneaux` | Tous les créneaux |
| `GET` | `/api/creneaux?classe_id=1&semaine=2025-09-15` | Emploi du temps filtré |
| `GET` | `/api/creneaux?enseignant_id=1` | Planning d'un enseignant |
| `GET` | `/api/creneaux/verifier?...` | **Vérifier les conflits** |
| `POST` | `/api/creneaux` | Créer un créneau |
| `PUT` | `/api/creneaux?id=1` | Modifier un créneau |
| `PATCH` | `/api/creneaux?id=1` | Changer le statut |
| `DELETE` | `/api/creneaux?id=1` | Supprimer |

**Paramètres de filtrage GET /api/creneaux :**

| Paramètre | Type | Description |
|-----------|------|-------------|
| `classe_id` | int | Filtrer par classe |
| `enseignant_id` | int | Filtrer par enseignant |
| `salle_id` | int | Filtrer par salle |
| `filiere_id` | int | Filtrer par filière |
| `jour` | int | 1=Lun, 2=Mar, ..., 6=Sam |
| `statut` | string | planifie / confirme / annule |
| `semaine` | date | YYYY-MM-DD (dans l'intervalle semaine_debut–semaine_fin) |

**Vérification des conflits — GET /api/creneaux/verifier :**

```bash
curl "http://localhost/keyce-backend/api/creneaux/verifier?\
classe_id=1&enseignant_id=1&salle_id=5&jour=1&\
heure_debut=08:30&heure_fin=10:30&\
semaine_debut=2025-09-15&semaine_fin=2026-01-31" \
  -H "Authorization: Bearer <token>"
```

**Réponse sans conflit :**
```json
{
  "success": true,
  "message": "Aucun conflit détecté.",
  "data": {
    "has_conflict": false,
    "conflicts": [],
    "checked_at": "2025-09-15 10:30:00"
  }
}
```

**Réponse avec conflit :**
```json
{
  "success": true,
  "message": "2 conflit(s) détecté(s).",
  "data": {
    "has_conflict": true,
    "conflicts": [
      {
        "type": "enseignant",
        "message": "L'enseignant est déjà occupé : Machine Learning (08:30–10:30) pour AMPHI SANAGA – IABD B2 A",
        "creneau": { ... }
      }
    ]
  }
}
```

---

### Statistiques Dashboard

```bash
GET /api/stats
```

Retourne : totaux, créneaux de la semaine, conflits détectés, répartition par filière, top enseignants.

---

### Indisponibilités

| Méthode | URL | Description |
|---------|-----|-------------|
| `GET` | `/api/indisponibilites?enseignant_id=1` | Liste |
| `POST` | `/api/indisponibilites` | Déclarer une indisponibilité |
| `PATCH` | `/api/indisponibilites?id=1` | Valider / Refuser (admin) |
| `DELETE` | `/api/indisponibilites?id=1` | Supprimer |

---

## 📦 Structure des Données

### Format de réponse uniforme

```json
{
  "success": true | false,
  "message": "Description de l'action",
  "data": { ... } | [ ... ] | null,
  "errors": { "champ": "message" }  // uniquement en cas d'erreur 422
}
```

### Format paginé

```json
{
  "success": true,
  "data": [ ... ],
  "pagination": {
    "total": 45,
    "per_page": 10,
    "current_page": 2,
    "last_page": 5,
    "from": 11,
    "to": 20
  }
}
```

### Codes HTTP retournés

| Code | Signification |
|------|--------------|
| `200` | Succès |
| `201` | Ressource créée |
| `204` | Requête OPTIONS (CORS preflight) |
| `400` | Paramètre manquant |
| `401` | Non authentifié (token absent ou expiré) |
| `403` | Accès refusé (rôle insuffisant) |
| `404` | Ressource introuvable |
| `405` | Méthode HTTP non autorisée |
| `409` | Conflit (contrainte FK ou conflit horaire) |
| `422` | Erreur de validation des données |
| `500` | Erreur serveur |

---

## 🔧 Configuration `.env.php`

```php
<?php
return [
    'DB_HOST'    => 'localhost',
    'DB_NAME'    => 'keyce_emploi_temps',
    'DB_USER'    => 'root',
    'DB_PASS'    => 'votre_mot_de_passe',
    'JWT_SECRET' => 'votre_secret_aleatoire_32_chars',
    'APP_ENV'    => 'development',
    'FRONTEND_URL' => 'http://localhost:5173',
];
```

---

## ⚙️ Configuration côté React

Dans votre projet React, créez un fichier `.env` :

```env
VITE_API_BASE_URL=http://localhost/keyce-backend/api
```

Configuration Axios recommandée :
```javascript
// src/api/axiosInstance.js
import axios from 'axios';

const api = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL,
  timeout: 10000,
  headers: { 'Content-Type': 'application/json' },
});

api.interceptors.request.use((config) => {
  const token = localStorage.getItem('keyce_token');
  if (token) config.headers.Authorization = `Bearer ${token}`;
  return config;
});

export default api;
```

---

## 🗃️ Données de Démonstration Incluses

| Entité | Quantité |
|--------|----------|
| Filières | 5 (IABD, DEV, RSI, CYBSEC, DATA) |
| Classes | 10 (2 par filière) |
| Matières | 15 (CM, TD, TP, Projet mélangés) |
| Enseignants | 5 (3 permanents, 2 vacataires) |
| Salles | 6 (2 amphis, 2 TD, 2 labos) |
| Créneaux | ~45 (semaine complète Lun–Sam) |
| ⚠️ Conflits | 1 conflit intentionnel (samedi PM) |
| Utilisateurs | 10 (tous rôles) |

---

## 🐛 Dépannage Fréquent

**Erreur CORS :**
```
Access to XMLHttpRequest at 'http://...' from origin 'http://localhost:5173' has been blocked by CORS policy
```
→ Vérifiez que votre `FRONTEND_URL` dans `.env.php` correspond à votre URL React. Le backend accepte aussi `*` par défaut pour les tests.

**Erreur 404 sur toutes les routes :**  
→ `mod_rewrite` n'est pas activé, ou `AllowOverride All` manque dans votre config Apache.

**Erreur de connexion BDD :**  
→ Vérifiez vos credentials dans `.env.php`. Assurez-vous que la base `keyce_emploi_temps` existe.

**Token JWT expiré (401) :**  
→ Reconnectez-vous. Le token dure 24h par défaut.

---

## 📁 Structure du Projet

```
keyce-backend/
├── .htaccess                    ← Réécriture URL Apache
├── .env.php.example             ← Template configuration
├── index.php                    ← Routeur principal
├── config/
│   ├── database.php             ← Connexion PDO
│   └── cors.php                 ← En-têtes CORS
├── auth/
│   ├── login.php                ← POST /auth/login
│   └── me.php                   ← GET /auth/me
├── middleware/
│   └── auth.php                 ← Vérification JWT
├── utils/
│   ├── JWT.php                  ← Encode/Decode JWT HS256
│   └── Response.php             ← Helper réponses JSON
├── api/
│   ├── filieres/index.php
│   ├── classes/index.php
│   ├── matieres/index.php
│   ├── enseignants/index.php
│   ├── salles/index.php
│   ├── creneaux/index.php       ← ⭐ Conflits inclus
│   ├── users/index.php
│   ├── stats/index.php
│   └── indisponibilites/index.php
└── sql/
    └── keyce_emploi_temps.sql   ← Schéma + données démo
```

---

## 📝 Licence

Ressource pédagogique — Keyce Informatique © 2025-2026  
Usage exclusivement réservé aux étudiants dans le cadre du cours de Développement Frontend React.js.
