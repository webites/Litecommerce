<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Core version
    |--------------------------------------------------------------------------
    |
    | Litecommerce has no release/tagging process yet — this is the first
    | version identifier in the project, introduced so a plugin manifest's
    | "core" constraint has something real to check against. Bump it when
    | a real release process exists; until then it only needs to be a
    | valid semver string for VersionConstraint to compare against.
    |
    */

    'core_version' => env('LITECOMMERCE_VERSION', '0.1.0'),

    /*
    |--------------------------------------------------------------------------
    | Package limits
    |--------------------------------------------------------------------------
    |
    | Sanity limits that keep a shared-hosting box from choking on an
    | oversized or pathologically-many-entries archive. Compression-ratio
    | and extraction-budget checks live in ZipPackageValidator.
    |
    */

    'max_package_size_mb' => env('EXTENSIONS_MAX_PACKAGE_SIZE_MB', 50),

    'max_file_count' => env('EXTENSIONS_MAX_FILE_COUNT', 2000),

    /*
    |--------------------------------------------------------------------------
    | Storage
    |--------------------------------------------------------------------------
    |
    | Extracted plugin code lives on this disk, never under public/ — see
    | "Walidacja paczki i instalacja" in docs/knowledge-base/08-extensions.md.
    | Reuses the same private disk Media\Contracts\PrivateFileLibrary uses.
    |
    */

    'disk' => env('EXTENSIONS_DISK', 'local'),

    'path' => 'extensions',

    /*
    |--------------------------------------------------------------------------
    | Trusted package signing keys
    |--------------------------------------------------------------------------
    |
    | key id => base64 Ed25519 public key, used by PackageSignatureVerifier for
    | packages from an API catalog. A locally uploaded ZIP does not need one.
    | Removing a key revokes it for every package it signed.
    |
    */

    'trusted_keys' => [],

];
