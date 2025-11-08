<?php

namespace Drupal\temporal_cms\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\NodeType;

class TemporalSettingsForm extends ConfigFormBase {

  public function getFormId(): string {
    return 'temporal_cms_settings_form';
  }

  protected function getEditableConfigNames(): array {
    return ['temporal_cms.settings'];
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('temporal_cms.settings');

    $form['endpoint'] = [
      '#type' => 'url',
      '#title' => $this->t('REST proxy base URL'),
      '#default_value' => $config->get('endpoint') ?? 'http://localhost:4000',
      '#required' => TRUE,
      '#description' => $this->t('Base URL of the worker REST proxy (e.g. http://localhost:4000).'),
    ];

    $form['site_identifier'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Site identifier'),
      '#default_value' => $config->get('site_identifier') ?? 'drupal',
      '#required' => TRUE,
    ];

    $form['default_locales'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Default locales'),
      '#default_value' => implode("\n", $config->get('default_locales') ?? ['en']),
      '#description' => $this->t('One locale per line (e.g. en, fr_CA, es_MX).'),
    ];

    $options = [];
    foreach (NodeType::loadMultiple() as $type_id => $type) {
      $options[$type_id] = $type->label();
    }

    $form['monitored_content_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Content types to monitor'),
      '#options' => $options,
      '#default_value' => $config->get('monitored_content_types') ?? [],
      '#description' => $this->t('Workflows start automatically when these content types match the triggers below.'),
    ];

    $form['start_on_create'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Start workflow on creation'),
      '#default_value' => $config->get('start_on_create') ?? TRUE,
    ];

    $form['start_on_publish'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Start workflow on publish transition'),
      '#default_value' => $config->get('start_on_publish') ?? FALSE,
      '#description' => $this->t('When checked, workflows also start when a monitored node transitions from unpublished to published.'),
    ];

    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $locales_raw = array_filter(array_map('trim', explode("\n", $form_state->getValue('default_locales') ?? '')));

    $this->configFactory->getEditable('temporal_cms.settings')
      ->set('endpoint', $form_state->getValue('endpoint'))
      ->set('site_identifier', $form_state->getValue('site_identifier'))
      ->set('default_locales', $locales_raw ?: ['en'])
      ->set('monitored_content_types', array_filter($form_state->getValue('monitored_content_types') ?? []))
      ->set('start_on_create', (bool) $form_state->getValue('start_on_create'))
      ->set('start_on_publish', (bool) $form_state->getValue('start_on_publish'))
      ->save();

    parent::submitForm($form, $form_state);
  }
}
