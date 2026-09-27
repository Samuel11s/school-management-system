<?php

namespace App\Models\Concerns;

use App\Models\StatusHistory;
use BackedEnum;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Records every change of the model's "status" attribute in status_histories.
 *
 * Call withStatusReason() before saving to attach a human readable reason.
 */
trait RecordsStatusHistory
{
    protected ?string $statusChangeReason = null;

    public static function bootRecordsStatusHistory(): void
    {
        static::created(function (self $model) {
            $model->recordStatusChange(null);
        });

        static::updated(function (self $model) {
            if ($model->wasChanged('status')) {
                $model->recordStatusChange($model->getOriginal('status'));
            }
        });
    }

    /**
     * @return MorphMany<StatusHistory, $this>
     */
    public function statusHistories(): MorphMany
    {
        return $this->morphMany(StatusHistory::class, 'subject')->latest('id');
    }

    public function withStatusReason(?string $reason): static
    {
        $this->statusChangeReason = $reason;

        return $this;
    }

    protected function recordStatusChange(mixed $from): void
    {
        $to = $this->getAttribute('status');

        $this->statusHistories()->create([
            'from_status' => $from instanceof BackedEnum ? $from->value : $from,
            'to_status' => $to instanceof BackedEnum ? $to->value : $to,
            'reason' => $this->statusChangeReason,
            'changed_by' => auth()->id(),
        ]);

        $this->statusChangeReason = null;
    }
}
