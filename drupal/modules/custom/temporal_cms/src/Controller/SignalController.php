<?php

namespace Drupal\temporal_cms\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\temporal_cms\Service\TemporalClient;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class SignalController extends ControllerBase {

  public function __construct(
    private readonly KeyValueFactoryInterface $keyValueFactory,
    private readonly TemporalClient $temporalClient,
  ) {}

  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('keyvalue.factory'),
      $container->get('temporal_cms.temporal_client'),
    );
  }

  public function send(Request $request, $node): Response {
    $signal = $request->get('signal');
    if (!$signal) {
      $this->messenger()->addError($this->t('Signal parameter missing.'));
      return $this->redirectToNode($node);
    }

    $workflowId = $this->keyValueFactory->get('temporal_cms.workflow_map')->get((string) $node);
    if (!$workflowId) {
      $this->messenger()->addError($this->t('No workflow linked to node @nid.', ['@nid' => $node]));
      return $this->redirectToNode($node);
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

    return $this->redirectToNode($node);
  }

  protected function redirectToNode($nid): RedirectResponse {
    return $this->redirect('entity.node.canonical', ['node' => $nid]);
  }

}
