<?php

declare(strict_types=1);

namespace TRITUM\RepeatableFormElements\Event;

/**
 * This file is part of the "repeatable_form_elements" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

use TYPO3\CMS\Form\Domain\Model\FormElements\FormElementInterface;

/**
 * Use this event to manipulate variantOptions on copying form elements
 */
class CopyVariantEvent
{
    private $options;
    private FormElementInterface $originalFormElement;
    private FormElementInterface $newFormElement;
    private string $newIdentifier;

    public function __construct(
        array                $options,
        FormElementInterface $originalFormElement,
        FormElementInterface $newFormElement,
        string               $newIdentifier
    )
    {
        $this->options             = $options;
        $this->originalFormElement = $originalFormElement;
        $this->newFormElement      = $newFormElement;
        $this->newIdentifier       = $newIdentifier;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function setOptions(array $options): void
    {
        $this->options = $options;
    }

    public function getOriginalFormElement(): FormElementInterface
    {
        return $this->originalFormElement;
    }

    public function getNewFormElement(): FormElementInterface
    {
        return $this->newFormElement;
    }

    public function getNewIdentifier(): string
    {
        return $this->newIdentifier;
    }


}
