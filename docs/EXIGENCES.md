# Cahier des exigences et traçabilité — ReWear

Ce document formalise les deux listes manuscrites fournies pour le projet et les relie à l'application actuelle. L'évaluation correspond à l'état du code au 15 octobre 2026.

## Règles de lecture

- **Couvert** : le comportement existe dans le code et peut être vérifié manuellement.
- **Partiel** : le parcours existe, mais une limite identifiée empêche de le considérer comme prêt pour une mise en production complète.
- Les deux familles de produits sont les **vêtements** et les **livres de seconde main**.
- Une demande de don peut contenir des vêtements, des livres, ou les deux dans un seul formulaire.

## Exigences fonctionnelles

| ID | Exigence | Statut | Preuves dans le projet | Critère vérifiable |
|---|---|---|---|---|
| EF-01 | Inscription | **Couvert** | `register.php`, table `users` dans `database.sql` | Un visiteur peut créer un compte avec un nom, un e-mail unique et un mot de passe d'au moins 8 caractères. Le mot de passe enregistré est haché. |
| EF-02 | Connexion | **Couvert** | `login.php`, `includes/functions.php` | Un compte actif peut se connecter avec ses identifiants. La session est renouvelée et l'utilisateur est dirigé vers l'espace correspondant à son rôle. |
| EF-03 | Déconnexion | **Couvert** | `logout.php`, `includes/header.php`, `mon-compte.php` | Une déconnexion envoyée en `POST` avec un jeton CSRF détruit la session d'authentification et conserve seulement la langue choisie. |
| EF-04 | Gestion du profil | **Couvert** | `mon-compte.php`, `includes/lang/fr.php`, `includes/lang/en.php` | L'utilisateur peut modifier son nom, son e-mail, son téléphone et son adresse, puis changer son mot de passe après vérification du mot de passe actuel. |
| EF-05 | Consultation du catalogue | **Couvert** | `index.php`, `catalogue.php`, `assets/style.css` | Le visiteur voit uniquement les produits actifs et disponibles. L'accueil présente d'abord les vêtements, puis les livres dans une section distincte. |
| EF-06 | Recherche d'articles | **Couvert** | `catalogue.php` | Une recherche peut trouver un article par nom ou description et un livre par auteur, genre, ISBN ou éditeur. Un filtre par catégorie est disponible. |
| EF-07 | Consultation des détails | **Couvert** | `produit.php` | La fiche affiche le prix, l'état, le stock et les informations propres à la famille du produit. Pour un livre : auteur, genre, ISBN, éditeur, année, langue, pages et format lorsqu'ils existent. |
| EF-08 | Consultation et gestion du panier | **Couvert** | `panier.php`, `produit.php` | Un utilisateur peut ajouter, modifier ou retirer un article. Les quantités sont limitées au stock et les produits devenus indisponibles sont retirés avec un message. |
| EF-09 | Choix du paiement | **Partiel** | `commande.php`, champ `mode_paiement` dans `database.sql` | Le client choisit « liquide à la livraison » ou « en ligne ». Le choix est enregistré, mais le paiement en ligne est actuellement simulé et ne débite aucune carte. |
| EF-10 | Gestion de la livraison et de la collecte | **Couvert** | `admin/commandes.php`, `admin/dons.php`, `livreur/index.php` | L'administrateur assigne un livreur. Le livreur ne voit que ses missions et fait avancer une collecte ou une livraison selon les transitions autorisées. |
| EF-11 | Gestion des utilisateurs | **Couvert** | `admin/utilisateurs.php` | L'administrateur peut ajouter plusieurs administrateurs ou livreurs, changer les rôles et activer ou désactiver les comptes. Son propre accès est protégé et au moins un administrateur actif doit toujours rester. |
| EF-12 | Gestion des produits | **Couvert** | `admin/produits.php`, `update_v3_books.sql` | L'administrateur peut créer, modifier, masquer et remettre en ligne un produit. Le formulaire adapte ses champs aux vêtements et aux livres ; l'auteur est obligatoire pour un livre. |
| EF-13 | Gestion des commandes | **Couvert** | `commande.php`, `admin/commandes.php`, tables `commandes` et `commande_lignes` | Une commande valide enregistre ses lignes et diminue le stock dans une transaction. L'administrateur suit son statut ; une annulation remet le stock une seule fois. |
| EF-14 | Interface multilingue | **Couvert** | `includes/lang/fr.php`, `includes/lang/en.php`, `includes/functions.php`, `includes/header.php`, `admin/*.php`, `livreur/index.php` | Le visiteur, le client, l'administrateur et le livreur peuvent utiliser leurs parcours en français ou en anglais. |
| EF-15 | Réinitialisation du mot de passe | **Partiel** | `mot-de-passe-oublie.php`, `reinitialiser.php`, table `password_resets` | Le lien possède un jeton aléatoire, haché en base, valable une heure et à usage unique. En local, il est affiché pour la démonstration ; en production, l'envoi d'e-mail dépend d'un service mail qui reste à configurer et tester. |

