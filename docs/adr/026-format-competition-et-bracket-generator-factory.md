# 026. Format de compétition choisi à la création ; `BracketGeneratorFactory` injectée pour résoudre le générateur

Date: 2026-08-02
Status: Accepted

## Contexte

Priorité 5c (voir ROADMAP) : dernier use case CQRS restant, `generateBracket`. Aujourd'hui, `Competition::generateBracket(BracketGenerator $generator)` accepte n'importe quel générateur choisi par l'appelant au moment de l'appel — l'agrégat ne porte aucune trace de "pour quel format" il a été créé. Deux problèmes concrets soulevés en cadrage avant implémentation :

1. **Workflow métier** : dans un vrai outil de gestion de tournoi, l'organisateur choisit le format (coupe simple, championnat, poules...) dès la création de la compétition, pas au moment de générer le bracket — ça conditionne la communication aux équipes bien avant la clôture des inscriptions.
2. **Invariant non protégé** : si le Handler HTTP se contente de résoudre un `BracketGenerator` et de le passer à `Competition::generateBracket()`, rien côté Domain n'empêche un appelant de passer un générateur incohérent avec ce que la compétition était censée être (ex: générer un bracket "coupe simple" pour une compétition pensée comme "coupe avec 3e place"). Le Domain doit protéger cet invariant lui-même, pas compter sur la discipline de la couche Application.

Une première ébauche envisageait un enum `CompetitionFormat` portant directement une méthode `createGenerator()` (`match` exhaustif sur ses propres cases). Écartée en discussion : chaque nouveau format de Priorité 4 (double élimination, poules, round-robin — déjà actés au ROADMAP, pas hypothétiques) obligerait à modifier cette méthode, violation directe de l'Open/Closed Principle sur un axe de variation déjà connu à l'avance.

## Décision

### 1. `CompetitionFormat` — enum backé, pure donnée, sans comportement

Nouveau VO dans `Domain/Model/`, discriminant persisté du format structurel du tournoi. Un seul case aujourd'hui : `SingleElimination`. Ne porte aucune méthode de résolution vers un générateur (contrairement à l'ébauche initiale) — reste un simple VO au même titre que `Side`, pas un point d'extension à modifier à chaque nouveau format.

### 2. `includeThirdPlaceMatch: bool` — option orthogonale au format, pas un case d'enum supplémentaire

Le match pour la 3e place (ADR 006) n'est pas modélisé comme `SingleEliminationWithThirdPlace` (un 2e case d'enum). C'est une option orthogonale au format structurel : elle a vocation à s'appliquer à d'autres formats futurs (ex: poules + élimination directe) sans dupliquer les cases combinatoirement.

Ce booléen reste cohérent avec ADR 006 ("pas configuré par flag") : celle-ci rejetait un flag *dans l'algorithme de génération* (sur `Participant` ou dans `SingleEliminationBracketGenerator`). Ici, le booléen ne fait que sélectionner *quelle composition construire* en amont (décoration ou non) — il n'entre jamais dans la logique interne d'un générateur, qui reste ignorant de son propre usage décoré ou non.

### 3. `BracketConfiguration` — VO regroupant `format` et `includeThirdPlaceMatch`

`format` et `includeThirdPlaceMatch` ne sont pas deux paramètres indépendants sur `Competition::create()` : ils forment un *data clump* (Fowler) — toujours passés ensemble au seul endroit qui les consomme ensemble, `BracketGeneratorFactory::forFormat()`. Regroupés dans un VO dédié :

```php
final readonly class BracketConfiguration
{
    public function __construct(
        public CompetitionFormat $format,
        public bool $includeThirdPlaceMatch,
    ) {}
}
```

`Competition::create()` gagne un paramètre `BracketConfiguration $bracketConfiguration` requis (pas de valeur par défaut — l'application n'étant pas encore en production, aucune donnée existante à migrer), plutôt que deux paramètres primitifs. `getFormat()`/`includesThirdPlaceMatch()` restent deux accesseurs distincts sur `Competition` (délégant à `bracketConfiguration`) : le regroupement est un détail de construction/stockage interne, pas une raison de forcer les consommateurs de `Competition` (ex: futur payload HTTP) à naviguer un objet imbriqué pour deux valeurs scalaires.

Ce regroupement n'est volontairement pas justifié par une anticipation de Priorité 4 (formats futurs qui pourraient vouloir d'autres options) — le couplage entre `format` et `includeThirdPlaceMatch` est déjà observable aujourd'hui, indépendamment de tout format futur.

### 4. `BracketGeneratorFactory` — Domain/Service, map injectée plutôt que `match` codé en dur

```php
final class BracketGeneratorFactory
{
    /** @param array<string, BracketGenerator> $generatorsByFormat */
    public function __construct(private array $generatorsByFormat) {}

    public function forConfiguration(BracketConfiguration $configuration): BracketGenerator
    {
        $generator = $this->generatorsByFormat[$configuration->format->value]
            ?? throw new \LogicException("No generator registered for format '{$configuration->format->value}'.");

        return $configuration->includeThirdPlaceMatch
            ? new BracketGeneratorWithThirdPlaceMatch($generator)
            : $generator;
    }
}
```

