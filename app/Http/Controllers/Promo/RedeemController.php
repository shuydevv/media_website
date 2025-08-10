<?php

namespace App\Http\Controllers\Promo;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Course;
use App\Models\PromoCode;
use App\Models\PromoRedemption;
use App\Service\EnrollmentService;

class RedeemController extends Controller
{
    public function __invoke(Request $request, EnrollmentService $enroll)
    {
        $user = $request->user();
        $code = trim((string) $request->query('code', ''));

        if ($code === '') {
            return redirect()->route('main.index')->withErrors(['code' => 'Не указан промокод']);
        }

        $promo = PromoCode::query()->where('code', $code)->first();

        if (!$promo || !$promo->is_active) {
            return redirect()->route('main.index')->withErrors(['code' => 'Промокод не найден или неактивен']);
        }
        if ($promo->starts_at && now()->lt($promo->starts_at)) {
            return redirect()->route('main.index')->withErrors(['code' => 'Промокод ещё не начал действовать']);
        }
        if ($promo->ends_at && now()->gt($promo->ends_at)) {
            return redirect()->route('main.index')->withErrors(['code' => 'Срок действия промокода истёк']);
        }
        if (!is_null($promo->max_uses) && $promo->used_count >= $promo->max_uses) {
            return redirect()->route('main.index')->withErrors(['code' => 'Достигнут лимит использований промокода']);
        }

        // Определяем курс: либо привязан к промо, либо из ?course_id=...
        $course = $promo->course_id
            ? Course::find($promo->course_id)
            : ($request->filled('course_id') ? Course::find((int)$request->query('course_id')) : null);

        if (!$course) {
            return redirect()->route('main.index')->withErrors(['code' => 'Курс не указан или не найден']);
        }

        // Срок доступа
        $expiresAt = now()->addDays($promo->duration_days);

        // Зачисляем через единый сервис
        $enroll->enrollUser($user, $course, [
            'status'      => 'active',
            'enrolled_at' => now(),
            'expires_at'  => $expiresAt,
            'source'      => 'promo',
            'promo_code'  => $promo->code,
        ]);

        // Фиксируем использование промокода и сам редемпшн
        $promo->increment('used_count');

        PromoRedemption::create([
            'promo_code_id' => $promo->id,
            'user_id'       => $user->id,
            'course_id'     => $course->id,
            'enrolled_at'   => now(),
            'expires_at'    => $expiresAt,
        ]);

        return redirect()
            ->route('main.index')
            ->with('success', "Доступ к курсу «{$course->title}» активирован до {$expiresAt->format('d.m.Y H:i')}");
    }
}
