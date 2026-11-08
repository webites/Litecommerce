/*
 * Litecommerce storefront runtime — one small, generic, dependency-free
 * script owned by the CORE (served from a fixed core URL, never copied
 * into a theme package), so that building an interactive storefront page
 * needs no theme-authored JavaScript for ordinary actions (add to cart,
 * update quantity, remove a line, apply a coupon, save the checkout
 * address, pick delivery/payment, place the order, log in/out).
 *
 * A theme opts into behaviour purely through markup — data-* attributes
 * and <template> elements — never by writing its own event listeners.
 * See docs/knowledge-base/09-presentation.md#projekt-techniczny-p1-013
 * ("Silnik storefrontu") for the full contract with examples. Summary:
 *
 *   <form data-storefront="ajax" action="/cart/items" method="post">
 *       ...named fields...
 *   </form>
 *       Intercepts submit, serializes named fields to JSON (supporting
 *       PHP/Rails-style bracket nesting: name="billing_address[city]"),
 *       sends it to `action` with `method` (or data-storefront-method to
 *       use PUT/DELETE, which <form method> cannot express natively).
 *       On success: shows a message in the nearest [data-storefront-message]
 *       element, broadcasts "storefront:refresh" on document (so every
 *       data-storefront-load region on the page re-syncs), and follows
 *       data-storefront-redirect if present (supports {field} tokens
 *       filled from the JSON response body).
 *       On failure: shows the API's message and, for each field named in
 *       a Laravel-shaped { errors: { field: [...] } } response, fills a
 *       matching [data-storefront-error-for="field"] element if the theme
 *       provided one.
 *
 *   <button data-storefront-action="post|put|delete"
 *           data-storefront-url="/cart/items/{variant_id}"
 *           data-storefront-redirect="/{locale}/checkout">
 *       Same request/response handling as an ajax form, for one-off
 *       actions that are not a form submission. {token} segments in the
 *       URL/redirect are resolved by walking up from the button via
 *       closest('[data-token-in-kebab-case]') and reading that attribute
 *       — the same mechanism a cloned list row exposes its own id with
 *       (see data-storefront-list below), so "remove this row" naturally
 *       finds "this row's" id without any per-page code. Add
 *       data-storefront-field="quantity" to trigger on the element's own
 *       "change" instead of a click, sending { quantity: element.value }

 *       as the body — e.g. a cart line's quantity <input> PUTting its
 *       new value to its own row's URL. Add data-storefront-refresh-on-error
 *       (here or on a data-storefront="ajax" form above) to also re-run
 *       every data-storefront-load region when this one fails — e.g.
 *       placing an order whose price_hash the backend rejects as stale
 *       (409 price_changed) redraws the checkout summary with the fresh
 *       amounts the same error response's backend recomputation already
 *       reflects server-side, instead of leaving the page showing numbers
 *       that are now wrong.
 *
 *   <div data-storefront-show-if="buyer_type:company" hidden>...</div>
 *       Shown whenever the radio/checkbox/select named "buyer_type"
 *       anywhere on the page currently has the value "company" (a
 *       checkbox compares against the literal string "true"/"false");
 *       hidden otherwise. Re-evaluated on every change event, so
 *       "show this section only when..." is never a reason to write
 *       page-specific script either. Comma-separate several field:value
 *       pairs to require all of them at once, e.g.
 *       data-storefront-show-if="kolor:czerwony,rozmiar:m" — the basis of
 *       a variant picker: one radio group per option axis (name="kolor",
 *       name="rozmiar", ...) plus one such block per real variant, each
 *       listing its own exact option combination, so picking values
 *       resolves to exactly one visible block with no page-specific
 *       script. Wrap the whole group in [data-storefront-show-if-container]
 *       and add one [data-storefront-show-if-fallback] sibling inside it
 *       to reveal a "not selected yet / that combination doesn't exist"
 *       message whenever none of the group's blocks currently match.
 *
 *   <input data-storefront-auto-submit form="the-form-id">
 *       On change, calls requestSubmit() on the form named by its own
 *       form="" attribute (native HTML — an input outside a <form> can
 *       still belong to it). Used for "pick a delivery method -> save
 *       immediately" without a separate button.
 *
 *   <div data-storefront-stepper>
 *       <button data-storefront-step="-1">-</button>
 *       <input type="text" readonly>
 *       <button data-storefront-step="1">+</button>
 *   </div>
 *       A +/- button adds its own signed integer to the nearest
 *       [data-storefront-stepper] ancestor's own <input> value (floored at
 *       1) and dispatches a real "change" event on it — found by DOM
 *       position, never an id, so this works unmodified inside a
 *       data-storefront-list clone (one stepper per cart line, all from
 *       the same <template>, where a literal id would collide). The
 *       input itself is a plain (readonly, to keep typed-in-garbage from
 *       ever reaching the API) text input, so a stepper composes for free
 *       with whatever that input's own change already does elsewhere —
 *       data-storefront-action="put" for an immediate cart-line quantity
 *       update, or nothing at all for a product page's add-to-cart form,
 *       where the value is only read on submit.
 *
 *   <button data-storefront-theme-toggle>
 *       Flips :root's data-theme attribute between "dark" and its default
 *       (light — removing the attribute, never writing "light" onto the
 *       element itself, since the theme's own :root is already light) and
 *       remembers the choice in a plain, non-HttpOnly "storefront_theme"
 *       cookie. The very next server render already reads that cookie
 *       (Storefront\Providers\StorefrontServiceProvider's $themeMode) and
 *       prints the right data-theme attribute itself, so there is no
 *       flash of the wrong theme on load and no inline bootstrap script —
 *       this one handler is the entire mechanism, core-owned like
 *       everything else on this page, never per-theme script.
 *
 *   <details data-storefront-language-switcher>
 *       <summary>...</summary>
 *       <ul>...</ul>
 *   </details>
 *       A native disclosure widget needs no script to open/close — this
 *       handler only closes it again on an outside click (the one thing
 *       <details> does not do natively), so picking a language doesn't
 *       leave a stray open menu behind if the click instead lands on the
 *       page underneath it.
 *
 *   <li data-storefront-submenu>
 *       <a>...</a>
 *       <button data-storefront-submenu-toggle aria-expanded="false">...</button>
 *       <ul class="sf-submenu">...</ul>
 *   </li>
 *       theme::partials.nav-menu-item's nested-item markup — a click toggles
 *       aria-expanded and an "is-open" class on the closest [data-storefront-
 *       submenu] (CSS shows .sf-submenu only while that class is present),
 *       and a click anywhere outside a currently-open one closes it again,
 *       the same outside-click contract as data-storefront-language-switcher.
 *
 *   <header data-storefront-nav>
 *       <button data-storefront-nav-toggle aria-expanded="false">...</button>
 *       <div data-storefront-nav-close></div>
 *       <nav class="sf-nav">...</nav>
 *   </header>
 *       The mobile hamburger drawer. A click on [data-storefront-nav-toggle]
 *       flips aria-expanded and an "is-open" class on the closest
 *       [data-storefront-nav] (theme::partials.nav-menu emits this whole
 *       block only when it actually has items — no menu configured means no
 *       hamburger either). [data-storefront-nav-close] (typically a backdrop
 *       behind the sliding panel) always closes, never toggles. A click
 *       outside a currently-open one, or the Escape key, also closes it.
 *       "sf-nav-open" is added to <html> itself while open, purely so a
 *       theme can lock body scroll behind a full-screen drawer if it wants
 *       to (e.g. "html.sf-nav-open { overflow: hidden; }") — this handler
 *       never touches scrolling itself. This is the ENTIRE mechanism,
 *       core-owned like every other primitive on this page — a theme never
 *       writes its own JS for this; it only decides, in its own theme.css,
 *       what ".is-open" looks like at its own responsive breakpoints (this
 *       is why the button/markup contract lives here at all: so "does the
 *       mobile menu work" is never a per-theme JavaScript question, only a
 *       per-theme CSS one).
 *
 *   <div data-storefront-country-select data-storefront-country-no-results="...">
 *       <input class="sf-country-search" role="combobox">
 *       <input type="hidden" name="billing_address[country_code]" data-storefront-country-value>
 *       <ul class="sf-country-options" role="listbox" hidden></ul>
 *   </div>
 *       A searchable country picker — see theme::partials.country-select.
 *       The visible search box carries no name (so serializeForm() never
 *       sees it); only the hidden input does, keeping the wire format
 *       identical to a plain 2-letter text field. The country list itself
 *       (code + translated name + flag, already filtered to what Delivery
 *       can actually quote — see CheckoutController::countries()) is
 *       fetched once from GET /checkout/countries and shared by every
 *       widget on the page, on demand rather than embedded in the initial
 *       HTML, so it never delays checkout's first paint. Typing filters
 *       the list by name or code; Up/Down/Enter/Escape and an outside
 *       click behave like every other disclosure in this file. No matches
 *       renders one non-selectable "<li class=is-empty>" instead of an
 *       empty list, with the theme's own translated copy read from
 *       data-storefront-country-no-results (never hardcoded here, since
 *       this file has no locale of its own).
 *
 *   <div data-storefront-init="post" data-storefront-url="/checkout"
 *        data-storefront-expose="id:draft">
 *       On page load, POSTs once to the URL and copies response.id onto
 *       this element as data-draft="..." — so descendant elements can use
 *       {draft} tokens in their own URLs, resolved the same way as above.
 *       This is how a checkout page bootstraps "the current draft" with
 *       zero page-specific script.
 *
 *   <div data-storefront-load="/cart" data-storefront-list-kind="...">
 *       On page load and on every "storefront:refresh" event, fetches the
 *       URL and renders the JSON response declaratively:
 *         - any descendant [data-field="x"] (not inside a <template>) is
 *           filled from response.x — form controls get their .value set,
 *           everything else gets .textContent;
 *         - a <template data-storefront-list="items"> is cloned once per
 *           entry in response.items into the nearest
 *           [data-storefront-slot="items"] (or its own parent), filling
 *           [data-field] the same way inside each clone, copying fields
 *           onto attributes per data-field-attr="a:data-a,b:data-b", and
 *           showing/hiding [data-field-if="available"] (or the negated
 *           "!available") by that field's truthiness;
 *         - [data-storefront-when="empty:items"] / [data-storefront-when="items:items"]
 *           (suffixed with the list's own key, since one container can
 *           hold more than one list) are shown/hidden depending on
 *           whether that particular list came back empty;
 *         - the raw response is also broadcast as a "storefront:data"
 *           event on the container, for the rare theme that wants extra,
 *           fully custom rendering alongside the generic one.
 *
 * Nothing here knows what a "cart" or a "coupon" is — it is a generic
 * fetch-bind-template engine. The one deliberate exception is delivery
 * options, which the checkout API nests as
 * option.pickup_points[] (a method can offer several pickup points) —
 * flattening that one, genuinely storefront-specific shape into flat,
 * selectable rows is real domain knowledge, so it lives here (in core),
 * not duplicated in every theme. See flattenListKind() below.
 */
