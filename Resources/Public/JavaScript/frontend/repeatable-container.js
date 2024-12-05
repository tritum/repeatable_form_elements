var ready = (callback) => {
  if (document.readyState != "loading") callback();
  else document.addEventListener("DOMContentLoaded", callback);
}
ready(() => {
    var containerClones = {};

    document.querySelectorAll('[data-repeatable-container][data-is-root]').forEach((rootElement) => {
        let containerClone = rootElement.cloneNode(true),
            containerIdentifier = rootElement.dataset['identifier'],
            formIdentifier = rootElement.closest('form').getAttribute('id'),
            copyButton = containerClone.querySelector('[data-repeatable-container][data-copy-button]');

        containerClones[formIdentifier] = containerClones[formIdentifier] || {};

        for (const copyElement of containerClone.querySelectorAll('[data-repeatable-container][data-is-copy]')) {
            copyElement.remove();
        }
        if (copyButton !== null) {
            copyButton.remove();
        }
        for (const alertElement of containerClone.querySelectorAll('[role="alert"]')) {
            alertElement.remove();
        }

        containerClone.querySelectorAll('[data-repeatable-container][data-is-root]').forEach((cloneElement) => {
            delete cloneElement.dataset.isRoot;
            cloneElement.dataset.isCopy = '';
            cloneElement.dataset.copyMother = cloneElement.dataset['identifier'];
        });
        delete containerClone.dataset.isRoot;
        containerClone.dataset.isCopy = '';
        containerClone.querySelectorAll('*').forEach((cloneChildElement) => {
            cloneChildElement.classList.remove('has-error');
        });

        let inputs = [...containerClone.querySelectorAll('input')];
        inputs.filter((input) => ['checkbox', 'radio', 'hidden'].indexOf(input.getAttribute('type')) == -1).forEach((inputElement) => {
            inputElement.setAttribute('value', '');
        });
        inputs.filter((input) => ['file'].indexOf(input.getAttribute('type')) >= 0).forEach((inputElement) => {
            [...inputElement.parentNode.children].filter((child) => child !== inputElement).forEach((siblingElement) => {
                siblingElement.remove();
            });
        });
        inputs.filter((input) => ['checkbox', 'radio'].indexOf(input.getAttribute('type')) >= 0).forEach((inputElement) => {
            inputElement.checked = false;
        });
        containerClone.querySelectorAll('textarea').forEach((textareaElement) => {
            textareaElement.setAttribute('value', '');
        });
        containerClone.querySelectorAll('select').forEach((selectElement) => {
            let selected = selectElement.selectedOptions;
            for (let i = 0; i < selected.length; i++) {
                selected[i].removeAttribute('selected');
            }
            selectElement.selectedIndex = -1;
        });

        containerClones[formIdentifier][containerIdentifier] = containerClone;
    });

    document.dispatchEvent(new CustomEvent('initialize-repeatable-container-copy-buttons', { 'detail': { 'containerClones': containerClones } }));
    document.dispatchEvent(new CustomEvent('initialize-repeatable-container-remove-buttons'));
});

