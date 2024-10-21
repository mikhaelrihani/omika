# Entités liées aux événements

## 1. Entité `Event`
```json
{
  "id": "string",                // Identifiant unique de l'événement
  "type": "string",              // Type d'événement (task ou info)
  "importance": "boolean",       // Indique si l'événement est important
  "shared_with": ["string"],     // Tableau JSON des utilisateurs avec qui l'événement est partagé
  "date_created": "datetime",    // Date de création de l'événement
  "date_limit": "datetime",      // Date limite pour la visibilité (un mois après "done" ou lecture par tous)
  "status": "string",            // Statut de l'événement (par exemple, "done", "pending", "not_view")
  "active_day_range": "integer", // Plage de jours actifs (par exemple, de -3 à +7 jours)
  "description": "string"        // Détails de l'événement
}
```

## 2. Entité `Event_Task` (hérite de Event)
```json
{
  "id": "string",                // Identifiant unique de la tâche
  "type": "task",                // Type de l'événement (task)
  "task_details": "string",      // Détails spécifiques à la tâche
  "task_status_active_range": {  // Tableau associatif JSON des statuts des tâches
    "date": "string",            // Date active
    "status": "string"           // Statut de la tâche (todo, pending, late, done)
  },
  "task_status_off_range": {     // Statuts des tâches hors de la plage active
    "date": "string",            // Date
    "status": "string"           // Statut de la tâche
  }
}
```
## 3. Entité `Event_Info`(hérite de Event)
```json
{
  "id": "string",                // Identifiant unique de l'info
  "type": "info",                // Type de l'événement (info)
  "tag_info_active_range": {     // Plage active des infos non lues
    "date": "string",            // Date active
    "not_view": "integer"        // Nombre d'infos non lues par utilisateur
  },
  "tag_info_off_range": {        // Infos non lues hors de la plage active
    "date": "string",            // Date
    "not_view": "integer"        // Nombre d'infos non lues
  },
  "read_users": ["string"]       // Liste des utilisateurs ayant lu l'info
}
