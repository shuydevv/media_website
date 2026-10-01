<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\Section;
use App\Models\Tag;
use App\Models\Topic;
use App\Models\User;
use Tests\TestCase;

/**
 * Планы ЕГЭ (посты с тегом «Планы») вынесены из ленты /posts на /plans,
 * сгруппированы по разделам/темам, старые адреса редиректят.
 *
 * БД реальная (см. Admin\TaskControllerValidationTest) — созданное
 * удаляется в tearDown().
 */
class PlansTest extends TestCase
{
    private array $posts = [];
    private array $topics = [];
    private array $sections = [];
    private ?Tag $createdPlanTag = null;

    protected function tearDown(): void
    {
        foreach ($this->posts as $post) {
            $post->tags()->detach();
            $post->forceDelete();
        }
        Topic::whereIn('id', $this->topics)->delete();
        Section::whereIn('id', $this->sections)->delete();
        $this->createdPlanTag?->forceDelete();
        parent::tearDown();
    }

    private function planTag(): Tag
    {
        $tag = Tag::where('title', Post::PLAN_TAG)->first();
        if (!$tag) {
            $tag = $this->createdPlanTag = Tag::create(['title' => Post::PLAN_TAG]);
        }

        return $tag;
    }

    private function topic(string $sectionTitle, string $topicTitle): Topic
    {
        $section = Section::create(['title' => $sectionTitle]);
        $this->sections[] = $section->id;
        $topic = Topic::create(['title' => $topicTitle, 'section_id' => $section->id]);
        $this->topics[] = $topic->id;

        return $topic;
    }

    private function makePost(string $title, bool $plan, ?Topic $topic = null): Post
    {
        $category = Category::first();
        $this->assertNotNull($category, 'В БД нет ни одной категории.');

        $post = Post::create([
            'title' => $title,
            'content' => '<x-text text="x" />',
            'category_id' => $category->id,
            'topic_id' => $topic?->id,
            'path' => 'plans-test-' . uniqid(),
        ]);
        if ($plan) {
            $post->tags()->attach($this->planTag()->id);
        }

        return $this->posts[] = $post->fresh();
    }

    /** @test */
    public function posts_feed_hides_plans_and_links_to_the_plans_page()
    {
        $this->makePost('ТестСтатья ' . uniqid(), false);
        $plan = $this->makePost('ТестПлан ' . uniqid(), true);

        $this->get(route('post.index'))
            ->assertOk()
            ->assertDontSee($plan->title)
            ->assertSee(route('plan.index'), false);
    }

    /** @test */
    public function plans_page_groups_plans_by_section_and_topic()
    {
        $economy = $this->topic('ТестРаздел Экономика', 'ТестТема Рынок');
        $law = $this->topic('ТестРаздел Право', 'ТестТема Конституция');
        $this->makePost('ТестПлан Конституция РФ', true, $law);
        $this->makePost('ТестПлан Рыночная экономика', true, $economy);
        $this->makePost('ТестПлан Без темы', true);
        $article = $this->makePost('ТестСтатья не план', false, $economy);

        $this->get(route('plan.index'))
            ->assertOk()
            ->assertSeeInOrder([
                'ТестРаздел Экономика', 'ТестТема Рынок', 'ТестПлан Рыночная экономика',
                'ТестРаздел Право', 'ТестТема Конституция', 'ТестПлан Конституция РФ',
                'Другие планы', 'ТестПлан Без темы',
            ])
            ->assertDontSee($article->title);
    }

    /** @test */
    public function old_post_address_of_a_plan_redirects_permanently_and_back()
    {
        $plan = $this->makePost('ТестПлан редирект', true);
        $article = $this->makePost('ТестСтатья редирект', false);

        $this->get(route('post.show', ['post' => $plan->path]))
            ->assertStatus(301)
            ->assertRedirect(route('plan.show', ['post' => $plan->path]));

        $this->get(route('plan.show', ['post' => $article->path]))
            ->assertStatus(301)
            ->assertRedirect(route('post.show', ['post' => $article->path]));

        $this->assertSame(route('plan.show', ['post' => $plan->path]), $plan->url);
        $this->assertSame(route('post.show', ['post' => $article->path]), $article->url);
    }

    /** @test */
    public function plan_page_shows_other_plans_from_the_same_section_only()
    {
        $topic = $this->topic('ТестРаздел Политика', 'ТестТема Власть');
        $otherSection = $this->topic('ТестРаздел Духовная', 'ТестТема Мораль');
        $plan = $this->makePost('ТестПлан Политическая власть', true, $topic);
        $sibling = $this->makePost('ТестПлан Политическая система', true, $topic);
        $foreign = $this->makePost('ТестПлан Мораль', true, $otherSection);

        $this->get(route('plan.show', ['post' => $plan->path]))
            ->assertOk()
            ->assertSee('Другие планы')
            ->assertSee($sibling->title)
            ->assertDontSee($foreign->title)
            ->assertSee(route('plan.index'), false);
    }

    /** @test */
    public function admin_can_set_topic_on_a_post()
    {
        $admin = User::where('role', User::ROLE_ADMIN)->first();
        $this->assertNotNull($admin, 'В БД нет ни одного администратора.');
        $topic = $this->topic('ТестРаздел Социальная сфера', 'ТестТема Семья');
        $plan = $this->makePost('ТестПлан Семья', true);

        $this->actingAs($admin)->get(route('admin.post.edit', $plan->id))
            ->assertOk()
            ->assertSee('ТестТема Семья');

        $this->actingAs($admin)->patch(route('admin.post.update', $plan->id), [
            'title' => $plan->title,
            'content' => $plan->content,
            'category_id' => $plan->category_id,
            'path' => $plan->path,
            'topic_id' => $topic->id,
            'tag_ids' => [$this->planTag()->id],
        ])->assertSessionHasNoErrors();

        $this->assertSame($topic->id, (int) $plan->fresh()->topic_id);
    }
}
