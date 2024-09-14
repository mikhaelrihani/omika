<?php

namespace App\Command;

use App\Entity\RefreshToken;
use App\Service\TokenCleanupService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:cleanup-tokens',
    description: 'Nettoie les tokens obsolètes.'
)]
class CleanupTokensCommand extends Command
{
    /**
     * Service utilisé pour effectuer le nettoyage des tokens.
     *
     * @var TokenCleanupService
     */
    private $tokenCleanupService;

    /**
     * Gestionnaire d'entités Doctrine pour interagir avec la base de données.
     *
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * Constructeur de la commande.
     *
     * @param TokenCleanupService $tokenCleanupService Service pour nettoyer les tokens.
     * @param EntityManagerInterface $entityManager Gestionnaire d'entités pour accéder aux tokens.
     */
    public function __construct(TokenCleanupService $tokenCleanupService, EntityManagerInterface $entityManager)
    {
        parent::__construct();
        $this->tokenCleanupService = $tokenCleanupService;
        $this->entityManager = $entityManager;
    }

    /**
     * Configure la commande avec une description et une aide supplémentaire.
     */
    protected function configure()
    {
        $this
            ->setDescription('Cleans up old refresh tokens if their number exceeds a certain threshold.')
            ->setHelp('This command allows you to clean up old refresh tokens in the database.');
    }

    /**
     * Exécution principale de la commande.
     *
     * Cette méthode effectue un comptage initial des tokens à nettoyer. Si le nombre de tokens est supérieur
     * à un certain seuil, elle délègue le processus de nettoyage au service `TokenCleanupService`.
     *
     * Note importante : le comptage des tokens après chaque itération dans le processus de nettoyage ne peut
     * pas se baser directement sur un rafraîchissement de la base de données après chaque itération. Cela est dû
     * au fait que la commande Gesdinet (utilisée pour révoquer les tokens) ne flush/actualise pas les changements
     * dans la base de données à chaque itération. Le nombre de tokens restant peut donc sembler incorrect durant
     * la boucle de nettoyage, car Doctrine n'a pas encore répercuté les suppressions en base. Il a été nécessaire
     * d'adapter le comportement pour éviter de se fier au `count()` après chaque processus de suppression.
     *
     * @param InputInterface $input Interface d'entrée (non utilisée ici).
     * @param OutputInterface $output Interface de sortie pour afficher les messages dans la console.
     * @return int Code de retour : Command::SUCCESS ou Command::FAILURE.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            // Compter le nombre de refresh tokens dans la base de données avant de commencer le nettoyage
            $count = $this->entityManager->getRepository(RefreshToken::class)->count([]);
            
            if (!$count) {
                // Aucun token à nettoyer
                $output->writeln('No tokens to clean up.');
                return Command::SUCCESS;
            }
            
            // Début du processus de nettoyage
            $output->writeln('Starting token cleanup process...');
            
            // Délégation du processus de nettoyage au service TokenCleanupService
            // Le comptage ne peut pas être actualisé à chaque itération à cause de la manière dont Gesdinet
            // gère les flush de la base de données
            $this->tokenCleanupService->performCleanup($output, $count);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            // Gestion des erreurs et affichage d'un message d'erreur
            $output->writeln('Error: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
