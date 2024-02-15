<?php

declare(strict_types=1);

namespace TRITUM\RepeatableFormElements\Service;

/**
 * This file is part of the "repeatable_form_elements" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

use TRITUM\RepeatableFormElements\FormElements\RepeatableContainer;
use TRITUM\RepeatableFormElements\FormElements\RepeatableRow;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Error\Error;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Property\PropertyMappingConfiguration;
use TYPO3\CMS\Extbase\Validation\Validator\ValidatorInterface;
use TYPO3\CMS\Form\Domain\Model\FormDefinition;
use TYPO3\CMS\Form\Domain\Model\FormElements\FormElementInterface;
use TYPO3\CMS\Form\Domain\Model\Renderable\AbstractRenderable;
use TYPO3\CMS\Form\Domain\Model\Renderable\CompositeRenderableInterface;
use TYPO3\CMS\Form\Domain\Model\Renderable\RenderableInterface;
use TYPO3\CMS\Form\Domain\Runtime\FormRuntime;
use TYPO3\CMS\Form\Domain\Runtime\FormState;
use TYPO3\CMS\Form\Mvc\ProcessingRule;
use TYPO3\CMS\Form\Service\TranslationService;

class CopyService
{
    /**
     * @var FormRuntime
     */
    protected $formRuntime;

    /**
     * @var FormState
     */
    protected $formState;

    /**
     * @var FormDefinition
     */
    protected $formDefinition;

    /**
     * @var array
     */
    protected $repeatableContainersByOriginalIdentifier = [];

    /**
     * @var array
     */
    protected $typeDefinitions = [];

    protected static array $rowMap = [];

    /**
     * @param FormRuntime $formRuntime
     */
    public function __construct(FormRuntime $formRuntime)
    {
        $this->formRuntime = $formRuntime;
        $this->formState = $formRuntime->getFormState();
        $this->formDefinition = $formRuntime->getFormDefinition();
        $this->typeDefinitions = $this->formDefinition->getTypeDefinitions();
    }

    /**
     * @return CopyService
     * @api
     */
    public function createCopiesFromCurrentRequest(): CopyService
    {
        $requestArguments = $this->formRuntime->getRequest()->getArguments();
        $this->removeDeletedRepeatableContainersFromFormValuesByRequest($requestArguments);
        $requestArguments = array_replace_recursive(
            $this->formState->getFormValues(),
            $requestArguments
        );

        $this->copyRepeatableContainersFromArguments($requestArguments);

        return $this;
    }

    /**
     * @return CopyService
     * @api
     */
    public function createCopiesFromFormState(): CopyService
    {
        $this->copyRepeatableContainersFromArguments($this->formState->getFormValues());

        return $this;
    }

    /**
     * @param string $originalFormElement
     * @param string $newElementCopy
     * @return ProcessingRule[]
     * @internal
     */
    public function copyProcessingRule(
        string $originalFormElement,
        string $newElementCopy
    ): array {
        $typo3Version = new Typo3Version();
        $originalProcessingRule = $this->formRuntime->getFormDefinition()->getProcessingRule($originalFormElement);

        if ($typo3Version->getVersion() >= 11) {
            GeneralUtility::addInstance(PropertyMappingConfiguration::class, $originalProcessingRule->getPropertyMappingConfiguration());
            $newProcessingRule = $this->formRuntime->getFormDefinition()->getProcessingRule($newElementCopy);
        } else {
            $newProcessingRule = $this->formRuntime->getFormDefinition()->getProcessingRule($newElementCopy);
            $newProcessingRule->injectPropertyMappingConfiguration($originalProcessingRule->getPropertyMappingConfiguration());
        }

        try {
            $newProcessingRule->setDataType($originalProcessingRule->getDataType());
        } catch (\TypeError $error) {
        }

        return [$originalProcessingRule, $newProcessingRule];
    }

    /**
     * @param array $requestArguments
     * @param array $argumentPath
     */
    protected function copyRepeatableContainersFromArguments(
        array $requestArguments,
        array $argumentPath = []
    ): void {
        foreach ($requestArguments as $argumentKey => $argumentValue) {
            if (is_array($argumentValue)) {
                $repeatableContainer = $this->getRepeatableContainerByOriginalIdentifier((string)$argumentKey);
                $copyIndexes = array_keys($argumentValue);
                $originalIndex = $copyIndexes[0];
                unset($copyIndexes[0]);
                $argumentPath[] = $argumentKey;

                if (
                    $repeatableContainer
                    && count(array_filter(array_keys($copyIndexes), 'is_string')) === 0
                ) {
                    /** @var RepeatableRow $originalRow */
                    $originalRow = $repeatableContainer->getElements()[0];
                    $originalRow->setRenderingOption('_rowNumber', $originalIndex);
                    $originalRow->setIdentifier($repeatableContainer->getIdentifier() . '.' . $originalIndex);
                    $copyIndexes = ArrayUtility::sortArrayWithIntegerKeys($copyIndexes);
                    $minimumCopies = (int)$repeatableContainer->getProperties()['minimumCopies'];
                    $maximumCopies = (int)$repeatableContainer->getProperties()['maximumCopies'];

                    $moveAfter = $originalRow;
                    foreach ($copyIndexes as $copyIndex) {
                        $contextPath = $argumentPath;
                        $contextPath[] = $copyIndex;

                        $copiedRow = $this->copyRow($originalRow, $moveAfter, $contextPath);
                        $moveAfter = $copiedRow;
                    }

                    $totalRows = count($repeatableContainer->getElements());
                    if ($totalRows > $maximumCopies) {
                        $this->addError($repeatableContainer, 1518701681, sprintf('The maximum number of rows of %d has been reached. You have %d.', $maximumCopies, count($repeatableContainer->getElements())), ['maximum' => $maximumCopies, 'total' => $totalRows]);
                    }

                    if ($totalRows < $minimumCopies) {
                        $this->addError($repeatableContainer, 1518701682, sprintf('The minimum number of rows of %d has not yet been reached. You have %d.', $minimumCopies, $totalRows), ['minimum' => $minimumCopies, 'total' => $totalRows]);
                    }
                }

                $this->copyRepeatableContainersFromArguments($argumentValue, $argumentPath);
                array_pop($argumentPath);
            }
        }
    }

    /**
     * @param RepeatableRow $copyFromRow
     * @param RepeatableRow $moveAfterRow
     * @param array         $contextPath
     *
     * @return FormElementInterface
     */
    protected function copyRow(RepeatableRow $copyFromRow, RepeatableRow $moveAfterRow, array $contextPath): FormElementInterface
    {
        /** @var CompositeRenderableInterface $container */
        $container = $copyFromRow->getParentRenderable();
        /** @var AbstractRenderable|FormElementInterface $newRow */
        $newRow = $container->createElement(self::buildRowIdentifier($copyFromRow->getParentRenderable()), $copyFromRow->getType());
        $this->copyOptions($newRow, $copyFromRow);
        $newRow->getParentRenderable()->moveElementAfter($newRow, $moveAfterRow);
        $newRow->setRenderingOption('_rowNumber', $contextPath[count($contextPath) - 1]);
        $newRow->setRenderingOption('_originalIdentifier', $copyFromRow->getIdentifier());

        foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/form']['afterBuildingFinished'] ?? [] as $className) {
            $hookObj = GeneralUtility::makeInstance($className);
            if (method_exists($hookObj, 'afterBuildingFinished')) {
                $hookObj->afterBuildingFinished($newRow);
            }
        }

        foreach ($copyFromRow->getElements() as $childToCopy) {
            /** @var CompositeRenderableInterface $newRow */
            /** @var AbstractRenderable $childToCopy */
            $this->createNestedElements($newRow, $childToCopy);
        }

        return $newRow;
    }

    /**
     * @param RenderableInterface $newElementCopy
     * @param RenderableInterface $originalFormElement
     */
    protected function copyOptions(
        RenderableInterface $newElementCopy,
        RenderableInterface $originalFormElement
    ): void {
        $newElementCopy->setLabel($originalFormElement->getLabel());
        $newElementCopy->setDefaultValue($originalFormElement->getDefaultValue());
        foreach ($originalFormElement->getProperties() as $key => $value) {
            $newElementCopy->setProperty($key, $value);
        }
        foreach ($originalFormElement->getRenderingOptions() as $key => $value) {
            if (
                $key === '_isRootRepeatableContainer'
                || $key === '_originalIdentifier'
                || $key === '_isReferenceContainer'
            ) {
                continue;
            }
            $newElementCopy->setRenderingOption($key, $value);
        }

        [$originalProcessingRule] = $this->copyProcessingRule($originalFormElement->getIdentifier(), $newElementCopy->getIdentifier());

        /** @var ValidatorInterface $validator */
        foreach ($originalProcessingRule->getValidators() as $validator) {
            $newElementCopy->addValidator($validator);
        }
    }

    /**
     * @param CompositeRenderableInterface $parentFormElementCopy
     * @param AbstractRenderable           $originalFormElement
     */
    protected function createNestedElements(CompositeRenderableInterface $parentFormElementCopy, AbstractRenderable $originalFormElement): void
    {
        $newFormElement = $parentFormElementCopy->createElement(bin2hex(random_bytes(12)), $originalFormElement->getType());
        $newFormElement->setIdentifier(self::buildIdentifierForNestedFields($newFormElement, $originalFormElement->getIdentifier()));
        $this->copyOptions($newFormElement, $originalFormElement);
        $this->copyProcessingRule($originalFormElement->getIdentifier(), $newFormElement->getIdentifier());
        $newFormElement->setRenderingOption('_originalIdentifier', $originalFormElement->getIdentifier());

        foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/form']['afterBuildingFinished'] ?? [] as $className) {
            $hookObj = GeneralUtility::makeInstance($className);
            if (method_exists($hookObj, 'afterBuildingFinished')) {
                $hookObj->afterBuildingFinished($newFormElement);
            }
        }

        if ($originalFormElement instanceof CompositeRenderableInterface) {
            foreach ($originalFormElement->getElements() as $originalChildFormElement) {
                $this->createNestedElements($newFormElement, $originalChildFormElement);
            }
        }
    }

    public static function buildIdentifierForNestedFields(RenderableInterface $renderable, string $overrideCurrentIdentifier = null)
    {
        $identifierParts = [$overrideCurrentIdentifier ?? $renderable->getIdentifier()];
        $currentRenderable = $renderable;

        do {
            $currentRenderable = $currentRenderable->getParentRenderable();
            if ($currentRenderable instanceof RepeatableRow) {
                array_unshift($identifierParts, $currentRenderable->getRenderingOptions()['_rowNumber']);
            } elseif ($currentRenderable instanceof RepeatableContainer) {
                array_unshift($identifierParts, $currentRenderable->getIdentifier());
            }
        } while ($currentRenderable->getParentRenderable() !== null);

        return implode('.', $identifierParts);
    }

    public static function buildRowIdentifier(RenderableInterface $repeatableContainer)
    {
        $base = 'repeatablerow';
        $counter = 0;

        do {
            $identifier = $base . '-' . $counter;
            if (!array_key_exists($identifier, self::$rowMap[$repeatableContainer->getRootForm()->getIdentifier()] ?? [])) {
                self::$rowMap[$repeatableContainer->getRootForm()->getIdentifier()][$identifier] = true;

                return $identifier;
            }
            $counter++;
        } while (true);
    }

    /**
     * @param string $originalIdentifier
     *
     * @return RepeatableContainer|null
     */
    protected function getRepeatableContainerByOriginalIdentifier(string $originalIdentifier): ?RepeatableContainer
    {
        if (
            !isset($this->repeatableContainersByOriginalIdentifier[$originalIdentifier])
            || $this->repeatableContainersByOriginalIdentifier[$originalIdentifier] === null
        ) {
            foreach ($this->formDefinition->getRenderablesRecursively() as $formElement) {
                $renderingOptions = $formElement->getRenderingOptions();
                if (
                    $formElement instanceof RepeatableContainerInterface
                    && ($renderingOptions['_originalIdentifier'] ?? null) === $originalIdentifier
                    && (bool)$renderingOptions['_isRootRepeatableContainer'] === true
                ) {
                    $this->repeatableContainersByOriginalIdentifier[$originalIdentifier] = $formElement;
                }
            }
            if (!isset($this->repeatableContainersByOriginalIdentifier[$originalIdentifier])) {
                $this->repeatableContainersByOriginalIdentifier[$originalIdentifier] = null;
            }
        }

        return $this->repeatableContainersByOriginalIdentifier[$originalIdentifier];
    }

    /**
     * @param FormElementInterface $originalFormElement
     * @param int                  $timestamp
     * @param string               $defaultMessage
     */
    protected function addError(
        FormElementInterface $formElement,
        int $timestamp,
        string $defaultMessage = '',
        array $messageArguments = []
    ): void {
        $error = $this->getObjectManager()->get(
            Error::class,
            TranslationService::getInstance()->translateFormElementError(
                $formElement,
                $timestamp,
                $messageArguments,
                $defaultMessage,
                $this->formRuntime
            ),
            $timestamp
        );
        $this->formDefinition
            ->getProcessingRule($formElement->getIdentifier())
            ->getProcessingMessages()
            ->addError($error);
    }

    /**
     * @param array $requestArguments
     * @param array $argumentPath
     */
    protected function removeDeletedRepeatableContainersFromFormValuesByRequest(
        array $requestArguments,
        array $argumentPath = []
    ): void {
        foreach ($requestArguments as $argumentKey => $argumentValue) {
            if (is_array($argumentValue)) {
                $originalContainer = $this->getRepeatableContainerByOriginalIdentifier((string)$argumentKey);
                $argumentPath[] = $argumentKey;
                $copyIndexes = array_keys($argumentValue);

                if (
                    $originalContainer
                    && count(array_filter(array_keys($copyIndexes), 'is_string')) === 0
                ) {
                    $currentArgumentPath = implode('.', $argumentPath);
                    $formValue = $this->formState->getFormValue($currentArgumentPath);
                    if ($formValue !== null) {
                        foreach ($formValue as $key => $_) {
                            if (!in_array($key, $copyIndexes, true)) {
                                unset($formValue[$key]);
                            }
                        }
                        $this->formState->setFormValue($currentArgumentPath, $formValue);
                    }
                }

                $this->removeDeletedRepeatableContainersFromFormValuesByRequest($argumentValue, $argumentPath);
                array_pop($argumentPath);
            }
        }
    }

    /**
     * @return ObjectManager
     */
    protected function getObjectManager(): ObjectManager
    {
        return GeneralUtility::makeInstance(ObjectManager::class);
    }
}
