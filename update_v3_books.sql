-- Re-Life v3 : informations spécifiques aux livres
-- Ce script peut être importé sur une base existante.
USE relife;
SET NAMES utf8mb4;

ALTER TABLE produits
  ADD COLUMN IF NOT EXISTS auteur VARCHAR(150) NULL AFTER taille,
  ADD COLUMN IF NOT EXISTS genre_livre VARCHAR(80) NULL AFTER auteur,
  ADD COLUMN IF NOT EXISTS isbn VARCHAR(20) NULL AFTER genre_livre,
  ADD COLUMN IF NOT EXISTS editeur VARCHAR(150) NULL AFTER isbn,
  ADD COLUMN IF NOT EXISTS annee_publication SMALLINT UNSIGNED NULL AFTER editeur,
  ADD COLUMN IF NOT EXISTS langue_livre VARCHAR(50) NULL AFTER annee_publication,
  ADD COLUMN IF NOT EXISTS nombre_pages SMALLINT UNSIGNED NULL AFTER langue_livre;
