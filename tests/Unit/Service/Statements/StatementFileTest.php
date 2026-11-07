<?php

namespace Tests\Unit\Service\Statements;

use App\Service\Statements\StatementImporter;
use Tests\TestCase;

/** Проверки файла базы суждений и правил приёмки (БД не нужна, нужен config). */
class StatementFileTest extends TestCase
{
    private function row(array $overrides = []): array
    {
        return array_merge([
            'id' => 'eh-1-1',
            'block' => 'law',
            'about' => 'о праве',
            'text' => 'Право — система норм.',
            'is_true' => true,
            'explanation' => 'Так и есть.',
            'correction' => null,
            'status' => 'active',
        ], $overrides);
    }

    /** @test */
    public function the_shipped_file_is_accepted()
    {
        $data = json_decode(file_get_contents(base_path(config('statements.path'))), true);
        $this->assertSame([], StatementImporter::validate($data));
        $this->assertGreaterThan(1000, count(array_filter($data['statements'], fn ($s) => $s['status'] === 'active')));
    }

    /** @test */
    public function bad_rows_are_rejected()
    {
        $errors = StatementImporter::validate(['statements' => [
            $this->row(),
            $this->row(),                                            // id повторяется
            $this->row(['id' => 'a', 'block' => 'history']),         // нет такого блока
            $this->row(['id' => 'b', 'is_true' => 'yes']),           // не bool
            $this->row(['id' => 'c', 'explanation' => null]),        // active без пояснения
            $this->row(['id' => 'd', 'status' => 'draft']),          // нет такого статуса
        ]]);

        $this->assertCount(5, $errors);
    }

    /** @test */
    public function a_statement_without_explanation_is_allowed_if_not_active()
    {
        $this->assertSame([], StatementImporter::validate(['statements' => [
            $this->row(['explanation' => null, 'status' => 'no_explanation']),
        ]]));
    }
}
