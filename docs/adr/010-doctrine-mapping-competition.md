# 010. Mapping Doctrine de `Competition` : périmètre, VOs et organisation de la config

Date: 2026-07-17
Status: Accepted

## Contexte

Priorité 5b (voir ROADMAP) : remplacer `InMemoryCompetitionRepository` par une vraie persistance. `symfony/orm-pack` (Doctrine ORM + DBAL PostgreSQL + migrations) avait déjà été bootstrappé via Flex, avec `config/packages/doctrine.yaml` pointant vers un dossier de mapping XML pas encore peuplé. Mapper l'agrégat `Competition` soulève plusieurs décisions non triviales : comment mapper des Value Objects (`CompetitionId`, `TeamCapacity`) sur des colonnes relationnelles, quel périmètre couvrir dans un premier temps, et où faire vivre la config Doctrine à mesure que d'autres modules apparaîtront.

## Décision

### 1. Périmètre du premier mapping : `id`/`name`/`capacity`/`closed`, pas `registrations` ni `bracket`

Seuls les champs exercés par le use case existant (`CreateCompetition`) sont mappés. `registrations` (liste de `Registration` = `Team` + `Player`) demande une stratégie de collection de VOs ; `bracket` est une interface polymorphe (`SingleEliminationBracket` ou un décorateur l'enveloppant avec des `Round`/`Encounter`) qui ne correspond à aucun mécanisme direct de mapping d'héritage Doctrine. Ces deux champs restent des décisions à part entière, reportées à des incréments futurs plutôt que tranchées maintenant par convenance.

### 2. VOs identifiants : Doctrine Type dédié, pas des embeddables

`CompetitionId` est mappé via un `Doctrine\DBAL\Types\Type` custom (`CompetitionIdType`, une colonne `VARCHAR`) plutôt qu'un embeddable à une seule propriété — cohérent avec le fait que `TeamId`/`PlayerId` sont aussi des VOs qui enveloppent nûment une valeur scalaire. `TeamCapacity` (deux entiers `min`/`max`) est en revanche mappé comme un **embeddable Doctrine** (`<embeddable>` dans son propre fichier XML), la collection de deux colonnes correspondant naturellement à ce mécanisme.

### 3. `CompetitionId` doit implémenter `Stringable`

Doctrine calcule le hash d'identité d'une entité (`UnitOfWork::getIdHashByIdentifier()`) via `implode(' ', $identifier)` sur la valeur **brute** de la propriété identifiant — c'est-à-dire l'objet VO tel qu'il est sur l'entité, avant toute conversion via `Type::convertToDatabaseValue()`. `implode()` exige que chaque élément soit convertible en string. Conséquence : tout VO utilisé comme identifiant Doctrine (clé primaire) doit implémenter `Stringable`. C'est un ajout au VO lui-même (pas de dépendance à Doctrine — `Stringable` est une interface PHP native), pas une fuite de préoccupation infrastructure dans le Domain.

### 4. Config Doctrine scopée par module dans un fichier dédié, pas dans `doctrine.yaml`

`config/packages/doctrine.yaml` ne garde que le bootstrap global (connexion, `naming_strategy`, `auto_mapping`, blocs `when@test`/`when@prod`). Les `dbal.types` et `orm.mappings` propres à un module métier vivent dans un fichier plat séparé, `config/packages/doctrine_competition.yaml` — évite que `doctrine.yaml` devienne un fourre-tout à mesure que d'autres modules ajoutent leurs propres VOs identifiants et mappings. Reste dans `config/packages/` (le glob standard `MicroKernelTrait` important `config/{packages}/*.{yaml,php}` n'est pas récursif ; pas de sous-dossier `doctrine/`), donc aucune configuration d'import supplémentaire n'est nécessaire.

Ce choix diverge de la convention déjà actée pour `services.yaml` (ADR 009 §2), où chaque module ajoute son propre bloc **dans le même fichier**. La différence assumée : `services.yaml` ne gagne qu'une poignée de lignes par module (le `resource`/`exclude`), alors que les types et mappings Doctrine par VO grossissent plus vite et méritent une frontière de fichier plus nette. Un futur module suit le même schéma : `config/packages/doctrine_<module>.yaml`.

### 5. Convention de nommage des fichiers XML : nom court, pas le FQCN en pointillés

`SymfonyFileLocator` (utilisé par le driver simplifié configuré via `dir`/`prefix`/`alias`) résout le nom de fichier attendu en retranchant le `prefix` de la classe complète — le fichier doit donc s'appeler `Competition.orm.xml` (nom court), pas `App.Competition.Domain.Model.Competition.orm.xml`. L'attribut `<entity name="...">` à l'intérieur du fichier, lui, doit rester le FQCN complet — c'est la clé sous laquelle `loadMappingFile()` indexe le résultat, comparée telle quelle à la classe demandée.

## Conséquences

- Un futur mapping de VO identifiant (`TeamId`, `PlayerId`, `EncounterId`...) suit le même patron : un Doctrine Type dédié + `Stringable` sur le VO.
- Mapper `registrations` (collection de VOs) et `bracket` (interface polymorphe) restent des décisions ouvertes, à trancher explicitement avant implémentation le moment venu — probablement deux ADRs distincts tant les problèmes sont différents (collection de value objects vs. polymorphisme d'agrégat).
- Le port `CompetitionRepository` est mappé mais pas encore rebranché : `services.yaml` lie toujours `CompetitionRepository` à `InMemoryCompetitionRepository`. Le rebranchement vers `DoctrineCompetitionRepository` est la prochaine étape (voir ROADMAP), volontairement reportée à une session séparée.