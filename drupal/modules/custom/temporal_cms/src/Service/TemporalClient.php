<?php

namespace Drupal\temporal_cms\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;

class TemporalClient {

  public function __construct(
    private readonly ClientInterface $httpClient,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly LoggerChannelFactoryInterface $loggerFactory,
  ) {}

  protected function baseUrl(): string {
    $config = $this->configFactory->get('temporal_cms.settings');
    return rtrim($config->get('endpoint') ?? 'http://localhost:4000', '/');
  }

  protected function logger() {
    return $this->loggerFactory->get('temporal_cms');
  }

  public function startWorkflow(array $payload): ?string {
    $url = $this->baseUrl() . '/workflows';
    try {
      $response = $this->httpClient->request('POST', $url, [
        'json' => $payload,
        'timeout' => 5,
      ]);
      $data = json_decode((string) $response->getBody(), TRUE);
      return $data['workflowId'] ?? NULL;
    }
    catch (GuzzleException $exception) {
      $this->logger()->error('Failed to start workflow: @message', ['@message' => $exception->getMessage()]);
      return NULL;
    }
  }

  public function sendSignal(string $workflowId, string $signal, array $body = []): bool {
    $url = sprintf('%s/signals/%s/%s', $this->baseUrl(), $workflowId, $signal);
    try {
      $this->httpClient->request('POST', $url, [
        'json' => $body,
        'timeout' => 5,
      ]);
      return TRUE;
    }
    catch (GuzzleException $exception) {
      $this->logger()->error('Failed to send Temporal signal @signal: @message', [
        '@signal' => $signal,
        '@message' => $exception->getMessage(),
      ]);
      return FALSE;
    }
  }

  public function fetchStatus(string $workflowId): ?array {
    $url = sprintf('%s/workflows/%s/status', $this->baseUrl(), $workflowId);
    try {
      $response = $this->httpClient->request('GET', $url, ['timeout' => 5]);
      return json_decode((string) $response->getBody(), TRUE);
    }
    catch (GuzzleException $exception) {
      $this->logger()->warning('Failed to fetch Temporal status for @id: @message', [
        '@id' => $workflowId,
        '@message' => $exception->getMessage(),
      ]);
      return NULL;
    }
  }

}
