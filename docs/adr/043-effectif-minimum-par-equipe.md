# 043. Effectif minimum par équipe, vérifié à la clôture des inscriptions

Date: 2026-10-05
Status: Accepted

## Contexte

Une équipe peut aujourd'hui participer au tableau avec son seul capitaine : rien ne contrôle la taille de l'effectif avant la clôture des inscriptions, qui fige les effectifs. L'organisateur doit pouvoir exiger un effectif minimum par équipe, remplaçants compris, et ce minimum varie selon le type de football joué (foot à 5, à 7, à 11) et selon l'organisateur lui-même : un tournoi de foot à 5 peut exiger 5, 7 ou 8 joueurs selon le nombre de remplaçants attendus.

Plusieurs questions couplées devaient être tranchées ensemble : la forme du concept, l'endroit où la règle s'applique, la façon de signaler les équipes en défaut, et l'impact sur le contrat d'API et les données existantes.

## Décision

### 1. Un minimum saisi par l'organisateur, sans notion de discipline

L'effectif minimum est un entier choisi par l'organisateur à la création de la compétition, porté par un VO `MinimumRosterSize::of(int)` — même traitement que `TeamCapacity` ([ADR 007](007-inscription-competition-agregat.md) §5), le VO porte sa propre règle de validité. Le nom suit le glossaire, où l'effectif se dit `roster`.

Alternative écartée : un concept `Discipline` (foot à 5, 7, 11) dont se déduirait le minimum. Le minimum, remplaçants compris, ne se déduit pas de la discipline seule ; le concept n'aurait aucun autre usage aujourd'hui.

**Borne basse : 1.** Le capitaine fait toujours partie de l'effectif, donc un minimum de 1 n'impose aucune contrainte supplémentaire. Une borne plus haute (5, plus petit format courant) supposerait qu'aucun format ne descend en dessous, alors que des formats à 3 existent. Le VO ne porte que le minimum : le maximum d'effectif est hors périmètre ; s'il est introduit, le VO deviendra une jauge `min`/`max` symétrique de `TeamCapacity`.

### 2. La règle vit dans `Competition::closeRegistration()`

Vérifier l'effectif de chaque équipe inscrite est une règle transverse à l'ensemble des équipes : elle appartient à l'agrégat racine, comme les règles de cohérence inter-équipes d'[ADR 032](032-coherence-inter-equipes.md). Seul l'effectif confirmé (`Team::getRoster()`, capitaine et remplaçants compris) est compté, jamais les demandes d'adhésion en attente — même portée qu'ADR 032 §3.

Le minimum d'équipes (règle préexistante) est vérifié avant l'effectif des équipes.

### 3. Une exception dédiée, qui porte les équipes incomplètes

La clôture refusée doit nommer les équipes en défaut. Une `IncompleteTeamsException extends \LogicException` porte la liste de ces équipes (identifiant et nom), plutôt que de les noyer dans le texte du message. En tant que `\LogicException`, elle est d'emblée mappée en 409 par le listener générique ; un listener dédié l'exposera en réponse structurée, `incompleteTeams: [{id, name}]`, le format de réponse différent étant l'exception explicitement prévue par [ADR 031](031-listener-not-authorized-unifie.md). L'identifiant est indispensable au client pour retirer l'équipe concernée.

### 4. Retirer une équipe incomplète : aucun nouveau comportement

Après un refus, les inscriptions restent ouvertes : l'organisateur propriétaire peut retirer une équipe par le retrait existant ([ADR 030](030-withdraw-double-acteur.md)), puis retenter la clôture.

### 5. Contrat d'API et données existantes

`minRosterSize` devient un champ obligatoire de la création de compétition : l'organisateur doit le définir. Ce changement de contrat incompatible est accepté avant la `v1.0.0`. Les compétitions déjà enregistrées reçoivent un minimum de 1 par migration, ce qui conserve exactement leur comportement.

## Conséquences

- `Competition::create()` prend un `MinimumRosterSize` ; `CompetitionBuilder` le fixe à 1 par défaut, un test n'en parle que s'il le vérifie.
- Une nouvelle colonne persistée sur `competition`, renseignée à 1 pour l'existant.
- Livraison en trois PR successives ([ADR 042](042-une-pr-par-increment-de-feature.md)) : la règle métier et sa persistance, avec un minimum neutre de 1 imposé par `CreateCompetitionHandler` ; la saisie du minimum à la création, qui retire ce raccourci ; la réponse structurée du refus de clôture.