<?php

namespace App\Http\Controllers\Promo;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Course;
use App\Models\PromoCode;
use App\Models\PromoRedemption;
use App\Service\EnrollmentService;
use App\Service\Pricing\PromoLookup;
use Illuminate\Support\Facades\DB;

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

        $user = $request->user();

        // Один и тот же access-промокод раньше можно было применить сколько
        // угодно раз одному пользователю — каждый раз продлевая доступ и
        // тратя общий max_uses, рассчитанный на разных студентов.
        if (PromoRedemption::where('promo_code_id', $promo->id)->where('user_id', $user->id)->exists()) {
            return back()->withErrors(['code' => 'Вы уже использовали этот промокод.'])->withInput();
        }

        // Срок доступа
        $expiresAt = now()->addDays($promo->duration_days);

        try {
            DB::transaction(function () use ($promo, $course, $user, $expiresAt, $enroll) {
                // Перечитываем и блокируем строку промокода — без этого два
                // параллельных запроса могли пройти проверку max_uses в
                // PromoLookup::find() (выполненную до транзакции) от одного и
                // того же used_count и оба применить код, превысив лимит.
                $locked = PromoCode::whereKey($promo->id)->lockForUpdate()->first();

                if (!is_null($locked->max_uses) && $locked->used_count >= $locked->max_uses) {
                    throw new \DomainException('Достигнут лимит использований промокода');
                }

                $enroll->enrollUser($user, $course, [
                    'status'      => 'active',
                    'enrolled_at' => now(),
                    'expires_at'  => $expiresAt,
                    'source'      => 'promo',
                    'promo_code'  => $locked->code,
                ]);

                $locked->increment('used_count');

                // Уникальный индекс [promo_code_id, user_id] — вторая линия
                // защиты от повторного применения тем же пользователем на
                // случай гонки между проверкой exists() выше и этой записью.
                PromoRedemption::create([
                    'promo_code_id' => $locked->id,
                    'user_id'       => $user->id,
                    'course_id'     => $course->id,
                    'enrolled_at'   => now(),
                    'expires_at'    => $expiresAt,
                ]);
            });
        } catch (\Illuminate\Database\QueryException $e) {
            if ((string) $e->getCode() === '23000') {
                return back()->withErrors(['code' => 'Вы уже использовали этот промокод.'])->withInput();
            }
            throw $e;
        } catch (\DomainException $e) {
            return back()->withErrors(['code' => $e->getMessage()])->withInput();
        }

        return redirect()->route('student.dashboard')
            ->with('success', "Доступ к «{$course->title}» активирован до {$expiresAt->format('d.m.Y H:i')}");
    }
}
