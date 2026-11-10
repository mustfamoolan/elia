<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
        'group',
    ];

    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = static::query()->where('key', $key)->first();

        if (! $setting) {
            return $default;
        }

        $decoded = json_decode((string) $setting->value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $setting->value;
    }

    public static function set(string $key, mixed $value, string $group = 'general'): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => json_encode($value, JSON_UNESCAPED_UNICODE), 'group' => $group],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function allAsArray(): array
    {
        return static::query()
            ->get()
            ->mapWithKeys(function (self $setting) {
                $decoded = json_decode((string) $setting->value, true);

                return [$setting->key => json_last_error() === JSON_ERROR_NONE ? $decoded : $setting->value];
            })
            ->all();
    }
}
