-- Re-Life v4 : distinguer les dons de vêtements et les dons de livres
-- À importer une seule fois sur une base existante.
USE relife;
SET NAMES utf8mb4;

ALTER TABLE dons
  ADD COLUMN IF NOT EXISTS type_don ENUM('vetements','livres','mixte') NOT NULL DEFAULT 'vetements' AFTER user_id;
