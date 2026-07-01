# 004. Bracket est l'unique point de mutation — pas de service séparé

Date: 2026-07-01
Status: Accepted

## Contexte

Pour implémenter la progression du bracket (enregistrer un résultat et propager le vainqueur), deux options ont été envisagées :

**Option A — service dédié :** un `BracketProgressor` (ou use case applicatif) reçoit le `Bracket` et orchestre la mutation.

**Option B — méthode sur l'agrégat :** `Bracket::recordResult()` est directement responsable de la mutation et de la propagation.

## Décision

**Option B adoptée.** `Bracket` est l'**Aggregate Root** et porte la logique de progression.

En DDD, la logique qui maintient les invariants d'un agrégat doit vivre dans l'agrégat lui-même. Externaliser cette logique dans un service produit un **modèle anémique** : l'agrégat devient un sac de données, et les règles métier se dispersent dans des services sans cohésion.

`Bracket::recordResult(EncounterId, EncounterResult): void` :
1. Localise l'encounter dans les rounds via `Round::findEncounterById()`
2. Enregistre le résultat via `Encounter::play()`
3. Propage le vainqueur dans le round suivant via `Round::resolveParticipant()`

La délégation aux méthodes de `Round` évite que `Bracket` ne connaisse les détails internes d'un `Round` — chaque objet gère ce qui le concerne.

## Conséquences

- Aucun `BracketProgressor`, `BracketService` ou use case applicatif n'est nécessaire pour la mutation de base
- La couche application (future) appellera simplement `$bracket->recordResult(...)` puis persistera l'agrégat
- Les invariants du bracket (pas deux fois le même résultat, participants connus avant de jouer) sont gardés par `Encounter::play()` — au plus près de la donnée