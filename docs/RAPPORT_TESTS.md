# Bilan des tests réalisés — Re-Life

Document établi le 26 octobre 2026 à partir des exécutions et contrôles effectués pendant le développement, notamment le 22 octobre 2026. Les tests n’ont pas été relancés pour la rédaction de ce bilan.

## 1. Environnement et méthode

Les vérifications ont été effectuées en local sous Windows avec XAMPP, PHP, Apache et MariaDB. Deux scripts PHP automatisent des requêtes HTTP et contrôlent les réponses ainsi que certaines données enregistrées en base.

Chaque suite crée une base temporaire et un serveur PHP local temporaire. Les comptes et données de test sont distincts des comptes réels. Les ressources temporaires sont supprimées à la fin. L’envoi réel de courriels est désactivé dans la suite de sécurité.

Les contrôles Apache ont été réalisés séparément : le serveur de développement PHP utilisé par les suites ne traite pas les fichiers `.htaccess`.

## 2. Résultats généraux

| Ensemble | Dernier résultat constaté |
|---|---|
| `tests/security.php` : sécurité et parcours fonctionnels | 59 vérifications réussies |
| `tests/admin_products_crud.php` : gestion des vêtements et livres | 27 vérifications réussies |
| Total des deux suites | **86 vérifications réussies** |
| Boutique : séparation des rayons et filtres | 5 scénarios vérifiés |
| Apache : accès publics et protection des fichiers internes | 7 contrôles HTTP conformes |
| Disponibilité des formulaires publics | 2 pages vérifiées |
| Syntaxe PHP, JavaScript et contrôle des différences Git | Contrôles conformes sur les fichiers concernés |

Le nombre de 86 correspond aux assertions exécutées, pas à 86 fonctionnalités distinctes : plusieurs connexions sont répétées pour préparer différents scénarios. Les anciens totaux de 47 et 74 correspondent à des versions précédentes des suites et ne doivent pas être additionnés à ce bilan.

## 3. Sécurité et parcours fonctionnels : 59 vérifications

Toutes les lignes ci-dessous ont été signalées comme réussies par la dernière exécution de `tests/security.php`. La colonne « Contrôle effectué » décrit la portée du test, sans supposer une couverture exhaustive.

| ID | Contrôle effectué |
|---|---|
| S01 | Réexécution de la migration de sécurité deux fois sans erreur sur le schéma de test. |
| S02 | Mode développement désactivé lorsque `RELIFE_MODE_DEV=0`. |
| S03 | Mode développement désactivé lorsque cette variable est absente dans l’environnement de test. |
| S04 | Présence des attributs `HttpOnly` et `SameSite=Lax` dans le cookie reçu. |
| S05 | Présence des directives contrôlées : scripts limités à la même origine, `nosniff` et `no-referrer`. |
| S06 | Jeton CSRF envoyé sous forme de tableau refusé avec HTTP 400. |
| S07 | Tentative d’injection SQL dans le formulaire de connexion : réponse HTTP 200, sans redirection de connexion réussie. |
| S08 | Recherche contenant une injection SQL : absence du produit témoin qui aurait été retourné par une recherche détournée. |
| S09 | Texte contenant une balise `script` échappé dans le résultat de recherche. |
| S10 | Inscription avec un mot de passe conforme : redirection après soumission ; le compte créé est utilisé dans la suite. |
| S11 | Première connexion du client de test. |
| S12 | Un don appartenant à un autre client n’apparaît pas dans l’espace personnel du client connecté. |
| S13 | Soumission d’un don mixte contenant vêtements et livres : redirection de succès. |
| S14 | Ajout d’un produit au panier : redirection après soumission. |
| S15 | Validation d’une commande avec paiement à la livraison : redirection après soumission. |
| S16 | Après cette commande, le stock du produit diminue exactement d’une unité. |
| S17 | Tentative de modification d’e-mail sans mot de passe actuel : adresse en base inchangée. |
| S18 | Accès d’un client à la gestion des produits refusé avec HTTP 403. |
| S19 | Deuxième connexion du même client pour disposer de deux sessions. |
| S20 | Après changement de mot de passe, la session courante ne peut plus accéder à l’espace personnel. |
| S21 | Après ce changement, l’autre session du client ne peut plus accéder à l’espace personnel. |
| S22 | Inscription avec un mot de passe de 73 octets refusée : aucun compte correspondant créé. |
| S23 | Connexion avec le nouveau mot de passe avant le scénario de récupération. |
| S24 | Aucun lien contenant le jeton de récupération n’est affiché dans la réponse publique. |
| S25 | Un mot de passe trop court ne termine pas la réinitialisation. |
| S26 | Réinitialisation avec jeton valide et mot de passe conforme : redirection de succès. |
| S27 | Les jetons de récupération du compte sont supprimés après la réinitialisation. |
| S28 | Le jeton consommé ne permet plus d’afficher le formulaire de nouveau mot de passe. |
| S29 | La session ouverte avant la récupération est révoquée. |
| S30 | Un jeton expiré ne permet plus d’afficher le formulaire de réinitialisation. |
| S31 | Connexion pour préparer le test d’inactivité. |
| S32 | Session expirée après simulation de plus de 30 minutes d’inactivité. |
| S33 | Connexion pour préparer le test de durée maximale. |
| S34 | Session expirée après simulation de plus de 8 heures depuis l’authentification. |
| S35 | Connexion pour préparer le test de déconnexion. |
| S36 | Déconnexion par requête POST munie du jeton CSRF : redirection. |
| S37 | L’ancien cookie ne permet plus d’accéder à l’espace personnel après déconnexion. |
| S38 | La onzième tentative de connexion échouée est bloquée avec HTTP 429 et `Retry-After`, malgré le renouvellement des cookies. |
| S39 | La quatrième demande de récupération pour la même adresse inexistante est bloquée avec HTTP 429. |
| S40 | Connexion du compte temporaire destiné au test de suppression. |
| S41 | Un client ne peut pas supprimer un compte via la page administrateur : HTTP 403. |
| S42 | Connexion administrateur pour tester la suppression des comptes. |
| S43 | La page de confirmation de suppression s’affiche en GET sans supprimer le compte. |
| S44 | Une suppression de compte sans jeton CSRF est refusée avec HTTP 400. |
| S45 | L’administrateur ne peut pas supprimer son propre compte ; celui-ci reste en base. |
| S46 | Le compte témoin lié à des commandes et dons n’est pas supprimé. |
| S47 | Un compte sans historique est supprimé par la soumission de confirmation. |
| S48 | Les jetons de récupération du compte supprimé sont également effacés. |
| S49 | La session du compte supprimé ne donne plus accès à l’espace personnel. |
| S50 | Présence dans le tableau de bord des liens contrôlés vers les commandes à traiter et les dons reçus. |
| S51 | Filtrage des dons en attente et reçus : les données témoins apparaissent uniquement dans les listes appropriées. |
| S52 | Une commande livrée est absente de la liste « à traiter », mais présente dans la liste complète. |
| S53 | Un fichier contenant du PHP présenté comme un JPEG est refusé ; aucun produit n’est créé. |
| S54 | Un fichier dépassant 3 Mo est refusé ; aucun produit n’est créé. |
| S55 | Une image PNG valide envoyée sous le nom `image.php` est acceptée et enregistrée comme image. |
| S56 | Le nom enregistré est aléatoire avec extension `.png`, et le contenu PHP ajouté à l’image a disparu après réencodage. |
| S57 | Une image de 6001 pixels de largeur est refusée. |
| S58 | La fiche du produit témoin reste consultable par l’administrateur. |
| S59 | Aucun avertissement, erreur fatale ou notice PHP correspondant aux motifs recherchés dans le journal du serveur de test. |


