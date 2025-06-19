<?php

namespace CloudflareDnsBundle\Command;

use CloudflareDnsBundle\Entity\DnsDomain;
use CloudflareDnsBundle\Entity\DnsRecord;
use CloudflareDnsBundle\Enum\DnsRecordType;
use CloudflareDnsBundle\Repository\DnsDomainRepository;
use CloudflareDnsBundle\Repository\DnsRecordRepository;
use CloudflareDnsBundle\Service\DnsRecordService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: self::NAME, description: '同步域名信息到本地')]
class SyncDomainRecordToLocalCommand extends Command
{
    public const NAME = 'cloudflare:sync-domain-record-to-local';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly DnsDomainRepository $domainRepository,
        private readonly DnsRecordRepository $recordRepository,
        private readonly LoggerInterface $logger,
        private readonly DnsRecordService $dnsService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('domainId', InputArgument::OPTIONAL, '主域名ID');
    }

    private function syncDomain(DnsDomain $domain, OutputInterface $output): void
    {
        $output->writeln("开始处理域名：{$domain->getName()}");

        $page = 1;
        $perPage = 50;

        while (true) {
            $result = $this->dnsService->listRecords($domain, [
                'page' => $page,
                'per_page' => $perPage,
            ]);

            if (!$result['success']) {
                $this->logger->warning('获取DNS记录列表失败', [
                    'domain' => $domain,
                    'result' => $result,
                ]);
                break;
            }

            foreach ($result['result'] as $item) {
                $record = $this->recordRepository->findOneBy([
                    'domain' => $domain,
                    'recordId' => $item['id'],
                ]);
                if ($record === null) {
                    $record = new DnsRecord();
                    $record->setDomain($domain);
                    $record->setRecordId($item['id']);
                }

                $record->setSyncing(true);
                try {
                    $name = $item['name'];
                    $suffix = ".{$domain->getName()}";
                    if (str_ends_with($name, $suffix)) {
                        $name = substr($name, 0, -strlen($suffix));
                    }

                    $record->setRecord($name);
                    $record->setType(DnsRecordType::tryFrom($item['type']));
                    $record->setContent($item['content']);
                    $record->setTtl($item['ttl']);
                    $record->setProxy($item['proxiable'] ?? false);

                    // 设置为已同步状态，因为数据来自远程
                    $record->setSynced(true);

                    $this->entityManager->persist($record);
                    $this->entityManager->flush();

                    $output->writeln("发现子域名：{$record->getFullName()}");
                } finally {
                    $record->setSyncing(false);
                }
            }

            if (!isset($result['result_info']['total_pages']) || $result['result_info']['total_pages'] <= $page) {
                break;
            }
            ++$page;
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $id = $input->getArgument('domainId');
        if ($id !== null) {
            $domains = $this->domainRepository->findBy(['id' => $id]);
        } else {
            $domains = $this->domainRepository->findAll();
        }

        foreach ($domains as $domain) {
            try {
                $this->syncDomain($domain, $output);
            } catch (\Throwable $exception) {
                $output->writeln('同步DNS发生错误：' . $exception);
                $this->logger->error('同步DNS记录失败', [
                    'domain' => $domain,
                    'exception' => $exception,
                ]);
            } finally {
                $output->writeln('');
            }
        }

        return Command::SUCCESS;
    }
}
