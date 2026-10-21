-- ============================================================
-- ReWear : base de données de la plateforme de don et réutilisation
-- Utilisation : importer ce fichier dans phpMyAdmin (onglet Importer)
-- ============================================================
CREATE DATABASE IF NOT EXISTS rewear CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE rewear;
SET NAMES utf8mb4;

-- Utilisateurs : 3 rôles
--   utilisateur = donateur ET acheteur (même compte)
--   livreur     = collecte les dons et livre les commandes
--   admin       = gère les utilisateurs, met les pièces en ligne, suit tout
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nom VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  mot_de_passe VARCHAR(255) NOT NULL,          -- haché avec password_hash()
  telephone VARCHAR(30),
  adresse VARCHAR(255),
  session_version INT UNSIGNED NOT NULL DEFAULT 1,
  role ENUM('utilisateur','livreur','admin') NOT NULL DEFAULT 'utilisateur',
  actif TINYINT(1) NOT NULL DEFAULT 1,         -- 0 = compte désactivé par l'admin
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Produits mis en ligne par l'admin (vêtements issus des dons)
CREATE TABLE produits (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nom VARCHAR(150) NOT NULL,
  description TEXT,
  categorie ENUM('Homme','Femme','Enfant','Chaussures','Accessoires','Livres') NOT NULL,
  taille VARCHAR(20),
  auteur VARCHAR(150) NULL,
  genre_livre VARCHAR(80) NULL,
  isbn VARCHAR(20) NULL,
  editeur VARCHAR(150) NULL,
  annee_publication SMALLINT UNSIGNED NULL,
  langue_livre VARCHAR(50) NULL,
  nombre_pages SMALLINT UNSIGNED NULL,
  etat ENUM('Comme neuf','Très bon état','Bon état') NOT NULL DEFAULT 'Bon état',
  prix DECIMAL(10,2) NOT NULL,                 -- prix très réduit (en Rs)
  stock INT NOT NULL DEFAULT 1,
  image VARCHAR(255),
  actif TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Dons déposés par les utilisateurs
CREATE TABLE dons (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  type_don ENUM('vetements','livres','mixte') NOT NULL DEFAULT 'vetements',
  description TEXT NOT NULL,                   -- ce que la personne donne
  quantite INT NOT NULL DEFAULT 1,
  adresse_collecte VARCHAR(255) NOT NULL,
  date_souhaitee DATE,
  statut ENUM('en_attente','assigne','collecte','recu','refuse') NOT NULL DEFAULT 'en_attente',
  livreur_id INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (livreur_id) REFERENCES users(id)
);

-- Commandes (achats)
CREATE TABLE commandes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  total DECIMAL(10,2) NOT NULL,
  adresse_livraison VARCHAR(255) NOT NULL,
  mode_paiement ENUM('livraison','carte') NOT NULL DEFAULT 'livraison',
  statut ENUM('en_attente','confirmee','en_livraison','livree','annulee') NOT NULL DEFAULT 'en_attente',
  livreur_id INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (livreur_id) REFERENCES users(id)
);

CREATE TABLE commande_lignes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  commande_id INT NOT NULL,
  produit_id INT NOT NULL,
  quantite INT NOT NULL,
  prix_unitaire DECIMAL(10,2) NOT NULL,        -- prix au moment de l'achat
  FOREIGN KEY (commande_id) REFERENCES commandes(id) ON DELETE CASCADE,
  FOREIGN KEY (produit_id) REFERENCES produits(id)
);

-- Compte admin par défaut : admin@rewear.mu / Admin123!  (à changer après la première connexion)
INSERT INTO users (nom, email, mot_de_passe, role) VALUES
('Administrateur', 'admin@rewear.mu', '$2y$10$vwIJLJDAcXtYqC/S9bv24uvbWHMrqLrJdyHDra6pHVKmX.FRpg7Ue', 'admin');

-- Quelques produits d'exemple
INSERT INTO produits (nom, description, categorie, taille, etat, prix, stock) VALUES
('Jean droit bleu', 'Jean en coton, très bien conservé.', 'Homme', 'M', 'Très bon état', 250.00, 3),
('Robe d''été fleurie', 'Robe légère, idéale pour la chaleur.', 'Femme', 'S', 'Comme neuf', 300.00, 2),
('T-shirt enfant coloré', 'T-shirt doux pour enfant de 6 ans.', 'Enfant', '6 ans', 'Bon état', 80.00, 5),
('Baskets blanches', 'Baskets confortables, peu portées.', 'Chaussures', '40', 'Très bon état', 350.00, 1),
('Chemise en lin verte', 'Chemise légère en lin, idéale pour le climat mauricien.', 'Homme', 'L', 'Très bon état', 350.00, 2),
('Pull en coton écru', 'Pull doux en coton écru, coupe confortable.', 'Femme', 'M', 'Comme neuf', 500.00, 1);

-- Exemple de livre avec ses informations bibliographiques
INSERT INTO produits (nom, description, categorie, taille, auteur, genre_livre, isbn, editeur, annee_publication, langue_livre, nombre_pages, etat, prix, stock) VALUES
('Le Petit Prince', 'Un classique poétique sur l’amitié, le voyage et le regard porté sur le monde.', 'Livres', 'Poche', 'Antoine de Saint-Exupéry', 'Conte', '9782070612758', 'Gallimard', 2007, 'Français', 120, 'Très bon état', 120.00, 2);

INSERT INTO produits (nom, description, categorie, taille, auteur, genre_livre, isbn, editeur, annee_publication, langue_livre, nombre_pages, etat, prix, stock) VALUES
('L’Étranger', 'Un roman majeur sur l’absurde et la condition humaine.', 'Livres', 'Poche', 'Albert Camus', 'Roman', '9782070360024', 'Gallimard', 1972, 'Français', 192, 'Bon état', 140.00, 2),
('Le Tour du monde en 80 jours', 'L’aventure de Phileas Fogg autour du monde.', 'Livres', 'Poche', 'Jules Verne', 'Aventure', '9782253006329', 'Le Livre de Poche', 2001, 'Français', 384, 'Très bon état', 160.00, 1),
('Matilda', 'L’histoire drôle et tendre d’une jeune lectrice extraordinaire.', 'Livres', 'Broché', 'Roald Dahl', 'Jeunesse', '9782070601585', 'Gallimard Jeunesse', 2016, 'Français', 272, 'Très bon état', 180.00, 2),
('Une brève histoire du temps', 'Une introduction accessible aux grandes questions de l’univers.', 'Livres', 'Poche', 'Stephen Hawking', 'Science', '9782290346655', 'J’ai lu', 2005, 'Français', 256, 'Bon état', 200.00, 1),
('Fables choisies', 'Une sélection de fables à lire et à transmettre.', 'Livres', 'Relié', 'Jean de La Fontaine', 'Classique', '9782070409136', 'Gallimard', 1999, 'Français', 224, 'Comme neuf', 170.00, 1);

-- Mot de passe oublié : jetons de réinitialisation (valables 1 heure, usage unique)
CREATE TABLE password_resets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  token_hash CHAR(64) NOT NULL,        -- empreinte du jeton, jamais le jeton lui-même
  expires_at DATETIME NOT NULL,
  used TINYINT(1) NOT NULL DEFAULT 0,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS auth_throttles (
    bucket CHAR(64) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
    attempts INT UNSIGNED NOT NULL DEFAULT 0,
    expires_at BIGINT UNSIGNED NOT NULL,
    INDEX idx_auth_expiry (expires_at)
) ENGINE=InnoDB;
