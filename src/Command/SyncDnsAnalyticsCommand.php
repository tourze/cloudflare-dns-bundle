<?php

declare(strict_types=1);

namespace CloudflareDnsBundle\Command;

use CloudflareDnsBundle\Entity\DnsDomain;
use CloudflareDnsBundle\Repository\DnsAnalyticsRepository;
use CloudflareDnsBundle\Repository\DnsDomainRepository;
use CloudflareDnsBundle\Service\AnalyticsDataProcessor;
use CloudflareDnsBundle\Service\DnsAnalyticsService;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: self::NAME, description: '同步 Cloudflare DNS 分析数据')]
#[WithMonologChannel(channel: 'cloudflare_dns')]
class SyncDnsAnalyticsCommand extends Command
{
    public const NAME = 'cloudflare:sync-dns-analytics';

    public function __construct(
        private readonly DnsDomainRepository $domainRepository,
        private readonly DnsAnalyticsRepository $analyticsRepository,
        private readonly DnsAnalyticsService $dnsService,
        private readonly AnalyticsDataProcessor $dataProcessor,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('since', null, InputOption::VALUE_REQUIRED, '开始时间(相对时间,如-24h)', '-24h')
            ->addOption('until', null, InputOption::VALUE_REQUIRED, '结束时间(相对时间,如now)', 'now')
            ->addOption('time-delta', null, InputOption::VALUE_REQUIRED, '时间间隔(如1h)', '1h')
            ->addOption('cleanup-before', null, InputOption::VALUE_REQUIRED, '清理多少天前的数据', '30')
            ->addOption('domain-id', null, InputOption::VALUE_OPTIONAL, '指定单个域名ID')
            ->addOption('skip-errors', null, InputOption::VALUE_NONE, '跳过错误，继续处理其他域名')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $this->cleanupOldData($input, $io);
        $domains = $this->findTargetDomains($input, $io);

        if ([] === $domains) {
            $io->warning('没有找到符合条件的域名');

            return Command::SUCCESS;
        }

        return $this->syncDomainsAnalytics($input, $io, $domains);
    }

    private function cleanupOldData(InputInterface $input, SymfonyStyle $io): void
    {
        $cleanupBeforeOption = $input->getOption('cleanup-before');
        $cleanupDays = is_numeric($cleanupBeforeOption) ? (int) $cleanupBeforeOption : 0;
        if ($cleanupDays > 0) {
            $cleanupTime = new \DateTimeImmutable("-{$cleanupDays} days");
            $count = $this->analyticsRepository->cleanupBefore($cleanupTime);
            $io->success("清理了 {$count} 条旧数据");
        }
    }

    /**
     * @return DnsDomain[]
     */
    private function findTargetDomains(InputInterface $input, SymfonyStyle $io): array
    {
        $criteria = ['valid' => true];

        $domainId = $input->getOption('domain-id');
        if (null !== $domainId) {
            $criteria['id'] = $domainId;
        }

        return $this->domainRepository->findBy($criteria);
    }

