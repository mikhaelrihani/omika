#### Entité `Event`
Représente l'entité de base commune aux événements, que ce soit des tâches ou des informations.

| Champ              | Type            | Description                                                                                       |
|--------------------|-----------------|---------------------------------------------------------------------------------------------------|
| `id`               | `Integer`       | Identifiant unique de l'événement.                                                                |
| `type`             | `String`        | Type d'événement (`task` ou `info`).                                                              |
| `importance`       | `Boolean`       | Indique si l'événement est important (`true` ou `false`).                                          |
| `description`      | `Text`          | Détails de l'événement.                                                                           |
| `shared_with`      | `Array`         | Tableau listant les identifiants des utilisateurs avec qui l'événement est partagé.                |
| `createdBy`        | `Integer`       | Identifiant de l'utilisateur ayant créé l'événement.                                               |
| `updatedBy`        | `Integer`       | Identifiant de l'utilisateur ayant modifié l'événement.                                            |
| `periode_start`    | `Date`          | Date de début de la période de l'événement.                                                       |
| `periode_end`      | `Date`          | Date de fin de la période de l'événement (peut être `null` pour un événement d'une seule journée).  |
| `date_status`      | `String`        | Statut temporel de l'événement (`past`, `activedayrange`, `future`).                               |
| `isRecurring`      | `Boolean`       | Indique si l'événement est récurrent (`true` ou `false`).                                          |
| `ispseudo_recurring` | `Boolean`     | Indique si l'événement est pseudo-récurrent.                                                      |
| `active_day_range` | `Integer`       | Plage de jours durant laquelle l'événement est actif (ex. de -3 à +7 jours autour de la date courante). |
| `event_frequence`  | `Integer`       | Référence vers l'entité `Event_Frequence` (clé étrangère).                                         |
| `side`             | `String`        | Côté ou contexte de l'événement (ex. "kitchen", "office").                                         |
| `date_limit`       | `Date`          | Date limite de suppression automatique de l'événement.                                             |
| `section`          | `Integer`       | Référence vers l'entité `Section` (clé étrangère).                                                 |

#### Relations :
- `event_frequence` : Relation One-to-One avec une fréquence d'événement.
- `section` : Relation One-to-One avec une section spécifique.

#### Entité `Event_Task`
Représente un événement spécifique de type tâche, héritant des propriétés de `Event`.

| Champ             | Type            | Description                                                                                       |
|-------------------|-----------------|---------------------------------------------------------------------------------------------------|
| `id`              | `Integer`       | Identifiant unique de la tâche (hérité de `Event`).                                                |
| `task_details`     | `Text`          | Détails supplémentaires concernant la tâche.                                                      |
| `task_status`      | `String`        | Statut de la tâche (`todo`, `pending`, `done`, `late`, `unrealised`).                              |
| `tag_task_active`  | `JSON`          | Structure de données JSON comptabilisant les tâches actives pour chaque section par jour. Exemple : `{ "sectionId": { "active_day_integer": count } }`. |

#### Relations :
- Inhérente à `Event`.

#### Entité `Event_Info`
Représente un événement spécifique de type information, héritant des propriétés de `Event`.

| Champ             | Type            | Description                                                                                       |
|-------------------|-----------------|---------------------------------------------------------------------------------------------------|
| `id`              | `Integer`       | Identifiant unique de l'information (hérité de `Event`).                                           |
| `unreadUsers`      | `Array`         | Liste des identifiants des utilisateurs n'ayant pas encore lu l'information.                       |
| `tag_info_active`  | `JSON`          | Structure de données JSON comptabilisant les informations non lues pour chaque section et utilisateur par jour. Exemple : `{ "sectionId": { "userId": { "active_day_integer": count } } }`. |

#### Relations :
- Inhérente à `Event`.

#### Entité `Event_Frequence`
Représente les détails de fréquence pour les événements récurrents.

| Champ       | Type     | Description                                                                                      |
|-------------|----------|--------------------------------------------------------------------------------------------------|
| `day`       | `Integer`| Jour de la semaine pour les événements récurrents hebdomadaires (1-7, 8 = illimité).           |
| `monthDay`  | `Integer`| Jour du mois pour les événements récurrents mensuels.                                          |

#### Relations :
- Aucun.

#### Entité `User_Events`
Représente les événements auxquels l'utilisateur a participé, classés par section et chronologie.

| Champ              | Type     | Description                                                                                     |
|--------------------|----------|-------------------------------------------------------------------------------------------------|
| `recurringEvents`  | `Array`  | Tableau associatif contenant les IDs des événements récurrents créés/modifiés par l'utilisateur et leur section. |
| `infoEvents`       | `Array`  | Tableau associatif contenant les IDs des événements info créés/modifiés par l'utilisateur et leur section.  |
| `taskEvents`       | `Array`  | Tableau associatif contenant les IDs des événements task créés/modifiés par l'utilisateur et leur section. |

#### Relations :
- User.

#### Entité `Supplier`
Représente les fournisseurs d'événements.

| Champ             | Type     | Description                                                                                     |
|-------------------|----------|-------------------------------------------------------------------------------------------------|
| `id`              | `Integer`| Identifiant du fournisseur.                                                                     |
| `recuring_events` | `Array`  | Tableau des IDs des événements récurrents associés au fournisseur.                              |

#### Entité `Section`
Représente une section dans laquelle les événements peuvent être organisés.

| Champ              | Type            | Description                                                                                       |
|--------------------|-----------------|---------------------------------------------------------------------------------------------------|
| `id`               | `Integer`       | Identifiant unique de la section.                                                                  |
| `name`             | `String`        | Nom de la section.                                                                                |                                                   |
                                        |
                                       |

