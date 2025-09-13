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
use App\Http\Controllers\Admin\Session\ApiController;
use App\Http\Controllers\LessonAjaxController;
use App\Http\Controllers\Promo\RedeemController;
use App\Http\Controllers\Student\DashboardController;
use App\Http\Controllers\Admin\Promo\PromoCodeController;
use App\Http\Controllers\Checkout\CourseCheckoutController;
use App\Http\Controllers\Student\CourseController;
use App\Http\Controllers\Student\LessonController as StudentLessonController;
use App\Http\Controllers\Student\SubmissionController as StudentSubmissionController;
use App\Http\Controllers\Mentor\SubmissionController as MentorSubmissionController;
use App\Http\Controllers\Student\SubmissionController;
use App\Http\Controllers\Mentor\ReviewAiController;

// --- Mentor/Admin: проверка письменной части ---
// use App\Http\Controllers\Mentor\SubmissionReviewController;





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
Route::get('/admin/api/courses/{course}/sessions', [ApiController::class, 'sessionsByCourse'])
    ->name('admin.api.sessions.by-course');

Route::middleware(['auth'])->group(function () {
    Route::get('/promo/redeem', [RedeemController::class, 'form'])->name('promo.redeem.form');
    Route::post('/promo/redeem', [RedeemController::class, 'redeem'])->name('promo.redeem');
});

// Route::match(['get', 'post'], '/promo/redeem', RedeemController::class)
//     ->middleware('auth')
//     ->name('promo.redeem');

Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/promos', [PromoCodeController::class, 'index'])->name('promos.index');
    Route::get('/promos/create', [PromoCodeController::class, 'create'])->name('promos.create');
    Route::post('/promos', [PromoCodeController::class, 'store'])->name('promos.store');

    // ✨ редактирование
    Route::get('/promos/{promo}/edit', [PromoCodeController::class, 'edit'])->name('promos.edit');
    Route::put('/promos/{promo}', [PromoCodeController::class, 'update'])->name('promos.update');

    // вкл/выкл
    Route::post('/promos/{promo}/toggle', [PromoCodeController::class, 'toggle'])->name('promos.toggle');

    // (опционально) удаление
    // Route::delete('/promos/{promo}', [PromoCodeController::class, 'destroy'])->name('promos.destroy');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/checkout/course/{course}', [CourseCheckoutController::class, 'show'])
        ->name('checkout.course.show');
    Route::post('/checkout/course/{course}', [CourseCheckoutController::class, 'apply'])
        ->name('checkout.course.apply');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/student/dashboard', DashboardController::class)->name('student.dashboard');
});

Route::middleware(['auth'])
    ->prefix('student')
    ->name('student.')
    ->group(function () {
        Route::get('/courses/{course}', [\App\Http\Controllers\Student\CourseController::class, 'show'])
            ->name('courses.show');

        // Страница урока для студента
        Route::get('/lessons/{lesson}', [StudentLessonController::class, 'show'])
            ->name('lessons.show');
    });

// Сдача домашки студентом
Route::middleware(['auth'])
    ->prefix('student')
    ->name('student.')
    ->group(function () {
        Route::get('/homeworks/{homework}/submit', [StudentSubmissionController::class, 'create'])
            ->name('submissions.create');
        Route::post('/homeworks/{homework}/submit', [StudentSubmissionController::class, 'store'])
            ->name('submissions.store');
    });

// Проверка домашних ментором
Route::middleware(['auth', 'mentor'])
    ->prefix('mentor')
    ->name('mentor.')
    ->group(function () {
        Route::get('/submissions', [MentorSubmissionController::class, 'index'])->name('submissions.index');
        Route::get('/submissions/{submission}', [MentorSubmissionController::class, 'show'])->name('submissions.show');
        Route::post('/submissions/{submission}', [MentorSubmissionController::class, 'update'])->name('submissions.update');
    });

Route::middleware(['auth'])->group(function () {
Route::get('/student/submissions/{submission}', [SubmissionController::class, 'show'])
        ->name('student.submissions.show');
});


// ==== РЕВЬЮ КУРАТОРА ====
use App\Http\Controllers\Mentor\SubmissionReviewController;


Route::prefix('mentor/review')
    ->name('mentor.review.')
    ->middleware(['auth','mentor'])
    ->group(function () {
        Route::get('/inbox', [SubmissionReviewController::class, 'inbox'])->name('inbox');
        Route::get('/{submission}', [SubmissionReviewController::class, 'show'])->name('show');

        // СНАЧАЛА — специфичные
        Route::post('/{submission}/task/{taskId}/regen', [ReviewAiController::class, 'regen'])
            ->where('taskId', '[^/]+')
            ->name('task.regen');

        Route::post('/{submission}/task/{taskId}/skip', [SubmissionReviewController::class, 'skipTask'])
            ->where('taskId', '[^/]+')
            ->name('task.skip');

        Route::post('/{submission}/task/{taskId}/unskip', [SubmissionReviewController::class, 'unskipTask'])
            ->where('taskId', '[^/]+')
            ->name('task.unskip');

        // ПОТОМ — общий
        Route::post('/{submission}/task/{taskId}', [SubmissionReviewController::class, 'saveTask'])
            ->where('taskId', '[^/]+')
            ->name('task.save');

        Route::post('/{submission}/finish', [SubmissionReviewController::class, 'finish'])->name('finish');
        Route::post('/{submission}/finish-and-next', [SubmissionReviewController::class, 'finishAndNext'])->name('finish_next');
    });


