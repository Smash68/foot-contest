# 049. Contrat d'API OpenAPI généré par NelmioApiDocBundle et validé par les tests HTTP

Date: 2026-11-24
Status: Accepted

## Contexte

L'API HTTP n'avait aucun contrat publié. Les routes se découvraient via `bin/console debug:router`, mais les payloads attendus, les réponses et les codes d'erreur ne se lisaient que dans le code des contrôleurs, des DTOs de requête et des listeners `kernel.exception`. Un client de l'API n'avait aucune spécification à laquelle se référer, et rien ne garantissait qu'une documentation suive le code.

Trois contraintes existantes encadrent la solution :

- les contrôleurs sont de simples points d'entrée qui dispatchent une Command ou une Query sur le bus Messenger ([ADR 008](008-application-cqrs-messenger.md)) ;
- les erreurs métier sont traduites en HTTP par des listeners globaux, au format `{"error": "..."}` ([ADR 015](015-mapping-erreurs-metier-http.md), [ADR 031](031-listener-not-authorized-unifie.md), [ADR 043](043-effectif-minimum-par-equipe.md)) ;
- `Domain` et `Application` ne dépendent d'aucune bibliothèque ([ADR 048](048-direction-des-dependances-entre-couches.md)).

## Décision

### 1. Spécification générée par NelmioApiDocBundle à partir des contrôleurs existants

NelmioApiDocBundle génère une spécification OpenAPI à partir des routes, des paramètres de chemin et des DTOs de requête (`#[MapRequestPayload]`). Les réponses, qu'aucun outil ne peut déduire d'une `JsonResponse` construite à la main, sont déclarées par des attributs OpenAPI sur les contrôleurs. L'architecture HTTP ne change pas.

La spécification est servie en JSON sur `GET /api/doc.json`, et en interface navigable (Swagger UI) sur `GET /api/doc`, dans tous les environnements : l'API est publique, sa description ne contient aucun secret.

Alternatives écartées :

- **API Platform**, avec des State Processors qui dispatchent sur Messenger : le CQRS de l'ADR 008 survivrait, mais API Platform impose sa propre traduction des exceptions en statuts et son format d'erreur (Problem Details), qui remplaceraient les listeners des ADR 015 et 031 et changeraient le contrat d'erreur existant. Les endpoints d'action (`close-registration`, `generate-bracket`, `approve`, `finish`…) sont des commandes métier, pas des ressources CRUD : ils deviendraient des opérations sur mesure. Les contrôleurs et leurs tests HTTP seraient réécrits pour un besoin de documentation.
- **Spécification écrite à la main** (fichier YAML versionné) : aucun lien avec le code, elle dérive dès le premier endpoint modifié.

### 2. Le contrat est validé par chaque test HTTP

Les attributs de réponse sont écrits à la main et peuvent diverger du comportement réel. Les tests HTTP valident donc leurs échanges contre la spécification générée par l'application, via l'objet de support `OpenApiContract` (`tests/Support/Http/`), qui s'appuie sur `league/openapi-psr7-validator`. Un échange satisfait le contrat si :

- l'opération de la requête est documentée, et la requête la respecte ;
- le statut de la réponse est documenté **explicitement** pour cette opération ;
- le corps de la réponse respecte le schéma documenté.

La deuxième règle écarte la réponse `default` : NelmioApiDocBundle en ajoute une, vide, à chaque opération, et le validateur l'utiliserait pour accepter sans contrôle toute réponse au statut non documenté.

Un endpoint non documenté, ou dont la réponse diverge de sa documentation, fait ainsi échouer la suite de tests. Aucun test supplémentaire par endpoint n'est nécessaire : les tests HTTP existants couvrent déjà chaque statut que l'endpoint peut renvoyer.

Alternatives écartées :

