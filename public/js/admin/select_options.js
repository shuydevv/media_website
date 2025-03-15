const category_select = document.querySelector('.category_select')
const section_select = document.querySelector('.section_select')
const topic_select = document.querySelector('.topic_select')

const category_option = document.querySelectorAll('.category_option')
const section_option = document.querySelectorAll('.section_option')
const topic_option = document.querySelectorAll('.topic_option')

const section_option_default = document.querySelectorAll('.section_option_default')

// console.log(section_option.length)

category_select.addEventListener('change', (e) => {
    const selected = e.target.value;
    // console.log(selected)
    let section_lenght = section_option.length
    // console.log(section_lenght)
    for (let i = 0; i < section_lenght; i++) {
        const item = section_option[i]

        console.log('here')
        // section_option_default.options.selected = true
            if (item.id !== selected) {
                console.log('case_1')
                item.classList.add('hidden');
                item.classList.remove('block');

            } else {
                console.log('case_2')
                item.classList.add('block')
                item.classList.remove('hidden');
            }
        }  
})

section_select.addEventListener('change', (e) => {
    const selected = e.target.value;
    // console.log(selected)
    let topic_lenght = topic_option.length
    // console.log(section_lenght)
    for (let i = 0; i < topic_lenght; i++) {
        const item = topic_lenght[i]

        console.log('here')
        // section_option_default.options.selected = true
            if (item.id !== selected) {
                console.log('case_1')
                item.classList.add('hidden');
                item.classList.remove('block');

            } else {
                console.log('case_2')
                item.classList.add('block')
                item.classList.remove('hidden');
            }
        }  
})