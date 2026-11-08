<?php

return [
    'source_unreachable' => 'Nie udało się połączyć z GitHubem. Spróbuj ponownie później.',
    'signature_invalid' => 'Podpis wydania jest nieprawidłowy, więc wydanie zostało odrzucone.',
    'manifest_invalid' => 'Opis wydania jest uszkodzony, więc wydanie zostało odrzucone.',
    'unexpected_error' => 'Wystąpił nieoczekiwany błąd.',
    'incompatible' => 'Serwer nie spełnia wymagań tego wydania.',
    'maintenance_already_active' => 'Tryb przerwy jest już włączony ręcznie. Wyłącz go i spróbuj ponownie.',
    'backup_failed' => 'Nie udało się wykonać kopii zapasowej bazy danych.',
    'backup_unwritable' => 'Katalog kopii zapasowych nie jest zapisywalny.',
    'archive_too_large' => 'Archiwum wydania jest większe niż dozwolony limit.',
    'archive_unreadable' => 'Pobrane archiwum jest uszkodzone.',
    'checksum_invalid' => 'Suma kontrolna wydania ma nieprawidłowy format.',
    'checksum_mismatch' => 'Suma kontrolna pobranego archiwum nie zgadza się z opublikowaną.',
    'files_missing' => 'W archiwum brakuje plików wymienionych w opisie wydania.',
    'staging_unwritable' => 'Nie można rozpakować archiwum w katalogu roboczym.',
    'unexpected_entry' => 'Archiwum zawiera pliki spoza opisu wydania.',
    'swap_failed' => 'Nie udało się podmienić plików. Pliki wróciły do poprzedniej wersji.',
    'restore_failed' => 'Nie udało się przywrócić poprzednich plików.',
    'migration_failed' => 'Migracja bazy danych nie powiodła się. Sklep pozostaje w trybie przerwy — przywróć kopię zapasową.',
    'post_update_failed' => 'Nie udało się wyczyścić pamięci podręcznej po aktualizacji.',
    'integrity_failed' => 'Pliki na serwerze różnią się od wydania. Sklep pozostaje w trybie przerwy.',
];
