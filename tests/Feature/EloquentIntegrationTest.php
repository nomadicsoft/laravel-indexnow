<?php

namespace NomadicSoft\LaravelIndexNow\Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Queue;
use NomadicSoft\LaravelIndexNow\Concerns\NotifiesIndexNow;
use NomadicSoft\LaravelIndexNow\Contracts\IndexNowable;
use NomadicSoft\LaravelIndexNow\Enums\IndexNowChange;
use NomadicSoft\LaravelIndexNow\Jobs\SubmitIndexNowUrls;
use NomadicSoft\LaravelIndexNow\Tests\TestCase;

final class EloquentIntegrationTest extends TestCase
{
    public function test_the_opt_in_trait_notifies_for_enabled_model_events(): void
    {
        config()->set('indexnow.queue.enabled', true);
        Queue::fake();

        $article = new IndexNowArticle;
        $article->slug = 'published';
        $article->published = true;
        $article->trigger('created');

        Queue::assertPushed(
            SubmitIndexNowUrls::class,
            fn (SubmitIndexNowUrls $job): bool => $job->urls === ['https://example.com/articles/published'],
        );
    }

    public function test_the_model_can_reject_non_public_changes(): void
    {
        config()->set('indexnow.queue.enabled', true);
        Queue::fake();

        $article = new IndexNowArticle;
        $article->slug = 'draft';
        $article->published = false;
        $article->trigger('updated');

        Queue::assertNothingPushed();
    }
}

final class IndexNowArticle extends Model implements IndexNowable
{
    use NotifiesIndexNow;

    public $timestamps = false;

    public string $slug;

    public bool $published;

    protected $guarded = [];

    public function indexNowUrls(IndexNowChange $change): iterable
    {
        return ['/articles/'.$this->slug];
    }

    public function shouldNotifyIndexNow(IndexNowChange $change): bool
    {
        return (bool) $this->published;
    }

    public function trigger(string $event): void
    {
        $this->fireModelEvent($event, false);
    }
}
