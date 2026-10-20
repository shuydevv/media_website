<?php

namespace App\Http\Controllers\Mentor;

use App\Http\Controllers\Controller;
use App\Models\AiGradingBatch;
use App\Models\User;
use App\Service\Homework\DesktopGradingExchange;
use App\Service\Homework\DesktopGradingSkill;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * ИИ-проверка через Claude Desktop (см. DesktopGradingExchange): выгрузка
 * порции заданий, повторное скачивание / отмена выгрузки, загрузка
 * результата и скачивание скилла. Только админ — выгрузка забирает задания
 * из очереди всех кураторов.
 */
class AiDesktopController extends Controller
{
    public function export(Request $request, DesktopGradingExchange $exchange)
    {
        $this->assertAdmin($request);
        $subject = $request->string('subject')->trim()->value() ?: null;

        $export = $exchange->export($request->user()->id, $subject);
        if (!$export) {
            return back()->with('success', 'Нет новых письменных заданий для выгрузки.');
        }

        return $this->download($export);
    }

    public function redownload(Request $request, AiGradingBatch $batch, DesktopGradingExchange $exchange)
    {
        $this->assertAdmin($request);
        $this->assertOpen($batch);

        $export = $exchange->redownload($batch);
        if (!$export) {
            return back()->with('error', "В выгрузке {$batch->external_id} не осталось заданий, ждущих проверки.");
        }

        return $this->download($export);
    }

    public function cancel(Request $request, AiGradingBatch $batch, DesktopGradingExchange $exchange)
    {
        $this->assertAdmin($request);
        $this->assertOpen($batch);

        $returned = $exchange->cancel($batch);

        return back()->with('success', "Выгрузка {$batch->external_id} отменена, в очередь вернулось заданий: {$returned}.");
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

        // Карточка отчёта на странице очереди (index.blade.php, ai_report).
        return back()->with('ai_report', $report);
    }

    public function skill(Request $request, DesktopGradingSkill $skill)
    {
        $this->assertAdmin($request);

        return response()->download($skill->build(), DesktopGradingSkill::NAME . '.zip')->deleteFileAfterSend();
    }

    private function download(array $export)
    {
        return response()->streamDownload(
            fn () => print($export['json']),
            $export['filename'],
            ['Content-Type' => 'application/json; charset=utf-8'],
        );
    }

    private function assertOpen(AiGradingBatch $batch): void
    {
        abort_unless(
            $batch->channel === AiGradingBatch::CHANNEL_DESKTOP && $batch->status === 'in_progress',
            404,
            'Выгрузка не найдена или уже закрыта.'
        );
    }

    private function assertAdmin(Request $request): void
    {
        abort_unless((int) $request->user()?->role === User::ROLE_ADMIN, 403, 'Только для администратора.');
    }
}
