const locale = () => document.documentElement.lang || 'en';

const timezone = () => document.querySelector('meta[name="store-timezone"]')?.content || undefined;

const formatters = new Map();

function dateFormatter(options) {
    const key = `${locale()}|${timezone()}|${JSON.stringify(options)}`;

    if (!formatters.has(key)) {
        try {
            formatters.set(key, new Intl.DateTimeFormat(locale(), { ...options, timeZone: timezone() }));
        } catch {
            formatters.set(key, new Intl.DateTimeFormat(locale(), options));
        }
    }

    return formatters.get(key);
}

function parse(value) {
    const moment = new Date(value);

    return Number.isNaN(moment.getTime()) ? null : moment;
}

/** Date and time in the store time zone and the panel locale; `empty` is returned for a missing value. */
export function formatDateTime(value, empty = '—') {
    const moment = value ? parse(value) : null;

    return moment ? dateFormatter({ dateStyle: 'medium', timeStyle: 'short' }).format(moment) : empty;
}

export function formatDate(value, empty = '—') {
    const moment = value ? parse(value) : null;

    return moment ? dateFormatter({ dateStyle: 'medium' }).format(moment) : empty;
}

/** Decimal quantity string ("2.000000") without trailing zeros and with locale separators. */
export function formatQuantity(value, empty = '—') {
    if (value === null || value === undefined || value === '') return empty;

    const number = Number(value);

    return Number.isFinite(number) ? new Intl.NumberFormat(locale(), { maximumFractionDigits: 6 }).format(number) : String(value);
}

/** Percentage value ("23.00") with locale separators and a percent sign, e.g. "23%". */
export function formatPercent(value, empty = '—') {
    if (value === null || value === undefined || value === '') return empty;

    const number = Number(value);

    return Number.isFinite(number) ? new Intl.NumberFormat(locale(), { style: 'percent', maximumFractionDigits: 2 }).format(number / 100) : String(value);
}

/** Localized country name for an ISO code; an unknown code is returned unchanged. */
export function formatCountry(code) {
    if (!code) return '';

    try {
        return new Intl.DisplayNames([locale()], { type: 'region' }).of(String(code).toUpperCase()) || code;
    } catch {
        return code;
    }
}

/** Address snapshot as one display line. */
export function formatAddress(address, empty = '—') {
    if (!address) return empty;

    const locality = ['US', 'CA', 'AU'].includes(String(address.country_code).toUpperCase())
        ? [address.city, address.region, address.postal_code]
        : [address.postal_code, address.city];

    return [
        address.recipient_name, address.company_name, address.address_line_1, address.address_line_2,
        locality.filter(Boolean).join(' '), formatCountry(address.country_code),
    ].filter(Boolean).join(', ') || empty;
}
