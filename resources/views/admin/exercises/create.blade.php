@extends('admin.layouts.main')

<style>
    .accordion-div {
    margin-bottom: 2px;
}
.accordion {
    color: #111;
    background-color: white;
    cursor: 'pointer';
    padding: 16px 24px 16px 24px;
    width: 100%;
    text-align: left;
    outline: none;
    transition: 0.2s;
    font-weight: 400;
    font-size: 20px;
  }
.accordion:hover {
    background-color: white;
}
  
  /* Add a background color to the button if it is clicked on (add the .active class with JS), and when you move the mouse over it (hover) */
  .active, .accordion:hover {
    /* background-color: #FFEBAD; */
    filter: drop-shadow(0px 4px 8px rgba(45, 149, 156, 0.05));

  }
  /* .active, .accordion:hover {
    background-color: black;
  } */
  
  /* Style the accordion panel. Note: hidden by default */
  .panel {
    padding: 0 18px;
    /* background-color: white; */
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.2s ease-out;
  }
  .panel p {
    padding-top: 0;
    padding-bottom: 24px;
    font-size: 18px;
  }
  .accordion:after {
    content: '+'; /* Unicode character for "plus" sign (+) */
    font-size: 24px;
    color: #777;
    float: right;
    margin-left: 5px;
    font-weight: 600;
  }
  
  .active:after {
    content: "-"; /* Unicode character for "minus" sign (-) */
  }
  /* .faq-block { */
    /* margin-bottom: 100px; */
    /* background-color: #F6F8FC; */
    /* padding-bottom: 90px;
    padding-top: 60px; */
  /* } */

  @media (max-width: 500px) {
    .accordion:after {
      font-size: 18px;
    }
    .panel p {
      font-size: 16px;
      line-height: 135%;
      padding-bottom: 20px;
    }
  }
</style>

