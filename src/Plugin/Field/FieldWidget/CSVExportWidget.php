<?php

namespace Drupal\drupal_gdpr\Plugin\Field\FieldWidget;

use Drupal\Core\Annotation\Translation;
use Drupal\Core\Field\Annotation\FieldWidget;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\drupal_gdpr\Plugin\Field\FieldType\CSVExport;

/**
 * @FieldWidget(
 *   id = "gdpr_csv_export_widget",
 *   label = @Translation("Link"),
 *   field_types = {
 *     "gdpr_csv_export"
 *   }
 * )
 */
class CSVExportWidget extends WidgetBase
{
    /** {@inheritdoc} */
    public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state)
    {
        $element = [];

        if (!isset($items[$delta])) {
            return $element;
        }

        $item = $items[$delta];
        if (!$item instanceof CSVExport) {
            return $element;
        }

        $element[CSVExport::LINK_LABEL] = [
            '#type'          => 'textfield',
            '#title'         => t('Link label'),
            '#placeholder'   => t('Label that will be used to display the link'),
            '#default_value' => $item->getLinkLabel(),
            '#required'      => $element['#required'] ?? false
        ];

        return $element;
    }
}
