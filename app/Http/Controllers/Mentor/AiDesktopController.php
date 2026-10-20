<?php

namespace App\Http\Controllers\Mentor;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Service\Homework\DesktopGradingExchange;
use App\Service\Homework\DesktopGradingSkill;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * ИИ-проверка через Claude Desktop (см. DesktopGradingExchange): выгрузка
 * порции заданий, загрузка результата и скачивание скилла. Только админ —
 * выгрузка забирает задания из очереди всех кураторов.
 */
class AiDesktopController extends Controller
{
    public function export(Request $request, DesktopGradingExchange $exchange)
    {
        $this->assertAdmin($request);

        $export = $exchange->export($request->user()->id);
        if (!$export) {
            return back()->with('success', 'Нет новых письменных заданий для выгрузки.');
        }

        return response()->streamDownload(
            fn () => print($export['json']),
            $export['filename'],
            ['Content-Type' => 'application/json; charset=utf-8'],
        );
    }

    public function import(Request $request, DesktopGradingExchange $exchange)
    {
        $this->assertAdmin($request);
        $request->validate(['result' => ['required', 'file', 'max:10240']]);

        try {
            $report = $exchange->import((string) file_get_contents($request->file('result')->getRealPath()));
        } catch (\Throwable $e) {
            Log::warning('Desktop grading import failed', ['error' => $e->getMessage()]);
            return back()->with('error', 'Результат не загружен: ' . $e->getMessage());
        }

        $message = "Загружено черновиков: {$report['imported']} (выгрузка {$report['export_id']}).";
        if ($report['missing']) {
            $message .= ' Не вернулись и попадут в следующую выгрузку: ' . implode(', ', $report['missing']) . '.';
        }
        if ($report['skipped']) {
            $message .= ' Пропущено: ' . implode('; ', $report['skipped']) . '.';
        }

        return back()->with('success', $message);
    }

    public function skill(Request $request, DesktopGradingSkill $skill)
    {
        $this->assertAdmin($request);

        return response()->download($skill->build(), DesktopGradingSkill::NAME . '.zip')->deleteFileAfterSend();
    }

    private function assertAdmin(Request $request): void
    {
        abort_unless((int) $request->user()?->role === User::ROLE_ADMIN, 403, 'Только для администратора.');
    }
}