@section('content')
    <div>
        <h1 class="text-xl sans mb-4">Создать упражнение</h1>
        <form action="{{route('admin.exercise.store')}}" method="post" enctype="multipart/form-data">
            @csrf
            <div class="mt-2">
                <label class="text-zinc-800 text-sm">Номер задания в экзамене</label>
                <input class="p-2 block border" type="text" placeholder="Введите номер задания" name="ex_number">
            </div>
            <div class="mt-2">
                <label class="text-zinc-800 text-sm">Условие задания</label>
                {{-- <input class="p-2 block border" type="text" placeholder="Введите название" name="title"> --}}
                <textarea rows='1' class="p-2 block border w-full min-h-40" type="text" placeholder="Введите текст задания (вопрос)" name="title"></textarea>

            </div>
            @error('title')
            <p class="mt-2 text-red-400">*{{ $message }}</p>
            @enderror

            {{-- <div class="mt-10">
                <h2 class="text-lg tracking-wider mb-2 mt-8">Это задание первой или второй части?</h2>
                <div>
                    <div>
                        <input type="radio" name="exercise_type" id="first_type">
                        <label for="first_type">Это первая часть</label>
                    </div>
                    <div>
                        <input type="radio" name="exercise_type" id="second_type">
                        <label for="second_type">Это вторая часть</label>
                    </div>
                </div>
            </div> --}}


            <div class="mt-10">
                <h2 class="text-lg tracking-wider mb-2 mt-8">Какое задание вы хотите создать?</h2>
                <div class="flex gap-4">
                    <button type="button" class="btn_answers bg-gray-100 border border-gray-300 p-4 inline-block">С выбором правильных ответов</button>
                    <button type="button" class="btn_columns bg-gray-100 border border-gray-300 p-4 ">На соответствие (с двумя колонками)</button>
                    <button type="button" class="btn_text bg-gray-100 border border-gray-300 p-4 ">Вторая часть</button>
                </div>
            </div>

            <div class="block_answers hidden">
                <hr class="mt-8">
                <h2 class="text-lg tracking-wider mb-2 mt-8">Тестовое задание на соответствие (с двумя колонками)</h2>
                <div class="flex gap-4 justify-between">
                    <div class="shrink w-full">
                        <div class="mt-2">
                            <label class="text-zinc-800 text-sm">Заголовок Левой колонки</label>
                            <input class="p-2 block border w-full" type="text" placeholder="Введите заголовок" name="content_column_1_title">
                        </div>
                        <div class="mt-2">
                            <label class="text-zinc-800 text-sm">Варианты ответа</label>
                            <textarea rows='1' class="p-2 block border w-full min-h-40" type="text" placeholder="Введите текст задания" name="content_column_1_content"></textarea>
                        </div>
                    </div>
                    <div class="shrink w-full">
                        <div class="mt-2">
                            <label class="text-zinc-800 text-sm">Заголовок Правой колонки</label>
                            <input class="p-2 block border w-full" type="text" placeholder="Введите заголовок" name="content_column_2_title">
                        </div>
                        <div class="mt-2">
                            <label class="text-zinc-800 text-sm">Варианты ответа</label>
                            <textarea rows='1' class="p-2 block border w-full min-h-40" type="text" placeholder="Введите текст задания" name="content_column_2_content"></textarea>
                        </div>
                    </div>
                </div>
                <hr class="mt-8 mb-8">
            </div>


            <div class="block_columns hidden">
                <hr class="mt-8">
                <h2 class="text-lg tracking-wider mb-2 mt-8">Тестовое задание с вариантами ответов</h2>
                <div class="">
                    <div class="mt-2">
                        <textarea rows='1' class="p-2 block border w-full min-h-40" type="text" placeholder="Варианты ответа" name="content_options"></textarea>
                    </div>
                </div>
                <hr class="mt-8 mb-8">
            </div>

            <div class="block_text hidden">
                <hr class="mt-8">
                <h2 class="text-lg tracking-wider mb-4 mt-8">Текст (из второй части)</h2>
                <div class="">
                    <div class="mt-2">
                        <textarea rows='1' class="p-2 block border w-full min-h-40" type="text" placeholder="Вставьте текст" name="text_spoiler"></textarea>
                    </div>
                </div>
                <hr class="mt-8 mb-8">
            </div>

            <div class="block_last hidden">
                {{-- <hr class="mt-8"> --}}
                <h2 class="text-lg tracking-wider mb-2 mt-8">Ответ и пояснение</h2>
                <div>
                    <label class="text-zinc-800 text-sm">Ответ на задание (Только цифры, для второй части заполните только пояснение)</label>
                    <input rows='1' class="p-2 block border mb-4 w-full" type="text" placeholder="Ответ (только цифры)" name="answer"></input>
                </div>
    
                <div class="mt-2">
                    <label class="text-zinc-800 text-sm">Пояснение к ответу</label>
                    <textarea rows='1' class="p-2 block border w-full min-h-40" type="text" placeholder="Введите пояснение" name="comment"></textarea>
                </div>
                {{-- @error('answer') --}}
                <div>
                </div>
                @error('post')
                <p class="mt-2 text-red-400">*{{ $message }}</p>
                @enderror
    
    
    
                <div class="mt-5">
                    <hr class="mt-8">
                    <h2 class="text-lg tracking-wider mb-4 mt-8">Укажите предмет, раздел и тему</h2>
                    <label class="mr-5">Выберите категорию (предмет)</label>
                    <select class="category_select p-2 border" name="category_id">
                        <option value disabled selected>Не выбрано</option>
                        @foreach ($categories as $category)
                        <a href="{{ request()->fullUrlWithQuery(['category' => $category->title ]) }}">
                            <option class="category_option" value="{{$category->id}}">{{$category->title}}</option>
                        </a>
                        @endforeach
                    </select>
                </div>
    
                <div class="mt-5">
                    <label class="mr-5">Выберите раздел</label>
                    <select class="section_select p-2 border" name="section_id">
                        <option class="section_option_default" value disabled selected>Не выбрано</option>
                        @foreach ($sections as $section)
                        <option class="section_option" id="{{$section->category_id}}" value="{{$section->id}}">{{$section->title}}</option>
                        @endforeach
                    </select>
                </div>
    
                <div class="mt-5">
                    <label class="mr-5">Выберите тему</label>
                    <select class="topic_select p-2 border" name="topic_id">
                        <option value disabled selected>Не выбрано</option>
                        @foreach ($topics as $topic)
                        <option class="topic_option" id="{{$topic->section_id}}" value="{{$topic->id}}">{{$topic->title}}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mt-10">
                    <hr class="mt-8">
                    <h2 class="text-lg tracking-wider mb-4 mt-8">Изображение (если требуется)</h2>
                    <label class="block mb-2 text-sm font-medium text-gray-900 dark:text-white" for="multiple_files">Изображение</label>
                    <input class="block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 dark:text-gray-400 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400" id="multiple_files" name="main_image" type="file"></div>
                <a><button type="submit" class="mt-12 p-2 px-4 bg-zinc-200 border-2 border-gray-600 hover:bg-zinc-300">Создать задание</button></a>
            </div>

            </form>
        
        <script src="{{ asset("/js/admin/tabs.js") }}"></script>
        <script src="{{ asset("/js/admin/select_options.js") }}"></script>

@endsection
