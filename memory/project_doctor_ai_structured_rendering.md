---
name: project-doctor-ai-structured-rendering
description: Project Doctor — rendu IA structuré et sécurisé (payload narratif sanitisé, chiffres déterministes) + consolidation des rôles/techniciens
metadata:
  type: project
---

# AquaSecure — Project Doctor IA structuré + consolidation des rôles

## 1. Consolidation des techniciens (terminé)

- Le technicien n'est plus une entité séparée : c'est un **rôle applicatif** `users.role = 'technician'`.
- Artefacts Spatie (rôles/permissions) supprimés ; l'accès reste assuré par `App\Http\Middleware\RoleMiddleware`.
- Rôles canoniques : `admin`, `manager`, `technician`, `citizen`.
  Rôles legacy normalisés par migration : `gestionnaire` → `manager`, `citoyen` → `citizen`,
  `technicien` → `technician`, `NULL` → `citizen`.
  Le middleware conserve l'alias de compatibilité (`gestionnaire`/`citoyen`).
- Migrations :
  - `2026_10_20_000006_normalize_user_roles.php`
  - `2026_10_20_000007_consolidate_technicians_into_users.php`
    (copie `phone` / `speciality` / `status` sur `users`, remap des `technician_id`
    dans `maintenances`, `interventions`, `maintenance_assignment_invitations`,
    puis suppression de la table `techniciens` et recréation des clés étrangères sur `users`).
- Les URLs `techniciens.*` et `technicien.*` sont conservées (binding `Route::bind('technicien', ...)`).
- Le modèle `Technicien` a disparu : les relations `technician()` pointent désormais vers `App\Models\User`.

## 2. Project Doctor — payload IA structuré et sécurisé

### Règle centrale
Les **valeurs numériques déterministes** (calculées côté serveur) restent l'unique source de vérité.
Les chiffres retournés par l'IA ne sont **jamais** fusionnés dans `data` : ils seraient ignorés.

### Contrat de réponse
`data` = chiffres déterministes + `projet` + `est_pi_ia` + `disclaimer` + **`ia`**.

`data.ia` ne contient que des champs **narratifs** filtrés par intention :

| Intention                    | Champs autorisés                                              |
|-----------------------------|---------------------------------------------------------------|
| `cost_estimation`           | `synthese`, `hypotheses`, `recommandations`, `informations_manquantes` |
| `delay_risk`                | `synthese`, `causes_possibles`, `actions_recommandees`, `limites` |
| `funding_forecast`          | `synthese`, `hypotheses`, `recommandations`, `informations_manquantes` |
| `project_overview`          | `synthese`, `alertes`                                          |
| `general_question`          | `synthese`, `reponse`, `limite` (→ `limites`)                  |

### Assainissement
- Suppression des blocs `<script>`/`<style>` et des balises HTML (sans casser les comparateurs `<`).
- Suppression des caractères de contrôle / largeur nulle.
- Troncature : 1500 caractères par texte, 400 par élément de liste, 8 éléments maximum par liste.
- Aucun champ inconnu de l'IA n'est propagé (pas de fuite de type `secret_key`).

### Repli déterministe
Quand Gemini est indisponible (non configuré, quota 429, timeout 504, JSON invalide) :
- la réponse déterministe est renvoyée, `est_pi_ia = false` ;
- `data.ia` est alimenté par les informations manquantes et alertes déterministes.

### Rendu front
`resources/views/components/project-doctor.blade.php` :
- carte de données structurées (`doctor-card`) par intention ;
- notes IA (`doctor-notes`) lues depuis `data.ia` ;
- markdown rendu **sur texte échappé** (XSS neutralisé côté client) ;
- sources, badge de risque, bouton de copie, états de chargement/erreur.

## 3. Tests

`tests/Feature/ProjectDoctorTest.php` — 28 tests, dont :
- `test_ai_numeric_values_never_override_deterministic_figures`
- `test_ai_payload_is_limited_to_sanitized_narrative_fields`
- `test_deterministic_fallback_exposes_missing_information_notes`
- `test_project_doctor_panel_is_rendered_on_projects_pages`

Suite complète : **182 tests / 656 assertions** au vert.
