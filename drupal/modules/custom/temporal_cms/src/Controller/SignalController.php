<?php

namespace Drupal\temporal_cms\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\temporal_cms\Service\TemporalClient;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class SignalController extends ControllerBase {

  public function __construct(
    private readonly KeyValueFactoryInterface $keyValueFactory,
    private readonly TemporalClient $temporalClient,
    private readonly CsrfTokenGenerator $csrfToken,
  ) {}

  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('keyvalue'),
      $container->get('temporal_cms.temporal_client'),
      $container->get('csrf_token'),
    );
  }

  public function send(Request $request, NodeInterface $node): Response {
    $signal = $request->get('signal');
    if (!$signal) {
      $this->messenger()->addError($this->t('Signal parameter missing.'));
      return $this->redirectToNode($node->id());
    }

    if (!$node->access('update')) {
      $this->messenger()->addError($this->t('You do not have access to signal this workflow.'));
      return $this->redirectToNode($node->id());
    }

    $workflowId = NULL;
    if ($node->hasField('temporal_cms_workflow_id') && !$node->get('temporal_cms_workflow_id')->isEmpty()) {
      $workflowId = $node->get('temporal_cms_workflow_id')->value;
    }
    if (!$workflowId) {
      $workflowId = $this->keyValueFactory->get('temporal_cms.workflow_map')->get((string) $node->id());
    }
    if (!$workflowId) {
      $this->messenger()->addError($this->t('No workflow linked to node @nid.', ['@nid' => $node->id()]));
      return $this->redirectToNode($node->id());
    }

    $token = $request->get('token');
    $context = $this->buildTokenContext($workflowId, $signal, $request->get('locale'));
    if (!$token || !$this->csrfToken->validate($token, $context)) {
      $this->messenger()->addError($this->t('Invalid signal token.'));
      return $this->redirectToNode($node->id());
    }

    $payload = [];
    if ($signal === 'translationComplete') {
      $payload['locale'] = $request->get('locale');
    }

    if ($this->temporalClient->sendSignal($workflowId, $signal, $payload)) {
      $this->messenger()->addStatus($this->t('Signal @signal sent.', ['@signal' => $signal]));
    }
    else {
      $this->messenger()->addError($this->t('Failed to send signal @signal.', ['@signal' => $signal]));
    }

    return $this->redirectToNode($node->id());
  }

  protected function redirectToNode($nid): RedirectResponse {
    return $this->redirect('entity.node.canonical', ['node' => $nid]);
  }

  protected function buildTokenContext(string $workflowId, string $signal, ?string $locale = NULL): string {
    return implode(':', array_filter(['temporal_cms', $workflowId, $signal, $locale]));
  }

}