- **Tests de contrat dédiés, distincts des tests HTTP** : ils rejoueraient les mêmes requêtes que les tests de contrôleur, pour chaque statut de chaque endpoint.
- **Test de propriétés généré à partir de la spécification** (outil externe qui interroge l'API avec des requêtes dérivées du schéma) : il vérifie la robustesse face à des entrées arbitraires, pas qu'un comportement métier précis est bien documenté, et demande un environnement d'exécution séparé de la suite PHPUnit.

### 3. Les formats d'erreur sont documentés tels qu'ils existent

L'API répond aux erreurs sous trois formats, chacun déclaré une seule fois comme schéma et comme réponses réutilisables, que les endpoints référencent avec leur statut :

| Origine | Schéma | Réponses réutilisables |
|---|---|---|
| Listeners d'erreurs métier, 404 d'une sous-ressource absente | `Error` (`{"error"}`) | `BusinessRuleViolation`, `Conflict`, `Forbidden`, `NotFound`, `InvalidCredentials` |
| Firewall JWT (token absent, invalide ou expiré) | `AuthenticationError` (`{"code", "message"}`) | `Unauthenticated` |
| `#[MapRequestPayload]` (JSON malformé, champ manquant ou mal typé) | `Problem`, `ValidationProblem` (Problem Details) | `MalformedPayload`, `InvalidPayload` |

Les erreurs de corps de requête ne sont rendues en JSON que si la requête envoie `Accept: application/json`, en HTML sinon : la description des réponses concernées le signale.

Unifier ces formats change le contrat d'erreur de l'API et relève d'une décision propre, suivie par l'issue #72. La documentation décrit le comportement actuel, et la validation de contrat protégera cette harmonisation : chaque format modifié devra l'être aussi dans la spécification.

Une réponse propre à un seul endpoint (le 409 enrichi de `incompleteTeams`, [ADR 043](043-effectif-minimum-par-equipe.md)) est documentée sur cet endpoint, pas comme réponse réutilisable.

### 4. Les assets de Swagger UI sont servis par l'application

Par défaut, NelmioApiDocBundle charge les scripts et feuilles de style de Swagger UI depuis jsDelivr, sur la branche principale de son dépôt, sans version figée. Le mode `bundle` les sert depuis `public/bundles`, où `assets:install` les copie à chaque installation des dépendances : leur version est figée par `composer.lock`, sans dépendance à un service tiers.

Alternative écartée : le mode `offline`, qui intègre les assets dans le HTML de chaque réponse, sans gain par rapport au mode `bundle` pour une application qui sert déjà ses propres fichiers.

## Conséquences

- Documenter un endpoint se fait dans l'`Infrastructure` uniquement : attributs OpenAPI sur le contrôleur et ses DTOs de requête. Les modèles de lecture (`*View`, [ADR 034](034-get-bracket-query-read-model.md)) appartiennent à l'`Application`, qui ne peut porter d'attribut de bibliothèque (ADR 048) : leur schéma de réponse est déclaré côté `Infrastructure`.
- Deux DTOs de requête de même nom court dans deux modules (`LoginRequest`) produisent des schémas suffixés automatiquement (`LoginRequest2`) : leur nom de schéma est fixé explicitement.
- Chaque nouvel endpoint, ou chaque nouveau statut renvoyé par un endpoint existant, doit être documenté pour que ses tests HTTP passent.
- La documentation des endpoints existants et l'activation de la validation dans leurs tests se livrent module par module, la validation devenant obligatoire pour tous les tests HTTP une fois chaque endpoint documenté.
- Nouvelles dépendances : `nelmio/api-doc-bundle`, `symfony/twig-bundle` et `symfony/asset` (rendu de Swagger UI) en production ; `league/openapi-psr7-validator`, `symfony/psr-http-message-bridge`, `nyholm/psr7` et `symfony/dom-crawler` en développement. Les services de `nyholm/psr7` sont déclarés en environnement de test seulement, le paquet étant absent en production.
- Le recipe de NelmioApiDocBundle relève de `recipes-contrib`, ignoré par Flex dans ce projet : son contenu est appliqué à la main, comme celui de DAMADoctrineTestBundle.
