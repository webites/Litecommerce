const formatters = new Map();

function formatterFor(locale, currency, places) {
    const key = `${locale}|${currency}|${places}`;

    if (!formatters.has(key)) {
        try {
            formatters.set(key, new Intl.NumberFormat(locale, { style: 'currency', currency, ...(places == null ? {} : { minimumFractionDigits: places, maximumFractionDigits: places }) }));
        } catch {
            formatters.set(key, null);
        }
    }

    return formatters.get(key);
}

/** Symbol, its position and the separators follow the locale and the currency (e.g. "100,00 zł" vs "$100.00"); without `places` the currency's own default applies. */
export function formatMoney(value, currency, places = null, locale = document.documentElement.lang || 'en') {
    if (value === null || value === undefined || value === '') return '—';

    const formatter = formatterFor(locale, currency, places);

    return formatter ? formatter.format(Number(value)) : `${value} ${currency}`;
}
