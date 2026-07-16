# 009. Bootstrap de l'infrastructure Symfony via Flex, pas de squelette fait main

Date: 2026-07-16
Status: Accepted

## Contexte

Le projet ne disposait d'aucun squelette HTTP Symfony (pas de `config/`, `public/index.php`, `src/Kernel.php`, `bin/console`) malgré `symfony/framework-bundle` en dépendance. L'introduction du premier contrôleur (`POST /competitions`, voir ADR 008) imposait de le construire.

## Décision

### 1. `symfony/flex` et ses recipes officielles, pas un squelette assemblé à la main

Un premier squelette a été écrit à la main (Kernel, `config/bundles.php`, `config/services.yaml`, `public/index.php`) avant d'être entièrement repris. Problèmes identifiés a posteriori : secret d'application codé en dur au lieu de `%env(APP_SECRET)%` via `.env`, `public/index.php` instanciant le Kernel manuellement au lieu de `symfony/runtime`, `config/services.yaml` listant les services un par un au lieu de l'auto-discovery PSR-4 standard. Décision : installer `symfony/flex` et rejouer les recipes officielles (`composer recipes:install --reset --force`) pour obtenir exactement le squelette qu'un projet Symfony standard aurait, plutôt que d'improviser des raccourcis pour éviter d'ajouter des dépendances.

Conséquence directe : `.env`/`.env.dev`/`.env.test`, `bin/console`, `bin/phpunit`, `public/index.php` (via `symfony/runtime`), `config/packages/{framework,messenger,cache,routing}.yaml`, `symfony.lock` proviennent tous des recipes, pas d'un assemblage manuel.

### 2. `config/services.yaml` : auto-discovery scopée par module, avec exclusion de tout `Domain/`

Le recipe standard de `symfony/framework-bundle` propose `App\: resource: '../src/'` sans exclusion. Deux adaptations retenues pour ce projet, structuré par contexte métier (`App\Competition\*`) :

- Le resource est scopé à `App\Competition\: resource: '../src/Competition/*'` plutôt qu'au namespace racine `App\` — cohérent avec la convention déjà en place (un namespace par module métier) et évite d'avoir à exclure `src/Kernel.php` explicitement à chaque module.
- Tout `Domain/` (`Model`, `Service`, `Format`) est exclu de l'auto-discovery : aucune classe du Domain n'est censée être un service DI (Value Objects et Entities n'ont pas vocation à être injectés). **Précision testée empiriquement** : Symfony ne casse pas la compilation du container même sans cette exclusion (l'autowiring n'est validé que pour les services réellement référencés, pas pour l'ensemble des définitions enregistrées) — l'exclusion est appliquée par hygiène architecturale (le Domain ne doit pas être vu comme des services DI, cohérent avec ADR 004), pas par nécessité technique.

### 3. `phpunit.xml` devient `phpunit.dist.xml` (convention Flex)

Le fichier de configuration PHPUnit tracké en Git passe de `phpunit.xml` à `phpunit.dist.xml`, conformément à la recipe `phpunit/phpunit` de Flex. Chaque poste de développement conserve sa propre copie locale `phpunit.xml` (gitignorée), nécessaire notamment pour la détection automatique par certains IDE. Le nom du testsuite (`Competition`) est préservé lors de la migration.

### 4. Le contrôleur vit sous `Infrastructure/Http/` du module, pas `src/Controller/`

Le placeholder Flex `src/Controller/` a été supprimé. L'auto-découverte des routes attribute-based (`#[Route]`) de Symfony repose sur le tag de service du contrôleur, pas sur un dossier fixe (`AttributeServicesLoader` itère les classes taguées, quel que soit leur emplacement) — le contrôleur peut donc vivre sous `src/Competition/Infrastructure/Http/`, cohérent avec le rangement des autres adapters (`Infrastructure/Persistence/...`), sans configuration de routing supplémentaire.

### 5. `#[MapRequestPayload]` + DTO Infrastructure pour sécuriser la forme du payload HTTP

Le contrôleur désérialise le corps JSON via un DTO dédié (`CreateCompetitionRequest`, sous `Infrastructure/Http/`) et l'attribut `#[MapRequestPayload]`, plutôt qu'un `json_decode()` manuel avec accès tableau non typé. Nécessite `symfony/serializer` (+ `symfony/property-access` et `symfony/property-info` pour la dénormalisation d'objets). Ce DTO valide uniquement la *forme* du payload (types) ; les règles métier (`min >= 2`, `min <= max`) restent portées par `TeamCapacity::of()` côté Domain — pas de duplication de validation entre les deux couches.

## Conséquences

- Toute nouvelle dépendance Composer nécessaire à une fonctionnalité standard (validation, persistance, etc.) doit passer par Flex quand une recipe existe, plutôt que par une configuration manuelle
- Un futur module métier (au-delà de `Competition`) ajoutera son propre bloc `App\NomDuModule\: resource: ... exclude: [Domain]` dans `config/services.yaml`, suivant le même schéma
- Les contrôleurs de futurs modules suivent la même convention de placement (`Infrastructure/Http/` du module concerné)
- `symfony/uid`, `symfony/serializer`, `symfony/property-access`, `symfony/property-info`, `symfony/messenger`, `symfony/yaml`, `symfony/runtime`, `symfony/dotenv` sont désormais des dépendances du projet ; `symfony/browser-kit` en dépendance de dev (tests fonctionnels `WebTestCase`)