<?php

namespace App\Service\Event;

use App\Entity\Event\Event;
use App\Entity\Event\EventRecurring;
use App\Repository\Event\EventRecurringRepository;
use App\Repository\Event\EventRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use App\Service\ResponseService;

class EventRecurringService
{
    protected $now;
    protected $activeDayStart;
    protected $activeDayEnd;

    public function __construct(
        protected EventRecurringRepository $eventRecurringRepository,
        protected EventRepository $eventRepository,
        protected EntityManagerInterface $em,
        protected EventService $eventService,
        protected ParameterBagInterface $parameterBag
    ) {
        $this->now = DateTimeImmutable::createFromFormat('Y-m-d ', (new DateTimeImmutable())->format('Y-m-d'));
        $this->activeDayStart = $this->parameterBag->get('active_day_start');
        $this->activeDayEnd = $this->parameterBag->get('active_day_end');
    }

    /**
     * Récupère tous les événements parents récurrents.
     *
     * @return ResponseService
     */
    public function getParents(): ResponseService
    {
        try {
            $parents = $this->eventRecurringRepository->findAll();
            return ResponseService::success('Parents events retrieved successfully', ['parents' => $parents]);
        } catch (\Exception $e) {
            return ResponseService::error('Error retrieving parent events: ' . $e->getMessage());
        }
    }

    /**
     * Récupère tous les événements enfants récurrents.
     *
     * @return ResponseService
     */
    public function getChildrens(): ResponseService
    {
        try {
            $childrens = $this->eventRepository->findBy(["isRecurring" => true]);
            return ResponseService::success('Children events retrieved successfully', ['childrens' => $childrens]);
        } catch (\Exception $e) {
            return ResponseService::error('Error retrieving children events: ' . $e->getMessage());
        }
    }

    /**
     * Gère le type de récurrence de l'événement parent.
     *
     * @param EventRecurring $parent L'événement parent récurrent.
     * @return ResponseService
     */
    public function handleRecurrenceType(EventRecurring $parent): ResponseService
    {
        try {
            $recurrenceType = $parent->getRecurrenceType();
            switch ($recurrenceType) {
                case "monthDays":
                    $this->handleMonthDays($parent);
                    break;
                case "weekDays":
                    $this->handleWeekdays($parent);
                    break;
                case "periodDates":
                    $this->handlePeriodDates($parent);
                    break;
                case "everyday":
                    $this->handleEveryday($parent);
                    break;
                default:
                    return ResponseService::error('Recurrence type not found');
            }
            return ResponseService::success('Recurrence handled successfully');
        } catch (\Exception $e) {
            return ResponseService::error('Error handling recurrence type: ' . $e->getMessage());
        }
    }

    /**
     * Gère la récurrence par jours de mois.
     *
     * @param EventRecurring $parent
     * @return ResponseService
     */
    public function handleMonthDays(EventRecurring $parent): ResponseService
    {
        try {
            $monthDays = $parent->getMonthDays();
            [$earliestCreationDate, $latestCreationDate] = $this->calculatePeriodLimits($parent);

            $currentMonthDate = $earliestCreationDate->modify('first day of this month');
            $endPeriodDate = $latestCreationDate->modify('first day of next month');

            while ($currentMonthDate <= $endPeriodDate) {
                foreach ($monthDays as $monthDay) {
                    $day = $monthDay->getDay();
                    $dueDate = $currentMonthDate->modify("+{$day} days -1 day");

                    if ($this->isWithinTargetPeriod($dueDate, $earliestCreationDate, $latestCreationDate)) {
                        $this->createOneEverydayChild($parent, $dueDate);
                    }
                }
                $currentMonthDate = $currentMonthDate->modify('first day of next month');
            }
            return ResponseService::success('Month days recurrence handled successfully');
        } catch (\Exception $e) {
            return ResponseService::error('Error handling month days recurrence: ' . $e->getMessage());
        }
    }

    /**
     * Gère la récurrence par jours de la semaine.
     *
     * @param EventRecurring $parent
     * @return ResponseService
     */
    public function handleWeekdays(EventRecurring $parent): ResponseService
    {
        try {
            $weekDays = $parent->getWeekDays();
            [$earliestCreationDate, $latestCreationDate] = $this->calculatePeriodLimits($parent);

            $currentWeekDate = $earliestCreationDate->modify('this week');

            while ($currentWeekDate <= $latestCreationDate) {
                foreach ($weekDays as $weekDay) {
                    $day = $weekDay->getDay();
                    $dueDate = $currentWeekDate->modify("+{$day} days -1 day");

                    if ($this->isWithinTargetPeriod($dueDate, $earliestCreationDate, $latestCreationDate)) {
                        $this->createOneEverydayChild($parent, $dueDate);
                    }
                }
                $currentWeekDate = $currentWeekDate->modify('next week');
            }
            return ResponseService::success('Weekdays recurrence handled successfully');
        } catch (\Exception $e) {
            return ResponseService::error('Error handling weekdays recurrence: ' . $e->getMessage());
        }
    }

