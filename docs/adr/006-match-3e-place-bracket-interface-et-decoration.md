# 006. Match pour la 3e place : Bracket devient une interface, décoration plutôt que flag, sous-classe ou 4e état

Date: 2026-07-14
Status: Accepted

## Contexte

Ajout d'une règle optionnelle du format élimination directe : le match pour la 3e place, opposant les deux perdants de demi-finale. Trois problèmes à résoudre ensemble :

1. Comment activer une règle optionnelle sans faire grossir indéfiniment le constructeur de `SingleEliminationBracket` (voir ADR 004) à chaque nouvelle règle future ?
2. Comment câbler le nouvel encounter aux deux demi-finales sources ?
3. Comment représenter "en attente du perdant d'une demi-finale" sans abîmer `Participant`, qui a délibérément 3 états (ADR 002) ?

## Décision

### 1. `Bracket` devient une interface

`Bracket` (`Model/Bracket.php`) passe de classe concrète à interface ; l'implémentation existante est renommée `SingleEliminationBracket`. Ça permet de **décorer** l'agrégat pour des règles optionnelles sans toucher à son constructeur.

### 2. La règle optionnelle s'active par décoration, pas par flag

```php
$generator = new BracketGeneratorWithThirdPlaceMatch(new SingleEliminationBracketGenerator());
$bracket = $generator->generate($teams); // instance de BracketWithThirdPlaceMatch
```

- `ThirdPlaceFixture` — VO de câblage : 3 `EncounterId` (le futur match + les deux demi-finales sources), aucune donnée de jeu
- `BracketGeneratorWithThirdPlaceMatch` décore `BracketGenerator` : repère le round avant la finale à la génération, valide l'éligibilité (exactement 2 encounters, aucun bye — sinon le perdant n'est pas déterminable), lève `InvalidArgumentException` sinon
- `BracketWithThirdPlaceMatch` décore `Bracket` : délègue toutes les méthodes du contrat à l'agrégat enveloppé ; expose `getThirdPlaceEncounter(): ?Encounter`

### 3. Aucun 4e état sur `Participant`

Les deux perdants de demi-finale ne sont représentés par aucun nouvel état (pas de `pendingLoserOf`, contrairement à `pendingWinnerOf`). `BracketWithThirdPlaceMatch` construit **paresseusement** l'`Encounter` de 3e place : rien n'existe tant que les deux demi-finales référencées ne sont pas `isCompleted()`. Une fois prêtes, l'encounter est créé directement avec deux `Participant::forTeam()` concrets, à partir des vrais perdants (`Encounter::getLoser()`).

## Pourquoi pas un flag booléen sur le générateur ?

`withThirdPlaceMatch: true` grossit indéfiniment le constructeur à chaque nouvelle règle optionnelle du format (double élimination, poules... auront chacune leurs propres options).

## Pourquoi pas un sous-classage (`BracketWithThirdPlaceMatch extends Bracket`) ?

L'héritage ne compose pas : dès que deux règles optionnelles doivent se cumuler, explosion combinatoire de classes (`WithThirdPlaceAndX`, `WithThirdPlaceAndY`...).

## Pourquoi pas un système d'extensions/hooks (liste de `BracketExtension` injectée dans `Bracket`) ?

Envisagé pour éviter le passe-plat des décorateurs (chaque décorateur doit redéléguer toutes les méthodes du contrat), mais écarté pour l'instant : une seule règle optionnelle existe aujourd'hui, construire ce mécanisme serait de la généralité spéculative. À réévaluer si une 2e règle apparaît et que le passe-plat devient réellement pénible.

## Pourquoi pas un 4e état sur `Participant` (`pendingLoserOf`) ?

Référencer les perdants de demi-finale via un état dédié aurait pollué un concept central déjà stable à 3 états (ADR 002), pour un besoin très localisé à une seule règle optionnelle.

## Conséquences

- La décoration compose : `new WithRegleA(new WithRegleB($bracket))` — un nombre de classes linéaire au nombre de règles optionnelles, jamais aux combinaisons
- `Bracket` (interface) devient le contrat stable ; toute implémentation ou tout décorateur doit le respecter en totalité
- `getThirdPlaceEncounter()` retourne `null` tant que les deux demi-finales ne sont pas jouées, l'`Encounter` réel une fois qu'elles le sont
- `Participant` reste à 3 états — aucune règle optionnelle future ne doit y ajouter un état sans une raison qui dépasse son propre besoin localisé
- Le système d'extensions/hooks reste une option pour plus tard, pas fermée définitivement