<?php

namespace CloudflareDnsBundle\Tests\Service;

use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * 简单的响应实现，用于测试
 */
class TestHttpResponse implements ResponseInterface
{
    private bool $success;

    public function __construct(bool $success)
    {
        $this->success = $success;
    }

    public function getStatusCode(): int
    {
        return $this->success ? 200 : 400;
    }

    public function getHeaders(bool $throw = true): array
    {
        return ['Content-Type' => ['application/json']];
    }

    public function getContent(bool $throw = true): string
    {
        return 'test-content';
    }

    public function toArray(bool $throw = true): array
    {
        if ($this->success) {
            return [
                'success' => true,
                'result' => ['id' => 'test-record-id'],
            ];
        } else {
            return [
                'success' => false,
                'errors' => [['code' => 1003, 'message' => 'Invalid access token']],
            ];
        }
    }

    public function cancel(): void
    {
        // Do nothing in test
    }

    /**
     * 获取响应信息
     *
     * @param string|null $type 需要获取的信息类型，为 null 时返回所有信息
     * @return mixed 请求的信息
     */
    public function getInfo(?string $type = null): mixed
    {
        return $type === null ? ['url' => 'https://example.com'] : null;
    }
}
