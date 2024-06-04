$(function() {
    var containerClones = {};

    $('[data-repeatable-container][data-is-root]').each(function(e) {
        var $element = $(this),
            $containerClone = $element.clone(),
            containerIdentifier = $element.attr('data-identifier'),
            formIdentifier = $element.closest('form').attr('id');

        containerClones[formIdentifier] = containerClones[formIdentifier] || {};

        $('[data-repeatable-container][data-is-copy]', $containerClone).remove();
        $('[data-repeatable-container][data-copy-button]', $containerClone).first().remove();
        $('[role="alert"]', $containerClone).remove();

        $('[data-repeatable-container][data-is-root]', $containerClone).each(function(e) {
            $(this).removeAttr('data-is-root').attr('data-is-copy', '').attr('data-copy-mother', $(this).attr('data-identifier'));
        });
        $containerClone.removeAttr('data-is-root').attr('data-is-copy', '');
        $containerClone.find('*').removeClass('has-error');

        $containerClone
            .find('input')
                .filter(':not(:checkbox, :radio, [type="hidden"])').attr('value', '').end()
                .filter(':file').siblings().remove().end()
                .filter(':checkbox, :radio').removeAttr('checked').end().end()
            .find('textarea').attr('value', '').end()
            .find('select').prop("selectedIndex", -1).find('option:selected').removeAttr('selected');

        containerClones[formIdentifier][containerIdentifier] = $containerClone;
    });

    $(document).trigger('initialize-repeatable-container-copy-buttons', [containerClones]);
    $(document).trigger('initialize-repeatable-container-remove-buttons');
});

$(document).on('initialize-repeatable-container-copy-buttons', function(event, containerClones) {
    var getNextCopyNumber = function($referenceElement, $form) {
            var highestCopyNumber = 0;

            $('[data-repeatable-container][data-copy-reference="' + $referenceElement.attr('data-identifier') + '"]', $form).each(function(e) {
                var copyNumber = parseInt($(this).attr('data-identifier').split('.').pop());

                if (copyNumber > highestCopyNumber) {
                    highestCopyNumber = copyNumber;
                }
            });

            return ++highestCopyNumber;
        },
        setRandomIds = function($subject) {
            var idReplacements = {};

            $('[id]', $subject).each(function(e) {
                var $element = $(this),
                    id = $element.attr('id'),
                    newId = Math.floor(Math.random() * 99999999) + Date.now();

                $element.attr('id', newId);
                idReplacements[id] = newId;
            });

            $subject.find('*').each(function(e) {
                for (var i = 0, len = this.attributes.length; i < len; i++) {
                    var attributeValue = this.attributes[i].nodeValue;

                    if (attributeValue in idReplacements) {
                        this.attributes[i].nodeValue = idReplacements[attributeValue];
                    }
                }
            });
        },
        escapeRegExp = function(subject) {
            return subject.replace(/[-\/\\^$*+?.()|[\]{}]/g, '\\$&');
        };

    $('[data-repeatable-container][data-copy-button]').each(function(e) {
        var $element = $(this),
            referenceElementIdentifier = $element.attr('data-copy-button-for'),
            $referenceElement = $('[data-repeatable-container][data-identifier="' + referenceElementIdentifier + '"]', $form),
            $form = $element.closest('form'),
            $elementCopies = $('[data-repeatable-container][data-copy-reference="' + referenceElementIdentifier + '"]', $form),
            maxCopies = $referenceElement.attr('data-max-copies');

        if ($elementCopies.length >= maxCopies) {
            $element.attr('disabled', 'disabled');
        } else {
            $element.attr('disabled', null);
            $element.off().on('click', function(e) {
                e.preventDefault();

                var $element = $(this),
                    referenceElementIdentifier = $element.attr('data-copy-button-for'),
                    $form = $element.closest('form'),
                    formIdentifier = $form.attr('id'),
                    $referenceElement = $('[data-repeatable-container][data-identifier="' + referenceElementIdentifier + '"]', $form),
                    referenceElementIsRoot = $referenceElement.is('[data-is-root]'),
                    $elementCopies = $('[data-repeatable-container][data-copy-reference="' + referenceElementIdentifier + '"]', $form),
                    containerCloneIdentifier = referenceElementIsRoot ? referenceElementIdentifier : $referenceElement.attr('data-copy-mother'),
                    copyMotherIdentifier = containerClones[formIdentifier][containerCloneIdentifier].attr('data-identifier'),
                    newIdentifierParts = $referenceElement.attr('data-identifier').split('.'),
                    oldIdentifierNameRegex = escapeRegExp('[' + copyMotherIdentifier.split('.').join('][') + ']'),
                    copyMotherIdentifierRegex = '(' + escapeRegExp('data-copy-mother="') + ')?' + escapeRegExp(copyMotherIdentifier),
                    newIdentifier = undefined,
                    newIdentifierNameRegex = undefined,
                    $containerClone = undefined,
                    containerCloneHtml = undefined;

                newIdentifierParts.pop();
                newIdentifierParts.push(getNextCopyNumber($referenceElement, $form));
                newIdentifier = newIdentifierParts.join('.');

                containerCloneHtml = containerClones[formIdentifier][containerCloneIdentifier][0].outerHTML;
                // leading, negative lookbehind ("?<!")
                containerCloneHtml = containerCloneHtml.replace(new RegExp(copyMotherIdentifierRegex, 'g'), function($0, $1) {
                    return $1 ? $0 : newIdentifier;
                });

                newIdentifierNameRegex = '[' + newIdentifierParts.join('][') + ']';
                containerCloneHtml = containerCloneHtml.replace(new RegExp(oldIdentifierNameRegex, 'g'), newIdentifierNameRegex);

                $containerClone = $(containerCloneHtml);
                $containerClone.attr('data-copy-reference', referenceElementIdentifier);

                containerCloneHtml = $containerClone[0].outerHTML;

                setRandomIds($containerClone);

                if ($elementCopies.length === 0) {
                    $referenceElement.after($containerClone);
                } else {
                    $elementCopies.last().after($containerClone);
                }

                $(document).trigger('after-element-copy', [$containerClone]);
                $(document).trigger('initialize-repeatable-container-copy-buttons', [containerClones]);
                $(document).trigger('initialize-repeatable-container-remove-buttons');
            });
        }
    });
});

