# 025. Tagger les Handlers Messenger par `resource`, pas un bloc par use case

Date: 2026-08-02
Status: Accepted

## Contexte

Depuis ADR 008, chaque Handler Application est taggé `messenger.message_handler` explicitement en config (`services.yaml`), pas par l'attribut `#[AsMessageHandler]`, pour garder la couche Application 100% PHP sans dépendance au framework dans le code. Avec 5 use cases (`CreateCompetition`, `RegisterTeam`, `CreatePlayer`, `Withdraw`, `CloseRegistration`), `services.yaml` accumulait un bloc identique par Handler :

```yaml
App\Competition\Application\Withdraw\WithdrawHandler:
    tags: ['messenger.message_handler']
```

Chaque nouveau use case ajoute mécaniquement un bloc de plus, sans jamais retirer de complexité — une répétition pure qui grandit indéfiniment avec le nombre de use cases.

**1. Splitter `services.yaml` en plusieurs fichiers (ex: `services_handlers.yaml`) ?**

Rejeté : déplace la répétition dans un autre fichier sans la réduire. N'apporte de valeur que si le fichier devient difficile à lire dans son ensemble — pas encore le cas (48 lignes), et ne le sera pas non plus avec un fichier scindé qui grandit au même rythme.

**2. Revenir sur ADR 008 et utiliser `#[AsMessageHandler]` ?**

Rejeté : réintroduit une dépendance au framework dans le code Application, ce qu'ADR 008 excluait explicitement. Le problème n'est pas où vit le tag, mais qu'il faille un bloc par use case pour l'exprimer.

## Décision

Un unique bloc `resource:` cible tous les Handlers par convention de nommage déjà en vigueur (un fichier `*Handler.php` par dossier de use case) :

```yaml
App\Competition\Application\:
    resource: '../src/Competition/Application/*/*Handler.php'
    tags: ['messenger.message_handler']
```

Ce bloc vient compléter — pas remplacer — le bloc générique `App\Competition\: resource: '../src/Competition/*'` déjà présent, qui enregistre tous les services du module (autowire/autoconfigure hérités de `_defaults`). Le second bloc, plus étroit, ne fait qu'ajouter le tag Messenger aux services qu'il matche.

## Conséquences

- Ajouter un nouveau use case (ex: `generateBracket`, priorité 5c) n'implique plus aucune modification de `services.yaml` tant que son Handler suit la convention `*Handler.php` — la config du module cesse de grandir avec le nombre de use cases.
- Le tag reste porté en config, pas par attribut sur la classe — ADR 008 inchangée.
- Repose sur la convention de nommage déjà établie (`Command`/`Handler` par dossier de use case) ; toute classe Application qui se nommerait `*Handler.php` sans être un Handler Messenger serait taggée à tort — risque jugé négligeable au vu de la convention déjà strictement suivie sur les 5 use cases existants.