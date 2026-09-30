<?php

namespace Tests\Unit\Support\PostContent;

use App\Models\Image;
use App\Support\BladeContent;
use App\Support\PostContent\ContentRenderer;
use Illuminate\Support\Collection;
use Tests\TestCase;

class ContentRendererTest extends TestCase
{
    private function images(): Collection
    {
        return collect([
            new Image(['name' => 'images/aaa.jpg', 'original_name' => 'kalka-1.jpg']),
            new Image(['name' => 'images/bbb.jpg', 'original_name' => 'Kalka-2.JPG']),
            new Image(['name' => 'images/ccc.jpg', 'original_name' => 'kalka-3.jpg']),
        ]);
    }

    private function render(string $content): string
    {
        return (string) (new ContentRenderer())->render($content, $this->images());
    }

    /** @test */
    public function image_is_found_by_file_name_without_extension()
    {
        $html = $this->render('<x-img src="kalka-3" description="Река" />');

        $this->assertStringContainsString('storage/images/ccc.jpg', $html);
        $this->assertStringContainsString('Река', $html);
    }

    /** @test */
    public function image_lookup_by_name_ignores_case_and_accepts_extension()
    {
        $this->assertStringContainsString('images/bbb.jpg', $this->render('<x-img src="kalka-2" description="" />'));
        $this->assertStringContainsString('images/bbb.jpg', $this->render('<x-img src="kalka-2.jpg" description="" />'));
    }

    /** @test */
    public function legacy_numeric_index_still_works()
    {
        $html = $this->render('<x-person img="1" title="Калка" description="Река" />');

        $this->assertStringContainsString('images/bbb.jpg', $html);
    }

    /** @test */
    public function missing_image_renders_caption_without_img_tag()
    {
        $html = $this->render('<x-img src="nope" description="Подпись" />');

        $this->assertStringContainsString('Подпись', $html);
        $this->assertStringNotContainsString('<img', $html);
    }

    /** @test */
    public function straight_quotes_inside_text_do_not_break_the_tag_or_its_neighbours()
    {
        $html = $this->render(
            '<x-text text="героем "Слова о полку Игореве". Однако" />' . "\n" .
            '<x-img src="kalka-1" description="Карта" />'
        );

        $this->assertStringContainsString('героем «Слова о полку Игореве». Однако', $html);
        $this->assertStringContainsString('images/aaa.jpg', $html);
        $this->assertStringNotContainsString('<x-', $html);
    }

    /** @test */
    public function blade_syntax_in_content_is_not_executed()
    {
        $html = $this->render('<x-text text="{{ 1 + 1 }}" /> @php echo "pwned"; @endphp {{ 2 + 2 }}');

        // Всё выводится как текст, ничего не вычисляется.
        $this->assertStringContainsString('{{ 1 + 1 }}', $html);
        $this->assertStringContainsString('@php echo "pwned"; @endphp {{ 2 + 2 }}', $html);
    }

    /** @test */
    public function attribute_values_are_escaped()
    {
        $html = $this->render('<x-text text="<script>alert(1)</script>" />');

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    /** @test */
    public function unknown_components_are_dropped()
    {
        $html = $this->render('до <x-footer /> после');

        $this->assertStringNotContainsString('x-footer', $html);
        $this->assertStringContainsString('до', $html);
        $this->assertStringContainsString('после', $html);
    }

    /** @test */
    public function nested_list_is_rendered_into_slot_and_plain_html_passes_through()
    {
        $html = $this->render('<p>Абзац <a href="/x">ссылка</a></p><x-ul text=""><x-li text="один" /><x-li text="два" /></x-ul>');

        $this->assertStringContainsString('<p>Абзац <a href="/x">ссылка</a></p>', $html);
        $this->assertMatchesRegularExpression('/<ul[^>]*>.*один.*два.*<\/ul>/s', $html);
    }

    /** @test */
    public function blade_content_normalizer_turns_inner_quotes_into_guillemets()
    {
        $fixed = BladeContent::fixAttributeQuotes('<x-text text="как "Битва трёх Мстиславов", так" /><x-img img="1" description="d"/>');

        $this->assertSame('<x-text text="как «Битва трёх Мстиславов», так" /><x-img img="1" description="d" />', $fixed);
    }
}
