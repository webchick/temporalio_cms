<?php

namespace Drupal\temporal_cms\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Link;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\Url;
use Drupal\temporal_cms\Service\TemporalClient;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\node\NodeInterface;

/**
 * Provides a block showing Temporal workflow status.
 *
 * @Block(
 *   id = "temporal_workflow_status",
 *   admin_label = @Translation("Temporal workflow status")
 * )
 */
class WorkflowStatusBlock extends BlockBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly CurrentRouteMatch $routeMatch,
    private readonly KeyValueFactoryInterface $keyValueFactory,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly CsrfTokenGenerator $csrfToken,
    private readonly TemporalClient $temporalClient,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_route_match'),
      $container->get('keyvalue.factory'),
      $container->get('config.factory'),
      $container->get('csrf_token'),
      $container->get('temporal_cms.temporal_client'),
    );
  }

  public function build(): array {
    $node = $this->routeMatch->getParameter('node');
    if (!$node instanceof NodeInterface) {
      return ['#markup' => $this->t('Temporal status unavailable.')];
    }

    $workflowId = NULL;
    if ($node->hasField('temporal_workflow_id') && !$node->get('temporal_workflow_id')->isEmpty()) {
      $workflowId = $node->get('temporal_workflow_id')->value;
    }
    $workflowId = $workflowId ?: $this->keyValueFactory->get('temporal_cms.workflow_map')->get((string) $node->id());
    if (!$workflowId) {
      return ['#markup' => $this->t('No Temporal workflow linked.')];
    }

    $status = $this->temporalClient->fetchStatus($workflowId);
    if (!$status) {
      return ['#markup' => $this->t('Workflow status unavailable.')];
    }

    $build = [
      '#theme' => 'item_list',
      '#title' => $this->t('Temporal workflow'),
      '#items' => [
        $this->t('Workflow ID: @id', ['@id' => $workflowId]),
        $this->t('Stage: @stage', ['@stage' => $status['stage'] ?? 'unknown']),
        $this->t('Pending locales: @locales', ['@locales' => implode(', ', $status['pendingLocales'] ?? [])]),
      ],
    ];

    $actions = $this->buildActions($node, $workflowId, $status);
    if (!empty($actions)) {
      $build['actions'] = [
        '#theme' => 'item_list',
        '#title' => $this->t('Workflow actions'),
        '#items' => $actions,
        '#attributes' => ['class' => ['temporal-workflow-actions']],
      ];
    }

    return $build;
  }

  protected function buildActions(NodeInterface $node, string $workflowId, array $status): array {
    $stage = $status['stage'] ?? '';
    $items = [];

    $config = $this->configFactory->get('temporal_cms.settings');
    $locales = $config->get('default_locales') ?? [];

    if (in_array($stage, ['translation', 'compliance', 'awaitingApproval'], TRUE)) {
      foreach ($locales as $locale) {
        $items[] = Link::fromTextAndUrl(
          $this->t('Mark @locale translated', ['@locale' => $locale]),
          $this->buildSignalUrl($node->id(), $workflowId, 'translationComplete', ['locale' => $locale])
        )->toRenderable();
      }
    }

    if ($stage === 'awaitingApproval') {
      $items[] = Link::fromTextAndUrl(
        $this->t('Approve content'),
        $this->buildSignalUrl($node->id(), $workflowId, 'approvalGranted')
      )->toRenderable();
    }

    if (in_array($stage, ['scheduled', 'awaitingApproval'], TRUE)) {
      $items[] = Link::fromTextAndUrl(
        $this->t('Publish now'),
        $this->buildSignalUrl($node->id(), $workflowId, 'publishNow')
      )->toRenderable();
    }

    return $items;
  }

  protected function buildSignalUrl(int $nid, string $workflowId, string $signal, array $query = []): Url {
    $context = $this->buildTokenContext($workflowId, $signal, $query['locale'] ?? NULL);
    $query['signal'] = $signal;
    $query['token'] = $this->csrfToken->get($context);

    return Url::fromRoute('temporal_cms.signal', ['node' => $nid], [
      'query' => $query,
      'attributes' => ['class' => ['button', 'button--small']],
    ]);
  }

  protected function buildTokenContext(string $workflowId, string $signal, ?string $locale = NULL): string {
    return implode(':', array_filter(['temporal_cms', $workflowId, $signal, $locale]));
  }

}
