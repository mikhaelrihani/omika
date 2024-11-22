

# Gestion des événements et tags et optimisation des requêtes

Cette application permet à l'utilisateur de consulter rapidement tous les événements (tâches ou informations) d'une journée donnée. L'objectif est d'optimiser les requêtes pour un affichage rapide, d'automatiser la gestion des statuts des tâches et d'améliorer la gestion des tags.

Il est primordial de comprendre qu'un événement ou un tag (count) correspond à un seul jour ; un événement récurrent est défini sur une période avec une fin ou de manière illimitée. 

Les événements sont supprimés automatiquement après 30 jours suivant leur date de presentation "duedate" les tags de meme avec "day" via un **cron job** quotidien a minuit.

## 1. Optimisation des requêtes

### 1.1 active day range
- **`active_day_range`** est une configuration qui définit une plage de jours autour de la date actuelle pour filtrer les événements actifs, de 3 jours avant à 7(variable dans .env).
- **Champ `active_day`** : Ce champ contient un entier compris entre -3 et 7 qui représente une date relative (par exemple, aujourd'hui = 0, hier = -1, demain = 1, etc.). 
Si active_day est nul, alors l'événement est considéré hors de la fenêtre de temps définie par active_day_range.
Ce champ optimise les performances en limitant les recherches aux événements récents ou imminents.
- Mise à jour quotidienne du **Champ `active_day`** via un **cron job**, qui ajuste la valeur en fonction de la date actuelle.

### 1.2 Gestion du `date_status`
- **Champ `date_status`** : Classifie les événements en fonction de leur statut temporel :
  - `past` : événements passés.
  - `active_day_range` :événements autour de la date actuelle, sur une plage de 10 jours.
  - `future` : événements futurs.

### 1.3 Cache des sessions
- Les événements sont mis en cache pour chaque session utilisateur, réduisant les appels à la base de données lorsque l’utilisateur navigue sur plusieurs jours.
Attention : Le cache pourrait ne pas prendre en compte une modification en temps réel !

### 1.4 Les 6 Indexes Composites

1. **Index composite sur `date_status` et `active_day` dans la table `Event`**
   - **Utilité** : Sélectionne directement les événements actifs dans la plage `active_day_range` et permet d’afficher rapidement les événements ou tags pour une date précise.
   - **Avantage** : Accélère l'accès aux événements actifs, optimisant la performance des requêtes.

2. **Index composite sur `date_status` et `due_date` dans la table `Event`**
   - **Utilité** : Gère efficacement les événements futurs et passés en fonction de leur date de réalisation initiale, garantissant le statut exact de chaque événement (futur, passé, actif).
   - **Avantage** : Optimise les requêtes pour les événements en dehors de la plage `active_day_range`, facilitant l'accès aux données historiques ou à venir tout en réduisant la charge de requêtes.

3. **Index composite sur `periode_start` et `periode_end` dans la table `EventRecurring`**
   - **Utilité** : Filtre les événements récurrents selon leur `date_status` (futur ou passé) et vérifie si une date donnée tombe entre `periode_start` et `periode_end`.
   - **Avantage** : Évite de traiter des événements récurrents non pertinents, réduisant ainsi la charge sur la base de données.

4. **Index composite sur `date_status` et `active_day` dans la table `tag`**
   - **Utilité** : Permet de récupérer les tags et événements actifs dans la plage `active_day_range` pour une date donnée.
   - **Avantage** : Accélère l’affichage des tags pour les dates les plus consultées, optimisant l'expérience utilisateur.

5. **Index composite sur `date_status` et `day` dans la table `tag`**
   - **Utilité** : Récupère les tags et événements actifs pour une date en dehors de la plage `active_day_range`, facilitant l'accès aux données qui ne sont pas incluses dans cette plage active.
   - **Avantage** : Assure la disponibilité des événements moins consultés, sans ralentir les requêtes principales.

6. **Index composite sur `user_id` et `tag_id` dans la table `TagInfo`**
   - **Utilité** : Récupère les informations spécifiques à l’utilisateur connecté pour chaque tag identifié, permettant d’afficher le nombre d'informations non lues par section.
   - **Avantage** : Simplifie l'accès aux données personnelles de chaque utilisateur, offrant un suivi des notifications en temps réel.

## 2. Gestion des événements, tâches et informations
- Les événements sont visibles uniquement sur leur `due_date`.
- Les événements et événements récurrents ne sont inscrits en base de données que s'ils font partie de la plage `active_day_range`. Un cron job met à jour quotidiennement la base de données en inscrivant chaque événement (en observant la `due_date`) ou événement récurrent (en observant leur période) qui entre dans la fenêtre de temps `active_day_range`.
- Dans les cas où une tâche est en statut `late` ou `pending`, l'événement tâche est dupliqué et enregistré en base de données au lendemain. Cette duplication continue chaque jour jusqu'à ce que le statut de la tâche passe à `done` ou `unrealised`. Cette mise à jour est effectuée par un cron job chaque jour à minuit. La duplication a lieu uniquement après vérification que l'événement tâche ne fait pas partie d'un groupe d'événements récurrents et que la période de l'événement récurrent est active le lendemain. Cela permet d'éviter d'avoir deux tâches identiques.
- L'événement tâche de la veille qui a généré cette duplication sera marqué comme `unrealised`.
- Les événements de type `info`, quant à eux, n'ont pas de statut ; cependant, une logique métier est appliquée au niveau des tags pour signaler les nouveaux événements d'information, c'est-à-dire ceux non lus par l'utilisateur connecté.
- Les statuts des événements dans le passé sont immuables.

### 2.1 Gestion des tâches
- **Statuts des tâches** :
  - `todo`, `done`, `late`, `pending`, `unrealised`, `warning`, `modified`.
- Chaque événement tâche créé par un utilisateur est initialisé avec un statut `todo` par défaut.
- Le statut `late` est utilisé pour marquer un événement tâche comme non réalisé mais représenté aujourd'hui.
- Le statut `unrealised` est utilisé pour marquer les événements de la veille lorsque qu'un événement tâche est marqué comme `late` à la date d'aujourd'hui. Ce statut ne changera pas dans le passé.
- Le statut `warning` est utilisé pour indiquer à l'utilisateur qu'un événement doit être reconsidéré car il a été classé obsolète en raison d'un changement dans son événement récurrent qui l'a créé.
- L'utilisateur peut également supprimer manuellement un événement tâche. Dans ce cas, un événement info, avec un tag important, est créé pour en aviser les autres utilisateurs et garder une trace de cette décision.
- Le statut `todo_modified` est utilisé lorsque l'utilisateur a modifié la description ou tout autre champ, accessible à la modification depuis l'interface, de l'événement tâche.
ce statut est uniquement utilisé pour les evenements enfant d'un eventRecurring(isRecurring=true);
il a pour objectif de conserver les modifications apportés par un user lorsque son eventRecurring parent est modifié.La procédure de modification du parent est de supprimer tous les enfants qui ont un statut `todo` et de changer le status des autres (inscrits en bdd) à `warning`.
ici nous somme dans le cas ou l'event enfant est toujours en pseudo todo car toujours a faire , cad qu'il est inscrit en bdd meme hors activedayrange mais avec un status `todo_modified` .ainsi il peut passer en warning aussi en cas de modification sur l'eventRecurring parent.
Sur l'interface le tag est ecrit `todo` , ici le status `todo_modified` est utilie a a logique metier uniquement.
- le status late n'existe que a la date de today.


### 2.2 Gestion des événements d'information
- **Champ `unreadUsers`** : Tableau contenant les utilisateurs n'ayant pas encore lu l'information.
- Dans le cas où un utilisateur n'a pas lu une info, l'événement info est créé par duplication et donc inscrit en base de données avec une période active d'un jour (aujourd'hui) tant que l'utilisateur est présent dans le champ **`shared_with`**. Cette actualisation se fait par un cron job à minuit.
- L'utilisateur peut également supprimer manuellement un événement de type "Info", sous réserve que tous les utilisateurs l'aient lu. Pour cela, on utilise le champ `userReadInfoCount`, qui est un entier s'incrémentant dès qu'un utilisateur lit l'info ; lorsque la valeur du champ `userReadInfoCount` est égale au nombre d'utilisateurs `sharedWithCount`, alors on passe la valeur du booléen `isFullyRead` à true, ce qui permet de valider la suppression souhaitée de l'info.
- Un champ `sharedWith` dans l'entité `eventInfo` est utilisé pour vérifier si l'événement info peut être affiché pour cet utilisateur.