## 4. Gestion des produits : 27 vérifications

Toutes les lignes suivantes ont réussi lors de l’exécution de `tests/admin_products_crud.php`. Le terme CRUD désigne la création, la consultation, la modification et la suppression.

| ID | Contrôle effectué |
|---|---|
| P01 | Un visiteur non connecté est redirigé lorsqu’il tente d’accéder à la gestion des produits. |
| P02 | Connexion administrateur avec les identifiants temporaires. |
| P03 | Présence des deux actions « Ajouter un vêtement » et « Ajouter un livre ». |
| P04 | La consultation d’un produit inexistant retourne HTTP 404. |
| P05 | Stock négatif refusé, sans création de produit, avec conservation de la description saisie. |
| P06 | Prix non numérique refusé, sans création de produit. |
| P07 | Soumission sans jeton CSRF refusée avec HTTP 400. |
| P08 | Création d’un vêtement de catégorie Homme. |
| P09 | Consultation de la fiche du vêtement créé. |
| P10 | Modification du vêtement et vérification du nouveau stock en base. |
| P11 | Masquage du vêtement : valeur `actif=0` en base. |
| P12 | Remise en visibilité du vêtement : valeur `actif=1` en base. |
| P13 | Ouverture de la confirmation de suppression du vêtement sans suppression en GET. |
| P14 | Suppression du vêtement après soumission POST. |
| P15 | Soumission d’un livre sans auteur : maintien sur le formulaire, sans redirection de succès. |
| P16 | Création d’un livre avec ses informations spécifiques. |
| P17 | Consultation de sa fiche, avec présence du nom et de l’auteur. |
| P18 | Modification du livre et vérification du nouveau stock en base. |
| P19 | Masquage du livre : valeur `actif=0` en base. |
| P20 | Remise en visibilité du livre : valeur `actif=1` en base. |
| P21 | Ouverture de la confirmation de suppression du livre sans suppression en GET. |
| P22 | Suppression du livre après soumission POST. |
| P23 | Suppression d’un produit lié à une commande refusée : produit conservé en base. |
| P24 | Ligne de commande liée conservée après cette tentative. |
| P25 | Filtre livres et recherche par auteur : présence du livre attendu et absence du vêtement témoin. |
| P26 | Présence des libellés anglais contrôlés dans l’administration des produits. |
| P27 | Après changement du rôle de l’administrateur de test en utilisateur, accès à la gestion des produits refusé avec HTTP 403. |

