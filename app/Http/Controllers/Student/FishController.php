<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Service\FishFoodService;
use Illuminate\Http\Request;

class FishController extends Controller
{
    public function feed(Request $request, FishFoodService $fish)
    {
        $user = $request->user();
        $result = $fish->feed($user);

        $fishLevel = $fish->levelFor((int) $user->fish_total_fed);
        $fishProgress = $fish->progressFor((int) $user->fish_total_fed);
        $fishBalance = (int) $user->fish_corm_balance;
        $fishName = $user->fish_name ?: $fish->levelName($fishLevel);

        $html = view('student.partials.fish-card', compact('fishLevel', 'fishProgress', 'fishBalance', 'fishName'))->render();

        if ($this->isHtmx($request)) {
            $response = response($html);

            // Не общий 'toast' (он всплывает по центру экрана) — левел-ап
            // должен привлекать внимание к самому маскоту, не отвлекать в
            // сторону. Отдельное событие ловит выделенный обработчик в
            // dashboard.blade.php, который рисует ВАУ-анимацию и баннер прямо
            // в рамке маскота.
            //
            // Картинки нового уровня передаются в детали события, а не через
            // отдельный OOB-свап #fish-mascot-img: OOB подменяет DOM-узел
            // мгновенно и никак не согласован по времени с запуском JS-
            // обработчика триггера — картинка успевала смениться раньше, чем
            // начиналась анимация, поэтому появление выглядело мгновенным.
            // Теперь один и тот же узел живёт всё время, а src меняет сам
            // GSAP-таймлайн ровно в момент, когда картинка уже скрыта
            // (scale:0) — подмена невидима, дальше её проявляет анимация.
            if ($result['leveled_up']) {
                $response->headers->set('HX-Trigger', json_encode([
                    'fish-level-up' => [
                        'defaultSrc' => $fish->mascotImageUrl($fishLevel),
                        'eatingSrc' => $fish->mascotImageUrl($fishLevel, 'eating'),
                    ],
                ]));
            }

            return $response;
        }

        return redirect()->route('student.dashboard');
    }

    private function isHtmx(Request $request): bool
    {
        return $request->header('HX-Request') === 'true';
    }
}
