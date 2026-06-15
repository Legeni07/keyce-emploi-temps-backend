-- ============================================================
--  KEYCE INFORMATIQUE — Gestion Emplois du Temps
--  Script SQL complet : Schéma + Données de démonstration
--  Version : 1.0 | Auteur : BOGNI-DANCHI T.
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';

-- ------------------------------------------------------------
-- Base de données
-- ------------------------------------------------------------
CREATE DATABASE IF NOT EXISTS `keyce_emploi_temps`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `keyce_emploi_temps`;

-- ============================================================
-- TABLE : users
-- ============================================================
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id`         INT           NOT NULL AUTO_INCREMENT,
  `nom`        VARCHAR(50)   NOT NULL,
  `prenom`     VARCHAR(50)   NOT NULL,
  `email`      VARCHAR(100)  NOT NULL UNIQUE,
  `password`   VARCHAR(255)  NOT NULL,
  `role`       ENUM('admin','responsable','enseignant','etudiant') NOT NULL DEFAULT 'etudiant',
  `ref_id`     INT           NULL COMMENT 'ID dans la table enseignants ou classes selon le rôle',
  `actif`      TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_email` (`email`),
  INDEX `idx_role`  (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE : filieres
-- ============================================================
DROP TABLE IF EXISTS `filieres`;
CREATE TABLE `filieres` (
  `id`           INT           NOT NULL AUTO_INCREMENT,
  `code`         VARCHAR(10)   NOT NULL UNIQUE,
  `libelle`      VARCHAR(100)  NOT NULL,
  `niveau`       VARCHAR(20)   NOT NULL COMMENT 'B1, B2, B3, M1, M2',
  `responsable`  VARCHAR(100)  NULL,
  `couleur`      VARCHAR(7)    NOT NULL DEFAULT '#1565C0' COMMENT 'Couleur HEX pour la grille',
  `created_at`   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_code`   (`code`),
  INDEX `idx_niveau` (`niveau`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE : classes (promotions)
-- ============================================================
DROP TABLE IF EXISTS `classes`;
CREATE TABLE `classes` (
  `id`             INT          NOT NULL AUTO_INCREMENT,
  `nom`            VARCHAR(50)  NOT NULL,
  `filiere_id`     INT          NOT NULL,
  `effectif`       INT          NOT NULL DEFAULT 0,
  `annee_scolaire` VARCHAR(9)   NOT NULL DEFAULT '2024-2025',
  `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_filiere` (`filiere_id`),
  CONSTRAINT `fk_classes_filieres`
    FOREIGN KEY (`filiere_id`) REFERENCES `filieres` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE : matieres
-- ============================================================
DROP TABLE IF EXISTS `matieres`;
CREATE TABLE `matieres` (
  `id`          INT          NOT NULL AUTO_INCREMENT,
  `code`        VARCHAR(10)  NOT NULL UNIQUE,
  `intitule`    VARCHAR(100) NOT NULL,
  `volume_h`    INT          NOT NULL DEFAULT 30,
  `type_cours`  ENUM('CM','TD','TP','Projet') NOT NULL DEFAULT 'CM',
  `filiere_id`  INT          NOT NULL,
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_code`    (`code`),
  INDEX `idx_filiere` (`filiere_id`),
  CONSTRAINT `fk_matieres_filieres`
    FOREIGN KEY (`filiere_id`) REFERENCES `filieres` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE : enseignants
-- ============================================================
DROP TABLE IF EXISTS `enseignants`;
CREATE TABLE `enseignants` (
  `id`          INT          NOT NULL AUTO_INCREMENT,
  `matricule`   VARCHAR(10)  NOT NULL UNIQUE,
  `nom`         VARCHAR(50)  NOT NULL,
  `prenom`      VARCHAR(50)  NOT NULL,
  `email`       VARCHAR(100) NOT NULL UNIQUE,
  `specialite`  VARCHAR(100) NULL,
  `statut`      ENUM('Permanent','Vacataire') NOT NULL DEFAULT 'Permanent',
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_matricule` (`matricule`),
  INDEX `idx_statut`    (`statut`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE : salles
-- ============================================================
DROP TABLE IF EXISTS `salles`;
CREATE TABLE `salles` (
  `id`           INT          NOT NULL AUTO_INCREMENT,
  `code`         VARCHAR(15)  NOT NULL UNIQUE,
  `type_salle`   ENUM('Amphithéâtre','TD','TP/Labo','Projet') NOT NULL,
  `capacite`     INT          NOT NULL DEFAULT 30,
  `equipements`  TEXT         NULL,
  `disponible`   TINYINT(1)  NOT NULL DEFAULT 1,
  `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_code`       (`code`),
  INDEX `idx_type_salle` (`type_salle`),
  INDEX `idx_disponible` (`disponible`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE : indisponibilites
-- ============================================================
DROP TABLE IF EXISTS `indisponibilites`;
CREATE TABLE `indisponibilites` (
  `id`             INT          NOT NULL AUTO_INCREMENT,
  `enseignant_id`  INT          NOT NULL,
  `date_debut`     DATE         NOT NULL,
  `date_fin`       DATE         NOT NULL,
  `motif`          ENUM('Congé','Mission','Maladie','Autre') NOT NULL DEFAULT 'Autre',
  `description`    TEXT         NULL,
  `statut`         ENUM('en_attente','validee','refusee') NOT NULL DEFAULT 'en_attente',
  `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_enseignant` (`enseignant_id`),
  INDEX `idx_dates`      (`date_debut`, `date_fin`),
  CONSTRAINT `fk_indispo_enseignants`
    FOREIGN KEY (`enseignant_id`) REFERENCES `enseignants` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE : creneaux (emploi du temps)
-- ============================================================
DROP TABLE IF EXISTS `creneaux`;
CREATE TABLE `creneaux` (
  `id`              INT          NOT NULL AUTO_INCREMENT,
  `classe_id`       INT          NOT NULL,
  `matiere_id`      INT          NOT NULL,
  `enseignant_id`   INT          NOT NULL,
  `salle_id`        INT          NOT NULL,
  `jour`            TINYINT      NOT NULL COMMENT '1=Lun, 2=Mar, 3=Mer, 4=Jeu, 5=Ven, 6=Sam',
  `heure_debut`     TIME         NOT NULL,
  `heure_fin`       TIME         NOT NULL,
  `semaine_debut`   DATE         NOT NULL,
  `semaine_fin`     DATE         NOT NULL,
  `recurrent`       TINYINT(1)  NOT NULL DEFAULT 1,
  `statut`          ENUM('planifie','confirme','annule') NOT NULL DEFAULT 'planifie',
  `created_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_classe`      (`classe_id`),
  INDEX `idx_matiere`     (`matiere_id`),
  INDEX `idx_enseignant`  (`enseignant_id`),
  INDEX `idx_salle`       (`salle_id`),
  INDEX `idx_jour_heure`  (`jour`, `heure_debut`, `heure_fin`),
  INDEX `idx_semaines`    (`semaine_debut`, `semaine_fin`),
  CONSTRAINT `fk_creneaux_classes`
    FOREIGN KEY (`classe_id`)     REFERENCES `classes`     (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_creneaux_matieres`
    FOREIGN KEY (`matiere_id`)    REFERENCES `matieres`    (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_creneaux_enseignants`
    FOREIGN KEY (`enseignant_id`) REFERENCES `enseignants` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_creneaux_salles`
    FOREIGN KEY (`salle_id`)      REFERENCES `salles`      (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- DONNÉES DE DÉMONSTRATION
-- ============================================================

-- ── USERS (mots de passe = password_hash('Keyce2025!', PASSWORD_BCRYPT))
-- Pour tests : admin@keyce.cm / Keyce2025! | enseignant1@keyce.cm / Keyce2025! | etudiant1@keyce.cm / Keyce2025!
INSERT INTO `users` (`nom`, `prenom`, `email`, `password`, `role`, `ref_id`, `actif`) VALUES
('Administrateur',  'Système',   'admin@keyce.cm',         '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin',        NULL, 1),
('Responsable',     'Pédago',    'responsable@keyce.cm',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'responsable',  NULL, 1),
('Bogni-Danchi',    'Thomas',    'enseignant1@keyce.cm',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'enseignant',   1,    1),
('Kamga',           'Pierre',    'enseignant2@keyce.cm',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'enseignant',   2,    1),
('Fouda',           'Marie',     'enseignant3@keyce.cm',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'enseignant',   3,    1),
('Manga',           'Jean',      'enseignant4@keyce.cm',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'enseignant',   4,    1),
('Nguema',          'Alice',     'enseignant5@keyce.cm',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'enseignant',   5,    1),
('Etudiant',        'IABD B2',   'etudiant1@keyce.cm',     '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'etudiant',     1,    1),
('Etudiant',        'DEV B2',    'etudiant2@keyce.cm',     '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'etudiant',     3,    1),
('Etudiant',        'RSI M1',    'etudiant3@keyce.cm',     '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'etudiant',     5,    1);

-- NOTE: Le hash ci-dessus correspond au mot de passe 'password' (hash Laravel par défaut)
-- En production, régénérez avec : password_hash('VotreMotDePasse', PASSWORD_BCRYPT)

-- ── FILIÈRES (3 filières, couleurs distinctes)
INSERT INTO `filieres` (`code`, `libelle`, `niveau`, `responsable`, `couleur`) VALUES
('IABD',  'Intelligence Artificielle & Big Data',  'B2', 'Dr. Thomas Bogni-Danchi',  '#1565C0'),
('DEV',   'Développement Logiciel & Web',          'B2', 'Dr. Pierre Kamga',          '#2E7D32'),
('RSI',   'Réseaux & Systèmes Informatiques',      'M1', 'Dr. Marie Fouda',           '#6A1B9A'),
('CYBSEC','Cybersécurité & Audit SI',              'B3', 'Ing. Jean Manga',           '#BF360C'),
('DATA',  'Data Engineering & Cloud',              'M1', 'Dr. Alice Nguema',          '#00695C');

-- ── CLASSES (2 classes par filière = 10 classes)
INSERT INTO `classes` (`nom`, `filiere_id`, `effectif`, `annee_scolaire`) VALUES
-- IABD B2
('AMPHI SANAGA – IABD B2 A',  1, 45, '2025-2026'),
('AMPHI WOURI – IABD B2 B',   1, 42, '2025-2026'),
-- DEV B2
('DEV B2 GROUPE 1',           2, 38, '2025-2026'),
('DEV B2 GROUPE 2',           2, 36, '2025-2026'),
-- RSI M1
('RSI M1 PRINCIPAL',          3, 28, '2025-2026'),
('RSI M1 ALTERNANCE',         3, 22, '2025-2026'),
-- CYBSEC B3
('CYBSEC B3 A',               4, 32, '2025-2026'),
('CYBSEC B3 B',               4, 30, '2025-2026'),
-- DATA M1
('DATA M1 INITIAL',           5, 25, '2025-2026'),
('DATA M1 CONTINU',           5, 18, '2025-2026');

-- ── MATIÈRES (15 matières réparties sur les filières)
INSERT INTO `matieres` (`code`, `intitule`, `volume_h`, `type_cours`, `filiere_id`) VALUES
-- IABD B2
('IABD-301', 'Machine Learning Fondamentaux',          40, 'CM',     1),
('IABD-302', 'Deep Learning & Réseaux de Neurones',   32, 'TP',     1),
('IABD-303', 'Big Data avec Spark & Hadoop',          28, 'TP',     1),
('IABD-304', 'Statistiques pour la Data Science',     36, 'TD',     1),
-- DEV B2
('DEV-301',  'Développement Web avec React.js',        40, 'TP',     2),
('DEV-302',  'API REST & Node.js',                    32, 'TP',     2),
('DEV-303',  'Base de Données Avancées',              28, 'TD',     2),
('DEV-304',  'Architecture Logicielle & Design Patterns', 30, 'CM', 2),
-- RSI M1
('RSI-501',  'Administration Réseaux Avancée',        36, 'TP',     3),
('RSI-502',  'Virtualisation & Cloud Computing',       32, 'TP',     3),
('RSI-503',  'Sécurité des Infrastructures',          28, 'CM',     3),
-- CYBSEC B3
('CYB-401',  'Ethical Hacking & Pentest',             40, 'TP',     4),
('CYB-402',  'Cryptographie Appliquée',               30, 'CM',     4),
-- DATA M1
('DATA-501', 'Data Pipeline & ETL',                   36, 'TP',     5),
('DATA-502', 'Visualisation de Données',              28, 'Projet', 5);

-- ── ENSEIGNANTS (5 enseignants)
INSERT INTO `enseignants` (`matricule`, `nom`, `prenom`, `email`, `specialite`, `statut`) VALUES
('ENS-001', 'Bogni-Danchi', 'Thomas',  'tbogni@keyce.cm',    'Intelligence Artificielle, PHP, Symfony', 'Permanent'),
('ENS-002', 'Kamga',        'Pierre',  'pkamga@keyce.cm',    'Réseaux, Cybersécurité, Linux',           'Permanent'),
('ENS-003', 'Fouda',        'Marie',   'mfouda@keyce.cm',    'Développement Web, React, Node.js',       'Permanent'),
('ENS-004', 'Manga',        'Jean',    'jmanga@keyce.cm',    'Big Data, Spark, Machine Learning',       'Vacataire'),
('ENS-005', 'Nguema',       'Alice',   'anguema@keyce.cm',   'Cloud Computing, DevOps, Kubernetes',     'Vacataire');

-- ── SALLES (6 salles : 2 amphis, 2 TD, 2 labos)
INSERT INTO `salles` (`code`, `type_salle`, `capacite`, `equipements`, `disponible`) VALUES
('AMPHI-SANAGA',  'Amphithéâtre', 120, 'Vidéoprojecteur HD, Sono, Tableau blanc, WiFi, Climatisation',              1),
('AMPHI-WOURI',   'Amphithéâtre', 80,  'Vidéoprojecteur 4K, Micro HF, Tableau interactif, WiFi, Climatisation',    1),
('SALLE-TD-A01',  'TD',           40,  'Tableau blanc x2, Vidéoprojecteur, WiFi, Tables mobiles',                   1),
('SALLE-TD-B02',  'TD',           35,  'Tableau blanc x2, Écran projection, WiFi',                                  1),
('LABO-INFO-1',   'TP/Labo',      30,  'PCs HP Core i7 x30, Écran double, VS Code, MySQL, Git, WiFi dédié',         1),
('LABO-INFO-2',   'TP/Labo',      25,  'PCs HP Core i5 x25, Kali Linux dual-boot, Wireshark, VMware, WiFi dédié',   0);

-- ── INDISPONIBILITÉS (quelques exemples)
INSERT INTO `indisponibilites` (`enseignant_id`, `date_debut`, `date_fin`, `motif`, `description`, `statut`) VALUES
(2, '2025-10-20', '2025-10-22', 'Mission',  'Conférence internationale sur la cybersécurité à Abidjan', 'validee'),
(4, '2025-11-03', '2025-11-07', 'Maladie',  NULL,                                                        'en_attente'),
(5, '2025-12-15', '2025-12-20', 'Congé',    'Congé annuel de fin d\'année',                              'validee');

-- ── CRÉNEAUX (emploi du temps complet — semaine du 2025-09-15 au 2025-09-20)
-- Semaine de référence : 15 sept 2025 (lundi)
INSERT INTO `creneaux`
  (`classe_id`, `matiere_id`, `enseignant_id`, `salle_id`, `jour`, `heure_debut`, `heure_fin`,
   `semaine_debut`, `semaine_fin`, `recurrent`, `statut`)
VALUES
-- ── LUNDI (jour=1)
-- 08h30–10h30 | IABD B2 A | ML Fondamentaux | Bogni-Danchi | AMPHI-SANAGA
(1,  1,  1, 1, 1, '08:30:00', '10:30:00', '2025-09-15', '2026-01-31', 1, 'confirme'),
-- 08h30–10h30 | DEV B2 G1 | React.js | Fouda | LABO-INFO-1
(3,  5,  3, 5, 1, '08:30:00', '10:30:00', '2025-09-15', '2026-01-31', 1, 'confirme'),
-- 10h45–12h45 | IABD B2 B | Statistiques DS | Manga | AMPHI-WOURI
(2,  4,  4, 2, 1, '10:45:00', '12:45:00', '2025-09-15', '2026-01-31', 1, 'confirme'),
-- 10h45–12h45 | DEV B2 G2 | BDD Avancées | Bogni-Danchi | SALLE-TD-A01
(4,  7,  1, 3, 1, '10:45:00', '12:45:00', '2025-09-15', '2026-01-31', 1, 'confirme'),
-- 14h00–16h00 | RSI M1 | Admin Réseaux | Kamga | LABO-INFO-2  [⚠ salle en travaux — simuler conflit possible]
(5,  9,  2, 6, 1, '14:00:00', '16:00:00', '2025-09-15', '2026-01-31', 1, 'planifie'),
-- 14h00–16h00 | DATA M1 | Data Pipeline | Nguema | LABO-INFO-1
(9, 14,  5, 5, 1, '14:00:00', '16:00:00', '2025-09-15', '2026-01-31', 1, 'confirme'),
-- 16h15–18h15 | CYBSEC B3 A | Ethical Hacking | Kamga | LABO-INFO-1
(7, 12,  2, 5, 1, '16:15:00', '18:15:00', '2025-09-15', '2026-01-31', 1, 'confirme'),
-- 18h30–20h30 | DEV B2 G1 | API REST Node.js | Fouda | SALLE-TD-B02
(3,  6,  3, 4, 1, '18:30:00', '20:30:00', '2025-09-15', '2026-01-31', 1, 'confirme'),

-- ── MARDI (jour=2)
-- 08h30–10h30 | IABD B2 A | Deep Learning | Bogni-Danchi | LABO-INFO-1
(1,  2,  1, 5, 2, '08:30:00', '10:30:00', '2025-09-15', '2026-01-31', 1, 'confirme'),
-- 08h30–10h30 | RSI M1 | Cloud Computing | Nguema | SALLE-TD-A01
(5, 10,  5, 3, 2, '08:30:00', '10:30:00', '2025-09-15', '2026-01-31', 1, 'confirme'),
-- 10h45–12h45 | DEV B2 G1 | Architecture Logicielle | Bogni-Danchi | AMPHI-SANAGA
(3,  8,  1, 1, 2, '10:45:00', '12:45:00', '2025-09-15', '2026-01-31', 1, 'confirme'),
-- 10h45–12h45 | CYBSEC B3 B | Cryptographie | Kamga | SALLE-TD-B02
(8, 13,  2, 4, 2, '10:45:00', '12:45:00', '2025-09-15', '2026-01-31', 1, 'confirme'),
-- 14h00–16h00 | IABD B2 B | Big Data Spark | Manga | LABO-INFO-1
(2,  3,  4, 5, 2, '14:00:00', '16:00:00', '2025-09-15', '2026-01-31', 1, 'confirme'),
-- 14h00–16h00 | DATA M1 | Visualisation | Nguema | SALLE-TD-A01
(9, 15,  5, 3, 2, '14:00:00', '16:00:00', '2025-09-15', '2026-01-31', 1, 'confirme'),
-- 16h15–18h15 | DEV B2 G2 | React.js | Fouda | LABO-INFO-1
(4,  5,  3, 5, 2, '16:15:00', '18:15:00', '2025-09-15', '2026-01-31', 1, 'confirme'),
-- 18h30–20h30 | RSI M1 Alt | Sécurité Infra | Kamga | SALLE-TD-B02
(6, 11,  2, 4, 2, '18:30:00', '20:30:00', '2025-09-15', '2026-01-31', 1, 'planifie'),

-- ── MERCREDI (jour=3)
-- 08h30–10h30 | IABD B2 A | ML Fondamentaux | Bogni-Danchi | AMPHI-SANAGA
(1,  1,  1, 1, 3, '08:30:00', '10:30:00', '2025-09-15', '2026-01-31', 1, 'confirme'),
-- 08h30–10h30 | DEV B2 G2 | API REST | Fouda | LABO-INFO-1
(4,  6,  3, 5, 3, '08:30:00', '10:30:00', '2025-09-15', '2026-01-31', 1, 'confirme'),
-- 10h45–12h45 | CYBSEC B3 A | Ethical Hacking | Kamga | LABO-INFO-2
(7, 12,  2, 6, 3, '10:45:00', '12:45:00', '2025-09-15', '2026-01-31', 1, 'annule'),
-- 10h45–12h45 | DATA M1 | Data Pipeline | Nguema | LABO-INFO-1
(9, 14,  5, 5, 3, '10:45:00', '12:45:00', '2025-09-15', '2026-01-31', 1, 'confirme'),
-- 14h00–16h00 | IABD B2 B | Deep Learning | Bogni-Danchi | LABO-INFO-1
(2,  2,  1, 5, 3, '14:00:00', '16:00:00', '2025-09-15', '2026-01-31', 1, 'confirme'),
-- 16h15–18h15 | RSI M1 | Admin Réseaux | Kamga | SALLE-TD-A01
(5,  9,  2, 3, 3, '16:15:00', '18:15:00', '2025-09-15', '2026-01-31', 1, 'confirme'),
-- 18h30–20h30 | DEV B2 G1 | BDD Avancées | Bogni-Danchi | SALLE-TD-B02
(3,  7,  1, 4, 3, '18:30:00', '20:30:00', '2025-09-15', '2026-01-31', 1, 'confirme'),

-- ── JEUDI (jour=4)
-- 08h30–10h30 | DEV B2 G1 | React.js TP | Fouda | LABO-INFO-1
(3,  5,  3, 5, 4, '08:30:00', '10:30:00', '2025-09-15', '2026-01-31', 1, 'confirme'),
-- 08h30–10h30 | CYBSEC B3 B | Cryptographie | Kamga | AMPHI-WOURI
(8, 13,  2, 2, 4, '08:30:00', '10:30:00', '2025-09-15', '2026-01-31', 1, 'confirme'),
-- 10h45–12h45 | IABD B2 A | Big Data | Manga | LABO-INFO-1
(1,  3,  4, 5, 4, '10:45:00', '12:45:00', '2025-09-15', '2026-01-31', 1, 'confirme'),
-- 10h45–12h45 | DATA M1 Continu | Visualisation | Nguema | SALLE-TD-A01
(10, 15, 5, 3, 4, '10:45:00', '12:45:00', '2025-09-15', '2026-01-31', 1, 'confirme'),
-- 14h00–16h00 | RSI M1 Alt | Cloud | Nguema | SALLE-TD-B02
(6, 10,  5, 4, 4, '14:00:00', '16:00:00', '2025-09-15', '2026-01-31', 1, 'confirme'),
-- 14h00–16h00 | DEV B2 G2 | Architecture | Bogni-Danchi | AMPHI-SANAGA
(4,  8,  1, 1, 4, '14:00:00', '16:00:00', '2025-09-15', '2026-01-31', 1, 'confirme'),
-- 16h15–18h15 | IABD B2 B | Statistiques | Manga | SALLE-TD-A01
(2,  4,  4, 3, 4, '16:15:00', '18:15:00', '2025-09-15', '2026-01-31', 1, 'confirme'),
-- 18h30–20h30 | CYBSEC B3 A | Pentest | Kamga | LABO-INFO-2
(7, 12,  2, 6, 4, '18:30:00', '20:30:00', '2025-09-15', '2026-01-31', 1, 'confirme'),

-- ── VENDREDI (jour=5)
-- 08h30–10h30 | IABD B2 A | ML Fondamentaux | Bogni-Danchi | AMPHI-SANAGA
(1,  1,  1, 1, 5, '08:30:00', '10:30:00', '2025-09-15', '2026-01-31', 1, 'confirme'),
-- 08h30–10h30 | DEV B2 G2 | React.js | Fouda | LABO-INFO-1
(4,  5,  3, 5, 5, '08:30:00', '10:30:00', '2025-09-15', '2026-01-31', 1, 'confirme'),
-- 10h45–12h45 | DATA M1 | Data Pipeline | Nguema | LABO-INFO-1
(9, 14,  5, 5, 5, '10:45:00', '12:45:00', '2025-09-15', '2026-01-31', 1, 'confirme'),
-- 10h45–12h45 | RSI M1 | Sécurité Infra | Kamga | SALLE-TD-B02
(5, 11,  2, 4, 5, '10:45:00', '12:45:00', '2025-09-15', '2026-01-31', 1, 'confirme'),
-- 14h00–16h00 | IABD B2 B | Deep Learning | Bogni-Danchi | LABO-INFO-1
(2,  2,  1, 5, 5, '14:00:00', '16:00:00', '2025-09-15', '2026-01-31', 1, 'confirme'),
-- 14h00–16h00 | CYBSEC B3 A | Ethical Hacking | Kamga | LABO-INFO-2
(7, 12,  2, 6, 5, '14:00:00', '16:00:00', '2025-09-15', '2026-01-31', 1, 'confirme'),
-- 16h15–18h15 | DEV B2 G1 | Projet React | Fouda | SALLE-TD-A01
(3,  5,  3, 3, 5, '16:15:00', '18:15:00', '2025-09-15', '2026-01-31', 0, 'planifie'),
-- 18h30–20h30 | DATA M1 | Visualisation | Nguema | SALLE-TD-B02
(9, 15,  5, 4, 5, '18:30:00', '20:30:00', '2025-09-15', '2026-01-31', 1, 'confirme'),

-- ── SAMEDI (jour=6) — Cours du soir / Alternance
-- 08h30–12h30 | RSI M1 Alt | Admin Réseaux | Kamga | LABO-INFO-2
(6,  9,  2, 6, 6, '08:30:00', '12:30:00', '2025-09-15', '2026-01-31', 1, 'confirme'),
-- 08h30–12h30 | DATA M1 Continu | Data Pipeline | Nguema | LABO-INFO-1
(10, 14, 5, 5, 6, '08:30:00', '12:30:00', '2025-09-15', '2026-01-31', 1, 'confirme'),
-- 13h30–17h30 | RSI M1 Alt | Cloud Computing | Nguema | SALLE-TD-A01
(6, 10,  5, 3, 6, '13:30:00', '17:30:00', '2025-09-15', '2026-01-31', 1, 'confirme'),
-- 13h30–17h30 | DATA M1 Continu | Visualisation | Nguema | SALLE-TD-B02 ← ⚠ CONFLIT : Nguema déjà dans SALLE-TD-A01
-- Ce créneau est volontairement en conflit pour les tests étudiants
(10, 15, 5, 4, 6, '13:30:00', '17:30:00', '2025-09-15', '2026-01-31', 1, 'planifie');

-- ============================================================
-- FIN DU SCRIPT
-- ============================================================
