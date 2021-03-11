<?php

namespace Drupal\drupal_gdpr\Form;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class CSVExportSettingsForm extends ConfigFormBase
{
    const FORM_ID = 'drupal_gdpr.export_csv';

    const FIELD_LINKED_ENTITY_FIELDS = 'linked_entity_fields';
    const FIELD_MAPPING = 'mapping';
    const LINKED_BUNDLES = 'linked_bundles';
    const LINKED_BUNDLE_SUFFIX = '_linked_bundle';
    const LINKED_ENTITIES = 'linked_entities';
    const LINKED_ENTITIES_FIELDSET = 'linked_entities_fieldset';
    const LINKED_ENTITY_SUFFIX = '_linked_entity';

    const USER = 'user';
    const USER_FIELD_PREFIX = 'user_';
    const USER_FIELDS = 'user_fields';
    const USER_FIELDSET = 'user_fieldset';

    /** @var EntityFieldManagerInterface */
    protected $entityFieldManager;

    /** @var EntityTypeBundleInfoInterface */
    protected $entityTypeBundleInfo;

    /** @var EntityTypeManagerInterface */
    protected $entityTypeManager;

    /** {@inheritdoc} */
    public function __construct(ConfigFactoryInterface $config_factory, EntityFieldManagerInterface $entityFieldManager, EntityTypeBundleInfoInterface $entityTypeBundleInfo, EntityTypeManagerInterface $entityTypeManager)
    {
        parent::__construct($config_factory);
        $this->entityFieldManager = $entityFieldManager;
        $this->entityTypeManager = $entityTypeManager;
        $this->entityTypeBundleInfo = $entityTypeBundleInfo;
    }

    /** {@inheritdoc} */
    public static function create(ContainerInterface $container)
    {
        return new static(
            $container->get('config.factory'),
            $container->get('entity_field.manager'),
            $container->get('entity_type.bundle.info'),
            $container->get('entity_type.manager')
        );
    }

