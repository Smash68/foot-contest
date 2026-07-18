# 016. Xdebug pour la couverture de tests, désactivé par défaut

Date: 2026-07-18
Status: Accepted

## Contexte

Aucun driver de couverture n'était installé dans le container `app` (ni Xdebug, ni PCOV) — PHPUnit ne peut pas produire de rapport de couverture sans l'un des deux. Besoin identifié en marge du travail sur la persistance, pas lié à un incrément du ROADMAP.

## Décision

### 1. Xdebug plutôt que PCOV

Xdebug sert un double usage : couverture de tests **et** debug pas-à-pas dans PhpStorm (l'interpréteur `app` de l'IDE référence déjà `debugger_id="php.debugger.XDebug"`). PCOV est plus rapide pour la couverture seule, mais n'offre pas de debug — aurait nécessité d'installer les deux outils pour couvrir les deux usages. Compilé depuis les sources (`pecl install xdebug`), nécessite `linux-headers` en plus des dépendances de build déjà présentes.

### 2. Désactivé par défaut (`xdebug.mode=off`), activé via la variable d'env `XDEBUG_MODE`

`docker/xdebug.ini` fixe `xdebug.mode=off` — Xdebug ajoute un coût mesurable même simplement chargé en mode inactif, et un coût bien plus important quand la couverture est active (mesuré : ~0.79s contre ~0.25s sur la suite actuelle de 97 tests, un facteur qui grossira avec la suite). `XDEBUG_MODE` est une variable d'environnement nativement lue par Xdebug en override de l'ini, sans avoir besoin d'interpolation de variables dans le fichier ini. Le run par défaut (`vendor/bin/phpunit`) reste donc aussi rapide qu'avant Xdebug.

`xdebug.client_host=host.docker.internal` (avec `extra_hosts` ajouté dans `compose.yaml` pour que ça fonctionne aussi sous Linux, transparent sur Mac/Docker Desktop) permet le debug pas-à-pas déclenché depuis PhpStorm.

### 3. Ne pas déclarer de rapport de couverture dans `phpunit.dist.xml`

Testé et rejeté : configurer un `<coverage><report>` dans `phpunit.dist.xml` rend la présence d'un driver de couverture **obligatoire** pour n'importe quel run — sans `XDEBUG_MODE=coverage`, PHPUnit refuse d'exécuter le moindre test (`No tests executed!`). Configurer la couverture dans le XML aurait donc cassé le run rapide par défaut. La couverture reste pilotée uniquement par flag CLI (`--coverage-text`) + variable d'env, jamais par la config persistante.

### 4. Script Composer `test:coverage`, pas de changement du comportement par défaut

`composer.json` : `"test:coverage": "XDEBUG_MODE=coverage vendor/bin/phpunit --coverage-text"`. Usage : `docker compose exec app composer test:coverage` — une seule commande à retenir plutôt que de composer `-e XDEBUG_MODE=coverage` et `--coverage-text` à chaque fois, sans toucher au comportement de `vendor/bin/phpunit` seul.

## Conséquences

- `docker compose exec app vendor/bin/phpunit` reste aussi rapide qu'avant (~0.15s sur la suite actuelle), aucune régression de vélocité sur le workflow TDD quotidien.
- Couverture actuelle (mesurée au moment de l'ADR) : 91.84% lignes, 80.17% méthodes — trous principalement sur des VOs triviaux (`PlayerId`, `TeamId`, `Player`, `EncounterId`), pas un signal d'alerte en soi.
- Debug pas-à-pas PhpStorm nécessite de revalider l'interpréteur `app` (Settings → PHP → CLI Interpreters) pour que l'IDE détecte Xdebug — le cache de détection (`PhpInterpretersPhpInfoCache` dans `.idea/php.xml`) date d'avant l'installation.
