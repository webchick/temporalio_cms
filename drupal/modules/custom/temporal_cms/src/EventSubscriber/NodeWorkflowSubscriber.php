<?php

namespace Drupal\temporal_cms\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\node\Entity\Node;
use Drupal\node\Event\NodeInsertEvent;
use Drupal\node\Event\NodeUpdateEvent;
use Drupal\temporal_cms\Service\TemporalClient;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

class NodeWorkflowSubscriber implements EventSubscriberInterface {
  use StringTranslationTrait;

  protected const STORE_KEY = 'temporal_cms.workflow_map';

  private bool $suppressStart = FALSE;

  public function __construct(
    private readonly TemporalClient $temporalClient,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly KeyValueFactoryInterface $keyValueFactory,
    private readonly AccountProxyInterface $currentUser,
    private readonly LoggerChannelFactoryInterface $loggerFactory,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  protected function logger(): LoggerChannelInterface {
    return $this->loggerFactory->get('temporal_cms');
  }

  public static function getSubscribedEvents(): array {
    return [
      'node.insert' => 'onNodeInsert',
      'node.update' => 'onNodeUpdate',
    ];
  }

  public function onNodeInsert(NodeInsertEvent $event): void {
    $this->logger()->info('Node insert detected for @nid.', ['@nid' => $event->getNode()->id()]);
    if ($this->suppressStart) {
      $this->logger()->info('Workflow start suppressed (insert).');
      return;
    }
    $this->maybeStartWorkflow($event->getNode(), 'create');
  }

  public function onNodeUpdate(NodeUpdateEvent $event): void {
    $this->logger()->info('Node update detected for @nid.', ['@nid' => $event->getNode()->id()]);
    if ($this->suppressStart) {
      $this->logger()->info('Workflow start suppressed (update).');
      return;
    }
    $node = $event->getOriginal();
    $updated = $event->getNode();

    $config = $this->configFactory->get('temporal_cms.settings');
    if ($config->get('start_on_publish')) {
      if ($node instanceof Node && !$node->isPublished() && $updated->isPublished()) {
        $this->maybeStartWorkflow($updated, 'publish');
      }
      else {
        $this->logger()->info('Publish trigger not met for @nid.', ['@nid' => $updated->id()]);
      }
    }
    else {
      $this->logger()->info('start_on_publish disabled; skipping publish trigger.');
    }
  }

  protected function maybeStartWorkflow(Node $node, string $trigger): void {
    $config = $this->configFactory->get('temporal_cms.settings');
    $monitored = array_filter($config->get('monitored_content_types') ?? []);
    $start_on_create = $config->get('start_on_create');

    $this->logger()->info('maybeStartWorkflow trigger=@trigger bundle=@bundle monitored=@monitored', [
      '@trigger' => $trigger,
      '@bundle' => $node->bundle(),
      '@monitored' => implode(',', $monitored),
    ]);

    if ($trigger === 'create' && !$start_on_create) {
      $this->logger()->info('start_on_create disabled; skipping workflow start.');
      return;
    }
    if (!in_array($node->bundle(), $monitored, TRUE)) {
      $this->logger()->info('Bundle @bundle not monitored; skipping workflow start.', ['@bundle' => $node->bundle()]);
      return;
    }

    $payload = [
      'cmsId' => (string) $node->id(),
      'site' => $config->get('site_identifier') ?? 'drupal',
      'locales' => $config->get('default_locales') ?? ['en'],
      'requestedBy' => $this->currentUser->getAccountName() ?: 'system',
    ];

    $workflowId = $this->temporalClient->startWorkflow($payload);
    if ($workflowId) {
      $this->keyValueFactory->get(self::STORE_KEY)->set((string) $node->id(), $workflowId);
      $this->logger()->info('Temporal workflow @workflow started for node @nid.', [
        '@workflow' => $workflowId,
        '@nid' => $node->id(),
      ]);
      $this->persistWorkflowId($node, $workflowId);
    }
    else {
      $this->logger()->error('Temporal workflow failed to start for node @nid.', ['@nid' => $node->id()]);
    }
  }

  protected function persistWorkflowId(Node $node, string $workflowId): void {
    if (!$node->hasField('temporal_cms_workflow_id')) {
      $this->logger()->warning('Node @nid missing temporal_cms_workflow_id field.', ['@nid' => $node->id()]);
      return;
    }

    $node->set('temporal_cms_workflow_id', $workflowId);
    $this->suppressStart = TRUE;
    try {
      $this->entityTypeManager->getStorage('node')->save($node);
      $this->logger()->info('Persisted workflow @workflow to node @nid.', [
        '@workflow' => $workflowId,
        '@nid' => $node->id(),
      ]);
    }
    finally {
      $this->suppressStart = FALSE;
    }
  }
}
