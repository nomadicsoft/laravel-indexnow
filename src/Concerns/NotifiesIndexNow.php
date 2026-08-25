<?php

namespace NomadicSoft\LaravelIndexNow\Concerns;

use NomadicSoft\LaravelIndexNow\Enums\IndexNowChange;
use NomadicSoft\LaravelIndexNow\IndexNowManager;
use Throwable;

trait NotifiesIndexNow
{
    public static function bootNotifiesIndexNow(): void
    {
        $events = (array) config('indexnow.model.events', ['created', 'updated', 'deleted', 'restored']);

        foreach ([
            'created' => IndexNowChange::Created,
            'updated' => IndexNowChange::Updated,
            'deleted' => IndexNowChange::Deleted,
        ] as $event => $change) {
            if (in_array($event, $events, true)) {
                static::{$event}(fn (self $model) => $model->notifyIndexNow($change));
            }
        }

        if (in_array('restored', $events, true)) {
            static::registerModelEvent(
                'restored',
                fn (self $model) => $model->notifyIndexNow(IndexNowChange::Restored),
            );
        }
    }

    public function shouldNotifyIndexNow(IndexNowChange $change): bool
    {
        return true;
    }

    public function notifyIndexNow(IndexNowChange $change): void
    {
        if (! $this->shouldNotifyIndexNow($change)) {
            return;
        }

        try {
            app(IndexNowManager::class)->notifyMany($this->indexNowUrls($change));
        } catch (Throwable $exception) {
            if (! config('indexnow.model.fail_silently', true)) {
                throw $exception;
            }

            report($exception);
        }
    }
}
