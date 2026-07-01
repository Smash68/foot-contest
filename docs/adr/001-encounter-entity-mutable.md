# 001. Encounter est une Entity mutable, pas un Value Object

Date: 2026-07-01
Status: Accepted

## Contexte

Lors de la modélisation du bracket, `Encounter` a d'abord été implémenté en `readonly class` avec propriétés publiques — traité comme un Value Object interne à l'agrégat.

Le problème est apparu lors de l'implémentation de `Bracket::recordResult()` : pour propager le vainqueur d'un encounter vers le round suivant, il fallait reconstruire entièrement tous les rounds et encounters, même ceux non impactés. L'algo était naïf, coûteux, et surtout le bracket ne conservait aucune trace des résultats joués.

## Décision

`Encounter` est une **Entity** : il a une identité (`EncounterId`), et son état évolue dans le temps (`?EncounterResult`).

Il devient une classe mutable avec un cycle de vie explicite :

- `play(EncounterResult)` — enregistre le score (guards : participants connus, pas déjà joué)
- `resolveHome(Participant)` / `resolveAway(Participant)` — résout un participant en attente
- `getWinner(): Team` — expose le vainqueur une fois joué
- `isCompleted(): bool` — état du cycle de vie

`Bracket::recordResult()` exploite les références PHP : muter l'`Encounter` trouvé propage le changement automatiquement dans le `Round[]` sans reconstruction.

## Conséquences

- Le bracket conserve l'historique complet des résultats (chaque `Encounter` porte son `EncounterResult`)
- `Bracket::isComplete()` peut s'implémenter trivialement en vérifiant `Encounter::isCompleted()` sur tous les encounters
- `Round` et `Participant` restent des Value Objects `readonly` — seuls `Bracket` et `Encounter` sont mutables
- Les getters (`getHome()`, `getAway()`) remplacent les propriétés publiques de la version readonly