(function () {
    'use strict';

    /* ---------------------------------------------------------------
     * CSRF-aware JSON fetch
     * --------------------------------------------------------------- */

    function readCookie(name) {
        const match = document.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'));
        return match ? decodeURIComponent(match[1]) : null;
    }

    async function fetchJson(url, options) {
        options = options || {};
        const headers = Object.assign({ Accept: 'application/json', 'Content-Type': 'application/json' }, options.headers || {});
        const xsrf = readCookie('XSRF-TOKEN');
        if (xsrf) {
            headers['X-XSRF-TOKEN'] = xsrf;
        }

        const response = await fetch(url, Object.assign({ credentials: 'same-origin' }, options, { headers }));
        let body = null;
        try {
            body = await response.json();
        } catch (error) {
            body = null;
        }

        return { ok: response.ok, status: response.status, body: body };
    }

    /* ---------------------------------------------------------------
     * {token} resolution — walk up from an element to find the nearest
     * ancestor (or itself) declaring data-<token>, in kebab-case.
     * --------------------------------------------------------------- */

    function toKebabCase(name) {
        return name.replace(/_/g, '-');
    }

    function resolveTokens(template, fromElement) {
        return template.replace(/\{([a-zA-Z0-9_]+)\}/g, function (match, token) {
            const holder = fromElement.closest('[data-' + toKebabCase(token) + ']');
            return holder ? holder.dataset[toCamelCase(token)] : match;
        });
    }

    function toCamelCase(name) {
        return name.replace(/_([a-z0-9])/g, function (_, letter) { return letter.toUpperCase(); });
    }

    /* ---------------------------------------------------------------
     * Form serialization with PHP/Rails-style bracket nesting:
     * "billing_address[city]" -> { billing_address: { city: value } },
     * "tags[]" -> { tags: [value, ...] }. Checkboxes always serialize to
     * a boolean (true/false), never simply omitted, so the API always
     * receives an explicit value for flags like ships_to_different_address.
     * --------------------------------------------------------------- */

    function assignPath(target, path, value) {
        const keys = path.replace(/\]/g, '').split('[');
        let node = target;
        for (let i = 0; i < keys.length - 1; i++) {
            let key = keys[i];
            if (key === '') {
                key = String(Object.keys(node).length);
            }
            if (!(key in node)) {
                node[key] = {};
            }
            node = node[key];
        }
        let lastKey = keys[keys.length - 1];
        if (lastKey === '') {
            lastKey = String(Object.keys(node).length);
        }
        node[lastKey] = value;
    }

    function serializeForm(form) {
        const data = {};

        for (const element of form.elements) {
            if (!element.name || element.disabled) {
                continue;
            }

            if (element.type === 'radio') {
                // form.elements already includes radios associated purely
                // via form="this-form-id" even when they live outside the
                // <form> tag in the DOM (e.g. a delivery-option row cloned
                // into a sibling summary panel) — checking .checked
                // directly (a plain element property) works regardless of
                // where in the document the element actually sits, unlike
                // a form.querySelector() lookup which only searches DOM
                // descendants.
                if (element.checked) {
                    assignPath(data, element.name, element.value);
                }
                continue;
            }

            if (element.type === 'checkbox') {
                assignPath(data, element.name, element.checked);
                continue;
            }

            if (element.tagName === 'SELECT' && element.multiple) {
                assignPath(data, element.name, Array.from(element.selectedOptions).map(function (o) { return o.value; }));
                continue;
            }

            assignPath(data, element.name, element.value === '' ? null : element.value);
        }

        return data;
    }

    function cssEscape(value) {
        return window.CSS && CSS.escape ? CSS.escape(value) : value.replace(/["\\]/g, '\\$&');
    }

    /* Radios/selects placed outside a <form> associate to it via form="id"
     * (native HTML) — form.elements already includes those, so
     * serializeForm() picks them up with no extra code. */

    /* ---------------------------------------------------------------
     * Messages and field errors
     * --------------------------------------------------------------- */

    function showMessage(scopeElement, state, text) {
        const target = scopeElement.matches('[data-storefront-message]')
            ? scopeElement
            : scopeElement.querySelector('[data-storefront-message]');
        if (!target) {
            return;
        }
        target.textContent = text || '';
        target.setAttribute('data-storefront-state', state);
    }

    function showFieldErrors(scopeElement, errors) {
        scopeElement.querySelectorAll('[data-storefront-error-for]').forEach(function (el) {
            el.textContent = '';
        });
        if (!errors) {
            return;
        }
        Object.keys(errors).forEach(function (field) {
            const el = scopeElement.querySelector('[data-storefront-error-for="' + cssEscape(field) + '"]');
            if (el) {
                el.textContent = Array.isArray(errors[field]) ? errors[field][0] : String(errors[field]);
            }
        });
    }

    function applyRedirect(template, fromElement, body) {
        if (!template) {
            return;
        }
        const withTokens = resolveTokens(template, fromElement);
        const withBody = withTokens.replace(/\{([a-zA-Z0-9_.]+)\}/g, function (match, path) {
            const value = path.split('.').reduce(function (acc, key) { return acc && acc[key] !== undefined ? acc[key] : undefined; }, body);
            return value !== undefined ? value : match;
        });
        window.location.href = withTokens === withBody ? withTokens : withBody;
    }

    function broadcastRefresh() {
        document.dispatchEvent(new CustomEvent('storefront:refresh'));
    }

    /* ---------------------------------------------------------------
     * data-storefront="ajax" forms
     * --------------------------------------------------------------- */

    function bindAjaxForms() {
        document.addEventListener('submit', async function (event) {
            const form = event.target.closest('[data-storefront="ajax"]');
            if (!form) {
                return;
            }
            event.preventDefault();

            // Read the raw attributes, not the .action/.method IDL
            // properties — those resolve to a normalized absolute URL
            // (percent-encoding "{" and "}", breaking token substitution)
            // and a GET/POST-only method respectively.
            const method = (form.dataset.storefrontMethod || form.getAttribute('method') || 'post').toUpperCase();
            const url = resolveTokens(form.getAttribute('action') || form.dataset.storefrontUrl, form);
            const result = await fetchJson(url, { method: method, body: JSON.stringify(serializeForm(form)) });

            if (result.ok) {
                showFieldErrors(form, null);
                showMessage(form, 'success', form.dataset.storefrontSuccessMessage || 'Zapisano.');
                broadcastRefresh();
                document.dispatchEvent(new CustomEvent('storefront:success', { detail: result.body }));
                applyRedirect(form.dataset.storefrontRedirect, form, result.body);
            } else {
                showFieldErrors(form, result.body && result.body.errors);
                showMessage(form, 'error', (result.body && result.body.message) || 'Nie udalo sie zapisac danych.');
                if (form.dataset.storefrontRefreshOnError !== undefined) {
                    broadcastRefresh();
                }
            }
        });
    }

    /* ---------------------------------------------------------------
     * data-storefront-action="post|put|delete" — one-off requests that
     * are not a form submission: a plain click for buttons/links, or a
     * change for an input reporting its own value (data-storefront-field
     * names which JSON key that value is sent under — e.g. a cart line's
     * quantity input PUTting {quantity: "3"} to its own row's URL).
     * --------------------------------------------------------------- */

    async function runAction(trigger) {
        const action = trigger.dataset.storefrontAction;
        if (action !== 'post' && action !== 'put' && action !== 'delete') {
            return;
        }
        if (trigger.dataset.storefrontConfirm && !window.confirm(trigger.dataset.storefrontConfirm)) {
            return;
        }

        const url = resolveTokens(trigger.dataset.storefrontUrl, trigger);
        const body = trigger.dataset.storefrontField ? JSON.stringify({ [trigger.dataset.storefrontField]: trigger.value }) : undefined;
        const result = await fetchJson(url, { method: action.toUpperCase(), body: body });

        if (result.ok) {
            broadcastRefresh();
            document.dispatchEvent(new CustomEvent('storefront:success', { detail: result.body }));
            applyRedirect(trigger.dataset.storefrontRedirect, trigger, result.body);
        } else {
            showMessage(trigger.closest('[data-storefront-message], body'), 'error', (result.body && result.body.message) || 'Operacja nie powiodla sie.');
            if (trigger.dataset.storefrontRefreshOnError !== undefined) {
                broadcastRefresh();
            }
        }
    }

    function bindActionButtons() {
        document.addEventListener('click', function (event) {
            const trigger = event.target.closest('[data-storefront-action]');
            if (trigger && !trigger.dataset.storefrontField) {
                runAction(trigger);
            }
        });
        document.addEventListener('change', function (event) {
            const trigger = event.target.closest('[data-storefront-action]');
            if (trigger && trigger.dataset.storefrontField) {
                runAction(trigger);
            }
        });
    }

    /* ---------------------------------------------------------------
     * data-storefront-show-if="field:value" — declarative conditional
     * visibility (a company-name field only when buyer_type is
     * "company", a shipping-address block only when a checkbox is
     * checked), so "show/hide this section" is never a reason to write
     * page-specific JS either.
     * --------------------------------------------------------------- */

    function matchesSingleShowIf(condition) {
        const [name, expected] = condition.split(':');
        const checkedOne = document.querySelector('input[name="' + cssEscape(name) + '"]:checked');
        if (checkedOne) {
            return checkedOne.type === 'checkbox' ? String(checkedOne.checked) === expected : checkedOne.value === expected;
        }
        const checkbox = document.querySelector('input[type="checkbox"][name="' + cssEscape(name) + '"]');
        return checkbox ? String(checkbox.checked) === expected : false;
    }

    // Comma-separated conditions are AND-ed — every one must hold, e.g. a
    // variant picker's block for one exact color+size combination.
    function matchesShowIf(condition) {
        return condition.split(',').every(matchesSingleShowIf);
    }

    function refreshShowIf() {
        document.querySelectorAll('[data-storefront-show-if]').forEach(function (el) {
            el.hidden = !matchesShowIf(el.dataset.storefrontShowIf);
        });

        // A group of alternative show-if blocks (one per real variant, say)
        // can end up with none matching the current picks — a combination
        // nobody generated a variant for. Reveal a designated fallback
        // inside the same container rather than silently showing nothing.
        document.querySelectorAll('[data-storefront-show-if-container]').forEach(function (container) {
            const fallback = container.querySelector('[data-storefront-show-if-fallback]');
            if (!fallback) {
                return;
            }
            const anyVisible = Array.from(container.querySelectorAll('[data-storefront-show-if]')).some(function (el) { return !el.hidden; });
            fallback.hidden = anyVisible;
        });
    }

    function bindShowIf() {
        document.addEventListener('change', function (event) {
            if (event.target.name) {
                refreshShowIf();
            }
        });
    }

    /* ---------------------------------------------------------------
     * Variant deep-link URL sync — keeps the visible address bar in step
     * with the picker without a page reload, mirroring
     * CatalogController::canonicalVariantSegments(). replaceState (not
     * pushState): reclicking swatches shouldn't fill Back history with one
     * entry per combination. A server-rendered preselection (@checked in
     * variant-picker.blade.php) needs no equivalent on load — the URL is
     * already correct in that case.
     * --------------------------------------------------------------- */

    function bindVariantUrlSync() {
        document.addEventListener('change', function (event) {
            if (!event.target.hasAttribute('data-storefront-value-slug')) {
                return;
            }
            const container = event.target.closest('[data-storefront-variant-base-url]');
            if (!container) {
                return;
            }

            const groups = {};
            container.querySelectorAll('input[data-storefront-axis-slug]').forEach(function (input) {
                (groups[input.name] = groups[input.name] || []).push(input);
            });

            const segments = [];
            for (const axisName of Object.keys(groups)) {
                const checked = groups[axisName].find(function (input) { return input.checked; });
                if (!checked) {
                    return; // incomplete selection — leave the URL alone
                }
                segments.push(checked.dataset.storefrontAxisSlug, checked.dataset.storefrontValueSlug);
            }

            const baseUrl = container.dataset.storefrontVariantBaseUrl;
            const path = segments.length > 0 ? baseUrl + '/' + segments.join('/') : baseUrl;
            window.history.replaceState(null, '', path);
        });
    }

    /* ---------------------------------------------------------------
     * data-storefront-step="±N" inside a [data-storefront-stepper] —
     * the +/- buttons of a quantity stepper (see file docblock).
     * --------------------------------------------------------------- */

    function bindStepper() {
        document.addEventListener('click', function (event) {
            const trigger = event.target.closest('[data-storefront-step]');
            if (!trigger) {
                return;
            }
            const wrapper = trigger.closest('[data-storefront-stepper]');
            const target = wrapper && wrapper.querySelector('input');
            if (!target) {
                return;
            }
            const delta = parseInt(trigger.dataset.storefrontStep, 10) || 0;
            const current = parseInt(target.value, 10) || 1;
            target.value = String(Math.max(1, current + delta));
            target.dispatchEvent(new Event('change', { bubbles: true }));
        });
    }

    /* ---------------------------------------------------------------
     * data-storefront-theme-toggle (see file docblock)
     * --------------------------------------------------------------- */

    function bindThemeToggle() {
        document.addEventListener('click', function (event) {
            const trigger = event.target.closest('[data-storefront-theme-toggle]');
            if (!trigger) {
                return;
            }
            const root = document.documentElement;
            const next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            if (next === 'dark') {
                root.setAttribute('data-theme', 'dark');
            } else {
                root.removeAttribute('data-theme');
            }
            document.cookie = 'storefront_theme=' + next + '; path=/; max-age=31536000; samesite=lax';
        });
    }

    /* ---------------------------------------------------------------
     * data-storefront-language-switcher (see file docblock)
     * --------------------------------------------------------------- */

    function bindLanguageSwitcher() {
        document.addEventListener('click', function (event) {
            document.querySelectorAll('[data-storefront-language-switcher][open]').forEach(function (details) {
                if (!details.contains(event.target)) {
                    details.removeAttribute('open');
                }
            });
        });
    }

    /* ---------------------------------------------------------------
     * data-storefront-submenu-toggle (see file docblock)
     * --------------------------------------------------------------- */

    function bindSubmenuToggle() {
        document.addEventListener('click', function (event) {
            const trigger = event.target.closest('[data-storefront-submenu-toggle]');
            if (trigger) {
                const submenu = trigger.closest('[data-storefront-submenu]');
                if (submenu) {
                    const open = !submenu.classList.contains('is-open');
                    submenu.classList.toggle('is-open', open);
                    trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
                }
                return;
            }

            document.querySelectorAll('[data-storefront-submenu].is-open').forEach(function (submenu) {
                if (!submenu.contains(event.target)) {
                    submenu.classList.remove('is-open');
                    const toggle = submenu.querySelector('[data-storefront-submenu-toggle]');
                    if (toggle) {
                        toggle.setAttribute('aria-expanded', 'false');
                    }
                }
            });
        });
    }

    /* ---------------------------------------------------------------
     * data-storefront-nav-toggle (see file docblock)
     * --------------------------------------------------------------- */

    function bindNavToggle() {
        function setNavOpen(nav, open) {
            nav.classList.toggle('is-open', open);
            const toggle = nav.querySelector('[data-storefront-nav-toggle]');
            if (toggle) {
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            }
            // Locks the page underneath a full-screen mobile drawer — set on
            // <html> (not the nav itself) since it must affect the whole
            // document's scroll, regardless of which theme's CSS the drawer uses.
            document.documentElement.classList.toggle('sf-nav-open', open);
        }

        document.addEventListener('click', function (event) {
            const toggle = event.target.closest('[data-storefront-nav-toggle]');
            if (toggle) {
                const nav = toggle.closest('[data-storefront-nav]');
                if (nav) {
                    setNavOpen(nav, !nav.classList.contains('is-open'));
                }
                return;
            }

            const closer = event.target.closest('[data-storefront-nav-close]');
            if (closer) {
                const nav = closer.closest('[data-storefront-nav]');
                if (nav) {
                    setNavOpen(nav, false);
                }
                return;
            }

            document.querySelectorAll('[data-storefront-nav].is-open').forEach(function (nav) {
                if (!nav.contains(event.target)) {
                    setNavOpen(nav, false);
                }
            });
        });

        document.addEventListener('keydown', function (event) {
            if (event.key !== 'Escape') {
                return;
            }
            document.querySelectorAll('[data-storefront-nav].is-open').forEach(function (nav) {
                setNavOpen(nav, false);
            });
        });
    }

    /* ---------------------------------------------------------------
     * data-storefront-carousel — accessible horizontal block carousel
     * --------------------------------------------------------------- */

    function bindCarousel() {
        function update(carousel) {
            const track = carousel.querySelector('[data-storefront-carousel-track]');
            const previous = carousel.querySelector('[data-storefront-carousel-prev]');
            const next = carousel.querySelector('[data-storefront-carousel-next]');
            if (!track || !previous || !next) {
                return;
            }
            previous.disabled = track.scrollLeft <= 2;
            next.disabled = track.scrollLeft + track.clientWidth >= track.scrollWidth - 2;
        }

        document.addEventListener('click', function (event) {
            const button = event.target.closest('[data-storefront-carousel-prev], [data-storefront-carousel-next]');
            if (!button) {
                return;
            }
            const carousel = button.closest('[data-storefront-carousel]');
            const track = carousel ? carousel.querySelector('[data-storefront-carousel-track]') : null;
            if (!carousel || !track) {
                return;
            }
            const direction = button.hasAttribute('data-storefront-carousel-prev') ? -1 : 1;
            const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            track.scrollBy({ left: direction * Math.max(240, track.clientWidth * 0.85), behavior: reducedMotion ? 'auto' : 'smooth' });
        });

        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('[data-storefront-carousel]').forEach(function (carousel) {
                const track = carousel.querySelector('[data-storefront-carousel-track]');
                if (!track) {
                    return;
                }
                track.addEventListener('scroll', function () { update(carousel); }, { passive: true });
                update(carousel);
            });
        });
        window.addEventListener('resize', function () {
            document.querySelectorAll('[data-storefront-carousel]').forEach(update);
        });
    }

    /* ---------------------------------------------------------------
     * data-storefront-country-select (see file docblock)
     * --------------------------------------------------------------- */

    let countryListPromise = null;

    function loadCountries() {
        if (!countryListPromise) {
            countryListPromise = fetchJson('/checkout/countries').then(function (result) {
                return result.ok && result.body && Array.isArray(result.body.countries) ? result.body.countries : [];
            });
        }
        return countryListPromise;
    }

    function bindCountrySelect() {
        function renderCountryOptions(widget, countries, query) {
            const list = widget.querySelector('.sf-country-options');
            const needle = query.trim().toLowerCase();
            const matches = needle === '' ? countries : countries.filter(function (country) {
                return country.name.toLowerCase().indexOf(needle) !== -1 || country.code.toLowerCase().indexOf(needle) !== -1;
            });
            list.innerHTML = '';

            if (matches.length === 0) {
                const empty = document.createElement('li');
                empty.className = 'is-empty';
                empty.textContent = widget.dataset.storefrontCountryNoResults || 'No matching country.';
                list.appendChild(empty);
                return;
            }

            matches.forEach(function (country) {
                const item = document.createElement('li');
                item.setAttribute('role', 'option');
                item.dataset.value = country.code;
                item.textContent = country.flag + ' ' + country.name;
                list.appendChild(item);
            });
        }

        function openCountryList(widget, countries, query) {
            renderCountryOptions(widget, countries, query);
            widget.querySelector('.sf-country-options').hidden = false;
            widget.querySelector('.sf-country-search').setAttribute('aria-expanded', 'true');
        }

        function closeCountryList(widget) {
            widget.querySelector('.sf-country-options').hidden = true;
            widget.querySelector('.sf-country-search').setAttribute('aria-expanded', 'false');
        }

        function selectCountry(widget, code, label) {
            const hidden = widget.querySelector('[data-storefront-country-value]');
            hidden.value = code;
            hidden.dispatchEvent(new Event('change', { bubbles: true }));
            widget.querySelector('.sf-country-search').value = label;
            closeCountryList(widget);
        }

        // Pre-fills the visible search box for a widget whose hidden field
        // already has a value once the list arrives — today's checkout
        // form always starts empty, but a theme/plugin reusing this widget
        // elsewhere (a persisted draft, an edited address) may not.
        document.querySelectorAll('[data-storefront-country-select]').forEach(function (widget) {
            const hidden = widget.querySelector('[data-storefront-country-value]');
            if (!hidden.value) {
                return;
            }
            loadCountries().then(function (countries) {
                const current = countries.find(function (country) { return country.code === hidden.value; });
                if (current) {
                    widget.querySelector('.sf-country-search').value = current.flag + ' ' + current.name;
                }
            });
        });

        document.addEventListener('focusin', function (event) {
            const search = event.target.closest('.sf-country-search');
            if (!search) {
                return;
            }
            const widget = search.closest('[data-storefront-country-select]');
            loadCountries().then(function (countries) { openCountryList(widget, countries, search.value); });
        });

        document.addEventListener('input', function (event) {
            const search = event.target.closest('.sf-country-search');
            if (!search) {
                return;
            }
            const widget = search.closest('[data-storefront-country-select]');
            widget.querySelector('[data-storefront-country-value]').value = '';
            loadCountries().then(function (countries) { openCountryList(widget, countries, search.value); });
        });

        document.addEventListener('click', function (event) {
            const option = event.target.closest('[data-storefront-country-select] .sf-country-options li');
            if (option && option.dataset.value) {
                selectCountry(option.closest('[data-storefront-country-select]'), option.dataset.value, option.textContent);
                return;
            }

            document.querySelectorAll('[data-storefront-country-select]').forEach(function (widget) {
                if (!widget.contains(event.target)) {
                    closeCountryList(widget);
                }
            });
        });

        document.addEventListener('keydown', function (event) {
            const widget = event.target.closest('[data-storefront-country-select]');
            if (!widget || widget.querySelector('.sf-country-options').hidden) {
                return;
            }
            if (event.key === 'Escape') {
                closeCountryList(widget);
                return;
            }
            if (event.key !== 'ArrowDown' && event.key !== 'ArrowUp' && event.key !== 'Enter') {
                return;
            }
            const items = Array.from(widget.querySelectorAll('.sf-country-options li:not(.is-empty)'));
            if (items.length === 0) {
                return;
            }
            event.preventDefault();
            let index = items.findIndex(function (item) { return item.classList.contains('is-active'); });
            if (event.key === 'Enter') {
                if (index !== -1) {
                    selectCountry(widget, items[index].dataset.value, items[index].textContent);
                }
                return;
            }
            index = event.key === 'ArrowDown' ? (index + 1) % items.length : (index <= 0 ? items.length - 1 : index - 1);
            items.forEach(function (item) { item.classList.remove('is-active'); });
            items[index].classList.add('is-active');
            items[index].scrollIntoView({ block: 'nearest' });
        });
    }

    /* ---------------------------------------------------------------
     * data-storefront-auto-submit inputs
     * --------------------------------------------------------------- */

    function bindAutoSubmit() {
        document.addEventListener('change', function (event) {
            const el = event.target.closest('[data-storefront-auto-submit]');
            if (!el) {
                return;
            }
            const form = el.form || document.getElementById(el.getAttribute('form'));
            if (form) {
                form.requestSubmit ? form.requestSubmit() : form.dispatchEvent(new Event('submit', { cancelable: true }));
            }
        });
    }

    /* ---------------------------------------------------------------
     * Delivery method -> pickup point pairing. The only other piece of
     * genuine storefront domain knowledge in this file (besides
     * flattenListKind): a delivery_method_id radio that represents a
     * pickup point (flattened above) carries that point's id as
     * data-pickup-point-id via data-field-attr; picking the radio must
     * copy it into the single shared delivery_pickup_point_id field, since
     * there is exactly one such field per form, not one per rendered row.
     * --------------------------------------------------------------- */

    function bindDeliveryPickupPointSync() {
        document.addEventListener('change', function (event) {
            const radio = event.target.closest('input[name="delivery_method_id"]');
            if (!radio) {
                return;
            }
            const target = document.querySelector('[name="delivery_pickup_point_id"]');
            if (target) {
                target.value = radio.dataset.pickupPointId || '';
            }
        });
    }

    /* ---------------------------------------------------------------
     * data-storefront-init="post" bootstrap-and-expose
     * --------------------------------------------------------------- */

    async function runInitBlocks() {
        const elements = document.querySelectorAll('[data-storefront-init]');
        for (const el of elements) {
            const method = el.dataset.storefrontInit.toUpperCase();
            const url = resolveTokens(el.dataset.storefrontUrl, el);
            const result = await fetchJson(url, { method: method });
            if (!result.ok) {
                showMessage(el, 'error', (result.body && result.body.message) || 'Nie udalo sie przygotowac strony.');
                continue;
            }
            (el.dataset.storefrontExpose || '').split(',').map(function (s) { return s.trim(); }).filter(Boolean).forEach(function (pair) {
                const [source, attr] = pair.split(':');
                if (source && attr && result.body[source] !== undefined) {
                    el.setAttribute('data-' + toKebabCase(attr), result.body[source]);
                }
            });
            // No broadcastRefresh() here — refreshAll() (the only caller,
            // once per page load) already follows this with the same
            // load-regions/cart-count pass unconditionally, so pages
            // without any init block still populate on load.
        }
    }

    /* ---------------------------------------------------------------
     * data-storefront-load regions: fetch + declarative render
     * --------------------------------------------------------------- */

    // A container's own data-field/data-storefront-list may reach into a
    // nested response shape (e.g. checkout's price.hash,
    // price.delivery.options) — dot paths, top-level only. Items cloned
    // from a list template stay flat (every list the runtime renders —
    // cart lines, delivery options, payment methods — is already a flat
    // object per entry), so fillField() below does not need this.
    function getPath(object, path) {
        return path.split('.').reduce(function (acc, key) {
            return acc && acc[key] !== undefined ? acc[key] : undefined;
        }, object);
    }

    function fillField(element, value) {
        if (value === undefined || value === null) {
            value = '';
        }
        if ('value' in element && (element.tagName === 'INPUT' || element.tagName === 'SELECT' || element.tagName === 'TEXTAREA')) {
            element.value = value;
        } else {
            element.textContent = value;
        }
    }

    /* Only genuinely storefront-specific reshaping in this file: a
     * delivery quote's pickup_points[] becomes one flat, selectable row
     * per point instead of a method with a nested picker — every other
     * list (cart lines, coupons, payment methods) is rendered as-is. */
    function flattenListKind(kind, items) {
        if (kind !== 'delivery-options') {
            return items;
        }
        const flattened = [];
        items.forEach(function (option) {
            if (option.kind === 'pickup' && option.pickup_points && option.pickup_points.length > 0) {
                option.pickup_points.forEach(function (point) {
                    flattened.push(Object.assign({}, option, {
                        pickup_point_id: point.id,
                        label: option.name + ' \u2014 ' + point.name,
                    }));
                });
            } else {
                flattened.push(Object.assign({ pickup_point_id: '', label: option.name }, option));
            }
        });
        return flattened;
    }

    function renderList(container, template, items) {
        const slot = container.querySelector('[data-storefront-slot="' + template.dataset.storefrontList + '"]') || template.parentElement;

        // A list re-renders wholesale on every load (e.g. picking a
        // delivery method reloads the whole price, which rebuilds the
        // payment-methods list too) — record what was checked by radio
        // group name first, so a still-valid choice survives being
        // replaced by a brand new set of cloned nodes instead of silently
        // reverting to the first row every time.
        const previouslyChecked = {};
        slot.querySelectorAll('input[type="radio"]:checked').forEach(function (el) { previouslyChecked[el.name] = el.value; });

        slot.querySelectorAll('[data-storefront-cloned]').forEach(function (el) { el.remove(); });

        const seenGroups = new Set();

        items.forEach(function (item, index) {
            const clone = template.content.cloneNode(true);
            const root = clone.firstElementChild;
            root.setAttribute('data-storefront-cloned', '');

            root.querySelectorAll('[data-field]').forEach(function (el) {
                fillField(el, item[el.dataset.field]);
            });
            if (root.hasAttribute('data-field')) {
                fillField(root, item[root.dataset.field]);
            }

            // data-field-attr copies a field onto an HTML attribute
            // instead of text/value — for anything a plain data-field
            // (text content, or .value on a form control) cannot express,
            // e.g. stamping the row's id onto data-variant-id so a
            // sibling "remove this row" button can find it via {token}
            // resolution. Works the same on the clone's root or any of
            // its descendants, exactly like data-field does.
            [root].concat(Array.from(root.querySelectorAll('[data-field-attr]'))).forEach(function (el) {
                (el.dataset.fieldAttr || '').split(',').map(function (s) { return s.trim(); }).filter(Boolean).forEach(function (pair) {
                    const [source, attr] = pair.split(':');
                    if (source && attr && item[source] !== undefined && item[source] !== null) {
                        el.setAttribute(attr, item[source]);
                    }
                });
            });

            // data-field-if="available" (or "!available") shows/hides an
            // element within the row based on a field's truthiness — e.g.
            // an "unavailable" badge that should only appear on the rows
            // where the API actually said available: false.
            [root].concat(Array.from(root.querySelectorAll('[data-field-if]'))).forEach(function (el) {
                const expr = el.dataset.fieldIf;
                if (!expr) {
                    return;
                }
                const negate = expr.startsWith('!');
                const value = Boolean(item[negate ? expr.slice(1) : expr]);
                el.hidden = negate ? value : !value;
            });

            const radio = root.matches('input[type="radio"]') ? root : root.querySelector('input[type="radio"]');
            if (radio) {
                const isFirstOfGroup = !seenGroups.has(radio.name);
                seenGroups.add(radio.name);
                if (radio.name in previouslyChecked) {
                    radio.checked = radio.value === previouslyChecked[radio.name];
                } else if (isFirstOfGroup && (radio.hasAttribute('data-storefront-preselect') || root.hasAttribute('data-storefront-preselect'))) {
                    radio.checked = true;
                }
            }

            slot.appendChild(clone);
        });
    }

    // Several triggers can each ask for a reload in close succession (an
    // auto-submitted delivery choice, the address save it followed, a
    // coupon change) — running them concurrently would let two passes
    // read the same "what's currently checked" snapshot before either has
    // appended its clones, so a real selection could render back to
    // unchecked. Coalesce instead: only one pass runs at a time, and a
    // call that arrives mid-pass just marks another pass as owed once the
    // current one finishes, rather than overlapping with it.
    let loadRegionsRunning = false;
    let loadRegionsPending = false;

    async function runLoadRegions() {
        if (loadRegionsRunning) {
            loadRegionsPending = true;
            return;
        }
        loadRegionsRunning = true;
        do {
            loadRegionsPending = false;
            await runLoadRegionsOnce();
        } while (loadRegionsPending);
        loadRegionsRunning = false;
    }

    async function runLoadRegionsOnce() {
        const containers = document.querySelectorAll('[data-storefront-load]');
        for (const container of containers) {
            const url = resolveTokens(container.dataset.storefrontLoad, container);
            const result = await fetchJson(url);
            if (!result.ok) {
                continue;
            }
            const body = result.body;

            container.querySelectorAll(':scope [data-field]').forEach(function (el) {
                // Excludes both an inert <template>'s own contents AND any
                // already-cloned row a PRIOR renderList() call appended
                // (data-storefront-cloned) — a cloned row's data-field
                // names are keyed into its OWN list item (e.g. a delivery
                // radio's "method_id"), not this container's top-level
                // body, so filling it from getPath(body, ...) here would
                // both show the wrong value AND — for a radio — overwrite
                // the .value renderList()'s own upcoming "previously
                // checked" snapshot depends on to restore the selection,
                // silently unchecking every option after any reload.
                if (el.closest('template') || el.closest('[data-storefront-cloned]')) {
                    return;
                }
                fillField(el, getPath(body, el.dataset.field));
            });

            container.querySelectorAll('template[data-storefront-list]').forEach(function (template) {
                const listKey = template.dataset.storefrontList;
                const items = flattenListKind(template.dataset.storefrontListKind || listKey, getPath(body, listKey) || []);
                renderList(container, template, items);

                // Suffixed with the list key (empty:lines, items:lines) —
                // a container can hold more than one list (cart lines and
                // its attached coupons, say), so an unqualified
                // data-storefront-when would not know which list's
                // emptiness it should reflect.
                container.querySelectorAll('[data-storefront-when="empty:' + listKey + '"]').forEach(function (el) { el.hidden = items.length > 0; });
                container.querySelectorAll('[data-storefront-when="items:' + listKey + '"]').forEach(function (el) { el.hidden = items.length === 0; });
            });

            container.dispatchEvent(new CustomEvent('storefront:data', { detail: body }));
        }
    }

    /* ---------------------------------------------------------------
     * Cart count badge — present in the header on every page, so it is
     * refreshed independently of any data-storefront-load region.
     * --------------------------------------------------------------- */

    async function refreshCartCount() {
        const badges = document.querySelectorAll('[data-storefront="cart-count"]');
        if (badges.length === 0) {
            return;
        }
        const result = await fetchJson('/cart');
        if (!result.ok) {
            return;
        }
        const total = (result.body.lines || []).reduce(function (sum, line) { return sum + Number(line.quantity); }, 0);
        badges.forEach(function (el) { el.textContent = total; });
    }

    /* ---------------------------------------------------------------
     * Boot
     * --------------------------------------------------------------- */

    async function refreshAll() {
        refreshShowIf();
        await runInitBlocks();
        await Promise.all([runLoadRegions(), refreshCartCount()]);
    }

    bindAjaxForms();
    bindActionButtons();
    bindShowIf();
    bindVariantUrlSync();
    bindStepper();
    bindThemeToggle();
    bindLanguageSwitcher();
    bindSubmenuToggle();
    bindNavToggle();
    bindCarousel();
    bindCountrySelect();
    bindDeliveryPickupPointSync(); // must run before bindAutoSubmit() below, so the hidden field it updates is already correct by the time an auto-submit reads the form
    bindAutoSubmit();
    document.addEventListener('storefront:refresh', function () {
        runLoadRegions();
        refreshCartCount();
    });
    document.addEventListener('DOMContentLoaded', refreshAll);

    window.Storefront = { fetchJson: fetchJson, refresh: broadcastRefresh };
})();
