<?php

namespace App\Command;

use App\Repository\AuditLogRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ListAuditLogsCommand extends Command
{
    protected static $defaultName = 'app:list-logs';
    private $repository;

    public function __construct(AuditLogRepository $repository)
    {
        parent::__construct();
        $this->repository = $repository;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $logs = $this->repository->findBy([], ['createdAt' => 'DESC'], 10);

        $io->title('Last 10 Audit Logs');

        $tableData = [];
        foreach ($logs as $log) {
            $tableData[] = [
                $log->getCreatedAt()->format('Y-m-d H:i:s'),
                $log->getUserEmail(),
                $log->getAction(),
                $log->getEntityName(),
                $log->getEntityId(),
                json_encode($log->getDetails())
            ];
        }

        $io->table(
            ['Date', 'User', 'Action', 'Entity', 'ID', 'Details'],
            $tableData
        );

        return Command::SUCCESS;
    }
}
