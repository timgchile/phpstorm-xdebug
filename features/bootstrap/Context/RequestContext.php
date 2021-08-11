<?php

declare(strict_types=1);

namespace Behat\Behat\Context;

use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Behat\Storage\StorageTrait;
use DateInterval;
use Datetime;
use GuzzleHttp\Client as GuzzleHttpClient;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Pool as GuzzleHttpPool;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Throwable;

use function count;
use function in_array;
use function is_array;

class RequestContext implements Context
{
    use StorageTrait;

    private GuzzleHttpClient $httpClient;
    private ?string $baseUrl;
    private array $responseQueryParams = [];
    private bool $stripeWebHookHeaders = false;

    public function __construct()
    {
        $this->baseUrl = sprintf('https://%s/', $this->storage()->get('domain'));
    }

    public function cleanup(): void
    {
        $storage = $this->storage();
        $storage->set('headers', []);
        $storage->set('payload', []);
        $storage->set('files', []);
        $storage->set('responseCode');
        $storage->set('responseType');
        $storage->set('responseBody');
        $storage->set('store', []);
    }

    /**
     * @AfterScenario
     */
    public function teardownScenario(): void
    {
        $this->cleanup();
    }

    /**
     * @BeforeScenario
     */
    public function setupScenario(BeforeScenarioScope $scope): void
    {
        $this->cleanup();
        $this->httpClient = new GuzzleHttpClient(['cookies' => true]);
    }


    /**
     * @Then I have live header
     */
    public function iHaveLiveHeader(): void
    {
        $storage = $this->storage();
        $headers = $storage->get('headers');
        $headers['live'] = true;
        $storage->set('headers', $headers);
    }

    /**
     * @Then I have the following authentication header :jwt
     */
    public function iHaveTheFollowingAuthenticationHeader(string $jwt): void
    {
        $storage = $this->storage();
        $headers = $storage->get('headers');
        $headers['Authorization'] = sprintf('Bearer %s', $jwt);
        $storage->set('headers', $headers);
    }

    /**
     * @When I make a GET request to :URL
     */
    public function iMakeAGetRequest(string $URL): void
    {
        $this->makeRequest('GET', $URL);
    }

    private function parseValue(mixed $value): mixed
    {
        preg_match_all('/([A-Z][A-Z|0-9|_|+]+\()([\w|\W]*)\)/', (string) $value, $matches, PREG_SET_ORDER);
        $func = null;
        if (count($matches)) {
            $func = substr($matches[0][1], 0, -1);
            $value = $matches[0][2];
            if ('' === $value) {
                $value = null;
            }
            if (null !== $value) {
                $value = $this->parseValue((string) $value);
            }
        }

        return match ($func) {
            'RANDOM_TEXT', 'RANDOM' => md5((string) mt_rand()),
            'YESTERDAY' => (new DateTime())->modify('-1 day')->format($value ?? 'Y-m-d 00:00:00'),
            'TODAY' => (new DateTime())->format($value ?? 'Y-m-d 00:00:00'),
            '20DAYS_AGO' => (new DateTime())->modify('-20 day')->format($value ?? 'Y-m-d 00:00:00'),
            '38DAYS_AGO' => (new DateTime())->modify('-38 day')->format($value ?? 'Y-m-d 00:00:00'),
            '1MONTH_AGO' => (new DateTime())->sub(new DateInterval('P1M')),
            '2MONTH_AGO' => $this->getDate(),
            '3MONTH_AGO' => (new DateTime())->sub(new DateInterval('P3M')),
            'TODAY+HOURS' => (new DateTime())->modify(sprintf('+%d hours', $value))->format('Y-m-d H:i:s'),
            'TOMORROW' => (new DateTime())->modify('1 day')->format($value ?? 'Y-m-d 00:00:00'),
            'TOMORROW+1' => (new DateTime())->modify('2 day')->format($value ?? 'Y-m-d 00:00:00'),
            'AS_STORED' => $this->storage()->get('store')[$value],
            'FROM_QUERY_STRING' => $this->responseQueryParams[$value],
            'FILE_GET_CONTENTS' => base64_encode(file_get_contents($value)),
            'TIMESTAMP' => (new DateTime())->getTimestamp(),
            'ENV' => getenv($value),
            'ENV_VALUE' => getenv($value) ?: $value,
            default => $value,
        };
    }

    private function getDate(): Datetime
    {
        $lastDayThisMonth = (int) (new DateTime())->format('t');
        $today = (int) (new DateTime())->format('d');

        return match (true) {
            in_array($today, [29, 30, 31], true) && $lastDayThisMonth === $today => (new DateTime(sprintf('2020-12-%s', $today))),
            default => (new DateTime())->sub(new DateInterval('P2M'))
        };
    }

