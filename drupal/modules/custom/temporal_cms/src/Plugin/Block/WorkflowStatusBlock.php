<?php

namespace Drupal\temporal_cms\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
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
      $container->get('temporal_cms.temporal_client'),
    );
  }

  public function build(): array {
    $node = $this->routeMatch->getParameter('node');
    if (!$node instanceof NodeInterface) {
      return ['#markup' => $this->t('Temporal status unavailable.')];
    }

    $workflowId = $this->keyValueFactory->get('temporal_cms.workflow_map')->get((string) $node->id());
    if (!$workflowId) {
      return ['#markup' => $this->t('No Temporal workflow linked.')];
    }

    $status = $this->temporalClient->fetchStatus($workflowId);
    if (!$status) {
      return ['#markup' => $this->t('Workflow status unavailable.')];
    }

    return [
      '#theme' => 'item_list',
      '#title' => $this->t('Temporal workflow'),
      '#items' => [
        $this->t('Workflow ID: @id', ['@id' => $workflowId]),
        $this->t('Stage: @stage', ['@stage' => $status['stage'] ?? 'unknown']),
        $this->t('Pending locales: @locales', ['@locales' => implode(', ', $status['pendingLocales'] ?? [])]),
      ],
    ];
  }

}
