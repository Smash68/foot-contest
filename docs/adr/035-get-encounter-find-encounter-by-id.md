# 035. Query `GetEncounter` : `Bracket::findEncounterById()` nullable, pas de Null Object

Date: 2026-09-20
Status: Accepted

## Contexte

La fiche d'un match (`GetEncounter`, deuxième Query du projet après `GetBracket`/ADR 034) doit retrouver un `Encounter` précis à partir de son seul `EncounterId`, sans que l'appelant connaisse à l'avance son round. Cette recherche à travers tous les rounds existait déjà, mais dupliquée en interne à deux endroits (`SingleEliminationBracket::recordResult()`, `BracketWithThirdPlaceMatch::findEncounter()`), jamais exposée publiquement sur le contrat `Bracket`.

Deux questions à trancher avant d'implémenter :

1. Comment exposer cette recherche sur l'interface `Bracket` — et comment représenter l'absence de résultat ?
2. Une fois exposée, faut-il déduplier les deux recherches internes existantes pour la réutiliser ?

## Décision

### 1. `Bracket::findEncounterById(EncounterId): ?Encounter`, nullable — pas de Null Object

Alternative sérieusement envisagée puis rejetée : un Null Object (`NullEncounter`) plutôt qu'un retour nullable, pour limiter les `null` dans le Domain. Rejetée pour trois raisons :

- Le Null Object convient à un rôle avec un comportement "ne rien faire" sûr par défaut (ex: un logger nul). Ici, "encounter introuvable" n'a pas de comportement sûr à donner à `play()`/`getWinner()`/`getLoser()` — les appeler sur un résultat "introuvable" serait toujours un bug appelant, jamais un cas métier légitime à absorber silencieusement.
- `Encounter` est une `final class` concrète, pas conçue pour être sous-typée ; en faire une interface avec deux implémentations pour ce seul besoin aurait été disproportionné.
- C'est l'idiome déjà établi localement : `Round::findEncounterById()` (dont la logique remonte au niveau `Bracket`) retourne déjà `?Encounter`. Le préfixe `find` (par opposition à `get`, qui lève une exception sur `getRound(int)`) signale déjà dans ce codebase "peut ne rien trouver" — cohérent de garder la même sémantique en la faisant remonter d'un niveau plutôt que d'introduire un deuxième idiome pour représenter la même absence.

Implémentée dans `SingleEliminationBracket` (boucle sur les rounds) et `BracketWithThirdPlaceMatch` (vérifie l'encounter de 3e place synthétisé avant de déléguer à l'agrégat enveloppé, puisque celui-ci n'appartient à aucun round de l'agrégat interne).

### 2. Déduplication des deux recherches internes préexistantes, en commit séparé du `feat`

Une fois `findEncounterById()` public, `SingleEliminationBracket::recordResult()` et `BracketWithThirdPlaceMatch::findEncounter()` (privée) sont réécrites pour la réutiliser plutôt que de reboucler sur les rounds. `recordResult()` perd au passage son suivi d'index de round : la résolution du round suivant se fait en appelant `resolveParticipant()` sur tous les rounds (no-op partout sauf sur celui qui porte réellement un participant en attente de ce résultat) — comportement strictement équivalent, plus simple. Commité séparément du `feat` introduisant `GetEncounter`, pour que la déduplication ressorte clairement de l'historique plutôt que d'être noyée dans l'ajout de fonctionnalité qui l'a motivée.

### 3. Nouvel accesseur `Competition::getTeamName(TeamId): string`

Manquant jusqu'ici (seuls `getTeamCaptainId()`/`getTeamRoster()` existaient) ; nécessaire pour afficher le nom des équipes sur la fiche. Lecture pure, aucune nouvelle règle métier, même mirroir que les accesseurs existants.

## Conséquences

- `Bracket` gagne une méthode d'interface de plus (`findEncounterById()`), à implémenter par tout futur format de tournoi (double élimination, poules) — cohérent avec le reste du contrat, aucune n'y échappe déjà.
- `EncounterViewAssembler` (mirroir de `BracketViewAssembler`, ADR 034) confirme le point d'extension anticipé par ADR 034 : dépendance supplémentaire (`PlayerRepository`) pour résoudre le nom des joueurs, ajout de paramètre constructeur sans changement de contrat.
- Le principe "dédupliquer en commit séparé, une fois le besoin réel confirmé" devient le patron à suivre chaque fois qu'une nouvelle Query fait émerger une méthode déjà dupliquée en interne ailleurs dans le Domain.