use App\Http\Controllers\Admin\TaskController;

// --- Банк заданий (только админ) ---
Route::prefix('admin/tasks')->middleware(['auth'])->group(function () {

    Route::get('/',          [TaskController::class, 'index'])->name('admin.tasks.index');
    Route::get('/create',    [TaskController::class, 'create'])->name('admin.tasks.create');
    Route::post('/',         [TaskController::class, 'store'])->name('admin.tasks.store');

    Route::get('/{task}',    [TaskController::class, 'show'])->name('admin.tasks.show');
    Route::get('/{task}/edit', [TaskController::class, 'edit'])->name('admin.tasks.edit');
    Route::put('/{task}',    [TaskController::class, 'update'])->name('admin.tasks.update');

    // Публикация / Архивация
    Route::post('/{task}/publish', [TaskController::class, 'publish'])->name('admin.tasks.publish');
    Route::post('/{task}/archive', [TaskController::class, 'archive'])->name('admin.tasks.archive');
});

// Route::get('/admin/courses/{course}/tasks', function(\App\Models\Course $course) {
//     return $course->category
//         ? $course->category->tasks()->select('id','number')->orderBy('number')->get()
//         : [];
// });

use App\Http\Controllers\Admin\CourseTaskController;

Route::middleware(['auth'])->prefix('admin')->group(function () {
    Route::get('/courses/{course}/tasks', [CourseTaskController::class, 'index'])
        ->name('admin.courses.tasks');
});


use Illuminate\Support\Facades\Http;

// Route::middleware(['auth', 'mentor']) // твой MentorMiddleware даёт доступ ментору/админу
//     ->prefix('mentor/review')
//     ->name('mentor.review.')
//     ->group(function () {
//         Route::post('/{submission}/{taskId}/regen', [ReviewAiController::class, 'regen'])
//             ->where('taskId', '.*')
//             ->name('task.regen');
//     });


// Route::get('/dev/ip', function () {
//     $proxy = (string) config('openai.proxy', '');
//     $options = ['verify' => false];
//     if ($proxy !== '') {
//         $options['proxy'] = ['http' => $proxy, 'https' => $proxy];
//         $options['curl']  = [CURLOPT_HTTPPROXYTUNNEL => 1];
//     }
//     return Http::withOptions($options)->get('http://api.ipify.org')->body();
// });

use App\Http\Controllers\Auth\PhoneAuthController;
use App\Http\Controllers\Auth\EmailAuthController;
use App\Http\Controllers\Onboarding\ProfileController;

// Телефонный вход/регистрация (публичные)
Route::get('/auth/phone', [PhoneAuthController::class, 'showPhoneForm'])->name('auth.phone.show');
Route::post('/auth/phone', [PhoneAuthController::class, 'sendCode'])->name('auth.phone.send');
Route::get('/auth/phone/verify', [PhoneAuthController::class, 'showVerifyForm'])->name('auth.phone.verify.show');
Route::post('/auth/phone/verify', [PhoneAuthController::class, 'verifyCode'])->name('auth.phone.verify');
Route::post('/auth/phone/resend', [PhoneAuthController::class, 'resend'])->name('auth.phone.resend');

// Онбординг (только для аутентифицированных)
Route::middleware('auth')->group(function () {
    Route::get('/onboarding/profile', [ProfileController::class, 'show'])->name('onboarding.profile.show');
    Route::post('/onboarding/profile', [ProfileController::class, 'save'])->name('onboarding.profile.save');
});

// E-mail вход/регистрация (гостевые)
Route::middleware('guest')->group(function () {
    // Шаг 1: форма e-mail
    Route::get('/auth/email', [EmailAuthController::class, 'showEmailForm'])->name('auth.email.show');
    Route::post('/auth/email', [EmailAuthController::class, 'send'])->name('auth.email.send');

    // Шаг 2А: ввод кода из письма
    Route::get('/auth/email/verify', [EmailAuthController::class, 'showVerifyForm'])->name('auth.email.verify.show');
    Route::post('/auth/email/verify', [EmailAuthController::class, 'verify'])->name('auth.email.verify');

    // Повторная отправка
    Route::post('/auth/email/resend', [EmailAuthController::class, 'resend'])->name('auth.email.resend');

    // Шаг 2Б: подтверждение по подписанной ссылке
    Route::get('/auth/email/link/{id}', [EmailAuthController::class, 'verifyByLink'])
        ->middleware(['signed','throttle:6,1'])
        ->name('auth.email.link');
});



Route::get('/dev/ip-https', function () {
    return Http::withOptions([
        'verify' => false, // только для теста
        'curl' => [CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4],
    ])->get('https://api.ipify.org')->body();
});




// Auth::routes(['verify' => true]);

// Выключаем штатную /register, оставляем остальное (login, reset, verify)
Auth::routes(['verify' => true, 'register' => false]);

// На всякий случай перенаправим старые ссылки на наш новый поток
Route::get('/register', fn() => redirect()->route('auth.email.show'))->name('register');

use App\Http\Controllers\HomeRedirectController;

Route::get('/home', HomeRedirectController::class)
    ->middleware(['auth','verified'])
    ->name('home');

    use App\Http\Controllers\LeadController;

Route::post('/lead', [LeadController::class, 'store'])
    ->name('lead.store')
    ->middleware('throttle:10,1'); // базовая защита от спама

Route::view('/thank-you', 'thank-you')->name('thankyou');

