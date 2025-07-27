<?php
namespace Overtrue\LaravelFollow;

use function config;
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

        self::saving(function ($follower) {
            // Убираем user_id
            // Вместо него ставим morphs 'follower'

            if (! $follower->follower_id || ! $follower->follower_type) {
                if (auth()->check()) {
                    $follower->follower_id = auth()->id();
                    $follower->follower_type = config('auth.providers.users.model');
                }
            }

            if (config('follow.uuids')) {
                $follower->setAttribute($follower->getKeyName(), $follower->{$follower->getKeyName()} ?: (string) Str::orderedUuid());
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
