# 013. `InMemoryCompetitionRepository` pour les tests HTTP contrôleur

Date: 2026-07-18
Status: Accepted

## Contexte

`services.yaml` branche désormais `CompetitionRepository` sur `DoctrineCompetitionRepository` (voir ADR 010, ADR 012). Sans intervention, `CreateCompetitionControllerTest` (`WebTestCase`) passerait donc aussi par Doctrine/PostgreSQL au prochain `POST /competitions` testé — alors que son rôle est de vérifier le contrat HTTP (routing, validation du payload, code retour, forme de la réponse), pas la persistance. La couverture de la persistance existe déjà, exhaustivement, dans `DoctrineCompetitionRepositoryTest` (aller-retour réel contre PostgreSQL).

## Décision

`config/services_test.yaml` réécrit l'alias `CompetitionRepository` vers `InMemoryCompetitionRepository`, actif uniquement en env `test` (import automatique de `{services}_%kernel.environment%.yaml` par `KernelTrait::configureContainer`, aucune configuration Kernel supplémentaire requise).

Ce choix ne casse pas la couverture de `DoctrineCompetitionRepository` : `DoctrineCompetitionRepositoryTest` instancie ce repository directement (`new DoctrineCompetitionRepository($entityManager)`), sans passer par le service du container — il reste donc inchangé par cet override.

## Conséquences

- `CreateCompetitionControllerTest` reste rapide et indépendant de PostgreSQL, centré sur le contrat HTTP.
- Vérifié en conditions réelles : le nombre de lignes de `competition` en base `app_test` est identique avant/après l'exécution de ce test.
- Si un futur test HTTP a explicitement besoin de vérifier un état persisté réel (ex. un smoke test e2e, priorité 5b du ROADMAP), il devra soit s'appuyer sur `DoctrineCompetitionRepositoryTest` existant, soit surcharger explicitement ce service pour ce test précis plutôt que de modifier `services_test.yaml` globalement.
