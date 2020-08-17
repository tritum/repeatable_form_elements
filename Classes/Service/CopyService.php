<?php
declare(strict_types = 1);
namespace TRITUM\RepeatableFormElements\Service;

/**
 * This file is part of the "repeatable_form_elements" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */
use TRITUM\RepeatableFormElements\FormElements\RepeatableContainerInterface;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Error\Error;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Form\Domain\Model\FormDefinition;
use TYPO3\CMS\Form\Domain\Model\FormElements\FormElementInterface;
use TYPO3\CMS\Form\Domain\Model\Renderable\CompositeRenderableInterface;
use TYPO3\CMS\Form\Domain\Runtime\FormRuntime;
use TYPO3\CMS\Form\Domain\Runtime\FormState;
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
     * @param array $requestArguments
     * @param array $argumentPath
     */
    protected function copyRepeatableContainersFromArguments(
        array $requestArguments,
        array $argumentPath = []
    ): void {
        foreach ($requestArguments as $argumentKey => $argumentValue) {
            if (is_array($argumentValue)) {
                $originalContainer = $this->getRepeatableContainerByOriginalIdentifier((string)$argumentKey);
                $copyIndexes = array_keys($argumentValue);
                unset($copyIndexes[0]);
                $argumentPath[] = $argumentKey;

                if (
                    $originalContainer
                    && count(array_filter(array_keys($copyIndexes), 'is_string')) === 0
                ) {
                    $copyIndexes = ArrayUtility::sortArrayWithIntegerKeys($copyIndexes);

                    if (count($argumentPath) <= 1) {
                        $referenceContainer = $originalContainer;
                    } else {
                        $referenceContainerPath = $argumentPath;
                        $referenceContainerPath[] = 0;
                        $referenceContainerIdentifier = implode('.', $referenceContainerPath);
                        $referenceContainer = $this->formDefinition->getElementByIdentifier($referenceContainerIdentifier);
                    }

                    $firstReferenceContainer = $referenceContainer;
                    $firstReferenceContainer->setRenderingOption('_isReferenceContainer', true);
                    $firstReferenceContainer->setRenderingOption('_copyMother', $originalContainer->getIdentifier());

                    $minimumCopies = (int)$firstReferenceContainer->getProperties()['minimumCopies'];
                    $maximumCopies = (int)$firstReferenceContainer->getProperties()['maximumCopies'];

                    $copyNumber = 1;
                    foreach ($copyIndexes as $copyIndex) {
                        $contextPath = $argumentPath;
                        $contextPath[] = $copyIndex;
                        $newIdentifier = implode('.', $contextPath);

                        $referenceContainer = $this->copyRepeatableContainer($originalContainer, $referenceContainer, $newIdentifier);
                        $referenceContainer->setRenderingOption('_copyReference', $firstReferenceContainer->getIdentifier());

                        if ($copyNumber > $maximumCopies) {
                            $this->addError($referenceContainer, 1518701681, 'The maximum number of copies has been reached');
                        }
                        $copyNumber++;
                    }

                    if ($copyNumber - 1 < $minimumCopies) {
                        $this->addError($firstReferenceContainer, 1518701682, 'The minimum number of copies has not yet been reached');
                    }
                }

                $this->copyRepeatableContainersFromArguments($argumentValue, $argumentPath);
                array_pop($argumentPath);
            }
        }
    }

    /**
     * @param RepeatableContainerInterface $originalContainer
     * @param RepeatableContainerInterface $moveAfterContainer
     * @param string $newIdentifier
     * @return RepeatableContainerInterface
     */
    protected function copyRepeatableContainer(
        RepeatableContainerInterface $copyFromContainer,
        RepeatableContainerInterface $moveAfterContainer,
        string $newIdentifier
    ): RepeatableContainerInterface {
        $typeName = $copyFromContainer->getType();
        $implementationClassName = $this->typeDefinitions[$typeName]['implementationClassName'];
        $parentRenderableForNewContainer = $moveAfterContainer->getParentRenderable();

        $newContainer = $this->getObjectManager()->get($implementationClassName, $newIdentifier, $typeName);
        $this->copyOptions($newContainer, $copyFromContainer);

        $parentRenderableForNewContainer->addElement($newContainer);
        $parentRenderableForNewContainer->moveElementAfter($newContainer, $moveAfterContainer);

        foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/form']['afterBuildingFinished'] ?? [] as $className) {
            $hookObj = GeneralUtility::makeInstance($className);
            if (method_exists($hookObj, 'afterBuildingFinished')) {
                $hookObj->afterBuildingFinished($newContainer);
            }
        }

        foreach ($copyFromContainer->getElements() as $originalFormElement) {
            $this->createNestedElements($originalFormElement, $newContainer, $copyFromContainer->getIdentifier(), $newIdentifier);
        }

        return $newContainer;
    }

    /**
     * @param FormElementInterface $newElementCopy
     * @param FormElementInterface $originalFormElement
     */
    protected function copyOptions(
        FormElementInterface $newElementCopy,
        FormElementInterface $originalFormElement
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

        $originalProcessingRule = $this->formRuntime->getFormDefinition()->getProcessingRule($originalFormElement->getIdentifier());
        $newProcessingRule = $this->formRuntime->getFormDefinition()->getProcessingRule($newElementCopy->getIdentifier());

        $newProcessingRule->injectPropertyMappingConfiguration($originalProcessingRule->getPropertyMappingConfiguration());
        try {
            $newProcessingRule->setDataType($originalProcessingRule->getDataType());
        } catch (\TypeError $error) {
        }

        foreach ($originalProcessingRule->getValidators() as $validator) {
            $newElementCopy->addValidator($validator);
        }
    }

    /**
     * @param FormElementInterface $originalFormElement
     * @param CompositeRenderableInterface $parentFormElement
     * @param string $identifierOriginal
     * @param string $identifierReplacement
     */
    protected function createNestedElements(
        FormElementInterface $originalFormElement,
        CompositeRenderableInterface $parentFormElementCopy,
        string $identifierOriginal,
        string $identifierReplacement
    ): void {
        $newIdentifier = str_replace($identifierOriginal, $identifierReplacement, $originalFormElement->getIdentifier());
        $newFormElement = $parentFormElementCopy->createElement(
            $newIdentifier,
            $originalFormElement->getType()
        );
        $this->copyOptions($newFormElement, $originalFormElement);

        foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/form']['afterBuildingFinished'] ?? [] as $className) {
            $hookObj = GeneralUtility::makeInstance($className);
            if (method_exists($hookObj, 'afterBuildingFinished')) {
                $hookObj->afterBuildingFinished($newFormElement);
            }
        }

        if ($originalFormElement instanceof CompositeRenderableInterface) {
            foreach ($originalFormElement->getElements() as $originalChildFormElement) {
                $this->createNestedElements($originalChildFormElement, $newFormElement, $identifierOriginal, $identifierReplacement);
            }
        }
    }

    /**
     * @param string $originalIdentifier
     * @return RepeatableContainerInterface|null
     */
    protected function getRepeatableContainerByOriginalIdentifier(string $originalIdentifier): ?RepeatableContainerInterface
    {
        if (
            !isset($this->repeatableContainersByOriginalIdentifier[$originalIdentifier])
            || $this->repeatableContainersByOriginalIdentifier[$originalIdentifier] === null
        ) {
            foreach ($this->formDefinition->getRenderablesRecursively() as $formElement) {
                $renderingOptions = $formElement->getRenderingOptions();
                if (
                    $formElement instanceof RepeatableContainerInterface
                    && $renderingOptions['_originalIdentifier'] === $originalIdentifier
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
     * @param int $timestamp
     * @param string $defaultMessage
     */
    protected function addError(
        FormElementInterface $formElement,
        int $timestamp,
        string $defaultMessage = ''
    ): void {
        $error = $this->getObjectManager()->get(
            Error::class,
            TranslationService::getInstance()->translateFormElementError(
                $formElement,
                $timestamp,
                [],
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
                            if (!in_array($key, $copyIndexes)) {
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
