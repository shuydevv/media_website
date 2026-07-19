<?php

namespace App\Http\Controllers\Promo;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Course;
use App\Models\PromoRedemption;
use App\Service\EnrollmentService;
use App\Service\Pricing\PromoLookup;

class RedeemController extends Controller
{
    // Показ формы
    public function form(Request $request)
    {
        // Если коды могут быть «для любого курса», передадим список курсов в форму
        $courses = \App\Models\Course::orderBy('title')->get();
        return view('promo.redeem', compact('courses'));
    }

    // Активация
    public function redeem(Request $request, EnrollmentService $enroll)
    {

        $data = $request->validate([
            'code'      => ['required','string','exists:promo_codes,code'],
            'course_id' => ['nullable','integer','exists:courses,id'],
        ]);

        [$promo, $error] = PromoLookup::find(
            $data['code'],
            null,
            'access',
            'Этот промокод даёт скидку и применяется при оплате, а не здесь.'
        );

        if (!$promo) {
            return back()->withErrors(['code' => $error])->withInput();
        }

        // Определяем курс: либо привязан к промокоду, либо из формы
        $course = $promo->course_id
            ? Course::find($promo->course_id)
            : (isset($data['course_id']) ? Course::find($data['course_id']) : null);

        if (!$course) {
            return back()->withErrors(['code' => 'Курс не указан или не найден'])->withInput();
        }

        // Срок доступа
        $expiresAt = now()->addDays($promo->duration_days);

        // Зачисляем через единый сервис
        $enroll->enrollUser($request->user(), $course, [
            'status'      => 'active',
            'enrolled_at' => now(),
            'expires_at'  => $expiresAt,
            'source'      => 'promo',
            'promo_code'  => $promo->code,
        ]);

        // Фиксируем использование и редемпшн
        $promo->increment('used_count');
        PromoRedemption::create([
            'promo_code_id' => $promo->id,
            'user_id'       => $request->user()->id,
            'course_id'     => $course->id,
            'enrolled_at'   => now(),
            'expires_at'    => $expiresAt,
        ]);

        return redirect()->route('student.dashboard')
            ->with('success', "Доступ к «{$course->title}» активирован до {$expiresAt->format('d.m.Y H:i')}");
    }
}
