<?php

/**
 * This file is part of the "repeatable_form_elements" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

declare(strict_types=1);

namespace TRITUM\RepeatableFormElements\Finisher;

use DateTimeInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Domain\Model\FileReference;
use TYPO3\CMS\Form\Domain\Model\FormElements\FormElementInterface;

/**
 * This finisher is an extension to the default SaveToDatabaseFinisher. 
 * It is intentionally registered as a new identifier to keep compatibility with existing forms.
 */
class SaveToDatabaseFinisher extends \TYPO3\CMS\Form\Domain\Finishers\SaveToDatabaseFinisher
{
    protected function process(int $iterationCount)
    {
        $this->throwExceptionOnInconsistentConfiguration();

        $table = $this->parseOption('table');
        $table = is_string($table) ? $table : '';
        $elementsConfiguration = $this->parseOption('elements');
        $elementsConfiguration  = is_array($elementsConfiguration) ? $elementsConfiguration : [];
        $databaseColumnMappingsConfiguration = $this->parseOption('databaseColumnMappings');

        $this->databaseConnection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable($table);

        $databaseData = [];
        foreach ($databaseColumnMappingsConfiguration as $databaseColumnName => $databaseColumnConfiguration) {
            $value = $this->parseOption('databaseColumnMappings.' . $databaseColumnName . '.value');
            if (
                empty($value)
                && ($databaseColumnConfiguration['skipIfValueIsEmpty'] ?? false) === true
            ) {
                continue;
            }

            $databaseData[$databaseColumnName] = $value;
        }

        // decide which strategy to use
        $containerConfiguration = $this->parseOption('container');
        if (!empty($containerConfiguration) && is_string($containerConfiguration)) {
            $this->processContainer($containerConfiguration, $elementsConfiguration, $databaseData, $table, $iterationCount);
        } else {
            $databaseData = $this->prepareData($elementsConfiguration, $databaseData);

            $this->saveToDatabase($databaseData, $table, $iterationCount);
        }

    }

    /**
     * This action will do mostly the same processing as the default processing but we need to set prefix for the finisher to find the correct element
     * @param string $containerPath the identifier of the container to process, can be for example `RootContainer` or `RootContainer.0.NestedContainer`
     * @param array $elementsConfiguration finisher-element-configuration
     * @param array $databaseData prepared data
     * @param string $table Tablename to save data to
     * @param int $iterationCount finisher iteration
     * @return void
     */
    protected function processContainer(string $containerPath, array $elementsConfiguration, array $databaseData, string $table, int $iterationCount)
    {
        $containerValues = ArrayUtility::getValueByPath($this->getFormValues(), $containerPath, '.');
        foreach ($containerValues as $copyId => $containerItem) {
            $prefix = $containerPath . '.' . $copyId . '.';
            // store data inside new array to keep prepared $databaseData for all iterations
            $itemDatabaseData = $this->prepareData($elementsConfiguration, $databaseData, $containerItem, $prefix);

            $this->saveToDatabase($itemDatabaseData, $table, $iterationCount, $copyId);
        }
    }

    /**
     * Adapted method for container data.
     * @param array $elementsConfiguration finisher element configuration
     * @param array $databaseData prepared data
     * @param array $values optional filled Array with form values to use
     * @param string $prefix prefix to get the form element object by a full identifier
     * @return array the filled database data
     */
    protected function prepareData(array $elementsConfiguration, array $databaseData, array $values = [], string $prefix = ''): array
    {
        if (empty($values)) {
            $values = $this->getFormValues();
        }

        foreach ($values as $elementIdentifier => $elementValue) {
            if (!$this->canValueBeHandled($elementValue, $elementsConfiguration, $elementIdentifier, $prefix)) {
                continue;
            }

            if ($elementValue instanceof FileReference) {
                if (isset($elementsConfiguration[$elementIdentifier]['saveFileIdentifierInsteadOfUid'])) {
                    $saveFileIdentifierInsteadOfUid = (bool)$elementsConfiguration[$elementIdentifier]['saveFileIdentifierInsteadOfUid'];
                } else {
                    $saveFileIdentifierInsteadOfUid = false;
                }

                if ($saveFileIdentifierInsteadOfUid) {
                    $elementValue = $elementValue->getOriginalResource()->getCombinedIdentifier();
                } else {
                    $elementValue = $elementValue->getOriginalResource()->getProperty('uid_local');
                }
            } elseif (is_array($elementValue)) {
                $elementValue = implode(',', $elementValue);
            } elseif ($elementValue instanceof DateTimeInterface) {
                $format = $elementsConfiguration[$elementIdentifier]['dateFormat'] ?? 'U';
                $elementValue = $elementValue->format($format);
            }

            $databaseData[$elementsConfiguration[$elementIdentifier]['mapOnDatabaseColumn']] = $elementValue;
        }

        return $databaseData;
    }

    /**
     * Save or insert the values from
     * $databaseData into the table $table
     * and provide some finisher variables
     */
    protected function saveToDatabase(array $databaseData, string $table, int $iterationCount, ?int $containerItemKey = null)
    {
        if (!empty($databaseData)) {
            if ($this->parseOption('mode') === 'update') {
                $whereClause = $this->parseOption('whereClause');
                foreach ($whereClause as $columnName => $columnValue) {
                    $whereClause[$columnName] = $this->parseOption('whereClause.' . $columnName);
                }
                $this->databaseConnection->update(
                    $table,
                    $databaseData,
                    $whereClause
                );
            } else {
                $this->databaseConnection->insert($table, $databaseData);
                $insertedUid = (int)$this->databaseConnection->lastInsertId($table);
                $this->finisherContext->getFinisherVariableProvider()->add(
                    $this->shortFinisherIdentifier,
                    'insertedUids.' . $iterationCount . (is_int($containerItemKey) ? '.' . $containerItemKey : ''),
                    $insertedUid
                );

                $currentCount = $this->finisherContext->getFinisherVariableProvider()->get(
                    $this->shortFinisherIdentifier,
                    'countInserts.', $iterationCount,
                    0
                );
                $this->finisherContext->getFinisherVariableProvider()->addOrUpdate(
                    $this->shortFinisherIdentifier,
                    'countInserts.' . $iterationCount,
                    $currentCount++
                );
            }
        }
    }

    /**
     * This will check if a element shall or can be handled
     * @param mixed $elementValue
     * @param array $elementsConfiguration
     * @param string $elementIdentifier
     * @param string $prefix
     * @return array
     */
    private function canValueBeHandled(mixed $elementValue, array $elementsConfiguration, string $elementIdentifier, string $prefix): bool
    {
        $elementConfig = $elementsConfiguration[$elementIdentifier];
        if (!isset($elementConfig)) {
            return false;
        }

        if (
            ($elementValue === null || $elementValue === '')
            && isset($elementConfig['skipIfValueIsEmpty'])
            && $elementConfig['skipIfValueIsEmpty'] === true
        ) {
            return false;
        }

        $element = $this->getElementByIdentifier($prefix . $elementIdentifier);
        if (!($element instanceof FormElementInterface) || !isset($elementConfig['mapOnDatabaseColumn'])) {
            return false;
        }

        return true;
    }
}