document.addEventListener('initialize-repeatable-container-copy-buttons', (copyEvent) => {
    let containerClones = copyEvent.detail.containerClones,
        getNextCopyNumber = (referenceElement, formElement) => {
            let highestCopyNumber = 0;

            formElement.querySelectorAll('[data-repeatable-container][data-copy-reference="' + referenceElement.dataset['identifier'] + '"]').forEach((copyElement) => {
                let copyNumber = parseInt(copyElement.dataset['identifier'].split('.').pop());

                if (copyNumber > highestCopyNumber) {
                    highestCopyNumber = copyNumber;
                }
            });

            return ++highestCopyNumber;
        },
        setRandomIds = (subject) => {
            let idReplacements = {};

            subject.querySelectorAll('[id]').forEach((subjectIdElement) => {
                let id = subjectIdElement.getAttribute('id'),
                    newId = Math.floor(Math.random() * 99999999) + Date.now();

                subjectIdElement.setAttribute('id', newId);
                idReplacements[id] = newId;
            });

            subject.querySelectorAll('*').forEach((subjectChildElement) => {
                for (let i = 0, len = subjectChildElement.attributes.length; i < len; i++) {
                    let attributeValue = subjectChildElement.attributes[i].nodeValue;

                    if (attributeValue in idReplacements) {
                        subjectChildElement.attributes[i].nodeValue = idReplacements[attributeValue];
                    }
                }
            });
        },
        escapeRegExp = (subject) => {
            return subject.replace(/[-\/\\^$*+?.()|[\]{}]/g, '\\$&');
        };

    document.querySelectorAll('[data-repeatable-container][data-copy-button]').forEach((copyBtnElement) => {
        let formElement = copyBtnElement.closest('form'),
            referenceElementIdentifier = copyBtnElement.dataset['copyButtonFor'],
            referenceElement = formElement.querySelector('[data-repeatable-container][data-identifier="' + referenceElementIdentifier + '"]'),
            elementCopies = formElement.querySelectorAll('[data-repeatable-container][data-copy-reference="' + referenceElementIdentifier + '"]'),
            maxCopies = referenceElement.dataset['maxCopies'] || false;

        if (maxCopies && elementCopies.length >= maxCopies) {
            copyBtnElement.disabled = true;
        } else {
            copyBtnElement.disabled = false;
            /* clone element to remove all event listeners */
            let copyBtnElementClone = copyBtnElement.cloneNode(true);
            copyBtnElement.replaceWith(copyBtnElementClone);
            copyBtnElementClone.addEventListener('click', (clickEvent) => {
                clickEvent.preventDefault();
                let clickElement = clickEvent.currentTarget,
                    clickFormElement = clickElement.closest('form'),
                    referenceElementIdentifier = clickElement.dataset['copyButtonFor'],
                    formIdentifier = clickFormElement.getAttribute('id'),
                    referenceElement = clickFormElement.querySelector('[data-repeatable-container][data-identifier="' + referenceElementIdentifier + '"]'),
                    referenceElementIsRoot = 'isRoot' in referenceElement.dataset,
                    elementCopies = clickFormElement.querySelectorAll('[data-repeatable-container][data-copy-reference="' + referenceElementIdentifier + '"]'),
                    containerCloneIdentifier = referenceElementIsRoot ? referenceElementIdentifier : referenceElement.dataset['copyMother'],
                    copyMotherIdentifier = containerClones[formIdentifier][containerCloneIdentifier].dataset['identifier'],
                    newIdentifierParts = referenceElement.dataset['identifier'].split('.'),
                    oldIdentifierNameRegex = escapeRegExp('[' + copyMotherIdentifier.split('.').join('][') + ']'),
                    copyMotherIdentifierRegex = '(' + escapeRegExp('data-copy-mother="') + ')?' + escapeRegExp(copyMotherIdentifier),
                    newIdentifier = undefined,
                    newIdentifierNameRegex = undefined,
                    containerClone = undefined,
                    containerCloneHtml = undefined;

                newIdentifierParts.pop();
                newIdentifierParts.push(getNextCopyNumber(referenceElement, clickFormElement));
                newIdentifier = newIdentifierParts.join('.');

                containerCloneHtml = containerClones[formIdentifier][containerCloneIdentifier].outerHTML;
                // leading, negative lookbehind ("?<!")
                containerCloneHtml = containerCloneHtml.replace(new RegExp(copyMotherIdentifierRegex, 'g'), ($0, $1) => {
                    return $1 ? $0 : newIdentifier;
                });

                newIdentifierNameRegex = '[' + newIdentifierParts.join('][') + ']';
                containerCloneHtml = containerCloneHtml.replace(new RegExp(oldIdentifierNameRegex, 'g'), newIdentifierNameRegex);

                let containerCloneWrap = document.createElement('div');
                containerCloneWrap.innerHTML = containerCloneHtml;
                containerClone = containerCloneWrap.firstChild;
                containerClone.dataset.copyReference = referenceElementIdentifier;

                setRandomIds(containerClone);

                if (elementCopies.length === 0) {
                    referenceElement.after(containerClone);
                } else {
                    [...elementCopies].at(-1).after(containerClone);
                }

                // document.dispatchEvent(new CustomEvent('after-element-copy', { 'detail': { 'containerClone': containerClone } }));
                document.dispatchEvent(new CustomEvent('initialize-repeatable-container-copy-buttons', { 'detail': { 'containerClones': containerClones } }));
                document.dispatchEvent(new CustomEvent('initialize-repeatable-container-remove-buttons'));
            });
        }
    });
});

