<?php

namespace App\Service;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class ReviewLock
{
    private static function table(): string
    {
        return 'submissions';
    }

    /**
     * Атомарно захватывает или продлевает лок на $ttlMinutes.
     * Возвращает true, если лок теперь за $userId.
     */
    public static function acquire(int $submissionId, int $userId, int $ttlMinutes = 60): bool
    {
        $now = Carbon::now();
        $expiresAt = $now->copy()->addMinutes($ttlMinutes);

        $affected = DB::table(self::table())
            ->where('id', $submissionId)
            ->where(function ($q) use ($now, $userId) {
                $q->whereNull('locked_by_id')
                  ->orWhere('lock_expires_at', '<', $now)
                  ->orWhere('locked_by_id', $userId); // продлеваем свой же лок
            })
            ->update([
                'locked_by_id'    => $userId,
                'lock_expires_at' => $expiresAt,
                'updated_at'      => $now,
            ]);

        return $affected === 1;
    }

    /** Продлевает лок владельца. */
    public static function extend(int $submissionId, int $userId, int $ttlMinutes = 60): bool
    {
        $now = Carbon::now();
        $expiresAt = $now->copy()->addMinutes($ttlMinutes);

        $affected = DB::table(self::table())
            ->where('id', $submissionId)
            ->where('locked_by_id', $userId)
            ->update([
                'lock_expires_at' => $expiresAt,
                'updated_at'      => $now,
            ]);

        return $affected === 1;
    }

    /** Снимает лок, если он принадлежит пользователю. */
    public static function release(int $submissionId, int $userId): void
    {
        DB::table(self::table())
            ->where('id', $submissionId)
            ->where('locked_by_id', $userId)
            ->update([
                'locked_by_id'    => null,
                'lock_expires_at' => null,
                'updated_at'      => now(),
            ]);
    }

    /** Возвращает true, если запись занята другим активным пользователем. */
    public static function lockedByOther(int $submissionId, int $userId): bool
    {
        $row = DB::table(self::table())
            ->select('locked_by_id', 'lock_expires_at')
            ->where('id', $submissionId)
            ->first();

        if (!$row) return false;

        $lockedByOther = $row->locked_by_id !== null
            && (int)$row->locked_by_id !== $userId;

        $expiresAt = $row->lock_expires_at ? Carbon::parse($row->lock_expires_at) : null;
        $stillActive = $expiresAt && $expiresAt->isFuture();

        return $lockedByOther && $stillActive;
    }

    /** Время истечения лока (Carbon|null). */
    public static function lockedUntil(int $submissionId): ?Carbon
    {
        $ts = DB::table(self::table())
            ->where('id', $submissionId)
            ->value('lock_expires_at');

        return $ts ? Carbon::parse($ts) : null;
    }
}
