console.log('1')

let button_answers = document.getElementsByClassName('btn_answers')
let button_columns = document.getElementsByClassName('btn_columns')
let button_text = document.getElementsByClassName('btn_text')

let block_answers = document.getElementsByClassName('block_answers')
let block_columns = document.getElementsByClassName('block_columns')
let block_text = document.getElementsByClassName('block_text')
let block_last = document.getElementsByClassName('block_last')

console.log(button_answers[0])

button_answers[0].addEventListener("click", function(e){
    e.preventDefault()
    console.log('1')

    // console.log(button_answers[0])

    this.classList.add('border-gray-600', 'border-2');
    button_columns[0].classList.remove('border-gray-600', 'border-2');
    button_text[0].classList.remove('border-gray-600', 'border-2');

    block_answers[0].classList.add("hidden")
    block_text[0].classList.add("hidden")
    block_columns[0].classList.remove("hidden")

    block_last[0].classList.remove("hidden")
})

button_columns[0].addEventListener("click", function(e){

    this.classList.add('border-gray-600', 'border-2');
    button_answers[0].classList.remove('border-gray-600', 'border-2');
    button_text[0].classList.remove('border-gray-600', 'border-2');

    e.preventDefault()
    console.log('2')
    block_columns[0].classList.add("hidden")
    block_text[0].classList.add("hidden")
    block_answers[0].classList.remove("hidden")

    block_last[0].classList.remove("hidden")
})

button_text[0].addEventListener("click", function(e){
    e.preventDefault()
    console.log('3')

    this.classList.add('border-gray-600', 'border-2');
    button_columns[0].classList.remove('border-gray-600', 'border-2');
    button_answers[0].classList.remove('border-gray-600', 'border-2');

    block_text[0].classList.remove("hidden")
    block_columns[0].classList.add("hidden")
    block_answers[0].classList.add("hidden")

    block_last[0].classList.remove("hidden")
})