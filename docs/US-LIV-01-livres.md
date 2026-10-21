# US-LIV-01 — Afficher et gérer les livres

## Besoin

ReWear doit permettre de proposer des livres en plus des vêtements. Une fiche livre doit présenter les informations utiles à un acheteur sans afficher des attributs propres aux vêtements.

## User Story

**En tant qu'administrateur, je veux enregistrer les informations bibliographiques d'un livre afin que les visiteurs puissent consulter une fiche adaptée avant de l'acheter.**

- Priorité proposée : haute
- Estimation proposée : 5 points
- Sprint proposé : prochain sprint fonctionnel

## Critères d'acceptation

1. Lorsque l'administrateur choisit la catégorie **Livres**, le formulaire affiche les champs auteur, genre, ISBN, éditeur, année, langue et nombre de pages.
2. L'auteur est obligatoire pour un livre. Les autres informations bibliographiques sont facultatives.
3. Les informations enregistrées apparaissent sur la page détaillée du livre.
4. Le catalogue et l'accueil affichent l'auteur du livre à la place de la taille.
5. Un livre sans photo utilise un symbole de livre.
6. Le fonctionnement des vêtements, dont l'affichage de la taille, reste opérationnel.
7. L'affichage reste lisible sur ordinateur et sur téléphone.
8. L'accueil présente les vêtements dans une première vitrine et les livres dans une seconde vitrine située en dessous.
9. Une carte livre affiche au minimum sa couverture, son titre, son auteur, son genre ou format et son prix.

## Cas de test manuels

| ID | Scénario | Étapes principales | Résultat attendu |
|---|---|---|---|
| TC-LIV-01 | Ajout valide | Créer un produit de catégorie Livres avec auteur et métadonnées | Le livre est enregistré et visible dans le catalogue et sa fiche |
| TC-LIV-02 | Auteur manquant | Créer un livre sans auteur | Le formulaire refuse l'enregistrement et explique que l'auteur est obligatoire |
| TC-LIV-03 | Produit vêtement | Créer ou modifier un vêtement | Les champs livre restent masqués et la taille continue de fonctionner |
| TC-LIV-04 | Accents | Enregistrer un auteur, une langue et un état avec des accents | Les caractères sont enregistrés et affichés correctement en UTF-8 |
| TC-LIV-05 | Responsive | Ouvrir l'accueil, le catalogue et une fiche livre sur ordinateur puis téléphone | Aucun contenu ne déborde et les actions restent accessibles |
| TC-LIV-06 | Livre sans image | Afficher un livre qui ne possède aucune photo | Un symbole de livre remplace l'image absente |

## Traçabilité

| Besoin | User Story | Critères | Tests associés |
|---|---|---|---|
| Proposer des livres avec une fiche adaptée | US-LIV-01 | CA 1 à 9 | TC-LIV-01 à TC-LIV-06 |

## Éléments livrés

- Migration : `update_v3_books.sql`
- Formulaire administrateur : `admin/produits.php`
- Catalogue : `catalogue.php`
- Fiche détaillée : `produit.php`
- Accueil : `index.php`
- Comportement dynamique : `assets/app.js`
- Présentation responsive : `assets/style.css`
