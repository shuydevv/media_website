var acc = document.getElementsByClassName("accordion");
console.log(acc[0].style)
var i;


// for (i = 0; i < acc.length; i++) {
  acc[0].addEventListener("click", function() {
    
console.log('accordion')
    this.classList.add("active");
    var panel = this.previousElementSibling;
    panel.classList.remove('hidden')
    if (panel.style.maxHeight) {
      panel.style.maxHeight = null;
    } else {
      panel.style.maxHeight = panel.scrollHeight + "px";
    }
    acc[0].classList.add('hidden');
  })