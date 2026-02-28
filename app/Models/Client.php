<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Client extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected static function booted(): void
    {
        static::creating(function (Client $client) {
            if (!empty($client->client_code)) {
                return;
            }

            $existingCodes = self::query()
                ->whereNotNull('client_code')
                ->pluck('client_code');

            $maxNumericCode = $existingCodes
                ->filter(fn ($code) => !Str::startsWith((string) $code, 'P') && ctype_digit((string) $code))
                ->map(fn ($code) => (int) $code)
                ->max() ?? 0;

            $client->client_code = str_pad((string) ($maxNumericCode + 1), 3, '0', STR_PAD_LEFT);
        });
    }

    public function loans()
    {
        return $this->hasMany(Loan::class);
    }
}
