# 014. Smoke test e2e : un seul happy path, surcharge locale du repository

Date: 2026-07-18
Status: Accepted

## Contexte

Aucun test n'exerçait jusqu'ici la stack complète (HTTP → Messenger → Handler → Doctrine → PostgreSQL réelle) : `CreateCompetitionControllerTest` utilise volontairement l'InMemory (ADR 013), `DoctrineCompetitionRepositoryTest` teste le mapping en isolation. Un test e2e reste utile pour détecter une rupture de câblage entre ces couches (ex. config Doctrine cassée, alias mal branché) qu'aucun des deux tests existants ne peut voir.

## Décision

### 1. Un seul test, le happy path uniquement

`tests/Competition/E2E/CreateCompetitionEndToEndTest.php` ne couvre que la création réussie d'une compétition. Les tests coûteux (base réelle via Docker) ont un ROI décroissant au-delà de la preuve que le câblage fonctionne : les codes d'erreur HTTP (validation, etc.) se déclenchent avant que la persistance soit atteinte, donc les re-tester contre une vraie base n'apporte aucun signal supplémentaire — leur place est dans `CreateCompetitionControllerTest` (rapide, InMemory).

### 2. Surcharge du service `CompetitionRepository` localement au test, pas globalement

Le test récupère `EntityManagerInterface` depuis `self::getContainer()` (accessible grâce à `framework.test: true`, déjà actif), construit une vraie `DoctrineCompetitionRepository`, et l'injecte via `self::getContainer()->set(CompetitionRepository::class, ...)` avant la requête HTTP — plutôt que de modifier `services_test.yaml` (qui resterait sur l'InMemory pour tous les autres tests, dont `CreateCompetitionControllerTest`). C'est exactement l'échappatoire anticipée par l'ADR 013.

### 3. Assertion sur l'état réellement persisté, pas seulement le code HTTP

Le test relit la compétition via `DoctrineCompetitionRepository::ofId()` après la requête, et vérifie son nom — un simple code 201 ne prouverait pas que la donnée a traversé Doctrine jusqu'à PostgreSQL, seulement que le contrôleur a répondu.

## Conséquences

- Vérifié en conditions réelles : `SELECT COUNT(*) FROM competition` sur `app_test` reste à 0 avant/après ce test — le rollback automatique (ADR 012) s'applique aussi à ce test malgré la surcharge locale du service.
- Reste à vérifier que `CreateCompetitionControllerTest` couvre bien les autres codes de réponse HTTP de la route (validation du payload, etc.) — pas encore audité.