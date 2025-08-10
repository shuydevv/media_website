<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Admin\Course\IndexController;
use App\Http\Controllers\Admin\Course\ShowController;
use App\Http\Controllers\Admin\Course\CreateController;
use App\Http\Controllers\Admin\Course\StoreController;
use App\Http\Controllers\Admin\Course\EditController;
use App\Http\Controllers\Admin\Course\UpdateController;
use App\Http\Controllers\Admin\Course\DestroyController;
use App\Http\Controllers\Admin\Lesson\LessonByCourseController;
use App\Http\Controllers\LessonAjaxController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::group(['namespace' => 'App\Http\Controllers\Main'], function() {
    Route::get('/', 'IndexController')->name('index');
    Route::get('/repetitor', 'RepetitorController')->name('main.repetitor');
    Route::get('/author', 'AuthorController')->name('main.author');
    // Route::get('/shpargalki', 'ShpargalkiController')->name('main.shpargalki');
});
Route::group(['namespace' => 'App\Http\Controllers\Post', 'prefix' => 'posts'], function() {
    Route::get('/', 'IndexController')->name('post.index');
    Route::get('/{post:path}', 'ShowController')->name('post.show');
});

Route::group(['namespace' => 'App\Http\Controllers\Exercise', 'prefix' => 'exercises'], function() {
    Route::get('/', 'IndexController')->name('exercise.index');
    Route::get('/{exercise}', 'ShowController')->name('exercise.show');
});

// Route::group(['namespace' => 'App\Http\Controllers\Course', 'prefix' => 'courses'], function() {
//     Route::get('/', 'IndexController')->name('post.index');
//     Route::get('/{post:path}', 'ShowController')->name('post.show');
// });