## Besoins métier complémentaires

Ces besoins précisent le périmètre ReWear et complètent les quinze exigences de la liste initiale.

| ID | Besoin | Statut | Preuves | Critère vérifiable |
|---|---|---|---|---|
| BM-01 | Vendre des vêtements et des livres de seconde main | **Couvert** | `docs/US-LIV-01-livres.md`, `index.php`, `catalogue.php`, `produit.php`, `admin/produits.php` | Les deux familles disposent d'une vitrine, de cartes et d'une fiche détaillée adaptées. |
| BM-02 | Donner des vêtements, des livres ou les deux | **Couvert** | `docs/US-DON-02-types-de-don.md`, `don.php`, `assets/app.js`, `update_v5_mixed_donations.sql` | Deux cases à cocher permettent les trois combinaisons. Au moins une case est obligatoire et une seule demande de collecte est créée avec le type `vetements`, `livres` ou `mixte`. |
| BM-03 | Afficher le type du don aux acteurs concernés | **Couvert** | `mon-compte.php`, `admin/dons.php`, `livreur/index.php`, `includes/functions.php` | Le donateur, l'administrateur et le livreur voient « Vêtements », « Livres » ou « Vêtements et livres » sur la même demande. |

## Exigences non fonctionnelles

| ID | Exigence | Statut | Mise en œuvre actuelle | Critère vérifiable |
|---|---|---|---|---|
| ENF-01 | Sécurité des mots de passe | **Couvert** | `password_hash()` à la création et au changement ; `password_verify()` à la connexion ; jetons de réinitialisation hachés et temporaires | Aucun mot de passe utilisateur ni jeton de réinitialisation utilisable n'est stocké en clair dans la base. |
| ENF-02 | Confidentialité des informations | **Couvert** | Requêtes limitées à l'identifiant connecté dans `mon-compte.php` ; gardes d'accès dans `includes/functions.php` ; échappement des sorties avec `e()` | Un utilisateur ne voit que ses commandes et dons. Les données d'administration ou de livraison exigent le rôle prévu. |
| ENF-03 | Ergonomie : interface simple, claire et compréhensible | **Partiel** | Navigation responsive, libellés, messages, états visuels et styles dans `assets/style.css` et `assets/app.js` | Les parcours principaux restent utilisables à 390 px et sur ordinateur, au clavier et sans débordement. Une validation avec de vrais utilisateurs reste nécessaire. |
| ENF-04 | Gestion des erreurs | **Partiel** | Messages de validation et messages flash dans les formulaires ; exceptions sensibles envoyées au journal | Une erreur métier affiche un message compréhensible sans exposer une requête SQL. Il reste à ajouter des pages d'erreur globales et une supervision pour la production. |
| ENF-05 | Validation des formulaires avant envoi | **Couvert** | Attributs HTML (`required`, `min`, `max`, types) et contrôles PHP côté serveur ; jetons CSRF sur les actions | Une donnée invalide est refusée même si les contrôles du navigateur sont contournés. Le formulaire de don refuse notamment zéro case cochée. |
| ENF-06 | Gestion des accès selon le rôle | **Couvert** | `exiger_connexion()`, `exiger_utilisateur()` et `exiger_role()` dans `includes/functions.php` | Les rôles `utilisateur`, `admin` et `livreur` sont dirigés vers leurs interfaces et une URL interdite est bloquée côté serveur. |