## 3. Champs spécifiques des entités autour des événements

### Chapitre entité Event
- **`isRecurring`** : booléen utilisé pour trier/séparer les événements récurrents des événements affichables sur l'interface.
- **`eventRecurring`** : entier représentant la relation de l'instance `eventRecurring` à laquelle cet événement est attaché. Permet de récupérer les événements qui doivent être pris en compte lors d'une modification de l'événement récurrent parent.
- **`task`** : entier représentant la relation de l'instance `task` dans laquelle on retrouve les infos propres à un événement tâche (voir chapitre entité task).
- **`info`** : entier représentant la relation de l'instance `info` dans laquelle on retrouve les infos propres à un événement info (voir chapitre entité info).
- **`dueDate`** : date à laquelle l'événement est dû et doit être visible sur l'interface.
- **`date_status`** : peut avoir l'une des valeurs suivantes : `past`, `activeDayRange`, `future`. Permet de filtrer les événements à rechercher pour optimiser les requêtes.
- **`active_day`** : entier représentant la valeur d'une date entre -3 et +7 (définie par `activeDayRange` dans le fichier .env). La valeur est modifiée chaque jour par un cron job à minuit pour garantir la cohérence des dates par rapport à `datenow()`, qui est aujourd'hui et a pour valeur 0. Par exemple, demain a une valeur de 1 et hier -1. Ce champ existe pour optimiser les requêtes en utilisant des index. Si la `dueDate` est hors du champ `activeDayRange`, alors ce champ est nul.
- **`side`** : indique si l'événement appartient à "kitchen" ou "office".
- **`type`** : spécifie si l'événement est de type "info" ou "task".
- **`section`** : sur l'interface, les événements sont filtrés d'abord sur leur `side`, puis sur leur `type`, et enfin par `section` (carte, recette, fournisseurs, inventaire, planning, etc.). Chaque section étant propre à "kitchen" ou "office", mais pouvant être associée à une info ou une tâche.
- **`title`** : titre pour visualiser rapidement le sujet lors du listing des événements d'une recherche.
- **`description`** : description de la tâche à effectuer ou de l'information à lire.
- **`createdBy`** : auteur original (application ou utilisateur).
- **`updatedBy`** : auteur de la mise à jour (application ou utilisateur).
- **`isImportant`** : permet de mettre en évidence un événement sur l'interface, le considérant comme prioritaire, par exemple.
- **`favoritedBy`** : permet de mettre en évidence sur l'interface un événement favori de l'utilisateur connecté.

