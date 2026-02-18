<?php

namespace App\Controller;

use App\Entity\CustomerAdmin\AuditEntry;
use App\Entity\CustomerAdmin\User;
use App\Entity\Forms\SqlConsole;
use App\Form\SqlConsoleType;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\UserInterface;
use Throwable;

class SqlConsoleController extends AbstractController
{
    private Connection $connection;
    private UserInterface $currentCsUser;

    public function __construct(Connection $connection, Security $security)
    {
        $this->connection = $connection;
        /** @var User $user */
        $user = $security->getUser();
        $this->currentCsUser = $user;
    }

    private function getForm(Request $request): FormInterface
    {
        $filter = new SqlConsole();

        $form = $this->createForm(SqlConsoleType::class, $filter);

        $form->handleRequest($request);

        return $form;
    }

    #[Route(path: '/admin/sql_console', name: 'sql_console')]
    public function searchForm(Request $request): Response
    {
        $form = $this->getForm($request);
        $parameters = [
            'results' => [],
            'count' => 0,
            'submitted' => 0,
        ];
        if ($form->isSubmitted() && $form->isValid()) {
            /** @var SqlConsole $data */
            $data = $form->getData();
            $sql = $data->getSql();

            $csEntityManger = $this->getDoctrine()->getManager('CustomerAdmin_ORM');
            $entry = new AuditEntry();
            $entry->setTimestamp(new \DateTime());
            $entry->setUser($this->currentCsUser->getUsername());
            $entry->setAction('run_sql_query');
            $entry->setContext($sql);
            $csEntityManger->persist($entry);
            $csEntityManger->flush();

            $this->connection->executeQuery('SET SESSION max_statement_time=30;');
            $this->connection->executeQuery('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED');
            $this->connection->executeQuery('START TRANSACTION READ ONLY');
            try {
                $resultSet = $this->connection->executeQuery($sql)->fetchAllAssociative();
                $parameters['export'] = $this->buildExportString($resultSet);
                $parameters['results'] = $resultSet;
                $parameters['count'] = count($resultSet);
                $parameters['submitted'] = 1;
            } catch (Throwable $e) {
                $form->get('sql')->addError(new FormError($e->getMessage()));
            }
        }
        $parameters['form'] = $form->createView();

        return $this->render('sql_console/new_search.html.twig', $parameters);
    }

    /**
     * Builds an export string.
     */
    private function buildExportString(array $results): string
    {
        if (!$results) {
            return '';
        }

        $lines = [];

        // add a header line
        $lines[] = implode("\t", array_keys($results[0]));
        // add in each result row
        foreach ($results as $row) {
            $lines[] = implode("\t", $row);
        }

        return implode("\n", $lines);
    }
}
