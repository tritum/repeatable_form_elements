<?php
declare(strict_types=1);
namespace TRITUM\RepeatableFormElements\Hooks;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use TRITUM\RepeatableFormElements\FormElements\RepeatableContainerInterface;
use TRITUM\RepeatableFormElements\Service\CopyService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Form\Domain\Model\FormElements\AbstractFormElement;
use TYPO3\CMS\Form\Domain\Model\FormElements\FormElementInterface;
use TYPO3\CMS\Form\Domain\Model\Renderable\CompositeRenderableInterface;
use TYPO3\CMS\Form\Domain\Model\Renderable\RenderableInterface;
use TYPO3\CMS\Form\Domain\Model\Renderable\RootRenderableInterface;
use TYPO3\CMS\Form\Domain\Runtime\FormRuntime;

/*
 * @author Ralf Zimmermann TRITUM GmbH <ralf.zimmermann@tritum.de>
 */
class FormHooks
{
    /**
     * @param FormRuntime $formRuntime
     * @param null|CompositeRenderableInterface $currentPage
     * @param null|CompositeRenderableInterface $lastPage
     * @param array $rawRequestArguments
     * @return null|CompositeRenderableInterface
     */
    public function afterInitializeCurrentPage(
        FormRuntime $formRuntime,
        CompositeRenderableInterface $currentPage = null,
        CompositeRenderableInterface $lastPage = null,
        array $rawRequestArguments = []
    ) {
        foreach ($formRuntime->getPages() as $page) {
            $this->setRootRepeatableContainerIdentifiers($page, $formRuntime);
        }

        // first request
        if (!$lastPage) {
            return $currentPage;
        }

        if ($this->userWentBackToPreviousStep($formRuntime, $currentPage, $lastPage)) {
            $this->getObjectManager()->get(CopyService::class, $formRuntime)->createCopiesFromFormState();
        } else {
            $this->getObjectManager()->get(CopyService::class, $formRuntime)->createCopiesFromCurrentRequest();
        }

        return $currentPage;
    }

    /**
     * @param FormRuntime $formRuntime
     * @param RootRenderableInterface $renderable
     * @return void
     */
    public function beforeRendering(FormRuntime $formRuntime, RootRenderableInterface $renderable)
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

    /**
     * @param RenderableInterface $formElement
     * @param FormRuntime $formRuntime
     * @param array $repeatableContainerIdentifiers
     */
    protected function setRootRepeatableContainerIdentifiers(
        RenderableInterface $renderable,
        FormRuntime $formRuntime,
        array $repeatableContainerIdentifiers = []
    ) {
        $isRepeatableContainer = $renderable instanceof RepeatableContainerInterface ? true : false;

        $hasOriginalIdentifier = isset($renderable->getRenderingOptions()['_originalIdentifier']);
        if ($isRepeatableContainer) {
            $repeatableContainerIdentifiers[] = $renderable->getIdentifier();
            if (!$hasOriginalIdentifier) {
                $renderable->setRenderingOption('_isRootRepeatableContainer', true);
                $renderable->setRenderingOption('_isReferenceContainer', true);
            }
        }

        if (!empty($repeatableContainerIdentifiers) && !$hasOriginalIdentifier) {
            $newIdentifier = implode('.0.', $repeatableContainerIdentifiers) . '.0';
            if (!$isRepeatableContainer) {
                $newIdentifier .= '.' . $renderable->getIdentifier();
            }
            $originalIdentifier = $renderable->getIdentifier();
            $renderable->setRenderingOption('_originalIdentifier', $originalIdentifier);

            if($renderable instanceof AbstractFormElement && $renderable->getDefaultValue()) {
                $formRuntime->getFormDefinition()->addElementDefaultValue($newIdentifier, $renderable->getDefaultValue());
            }

            $formRuntime->getFormDefinition()->unregisterRenderable($renderable);
            $renderable->setIdentifier($newIdentifier);
            $formRuntime->getFormDefinition()->registerRenderable($renderable);
            $validators = $formRuntime->getFormDefinition()->getProcessingRule($originalIdentifier)->getValidators();
            foreach ($validators as $validator) {
                $renderable->addValidator($validator);
            }
        }

        if ($renderable instanceof CompositeRenderableInterface) {
            foreach ($renderable->getElements() as $childRenderable) {
                $this->setRootRepeatableContainerIdentifiers($childRenderable, $formRuntime, $repeatableContainerIdentifiers);
            }
        }
    }

    /**
     * returns TRUE if the user went back to any previous step in the form.
     *
     * @param FormRuntime $formRuntime
     * @param CompositeRenderableInterface $currentPage
     * @param CompositeRenderableInterface $lastPage
     * @return bool
     */
    protected function userWentBackToPreviousStep(
        FormRuntime $formRuntime,
        CompositeRenderableInterface $currentPage = null,
        CompositeRenderableInterface $lastPage = null
    ): bool {
        return $currentPage !== null
                && $lastPage !== null
                && $currentPage->getIndex() < $lastPage->getIndex();
    }

    /**
     * @return ObjectManager
     */
    protected function getObjectManager(): ObjectManager
    {
        return GeneralUtility::makeInstance(ObjectManager::class);
    }
}