## Limites connues avant une mise en production

1. **Paiement en ligne simulé** : l'option existe pour la démonstration, mais aucune passerelle, donnée bancaire, confirmation serveur ou remboursement réel n'est implémenté.
2. **Compte administrateur local de démonstration** : les identifiants documentés dans le `README.md` doivent être remplacés. Il faut créer un administrateur nominatif, changer le mot de passe, puis désactiver le compte fourni avec les données d'exemple.
3. **Réinitialisation en production** : le code utilise l'envoi mail du serveur. Il faut configurer un service SMTP fiable, tester la délivrabilité et utiliser une URL HTTPS correcte.
4. **Configuration de production** : utiliser `REWEAR_MODE_DEV=0`, des identifiants de base de données dédiés, HTTPS et des cookies de session sécurisés, protéger les sauvegardes et centraliser les journaux d'erreur.
5. **Durcissement restant** : prévoir la limitation des tentatives de connexion et de réinitialisation, des en-têtes de sécurité adaptés et des tests automatisés de régression.

## User Stories de référence

| ID | User Story synthétique |
|---|---|
| US-AUTH-01 | En tant que visiteur, je veux créer un compte, me connecter et me déconnecter afin d'utiliser les services personnels. |
| US-PRO-01 | En tant qu'utilisateur, je veux mettre à jour mes coordonnées et mon mot de passe afin de garder un profil exact et sécurisé. |
| US-CAT-01 | En tant que visiteur, je veux parcourir, rechercher et consulter un article afin de décider s'il me convient. |
| US-LIV-01 | En tant qu'administrateur, je veux enregistrer les informations bibliographiques d'un livre afin d'afficher une fiche adaptée. |
| US-DON-02 | En tant que donateur, je veux sélectionner les vêtements, les livres ou les deux dans un seul formulaire afin d'organiser une seule collecte. |
| US-ACH-01 | En tant que client, je veux gérer mon panier, choisir mon mode de paiement et commander afin de recevoir mes articles. |
| US-LOG-01 | En tant qu'administrateur ou livreur, je veux assigner et suivre les missions afin d'organiser les collectes et livraisons. |
| US-ADM-01 | En tant qu'administrateur, je veux gérer les utilisateurs, produits et commandes afin d'exploiter la plateforme. |
| US-I18N-01 | En tant qu'utilisateur, je veux choisir le français ou l'anglais afin de comprendre l'interface. |
| US-REC-01 | En tant qu'utilisateur, je veux réinitialiser un mot de passe oublié afin de retrouver l'accès à mon compte. |

## Matrice de traçabilité Besoin → User Story → tests

| Besoin | User Story | Exigences / critères couverts | Tests manuels associés |
|---|---|---|---|
| Accéder à la plateforme avec un compte | US-AUTH-01 | EF-01, EF-02, EF-03, ENF-01, ENF-05 | TC-01, TC-02 |
| Gérer ses informations personnelles | US-PRO-01 | EF-04, ENF-02, ENF-05 | TC-03 |
| Retrouver l'accès à son compte | US-REC-01 | EF-15, ENF-01 | TC-04 |
| Consulter les articles de seconde main | US-CAT-01 | EF-05, EF-06, EF-07, ENF-03 | TC-05, TC-06, TC-07 |
| Proposer des livres avec une fiche adaptée | US-LIV-01 | BM-01, EF-05, EF-07, EF-12 | TC-05, TC-07, TC-13 |
| Donner une ou deux familles d'articles en une collecte | US-DON-02 | BM-02, BM-03, ENF-05 | TC-08 |
| Acheter un article disponible | US-ACH-01 | EF-08, EF-09, EF-13 | TC-09, TC-10, TC-11 |
| Organiser les collectes et livraisons | US-LOG-01 | EF-10, BM-03, ENF-06 | TC-12 |
| Administrer la plateforme | US-ADM-01 | EF-11, EF-12, EF-13, ENF-02, ENF-06 | TC-12, TC-13 |
| Utiliser l'interface dans sa langue | US-I18N-01 | EF-14 | TC-14 |
| Utiliser une interface claire et recevoir des erreurs utiles | Toutes | ENF-03, ENF-04, ENF-05 | TC-15 |

