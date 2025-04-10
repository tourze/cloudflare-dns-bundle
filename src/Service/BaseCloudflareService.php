<?php

namespace CloudflareDnsBundle\Service;

use CloudflareDnsBundle\Entity\DnsDomain;
use Psr\Log\LoggerInterface;

abstract class BaseCloudflareService
{
    public function __construct(
        protected readonly LoggerInterface $logger,
    ) {
    }

    protected function getCloudFlareClient(DnsDomain $domain): CloudflareHttpClient
    {
        return new CloudflareHttpClient($domain->getIamKey()->getAccessKey(), $domain->getIamKey()->getSecretKey());
    }

    protected function handleResponse(array $result, string $errorMessage, array $context = []): array
    {
        if (!$result['success']) {
            $this->logger->error($errorMessage, array_merge($context, [
                'errors' => $result['errors'],
            ]));
            throw new \RuntimeException($errorMessage . ': ' . json_encode($result['errors']));
        }

        $this->logger->info(str_replace('失败', '成功', $errorMessage), array_merge($context, [
            'result' => $result,
        ]));

        return $result;
    }
}
