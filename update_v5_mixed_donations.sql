-- Re-Life v5 : autoriser un don unique contenant vêtements et livres
-- À importer après update_v4_donation_types.sql sur une base déjà installée.
USE relife;
SET NAMES utf8mb4;

ALTER TABLE dons
  MODIFY COLUMN type_don ENUM('vetements','livres','mixte') NOT NULL DEFAULT 'vetements';
