# 015. Mapper les violations de règles métier en 422, pas en 500

Date: 2026-07-18
Status: Accepted

## Contexte

En auditant `CreateCompetitionControllerTest` (un seul happy path testé), une violation d'invariant métier (ex. `TeamCapacity::of()` avec `min < 2`) traversait le contrôleur sans être interceptée : Messenger l'enveloppe dans `HandlerFailedException`, qui remonte comme exception non gérée jusqu'au kernel — réponse HTTP 500 (alors que c'est une erreur du client, pas un bug serveur), page d'erreur Symfony HTML (pas de format JSON cohérent avec le reste de l'API), message d'exception interne exposé tel quel dans le titre de la page.

Le domaine a déjà une convention établie et cohérente pour signaler ces violations : `\InvalidArgumentException` (input invalide — `TeamCapacity`, `Score`, VOs identifiants...) et `\LogicException` (transition d'état invalide — `Competition::register()`, `closeRegistration()`...), jamais utilisées pour un vrai bug de programmation.

## Décision

### 1. Listener global `kernel.exception`, pas de try/catch par contrôleur

`InvalidArgumentExceptionListener` (`Infrastructure/Http/`), écouteur `#[AsEventListener(event: KernelEvents::EXCEPTION)]` — auto-enregistré via l'autoconfiguration (`services.yaml`, `autoconfigure: true`), aucune config supplémentaire. Un seul point d'interception réutilisable par les futures routes du module, plutôt qu'un try/catch dupliqué dans chaque contrôleur à mesure que d'autres commandes seront exposées en HTTP.

Désencapsule `HandlerFailedException` (Messenger) via `getPrevious()` — c'est l'exception réellement levée par le domaine que Messenger passe en `$previous` au constructeur.

### 2. Seule `\InvalidArgumentException` est interceptée pour l'instant, pas `\LogicException`

C'est la seule exception atteignable aujourd'hui : `CreateCompetitionCommand` ne traverse que `TeamCapacity::of()`. `\LogicException` (ex. "Competition a atteint son nombre maximum d'équipes") n'est pas encore exposée en HTTP — aucune route n'appelle `register()`/`closeRegistration()`/`generateBracket()` pour l'instant (priorités 5c/6/7 du ROADMAP). Conforme au principe « petits incréments » du projet : étendre le listener à `\LogicException` quand la prochaine route en aura réellement besoin, pas par anticipation.

### 3. Réponse `JsonResponse(['error' => $message], 422)`, message de l'exception exposé tel quel

Les messages d'exception du domaine sont déjà rédigés pour être lisibles (`'min must be at least 2.'`) — pas de texte de dump PHP, pas de trace, pas de détail d'infrastructure (SQL, chemins de fichiers). Les exposer directement au client est sûr précisément parce que le domaine ne lève jamais `\InvalidArgumentException`/`\LogicException` pour une erreur d'infrastructure (celles-ci restent non interceptées par ce listener, donc toujours 500).

## Conséquences

- `CreateCompetitionControllerTest` couvre désormais les 4 réponses possibles de la route : 201 (succès), 422 (règle métier invalide, champ manquant, type invalide), 400 (JSON malformé — géré nativement par `#[MapRequestPayload]`, aucun code à écrire).
- Vérifié manuellement : `POST /competitions` avec `minTeams: 1` répond `{"error":"min must be at least 2."}` en 422, sans trace ni HTML.
- Étendre à `\LogicException` (et réévaluer si le message brut reste sûr à exposer) est un incrément séparé, à faire quand une route appellera une méthode de `Competition` qui la lève.
