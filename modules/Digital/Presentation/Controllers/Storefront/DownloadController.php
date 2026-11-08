<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Digital\Presentation\Controllers\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Litecommerce\Modules\Digital\Application\ConsumeDownload;
use Litecommerce\Modules\Digital\Application\DownloadOutcome;
use Litecommerce\Modules\Media\Contracts\PrivateFileLibrary;
use Symfony\Component\HttpFoundation\Response;

// Unauthenticated, token-secured endpoint an order-confirmation/e-mail link points to — no account or theme needed.
final class DownloadController extends Controller
{
    public function show(Request $request, string $token, ConsumeDownload $consumer, PrivateFileLibrary $files): Response
    {
        $result = $consumer->execute($token, $request->header('Range'), $request->ip(), $request->userAgent());

        return match ($result->outcome) {
            DownloadOutcome::Served => $files->respond((int) $result->privateFileId, (string) $result->downloadFilename),
            DownloadOutcome::NotFound => response(trans('digital::messages.link_invalid'), 404),
            DownloadOutcome::Expired => response(trans('digital::messages.link_expired'), 410),
            DownloadOutcome::Revoked => response(trans('digital::messages.access_revoked'), 403),
            DownloadOutcome::LimitExceeded => response(trans('digital::messages.limit_exceeded'), 403),
            DownloadOutcome::Unavailable => response(trans('digital::messages.file_unavailable'), 503),
        };
    }
}
