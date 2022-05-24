<?php
declare(strict_types = 1);
namespace TRITUM\RepeatableFormElements\Hooks;

/**
 * This file is part of the "repeatable_form_elements" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

use TRITUM\RepeatableFormElements\FormElements\RepeatableContainer;
use TRITUM\RepeatableFormElements\FormElements\RepeatableRow;
use TRITUM\RepeatableFormElements\Service\CopyService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Form\Domain\Model\FormElements\FormElementInterface;
use TYPO3\CMS\Form\Domain\Model\Renderable\CompositeRenderableInterface;
use TYPO3\CMS\Form\Domain\Model\Renderable\RenderableInterface;
use TYPO3\CMS\Form\Domain\Model\Renderable\RootRenderableInterface;
use TYPO3\CMS\Form\Domain\Runtime\FormRuntime;

class FormHooks
{
    /**
     * @param FormRuntime $formRuntime
     * @param CompositeRenderableInterface|null $currentPage
     * @param CompositeRenderableInterface|null $lastPage
     * @param array $rawRequestArguments
     * @return CompositeRenderableInterface|null
     */
    public function afterInitializeCurrentPage(
        FormRuntime $formRuntime,
        CompositeRenderableInterface $currentPage = null,
        CompositeRenderableInterface $lastPage = null,
        array $rawRequestArguments = []
    ): ?CompositeRenderableInterface {
        foreach ($formRuntime->getPages() as $page) {
            $this->wrapChildrenIntoRow($page, $formRuntime);
        }

        // first request
        if (!$lastPage) {
            foreach($formRuntime->getPages() as $page) {
                foreach($page->getElementsRecursively() as $formElement) {
                    $formElement->setRenderingOption('_originalIdentifier', $formElement->getIdentifier());
                    $formElement->setIdentifier($this->buildIdentifierForNestedFields($formElement));
                }
            }

            return $currentPage;
        }

        if ($this->userWentBackToPreviousStep($currentPage, $lastPage)) {
            GeneralUtility::makeInstance(CopyService::class, $formRuntime)->createCopiesFromFormState();
        } else {
            GeneralUtility::makeInstance(CopyService::class, $formRuntime)->createCopiesFromCurrentRequest();
        }

        foreach($formRuntime->getPages() as $page) {
            foreach($page->getElementsRecursively() as $formElement) {
                $formElement->setRenderingOption('_originalIdentifier', $formElement->getRenderingOptions()['_originalIdentifier'] ?? $formElement->getIdentifier());
                $formElement->setIdentifier($this->buildIdentifierForNestedFields($formElement));
            }
        }

        return $currentPage;
    }

    /**
     * @param FormRuntime $formRuntime
     * @param RootRenderableInterface $renderable
     */
    public function beforeRendering(FormRuntime $formRuntime, RootRenderableInterface $renderable): void
    {
        if ($renderable instanceof FormElementInterface) {
            $properties = $renderable->getProperties();

            $fluidAdditionalAttributes = $properties['fluidAdditionalAttributes'] ?? [];
            $fluidAdditionalAttributes['data-element-type'] = $renderable->getType();
            if ($renderable->getType() === 'DatePicker') {
                $fluidAdditionalAttributes['data-element-datepicker-enabled'] = (int)$renderable->getProperties()['enableDatePicker'];
                $fluidAdditionalAttributes['data-element-datepicker-date-format'] = $renderable->getProperties()['dateFormat'];
            }

            $renderable->setProperty('fluidAdditionalAttributes', $fluidAdditionalAttributes);
        }
    }

    protected function wrapChildrenIntoRow(RenderableInterface $renderable, FormRuntime $formRuntime)
    {
        if ($renderable instanceof RepeatableContainer) {
            $renderable->setRenderingOption('_originalIdentifier', $renderable->getIdentifier());
            /** @var RepeatableRow $row */
            $row = $renderable->createElement(CopyService::buildRowIdentifier($renderable), 'RepeatableRow');
            $row->setRenderingOption('_rowNumber', 0);

            /** @var FormElementInterface|RenderableInterface $childElement */
            foreach($renderable->getElements() as $childElement) {
                if ($childElement !== $row) {
                    $renderable->removeElement($childElement);
                    $row->addElement($childElement);
                    // Fix the missing parent renderables removed by `removeElement`
                    $this->updateParentRenderableRecursively($childElement, $formRuntime);
                }
            }

            foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/form']['afterBuildingFinished'] ?? [] as $className) {
                $hookObj = GeneralUtility::makeInstance($className);
                if (method_exists($hookObj, 'afterBuildingFinished')) {
                    $hookObj->afterBuildingFinished($row);
                }
            }
        } elseif ($renderable instanceof CompositeRenderableInterface) {
            foreach ($renderable->getElements() as $element) {
                $this->wrapChildrenIntoRow($element, $formRuntime);
            }
        }
    }

    protected function updateParentRenderableRecursively(RenderableInterface $renderable, FormRuntime $formRuntime) {
        if ($renderable instanceof CompositeRenderableInterface && method_exists($renderable, 'getElements')) {
            /** @var RenderableInterface $childElement */
            foreach ($renderable->getElements() as $childElement) {
                $formRuntime->getFormDefinition()->unregisterRenderable($childElement);
                $childElement->setParentRenderable($renderable);

                if ($childElement instanceof CompositeRenderableInterface) {
                    $this->updateParentRenderableRecursively($childElement, $formRuntime);
                }
            }
        }
    }

    protected function buildIdentifierForNestedFields(RenderableInterface $renderable)
    {
        $identifierParts = [$renderable->getRenderingOptions()['_rowNumber'] ?? $renderable->getRenderingOptions()['_originalIdentifier'] ?? $renderable->getIdentifier()];
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

    /**
     * returns TRUE if the user went back to any previous step in the form.
     *
     * @param CompositeRenderableInterface|null $currentPage
     * @param CompositeRenderableInterface|null $lastPage
     *
     * @return bool
     */
    protected function userWentBackToPreviousStep(
        CompositeRenderableInterface $currentPage = null,
        CompositeRenderableInterface $lastPage = null
    ): bool {
        return $currentPage !== null
                && $lastPage !== null
                && $currentPage->getIndex() < $lastPage->getIndex();
    }
}
