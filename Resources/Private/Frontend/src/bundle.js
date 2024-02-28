import {RepeatableContainer} from './components/RepeatableContainer'

document.addEventListener('DOMContentLoaded', () => {
    const repeatableContainers = document.querySelectorAll('[data-repeatable-container]')
    repeatableContainers.forEach(element => {
        new RepeatableContainer(element)
    })
})
