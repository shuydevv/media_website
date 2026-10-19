<?php

namespace Tests\Unit\Service\Telegram;

use App\Service\Telegram\StartParam;
use PHPUnit\Framework\TestCase;

class StartParamTest extends TestCase
{
    private function parse(?string $param): array
    {
        return StartParam::parse($param, ['cho', 'er'], 'cho');
    }

    /** @test */
    public function lesson_code_and_ad_tag_are_split_on_the_first_underscore()
    {
        $this->assertSame(['lesson' => 'er', 'source' => 'vk_ad1'], $this->parse('er_vk_ad1'));
        $this->assertSame(['lesson' => 'cho', 'source' => 'tiktok'], $this->parse('cho_tiktok'));
    }

    /** @test */
    public function lesson_code_is_found_anywhere_and_in_any_case()
    {
        $this->assertSame(['lesson' => 'er', 'source' => 'vk1'], $this->parse('vk1_er'));
        $this->assertSame(['lesson' => 'er', 'source' => 'vk_1'], $this->parse('vk_er_1'));
        $this->assertSame(['lesson' => 'er', 'source' => 'vk1'], $this->parse('ER_VK1'));
        $this->assertSame(['lesson' => 'cho', 'source' => 'tiktok-ad'], $this->parse('tiktok-cho-ad'));
        $this->assertSame(['lesson' => 'er', 'source' => 'vk'], $this->parse('vk-er'));
    }

    /** @test */
    public function code_inside_a_word_is_not_a_lesson()
    {
        $this->assertSame(['lesson' => 'cho', 'source' => 'summer_vk'], $this->parse('summer_vk'));
        $this->assertSame(['lesson' => 'cho', 'source' => 'echo'], $this->parse('echo'));
    }

    /** @test */
    public function tag_is_lowercased()
    {
        $this->assertSame(['lesson' => 'cho', 'source' => 'vk_ad1'], $this->parse('VK_Ad1'));
    }

    /** @test */
    public function bare_lesson_code_has_no_tag()
    {
        $this->assertSame(['lesson' => 'er', 'source' => null], $this->parse('er'));
        $this->assertSame(['lesson' => 'er', 'source' => null], $this->parse('er_'));
    }

    /** @test */
    public function unknown_prefix_is_kept_whole_as_the_tag_with_default_lesson()
    {
        $this->assertSame(['lesson' => 'cho', 'source' => 'vk_ad1'], $this->parse('vk_ad1'));
        $this->assertSame(['lesson' => 'cho', 'source' => 'erx_ad'], $this->parse('erx_ad'));
    }

    /** @test */
    public function empty_or_garbage_param_gives_default_lesson_without_tag()
    {
        $this->assertSame(['lesson' => 'cho', 'source' => null], $this->parse(null));
        $this->assertSame(['lesson' => 'cho', 'source' => null], $this->parse('   '));
        $this->assertSame(['lesson' => 'cho', 'source' => null], $this->parse('<>!'));
    }

    /** @test */
    public function tag_is_cut_to_64_chars()
    {
        $this->assertSame(64, strlen($this->parse(str_repeat('a', 100))['source']));
    }
}