## Plan de tests manuel concis

Utiliser une base de test, un compte utilisateur, un compte livreur et un compte administrateur. Tester sur ordinateur puis avec une largeur mobile proche de 390 px.

| ID | Parcours | Actions principales | Résultat attendu |
|---|---|---|---|
| TC-01 | Inscription | Créer un compte valide, puis tenter le même e-mail et un mot de passe court | Le premier compte est créé ; les deux tentatives invalides affichent un message clair. |
| TC-02 | Connexion et déconnexion | Tester un bon mot de passe, un mauvais mot de passe, un compte désactivé, puis se déconnecter | Seul le compte actif avec le bon mot de passe entre ; la déconnexion ferme la session. |
| TC-03 | Profil | Modifier nom, e-mail, téléphone et adresse ; tester un e-mail déjà pris ; changer le mot de passe avec une mauvaise puis une bonne confirmation | Les valeurs valides sont conservées et la session reflète le nouveau profil ; les valeurs invalides sont refusées. |
| TC-04 | Mot de passe oublié | Demander un lien, l'utiliser, puis essayer de le réutiliser ou d'utiliser un lien expiré | Le premier changement réussit ; un jeton utilisé, invalide ou expiré est rejeté. |
| TC-05 | Accueil et catalogue | Ouvrir l'accueil puis la boutique avec des vêtements et des livres disponibles | Les vêtements apparaissent dans la première vitrine, les livres dans la seconde, et tous les articles disponibles dans le catalogue. |
| TC-06 | Recherche | Rechercher un vêtement, puis un livre par titre, auteur, genre, ISBN et éditeur ; filtrer sur « Livres » | Chaque recherche retourne les articles correspondants sans afficher de produit inactif ou épuisé. |
| TC-07 | Détails | Ouvrir un vêtement puis un livre | Le vêtement affiche sa taille ; le livre affiche son auteur et ses métadonnées sans attribut trompeur. |
| TC-08 | Don unique | Envoyer successivement : vêtements seuls, livres seuls, les deux, puis aucune case | Les trois premiers créent chacun une seule demande du bon type ; le dernier est bloqué. Le type est visible dans les trois espaces. |
| TC-09 | Panier | Ajouter un article, changer sa quantité, le retirer ; diminuer ensuite son stock depuis l'administration | Le panier reste cohérent avec le stock et explique toute correction ou suppression automatique. |
| TC-10 | Commande et paiement | Commander une fois en liquide et une fois avec l'option en ligne | Les deux modes sont enregistrés. L'interface précise que le paiement en ligne est simulé et ne demande aucune carte. |
| TC-11 | Cohérence du stock | Dans deux sessions, essayer d'acheter simultanément la dernière unité du même produit | Une seule commande prend la dernière unité ; l'autre reçoit un message et aucun stock négatif n'est créé. |
| TC-12 | Livraison et rôles | Assigner un livreur, avancer les statuts dans l'ordre, puis tenter les pages admin/livreur avec un mauvais rôle | Les transitions autorisées réussissent ; les transitions et accès interdits sont refusés. |
| TC-13 | Administration | Créer/modifier/masquer un vêtement et un livre ; gérer un compte ; annuler une commande | Les changements sont visibles, l'auteur manquant bloque un livre, et l'annulation remet le stock une seule fois. |
| TC-14 | Langues | Basculer FR/EN sur l'accueil, le catalogue, le détail, le panier, la commande, le don, le compte, l'administration et l'espace livreur | Toutes les pages changent de langue, y compris les libellés, états et messages des espaces administrateur et livreur. |
| TC-15 | Ergonomie, validation et erreurs | Parcourir les formulaires au clavier, tester les champs requis et ouvrir les pages principales à 390 px et sur ordinateur | Le focus reste utilisable, les erreurs sont lisibles, les champs invalides sont refusés et aucun contenu essentiel ne déborde. |

## Décision de recette

La version peut être présentée comme une **version scolaire fonctionnelle locale** lorsque TC-01 à TC-15 ont été exécutés et documentés. Une mise en production publique exige d'abord la levée des limites EF-09 et EF-15 ainsi que les actions de durcissement listées ci-dessus.
