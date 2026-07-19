# 022. `Bracket` référence les équipes par `TeamId` ; `Team` absorbe `Registration`

Date: 2026-07-19
Status: Accepted

## Contexte

Priorité 5b (voir ROADMAP) : après le mapping Doctrine de `Competition` (id/name/capacity/closed, voir ADR 010), il reste à mapper `registrations`. Ce chantier, en apparence un simple mapping technique, a fait remonter plusieurs questions de modélisation couplées entre elles — traitées ici ensemble plutôt qu'en plusieurs ADR séquentiels, précisément parce qu'elles se sont révélées coupler (une réponse à l'une changeait la réponse à une autre).

**1. `Player` doit-il être retrouvable indépendamment d'une compétition ?**

Oui : un joueur parcourt la liste des compétitions, peut créer une équipe (il en devient capitaine) ou demander à rejoindre une équipe déjà inscrite à la même compétition (validation du capitaine requise). Il existe indépendamment de toute compétition particulière, en traverse plusieurs dans le temps — c'est un agrégat à part entière.

**2. `Team` doit-elle être retrouvable indépendamment d'une compétition, comme `Player` ?**

Non. Le scénario concret (tournoi inter-entreprise) montre qu'une équipe **ne vit que pour la compétition** à laquelle elle est inscrite : l'année suivante, une entreprise peut aligner une équipe complètement différente. `Team` n'a pas de cycle de vie propre indépendant de `Competition` — elle ne mérite ni repository ni table dédiés.

**3. `Bracket` a-t-il besoin de la `Team` complète (avec son nom), ou seulement de son identité ?**

Vérifié dans le code existant : **aucun** site, en production comme en test, ne lit jamais `Team::getName()` à travers le sous-système `Bracket` (`Participant`, `Encounter`, `Round`, `SingleEliminationBracket`, `BracketGenerator`) — seul `getId()` est utilisé sur les `Team` qu'il retourne (`getChampion()`, `getWinner()`, `getLoser()`, `getTeam()`). `Bracket` et `Competition` sont deux agrégats délibérément séparés (ADR 007 §1, pas de fusion) ; un agrégat qui référence un concept d'un autre contexte devrait le faire par id, jamais par valeur complète — la même règle déjà appliquée à `Player`. `Bracket` ne connaît donc désormais que `TeamId`, jamais `Team`.

**4. Une fois `Bracket` détaché de `Team`, `Registration` a-t-elle encore une utilité ?**

Non. Une fois que `Team` n'a plus qu'un seul consommateur (l'inscription à une compétition), elle et `Registration` (VO introduit par ADR 007 associant `Team` + `Player` capitaine) deviennent le même concept : par la définition métier elle-même ("une équipe est une combinaison de joueurs, dont un capitaine, qui s'inscrit pour une compétition"), une *Team* **est** son inscription — ce ne sont pas deux mots pour deux choses différentes. `Registration` disparaît ; `Team` porte directement `name`, `captainId: PlayerId` (référence, jamais un `Player` complet — même règle qu'au point 1) et `roster: PlayerId[]`.

Une piste intermédiaire (faire porter `captainId`/`roster` par `Team` **sans** détacher `Bracket`) a été explorée puis écartée : les 35 sites de `Team` dans `Bracket` auraient dû fournir un capitaine sans aucun sens pour eux. C'est la résolution du point 3 qui débloque le point 4.

## Décision

### 1. `Player` devient un agrégat indépendant, persisté

Même patron que `Competition` (ADR 010) : `PlayerIdType` dédié (Doctrine `Type` custom), `PlayerId` implémente `Stringable` (requis pour tout VO identifiant Doctrine), mapping XML `id`/`name`, `PlayerRepository` (port) + `DoctrinePlayerRepository` branchés en service. Pas de `nextIdentity()` sur le port : `PlayerId` est une clé métier (l'email, voir ADR 007 §3), pas un id technique généré — contrairement à `CompetitionId`.

### 2. `Bracket` référence les équipes par `TeamId`, jamais par `Team`

`Participant::forTeam(TeamId)`, `Participant::getTeamId(): TeamId` (renommé depuis `getTeam()` — le type retourné change de nature, le nom doit le signaler), `Encounter::getWinner()/getLoser(): TeamId`, `Round::resolveParticipant(EncounterId, TeamId)`, `Bracket::getChampion(): TeamId`, `BracketGenerator::generate(TeamId[] $teamIds): Bracket`. Les noms `getWinner()`/`getLoser()`/`getChampion()` restent inchangés malgré le type de retour : le sens ("qui a gagné") reste correct.

Conséquence assumée : si un jour un bracket doit s'afficher avec les noms d'équipes, ce sera une lecture qui va chercher le nom ailleurs (`Competition`), pas une donnée portée par l'agrégat `Bracket` lui-même.

### 3. `Team` absorbe `Registration`, reste une entité interne à `Competition` sans repository

`Team` devient `{ id: TeamId, name: string, captainId: PlayerId, roster: PlayerId[] }`. `roster` est initialisé à `[captainId]` à la création ; le flux "rejoindre une équipe" (ajout au roster, validation par le capitaine) reste un futur incrément, non conçu ici. `Registration` (classe) est supprimée.

Sur `Competition`, les noms de méthodes publiques (`register`, `withdraw`, `countRegistrations`, `isOpenForRegistration`, `closeRegistration`) restent inchangés — ce sont des verbes décrivant une action ("inscrire une équipe"), valides même si l'objet stocké est désormais directement `Team`.

### 4. Conséquence pour le mapping de `registrations`

`Team` (nouvelle forme) est désormais entièrement auto-suffisante — scalaires + `PlayerId[]`, aucune référence vers `Bracket` ni vers un objet `Player` complet. La collection `Competition.registrations` se sérialise donc en JSON sans dépendance à un repository externe au moment de l'hydratation Doctrine (`Type` custom, sans état, suffit). La résolution des noms de joueurs à partir de `PlayerId` (ex: afficher le roster complet avec les noms) devient une préoccupation de lecture/affichage, pas une contrainte du mapping d'écriture de l'agrégat.

### 5. Hors périmètre, explicitement reporté

Les règles métier de cohérence entre équipes d'une même compétition (unicité du nom d'équipe, un joueur ne peut appartenir qu'à une seule `Team` par `Competition`) ne sont pas traitées ici. Elles n'ont aucun impact sur la forme retenue ci-dessus (validations pures sur `Competition`, aucune conséquence sur le mapping Doctrine) — un incrément dédié suivra, avec son propre ADR si nécessaire.

## Conséquences

- `Registration` (classe) et ses tests sont supprimés ; tous les appelants sont mis à jour pour construire/manipuler `Team` directement.
- Le sous-système `Bracket` (interfaces et implémentations) et l'intégralité de ses tests sont mis à jour pour manipuler `TeamId` au lieu de `Team`.
- Corrige ADR 007 §2 sur ce point précis (le reste de l'ADR 007 — `Competition` agrégat séparé de `Bracket`, `PlayerId` = email, `TeamCapacity`, nommage — reste valide).
- `Player` suit le patron déjà établi pour `Competition` (ADR 010) : futur module suit le même schéma pour tout VO identifiant Doctrine.
- ROADMAP.md est mis à jour : la persistance de `Player` en agrégat indépendant est extraite de la Priorité 6 vers la Priorité 5b ; `Team` n'y a jamais eu sa place ; `registrations` se mappe sans obstacle technique restant.
- Les règles de cohérence inter-équipes (nom unique, un joueur une équipe) sont un incrément séparé, volontairement non traité ici.