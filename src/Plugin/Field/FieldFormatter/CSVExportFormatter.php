<?php

namespace Drupal\drupal_gdpr\Plugin\Field\FieldFormatter;

use Drupal\Core\Annotation\Translation;
use Drupal\Core\Field\Annotation\FieldFormatter;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxy;
use Drupal\Core\Url;
use Drupal\drupal_gdpr\Plugin\Field\FieldType\CSVExport;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @FieldFormatter(
 *   id = "gdpr_csv_export_formatter",
 *   label = @Translation("Link"),
 *   field_types = {
 *     "gdpr_csv_export"
 *   },
 *   settings = {
 *     "classes" = ""
 *   }
 * )
 */
class CSVExportFormatter extends FormatterBase
{
    const CLASSES = 'classes';

    /** @var AccountProxy */
    private $currentUser;

    /** {@inheritdoc} */
    public function __construct(
        $plugin_id,
        $plugin_definition,
        FieldDefinitionInterface $field_definition,
        array $settings,
        $label,
        $view_mode,
        array $third_party_settings,
        AccountProxy $currentUser
    ) {
        parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
        $this->currentUser = $currentUser;
    }

    /** {@inheritdoc} */
    public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition)
    {
        return new static(
            $plugin_id,
            $plugin_definition,
            $configuration['field_definition'],
            $configuration['settings'],
            $configuration['label'],
            $configuration['view_mode'],
            $configuration['third_party_settings'],
            $container->get('current_user')
        );
    }

    /** {@inheritdoc} */
    public static function defaultSettings()
    {
        return [
                self::CLASSES => ''
            ] + parent::defaultSettings();
    }

    /** {@inheritdoc} */
    public function settingsForm(array $form, FormStateInterface $form_state)
    {
        $element = parent::settingsForm($form, $form_state);

        $element[self::CLASSES] = [
            '#type'          => 'textfield',
            '#title'         => t('Classes'),
            '#description'   => t('Added to the link for rendering'),
            '#default_value' => $this->getSetting(self::CLASSES),
            '#required'      => false
        ];

        return $element;
    }

    /** {@inheritdoc} */
    public function settingsSummary()
    {
        $summary = [];

        $classes = $this->getSetting(self::CLASSES);
        if (!empty($classes)) {
            $summary[self::CLASSES] = t('Classes : ') . $classes;
        }

        return $summary;
    }

    /** {@inheritdoc} */
    public function viewElements(FieldItemListInterface $items, $langcode)
    {
        $elements = [];

        foreach ($items as $delta => $item) {
            if (!$item instanceof CSVExport) {
                continue;
            }

            $label = $item->getLinkLabel();
            if (empty($label)) {
                continue;
            }

            $uid = $this->currentUser->id();
            if ($uid === 0) {
                continue;
            }

            $elements[$delta] = [
                '#type'  => 'link',
                '#title' => $label,
                '#url'   => Url::fromRoute('drupal_gdpr.export_csv', [
                    'uid' => $uid
                ]),
                '#attributes' => [
                    'class' => [$this->getSetting(self::CLASSES)]
                ]
            ];
        }

        return $elements;
    }
}
