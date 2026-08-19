# 031. Listener HTTP unique pour les exceptions "non autorisé"

Date: 2026-08-19
Status: Accepted

## Contexte

En ajoutant `ApproveJoinRequest` (autorisation capitaine-seul, Priorité 6), une troisième exception 403 (`NotAuthorizedToManageJoinRequestException`) s'apprêtait à recevoir son propre listener `kernel.exception`, sur le modèle de `NotAuthorizedToWithdrawExceptionListener` (ADR 030) et `OrganizerNotAuthorizedForOrganizationExceptionListener` (ADR 029). Comparaison des deux listeners existants : code identique à l'exception attrapée près (désencapsulation `HandlerFailedException`, réponse `JsonResponse(['error' => ...], 403)`). Un troisième exemplaire aurait rendu la duplication impossible à ignorer.

`InvalidArgumentExceptionListener` (ADR 015) n'a jamais eu ce problème : il attrape le type natif `\InvalidArgumentException` directement, sans classe dédiée par règle métier violée. Rien n'empêchait d'appliquer le même principe aux 403.

## Décision

Nouvelle classe abstraite `App\Competition\Domain\Exception\NotAuthorizedException extends \RuntimeException`, sans comportement propre — un marqueur de famille, pas un type porteur de logique. `NotAuthorizedToWithdrawException`, `OrganizerNotAuthorizedForOrganizationException` et la nouvelle `NotAuthorizedToManageJoinRequestException` en héritent désormais au lieu d'étendre `\RuntimeException` directement.

Un unique `NotAuthorizedExceptionListener` remplace les deux listeners existants : il attrape `NotAuthorizedException` (le type de base) et répond `403` avec `$throwable->getMessage()`, exactement comme avant. Ajouter une future règle d'autorisation ne demande plus qu'une classe d'exception étendant `NotAuthorizedException` — aucun nouveau listener à écrire.

Alternative envisagée : une interface marqueur plutôt qu'une classe abstraite, pour ne pas contraindre la hiérarchie d'héritage si une future exception "non autorisée" avait besoin d'un autre parent que `\RuntimeException`. Rejetée faute de besoin réel — les trois exceptions existantes étendent déjà `\RuntimeException` sans autre contrainte, et le reste du dossier `Domain/Exception/` n'utilise aucune interface. Une classe abstraite reste plus simple et cohérente avec le style déjà établi (classes `final`, sans interface, sur les exceptions).

## Conséquences

- `InvalidArgumentException` (validations métier, 422) et `NotAuthorizedException` (autorisation, 403) sont désormais les deux seules familles de listener générique du module — toute nouvelle règle rentre dans l'une des deux sans code HTTP supplémentaire, sauf réel besoin d'un statut ou d'un format de réponse différent.
- `InvalidCredentialsException` (401) reste seule avec son propre listener : statut différent des deux familles ci-dessus, pas de regroupement pertinent avec l'une ou l'autre.
- Les exceptions concrètes ne changent pas de message ni de constructeur, seulement de parent — aucun test de couche Application n'a dû changer (`expectException` cible toujours la classe concrète, jamais `NotAuthorizedException`).