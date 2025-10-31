<?php

declare(strict_types=1);

namespace CloudflareDnsBundle\Abstract;

use CloudflareDnsBundle\Entity\DnsDomain;
use CloudflareDnsBundle\Exception\CloudflareServiceException;
use CloudflareDnsBundle\Service\CloudflareHttpClient;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;

#[WithMonologChannel(channel: 'cloudflare_dns')]
abstract class BaseCloudflareService
{
    public function __construct(
        protected readonly LoggerInterface $logger,
    ) {
    }

    protected function getCloudFlareClient(DnsDomain $domain): CloudflareHttpClient
    {
        $iamKey = $domain->getIamKey();
        if (null === $iamKey) {
            throw new CloudflareServiceException('Domain does not have an IAM key configured');
        }

        $accessKey = $iamKey->getAccessKey();
        $secretKey = $iamKey->getSecretKey();

        if (null === $accessKey || null === $secretKey) {
            throw new CloudflareServiceException('IAM key is missing access key or secret key');
        }

        return new CloudflareHttpClient($accessKey, $secretKey);
    }

    /**
     * @param array<string, mixed> $result
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    protected function handleResponse(array $result, string $errorMessage, array $context = []): array
    {
        $success = $result['success'] ?? false;
        if (!is_bool($success) || !$success) {
            $this->logger->error($errorMessage, array_merge($context, [
                'errors' => $result['errors'],
            ]));
            throw new CloudflareServiceException($errorMessage . ': ' . json_encode($result['errors']));
        }

        $this->logger->info(str_replace('失败', '成功', $errorMessage), array_merge($context, [
            'result' => $result,
        ]));

        return $result;
    }

    /**
     * 安全地转换响应为数组类型
     * @param mixed $response
     * @return array<string, mixed>
     */
    protected function toArraySafely($response): array
    {
        if (!is_object($response) || !method_exists($response, 'toArray')) {
            return [];
        }

        $data = $response->toArray();
        if (!is_array($data)) {
            return [];
        }

        // Ensure all keys are strings
        $stringKeyedData = [];
        foreach ($data as $key => $value) {
            $stringKeyedData[is_string($key) ? $key : (string) $key] = $value;
        }

        return $stringKeyedData;
    }
}
