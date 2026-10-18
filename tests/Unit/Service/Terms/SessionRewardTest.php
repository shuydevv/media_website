<?php

namespace Tests\Unit\Service\Terms;

use App\Service\Terms\SessionReward;
use PHPUnit\Framework\TestCase;

class SessionRewardTest extends TestCase
{
    private function reward(): SessionReward
    {
        return new SessionReward((require __DIR__.'/../../../../config/terms.php')['corm']);
    }

    /** @test */
    public function corm_depends_on_the_share_answered_right_first_time()
    {
        $reward = $this->reward();

        $this->assertSame(3, $reward->corm(20, 20));
        $this->assertSame(2, $reward->corm(19, 20));
        $this->assertSame(2, $reward->corm(16, 20)); // ровно 80%
        $this->assertSame(1, $reward->corm(15, 20));
        $this->assertSame(1, $reward->corm(12, 20)); // ровно 60%
        $this->assertSame(0, $reward->corm(11, 20));
        $this->assertSame(0, $reward->corm(0, 20));
    }

    /** @test */
    public function small_sessions_use_the_same_shares()
    {
        $reward = $this->reward();

        $this->assertSame(2, $reward->corm(4, 5));
        $this->assertSame(1, $reward->corm(3, 5));
        $this->assertSame(0, $reward->corm(1, 2));
        $this->assertSame(3, $reward->corm(1, 1));
    }

    /** @test */
    public function an_empty_session_gives_nothing()
    {
        $this->assertSame(0, $this->reward()->corm(0, 0));
    }
}
