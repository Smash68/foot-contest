# 034. Query `GetBracket` : DTO de lecture dédié, Assembler injecté, sous-ressource HTTP

Date: 2026-09-20
Status: Accepted

## Contexte

Priorité 7 (gestion de la compétition en cours) ouvre le chantier de consultation du bracket — premier vrai use case de lecture du projet consommé par un appelant externe : la seule Query existante (`IsOrganizerOwnerOfOrganization`, ADR 029) est interne à l'ACL entre modules, jamais exposée en HTTP.

Trois questions de conception, couplées, devaient être tranchées avant d'implémenter :

1. Que doit retourner `GetBracketHandler` — le `Bracket` du domaine directement, ou une structure dédiée à la lecture ?
2. Si une structure dédiée, où vit le mapping Domain → structure de lecture ?
3. Comment le contrôleur HTTP doit-il réagir quand la compétition existe mais que le bracket n'est pas encore généré ?

## Décision

### 1. Un DTO de lecture dédié (`BracketView` et sa hiérarchie), pas le `Bracket` du domaine

`GetBracketHandler` retourne un `?BracketView` (Application), jamais l'interface `Bracket` du domaine. Alternative sérieusement envisagée puis rejetée : retourner `Bracket` directement, par mirroir du patron déjà admis pour les Commands (ADR 008, `CreateCompetitionHandler` retourne un `CompetitionId`). Rejetée car la justification d'ADR 008 ne s'applique pas ici : ADR 008 répond à une contrainte technique réelle (un appelant HTTP synchrone a besoin d'un id généré, aucun autre moyen de l'obtenir) ; aucune contrainte équivalente n'empêche de construire un DTO de lecture pour une Query. Surtout, `Bracket` expose `recordResult()` — une méthode de mutation — la retourner depuis une Query romprait la séparation lecture/écriture du CQRS en offrant, même sans appelant aujourd'hui, un chemin de lecture vers le comportement d'écriture de l'agrégat.

`BracketView`/`RoundView`/`EncounterView`/`ParticipantView`/`EncounterResultView`/`ScoreView` vivent sous `Application/GetBracket/View/` (sous-dossier dédié au sein du use case, même logique de regroupement que `Domain/Format/SingleElimination/`) : ce sont des `readonly class` sans comportement, sans dépendance vers `Domain`.

### 2. Mapping isolé dans un `BracketViewAssembler` injecté, pas de `fromDomain()` sur les View

Le mapping Domain → View est concentré dans `BracketViewAssembler::toView(Bracket): BracketView` (`Application/GetBracket/`), injecté au constructeur de `GetBracketHandler`. Deux alternatives rejetées :

- **Méthode `fromDomain()` sur chaque classe de vue** : aurait forcé les 6 classes de vue à importer leur pendant `Domain\Model` et à connaître son API interne, dispersant le couplage domaine dans 6 fichiers au lieu d'un seul, et leur aurait donné deux raisons de changer (leur propre contrat de lecture, et l'évolution du modèle du domaine) — l'inverse de l'objectif SRP recherché.
- **`BracketViewAssembler` en méthode statique** : rejetée par cohérence avec le reste de la couche Application, entièrement construite par injection de dépendances (statique réservé aux named constructors de VOs du Domain, jamais à un composant applicatif) ; et pour anticiper un besoin déjà identifié mais hors périmètre de cet incrément — enrichir la vue avec le nom des équipes (seuls les `TeamId` sont exposés aujourd'hui) nécessitera une dépendance sur l'Assembler, un ajout de paramètre constructeur plutôt qu'un refactor complet de son API.

Sérialisation JSON : chaque classe de vue implémente `\JsonSerializable` plutôt que de laisser le contrôleur reconstruire l'arbre à la main — la profondeur de nesting (rounds → encounters → participants/résultat → scores) rendrait cette reconstruction manuelle une pure duplication de ce que la vue encode déjà. Le contrôleur reste une pure sérialisation, sans connaître la forme interne du domaine ni de la vue.

### 3. `GET /competitions/{id}/bracket` renvoie 404 si le bracket n'est pas encore généré

Compétition inconnue → `InvalidArgumentException` (mirroir des autres Handlers), déjà mappée en 422 par le listener existant (ADR 015). Compétition existante mais bracket `null` → 404, le bracket étant traité comme une sous-ressource à part entière pas encore créée, pas comme un état à signaler dans un corps 200. Alternative rejetée : toujours répondre 200 avec un corps `{"generated": false}` — écartée au profit de la sémantique REST standard d'une sous-ressource absente.

## Conséquences

- Patron établi pour les futures Queries de Priorité 7 (consultation des scores/rounds) : Handler retourne un DTO de lecture Application, mapping concentré dans un Assembler injecté, jamais de retour direct d'un objet du Domain ni de logique de traduction dispersée sur les DTOs.
- `BracketViewAssembler` est le point d'extension naturel si un enrichissement (nom des équipes, etc.) est requis plus tard — ajout de dépendance, pas de refactor de contrat.
- Premier endpoint HTTP en lecture du projet (`GET`) ; les conventions HTTP existantes (ADR 015 422, ADR 031 403, `LogicExceptionListener` 409) restent inchangées, 404 s'ajoute comme un nouveau cas propre aux sous-ressources absentes.