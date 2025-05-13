<?php

namespace CloudflareDnsBundle\Command;

use CloudflareDnsBundle\Repository\DnsRecordRepository;
use CloudflareDnsBundle\Service\DnsRecordService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: SyncDomainRecordToRemoteCommand::NAME)]
class SyncDomainRecordToRemoteCommand extends Command
{
    public const NAME = 'cloudflare:sync-dns-domain-record-to-remote';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly DnsRecordRepository $recordRepository,
        private readonly DnsRecordService $dnsService,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('dnsRecordId', InputArgument::REQUIRED, 'DNS本地记录ID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $record = $this->recordRepository->find($input->getArgument('dnsRecordId'));
        if (!$record) {
            throw new \Exception('找不到记录信息');
        }

        if ($record->isSyncing()) {
            $output->writeln('记录正在同步中,跳过处理');
            return Command::FAILURE;
        }

        try {
            $record->setSyncing(true);
            $domain = $record->getDomain();

            // 如果没记录ID，那么我们试试搜索
            if (!$record->getRecordId()) {
                $response = $this->dnsService->listRecords($domain, [
                    'type' => $record->getType()->value,
                    'name' => "{$record->getRecord()}.{$domain->getName()}",
                ]);

                if (!empty($response['result'])) {
                    $record->setRecordId($response['result'][0]['id']);
                    $this->entityManager->persist($record);
                    $this->entityManager->flush();
                }
            }

            // 还是没有，我们尝试创建
            if (!$record->getRecordId()) {
                $result = $this->dnsService->createRecord($domain, $record);
                $this->logger->info('DNS记录创建结果', [
                    'result' => $result,
                    'record' => $record,
                ]);

                $record->setRecordId($result['id']);
                $this->entityManager->persist($record);
                $this->entityManager->flush();
            }

            // 更新最终本地结果到远程
            $result = $this->dnsService->updateRecord($record);
            $this->logger->info('DNS记录更新结果', [
                'record' => $record,
                'result' => $result,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('同步DNS记录失败', [
                'record' => $record,
                'exception' => $e,
            ]);
            throw $e;
        } finally {
            $record->setSyncing(false);
            $this->entityManager->persist($record);
            $this->entityManager->flush();
        }

        return Command::SUCCESS;
    }
}
