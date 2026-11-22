<?php

namespace App\Models;

use App\Support\Eyal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ApiToken extends Model
{
    protected $table = 'api_tokens';

    protected $fillable = [
        'user_id',
        'name',
        'token_hash',
        'last_used_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * إصدار رمز دخول دائم (Bearer Token) لتطبيق الموبايل مع حفظ التشفير SHA-256 في قاعدة البيانات.
     *
     * @return array{plain_token: string, token: self}
     */
    public static function issueForUser(User $user, string $deviceName = 'ayla-mobile'): array
    {
        Eyal::ensureApiTokensTable();

        $rawSecret = Str::random(48);
        $hash = hash('sha256', $rawSecret);

        $token = self::create([
            'user_id' => $user->id,
            'name' => mb_substr(trim($deviceName) ?: 'ayla-mobile', 0, 120),
            'token_hash' => $hash,
            'last_used_at' => now(),
            'expires_at' => null,
        ]);

        return [
            'plain_token' => $token->id.'|'.$rawSecret,
            'token' => $token,
        ];
    }

    /**
     * التحقق من صلاحية الـ Bearer Token وإرجاع سجل التوكن مع المستخدم.
     */
    public static function findValidToken(?string $rawBearer): ?self
    {
        if (! $rawBearer || trim($rawBearer) === '') {
            return null;
        }

        Eyal::ensureApiTokensTable();

        $rawBearer = trim($rawBearer);
        $tokenModel = null;

        if (str_contains($rawBearer, '|')) {
            [$idPart, $secretPart] = explode('|', $rawBearer, 2);
            if (ctype_digit($idPart) && $secretPart !== '') {
                $candidate = self::with('user')->find((int) $idPart);
                if ($candidate && hash_equals($candidate->token_hash, hash('sha256', $secretPart))) {
                    $tokenModel = $candidate;
                }
            }
        } else {
            $tokenModel = self::with('user')
                ->where('token_hash', hash('sha256', $rawBearer))
                ->first();
        }

        if (! $tokenModel || ! $tokenModel->user || ! $tokenModel->user->is_active) {
            return null;
        }

        if ($tokenModel->expires_at && $tokenModel->expires_at->isPast()) {
            return null;
        }

        return $tokenModel;
    }
}
