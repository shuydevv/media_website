<?php

namespace App\Http\Controllers\Admin\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\User\StoreRequest;
use App\Models\Course;
use App\Models\User;
use App\Service\EnrollmentService;
use App\Service\UserInviteService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class StoreController extends Controller
{
    public function __invoke(StoreRequest $request, EnrollmentService $enroll, UserInviteService $invite) {
        $data = $request->validated();

        $courseIds = $data['course_ids'] ?? [];
        $accessUntil = $data['access_until'] ?? [];
        unset($data['course_ids'], $data['access_until']);

        // "name" здесь — логин в телеграме (то же поле, что на онбординге
        // ученика), не полное имя. Админ может знать его заранее и указать
        // сразу; если нет — временно подставляем ФИО, чтобы поле не было
        // пустым в списках, ученик поправит на онбординге.
        $data['name'] = $data['name'] ?: trim($data['first_name'].' '.($data['last_name'] ?? ''));
        // Ученик никогда не видит и не вводит этот пароль — вход только по
        // ссылке-приглашению (UserInviteService), минимум действий для него.
        // Случайная строка нужна только потому, что колонка password NOT NULL.
        $data['password'] = Hash::make(Str::random(32));
        // Отличает реального лида, заведённого админом, от того, кто сам
        // пришёл через публичную регистрацию — используется, чтобы не
        // показывать в /admin/crm неподтверждённых самозарегистрировавшихся
        // (боты и бросившие на середине), см. User::scopeVisibleInCrm().
        $data['created_by_admin_id'] = auth()->id();

        $user = User::firstOrCreate(['email' => $data['email']], $data);

        // Каждый курс — своя дата: одному могут дать месяц математики, а
        // физику продлить на другой срок, это не всегда один и тот же день.
        foreach ($courseIds as $courseId) {
            $course = Course::findOrFail($courseId);
            $enroll->enrollUser($user, $course, [
                'source' => 'manual',
                'expires_at' => Carbon::parse($accessUntil[$courseId])->endOfDay(),
            ]);
        }

        $inviteUrl = $invite->send($user);

        // На edit, а не на index — сразу видно ссылку-приглашение, чтобы
        // при необходимости скопировать и отправить вручную (письмо могло
        // не дойти/уйти в спам), не отдельным походом на страницу ученика.
        return redirect()->route('admin.user.edit', $user)->with('inviteUrl', $inviteUrl);
    }
}
