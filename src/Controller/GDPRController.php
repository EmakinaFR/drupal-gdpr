<?php

namespace Drupal\drupal_gdpr\Controller;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityFieldManager;
use Drupal\Core\File\FileSystem;
use Drupal\drupal_gdpr\Form\CSVExportSettingsForm;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use ZipArchive;

class GDPRController extends ControllerBase
{
    const CSV_EXPORTS_FOLDER = 'public://gdpr/csv_exports';

    /** @var EntityFieldManager */
    protected $entityFieldManager;

    /** @var FileSystem */
    private $fileSystem;

    public function __construct(EntityFieldManager $entityFieldManager, FileSystem $fileSystem)
    {
        $this->entityFieldManager = $entityFieldManager;
        $this->fileSystem = $fileSystem;
    }

    /** {@inheritdoc} */
    public static function create(ContainerInterface $container)
    {
        return new static(
            $container->get('entity_field.manager'),
            $container->get('file_system')
        );
    }

    public function exportCSV(string $uid)
    {
        $currentUser = $this->currentUser();
        if ($uid === '0' || $currentUser->id() !== $uid) {
            throw new NotFoundHttpException();
        }

        $user = User::load($uid);
        if (!$user instanceof User) {
            throw new NotFoundHttpException();
        }

        $csvs = [];
        $personalFolder = self::CSV_EXPORTS_FOLDER . '/' . $uid;
        $this->fileSystem->prepareDirectory($personalFolder, FileSystem::CREATE_DIRECTORY);

        $config = $this->config(CSVExportSettingsForm::FORM_ID);
        if ((bool) $config->get(CSVExportSettingsForm::USER)) {
            $csvs[] = $this->convertUserToCSV($user, $config->get(CSVExportSettingsForm::USER_FIELDS) ?? [], $personalFolder);
        }

        $linkedEntities = $config->get(CSVExportSettingsForm::LINKED_ENTITIES);
        foreach ($linkedEntities as $entityTypeID => $checkedEntity) {
            if (empty($checkedEntity)) {
                continue;
            }

            $linkedEntity = $config->get($entityTypeID . CSVExportSettingsForm::LINKED_ENTITY_SUFFIX);
            $linkedBundles = $linkedEntity[CSVExportSettingsForm::LINKED_BUNDLES] ?? [];
            foreach ($linkedBundles as $bundleID => $checkedBundle) {
                if (empty($checkedBundle)) {
                    continue;
                }

                $linkedBundle = $linkedEntity[$bundleID . CSVExportSettingsForm::LINKED_BUNDLE_SUFFIX] ?? [];
                $csvs[] = $this->convertEntityToCSV($user, $entityTypeID, $bundleID, $linkedBundle, $personalFolder);
            }
        }

        if (!empty($csvs)) {
            $filename = 'export_' . time() . '.zip';
            $uri = $personalFolder . '/' . $filename;

            // Generating Zip path and create the file
            $absolutePersonalPath = $this->fileSystem->realpath($personalFolder);
            $absoluteZipPath = $absolutePersonalPath . '/' . $filename;
            $zip = new ZipArchive();
            if ($zip->open($absoluteZipPath, ZipArchive::CREATE)) {
                foreach ($csvs as $csv) {
                    if (empty($csv)) {
                        continue;
                    }

                    $absoluteFilePath = $absolutePersonalPath . '/' . $csv;
                    $zip->addFile($absoluteFilePath, $csv);
                }

                $zip->close();
                $response = new BinaryFileResponse($uri);
                $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename);
                $response->send();
            }
        }

        return new Response(null, 200);
    }

    private function convertEntityToCSV(User $user, string $entityTypeID, string $bundleID, array $configuration, string $personalFolder): ?string
    {
        try {
            $entityTypeFields = $this->entityFieldManager->getFieldDefinitions($entityTypeID, $bundleID);
            $mappingField = $configuration[CSVExportSettingsForm::FIELD_MAPPING] ?? '';
            if (empty($mappingField) || !isset($entityTypeFields[$mappingField])) {
                return null;
            }

            $entityTypeManager = $this->entityTypeManager();
            $storage = $entityTypeManager->getStorage($entityTypeID);
            $query = $storage->getQuery()
                ->condition($mappingField, $user->id());

            if (isset($entityTypeFields['type'])) {
                $query->condition('type', $bundleID);
            }

            $eids = $query->execute();
            if (!is_array($eids) || empty($eids)) {
                return null;
            }

            $already = false;
            $columns = [];
            $formattedEntities = [];

            $configuredFields = $configuration[CSVExportSettingsForm::FIELD_LINKED_ENTITY_FIELDS];
            foreach ($storage->loadMultiple($eids) as $index => $entity) {
                $formattedEntity = [];
                foreach ($configuredFields as $key => $activated) {
                    if (!$activated || !$entity->hasField($key)) {
                        continue;
                    }

                    $formattedEntity[$key] = $entity->get($key)->getString();
                    if (!$already) {
                        $columns[] = $entity->get($key)->getFieldDefinition()->getLabel();
                    }
                }

                $formattedEntities[$index] = $formattedEntity;
                $already = true;
            }

            $filename = 'export_' . $entityTypeID . '_' . $bundleID . '_' . time() . '.csv';
            $uri = $personalFolder . '/' . $filename;
            if (!$csv = fopen($uri, 'w')) {
                return null;
            }

            fputcsv($csv, $columns, ';');
            foreach ($formattedEntities as $formattedEntity) {
                fputcsv($csv, $formattedEntity, ';');
            }
            fclose($csv);

            return $filename;
        } catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
            return null;
        }
    }

    private function convertUserToCSV(User $user, array $fields, string $personalFolder): ?string
    {
        $columns = [];
        $formattedUser = [];

        foreach ($fields as $key => $activated) {
            if (!$activated) {
                continue;
            }

            $field = $user->get($key);
            $columns[] = $field->getFieldDefinition()->getLabel();
            $formattedUser[] = $field->getString();
        }

        $filename = 'export_user_' . time() . '.csv';
        $uri = $personalFolder . '/' . $filename;
        if (!$csv = fopen($uri, 'w')) {
            return null;
        }

        fputcsv($csv, $columns, ';');
        fputcsv($csv, $formattedUser, ';');
        fclose($csv);

        return $filename;
    }
}
