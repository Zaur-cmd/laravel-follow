<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;
use Overtrue\LaravelFollow\Events\Followed;
use Overtrue\LaravelFollow\Events\Unfollowed;

class Followable extends Model
{
    protected $guarded = [];

    protected $dispatchesEvents = [
        'created' => Followed::class,
        'deleted' => Unfollowed::class,
    ];

    protected $dates = ['accepted_at'];

    public function __construct(array $attributes = [])
    {
        $this->table = config('follow.followables_table', 'followables');

        parent::__construct($attributes);
    }

    protected static function boot()
    {
        parent::boot();

        self::saving(function ($model) {
            // НЕ подставляем follower_id и follower_type из auth()
            // Если они уже заданы, оставляем как есть
            if (config('follow.uuids')) {
                $model->setAttribute(
                    $model->getKeyName(),
                    $model->{$model->getKeyName()} ?: (string) Str::orderedUuid()
                );
            }
        });
    }

    // Морфная связь на подписчика
    public function follower(): MorphTo
    {
        return $this->morphTo('follower');
    }

    // Морфная связь на подписываемый объект
    public function followable(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeWithType(Builder $query, string $type): Builder
    {
        return $query->where('followable_type', app($type)->getMorphClass());
    }

    public function scopeOf(Builder $query, Model $model): Builder
    {
        return $query->where('followable_type', $model->getMorphClass())
            ->where('followable_id', $model->getKey());
    }

    public function scopeFollowedBy(Builder $query, Model $follower): Builder
    {
        return $query->where('follower_id', $follower->getKey())
            ->where('follower_type', $follower->getMorphClass());
    }

    public function scopeAccepted(Builder $query): Builder
    {
        return $query->whereNotNull('accepted_at');
    }

    public function scopeNotAccepted(Builder $query): Builder
    {
        return $query->whereNull('accepted_at');
    }
}
