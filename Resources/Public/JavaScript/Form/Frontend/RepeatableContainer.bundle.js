(()=>{"use strict";class e{constructor(e){this.root=e,this.initializeElements(),this.initializeEvents(),this.initializeRows()}initializeElements(){this.addButton=this.root.querySelector("[data-copy-button]"),this.repeatableContainer=this.root.querySelector("[data-repeatable-root]"),this.rowPrototype=(e=>{const t=document.createElement("div");return t.innerHTML=e.outerHTML,t.querySelectorAll('input:not([type="checkbox"]):not([type="radio"]):not([type="hidden"])').forEach((e=>{e.setAttribute("value","")})),t.querySelectorAll('input[type="checkbox"], input[type="radio"]').forEach((e=>{e.removeAttribute("checked"),e.removeAttribute("selected")})),t.querySelectorAll("select").forEach((e=>{e.selectedIndex=0,e.querySelectorAll("option").forEach((e=>{e.removeAttribute("selected")}))})),t.innerHTML})(this.repeatableContainer.firstElementChild),this.nameRegex=new RegExp("(\\["+this.root.getAttribute("id").replace(/\.\d+$/,"")+"\\])\\[(\\d+)\\]","g")}initializeEvents(){this.addButton.addEventListener("click",(e=>{e.preventDefault(),this.initializeSingleRow(this.addRow()),this.updateDeleteButtons()}))}initializeRows(){Array.from(this.repeatableContainer.children).forEach((e=>{this.initializeSingleRow(e)})),this.updateDeleteButtons(),this.dertermineHighestRowNumber()}initializeSingleRow(e){e.querySelector("[data-remove-button]").addEventListener("click",(t=>{t.preventDefault(),e.remove(),this.updateDeleteButtons()}))}updateDeleteButtons(){this.repeatableContainer.children.length>1?this.repeatableContainer.querySelectorAll("[data-remove-button]").forEach((e=>e.removeAttribute("disabled"))):this.repeatableContainer.querySelectorAll("[data-remove-button]").forEach((e=>e.setAttribute("disabled","disabled")))}addRow(){const e=document.createElement("div");return e.innerHTML=this.rowPrototype.replace(this.nameRegex,`$1[${this.rowNumber}]`),this.rowNumber++,this.repeatableContainer.appendChild(e.firstElementChild)}dertermineHighestRowNumber(){let e=0;const t=this.repeatableContainer.innerHTML.matchAll(this.nameRegex);for(const i of t)e=Math.max(e,parseInt(i[2]));this.rowNumber=e+1}}document.addEventListener("DOMContentLoaded",(()=>{document.querySelectorAll("[data-repeatable-container]").forEach((t=>{new e(t)}))}))})();