    private function makeRequest(string $method, string $url, string $baseUrl = null, int $concurrency = null, string $saveAt = null): void
    {
        if ($this->stripeWebHookHeaders) {
            $headers = $this->storage()->get('headers');
            $timestamp = (new DateTime())->getTimestamp();

            try {
                $signature = hash_hmac('sha256', sprintf('%s.%s', $timestamp, json_encode($this->storage()->get('payload'), JSON_THROW_ON_ERROR)), getenv('STRIPE_WEBHOOK_SECRET'));
            } catch (Throwable) {
                $signature = '';
            }
            $headers['Stripe-Signature'] = sprintf('t=%s,v1=%s,v0=%s', $timestamp, $signature, $signature);
            $this->storage()->set('headers', $headers);
        }

        $url = $this->parseURL($url, $baseUrl);
        $request = new Request($method, $url, $this->storage()->get('headers'));
        $options = ['verify' => false, 'on_stats' => [$this, 'guzzleRequestStats']];
        if (null !== $saveAt) {
            $options['sink'] = $saveAt;
        }

        switch ($method) {
            case 'GET':
            case 'DELETE':
            case 'PURGE':
                if ([] !== $this->storage()->get('payload')) {
                    $options['query'] = $this->storage()->get('payload');
                }

                break;

            case 'POST':
                if (0 < count($this->storage()->get('files'))) {
                    $options['multipart'] = [];
                    foreach ($this->storage()->get('payload') as $key => $value) {
                        try {
                            $options['multipart'][] = [
                                'name' => $key,
                                'contents' => is_array($value) ? json_encode($value, JSON_THROW_ON_ERROR) : $value,
                            ];
                        } catch (Throwable) {
                        }
                    }
                    foreach ($this->storage()->get('files') as $name => $filePath) {
                        $options['multipart'][] = [
                            'name' => $name,
                            'contents' => fopen($filePath, 'rb'),
                            'filename' => $name,
                        ];
                    }
                } elseif ('application/x-www-form-urlencoded' === ($this->storage()->get('headers')['content-type'] ?? '')) {
                    $options['form_params'] = $this->storage()->get('payload');
                } else {
                    $options['json'] = $this->storage()->get('payload');
                }

                break;

            case 'PUT':
            case 'PATCH':
                if ('application/x-www-form-urlencoded' === ($this->storage()->get('headers')['content-type'] ?? '')) {
                    $options['form_params'] = $this->storage()->get('payload');
                } else {
                    $options['json'] = $this->storage()->get('payload');
                }

                break;
        }
        if (null === $concurrency) {
            $promise = $this->httpClient->sendAsync($request, $options)
                ->then(
                    function (ResponseInterface $response): void {
                        $storage = $this->storage();
                        $storage->set('responseCode', $response->getStatusCode());
                        $storage->set('responseType', self::parseResponseContentType($response->getHeaderLine('content-type')));
                        $storage->set('responseBody', $response->getBody());
                        $storage->set('responseHeaders', $response->getHeaders());
                    },
                    function (RequestException $reason): void {
                        $response = $reason->getResponse();
                        if (null !== $response) {
                            $storage = $this->storage();
                            $storage->set('responseCode', $response->getStatusCode());
                            $storage->set('responseType', self::parseResponseContentType($response->getHeaderLine('content-type')));
                            $storage->set('responseBody', $response->getBody());
                            $storage->set('responseHeaders', $response->getHeaders());
                        } else {
                            throw new RuntimeException($reason->getMessage());
                        }
                    }
                )
            ;
            $promise->wait();
        } else {
            $responses = [];
            $requests = static function (Request $request, int $total) {
                for ($i = 0; $i < $total; ++$i) {
                    yield $request;
                }
            };
            $pool = new GuzzleHttpPool(
                $this->httpClient,
                $requests($request, $concurrency),
                [
                    'options' => $options,
                    'concurrency' => 10,
                    'fulfilled' => static function (ResponseInterface $response, $index) use (&$responses): void {
                        $responses[$index] = [
                            'responseCode' => $response->getStatusCode(),
                            'responseType' => self::parseResponseContentType($response->getHeaderLine('content-type')),
                            'responseBody' => $response->getBody(),
                            'responseHeaders' => $response->getHeaders(),
                        ];
                    },
                    'rejected' => static function (RequestException $reason, $index) use (&$responses): void {
                        $response = $reason->getResponse();
                        if (null !== $response) {
                            $responses[$index] = [
                                'responseCode' => $response->getStatusCode(),
                                'responseType' => self::parseResponseContentType($response->getHeaderLine('content-type')),
                                'responseBody' => $response->getBody(),
                                'responseHeaders' => $response->getHeaders(),
                            ];
                        } else {
                            throw new RuntimeException($reason->getMessage());
                        }
                    },
                ]
            );

            $pool->promise()->wait();

            $this->storage()->set('responses', $responses);
        }
    }

    private function parseURL(string $url, string $baseUrl = null): string
    {
        return sprintf('%s%s', $baseUrl ?? $this->baseUrl, preg_replace('/([A-Z][A-Z|0-9|_]+\()([\w|\W]+)\)/', $this->parseValue($url), $url));
    }

    private static function parseResponseContentType(string $contentType): ?string
    {
        if (in_array($contentType, ['application/json', 'application/json; charset=UTF-8'], true)) {
            $contentType = 'json';
        } elseif (in_array($contentType, ['text/html', 'text/html; charset=UTF-8'], true)) {
            $contentType = 'html';
        } elseif (in_array($contentType, ['text/html', 'application/x-www-form-urlencoded'], true)) {
            $contentType = 'form';
        } elseif ('application/pdf' === $contentType) {
            $contentType = 'pdf';
        }

        return $contentType;
    }
}
