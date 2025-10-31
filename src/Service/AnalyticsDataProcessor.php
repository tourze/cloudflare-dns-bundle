<?php

declare(strict_types=1);

namespace CloudflareDnsBundle\Service;

use CloudflareDnsBundle\Entity\DnsAnalytics;
use CloudflareDnsBundle\Entity\DnsDomain;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

/**
 * DNS分析数据处理器
 *
 * 负责处理从API获取的DNS分析数据，包括验证、转换和持久化
 */
#[Autoconfigure(public: true)]
class AnalyticsDataProcessor
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * 保存分析数据到数据库
     *
     * @param array<mixed> $data
     */
    public function saveAnalyticsData(DnsDomain $domain, array $data): int
    {
        $recordCount = 0;

        foreach ($data as $item) {
            $recordCount += $this->processAnalyticsItem($domain, $item, $recordCount);
        }

        $this->entityManager->flush();

        return $recordCount;
    }

    /**
     * 处理单个分析数据项
     */
    private function processAnalyticsItem(DnsDomain $domain, mixed $item, int $currentRecordCount): int
    {
        if (!is_array($item) || !isset($item['data']) || !is_array($item['data'])) {
            return 0;
        }

        $stringKeyedItem = $this->normalizeArrayKeys($item);
        $itemTime = $this->extractItemTime($stringKeyedItem);

        return $this->processItemDataEntries($domain, $item['data'], $itemTime, $currentRecordCount);
    }

    /**
     * 处理数据项的所有条目
     *
     * @param array<mixed> $dataEntries
     */
    private function processItemDataEntries(DnsDomain $domain, array $dataEntries, string $itemTime, int $currentRecordCount): int
    {
        $processedCount = 0;

        foreach ($dataEntries as $dataItem) {
            $processedCount += $this->processSingleDataEntry($domain, $dataItem, $itemTime, $currentRecordCount, $processedCount);
        }

        return $processedCount;
    }

    /**
     * 处理单个数据条目
     */
    private function processSingleDataEntry(DnsDomain $domain, mixed $dataItem, string $itemTime, int $currentRecordCount, int $processedCount): int
    {
        if (!is_array($dataItem)) {
            return 0;
        }

        $stringKeyedDataItem = $this->normalizeArrayKeys($dataItem);
        if (!$this->isValidDataItem($stringKeyedDataItem)) {
            return 0;
        }

        $analytics = $this->createAnalyticsEntity($domain, $stringKeyedDataItem, $itemTime);
        $this->entityManager->persist($analytics);

        if (0 === ($currentRecordCount + $processedCount + 1) % 100) {
            $this->entityManager->flush();
        }

        return 1;
    }

    /**
     * 创建分析实体
     *
     * @param array<string, mixed> $dataItem
     */
    private function createAnalyticsEntity(DnsDomain $domain, array $dataItem, string $time): DnsAnalytics
    {
        $analytics = new DnsAnalytics();
        $analytics->setDomain($domain);
        $analytics->setStatTime(new \DateTimeImmutable($time));

        $this->populateAnalyticsData($analytics, $dataItem);

        return $analytics;
    }

    /**
     * 填充分析数据
     *
     * @param array<string, mixed> $dataItem
     */
    private function populateAnalyticsData(DnsAnalytics $analytics, array $dataItem): void
    {
        $this->setAnalyticsQueryInfo($analytics, $dataItem);
        $this->setAnalyticsMetrics($analytics, $dataItem);
    }

    /**
     * 设置查询信息
     *
     * @param array<string, mixed> $dataItem
     */
    private function setAnalyticsQueryInfo(DnsAnalytics $analytics, array $dataItem): void
    {
        $dimensions = $this->extractDimensions($dataItem);
        $queryName = $this->extractStringFromArray($dimensions, 0, 'unknown');
        $queryType = $this->extractStringFromArray($dimensions, 1, 'unknown');

        $analytics->setQueryName($queryName);
        $analytics->setQueryType($queryType);
    }

    /**
     * 设置指标
     *
     * @param array<string, mixed> $dataItem
     */
    private function setAnalyticsMetrics(DnsAnalytics $analytics, array $dataItem): void
    {
        $metrics = $this->extractMetrics($dataItem);
        $queryCount = $this->extractNumericFromArray($metrics, 0, 0);
        $responseTimeAvg = $this->extractNumericFromArray($metrics, 1, 0);

        $analytics->setQueryCount((int) $queryCount);
        $analytics->setResponseTimeAvg((float) $responseTimeAvg);
    }

    /**
     * 验证数据项是否有效
     *
     * @param array<string, mixed> $dataItem
     */
    private function isValidDataItem(array $dataItem): bool
    {
        if (!isset($dataItem['dimensions'], $dataItem['metrics'])) {
            return false;
        }

        $dimensions = $dataItem['dimensions'];
        $metrics = $dataItem['metrics'];

        return is_array($dimensions) && count($dimensions) >= 2
            && is_array($metrics) && count($metrics) >= 2;
    }

    /**
     * 提取维度数据
     *
     * @param array<string, mixed> $dataItem
     * @return array<mixed>
     */
    private function extractDimensions(array $dataItem): array
    {
        $dimensions = $dataItem['dimensions'] ?? [];

        return is_array($dimensions) ? $dimensions : [];
    }

    /**
     * 提取指标数据
     *
     * @param array<string, mixed> $dataItem
     * @return array<mixed>
     */
    private function extractMetrics(array $dataItem): array
    {
        $metrics = $dataItem['metrics'] ?? [];

        return is_array($metrics) ? $metrics : [];
    }

    /**
     * 从数组中安全提取字符串
     *
     * @param array<mixed> $array
     */
    private function extractStringFromArray(array $array, int $index, string $default): string
    {
        $value = $array[$index] ?? $default;

        return is_string($value) ? $value : $default;
    }

    /**
     * 从数组中安全提取数值
     *
     * @param array<mixed> $array
     */
    private function extractNumericFromArray(array $array, int $index, int|float $default): int|float
    {
        $value = $array[$index] ?? $default;

        return is_numeric($value) ? (is_int($value) ? $value : (float) $value) : $default;
    }

    /**
     * 提取项目时间
     *
     * @param array<string, mixed> $item
     */
    private function extractItemTime(array $item): string
    {
        $itemTime = $item['time'] ?? '';

        return is_string($itemTime) ? $itemTime : '';
    }

    /**
     * 标准化数组键为字符串类型
     *
     * @param array<mixed> $array
     * @return array<string, mixed>
     */
    private function normalizeArrayKeys(array $array): array
    {
        $result = [];
        foreach ($array as $key => $value) {
            $result[is_string($key) ? $key : (string) $key] = $value;
        }

        return $result;
    }
}