La map `format => générateur` est fournie par la config DI (`services.yaml`), pas codée en dur dans la classe. Ajouter un format futur = une nouvelle classe générateur + une ligne de config ; la factory elle-même n'est jamais modifiée — c'est le respect effectif de l'OCP que l'ébauche à base d'enum n'offrait pas. La factory prend `BracketConfiguration` en entier (pas `CompetitionFormat`/`bool` séparés), cohérent avec le regroupement décidé au point 3.

### 5. `Competition::generateBracket(BracketGeneratorFactory $factory)` — résolution interne à l'agrégat

Remplace `generateBracket(BracketGenerator $generator)`. L'agrégat résout lui-même son générateur : `$factory->forConfiguration($this->bracketConfiguration)`. C'est le point qui protège l'invariant identifié en contexte — aucun appelant ne peut plus injecter un générateur incohérent avec le format/l'option déclarés à la création, puisque l'agrégat ne reçoit plus le générateur lui-même mais seulement la fabrique.

Alternative écartée : laisser le Handler résoudre le générateur via la factory et le passer tout fait à `Competition::generateBracket(BracketGenerator $generator)` (signature quasi inchangée par rapport à aujourd'hui). Rejetée : ne protège rien côté Domain, un appelant direct de l'agrégat (test, futur handler) pourrait toujours passer un générateur incohérent.

### 6. Câblage DI : exception ciblée à l'exclusion `Domain/` de ADR 009

`SingleEliminationBracketGenerator` vit sous `Domain/Format/SingleElimination/`, un chemin exclu du container par `services.yaml` (ADR 009, "pureté hexagonale"). Il est enregistré explicitement comme service, seule entrée de la map de `BracketGeneratorFactory` pour l'instant — même patron que les alias `CompetitionRepository`/`PlayerRepository`/`TeamRepository` déjà présents dans `services.yaml`, cette fois motivé par le besoin réel de la factory plutôt qu'ajouté par anticipation.

### 7. Impact rétroactif sur `CreateCompetition`

`CreateCompetitionCommand`, `CreateCompetitionRequest` (HTTP) et `CreateCompetitionHandler` gagnent une donnée `BracketConfiguration` (format + option 3e place). Ce use case était déjà livré bout en bout (Priorité 5a/5c) ; il est rouvert ici car le format est une donnée de création, pas de génération — le choix de le traiter maintenant plutôt qu'après coup évite un backfill de données une fois l'application en production.

### 8. Nouveau use case `GenerateBracket`

`GenerateBracketCommand` (DTO immuable, `competitionId`) / `GenerateBracketHandler`, même patron minimal que `CloseRegistration`/`Withdraw` : injecte `BracketGeneratorFactory` (autowired), délègue entièrement à `Competition::generateBracket($factory)` déjà responsable de ses propres règles métier (inscription close, bracket pas déjà généré — inchangées, déjà couvertes côté Domain). Le Handler ne teste à son niveau que ce qu'il possède en propre (compétition inconnue). Route `POST /competitions/{id}/generate-bracket`, même convention de verbe explicite que `close-registration` (ADR précédent, Google AIP-136).

## Conséquences

- `Competition::create()` a un paramètre requis de plus, `BracketConfiguration $bracketConfiguration` (regroupant `format` et `includeThirdPlaceMatch`) — tous les appelants existants (tests Domain, Application, Infrastructure) doivent être mis à jour.
- `Competition` expose toujours `getFormat()`/`includesThirdPlaceMatch()` en deux accesseurs distincts — le regroupement en `BracketConfiguration` reste un détail de construction interne, pas une fuite d'un objet imbriqué vers les consommateurs de `Competition`.
- `Competition::generateBracket()` change de type de paramètre (`BracketGenerator` → `BracketGeneratorFactory`) ; les tests qui construisaient un générateur concret pour l'appeler directement passent désormais par une factory (réelle ou construite ad hoc en test avec une map minimale).
- Nouvelle colonne `format` (et `include_third_place_match`) dans le mapping Doctrine de `Competition` (périmètre ouvert par ADR 010), migration à écrire.
- `SingleEliminationBracketGenerator` devient un service enregistré explicitement, cassant la règle générale "aucune classe de `Domain/` n'est un service" — exception documentée ici, pas une remise en cause d'ADR 009.
- Le discriminant `CompetitionFormat` ne généralise pas encore aux formats de Priorité 4 : `BracketGeneratorFactory` échoue explicitement (`\LogicException`) si un format sans générateur enregistré était atteint — cas qui ne peut pas encore se produire tant qu'un seul case existe, mais protège dès l'introduction d'un futur format oublié en config.
- `includeThirdPlaceMatch` reste un simple booléen dans `BracketConfiguration` ; si un futur format ne supporte structurellement pas l'option (ex: round-robin), la validation d'éligibilité déjà en place dans `BracketGeneratorWithThirdPlaceMatch` (exactement 2 encounters, aucun bye) continue de s'appliquer à la génération — pas de règle supplémentaire anticipée ici.