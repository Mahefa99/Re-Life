# Sécurité de Re-Life

État du 22 octobre 2026 — branche `security-hardening`.

## Configuration et installation existante

1. Sauvegarder la base avant déploiement, puis importer `update_v6_security.sql`.
   Cette migration ajoute `users.session_version` et la table `auth_throttles`.
   Elle conserve les comptes, produits, dons et commandes. Elle peut être réexécutée.
2. Copier `includes/config.example.php` vers `includes/config.local.php`, exclu de Git,
   et reprendre les paramètres de l’ancienne configuration. Le code utilise désormais
   `includes/bootstrap.php` ; l’ancien `config.php` n’est plus chargé par les pages.
   Les variables d’environnement sont prioritaires, y compris la valeur `0`.
3. Activer les extensions PDO MySQL, mbstring, fileinfo et GD dans le PHP du serveur.
   cURL est nécessaire à la suite de tests. Après modification de php.ini, redémarrer
   Apache depuis XAMPP. Sans GD, les nouveaux envois d’images sont refusés.
4. Sur le serveur public, configurer une URL HTTPS fixe dans `RELIFE_SITE_URL`,
   `RELIFE_REQUIRE_HTTPS=1` et `RELIFE_MODE_DEV=0`. Le serveur doit signaler HTTPS
   à PHP. Derrière un proxy, cette information doit venir d’un proxy de confiance,
   jamais d’un en-tête client non vérifié. HSTS est envoyé seulement en HTTPS forcé.
5. Configurer et tester le transport utilisé par `mail()`. Aucun lien de récupération
   n’est affiché ou journalisé. Un échec d’envoi garde une réponse générique.
6. Créer un administrateur nominatif et désactiver le compte de démonstration avant
   ouverture au public. Utiliser un compte SQL dédié à `relife` avec uniquement
   SELECT, INSERT, UPDATE et DELETE pour l’application. Les migrations utilisent
   un compte distinct autorisé à modifier le schéma.

Les valeurs locales par défaut permettent XAMPP ; elles ne constituent pas une
configuration publique prête à l’emploi. PHP 8.0 doit être remplacé par une branche
maintenue avant publication. Les changements de version du serveur se testent
séparément et ne sont pas appliqués par un commit Git.

## Authentification et sessions

- Cookies `relife_session`, HttpOnly, SameSite=Lax, limités au chemin du site.
  Secure est activé sur HTTPS ou lorsque HTTPS est requis.
- Identifiant renouvelé à la connexion et lors de la déconnexion ; CSRF renouvelé
  à la connexion. Session strictement transmise par cookie.
- Expiration côté serveur après 30 minutes d’inactivité et 8 heures au maximum.
  Les paramètres `RELIFE_SESSION_IDLE` et `RELIFE_SESSION_MAX` sont en secondes.
- Changement ou récupération du mot de passe : incrément de `session_version`.
  Toutes les anciennes sessions, y compris la session courante, doivent se reconnecter.
- La désactivation, la réactivation ou le changement de rôle depuis l’administration
  révoquent également les sessions de la personne concernée.
- Changer l’e-mail exige le mot de passe actuel et révoque sessions et jetons.
- Nouveaux mots de passe : au moins 12 caractères et au maximum 72 octets,
  sans octet nul, pour éviter la troncature bcrypt. Les anciens mots de passe
  continuent à fonctionner à la connexion. Hachage PHP et rehash opportuniste.

## Limites de tentatives

| Action | Par compte | Par adresse IP | Fenêtre |
|---|---:|---:|---:|
| Connexion | 10 | 50 | 15 minutes |
| Récupération | 3 | 20 | 1 heure |
| Inscription | 5 | 20 | 1 heure |

Les compteurs sont stockés en base et restent valables après un changement de
cookie. Les clés sont des empreintes d’identifiants normalisés ; ce hachage n’est
pas une anonymisation garantie. Les entrées expirées sont nettoyées périodiquement
lors des requêtes. Une maintenance peut aussi supprimer les entrées expirées.
Une connexion réussie remet à zéro le compteur du compte, pas celui de l’IP.
La réponse de blocage utilise HTTP 429 et Retry-After. Aucun en-tête IP fourni
par le visiteur n’est utilisé. Un réseau partagé peut donc partager le quota IP.

## Récupération de compte

Jeton aléatoire de 32 octets, empreinte SHA-256 en base, validité d’une heure.
L’émission verrouille le compte et remplace les anciens jetons. La consommation
verrouille le compte puis revérifie le jeton dans la transaction ; la modification
du mot de passe, l’invalidation des sessions et la suppression des jetons sont
atomiques. Deux requêtes simultanées ne peuvent pas consommer le même jeton.

## Fichiers et protections HTTP

- Envoi réservé aux administrateurs avec contrôle CSRF.
- JPEG, PNG et WebP uniquement ; 3 Mo maximum, 6000 pixels par dimension et
  12 millions de pixels maximum. MIME détecté côté serveur, dimensions vérifiées,
  décodage complet et réencodage avec GD. Les données annexées et métadonnées
  ne sont pas recopiées. Les noms utilisent 16 octets aléatoires.
- `uploads/.htaccess` force un traitement statique des images et refuse les autres
  extensions. Pas d’exécution de scripts ni de listing de répertoire.
- Le `.htaccess` principal protège les chemins cachés, les fichiers SQL et de
  configuration, ainsi que `includes`, `tests` et `docs`. Apache doit autoriser
  les directives et charger mod_rewrite. Sur Nginx, configurer des règles équivalentes.
  Le serveur de développement PHP ne lit pas les `.htaccess` : ne pas l’exposer.
- PDO utilise les préparations natives et interdit les requêtes multiples.
- CSP interdit les scripts inline, objets et cadres ; les styles inline existants
  restent autorisés. Les en-têtes incluent nosniff, no-referrer et no-store sur PHP.

## Validation avant fusion

- `php tests/admin_products_crud.php` : cycle CRUD vêtements/livres et historique.
- `php tests/security.php` : authentification, expiration et révocation des sessions,
  CSRF, injections SQL/XSS, récupération, quotas et fichiers trompeurs ou trop grands.
  Vérifie aussi inscription, don mixte, panier, commande et séparation des comptes.
- Les suites créent une base MySQL jetable et un serveur local temporaire ; elles
  les suppriment à la fin. Ne pas utiliser un compte de production pour ces tests.
- Vérifier les refus HTTP Apache pour `.git/config`, `database.sql`,
  `includes/config.example.php`, `tests/security.php` et `uploads/.htaccess`.
- Sur l’hébergement cible, vérifier HTTPS, cookie Secure et livraison effective
  des e-mails. Ces vérifications d’infrastructure ne sont pas remplacées par les tests locaux.

La branche doit être relue avant fusion. Aucune fusion automatique n’est configurée.
