# @litecommerce/page-builder

Cienka warstwa nad [Puck](https://puckeditor.com/) (`@puckeditor/core`) używana wyłącznie w edytorze CMS panelu administracyjnego Litecommerce. Buduje konfigurację Pucka z opisu bloków dostarczonego przez backend (`config/cms.php` w module `Cms`) i dostarcza pola specyficzne dla Litecommerce (wybór produktu/kategorii, upload obrazu).

## Status: pakiet tymczasowy w tym repozytorium

To jest **npm workspace wewnątrz głównego repo Litecommerce**, nie osobny, wersjonowany pakiet. Docelowo ten kod ma zostać wydzielony do osobnego repozytorium i publikowany jako niezależny pakiet npm — gdy API Pucka i kształt konfiguracji bloków się ustabilizują. Do tego czasu:

- brak infrastruktury publikowania (nie ma `npm publish`, nie ma niezależnego CI/wersjonowania),
- pakiet jest importowany przez panel jako zwykła zależność workspace (`workspaces: ["packages/*"]` w root `package.json`),
- nie traktuj jego publicznego API jako stabilnego — zmiany razem z panelem są dozwolone bez zachowania wstecznej kompatybilności.

## Dlaczego Puck renderuje tylko tutaj

Puck (i cały jego runtime Reacta) działa **wyłącznie w edytorze panelu** — nigdy na storefroncie. Zapisany JSON (`{content, root}`) trafia do bazy jako zwykła kolumna i jest interpretowany po stronie storefrontu przez czysto-PHP `Cms\Infrastructure\BlockRenderer`, bez importowania Pucka do PHP. Dzięki temu domyślny runtime sklepu nadal działa bez Node.js. Szczegóły: [`docs/knowledge-base/09-presentation.md`](../../docs/knowledge-base/09-presentation.md).

## Eksporty

- `buildPuckConfig(blocks, rootFields, endpoints)` — buduje pełny Puck `config` (`components` z opisu bloków `config('cms.blocks')`, `root.fields` z `config('cms.root_fields')`) przekazany przez backend jako prop. `endpoints` dostarcza URL-e wykorzystywane przez pola niestandardowe (`productSearchUrl`, `imageUploadUrl`). Wybór kategorii (tryb `category` bloku `product-grid`) to zwykłe pole `select` — lista kategorii jest mała, więc backend dogrywa jej opcje bezpośrednio do configu zamiast osobnego endpointu wyszukiwania.
- `PageBuilderEditor` — komponent React opakowujący `<Puck>` gotowy do osadzenia na ekranie panelu.
- `createSearchPickerField`, `createImagePickerField` — fabryki pól niestandardowych Pucka, używane wewnętrznie przez `buildPuckConfig`, eksportowane też do ewentualnego samodzielnego użycia.

Renderowanie wewnątrz edytora (`render` każdego komponentu w configu) to wyłącznie uproszczony podgląd na potrzeby Pucka — nie jest to finalny wygląd bloku na sklepie, o ten dba `theme::blocks.{type}` renderowany przez `BlockRenderer`.

## Pola strony (`root`), nie tylko bloki

Puck ma wbudowaną koncepcję pól na poziomie CAŁEJ strony (`config.root.fields`, niezależnych od listy bloków `content`) — Litecommerce wykorzystuje to miejsce na tytuł/slug/SEO/status strony (`config('cms.root_fields')`), zamiast trzymać osobny formularz metadanych obok osadzonego edytora. Dzięki temu `<PageBuilderEditor>` jest JEDYNYM ekranem edycji strony (uruchamianym pełnoekranowo, `Panel/Cms/Edit.jsx`) — jeden przycisk „Publish” Pucka zapisuje i treść (`data.content`), i metadane (`data.root.props`) w jednym żądaniu.

## Layout: kontener grid/flex i zagnieżdżanie (slots)

Blok `container` (`config('cms.blocks').container`) używa natywnego typu pola Pucka `slot` (`column_1`..`column_4`) — każda kolumna to osobna, zagnieżdżona strefa upuszczania, w którą można wstawić DOWOLNY inny blok, w tym kolejny `container` (grid w gridzie działa, bo pola slot nie mają ustawionego `allow`/`disallow`). `buildConfig.js` przepuszcza pola typu `slot` bez zmian (Puck sam zamienia wartość takiego propa w komponencie `render` na wywoływalny podkomponent — patrz `renderers/containerBlock.jsx`). Po stronie storefrontu `Cms\Infrastructure\ThemeBlockRenderer::renderBlocks()` jest rekurencyjne — dla bloku `container` renderuje zawartość każdego slotu tą samą metodą, którą renderuje listę bloków najwyższego poziomu.

Każdy blok (nie tylko `container`) ma też pole `width` (`W kontenerze` / `Pełna szerokość`) — obsługiwane CENTRALNIE w `ThemeBlockRenderer::renderBlocks()` (owija wynik `theme::blocks.{type}` w `<div class="cms-block cms-block--{width}">`), nie w każdym pliku Blade bloku osobno.

`container` obsługuje także proporcje kolumn (`equal`, `2-1`, `3-1`, `1-2`). Wbudowany moduł Blog dopina trzy bloki: dynamiczną treść bieżącego wpisu, wpisy blogowe (`current`/`latest`, `grid`/`list`/`carousel`, limit) i kategorie bloga. Dane tych bloków nie są atrapami zapisanymi w dokumencie Pucka — na storefroncie rozwiązuje je `Cms\Contracts\BlockDataRegistry` z kontekstu archetypu `blog_post`; edytor pokazuje jedynie reprezentatywny podgląd.
