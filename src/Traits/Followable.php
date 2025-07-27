<?php

namespace Overtrue\LaravelFollow\Traits;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Overtrue\LaravelFollow\Traits\Follower as Follower;

trait Followable
{
    /**
     * Нужно ли подтверждение подписки.
     */
    public function needsToApproveFollowRequests(): bool
    {
        return false;
    }

    /**
     * Отклонить запрос на подписку от модели $follower.
     *
     * @throws \InvalidArgumentException
     */
    public function rejectFollowRequestFrom(Model $follower): void
    {
        if (! in_array(Follower::class, class_uses($follower))) {
            throw new \InvalidArgumentException('The model must use the Follower trait.');
        }

        $this->followables()->followedBy($follower)->get()->each->delete();
    }

    /**
     * Принять запрос на подписку от модели $follower.
     *
     * @throws \InvalidArgumentException
     */
    public function acceptFollowRequestFrom(Model $follower): void
    {
        if (! in_array(Follower::class, class_uses($follower))) {
            throw new \InvalidArgumentException('The model must use the Follower trait.');
        }

        $this->followables()->followedBy($follower)->get()->each->update(['accepted_at' => now()]);
    }

    /**
     * Проверить, подписан ли $follower на этот объект.
     *
     * @throws \InvalidArgumentException
     */
    public function isFollowedBy(Model $follower): bool
    {
        if (! in_array(Follower::class, class_uses($follower))) {
            throw new \InvalidArgumentException('The model must use the Follower trait.');
        }

        if ($this->relationLoaded('followables')) {
            return $this->followables->whereNotNull('accepted_at')->contains($follower);
        }

        return $this->followables()->accepted()->followedBy($follower)->exists();
    }

    /**
     * Скопировать порядок по количеству подписчиков (используйте с Query Builder).
     */
    public function scopeOrderByFollowersCount($query, string $direction = 'desc')
    {
        return $query->withCount('followers')->orderBy('followers_count', $direction);
    }

    public function scopeOrderByFollowersCountDesc($query)
    {
        return $this->scopeOrderByFollowersCount($query, 'desc');
    }

    public function scopeOrderByFollowersCountAsc($query)
    {
        return $this->scopeOrderByFollowersCount($query, 'asc');
    }

    /**
     * Связь с моделью Followable (записи подписок).
     */
    public function followables(): HasMany
    {
        return $this->hasMany(
            config('follow.followables_model', \Overtrue\LaravelFollow\Followable::class),
            'followable_id'
        )->where('followable_type', $this->getMorphClass());
    }

    /**
     * Подписчики — записи в таблице followables с типом и ID подписчика.
     * Возвращает коллекцию моделей Followable (записей подписок).
     */
    public function followers(): HasMany
    {
        return $this->hasMany(
            config('follow.followables_model', \Overtrue\LaravelFollow\Followable::class),
            'followable_id'
        )->where('followable_type', $this->getMorphClass());
    }

    /**
     * Получить коллекцию моделей подписчиков (моделей, а не записей Followable).
     * Работает по группировке по follower_type и выборке моделей из базы.
     */
    public function followerModels(): Collection
    {
        $followers = $this->followers()->get();

        $models = collect();

        $grouped = $followers->groupBy('follower_type');

        foreach ($grouped as $type => $items) {
            $ids = $items->pluck('follower_id')->toArray();
            $modelsOfType = app($type)->whereIn('id', $ids)->get();
            $models = $models->merge($modelsOfType);
        }

        return $models;
    }

    /**
     * Подтверждённые подписчики (accepted_at не null).
     */
    public function approvedFollowers()
    {
        return $this->followers()->whereNotNull('accepted_at');
    }

    /**
     * Не подтверждённые подписчики (accepted_at null).
     */
    public function notApprovedFollowers()
    {
        return $this->followers()->whereNull('accepted_at');
    }
}
