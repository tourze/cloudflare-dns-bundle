<?php

declare(strict_types=1);

namespace CloudflareDnsBundle\Command;

use CloudflareDnsBundle\Entity\DnsDomain;
use CloudflareDnsBundle\Entity\DnsRecord;
use CloudflareDnsBundle\Enum\DnsRecordType;
use CloudflareDnsBundle\Repository\DnsDomainRepository;
use CloudflareDnsBundle\Repository\DnsRecordRepository;
use CloudflareDnsBundle\Service\DnsRecordService;
use Doctrine\ORM\EntityManagerInterface;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: self::NAME, description: '同步域名信息到本地')]
#[WithMonologChannel(channel: 'cloudflare_dns')]
final class SyncDomainRecordToLocalCommand extends Command
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
            $result = $this->fetchRecordsPage($domain, $page, $perPage);

            if (!$this->isResultValid($result, $domain)) {
                break;
            }

            $this->processPageRecords($result, $domain, $output);

            if ($this->isLastPage($result, $page)) {
                break;
            }
            ++$page;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchRecordsPage(DnsDomain $domain, int $page, int $perPage): array
    {
        return $this->dnsService->listRecords($domain, [
            'page' => $page,
            'per_page' => $perPage,
        ]);
    }

    /**
     * @param array<string, mixed> $result
     */
    private function isResultValid(array $result, DnsDomain $domain): bool
    {
        if (!isset($result['success']) || true !== $result['success']) {
            $this->logger->warning('获取DNS记录列表失败', [
                'domain' => $domain,
                'result' => $result,
            ]);

            return false;
        }

        return true;
    }

    /**
     * @param array<int, array<string, mixed>> $records
     */
    private function processRecords(array $records, DnsDomain $domain, OutputInterface $output): void
    {
        foreach ($records as $item) {
            $recordId = $item['id'] ?? null;
            if (!is_string($recordId)) {
                continue;
            }
            $record = $this->findOrCreateRecord($domain, $recordId);
            $this->updateRecord($record, $item, $domain, $output);
        }
    }

    private function findOrCreateRecord(DnsDomain $domain, string $recordId): DnsRecord
    {
        $record = $this->recordRepository->findOneBy([
            'domain' => $domain,
            'recordId' => $recordId,
        ]);

        if (null === $record) {
            $record = $this->createNewRecord($domain, $recordId);
        }

        return $record;
    }

    /**
     * @param array<string, mixed> $item
     */
    /**
     * @param array<string, mixed> $item
     */
    private function updateRecord(DnsRecord $record, array $item, DnsDomain $domain, OutputInterface $output): void
    {
        $record->setSyncing(true);

        try {
            $this->setRecordData($record, $item, $domain);
            $record->setSynced(true);

            // 确保关联的实体也被持久化
            if (null !== $domain->getIamKey()) {
                $this->entityManager->persist($domain->getIamKey());
            }
            $this->entityManager->persist($domain);
            $this->entityManager->persist($record);
            $this->entityManager->flush();

            $output->writeln("发现子域名：{$record->getFullName()}");
        } finally {
            $record->setSyncing(false);
        }
    }

    /**
     * @param array<string, mixed> $item
     */
    private function setRecordData(DnsRecord $record, array $item, DnsDomain $domain): void
    {
        $this->setRecordName($record, $item, $domain);
        $this->setRecordType($record, $item);
        $this->setRecordContent($record, $item);
        $this->setRecordTtl($record, $item);
        $this->setRecordProxy($record, $item);
    }

    private function extractRecordName(string $fullName, DnsDomain $domain): string
    {
        $suffix = ".{$domain->getName()}";

        if (str_ends_with($fullName, $suffix)) {
            return substr($fullName, 0, -strlen($suffix));
        }

        return $fullName;
    }

    /**
     * @param array<string, mixed> $result
     */
    private function isLastPage(array $result, int $currentPage): bool
    {
        $resultInfo = $result['result_info'] ?? [];
        if (!is_array($resultInfo)) {
            return true;
        }
        $totalPages = $resultInfo['total_pages'] ?? 0;
        if (!is_int($totalPages)) {
            return true;
        }

        return $totalPages <= $currentPage;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $id = $input->getArgument('domainId');
        if (null !== $id) {
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

    /**
     * @param array<string, mixed> $result
     */
    private function processPageRecords(array $result, DnsDomain $domain, OutputInterface $output): void
    {
        $records = $result['result'] ?? [];
        if (!is_array($records)) {
            return;
        }

        $typedRecords = $this->normalizeRecordsArray($records);
        $this->processRecords($typedRecords, $domain, $output);
    }

    /**
     * @param array<mixed> $records
     * @return array<int, array<string, mixed>>
     */
    private function normalizeRecordsArray(array $records): array
    {
        $typedRecords = [];
        foreach ($records as $record) {
            if (!is_array($record)) {
                continue;
            }

            $stringKeyedRecord = [];
            foreach ($record as $key => $value) {
                $stringKeyedRecord[is_string($key) ? $key : (string) $key] = $value;
            }
            $typedRecords[] = $stringKeyedRecord;
        }

        return $typedRecords;
    }

    private function createNewRecord(DnsDomain $domain, string $recordId): DnsRecord
    {
        $record = new DnsRecord();
        $record->setDomain($domain);
        $record->setRecordId($recordId);

        $this->initializeTimestampFields($record);

        return $record;
    }

    private function initializeTimestampFields(DnsRecord $record): void
    {
        $now = new \DateTimeImmutable();
        $reflection = new \ReflectionClass($record);

        $this->setTimestampField($reflection, $record, 'createTime', $now);
        $this->setTimestampField($reflection, $record, 'updateTime', $now);
    }

    /**
     * @param \ReflectionClass<DnsRecord> $reflection
     */
    private function setTimestampField(\ReflectionClass $reflection, DnsRecord $record, string $fieldName, \DateTimeImmutable $timestamp): void
    {
        if (!$reflection->hasProperty($fieldName)) {
            return;
        }

        $property = $reflection->getProperty($fieldName);
        $property->setAccessible(true);
        $property->setValue($record, $timestamp);
    }

    /**
     * @param array<string, mixed> $item
     */
    private function setRecordName(DnsRecord $record, array $item, DnsDomain $domain): void
    {
        $fullName = $item['name'] ?? '';
        if (!is_string($fullName)) {
            $fullName = '';
        }
        $name = $this->extractRecordName($fullName, $domain);
        $record->setRecord($name);
    }

    /**
     * @param array<string, mixed> $item
     */
    private function setRecordType(DnsRecord $record, array $item): void
    {
        $type = $item['type'] ?? '';
        if (!is_string($type) && !is_int($type)) {
            return;
        }

        $recordType = DnsRecordType::tryFrom($type);
        if (null !== $recordType) {
            $record->setType($recordType);
        }
    }

    /**
     * @param array<string, mixed> $item
     */
    private function setRecordContent(DnsRecord $record, array $item): void
    {
        $content = $item['content'] ?? '';
        if (is_string($content)) {
            $record->setContent($content);
        }
    }

    /**
     * @param array<string, mixed> $item
     */
    private function setRecordTtl(DnsRecord $record, array $item): void
    {
        $ttl = $item['ttl'] ?? 0;
        if (is_int($ttl)) {
            $record->setTtl($ttl);
        }
    }

    /**
     * @param array<string, mixed> $item
     */
    private function setRecordProxy(DnsRecord $record, array $item): void
    {
        $proxiable = $item['proxiable'] ?? false;
        if (is_bool($proxiable)) {
            $record->setProxy($proxiable);
        }
    }
}
