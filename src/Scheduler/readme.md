/**
     * Exécution du Scheduler.
     *
     * Après avoir défini le calendrier avec un message récurrent toutes les minutes, 
     * tu dois lancer le consommateur de messages pour que le scheduler fonctionne.
     * Utilise la commande suivante pour démarrer le consommateur de messages :
     *
     *     php bin/console messenger:consume scheduler_CleanupTokensSchedule -vv
     *     pour voir les outputs en console executer manuellement php bin/console app:cleanup-tokens
     *
     * Cette commande va écouter et consommer les messages asynchrones, y compris ceux 
     * envoyés par le scheduler toutes les minutes.
     */