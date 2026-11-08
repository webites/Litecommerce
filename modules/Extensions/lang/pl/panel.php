<?php

return [
    'index' => [
        'title' => 'Rozszerzenia',
        'description' => 'Instalowane z lokalnej paczki ZIP — dostęp do rdzenia jak zaufany kod aplikacji.',
        'upload_card_title' => 'Wgraj paczkę',
        'upload_card_description' => 'Plik ZIP z manifest.json w katalogu głównym. Po wgraniu rozszerzenie jest wyłączone — zainstaluj je w szczegółach.',
        'upload_button' => 'Wgraj ZIP',
        'untrusted_ack' => 'Rozumiem, że wtyczka to kod PHP z pełnym dostępem do sklepu, a jego autentyczność nie jest weryfikowana. Wgrywam tylko paczki od zaufanych autorów.',
        'installed_card_title' => 'Zainstalowane rozszerzenia',
        'empty_title' => 'Brak rozszerzeń',
        'empty_description' => 'Wgraj pierwszą paczkę ZIP powyżej.',
        'version_prefix' => 'wersja',
        'details' => 'Szczegóły',
    ],
    'status' => [
        'winding_down' => 'Wygaszanie',
        'disabled' => 'Wyłączone',
        'enabled' => 'Włączone',
        'not_installed' => 'Niezainstalowane',
    ],
    'show' => [
        'identifier' => 'Identyfikator: :id',
        'state_title' => 'Stan',
        'status_label' => 'Status',
        'secret_saved' => 'Zapisano — zostaw puste, aby zachować',
        'version_label' => 'Wersja',
        'sha256_label' => 'Suma SHA-256 paczki',
        'dependencies_label' => 'Zależności',
        'install' => 'Zainstaluj',
        'enable' => 'Włącz',
        'disable' => 'Wyłącz',
        'uninstall' => 'Odinstaluj',
        'uninstall_confirm' => 'Odinstalować „:name”? Autor pluginu decyduje o losie jego danych.',
        'settings_title' => 'Ustawienia',
        'save' => 'Zapisz',
    ],
];
