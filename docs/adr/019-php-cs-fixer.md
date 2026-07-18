# 019. PHP-CS-Fixer : `@Symfony` avec trois écarts assumés

Date: 2026-07-18
Status: Accepted

## Contexte

Aucun outil de formatage automatique n'était en place — seul PHPStan (ADR 018) couvrait la correction de types, pas le style. Besoin identifié en marge du travail sur la persistance, comme PHPStan avant lui.

## Décision

### 1. `@Symfony`, pas `@Symfony:risky`

Uniquement des règles de formatage (espaces, imports, structure) — aucune règle susceptible de changer le comportement du code (`declare_strict_types`, comparaisons strictes forcées, etc.). Le projet applique déjà `strict_types=1` partout à la main ; formaliser ça via des règles risky aurait été un chantier à part, pas un sous-produit de la mise en place de l'outil.

### 2. Revue règle par règle avant d'appliquer, pas un `fix` en aveugle

`@Symfony` seul aurait renommé toutes les méthodes de test en camelCase (`php_unit_method_casing`), contredisant la convention snake_case déjà établie dans tout le projet (`it_creates_a_competition`, etc.) — configuré explicitement en `snake_case`. Trois autres règles ont été passées en revue et désactivées par préférence explicite (pas un défaut aveugle) :

- `yoda_style` — désactivée, `$value === null` plutôt que `null === $value`.
- `concat_space` — désactivée, `'a' . $b` (avec espaces) plutôt que `'a'.$b`.
- `increment_style` — désactivée, `$i++` plutôt que `++$i`.

`global_namespace_import` (retire les `use` des exceptions SPL au profit de `\InvalidArgumentException` inline) a été gardée : déjà la convention majoritaire dans le code existant avant même l'introduction de l'outil.

Le reste de `@Symfony` (tri des imports, accolades multi-lignes pour un corps vide, espacement `fn (...)`, ligne vide finale, séparation des blocs PHPDoc, ligne vide avant `return`) a été appliqué tel quel — purement mécanique, aucun avis raisonnable à en avoir.

### 3. Vérification seule en CI (`cs-check`), jamais de fix automatique

`composer cs-check` (`--dry-run --diff`) fait échouer la CI si du code n'est pas formaté ; `composer cs-fix` reste une commande locale explicite. Pas d'auto-fix + commit automatique en CI — cohérent avec le reste du projet où toute modification passe par une revue humaine avant merge.

## Conséquences

- `docker compose exec app composer cs-check` / `composer cs-fix` disponibles, intégrés à la CI juste après PHPStan (ADR 018), avant les migrations — ni l'un ni l'autre ne dépend de PostgreSQL.
- 44 fichiers reformatés en un seul passage (espacement, imports, structure) — aucun changement de comportement, tests et PHPStan restés au vert après application.
- Toute nouvelle divergence de style fait échouer la CI immédiatement, sans ambiguïté sur les règles retenues (elles sont explicites dans `.php-cs-fixer.dist.php`, pas seulement "le défaut de @Symfony").
