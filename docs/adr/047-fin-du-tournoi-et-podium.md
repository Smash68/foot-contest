# 047. Fin du tournoi : action manuelle de l'organisateur, podium porté par le tableau

Date: 2026-11-19
Status: Accepted

## Contexte

Une fois toutes les rencontres jouées, l'organisateur doit pouvoir proclamer les résultats : le champion, le finaliste et, si le match pour la 3e place est prévu, le troisième. Un tournoi terminé n'accepte plus aucun résultat.

Plusieurs questions couplées devaient être tranchées ensemble :

- **Le déclenchement de la fin** : automatique à la saisie de la dernière rencontre, ou action explicite de l'organisateur.
- **L'interaction avec la correction d'un résultat** ([ADR 045](045-correction-resultat-rencontre.md)) : une correction reste-t-elle possible après la fin ?
- **Le calcul du podium** : par le use case, par `Competition` ou par le tableau.
- **La complétude d'un tableau avec match pour la 3e place** : `BracketWithThirdPlaceMatch::isComplete()` délègue au tableau enveloppé, et considère donc le tableau complet dès la finale jouée, même si le match pour la 3e place ne l'est pas.

## Décision

### 1. La fin du tournoi est une action manuelle de l'organisateur

`Competition::finish()` termine le tournoi. L'action est refusée tant que le tableau n'est pas complet (`\LogicException`), et ne peut être répétée.

Dans la continuité de l'[ADR 007](007-inscription-competition-agregat.md), où la clôture des inscriptions et la génération du tableau sont deux actions distinctes de l'organisateur, la fin du tournoi n'est pas déduite de l'état du tableau : l'organisateur vérifie les résultats saisis avant de les proclamer.

Alternative écartée : une fin automatique à la saisie de la dernière rencontre. Elle impose un choix entre deux défauts : soit le tournoi est figé dès cette saisie, et une erreur sur le score de la dernière rencontre ne peut plus être corrigée ; soit la correction reste possible, et les résultats proclamés peuvent encore changer.

### 2. Un tournoi terminé n'accepte ni saisie ni correction

`Competition::recordResult()` et `Competition::correctResult()` refusent d'agir sur un tournoi terminé (`\LogicException`). La règle est portée par `Competition`, point d'entrée de la saisie et de la correction ([ADR 044](044-saisie-resultat-rencontre.md)), et non par le tableau : être terminé est un état du tournoi, pas du tableau.

### 3. Le podium est calculé par le tableau

Le contrat `Bracket` expose `getPodium(): Podium`, un Value Object portant le champion, le finaliste et un troisième optionnel. Comme pour les résultats acceptables (ADR 044) et les conséquences d'une correction (ADR 045), le classement final dépend du format : chaque implémentation de `Bracket` en décide.

- **`SingleEliminationBracket`** désigne le vainqueur et le perdant de la finale ; il n'y a pas de troisième.
- **`BracketWithThirdPlaceMatch`** y ajoute le vainqueur du match pour la 3e place ([ADR 006](006-match-3e-place-bracket-interface-et-decoration.md)).

Sans match pour la 3e place, le podium ne compte pas de troisième : les deux perdants des demi-finales ne sont pas départagés.

Le podium n'est consultable qu'une fois le tournoi terminé : c'est la proclamation des résultats, pas une lecture provisoire du tableau. Cette règle étant un état du tournoi, la consultation passe par `Competition::getPodium()`, qui délègue au tableau. La couche Application n'appelle jamais `getBracket()->getPodium()` : le tableau est propre à l'élimination directe ([ADR 007](007-inscription-competition-agregat.md)), et un futur championnat calculera son podium à partir d'une autre structure sans changer ce point d'entrée.

Le champion, le deuxième et le troisième désignent des places, pas des issues de rencontre : en élimination directe, le deuxième est le finaliste ; en championnat, ce sera le deuxième du classement.

Alternative écartée : calculer le podium dans le use case de consultation. Il devrait connaître la structure de chaque format, ce que l'ADR 044 réserve au tableau.

### 4. Un tableau avec match pour la 3e place n'est complet qu'une fois ce match joué

`BracketWithThirdPlaceMatch::isComplete()` exige que le tableau enveloppé soit complet **et** que le match pour la 3e place soit joué. Le tableau n'est complet que lorsque toutes ses rencontres le sont.

## Conséquences

- `Competition` gagne un état « terminé », persisté : une migration ajoute la colonne correspondante.
- Le podium n'est pas persisté : il se recalcule à partir du tableau, figé une fois le tournoi terminé.
- La consultation du tableau (`GetBracket`) ne signale plus un tableau complet tant que le match pour la 3e place reste à jouer.
- Un futur format (championnat) calculera son podium à partir de son classement, sans toucher aux use cases.