    public function buildForm(array $form, FormStateInterface $form_state)
    {
        $config = $this->config(self::FORM_ID);

        $form[self::USER_FIELDSET] = [
            '#type'       => 'fieldset',
            '#title'      => t('User entity'),
            '#attributes' => [
                'id' => 'user-fields-wrapper'
            ]
        ];

        $form[self::USER_FIELDSET][self::USER] = [
            '#type'          => 'checkbox',
            '#title'         => t('Export User fields'),
            '#default_value' => $config->get(self::USER),
            '#ajax'          => [
                'callback' => '::refreshUserFieldset',
                'wrapper'  => 'user-fields-wrapper'
            ]
        ];

        $displayUserFields = $form_state->getValue(self::USER) ?? $config->get(self::USER);
        if ($displayUserFields) {
            $form[self::USER_FIELDSET][self::USER_FIELDS] = [
                '#type'  => 'details',
                '#title' => t('Fields'),
                '#tree'  => true
            ];

            $fields = $this->entityFieldManager->getFieldDefinitions('user', 'user');
            foreach ($fields as $key => $field) {
                $form[self::USER_FIELDSET][self::USER_FIELDS][$key] = [
                    '#type'          => 'checkbox',
                    '#title'         => $key,
                    '#default_value' => $config->get(self::USER_FIELDS . '.' . $key)
                ];
            }
        }

        $form[self::LINKED_ENTITIES_FIELDSET] = [
            '#type'       => 'fieldset',
            '#title'      => t('Linked entities'),
            '#attributes' => [
                'id' => 'linked-entities-wrapper'
            ]
        ];

        $entityOptions = [];
        foreach ($this->entityTypeManager->getDefinitions() as $eid => $configuration) {
            if ($eid === self::USER) {
                continue;
            }

            $entityOptions[$eid] = $configuration->getLabel()->render();
        }

        $form[self::LINKED_ENTITIES_FIELDSET][self::LINKED_ENTITIES] = [
            '#type'          => 'checkboxes',
            '#options'       => $entityOptions,
            '#default_value' => $config->get(self::LINKED_ENTITIES) ?? [],
            '#ajax'          => [
                'callback' => '::refreshLinkedEntities',
                'wrapper'  => 'linked-entities-wrapper'
            ]
        ];

        $linkedEntities = $form_state->getValue(self::LINKED_ENTITIES) ?? $config->get(self::LINKED_ENTITIES);
        foreach ($linkedEntities as $entityTypeID => $checkedEntity) {
            if (empty($checkedEntity)) {
                continue;
            }

            try {
                $linkedEntity = $entityTypeID . self::LINKED_ENTITY_SUFFIX;
                $entityType = $this->entityTypeManager->getDefinition($entityTypeID);

                $form[self::LINKED_ENTITIES_FIELDSET][$linkedEntity] = [
                    '#type'       => 'details',
                    '#title'      => $entityType->getLabel(),
                    '#tree'       => true,
                    '#open'       => true,
                    '#attributes' => [
                        'id' => 'linked-bundles-' . $entityTypeID . '-wrapper'
                    ]
                ];

                $bundleOptions = [];
                foreach ($this->entityTypeBundleInfo->getBundleInfo($entityTypeID) as $bid => $configuration) {
                    if (!isset($configuration['label'])) {
                        continue;
                    }

                    $bundleOptions[$bid] = $configuration['label'];
                }

                $form[self::LINKED_ENTITIES_FIELDSET][$linkedEntity][self::LINKED_BUNDLES] = [
                    '#type'          => 'checkboxes',
                    '#options'       => $bundleOptions,
                    '#default_value' => $config->get($linkedEntity . '.' . self::LINKED_BUNDLES) ?? [],
                    '#ajax'          => [
                        'callback' => '::refreshLinkedBundles',
                        'wrapper'  => 'linked-bundles-' . $entityTypeID . '-wrapper'
                    ]
                ];

                $linkedBundles = $form_state->getValue($linkedEntity)[self::LINKED_BUNDLES] ?? $config->get($linkedEntity . '.' . self::LINKED_BUNDLES) ?? [];
                foreach ($linkedBundles as $bundleID => $checkedBundle) {
                    if (empty($checkedBundle)) {
                        continue;
                    }

                    $linkedBundle = $bundleID . self::LINKED_BUNDLE_SUFFIX;
                    $form[self::LINKED_ENTITIES_FIELDSET][$linkedEntity][$linkedBundle] = [
                        '#type'  => 'details',
                        '#title' => $bundleOptions[$bundleID]
                    ];

                    $form[self::LINKED_ENTITIES_FIELDSET][$linkedEntity][$linkedBundle][self::FIELD_MAPPING] = [
                        '#type'          => 'textfield',
                        '#title'         => t('Mapping field'),
                        '#default_value' => $config->get($linkedEntity . '.' . $linkedBundle . '.' . self::FIELD_MAPPING)
                    ];

                    $form[self::LINKED_ENTITIES_FIELDSET][$linkedEntity][$linkedBundle][self::FIELD_LINKED_ENTITY_FIELDS] = [
                        '#type'  => 'details',
                        '#title' => t('Fields')
                    ];

                    $fields = $this->entityFieldManager->getFieldDefinitions($entityTypeID, $bundleID);
                    foreach ($fields as $key => $field) {
                        $form[self::LINKED_ENTITIES_FIELDSET][$linkedEntity][$linkedBundle][self::FIELD_LINKED_ENTITY_FIELDS][$key] = [
                            '#type'          => 'checkbox',
                            '#title'         => $key,
                            '#default_value' => $config->get($linkedEntity . '.' . $linkedBundle . '.' . self::FIELD_LINKED_ENTITY_FIELDS . '.' . $key)
                        ];
                    }
                }
            } catch (PluginNotFoundException $e) {
                continue;
            }
        }

        return parent::buildForm($form, $form_state);
    }

    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        $config = $this->config(self::FORM_ID);
        $form_state->cleanValues();
        $values = $form_state->getValues();

        if (isset($values[self::USER])) {
            $userFields = (bool) $values[self::USER];
            $config->set(self::USER, $userFields);
            $config->set(self::USER_FIELDS, $userFields ? ($values[self::USER_FIELDS] ?? null) : null);
        }

        if (isset($values[self::LINKED_ENTITIES])) {
            $config->set(self::LINKED_ENTITIES, $values[self::LINKED_ENTITIES]);
            foreach ($values[self::LINKED_ENTITIES] as $entityTypeID => $checked) {
                if (empty($checked)) {
                    continue;
                }

                $linkedEntity = $entityTypeID . self::LINKED_ENTITY_SUFFIX;
                if (!isset($values[$linkedEntity])) {
                    continue;
                }

                $config->set($linkedEntity, $values[$linkedEntity]);
            }
        }

        $config->save();
    }

    public function getFormId()
    {
        return self::FORM_ID;
    }

    public function refreshLinkedBundles(array &$form, FormStateInterface $form_state)
    {
        $triggered = $form_state->getTriggeringElement();

        return $form[self::LINKED_ENTITIES_FIELDSET][$triggered['#parents'][0]];
    }

    public function refreshLinkedEntities(array &$form, FormStateInterface $form_state)
    {
        return $form[self::LINKED_ENTITIES_FIELDSET];
    }

    public function refreshUserFieldset(array &$form, FormStateInterface $form_state)
    {
        return $form[self::USER_FIELDSET];
    }

    protected function getEditableConfigNames()
    {
        return [
            self::FORM_ID
        ];
    }
}
