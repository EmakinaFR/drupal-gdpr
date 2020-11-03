<?php

namespace Drupal\drupal_gdpr\Plugin\Field\FieldType;

use Drupal\Core\Annotation\Translation;
use Drupal\Core\Field\Annotation\FieldType;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Core\TypedData\Exception\MissingDataException;

/**
 * @FieldType(
 *   id = "gdpr_csv_export",
 *   label = @Translation("CSV Export"),
 *   default_formatter = "gdpr_csv_export_formatter",
 *   default_widget = "gdpr_csv_export_widget"
 * )
 */
class CSVExport extends FieldItemBase
{
    const LINK_LABEL = 'link_label';

    /** {@inheritdoc} */
    public static function schema(FieldStorageDefinitionInterface $field_definition)
    {
        return [
            'columns' => [
                self::LINK_LABEL => [
                    'type' => 'text'
                ]
            ]
        ];
    }

    /** {@inheritdoc} */
    public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition)
    {
        $properties[self::LINK_LABEL] = DataDefinition::create('string')
            ->setLabel(t('Link label'));

        return $properties;
    }

    /**
     * {@inheritdoc}
     * @throws MissingDataException
     */
    public function isEmpty()
    {
        return empty($this->get(self::LINK_LABEL)->getValue());
    }

    public function getLinkLabel(): string
    {
        try {
            return $this->get(self::LINK_LABEL)->getString();
        } catch (MissingDataException $e) {
            return '';
        }
    }
}
