# 018. PHPStan pour l'analyse statique, niveau max, baseline sur les tests

Date: 2026-07-18
Status: Accepted

## Contexte

Aucun outil d'analyse statique n'était en place. Besoin identifié en marge du travail sur la persistance, comme la CI (ADR 017) qui excluait explicitement l'analyse statique de son périmètre initial.

## Décision

### 1. PHPStan plutôt que Psalm

Standard de facto dans l'écosystème Symfony aujourd'hui. `phpstan/phpstan-symfony` et `phpstan/phpstan-doctrine` comprennent les services autowirés et les mappings ORM sans faux positifs artificiels.

### 2. Niveau `max` dès maintenant, pas un niveau intermédiaire

Testé directement au niveau le plus strict plutôt que de partir bas et monter progressivement — le codebase est encore petit, donc le coût d'adopter `max` tout de suite est faible comparé à devoir remonter les niveaux plus tard sur un code plus volumineux.

### 3. `src/` corrigé, `tests/` mis en baseline

Premier run à `max` : 49 erreurs. 6 dans `src/`, réelles mais mineures (frontières framework/Doctrine) :
- `CreateCompetitionController` : `HandledStamp|null` et le résultat `mixed` de `getResult()` accédés sans vérification — `assert($handledStamp instanceof HandledStamp)` / `assert($id instanceof CompetitionId)` ajoutés, narrowing statique **et** filet de sécurité runtime (compilé en no-op si `zend.assertions=-1` en prod).
- `CompetitionIdType` : `Doctrine\DBAL\Types\Type::convertToPHPValue()`/`convertToDatabaseValue()` déclarent `mixed $value` dans la classe parente — impossible de restreindre le type dans la signature de l'override (violerait la compatibilité de déclaration PHP), donc validation par `assert(is_string($value))` / `assert($value instanceof CompetitionId)` dans le corps de la méthode.
- `Kernel.php::getAllowedEnvs()` signalé comme méthode privée inutilisée : faux positif, PHPStan ne trace pas l'appel `$this->getAllowedEnvs()` fait depuis `KernelTrait::getKernelParameters()` sur une méthode privée qui surcharge celle du trait. Ignoré via une règle `ignoreErrors` ciblée (message + chemin précis) avec commentaire explicatif dans `phpstan.dist.neon`, plutôt qu'un ignore générique.

Les 43 erreurs restantes, toutes dans `tests/`, viennent de types génériques que PHPStan ne peut pas affiner sans annotations (`self::getContainer()->get()` retourne `object`, `BrowserKit::request()` et `json_decode()` retournent des unions larges) — pas de vrais bugs. `phpstan-baseline.neon` généré (`--generate-baseline`) : mécanisme officiel PHPStan pour adopter l'outil sans réécrire les tests existants dans cet incrément. La CI bloque dès maintenant toute **nouvelle** erreur, sans forcer un gros refactor immédiat des tests.

### 4. Script `composer stan`, intégré à la CI après l'installation des dépendances

`docker compose exec app composer stan` — cohérent avec `test:coverage` (ADR 016). Ajouté à `.github/workflows/ci.yml` juste après l'attente de `vendor/autoload.php`, avant la création de la base de test : PHPStan ne dépend pas de PostgreSQL, autant échouer vite sur une erreur de type avant de payer le coût des migrations.

## Conséquences

- `docker compose exec app composer stan` : 0 erreur (48 fichiers analysés, dont les 43 de la baseline).
- Toute nouvelle violation de type dans `src/` ou `tests/` fait échouer la CI immédiatement.
- Les 43 erreurs baselinées restent une dette documentée, pas cachée : visibles dans `phpstan-baseline.neon`, à résorber au fil de l'eau si les tests concernés sont retouchés (pas un chantier dédié pour l'instant).