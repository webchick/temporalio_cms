<?php

namespace Drupal\temporal_cms\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\Entity\Node;
use Drupal\node\Event\NodeInsertEvent;
use Drupal\node\Event\NodeUpdateEvent;
use Drupal\node\NodeEvents;
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

  public static function getSubscribedEvents(): array {
    return [
      NodeEvents::INSERT => 'onNodeInsert',
      NodeEvents::UPDATE => 'onNodeUpdate',
    ];
  }

  public function onNodeInsert(NodeInsertEvent $event): void {
    if ($this->suppressStart) {
      return;
    }
    $this->maybeStartWorkflow($event->getNode(), 'create');
  }

  public function onNodeUpdate(NodeUpdateEvent $event): void {
    if ($this->suppressStart) {
      return;
    }
    $node = $event->getOriginal();
    $updated = $event->getNode();

    $config = $this->configFactory->get('temporal_cms.settings');
    if ($config->get('start_on_publish')) {
      if ($node instanceof Node && !$node->isPublished() && $updated->isPublished()) {
        $this->maybeStartWorkflow($updated, 'publish');
      }
    }
  }

  protected function maybeStartWorkflow(Node $node, string $trigger): void {
    $config = $this->configFactory->get('temporal_cms.settings');
    $monitored = array_filter($config->get('monitored_content_types') ?? []);
    $start_on_create = $config->get('start_on_create');

    if ($trigger === 'create' && !$start_on_create) {
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
    if ($workflowId) {
      $this->keyValueFactory->get(self::STORE_KEY)->set((string) $node->id(), $workflowId);
      $this->loggerFactory->get('temporal_cms')->info('Temporal workflow @workflow started for node @nid.', [
        '@workflow' => $workflowId,
        '@nid' => $node->id(),
      ]);
      $this->persistWorkflowId($node, $workflowId);
    }
  }

  protected function persistWorkflowId(Node $node, string $workflowId): void {
    if (!$node->hasField('temporal_workflow_id')) {
      return;
    }

    $node->set('temporal_workflow_id', $workflowId);
    $this->suppressStart = TRUE;
    try {
      $this->entityTypeManager->getStorage('node')->save($node);
    }
    finally {
      $this->suppressStart = FALSE;
    }
  }
}