## 5. Contrôles complémentaires de la boutique

Ces cinq scénarios ont été contrôlés en analysant le HTML produit par PHP, avec lecture de la base locale. Ils ne constituent pas un test visuel dans un navigateur.

| ID | Scénario | Résultat constaté |
|---|---|---|
| B01 | Boutique sans filtre | Section vêtements avant la section livres ; aucun livre dans le rayon vêtements et aucun vêtement dans le rayon livres. |
| B02 | Filtre `cat=Livres` | Section livres uniquement. |
| B03 | Filtre `cat=Homme` | Section vêtements uniquement. |
| B04 | Boutique en anglais | Présence des titres « Clothing » et « Second-hand books ». |
| B05 | Recherche sans correspondance | Aucune section de produits affichée. |

## 6. Contrôles du serveur Apache

Sept requêtes HTTP HEAD ont donné les résultats suivants :

| ID | Ressource | Résultat constaté |
|---|---|---|
| H01 | `login.php` | HTTP 200 : page accessible. |
| H02 | `assets/app.js` | HTTP 200 : fichier public accessible. |
| H03 | `.git/config` | HTTP 403 : accès interdit. |
| H04 | `database.sql` | HTTP 403 : accès interdit. |
| H05 | `includes/config.example.php` | HTTP 403 : accès interdit. |
| H06 | `tests/security.php` | HTTP 403 : accès interdit. |
| H07 | `uploads/.htaccess` | HTTP 403 : accès interdit. |

Deux contrôles supplémentaires par requête GET ont confirmé :

- `register.php` : HTTP 200 et présence du champ CSRF du formulaire.
- `login.php` : HTTP 200 et présence du champ CSRF du formulaire.

La syntaxe de la configuration Apache a également été vérifiée avec un résultat « Syntax OK ». La tentative de redémarrage d’Apache par commande de service n’a pas abouti, car le service Apache2.4 n’était pas installé. Ce redémarrage ne doit donc pas être présenté comme validé par ce contrôle.

## 7. Contrôles techniques des modifications

- Analyse syntaxique PHP des fichiers modifiés avec `php -l` : aucune erreur de syntaxe détectée lors des contrôles.
- Analyse syntaxique du JavaScript avec `node --check assets/app.js` : contrôle réussi.
- Contrôle des différences avec `git diff --check` : aucune erreur d’espacement signalée ; des avertissements de conversion LF/CRLF ont été affichés par Git.
- Vérification de l’activation de GD dans PHP en ligne de commande : extension active. Cette vérification ne prouve pas que le processus Apache déjà démarré avait rechargé sa configuration.

## 8. Limites et tests restant à réaliser

Les résultats établissent le bon fonctionnement des scénarios testés dans l’environnement local. Ils ne constituent ni une garantie d’absence de vulnérabilité ni une validation complète en production.

Les points suivants ne disposent pas d’un résultat d’exécution complet dans cette campagne :

- Livraison réelle des courriels de récupération et configuration du transport mail.
- HTTPS sur l’hébergement cible, attribut `Secure` des cookies et HSTS en conditions réelles.
- Clics « Afficher / Masquer » dans les navigateurs, utilisation au clavier et comportement sur mobile : le code a été ajouté et sa syntaxe contrôlée, mais ces interactions n’ont pas été validées visuellement.
- Vérification visuelle complète des cinq cartes du tableau de bord : les tests automatisés contrôlent certains liens et les filtres, sans simuler tous les clics dans un navigateur.
- Compatibilité complète entre navigateurs, affichage responsive et audit d’accessibilité.
- Paiement bancaire réel, charge élevée et requêtes concurrentes sur les stocks, comptes et jetons.
- Ensemble des transitions de livraison et de dons ; don de livres seul et don de vêtements seul dans une campagne dédiée.
- Cas supplémentaires : doublon d’e-mail, confirmations de mots de passe différentes, tous les champs obligatoires, quotas par IP et quota d’inscription, protection du dernier administrateur avec plusieurs sessions concurrentes.
- Acceptation de toutes les variantes JPEG et WebP et test indépendant de la limite de 12 millions de pixels : la suite actuelle emploie notamment des images PNG et un faux JPEG.

Un problème « Accès refusé » après inscription a été signalé par un utilisateur. La création d’un compte a réussi dans les tests isolés et les formulaires publics étaient accessibles. L’erreur signalée n’a pas été reproduite ; l’adresse exacte ou une capture du cas réel restait nécessaire. Ce signalement ne doit pas être classé comme définitivement résolu.

## 9. Reproduction des suites

Depuis le dossier du projet, avec MySQL/MariaDB démarré :

```powershell
C:\xampp\php\php.exe tests/security.php
C:\xampp\php\php.exe tests/admin_products_crud.php
```

Le compte SQL utilisé pour ces tests doit pouvoir créer et supprimer les bases temporaires. Les suites ne doivent pas être lancées avec des identifiants de production. Les résultats d’une nouvelle exécution doivent être conservés avec la date et la version du code correspondantes.