    /**
     * @param DnsDomain[] $domains
     */
    private function syncDomainsAnalytics(InputInterface $input, SymfonyStyle $io, array $domains): int
    {
        $io->title('开始同步DNS分析数据');
        $io->progressStart(count($domains));

        $skipErrors = $this->extractSkipErrorsOption($input);
        $params = $this->buildApiParams($input);
        $results = ['success' => 0, 'error' => 0];

        foreach ($domains as $domain) {
            $io->progressAdvance();

            $processResult = $this->processSingleDomain($domain, $params, $results, $skipErrors, $io);
            $results = $processResult['results'];

            if ($processResult['shouldStop']) {
                $io->progressFinish();

                return Command::FAILURE;
            }
        }

        $io->progressFinish();
        $this->displayResults($io, $results);

        return Command::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildApiParams(InputInterface $input): array
    {
        return [
            'dimensions' => ['queryName', 'queryType'],
            'metrics' => ['queryCount', 'responseTimeAvg', 'uncachedCount'],
            'since' => $input->getOption('since'),
            'until' => $input->getOption('until'),
            'time_delta' => $input->getOption('time-delta'),
            'limit' => 10000,
        ];
    }

    /**
     * @param DnsDomain            $domain
     * @param array<string, mixed> $params
     * @param array<string, int>   $results
     *
     * @return array{shouldStop: bool, results: array<string, int>}
     */
    private function processSingleDomain($domain, array $params, array $results, bool $skipErrors, SymfonyStyle $io): array
    {
        try {
            return $this->processValidDomain($domain, $params, $results);
        } catch (\Throwable $e) {
            return $this->handleDomainProcessError($e, $domain, $results, $skipErrors, $io);
        }
    }

    /**
     * 记录域名处理开始
     */
    private function logDomainProcessStart(DnsDomain $domain): void
    {
        $this->logger->info('开始处理域名', [
            'domain' => $domain->getName(),
            'zoneId' => $domain->getZoneId(),
        ]);
    }

    /**
     * 记录域名处理成功
     */
    private function logDomainProcessSuccess(DnsDomain $domain, int $recordCount): void
    {
        $this->logger->info('域名DNS分析数据同步成功', [
            'domain' => $domain->getName(),
            'recordCount' => $recordCount,
        ]);
    }

    /**
     * 创建处理结果
     * @param array<string, int> $results
     * @return array{shouldStop: bool, results: array<string, int>}
     */
    private function createProcessResult(bool $shouldStop, array $results): array
    {
        return ['shouldStop' => $shouldStop, 'results' => $results];
    }

    /**
     * 处理域名处理错误
     * @param array<string, int> $results
     * @return array{shouldStop: bool, results: array<string, int>}
     */
    private function handleDomainProcessError(\Throwable $e, DnsDomain $domain, array $results, bool $skipErrors, SymfonyStyle $io): array
    {
        ++$results['error'];
        $this->logger->error('同步DNS分析数据失败', [
            'domain' => $domain->getName(),
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        if (!$skipErrors) {
            $io->error("处理域名 {$domain->getName()} 时出错: {$e->getMessage()}");

            return $this->createProcessResult(true, $results);
        }

        return $this->createProcessResult(false, $results);
    }

    private function validateDomain(DnsDomain $domain): bool
    {
        if (null === $domain->getZoneId() || '' === $domain->getZoneId()) {
            $this->logger->warning('域名没有zoneId，跳过', [
                'domain' => $domain->getName(),
            ]);

            return false;
        }

        return true;
    }

    private function validateDomainAccess(DnsDomain $domain): void
    {
        try {
            $zoneDetails = $this->dnsService->getZoneDetails($domain);
            $this->validateZoneDetails($domain, $zoneDetails);
        } catch (\Throwable $e) {
            $this->logger->warning('获取域名详情失败，但将继续尝试获取分析数据', [
                'domain' => $domain->getName(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 验证区域详情
     * @param array<string, mixed> $zoneDetails
     */
    private function validateZoneDetails(DnsDomain $domain, array $zoneDetails): void
    {
        $result = $this->extractZoneResult($zoneDetails);
        $this->validateZonePlan($domain, $result);
        $this->validateZoneStatus($domain, $result);
    }

    /**
     * 提取区域结果
     * @param array<string, mixed> $zoneDetails
     * @return array<string, mixed>
     */
    private function extractZoneResult(array $zoneDetails): array
    {
        $result = $zoneDetails['result'] ?? [];
        if (!is_array($result)) {
            return [];
        }
        // Ensure all keys are strings
        $stringKeyedResult = [];
        foreach ($result as $key => $value) {
            $stringKeyedResult[is_string($key) ? $key : (string) $key] = $value;
        }

        return $stringKeyedResult;
    }

    /**
     * 验证区域计划
     * @param array<string, mixed> $result
     */
    private function validateZonePlan(DnsDomain $domain, array $result): void
    {
        $plan = $this->extractPlanFromResult($result);
        if (!$this->isPlanSupported($plan)) {
            $this->logUnsupportedPlan($domain, $plan);
        }
    }

    /**
     * 验证区域状态
     * @param array<string, mixed> $result
     */
    private function validateZoneStatus(DnsDomain $domain, array $result): void
    {
        $status = $this->extractStatusFromResult($result);
        if (!$this->isStatusActive($status)) {
            $this->logInactiveStatus($domain, $status);
        }
    }

    /**
     * @param array<string, mixed> $params
     */
    private function syncDomainAnalytics(DnsDomain $domain, array $params): int
    {
        $apiParams = $this->sanitizeApiParams($params);
        $result = $this->dnsService->getDnsAnalytics($domain, $apiParams);

        if (!$this->isValidAnalyticsResult($result)) {
            $this->logInvalidAnalyticsResult($domain, $result);

            return 0;
        }

        $data = $this->extractAnalyticsData($result);

        return $this->dataProcessor->saveAnalyticsData($domain, $data);
    }

    /**
     * @param array<string, int> $results
     */
    private function displayResults(SymfonyStyle $io, array $results): void
    {
        if ($results['success'] > 0) {
            $io->success("成功同步 {$results['success']} 个域名的DNS分析数据");
        }

        if ($results['error'] > 0) {
            $io->warning("有 {$results['error']} 个域名同步失败");
        }
    }

    private function extractSkipErrorsOption(InputInterface $input): bool
    {
        $skipErrorsOption = $input->getOption('skip-errors');

        return is_bool($skipErrorsOption) ? $skipErrorsOption : (bool) $skipErrorsOption;
    }

    /**
     * @param array<string, mixed> $params
     * @return array{dimensions?: array<string>, metrics?: array<string>, since?: string, until?: string, time_delta?: string, limit?: int}
     */
    private function sanitizeApiParams(array $params): array
    {
        $dimensions = $this->ensureArray($params['dimensions'] ?? []);
        $metrics = $this->ensureArray($params['metrics'] ?? []);
        $since = $this->ensureString($params['since'] ?? '');
        $until = $this->ensureString($params['until'] ?? '');
        $timeDelta = $this->ensureString($params['time_delta'] ?? '');
        $limit = $this->ensureInt($params['limit'] ?? 1000);

        return [
            'dimensions' => $this->ensureStringArray($dimensions),
            'metrics' => $this->ensureStringArray($metrics),
            'since' => $since,
            'until' => $until,
            'time_delta' => $timeDelta,
            'limit' => $limit,
        ];
    }

    /**
     * @param array<string, mixed> $result
     */
    private function isValidAnalyticsResult(array $result): bool
    {
        return isset($result['success'])
            && true === $result['success']
            && ($result['data'] ?? null) !== [];
    }

    /**
     * @param array<string, mixed> $result
     */
    private function logInvalidAnalyticsResult(DnsDomain $domain, array $result): void
    {
        $this->logger->warning('API返回成功但无分析数据', [
            'domain' => $domain->getName(),
            'response' => $result,
        ]);
    }

    /**
     * @param array<string, mixed> $result
     * @return array<mixed>
     */
    private function extractAnalyticsData(array $result): array
    {
        $data = $result['data'] ?? [];

        return is_array($data) ? $data : [];
    }

    /**
     * @return array<mixed>
     */
    private function ensureArray(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    private function ensureString(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    private function ensureInt(mixed $value): int
    {
        return is_int($value) ? $value : 1000;
    }

    /**
     * @param DnsDomain            $domain
     * @param array<string, mixed> $params
     * @param array<string, int>   $results
     * @return array{shouldStop: bool, results: array<string, int>}
     */
    private function processValidDomain(DnsDomain $domain, array $params, array $results): array
    {
        $this->logDomainProcessStart($domain);

        if (!$this->validateDomain($domain)) {
            return $this->createProcessResult(false, $results);
        }

        $this->validateDomainAccess($domain);
        $recordCount = $this->syncDomainAnalytics($domain, $params);
        $this->logDomainProcessSuccess($domain, $recordCount);

        ++$results['success'];

        return $this->createProcessResult(false, $results);
    }

    /**
     * 确保数组元素为字符串类型
     * @param array<mixed> $array
     * @return array<string>
     */
    private function ensureStringArray(array $array): array
    {
        $result = [];
        foreach ($array as $item) {
            if (is_string($item)) {
                $result[] = $item;
            } elseif (is_scalar($item) || null === $item) {
                $result[] = (string) $item;
            }
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $result
     */
    private function extractPlanFromResult(array $result): string
    {
        $planData = $result['plan'] ?? [];
        if (!is_array($planData)) {
            return 'Unknown';
        }

        $plan = $planData['name'] ?? 'Unknown';

        return is_string($plan) ? $plan : 'Unknown';
    }

    private function isPlanSupported(string $plan): bool
    {
        return in_array($plan, ['Enterprise', 'Business', 'Pro'], true);
    }

    private function logUnsupportedPlan(DnsDomain $domain, string $plan): void
    {
        $this->logger->warning('域名计划可能不支持DNS Analytics', [
            'domain' => $domain->getName(),
            'plan' => $plan,
        ]);
    }

    /**
     * @param array<string, mixed> $result
     */
    private function extractStatusFromResult(array $result): string
    {
        $status = $result['status'] ?? 'unknown';

        return is_string($status) ? $status : 'unknown';
    }

    private function isStatusActive(string $status): bool
    {
        return 'active' === $status;
    }

    private function logInactiveStatus(DnsDomain $domain, string $status): void
    {
        $this->logger->warning('域名不是活跃状态，可能无法获取分析数据', [
            'domain' => $domain->getName(),
            'status' => $status,
        ]);
    }
}
