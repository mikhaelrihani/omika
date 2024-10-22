# Schéma des Entités Liées aux Événements

## 1. Entité `Event`
| Champ               | Type        | Description                                                      |
|---------------------|-------------|------------------------------------------------------------------|
| `id`                | `string`    | Identifiant unique de l'événement                                |
| `type`              | `string`    | Type d'événement (task ou info)                                  |
| `importance`        | `boolean`   | Indique si l'événement est important                             |
| `description`       | `string`    | Détails de l'événement                                           |
| `shared_with`       | `json`      | Liste des utilisateurs avec qui l'événement est partagé          |
| `createdBy`         | `string`    | Auteur de l'événement                                            |
| `updatedBy`         | `string`    | Dernier auteur ayant modifié l'événement                         |
| `periode_start`     | `date`      | Date de début de la période                                      |
| `periode_end`       | `date`      | Date de fin de la période                                        |
| `date_status`       | `string`    | Statut de la date (past, activedayrange, future)                 |
| `isRecurring`       | `boolean`   | Indique si l'événement est récurrent                             |
| `ispseudo_recurring`| `boolean`   | Indique si l'événement est pseudo-récurrent                      |
| `event_frequence`   | `relation`  | Relation One-to-One avec `Event_Frequence` pour gérer la récurrence |
| `task_details`      | `json`      | Détails de la tâche associée (si type = task)                    |
| `task_status`       | `string`    | Statut de la tâche (ex. todo, pending, done, late)               |
| `unreadUsers`       | `json`      | Liste des utilisateurs n'ayant pas lu l'événement (si type = info)|
| `side`              | `string`    | Côté de l'événement (ex. "kitchen", "office")                    |
| `date_limit`        | `date`      | Date limite pour la visibilité (pour tâches automatisées)        |
| `active_day_range`  | `integer`   | Plage de jours actifs (ex. -3 à +7 jours)                        |
| `event_section`     | `relation`  | Relation One-to-One avec `Event_Section`                         |

---

## 2. Entité `Event_Section`
| Champ      | Type     | Description                             |
|------------|----------|-----------------------------------------|
| `id`       | `string` | Identifiant unique de la section         |
| `name`     | `string` | Nom de la section associée à l'événement |

---

## 3. Entité `Event_Frequence`
| Champ       | Type     | Description                           |
|-------------|----------|---------------------------------------|
| `id`        | `string` | Identifiant unique de la fréquence    |
| `day`       | `integer`| Jour associé à la fréquence (1 = lundi, 7 = dimanche, 8 = illimité) |
| `monthDay`  | `integer`| Jour du mois associé à la fréquence (1 à 31)   |

---

## 4. Entité `User_Info`
| Champ              | Type     | Description                              |
|--------------------|----------|------------------------------------------|
| `id`               | `string` | Identifiant unique de l'utilisateur      |
| `name`             | `string` | Nom de l'utilisateur                     |
| `recurring_events` | `json`   | Liste des événements récurrents de l'utilisateur |

---

## 5. Entité `Supplier`
| Champ              | Type     | Description                              |
|--------------------|----------|------------------------------------------|
| `id`               | `string` | Identifiant unique du fournisseur        |
| `recuring_events`  | `json`   | Liste des événements récurrents générés  |

---

### Relations entre Entités

- Un **`Event`** est associé à une **`Event_Section`** via une relation One-to-One.
- Un **`Event`** est associé à une **`Event_Frequence`** via une relation One-to-One pour gérer la récurrence.
- Un **`User_Info`** peut contenir plusieurs **`recurring_events`** dans un tableau JSON pour suivre les événements récurrents.
- Un **`Supplier`** peut générer plusieurs **`Event`** récurrents (relation 1-N).

---
