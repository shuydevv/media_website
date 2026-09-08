<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<\Throwable>, \Psr\Log\LogLevel::*>
     */
    protected $levels = [
        //
    ];

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        // Ссылки-подтверждения (auth.email.link, auth.invite.link) подписаны с TTL
        // (см. VerifyEmailWithCode::toMail()) — если по ссылке переходят повторно
        // после истечения срока (например, ученик кликнул её со второго устройства,
        // не будучи залогиненным, спустя больше часа), Laravel по умолчанию кидает
        // голую 403-страницу вместо понятного поведения. Отправляем на главную.
        $this->renderable(function (InvalidSignatureException $e, Request $request) {
            if ($request->expectsJson()) {
                return null;
            }

            return redirect()->route('index')
                ->with('status', 'Ссылка недействительна или уже устарела.');
        });
    }
}
