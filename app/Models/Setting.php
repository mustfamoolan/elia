<?php

namespace App\Models;

use App\Support\Eyal;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    use HasFactory;

    public const CACHE_KEY = 'eyal.v1.settings';

    /**
     * كاش داخلي ضمن نفس الطلب (Request Memory Cache).
     *
     * @var array<string, mixed>|null
     */
    protected static ?array $runtimeSettings = null;

    protected $fillable = [
        'key',
        'value',
        'group',
    ];

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = static::allAsArray();

        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    public static function set(string $key, mixed $value, string $group = 'general'): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => json_encode($value, JSON_UNESCAPED_UNICODE), 'group' => $group],
        );

        static::flushCache();
    }

    /**
     * @return array<string, mixed>
     */
    public static function allAsArray(): array
    {
        if (static::$runtimeSettings !== null) {
            return static::$runtimeSettings;
        }

        try {
            static::$runtimeSettings = Cache::remember(self::CACHE_KEY, 86400, function (): array {
                return static::query()
                    ->get(['key', 'value'])
                    ->mapWithKeys(function (self $setting) {
                        $decoded = json_decode((string) $setting->value, true);

                        return [$setting->key => json_last_error() === JSON_ERROR_NONE ? $decoded : $setting->value];
                    })
                    ->all();
            });
        } catch (\Throwable) {
            static::$runtimeSettings = [];
        }

        return static::$runtimeSettings;
    }

    public static function flushCache(): void
    {
        static::$runtimeSettings = null;

        try {
            Cache::forget(self::CACHE_KEY);
            Eyal::flushCache();
        } catch (\Throwable) {
            //
        }
    }
}
