# US-DON-02 — Donner des vêtements, des livres ou les deux

## Besoin

La plateforme collecte deux familles d'articles : les vêtements et les livres. Une même collecte peut contenir une seule famille ou les deux. Le contenu doit être identifié dès la demande afin que l'équipe puisse préparer le tri adapté.

## User Story

**En tant que donateur, je veux sélectionner les vêtements, les livres ou les deux dans un seul formulaire afin d'organiser une seule collecte.**

## Critères d'acceptation

1. La page « Faire un don » propose deux cases visibles : vêtements et livres.
2. Le donateur peut sélectionner vêtements seulement, livres seulement, ou les deux.
3. Au moins une case doit être cochée avant l'envoi.
4. Un seul formulaire enregistre une seule demande de collecte, y compris pour un don mixte.
5. Le libellé et l'exemple de description s'adaptent aux choix cochés.
6. Le type enregistré vaut `vetements`, `livres` ou `mixte`.
7. Le type apparaît dans l'espace du donateur, l'administration et les missions du livreur.
8. Le formulaire reste utilisable au clavier et sur téléphone.

## Cas de test manuels

| ID | Scénario | Résultat attendu |
|---|---|---|
| TC-DON-01 | Ouvrir le formulaire | La case vêtements est sélectionnée par défaut |
| TC-DON-02 | Sélectionner seulement « Livres » | Le libellé demande quels livres sont donnés |
| TC-DON-03 | Sélectionner vêtements et livres | Les deux cases restent actives et la description mentionne les deux familles |
| TC-DON-04 | Décocher les deux puis envoyer | Le formulaire est bloqué avec un message clair |
| TC-DON-05 | Envoyer un don mixte valide | Une seule demande est enregistrée avec le type `mixte` |
| TC-DON-06 | Consulter le don dans les trois espaces | Le badge « Vêtements et livres » est visible partout |
| TC-DON-07 | Utiliser un écran étroit ou le clavier | Les choix restent lisibles, sélectionnables et sans débordement |

## Traçabilité

| Besoin | User Story | Critères | Tests associés |
|---|---|---|---|
| Accepter vêtements, livres ou les deux dans une collecte | US-DON-02 | CA 1 à 8 | TC-DON-01 à TC-DON-07 |