$(document).on('initialize-repeatable-container-remove-buttons', function(event) {
    $('[data-repeatable-container][data-remove-button]').each(function(e) {
        var $element = $(this),
            referenceElementIdentifier = $element.attr('data-remove-button-for'),
            $form = $element.closest('form'),
            $referenceElement = $('[data-repeatable-container][data-identifier="' + referenceElementIdentifier + '"]', $form),
            referenceElementIsRoot = $referenceElement.is('[data-is-root]'),
            referenceReferenceElementIdentifier = referenceElementIsRoot ? referenceElementIdentifier : $referenceElement.attr('data-copy-reference'),
            $referenceReferenceElement = $('[data-repeatable-container][data-identifier="' + referenceReferenceElementIdentifier + '"]', $form),
            $elementCopies = $('[data-repeatable-container][data-copy-reference="' + referenceReferenceElementIdentifier + '"]', $form),
            minCopies = $referenceReferenceElement.attr('data-min-copies');

        if ($referenceElement.is('[data-copy-reference]') && $referenceElement.attr('data-copy-reference') !== '') {
            if ($elementCopies.length <= minCopies) {
                $element.removeClass('hidden').addClass('disabled');
            } else {
                $element.removeClass('hidden disabled');
            }
        } else {
            $element.empty().off().remove();
        }

        if ($element.hasClass('disabled')) {
            $element.off();
        } else {
            $element.off().on('click', function(e) {
                e.preventDefault();

                var $element = $(this),
                    referenceElementIdentifier = $element.attr('data-remove-button-for'),
                    $form = $element.closest('form'),
                    $referenceElement = $('[data-repeatable-container][data-identifier="' + referenceElementIdentifier + '"]', $form),
                    referenceElementIsRoot = $referenceElement.is('[data-is-root]'),
                    referenceReferenceElementIdentifier = referenceElementIsRoot ? referenceElementIdentifier : $referenceElement.attr('data-copy-reference'),
                    $referenceReferenceElement = $('[data-repeatable-container][data-identifier="' + referenceReferenceElementIdentifier + '"]', $form),
                    $elementCopies = $('[data-repeatable-container][data-copy-reference="' + referenceReferenceElementIdentifier + '"]', $form),
                    maxCopies = $referenceReferenceElement.attr('data-max-copies'),
                    $copyButton = $('[data-repeatable-container][data-copy-button-for="' + referenceReferenceElementIdentifier + '"]', $form);

                if ($elementCopies.length - 1 < maxCopies) {
                    $copyButton.attr('disabled', null);
                }

                $referenceElement.empty().off().remove();
                $(document).trigger('initialize-repeatable-container-remove-buttons');
            });
        }
    });
});

$(document).on('after-element-copy', function(event, $containerClone) {
    $('[data-element-type="DatePicker"]').each(function(e) {
        var $element = $(this),
            dateFormat;

        if (!$element.hasClass('hasDatepicker') && parseInt($element.attr('data-element-datepicker-enabled')) === 1) {
            dateFormat = $element.attr('data-element-datepicker-date-format');

            dateFormat = dateFormat.replace('d', 'dd');
            dateFormat = dateFormat.replace('j', 'o');
            dateFormat = dateFormat.replace('l', 'DD');
            dateFormat = dateFormat.replace('F', 'MM');
            dateFormat = dateFormat.replace('m', 'mm');
            dateFormat = dateFormat.replace('n', 'm');
            dateFormat = dateFormat.replace('Y', 'yy');

            $('#' + $element.attr('id')).datepicker({
                dateFormat: dateFormat
            }).on('keydown', function(e) {
                if(e.keyCode == 8 || e.keyCode == 46) {
                    e.preventDefault();
                    $.datepicker._clearDate(this);
                }
            });
        }
    });
});
