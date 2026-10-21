# ReWear — plateforme de don et de réutilisation de vêtements

Site PHP / MySQL : on y donne des vêtements et des livres, et on y achète des pièces de seconde main à prix très réduits.

## Rôles

| Rôle | Ce qu'il peut faire |
|---|---|
| **Utilisateur** (donateur + acheteur) | Créer un compte, faire un don, acheter (panier, commande), suivre ses dons et commandes |
| **Administrateur** | Mettre les pièces en ligne, gérer produits / commandes / dons / comptes, assigner les livreurs |
| **Livreur** | Voir ses missions : collecter les dons, livrer les commandes |

Un compte est **obligatoire** avant de soumettre un don ou d'acheter.

## Installation (XAMPP ou WAMP)

1. Copier le dossier `wear-share` dans `htdocs` (XAMPP) ou `www` (WAMP).
2. Démarrer Apache et MySQL.
3. Ouvrir phpMyAdmin → **Importer** → choisir `database.sql`.
4. Vérifier les identifiants MySQL dans `includes/config.php` (par défaut : `root`, mot de passe vide).
5. Ouvrir `http://localhost/wear-share/`.

**Compte administrateur par défaut :** `admin@rewear.mu` / `Admin123!` → à changer dès la première connexion
(pour l'instant : créer un nouvel admin dans *Administration → Gestion des comptes*, puis désactiver l'ancien).

## Nouveautés de la version 2
- **Paiement** : « Liquide (à la livraison) » ou « En ligne » (le paiement en ligne est simulé).
- **Langue** : boutons FR | EN dans le menu. Les textes sont dans `includes/lang/fr.php` et `includes/lang/en.php`.
  Pour traduire un nouveau texte : ajouter la même clé dans les deux fichiers, puis écrire `<?= t('ma_cle') ?>` dans la page.
  Les espaces administrateur et livreur utilisent également ces traductions.
- **Mot de passe oublié** : lien sur la page de connexion. En local (`REWEAR_MODE_DEV=1`),
  le lien de réinitialisation s'affiche à l'écran ; en ligne, utiliser `REWEAR_MODE_DEV=0` pour l'envoyer par e-mail.
- **Base déjà installée ?** Importer `update_v2.sql` une fois (ajoute la table `password_resets`).
- `REWEAR_MODE_DEV=1` affiche aussi les erreurs PHP. Utiliser `REWEAR_MODE_DEV=0` en production.

## Design et prise en charge des livres

- Nouvelle page d'accueil responsive, navigation mobile, cartes produit et blocs d'impact.
- Les livres disposent d'une fiche adaptée : auteur, genre, ISBN, éditeur, année, langue et nombre de pages.
- L'accueil présente deux vitrines séparées : les vêtements en premier, puis les livres de seconde main avec auteur et format.
- Le formulaire de don propose deux cases : vêtements et livres. Une seule ou les deux peuvent être cochées pour envoyer un formulaire unique.
- Dans l'administration, sélectionner la catégorie **Livres** affiche automatiquement les champs bibliographiques.
- **Administration → Produits** propose un CRUD complet : ajouter un vêtement ou un livre, rechercher/filtrer la liste, consulter une fiche même masquée, modifier et supprimer après confirmation. Un produit déjà lié à une commande peut être masqué mais ne peut pas être supprimé, afin de conserver l'historique.
- Pour une base déjà installée, importer `update_v3_books.sql` une seule fois.
- Importer ensuite `update_v4_donation_types.sql` pour enregistrer le type de chaque don.
- Si la version 4 était déjà installée, importer `update_v5_mixed_donations.sql` pour permettre un don unique contenant vêtements et livres.
- `seed_demo_catalogue.sql` peut être importé facultativement pour remplir les deux vitrines avec des produits de démonstration.
- La User Story, les critères d'acceptation et les tests manuels sont documentés dans `docs/US-LIV-01-livres.md`.
- La synthèse complète des besoins, leur couverture et le plan de recette sont documentés dans `docs/EXIGENCES.md`.

## Structure

```
wear-share/
├── database.sql          schéma + données d'exemple
├── update_v3_books.sql   migration pour les informations bibliographiques
├── update_v4_donation_types.sql   migration pour distinguer les types de dons
├── update_v5_mixed_donations.sql  migration pour les dons vêtements + livres
├── seed_demo_catalogue.sql        produits de démonstration facultatifs
├── docs/                 User Stories, critères d'acceptation et tests
├── includes/             config, fonctions, en-tête, pied de page, lang/ (traductions)
├── assets/               styles, comportement du menu et images du site
├── uploads/              photos des produits
├── index.php, catalogue.php, produit.php     pages publiques
├── register.php, login.php, logout.php       comptes
├── panier.php, commande.php                  achat
├── don.php, mon-compte.php                   dons et historique
├── admin/                espace administrateur
└── livreur/              espace livreur
```

## Sécurité déjà en place
Mots de passe hachés (`password_hash`), requêtes préparées PDO (anti-injection SQL), échappement des sorties (anti-XSS),
jetons CSRF sur tous les formulaires, contrôle des rôles, vérification du type des images envoyées, stock verrouillé pendant la commande.

## Vérification du CRUD produits

Exécuter `C:\xampp\php\php.exe tests/admin_products_crud.php` avec MySQL démarré.
Ce test crée une base temporaire indépendante, exerce les parcours HTTP vêtements/livres
(création, consultation, modification, visibilité, suppression, validation, accès et historique),
puis supprime sa base et arrête son serveur. Il utilise les variables `REWEAR_DB_HOST`,
`REWEAR_DB_PORT`, `REWEAR_DB_USER` et `REWEAR_DB_PASS` si elles sont définies.
Le compte MySQL de test doit pouvoir créer et supprimer cette base temporaire.

## Pistes pour la suite
Vrai paiement en ligne, e-mails de confirmation, mot de passe oublié, page de statistiques d'impact (kg de vêtements sauvés), plusieurs photos par produit.
