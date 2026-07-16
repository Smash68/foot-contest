# 008. Couche Application : CQRS via Symfony Messenger, id généré par le repository

Date: 2026-07-16
Status: Accepted

## Contexte

Priorité 5 (infrastructure) entamée via un premier use case complet, bout-en-bout : la création d'une compétition (`POST /competitions`). La couche `Application/` n'existait pas encore — son introduction avait été volontairement reportée (voir ADR 007) faute de repository à orchestrer. Plusieurs décisions de conception à trancher pour l'introduire.

## Décision

### 1. CQRS via `symfony/messenger`, pas de bus fait main

Une classe de bus custom (`CompetitionCommandBus`), encapsulant un `MessageBus` construit à la main avec un `HandlersLocator` listant explicitement chaque handler, a été écartée : elle prenait un handler par paramètre de constructeur, ce qui n'aurait pas scalé (chaque nouvelle Command aurait imposé de modifier son constructeur). C'est une abstraction par agrégat là où il en faut une seule, applicative, alimentée par tous les handlers de l'app — exactement ce que le container Symfony fait déjà nativement via le tag `messenger.message_handler` et l'auto-discovery. Décision : aucune classe de bus custom, on s'appuie entièrement sur `MessageBusInterface` fourni par le framework.

### 2. Le Handler est taggé en config, pas via l'attribut `#[AsMessageHandler]`

Deux façons de déclarer un handler Messenger : l'attribut `#[AsMessageHandler]` directement sur la classe, ou un tag `messenger.message_handler` déclaré dans `config/services.yaml`. La première couple l'Application layer au package `symfony/messenger` ; la seconde garde le Handler en PHP pur (juste `__invoke()`), toute connaissance du framework restant en Infrastructure/config. Choisi : le tag en config, cohérent avec la pureté hexagonale déjà appliquée au Domain (voir ADR 004).

### 3. L'id est généré par le repository (`nextIdentity()`), pas par la Command ni le contrôleur

Trois emplacements ont été envisagés pour la génération de l'id de la compétition :
- Le client le fournit dans la Command (convention CQRS pure — une Command ne retourne rien, donc classiquement le client doit connaître l'id à l'avance). Écarté : impose une responsabilité d'unicité au client sans raison forte ici, puisque le endpoint HTTP est synchrone et peut renvoyer l'id généré.
- Le contrôleur le génère (`Uuid::v7()`) avant de construire la Command. Écarté après relecture : le rôle d'un contrôleur est de diriger le flux, pas de prendre une décision (même mineure) qui appartient au domaine/à l'infrastructure de persistance.
- Le repository le fournit via `nextIdentity(): CompetitionId` — pattern DDD classique (Vernon, *Implementing Domain-Driven Design*). Retenu : `CreateCompetitionHandler` appelle `$this->repository->nextIdentity()`, construit l'agrégat, le persiste, et **retourne** le `CompetitionId`.

Conséquence sur le contrat CQRS : `CreateCompetitionHandler::__invoke()` n'est plus `void`. C'est une entorse pragmatique assumée à la convention "une Command CQRS ne retourne rien" — nécessaire spécifiquement pour les handlers de création, où un appelant synchrone (contrôleur HTTP) a besoin de connaître l'id généré sans repasser par une lecture séparée. Le mécanisme utilisé pour récupérer ce retour est celui fourni par Messenger lui-même : `$envelope = $bus->dispatch($command)` puis `$envelope->last(HandledStamp::class)->getResult()` — pas un hack, un usage documenté du composant.

### 4. `InMemoryCompetitionRepository` sert à la fois d'adapter réel et de test double

Faute de vraie persistance (à trancher : Doctrine ou non, priorité 5 suite), l'implémentation en mémoire du port `CompetitionRepository` est utilisée directement dans les tests (`CreateCompetitionHandlerTest`) et câblée telle quelle dans le container applicatif (`config/services.yaml`). Pas de mock : conforme à la règle des tests comportementaux déjà en vigueur.

## Conséquences

- `CreateCompetitionCommand` ne porte que `name`/`minTeams`/`maxTeams` — pas d'id
- `CompetitionRepository` (port, Domain) expose `nextIdentity(): CompetitionId`, `save()`, `ofId()`
- Tout futur Handler de création suivra la même mécanique : générer l'id via le repository, retourner l'agrégat créé (ou son id), lecture via `HandledStamp` côté appelant synchrone
- Aucune classe de bus custom à maintenir ; le câblage Messenger vit entièrement dans `config/services.yaml` (voir aussi ADR 009)
- Le jour où un mode d'invocation réellement asynchrone (transport dédié, pas le bus sync par défaut) sera introduit, la convention "le handler retourne l'id" devra être réévaluée — elle suppose un dispatch synchrone