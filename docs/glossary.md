# Glossaire

Langage métier du projet (ubiquitous language) : le terme français utilisé en conversation, son nom dans le code, et ce qu'il désigne. Les acteurs (organisateur, capitaine, joueur) sont décrits dans le [`README`](../README.md#acteurs).

Ce fichier ne change que lorsque le domaine change — un nouveau concept métier, ou un terme renommé — jamais pour un simple ajout de code.

## Organisation et inscription

| Terme | Code | Définition |
|-------|------|------------|
| Organisation | `Organization` | Le client du SaaS (entreprise, association, mairie) : le tenant. N'existe qu'une fois son paiement confirmé |
| Session de paiement | `CheckoutSession` | Paiement en cours pour créer une organisation : initié, puis confirmé ou échoué par le fournisseur |
| Compétition | `Competition` | Un tournoi organisé par une organisation. Traverse deux phases : l'inscription, puis le jeu |
| Jauge | `TeamCapacity` | Nombre minimum et maximum d'équipes d'une compétition |
| Inscription | — | Phase pendant laquelle les équipes s'inscrivent et composent leur effectif. Close manuellement par l'organisateur, une fois le minimum d'équipes atteint ; les effectifs sont alors figés |
| Équipe | `Team` | Inscrite à une compétition par son capitaine **seul** ; l'effectif se compose ensuite par demandes d'adhésion. Inscrire une équipe déjà complète court-circuiterait la validation du capitaine |
| Effectif | `roster` | Les joueurs confirmés d'une équipe. Un joueur n'appartient qu'à une seule équipe par compétition |
| Demande d'adhésion | `pendingRequests` | Candidature d'un joueur pour rejoindre une équipe, acceptée ou refusée par le capitaine. Un joueur peut candidater à plusieurs équipes |

## Tableau et rencontres

| Terme | Code | Définition |
|-------|------|------------|
| Format | `CompetitionFormat` | Structure du tournoi, choisie à la création de la compétition. Aujourd'hui : élimination directe (coupe) |
| Tableau | `Bracket` | L'ensemble des tours d'une compétition, généré par une action manuelle distincte, après la clôture des inscriptions |
| Tour | `Round` | Une étape du tableau ; les vainqueurs d'un tour s'affrontent au suivant |
| Rencontre | `Encounter` | Un match entre deux participants |
| Participant | `Participant` | Ce qui occupe un côté d'une rencontre : une équipe connue, une exemption, ou le vainqueur encore inconnu d'une rencontre précédente |
| Exemption | `bye` | Qualification automatique pour le tour suivant, quand le nombre d'équipes n'est pas une puissance de 2 |
| Résultat | `EncounterResult` | Issue d'une rencontre : score en temps réglementaire, après prolongation ou après tirs au but |
| Match pour la 3e place | option `includeThirdPlaceMatch` | Rencontre optionnelle entre les deux perdants des demi-finales, choisie à la création de la compétition |
| Champion | — | Vainqueur de la dernière rencontre du tableau |

## Termes écartés

| Terme écarté | Remplacé par | Raison |
|--------------|--------------|--------|
| `Match` | `Encounter` | Mot réservé de PHP 8 |
| `Fixture` | `Encounter` | Sans sens métier pour un tournoi |
| `Slot` | `Participant` | Sans sens métier dans le football |
| `Tournament` / `Contest` | `Competition` | Voir [ADR 007](adr/007-inscription-competition-agregat.md) |