    /**
     * Gère la récurrence par dates fixes.
     *
     * @param EventRecurring $parent
     * @return ResponseService
     */
    public function handlePeriodDates(EventRecurring $parent): ResponseService
    {
        try {
            [$earliestCreationDate, $latestCreationDate] = $this->calculatePeriodLimits($parent);
            $dates = $parent->getPeriodDates();

            foreach ($dates as $date) {
                $dueDate = $date->getDate();

                if ($this->isWithinTargetPeriod($dueDate, $earliestCreationDate, $latestCreationDate)) {
                    $this->createOneEverydayChild($parent, $dueDate);
                }
            }
            return ResponseService::success('Period dates recurrence handled successfully');
        } catch (\Exception $e) {
            return ResponseService::error('Error handling period dates recurrence: ' . $e->getMessage());
        }
    }

    /**
     * Gère la récurrence tous les jours.
     *
     * @param EventRecurring $parent
     * @return ResponseService
     */
    public function handleEveryday(EventRecurring $parent): ResponseService
    {
        try {
            [$earliestCreationDate, $latestCreationDate] = $this->calculatePeriodLimits($parent);
            $diff = (int) $earliestCreationDate->diff($latestCreationDate)->format('%r%a');

            for ($i = 0; $i <= $diff; $i++) {
                $dueDate = $earliestCreationDate->modify("+{$i} day");

                if ($this->isWithinTargetPeriod($dueDate, $earliestCreationDate, $latestCreationDate)) {
                    $this->createOneEverydayChild($parent, $dueDate);
                }
            }
            return ResponseService::success('Everyday recurrence handled successfully');
        } catch (\Exception $e) {
            return ResponseService::error('Error handling everyday recurrence: ' . $e->getMessage());
        }
    }

    /**
     * Vérifie si une date est dans la période cible.
     *
     * @param DateTimeImmutable $dueDate
     * @param DateTimeImmutable $earliestCreationDate
     * @param DateTimeImmutable $latestCreationDate
     * @return bool
     */
    public function isWithinTargetPeriod(DateTimeImmutable $dueDate, DateTimeImmutable $earliestCreationDate, DateTimeImmutable $latestCreationDate): bool
    {
        return $dueDate >= $earliestCreationDate && $dueDate <= $latestCreationDate;
    }

    /**
     * Calcule les limites de la période en fonction des paramètres de l'événement récurrent.
     *
     * @param EventRecurring $parent
     * @return array
     */
    private function calculatePeriodLimits(EventRecurring $parent): array|ResponseService
    {
        try {
            $latestDate = (new DateTimeImmutable($this->now->format('Y-m-d')))->modify("+{$this->activeDayEnd} day");
            $latestCreationDate = min($latestDate, $parent->getPeriodeEnd());

            $earliestCreationDate = max($this->now, $parent->getPeriodeStart());
            $earliestCreationDate = new DateTimeImmutable($earliestCreationDate->format('Y-m-d'));

            return [$earliestCreationDate, $latestCreationDate];
        } catch (\Exception $e) {
            return ResponseService::error('Error calculating period limits: ' . $e->getMessage());
        }
    }

    /**
     * Crée un événement enfant pour un événement récurrent.
     *
     * @param EventRecurring $parent
     * @param DateTimeImmutable $dueDate
     * @return void
     */
    public function createOneEverydayChild(EventRecurring $parent, DateTimeImmutable $dueDate): ResponseService
    {
        try {
            $child = $this->setOneChildBase($parent);
            $child->setDueDate($dueDate);
            $this->eventService->setTimestamps($child);

            $users = $parent->getSharedWith();
            $status = $parent->getType() ? "todo" : null;
            $this->eventService->setRelations($child, $status, $users);

            $this->em->persist($child);
            $parent->addEvent($child);
            $this->em->flush();
            return ResponseService::success('Child event created successfully');
        } catch (\Exception $e) {
            return ResponseService::error('Error creating child event: ' . $e->getMessage());
        }
    }

    /**
     * Définit les propriétés de base d'un événement enfant.
     *
     * @param EventRecurring $parent
     * @return Event
     */
    public function setOneChildBase(EventRecurring $parent): Event
    {
        $child = (new Event())
            ->setDescription($parent->getDescription())
            ->setIsImportant(false)
            ->setSide($parent->getSide())
            ->setTitle($parent->getTitle())
            ->setCreatedBy($parent->getCreatedBy()->getFullName())
            ->setUpdatedBy($parent->getUpdatedBy()->getFullName())
            ->setType($parent->getType())
            ->setSection($parent->getSection())
            ->setIsRecurring(true);

        return $child;
    }
}
