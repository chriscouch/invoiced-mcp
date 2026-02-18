<?php

namespace App\Command;

use App\Entity\CustomerAdmin\NewAccount;
use App\Repository\CustomerAdmin\OrderRepository;
use App\Service\NewCompanyCreator;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

/**
 * @property LoggerInterface $logger
 */
class DailyTasksCommand extends Command implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    protected static $defaultName = 'app:daily-tasks';

    public function __construct(private OrderRepository $orderRepository, private NewCompanyCreator $creator, private ManagerRegistry $doctrine)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Daily cron tasks')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $this->provisionNewAccounts($io);

        return Command::SUCCESS;
    }

    private function provisionNewAccounts(SymfonyStyle $io): void
    {
        try {
            $io->note('Provisioning new accounts');

            $orders = $this->orderRepository->getOpenNewAccountOrders();

            $n = 0;
            foreach ($orders as $order) {
                // Create the new account
                $newAccount = $order->getNewAccount();
                if (!($newAccount instanceof NewAccount)) {
                    throw new RuntimeException('Missing new account parameters');
                }
                $result = $this->creator->create($newAccount);

                // Update the order with details
                $order->setCreatedTenant($result->id);
                if ($contract = $order->getContract()) {
                    $contract->addTenantId($result->id);
                }
                $order->markFulfilledBySystem();
                /** @var ObjectManager $em */
                $em = $this->doctrine->getManagerForClass(get_class($order));
                $em->persist($order);
                $em->flush();

                ++$n;
            }

            $io->success("$n accounts created");
        } catch (Throwable $e) {
            $this->logger->error('Provisioning new accounts failed', ['exception' => $e]);
        }
    }
}