document.addEventListener('initialize-repeatable-container-remove-buttons', (removeEvent) => {
    document.querySelectorAll('[data-repeatable-container][data-remove-button]').forEach((removeBtnElement) => {
        let formElement = removeBtnElement.closest('form'),
            referenceElementIdentifier = removeBtnElement.dataset['removeButtonFor'],
            referenceElement = formElement.querySelector('[data-repeatable-container][data-identifier="' + referenceElementIdentifier + '"]'),
            referenceElementIsRoot = 'isRoot' in referenceElement.dataset,
            referenceReferenceElementIdentifier = referenceElementIsRoot ? referenceElementIdentifier : referenceElement.dataset['copyReference'],
            referenceReferenceElement = formElement.querySelector('[data-repeatable-container][data-identifier="' + referenceReferenceElementIdentifier + '"]'),
            elementCopies = formElement.querySelectorAll('[data-repeatable-container][data-copy-reference="' + referenceReferenceElementIdentifier + '"]'),
            minCopies = referenceReferenceElement?.dataset['minCopies'] || false;

        if ((referenceElement.dataset['copyReference'] || false) && referenceElement.dataset['copyReference'] !== '') {
            if (minCopies && elementCopies.length <= minCopies) {
                // removeBtnElement.classList.remove('d-none');
                removeBtnElement.disabled = true;
            } else {
                // removeBtnElement.classList.remove('d-none');
                removeBtnElement.disabled = false;
            }
        } else {
            removeBtnElement.remove();
        }

        if (removeBtnElement.disabled) {
            /* clone element to remove all event listeners */
            removeBtnElement.replaceWith(removeBtnElement.cloneNode(true));
        } else {
            /* clone element to remove all event listeners */
            let removeBtnElementClone = removeBtnElement.cloneNode(true);
            removeBtnElement.replaceWith(removeBtnElementClone);
            removeBtnElementClone.addEventListener('click', (clickEvent) => {
                clickEvent.preventDefault();

                let clickElement = clickEvent.currentTarget,
                    clickFormElement = clickElement.closest('form'),
                    referenceElementIdentifier = clickElement.dataset['removeButtonFor'],
                    referenceElement = clickFormElement.querySelector('[data-repeatable-container][data-identifier="' + referenceElementIdentifier + '"]'),
                    referenceElementIsRoot = 'isRoot' in referenceElement.dataset,
                    referenceReferenceElementIdentifier = referenceElementIsRoot ? referenceElementIdentifier : referenceElement.dataset['copyReference'],
                    referenceReferenceElement = clickFormElement.querySelector('[data-repeatable-container][data-identifier="' + referenceReferenceElementIdentifier + '"]'),
                    elementCopies = clickFormElement.querySelectorAll('[data-repeatable-container][data-copy-reference="' + referenceReferenceElementIdentifier + '"]'),
                    maxCopies = referenceReferenceElement?.dataset['maxCopies'] || false,
                    copyButton = clickFormElement.querySelector('[data-repeatable-container][data-copy-button-for="' + referenceReferenceElementIdentifier + '"]');

                if (maxCopies && elementCopies.length - 1 < maxCopies) {
                    copyButton.disabled = false;
                }

                referenceElement.remove();
                document.dispatchEvent(new CustomEvent('initialize-repeatable-container-remove-buttons'));
            });
        }
    });
});

/* DatePicker is a jQuery UI function and we want to get rid of jQuery... */
/* document.addEventListener('after-element-copy', (afterCopyEvent) => {
    let containerClone = afterCopyEvent.detail.containerClone;
    document.querySelectorAll('[data-element-type="DatePicker"]').forEach((datePickerElement) => {
        var dateFormat;

        // if (!datePickerElement.classList.contains('hasDatepicker') && parseInt(datePickerElement.getAttribute('data-element-datepicker-enabled')) === 1) {
        if (!datePickerElement.classList.contains('hasDatepicker') && parseInt(datePickerElement.dataset['elementDatepickerEnabled']) === 1) {
            // dateFormat = datePickerElement.getAttribute('data-element-datepicker-date-format');
            dateFormat = datePickerElement.dataset['elementDatepickerDateFormat'];

            dateFormat = dateFormat.replace('d', 'dd');
            dateFormat = dateFormat.replace('j', 'o');
            dateFormat = dateFormat.replace('l', 'DD');
            dateFormat = dateFormat.replace('F', 'MM');
            dateFormat = dateFormat.replace('m', 'mm');
            dateFormat = dateFormat.replace('n', 'm');
            dateFormat = dateFormat.replace('Y', 'yy');

            $('#' + datePickerElement.attr('id')).datepicker({
                dateFormat: dateFormat
            }).on('keydown', function(e) {
                if(e.keyCode == 8 || e.keyCode == 46) {
                    e.preventDefault();
                    $.datepicker._clearDate(this);
                }
            });
        }
    });
}); */
