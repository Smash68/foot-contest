# Foot Contest — Roadmap

Vision produit et acteurs : [`README.md`](README.md). Modèle de domaine et conventions : [`CLAUDE.md`](CLAUDE.md). Raisonnement derrière chaque décision structurante : [`docs/adr/`](docs/adr/).

## Priorité 1 — Avancement du bracket (suite) ✅ implémenté

#### 1a — Détection de fin de tournoi ✅ implémenté

Quand la finale est jouée (`recordResult()` sur le dernier encounter), le bracket est terminé. Exposé via `Bracket::isComplete(): bool` et `Bracket::getChampion(): Team`.

#### 1b — Score enrichi ✅ implémenté

Voir ADR 005 (`EncounterResult` neutre, `Score` VO, `afterExtraTime()`, `afterPenalties()`).

## Priorité 2 — Match pour la 3e place ✅ implémenté

Optionnel, activé par composition (`BracketGeneratorWithThirdPlaceMatch` / `BracketWithThirdPlaceMatch`) — voir ADR 006.

## Priorité 3 — Inscription au tournoi ✦ prochaine étape

- Création d'un tournoi par un organisateur (format, min/max équipes, règles)
- Inscription d'une équipe par un capitaine
- Déclenchement de la génération du bracket quand le nombre d'équipes est atteint

## Priorité 4 — Autres formats de tournoi

- Double élimination
- Phase de poules + élimination directe
- Round-robin / championnat

## Priorité 5 — Infrastructure

- Persistance (base de données)
- API REST ou GraphQL
- Multi-tenancy
- Front (Vue 3 ou Twig — à décider)