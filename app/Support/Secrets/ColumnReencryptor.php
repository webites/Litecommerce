<?php

declare(strict_types=1);

namespace App\Support\Secrets;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

final class ColumnReencryptor
{
    /**
     * @param  list<string>  $columns
     * @return array{0: int, 1: int} [reencrypted values, values no configured key can decrypt]
     */
    public static function table(string $table, string $keyColumn, array $columns, int $batchSize, ?callable $constrain = null): array
    {
        $done = 0;
        $broken = 0;

        $query = DB::table($table)->select([$keyColumn, ...$columns])->orderBy($keyColumn);
        if ($constrain !== null) {
            $constrain($query);
        }

        $query->chunk(max(1, $batchSize), function ($rows) use ($table, $keyColumn, $columns, &$done, &$broken): void {
            foreach ($rows as $row) {
                $updates = [];
                foreach ($columns as $column) {
                    $value = $row->{$column};
                    if (! is_string($value) || $value === '') {
                        continue;
                    }
                    try {
                        $updates[$column] = Crypt::encryptString(Crypt::decryptString($value));
                    } catch (DecryptException) {
                        $broken++;
                    }
                }
                if ($updates !== []) {
                    DB::table($table)->where($keyColumn, $row->{$keyColumn})->update($updates);
                    $done += count($updates);
                }
            }
        });

        return [$done, $broken];
    }
}