### Chapitre entité Task
- **Champ `task_status`** : voir le chapitre gestion des tâches.
- **Champ `task_details`** : utilisé pour lier une action utilisateur à une tâche correspondante afin de mettre à jour son statut. Ce champ est généré lors de la création de la tâche, que ce soit par l'application ou par l'utilisateur, et contient toutes les informations nécessaires pour tracer l'action effectuée.
- Le champ `task_details` contient des informations spécifiques telles que la concaténation des valeurs de `event_section` (pour indiquer la section de l'événement) et d'autres indicateurs/références comme `supplierName` (nom du fournisseur) ou d'autres éléments contextuels.

- sur l'interface lorsque je suis sur la page cde pour un supplier donné , je récupère déjà la section(commande), le nom du supplier, et la date de soumission(envoyé ou en cours) de la commande qui correspond a due date.
Si il n y a pas d correspondance a today alors cela veut dire que le user souhaite passer une cde prévue dans le futur proche, dans ce cas on va chercher la prochaine task cde de ce supplier.
- pour un inventaire , l user commence un inventaire et tant que celui ci n est pas finiil est representé avec une duedate = today . donc meme principe que cde on cherche un match duedate/date en plus des parametre propre a l inventaire comme room etc..
Conclusion il semble que l on est pas besoin de task details.
**Cas d'usage** :
- Lorsque l'utilisateur réalise une action sur l'application (comme passer une commande), le statut de la tâche doit être mis à jour en conséquence. Par exemple, une tâche initialement en statut "todo" avec la description "passer une commande chez le fournisseur B" doit passer à un autre statut (comme "done") une fois l'action effectuée par l'utilisateur. Lorsqu'il soumet la commande, on va chercher une correspondance avec une tâche en base de données en utilisant des champs comme `dueDate`, `supplierName`, `section`, etc.
- Cette action/mise à jour/liaison automatique n'est pas possible lorsque l'utilisateur effectue une tâche hors de l'application. Dans ce cas, l'utilisateur doit lui-même passer la commande de gauche à droite pour changer le statut de la tâche en "done". Par contre, si la tâche est en cours, il peut modifier le statut directement à droite sur le tag où est renseigné le statut. On pensera à faire un rappel quotidien ou toutes les deux heures pour que l'utilisateur bascule le statut des tâches hors application.

### Chapitre entité Info
- **Champ `sharedWith`** : liste d'utilisateurs qui peuvent voir l'info. Ce champ est utilisé très fréquemment, par exemple, chaque fois qu'on effectue une requête sur une date, on vérifie ce champ pour déterminer si l'utilisateur connecté peut voir l'info.
- **Champ `shared_with_count`** : entier représentant avec combien d'utilisateurs autorisés l'info a été partagée (mis à jour à la création de l'info ou lors d'une modification).
- **Champ `user_read_info_count`** : entier représentant le nombre d'utilisateurs autorisés ayant lu l'info (on estime que l'info a été lue dès que l'utilisateur clique dessus sur l'interface).
- **Champ `is_fully_read`** : booléen indiquant si tous les utilisateurs autorisés ont lu l'info. Lorsque `user_read_info_count` est égal à `shared_with_count`, on passe le statut du booléen à true. Ce qui permettra plus tard dans l'application, si un utilisateur souhaite supprimer l'info, de le faire, car la suppression est liée à la condition que ce champ soit true. Au plus tard, l'info sera supprimée après une période de 30 jours après sa due date par un cron job.


## 4. Gestion des événements récurrents

### 4.1 Structure des événements récurrents
- Les événements récurrents sont inscrits en base de données lors de leur création par l'utilisateur.
- Ils ne sont pas directement affichés sur l'interface, car leur but est de gérer les événements qui doivent se répéter régulièrement.
- Les événements récurrents peuvent être considérés comme les "parents" des événements individuels récurrents. Ainsi, ils permettent à l'utilisateur de visualiser des événements réguliers sur une période donnée, même si ces événements ne sont pas encore inscrits en base de données.
- Les événements récurrents permettent l'inscription d'événements lorsque leur `dueDate` est dans la fenêtre `active_day_range` ou lorsqu'un utilisateur interagit avec un événement récurrent visible sur l'interface (ex. marquer une info comme lue ou changer le statut d'une tâche de `todo` à `done`).

### 4.2 Champs spécifiques à l'entité `EventRecurring`
- **`periodeStart`** et **`periodeEnd`** : définissent la période durant laquelle les événements récurrents sont créés ou visibles sur l'interface dès la création de cet événement récurrent parent.
- **`events`** : liste des événements enfants récurrents inscrits en base de données. Exemple d'utilisation : lors de la modification d'un événement récurrent parent, tous les événements enfants existants (dans la base de données) doivent être mis à jour pour refléter les changements appliqués à l'événement parent.

#### Processus de mise à jour des événements enfants
1. Suppression de tous les événements info inscrits en base (en vérifiant/supprimant les références `isFavorite` dans l'entité `User`), puis création/inscription (si dans `active_day_range`) des nouveaux événements info "modifiés".
2. Suppression de tous les événements tâches avec un statut `todo`, puis création/inscription (si dans `active_day_range`) des nouveaux événements tâches "modifiés".
3. Signalement à l’utilisateur des événements tâches dont le statut diffère de `todo` : ces événements passent à un statut `warning`, indiquant à l'utilisateur de décider de leur statut obsolète.

- **`periodDates`**, **`weekDays`**, **`monthDays`** : listes de dates ou entiers qui permettent la mise à jour ou l’inscription (par cron job) des nouveaux événements tâches ou infos dans la base, ou permettent de les visualiser à des dates hors de la fenêtre `active_day_range`.
  - Ex. Une recherche sur une date future va récupérer l'entité `EventRecurring` et utiliser ces champs pour afficher ou inscrire les événements tâches/infos.
- **`isEveryday`** : booléen indiquant si un événement récurrent est prévu pour être inscrit ou visible tous les jours entre **`periodeStart`** et **`periodeEnd`**.


## 5. Gestion des événements automatiques de l'application
- **Entité `Supplier`** : Associe des événements récurrents aux fournisseurs via le champ **`recurring_event_children`**, listant les IDs des événements récurrents liés aux commandes ou opérations récurrentes du fournisseur. Cela facilite la modification des événements récurrents pour répondre aux besoins spécifiques d'un fournisseur.


## 6. Gestion des Tags et Comptage

### 6.1 Structure des Tags des Événements
Pour optimiser les requêtes et éviter les calculs en temps réel, chaque **section** génère un tag quotidien pour chaque nouveau jour dans `active_day_range`. Ces tags sont inscrits avec une valeur initiale de zéro et conservés pour l’historique des jours passés (supprimés après 30 jours via un cron job). Cela garantit un nombre minimal de tags inscrits quotidiennement, sans inscription de tags futurs (ceux-ci sont calculés uniquement pour l'affichage sur l'interface).

La création ou la mise à jour d'un événement (hors événements de type `eventRecurring`) dans `active_day_range` affecte directement le tag de la même section et date en incrémentant ou décrémentant son compteur.

### 6.2 Champs spécifiques de l’entité `Tag`
- **`section`** : Section sur laquelle le tag sera affiché (ex : carte, recette, fournisseurs, inventaire, planning).
- **`day`** : Date de prise en compte du tag pour afficher les valeurs correctes sur l’interface.
- **`date_status`** : Indique si la date du tag est dans le passé ou dans `active_day_range`, permettant une optimisation des requêtes en limitant la recherche.
- **`task_count`** : Entier représentant le nombre de tâches associées au tag d'une section pour une date spécifique.
- **`active_day`** : Entier représentant une date relative entre -3 et +7 (selon la variable `activeDayRange` dans `.env`). Cette valeur est mise à jour chaque jour par un cron job pour garantir la cohérence des dates avec `datenow()` et optimiser les requêtes via les index. Ce champ est nul si `day` est en dehors de `activeDayRange`.

### 6.3 Champs spécifiques de l’entité `TagInfo`
Pour le comptage des infos non lues par utilisateur et par section, les informations sont personnalisées selon l’utilisateur connecté. La table pivot `TagInfo` permet de gérer ces valeurs spécifiques en fonction de chaque utilisateur.

- **`tag_id`** : Identifiant du tag concerné.
- **`user_id`** : Identifiant de l'utilisateur concerné.
- **`unread_info_count`** : Entier représentant le nombre d’infos non lues par l’utilisateur pour la section et date correspondante.

#### Gestion des Comptes `unread_info_count`
1. **Date dans `active_day_range` ou passée** : Le champ `unread_info_count` est mis à jour en temps réel :
   - **Création d'un événement `info`** : Incrémentation du compteur pour la section et la date.
   - **Lecture par l’utilisateur** : Décrémentation du compteur lorsqu’une info est marquée comme lue.

2. **Date future (tag non inscrit en base)** : Le calcul du compte d’infos non lues est basé sur la récupération des infos partagées avec l’utilisateur via l’entité `EventSharedInfo`, en excluant celles déjà lues (`isRead`).

#### Gestion des `TagInfo` lors de la Suppression ou Modification d’un `EventRecurring`
En cas de suppression ou modification d’un événement récurrent, les `TagInfo` correspondants dans `active_day_range` sont mis à jour :
- **Suppression de l'info** : Le champ `unread_info_count` est décrémenté.
- **Création d'une nouvelle info** (si la modification recrée un `EventInfo`) : Le compteur `unread_info_count` est incrémenté de 1.










