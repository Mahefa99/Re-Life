# Re-Life — plateforme de don et de réutilisation de vêtements

Site PHP / MySQL : on y donne des vêtements et des livres, et on y achète des pièces de seconde main à prix très réduits.

## Rôles

| Rôle | Ce qu'il peut faire |
|---|---|
| **Utilisateur** (donateur + acheteur) | Créer un compte, faire un don, acheter (panier, commande), suivre ses dons et commandes |
| **Administrateur** | Mettre les pièces en ligne, gérer produits / commandes / dons / comptes, assigner les livreurs |
| **Livreur** | Voir ses missions : collecter les dons, livrer les commandes |

Un compte est **obligatoire** avant de soumettre un don ou d'acheter.

## Installation (XAMPP ou WAMP)

1. Copier le dossier `Re-Life` dans `htdocs` (XAMPP) ou `www` (WAMP).
2. Démarrer Apache et MySQL.
3. Ouvrir phpMyAdmin → **Importer** → choisir `database.sql`.
4. Copier `includes/config.example.php` vers `includes/config.local.php` et renseigner la connexion MySQL locale (XAMPP standard : utilisateur `root`, mot de passe vide, base `relife`). Les variables `RELIFE_*` sont prioritaires. Pour une installation existante, reporter les anciennes valeurs de `config.php` dans ce fichier local.
5. Ouvrir `http://localhost/Re-Life/`.

**Compte administrateur par défaut :** `admin@relife.mu` / `Admin123!` → à changer dès la première connexion
(pour l'instant : créer un nouvel admin dans *Administration → Gestion des comptes*, puis désactiver l'ancien).

## Nouveautés de la version 2
- **Paiement** : « Liquide (à la livraison) » ou « En ligne » (le paiement en ligne est simulé).
- **Langue** : boutons FR | EN dans le menu. Les textes sont dans `includes/lang/fr.php` et `includes/lang/en.php`.
  Pour traduire un nouveau texte : ajouter la même clé dans les deux fichiers, puis écrire `<?= t('ma_cle') ?>` dans la page.
  Les espaces administrateur et livreur utilisent également ces traductions.
- **Mot de passe oublié** : les liens sont envoyés uniquement par e-mail et ne sont jamais affichés, même en développement. Configurer le transport mail du serveur avant utilisation.
- **Base déjà installée ?** Importer `update_v2.sql` une fois (ajoute la table `password_resets`).
- Le mode développement est désactivé par défaut. `RELIFE_MODE_DEV=0` le désactive explicitement ; ne jamais utiliser `1` sur un serveur public.

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
- Avant le déploiement des corrections de sécurité, importer `update_v6_security.sql` (versions de session et compteurs de tentatives). La migration est réexécutable.
- `seed_demo_catalogue.sql` peut être importé facultativement pour remplir les deux vitrines avec des produits de démonstration.
- La User Story, les critères d'acceptation et les tests manuels sont documentés dans `docs/US-LIV-01-livres.md`.
- La synthèse complète des besoins, leur couverture et le plan de recette sont documentés dans `docs/EXIGENCES.md`.

## Structure

```
Re-Life/
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

La politique de sécurité et les étapes de déploiement sont décrites dans [docs/SECURITE.md](docs/SECURITE.md).

## Vérification du CRUD produits

Exécuter `C:\xampp\php\php.exe tests/admin_products_crud.php` avec MySQL démarré.
Ce test crée une base temporaire indépendante, exerce les parcours HTTP vêtements/livres
(création, consultation, modification, visibilité, suppression, validation, accès et historique),
puis supprime sa base et arrête son serveur. Il utilise les variables `RELIFE_DB_HOST`,
`RELIFE_DB_PORT`, `RELIFE_DB_USER` et `RELIFE_DB_PASS` si elles sont définies.
Le compte MySQL de test doit pouvoir créer et supprimer cette base temporaire.

## Tests de sécurité

Exécuter `C:\xampp\php\php.exe tests/security.php`. La suite utilise également une base temporaire, sans modifier les comptes du site. Elle vérifie notamment les sessions, la récupération, les limites de tentatives, les injections et les fichiers envoyés.
PHP doit disposer des extensions `pdo_mysql`, `mbstring`, `fileinfo`, `curl` et `gd`. Le serveur Apache doit autoriser les règles `.htaccess` du projet.

## Pistes pour la suite
Vrai paiement en ligne, e-mails de confirmation, mot de passe oublié, page de statistiques d'impact (kg de vêtements sauvés), plusieurs photos par produit.

## Identité Re-Life

Le site est accessible à l’adresse `http://localhost/Re-Life/`. La base MySQL s’appelle `relife` et se gère dans phpMyAdmin. Les variables de configuration utilisent le préfixe `RELIFE_`. Le nom affiché reste **Re-Life** dans les deux langues.

Importer `database.sql` uniquement pour une nouvelle installation ; pour une base contenant déjà des données, sauvegarder puis appliquer uniquement les migrations nécessaires.
