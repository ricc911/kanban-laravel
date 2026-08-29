<?php

use App\Models\Board;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('board.{board}', function (User $user, Board $board): bool {
    return $board->workspace->isOwner($user) || $board->workspace->hasMember($user);
});

Broadcast::channel('board-presence.{board}', function (User $user, Board $board): array|false {
    if (! $board->workspace->isOwner($user) && ! $board->workspace->hasMember($user)) {
        return false;
    }

    return [
        'id' => (int) $user->id,
        'name' => $user->name,
    ];
});
