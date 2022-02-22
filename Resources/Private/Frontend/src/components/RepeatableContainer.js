class RepeatableContainer {
    /**
     * @param root {HTMLElement}
     */
    constructor(root) {
        this.root = root

        this.initializeElements()
        this.initializeEvents()
        this.initializeRows()
    }

    initializeElements() {
        /**
         * @type {Element|HTMLElement}
         */
        this.addButton = this.root.querySelector('[data-copy-button]')
        /**
         * @type {Element|HTMLElement}
         */
        this.repeatableContainer = this.root.querySelector('[data-repeatable-root]')
        /**
         * @type {string}
         */
        this.rowPrototype = (firstRow => {
            const temp = document.createElement('div')
            temp.innerHTML = firstRow.outerHTML
            temp.querySelectorAll('input:not([type="checkbox"]):not([type="radio"]):not([type="hidden"])').forEach(input => {
                input.setAttribute('value', '')
            })
            temp.querySelectorAll('input[type="checkbox"], input[type="radio"]').forEach(input => {
                input.removeAttribute('checked')
                input.removeAttribute('selected')
            })
            temp.querySelectorAll('select').forEach(select => {
                select.selectedIndex = 0
                select.querySelectorAll('option').forEach(option => {
                    option.removeAttribute('selected')
                })
            })

            return temp.innerHTML
        })(this.repeatableContainer.firstElementChild)
        this.nameRegex = new RegExp('(\\\[' + this.root.getAttribute('id').replace(/\.\d+$/, '') + '\\\])\\\[(\\\d+)\\\]', 'g')
    }

    initializeEvents() {
        this.addButton.addEventListener('click', ev => {
            ev.preventDefault()
            this.initializeSingleRow(this.addRow())
            this.updateDeleteButtons()
        })
    }

    initializeRows() {
        Array.from(this.repeatableContainer.children).forEach(row => {
            this.initializeSingleRow(row)
        })

        this.updateDeleteButtons()
        this.dertermineHighestRowNumber()
    }

    initializeSingleRow(row) {
        row.querySelector('[data-remove-button]').addEventListener('click', ev => {
            ev.preventDefault()

            row.remove()
            this.updateDeleteButtons()
        })
    }

    updateDeleteButtons() {
        if (this.repeatableContainer.children.length > 1) {
            this.repeatableContainer.querySelectorAll('[data-remove-button]').forEach(button => button.removeAttribute('disabled'))
        } else {
            this.repeatableContainer.querySelectorAll('[data-remove-button]').forEach(button => button.setAttribute('disabled', 'disabled'))
        }
    }

    addRow() {
        const temp = document.createElement('div')
        temp.innerHTML = this.rowPrototype.replace(this.nameRegex, `$1[${this.rowNumber}]`)
        this.rowNumber++

        return this.repeatableContainer.appendChild(temp.firstElementChild)
    }

    dertermineHighestRowNumber() {
        let highestRowNumber = 0
        const matches = this.repeatableContainer.innerHTML.matchAll(this.nameRegex)
        for (const match of matches) {
            highestRowNumber = Math.max(highestRowNumber, parseInt(match[2]))
        }

        this.rowNumber = highestRowNumber + 1
    }
}

export { RepeatableContainer }
