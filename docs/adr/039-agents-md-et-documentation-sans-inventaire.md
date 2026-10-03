# 039. AGENTS.md et documentation de conventions sans inventaire du code

Date: 2026-10-03
Status: Accepted

## Contexte

`CLAUDE.md` concentrait les instructions de travail du dépôt : commandes, workflow, mais surtout une description d'architecture qui en représentait les trois quarts (261 lignes, 63 Ko). Le fichier est chargé intégralement dans le contexte d'un agent de code à chaque session, quelle que soit la tâche ; au-delà d'environ 200 lignes, le coût en contexte augmente et l'adhérence aux consignes baisse — les règles qui s'appliquent à chaque tâche (workflow) se retrouvaient noyées dans la description d'architecture.

Cette description était pour l'essentiel un **inventaire du code** : un tableau par classe du domaine, un paragraphe par use case, un paragraphe de résumé par ADR. Un inventaire grossit à chaque ajout, et se périme dès qu'une mise à jour est oubliée. À l'ouverture du chantier, plusieurs affirmations étaient fausses : signature de `BracketGenerator::generate()`, Value Object `Registration` qui n'existait plus (ADR 022), `PlayerId` présenté comme l'email (ADR 028), rattachement `Competition`/`Organization` présenté comme à faire (ADR 029), `CloseRegistration` présenté comme non authentifié (ADR 030), `Stringable` présenté comme requis sur tous les identifiants (ADR 010 le limite aux clés primaires Doctrine). La règle de workflow « documentation à jour dès qu'une feature est terminée » alimentait elle-même ce catalogue en exigeant une entrée par use case.

Enfin, le nom `CLAUDE.md` liait le dépôt à un outil particulier.

## Décision

### 1. Critère : la documentation décrit des conventions, jamais l'inventaire du code

Une ligne qui devrait changer à chaque ajout de classe ou de use case n'a pas sa place dans la documentation : le code, le routeur (`bin/console debug:router`), `security.yaml` et les ADR le disent déjà, et une copie finit par diverger. Ne s'écrit que ce qui reste vrai quand le projet grandit — conventions, patterns, langage métier. Ce critère s'applique à tous les fichiers de documentation, et remplace la règle de workflow qui exigeait une entrée par use case.

### 2. Répartition

- **`AGENTS.md`** (racine, ~80 lignes) : commandes, vue d'ensemble de l'architecture, renvois vers `docs/`, workflow intégral, suivi de l'avancement. Les règles de workflow y restent toutes : elles doivent s'appliquer avant même d'ouvrir un fichier.
- **`docs/architecture.md`** : modules et dépendances, conventions par couche (identifiants, exceptions, use cases, lecture, autorisation, persistance, sécurité, erreurs HTTP, tests), chacune reliée à l'ADR qui la justifie.
- **`docs/glossary.md`** : langage métier, nom dans le code, termes écartés.
- **`docs/adr/`** : inchangé ; aucun résumé n'est recopié ailleurs, les noms de fichiers `NNN-sujet.md` servent d'index.

Les catalogues supprimés ont été vérifiés un par un : toute information non dérivable du code et non portée par une ADR a été déplacée dans `docs/` (trois cas), une seule justification mineure a été abandonnée.

### 3. `AGENTS.md` plutôt que `CLAUDE.md`

`AGENTS.md` est la convention partagée entre agents de code. Claude Code le lit nativement lorsqu'aucun `CLAUDE.md` n'existe ; les deux fichiers ne doivent donc jamais coexister. La documentation détaillée vit dans `docs/`, rédigée pour n'importe quel contributeur et pas seulement pour un agent.

### Alternatives rejetées

- **Un fichier d'instructions par dossier** (`src/Competition/CLAUDE.md`…) : chargement par contexte réel, mais disperse des fichiers d'outillage dans `src/` et `tests/`.
- **Imports `@chemin`** depuis le fichier racine : les fichiers importés sont chargés au démarrage ; ils organisent le contenu sans réduire son coût en contexte.
- **Une documentation par module reprenant les tableaux de classes et de use cases** : déplace le problème sans le résoudre — même croissance, même péremption.
- **Un index d'une ligne par ADR dans `AGENTS.md`** : grossit d'une entrée par ADR et duplique ce que `ls docs/adr` donne déjà.
- **Règles scopées par chemin propres à un outil (`.claude/rules/`)** : écartées pour l'instant ; à reconsidérer seulement si l'usage montre que des conventions de `docs/` sont manquées faute d'être en contexte.

## Conséquences

- `AGENTS.md` : 80 lignes / 16 Ko, contre 261 lignes / 63 Ko pour `CLAUDE.md`. `git mv` conserve l'historique du fichier.
- Les conventions de `docs/` ne sont plus chargées d'office : elles sont lues à la demande, sur renvoi explicite d'`AGENTS.md` (« à lire avant d'ajouter un use case », « avant de nommer un concept »). C'est le compromis assumé en écartant les fichiers par dossier et les règles scopées par chemin, seuls mécanismes de chargement automatique par contexte.
- Une feature terminée ne touche plus la documentation, sauf si elle change une convention, introduit un pattern ou un terme métier.
- Les ADR 011, 020 et 037 mentionnent `CLAUDE.md` : traces historiques, non modifiées.