Route::group(['namespace' => 'App\Http\Controllers\Shpargalka', 'prefix' => 'materials'], function() {
    Route::get('/', 'IndexController')->name('shpargalka.index');
    Route::get('/{shpargalka}', 'ShowController')->name('shpargalka.show');
});
Route::group(['namespace' => 'App\Http\Controllers\Admin', 'prefix' => 'admin', 
'middleware' => ['auth', 'admin', 'verified']], function() {
    Route::group(['namespace' => 'Main'], function() {
        Route::get('/', 'IndexController')->name('main.index');
    });

    Route::group(['namespace' => 'Course', 'prefix' => 'courses'], function() {
        Route::get('/', 'IndexController')->name('admin.courses.index');
        Route::get('/create', 'CreateController')->name('admin.courses.create');
        Route::post('/store', 'StoreController')->name('admin.courses.store');
        Route::get('/{course}', 'ShowController')->name('admin.courses.show');
        Route::get('/{course}/edit', 'EditController')->name('admin.courses.edit');
        Route::patch('/{course}', 'UpdateController')->name('admin.courses.update');
        Route::delete('/{course}', 'DestroyController')->name('admin.courses.destroy');

    });

        Route::group(['namespace' => 'Session', 'prefix' => 'sessions'], function() {
            Route::get('/', 'IndexController')->name('admin.sessions.index');
            Route::get('/create', 'CreateController')->name('admin.sessions.create');
            Route::post('/store', 'StoreController')->name('admin.sessions.store');
            Route::get('{session}/edit', 'EditController')->name('admin.sessions.edit');
            Route::put('{session}', 'UpdateController')->name('admin.sessions.update');
    });

    Route::group(['namespace' => 'Lesson', 'prefix' => 'lessons'], function() {
            Route::get('/', 'IndexController')->name('admin.lessons.index');
            Route::get('/create', 'CreateController')->name('admin.lessons.create');
            Route::post('/', 'StoreController')->name('admin.lessons.store');
            Route::get('/{lesson}/edit', 'EditController')->name('admin.lessons.edit');
            Route::put('/{lesson}', 'UpdateController')->name('admin.lessons.update');
            Route::delete('/{lesson}', 'DestroyController')->name('admin.lessons.destroy');
    });
    // });

    Route::group(['namespace' => 'Homework', 'prefix' => 'homeworks'], function() {
        Route::get('/', 'IndexController')->name('admin.homeworks.index');
        Route::get('create', 'CreateController')->name('admin.homeworks.create');
        Route::get('{homework}/edit', 'EditController')->name('admin.homeworks.edit');
        Route::get('/{homework}', 'ShowController')->name('admin.homeworks.show');
        Route::post('/', 'StoreController')->name('admin.homeworks.store');
        Route::put('/{homework}', 'UpdateController')->name('admin.homeworks.update');
        Route::delete('/{homework}', 'DestroyController')->name('admin.homeworks.destroy');
    });

    Route::get('/api/courses/{course}/sessions', [\App\Http\Controllers\Admin\Session\ApiController::class, 'sessionsByCourse']);


    Route::group(['namespace' => 'Category', 'prefix' => 'categories'], function() {
        Route::get('/', 'IndexController')->name('admin.category.index');
        Route::get('/create', 'CreateController')->name('admin.category.create');
        Route::post('/store', 'StoreController')->name('admin.category.store');
        Route::get('/{category}', 'ShowController')->name('admin.category.show');
        Route::get('/{category}/edit', 'EditController')->name('admin.category.edit');
        Route::patch('/{category}', 'UpdateController')->name('admin.category.update');
        Route::delete('/{category}', 'DeleteController')->name('admin.category.delete');
        // Route::get('/store', 'IndexController')->name('category.index');
    });

    Route::group(['namespace' => 'Section', 'prefix' => 'sections'], function() {
        Route::get('/', 'IndexController')->name('admin.section.index');
        Route::get('/create', 'CreateController')->name('admin.section.create');
        Route::post('/store', 'StoreController')->name('admin.section.store');
        Route::get('/{section}', 'ShowController')->name('admin.section.show');
        Route::get('/{section}/edit', 'EditController')->name('admin.section.edit');
        Route::patch('/{section}', 'UpdateController')->name('admin.section.update');
        Route::delete('/{section}', 'DeleteController')->name('admin.section.delete');
        // Route::get('/store', 'IndexController')->name('category.index');
    });

    Route::group(['namespace' => 'Topic', 'prefix' => 'topics'], function() {
        Route::get('/', 'IndexController')->name('admin.topic.index');
        Route::get('/create', 'CreateController')->name('admin.topic.create');
        Route::post('/store', 'StoreController')->name('admin.topic.store');
        Route::get('/{topic}', 'ShowController')->name('admin.topic.show');
        Route::get('/{topic}/edit', 'EditController')->name('admin.topic.edit');
        Route::patch('/{topic}', 'UpdateController')->name('admin.topic.update');
        Route::delete('/{topic}', 'DeleteController')->name('admin.topic.delete');
        // Route::get('/store', 'IndexController')->name('category.index');
    });
    Route::group(['namespace' => 'Shpargalka', 'prefix' => 'shpargalki'], function() {
        Route::get('/', 'IndexController')->name('admin.shpargalka.index');
        Route::get('/create', 'CreateController')->name('admin.shpargalka.create');
        Route::post('/store', 'StoreController')->name('admin.shpargalka.store');
        Route::get('/{shpargalka}', 'ShowController')->name('admin.shpargalka.show');
        Route::get('/{shpargalka}/edit', 'EditController')->name('admin.shpargalka.edit');
        Route::patch('/{shpargalka}', 'UpdateController')->name('admin.shpargalka.update');
        Route::delete('/{shpargalka}', 'DeleteController')->name('admin.shpargalka.delete');

    });

    Route::group(['namespace' => 'Tag', 'prefix' => 'tags'], function() {
        Route::get('/', 'IndexController')->name('admin.tag.index');
        Route::get('/create', 'CreateController')->name('admin.tag.create');
        Route::post('/store', 'StoreController')->name('admin.tag.store');
        Route::get('/{tag}', 'ShowController')->name('admin.tag.show');
        Route::get('/{tag}/edit', 'EditController')->name('admin.tag.edit');
        Route::patch('/{tag}', 'UpdateController')->name('admin.tag.update');
        Route::delete('/{tag}', 'DeleteController')->name('admin.tag.delete');
    });

    Route::group(['namespace' => 'Post', 'prefix' => 'posts'], function() {
        Route::get('/', 'IndexController')->name('admin.post.index');
        Route::get('/create', 'CreateController')->name('admin.post.create');
        Route::post('/store', 'StoreController')->name('admin.post.store');
        Route::get('/{post:path}', 'ShowController')->name('admin.post.show');
        Route::get('/{post:path}/edit', 'EditController')->name('admin.post.edit');
        Route::patch('/{post:path}', 'UpdateController')->name('admin.post.update');
        Route::delete('/{post:path}', 'DeleteController')->name('admin.post.delete');
    });

    Route::group(['namespace' => 'Exercise', 'prefix' => 'exercises'], function() {
        Route::get('/', 'IndexController')->name('admin.exercise.index');
        Route::get('/create', 'CreateController')->name('admin.exercise.create');
        Route::post('/store', 'StoreController')->name('admin.exercise.store');
        Route::get('/{exercise}', 'ShowController')->name('admin.exercise.show');
        Route::get('/{exercise}/edit', 'EditController')->name('admin.exercise.edit');
        Route::patch('/{exercise}', 'UpdateController')->name('admin.exercise.update');
        Route::delete('/{exercise}', 'DeleteController')->name('admin.exercise.delete');
    });

    Route::group(['namespace' => 'User', 'prefix' => 'users'], function() {
        Route::get('/', 'IndexController')->name('admin.user.index');
        Route::get('/create', 'CreateController')->name('admin.user.create');
        Route::post('/store', 'StoreController')->name('admin.user.store');
        Route::get('/{user}', 'ShowController')->name('admin.user.show');
        Route::get('/{user}/edit', 'EditController')->name('admin.user.edit');
        Route::patch('/{user}', 'UpdateController')->name('admin.user.update');
        Route::delete('/{user}', 'DeleteController')->name('admin.user.delete');
    });
});
Route::group(['namespace' => 'App\Http\Controllers\Controller'], function() {
    Route::get('/1', 'ComponentController');
});


// Route::get('/', function () {
//     return view('welcome');
// });


Route::get('/lessons', LessonByCourseController::class)->name('lessons.by-course');

Auth::routes(['verify' => true]);

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
