-- Données de démonstration facultatives pour remplir les deux vitrines de l'accueil.
-- Les clauses NOT EXISTS permettent de relancer ce script sans créer de doublons.
USE relife;
SET NAMES utf8mb4;

INSERT INTO produits (nom, description, categorie, taille, etat, prix, stock)
SELECT 'Chemise en lin verte', 'Chemise légère en lin, idéale pour le climat mauricien.', 'Homme', 'L', 'Très bon état', 350.00, 2
WHERE NOT EXISTS (SELECT 1 FROM produits WHERE nom = 'Chemise en lin verte');

INSERT INTO produits (nom, description, categorie, taille, etat, prix, stock)
SELECT 'Pull en coton écru', 'Pull doux en coton écru, coupe confortable.', 'Femme', 'M', 'Comme neuf', 500.00, 1
WHERE NOT EXISTS (SELECT 1 FROM produits WHERE nom = 'Pull en coton écru');

INSERT INTO produits (nom, description, categorie, taille, auteur, genre_livre, isbn, editeur, annee_publication, langue_livre, nombre_pages, etat, prix, stock)
SELECT 'L’Étranger', 'Un roman majeur sur l’absurde et la condition humaine.', 'Livres', 'Poche', 'Albert Camus', 'Roman', '9782070360024', 'Gallimard', 1972, 'Français', 192, 'Bon état', 140.00, 2
WHERE NOT EXISTS (SELECT 1 FROM produits WHERE isbn = '9782070360024');

INSERT INTO produits (nom, description, categorie, taille, auteur, genre_livre, isbn, editeur, annee_publication, langue_livre, nombre_pages, etat, prix, stock)
SELECT 'Le Tour du monde en 80 jours', 'L’aventure de Phileas Fogg autour du monde.', 'Livres', 'Poche', 'Jules Verne', 'Aventure', '9782253006329', 'Le Livre de Poche', 2001, 'Français', 384, 'Très bon état', 160.00, 1
WHERE NOT EXISTS (SELECT 1 FROM produits WHERE isbn = '9782253006329');

INSERT INTO produits (nom, description, categorie, taille, auteur, genre_livre, isbn, editeur, annee_publication, langue_livre, nombre_pages, etat, prix, stock)
SELECT 'Matilda', 'L’histoire drôle et tendre d’une jeune lectrice extraordinaire.', 'Livres', 'Broché', 'Roald Dahl', 'Jeunesse', '9782070601585', 'Gallimard Jeunesse', 2016, 'Français', 272, 'Très bon état', 180.00, 2
WHERE NOT EXISTS (SELECT 1 FROM produits WHERE isbn = '9782070601585');

INSERT INTO produits (nom, description, categorie, taille, auteur, genre_livre, isbn, editeur, annee_publication, langue_livre, nombre_pages, etat, prix, stock)
SELECT 'Une brève histoire du temps', 'Une introduction accessible aux grandes questions de l’univers.', 'Livres', 'Poche', 'Stephen Hawking', 'Science', '9782290346655', 'J’ai lu', 2005, 'Français', 256, 'Bon état', 200.00, 1
WHERE NOT EXISTS (SELECT 1 FROM produits WHERE isbn = '9782290346655');

INSERT INTO produits (nom, description, categorie, taille, auteur, genre_livre, isbn, editeur, annee_publication, langue_livre, nombre_pages, etat, prix, stock)
SELECT 'Fables choisies', 'Une sélection de fables à lire et à transmettre.', 'Livres', 'Relié', 'Jean de La Fontaine', 'Classique', '9782070409136', 'Gallimard', 1999, 'Français', 224, 'Comme neuf', 170.00, 1
WHERE NOT EXISTS (SELECT 1 FROM produits WHERE isbn = '9782070409136');
