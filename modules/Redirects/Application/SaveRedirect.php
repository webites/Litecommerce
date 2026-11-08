<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Redirects\Application;

use Illuminate\Validation\ValidationException;
use Litecommerce\Modules\Redirects\Infrastructure\Models\Redirect;

/**
 * Rejecting a new/edited redirect whose to_path is any OTHER redirect's
 * from_path (and the symmetric case: whose from_path is any OTHER
 * redirect's to_path) blocks every 2-entry chain from ever forming in
 * either construction order — and since a chain of any length is built one
 * link at a time, this transitively forbids chains/cycles of any length
 * too, without walking the graph.
 */
final class SaveRedirect
{
    public function handle(?Redirect $redirect, SaveRedirectData $data): Redirect
    {
        if ($data->fromPath === $data->toPath) {
            throw ValidationException::withMessages(['to_path' => 'Adres docelowy nie może być taki sam jak adres źródłowy.']);
        }

        $ownId = $redirect?->getKey();

        $duplicate = Redirect::query()
            ->where('locale', $data->locale)
            ->where('from_path', $data->fromPath)
            ->when($ownId !== null, fn ($query) => $query->where('id', '!=', $ownId))
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages(['from_path' => 'Przekierowanie z tego adresu już istnieje.']);
        }

        $chainsForward = Redirect::query()
            ->where('locale', $data->locale)
            ->where('from_path', $data->toPath)
            ->when($ownId !== null, fn ($query) => $query->where('id', '!=', $ownId))
            ->exists();

        if ($chainsForward) {
            throw ValidationException::withMessages(['to_path' => 'Adres docelowy jest sam źródłem innego przekierowania — wskaż jego cel bezpośrednio, zamiast tworzyć łańcuch.']);
        }

        $chainsBackward = Redirect::query()
            ->where('locale', $data->locale)
            ->where('to_path', $data->fromPath)
            ->when($ownId !== null, fn ($query) => $query->where('id', '!=', $ownId))
            ->exists();

        if ($chainsBackward) {
            throw ValidationException::withMessages(['from_path' => 'Ten adres jest już celem innego przekierowania — dodanie stąd kolejnego stworzyłoby łańcuch.']);
        }

        $redirect ??= new Redirect;
        $redirect->fill(['locale' => $data->locale, 'from_path' => $data->fromPath, 'to_path' => $data->toPath]);
        $redirect->save();

        return $redirect;
    }
}
