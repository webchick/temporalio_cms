<?php

namespace Drupal\temporal_cms\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\node\Entity\Node;

class TemporalWorkflowManager {

  public function __construct(
    private readonly TemporalClient $temporalClient,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly KeyValueFactoryInterface $keyValueFactory,
    private readonly AccountProxyInterface $currentUser,
    private readonly LoggerChannelFactoryInterface $loggerFactory,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Trigger workflow based on the configured rules.
   */
  public function triggerWorkflow(Node $node, string $trigger): void {
    $config = $this->configFactory->get('temporal_cms.settings');
    $monitored = array_filter($config->get('monitored_content_types') ?? []);

    if ($trigger === 'create' && empty($config->get('start_on_create'))) {
      return;
    }
    if ($trigger === 'update' && empty($config->get('start_on_publish'))) {
      return;
    }
    if (!in_array($node->bundle(), $monitored, TRUE)) {
      return;
    }

    $payload = [
      'cmsId' => (string) $node->id(),
      'site' => $config->get('site_identifier') ?? 'drupal',
      'locales' => $config->get('default_locales') ?? ['en'],
      'requestedBy' => $this->currentUser->getAccountName() ?: 'system',
    ];

    $workflowId = $this->temporalClient->startWorkflow($payload);
    if (!$workflowId) {
      $this->loggerFactory->get('temporal_cms')->error('Failed to start workflow for node @nid.', ['@nid' => $node->id()]);
      return;
    }

    $this->keyValueFactory->get('temporal_cms.workflow_map')->set((string) $node->id(), $workflowId);
    $this->persistWorkflowId($node, $workflowId);
  }

  protected function persistWorkflowId(Node $node, string $workflowId): void {
    if (!$node->hasField('temporal_cms_workflow_id')) {
      return;
    }

    $node->set('temporal_cms_workflow_id', $workflowId);
    $this->entityTypeManager->getStorage('node')->save($node);
  }

}
