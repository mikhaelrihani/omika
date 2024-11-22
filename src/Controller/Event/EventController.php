<?php

namespace App\Controller\Event;

use App\Repository\Event\EventRepository;
use App\Service\Event\EventService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;


#[Route('/api/event', name: "app_event_")]
class EventController extends AbstractController
{
    public function __construct(private EventService $eventService, private EventRepository $eventRepository)
    {
    }

    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $events = $this->eventRepository->findAll();
        return $this->json($events, 200, [], ['groups' => 'event']);
    }

    #[Route('/{id}', name: 'getEvent', methods: ['GET'])]
    public function getEvent(int $id): JsonResponse
    {
        $event = $this->eventRepository->find($id);
        return $this->json($event, 200, [], ['groups' => 'event']);
    }

    //! event map  

    // un event est le résultat d'une interaction interne a l'app(ie: passer une cde en soumettant un form) ou externe(realiser une tache demandée depuis l'app mais réalisé a l'extérieur, ie: ranger l'economat) de la part de l'utilisateur

    // l'interface de l'app a pour but de rendre visible tous les events (taches ou info) pour un jour demandé par l'utilisateur et de classer les taks en fonction de leur status done or todo,
    // les infos elles sont visible sur un meme bloc.

    // infos générales event:
    // - optimisation des requêtes (active_day_range), requêtes classique off range(past, future)
    // - automatisation de la valeur du status(todo, done) des tasks réalisées en interne(on utilisera alors les champs task_status_active_range/task_status_off_range et le champs task_détails), manuel par user si externe.
    // - optimisation des requetes pour les tags des infos/tasks avec un champs tag_info_active_range/tag_info_off_range, tag_task_active_range/tag_task_off_range)


    // Pour faciliter et optimiser le temps de réponse des requete afin d'afficher les events d'une date précise et afin de ne pas devoir passer en revue tous les events en bdd ,
    // un champ active_day_range avec une valeur integer entier de -3 a +7 (3 days before and 1 week after today , 10 days range) va nous permettre de faire une recherche rapide sur un index,
    // index qui est le résultat d'un travail cron et qui effectue ces grosses requêtes daily a minuit ;
    // dans le cas ou l utilisateur demande de voir les events d'une date postérieur ou antérieur a ce range de 10 jours alors on est forcé de faire ces requetes longues en real time.

    // un champ task_status_active_range avec un array json associatif ou la/les valeur active_day_range est/sont associée a la valeur du status  de la task  "done" or "todo".
    // un champ task_status_off_range avec un array json associatif ou la/les valeur date demandé de réalisation de la tache est/sont associée a la valeur du status  de la task "done" or "todo".
    // pour les tasks dont la récurrence est unlimited ou périodique/hebdomadaire on limite le nombre de clé/valeur a une période de 2 mois;
    // cela veut dire que l'on peut prévoir une tache a faire meme dans 1 an, mais celle ci ne pourra etre visible que si elle est a faire dans moins de 2 mois.
    // la valeur de ces array json est mis a jour par un travail cron a minuit daily.
    // tant qu'un event task n'a pas été réalisé le jour souhaité alors le champ active day_range ne sera pas amputé de un jour , on garde donc les memes valeurs integer, plus un event infos alerte est crée.
    // pour connaitre la valeur des tags des tasks on utilise deux champs de event section_event(carte,cde, inventory...) et le champs tag_tasks_active_range/tag_task_off_range avec un array json associatif
    // ou la/les valeur active_day_range/date est/sont associée a la valeur du nombre integer de taches a effectuer.
    // ces valeurs sont mises a jour a chaque interaction user avec l interface lorsque l user fait une taches interne/externe ou lors de la création d'une tache, en plus du travail cron job.

    // if the event type is info :
    // chaque info event a une durée de vie de 1 mois(champ event_limit_date) après sa date souhaité de prise de connaissance avant d'être supprimé de la bdd par un cron job a minuit;
    // ceci va permettre de garder une trace de cette info dans une recherche sur le passé.
    // a chaque form submit un event info est généré
    // un champ info_status_active_range avec un array json associatif ou la/les valeur active_day_range est/sont associée a la valeur du status  de l'info'  "view" or "not_view".
    // un champ info_status_off_range avec un array json associatif ou la/les valeur date de visibilité de l'info est/sont associée a la valeur du status de l'info'  "view" or "not_view".
    // lorsque l user ouvre la section souhaité cote info , alors le tag comptabilisant les nouvelles infos sera remis a zero mais les infos restent présente
    // ce qui permet a l user connecté de savoir si il y a de nouvelles infos 
    // pour connaitre la valeur des tags des infos on utilise deux champs de event section_event(carte,cde, inventory...) et le champs tag_infos_active_range/tag_info_off_range avec un array json associatif
    // ou la/les valeur active_day_range/date est/sont associée a la valeur du nombre integer de infos not view par user.
    // ces valeurs sont mises a jour a chaue interaction user avec l interface pour voir les infos ou lors de la création de infos.

    // if the event type is task :
    // chaque task event a une duréé de vie de 1 mois(champ event_last_time_displayed_date) après sa réalisation avant d'être supprimé de la bdd par un cron job a minuit;
    // ceci va permettre de garder une trace de cette tache dans une recherche sur le passé.
    // lorsqu'un event task est marqué comme done alors le tag comptabilisant est décrementé de un et vice versa a sa création
    // utiliser le champs/propriétée task_détails dont la valeur serait construite en fonction de la section => task_commande_...,
    // puis en ajoutant d'autres indices comme le nom du fournisseur qui doivent nous amener pour chaque section a trouver l'entrée en bdd qui corespond a une tache précise
    // avec le status todo, car cette tache une fois que l user l a fini doit etre identifiable pour etre basculée vers done
    // on imagine que l user passe une cde alors on va rechercher un match de valeur sur le champs task_détails et le champ active_day_range, ou off range si non trouvé , si match alors on bascule le status en done(ie: la valeur serait commande_suppliername) 
    // lorsque l user realise un tache qui ne renvoie pas d'event alors l user bascule manuelement la valeur du status manuelement en glissant le status de la tache de done a todo, les infos seraient affichéés en dessous 





    //! divers flows
    // afficher les événements by check if they are visible for this user,
    // en récupérant les events en fonction de leur type, side,field active_day_range
    // créer un event 
    // créer un service pour validation 
    // créer des groups pour récupérer les propriétés de l´entité events sans références circulaires
    // méthode pour compter les events de chaque section pour afficher les badges dans la page mes événements
    // modifier un event par id
    // supprimer un event par id
    // Set Up all the Cron Job  to delete and updates Events using the following:
    // verifier/informer si le cron job a bien été réalise sans bug chaque jour avec des tests
    // construire des index en bdd par ex Une recherche rapide pourra être faite sur un index du champ task_details et active_day_range
    // méthode pour modifier des events automatique (ie: le supplier a des jours précis de cde affichés sur le descriptif de celui-ci et modifiable par l'user)

}
