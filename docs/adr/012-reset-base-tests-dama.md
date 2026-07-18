# 012. Reset de la base de données entre chaque test : `DAMADoctrineTestBundle`

Date: 2026-07-18
Status: Accepted

## Contexte

`DoctrineCompetitionRepositoryTest` (et les futurs smoke tests e2e, priorité 5b du ROADMAP) touchent une vraie base PostgreSQL. Sans mécanisme de reset, l'état créé par un test peut fuiter vers le suivant — dépendance à l'ordre d'exécution, faux positifs/négatifs difficiles à diagnostiquer. Il faut un reset automatique et fiable entre chaque test qui touche la base, sans alourdir chaque test d'un `setUp()`/`tearDown()` manuel (truncate, delete).

## Décision

`dama/doctrine-test-bundle` (v8) plutôt qu'un nettoyage manuel par test. Le bundle wrappe chaque test dans une transaction ouverte avant le test et annulée (`ROLLBACK`) après, via un hook sur le cycle de vie PHPUnit — aucun code à écrire dans les tests eux-mêmes.

Deux enregistrements nécessaires (la recipe Symfony contrib a été ignorée par Flex, configuration faite à la main) :
- `config/bundles.php` : bundle activé uniquement en env `test` (`['test' => true]`)
- `phpunit.dist.xml` : extension `DAMA\DoctrineTestBundle\PHPUnit\PHPUnitExtension` ajoutée à côté de `SymfonyExtension`

Alternative rejetée : truncate/delete manuel dans un `tearDown()` commun. Écarté parce que ça demande de maintenir une liste de tables à vider à chaque nouvelle entité mappée, et parce que le pattern transaction+rollback est plus rapide (pas de round-trip DDL/DML de nettoyage, juste un `ROLLBACK`).

## Conséquences

- Le wrapping ne s'active que sur les tests qui bootent réellement une connexion Doctrine (`KernelTestCase`/`WebTestCase`) — les tests de `Domain/` (`PHPUnit\Framework\TestCase` pur, pas de kernel) restent totalement inaffectés, aucune connexion n'existe à wrapper.
- Vérifié en conditions réelles : le compte de lignes de la table `competition` en base `app_test` est strictement identique avant et après un run complet de `DoctrineCompetitionRepositoryTest`.
- Les futurs smoke tests e2e (priorité 5b, `WebTestCase` contre la stack Doctrine réelle) bénéficient du même reset automatique sans configuration supplémentaire.