import { formatMoney } from '@/lib/money';

export const emptyPoint = (countries) => ({
    name: '', country_code: countries.some((c) => c.code === 'PL') ? 'PL' : '', postal_code: '', city: '', region: '',
    address_line_1: '', address_line_2: '', instructions: {}, active: true,
});

export const emptyRule = () => ({ rule_type: 'country_rate', country_code: '', continent_code: '', price_amount: '', currency_overrides: [] });

export const hasActivePoint = (points) => points.some((point) => point.active);

export const hasRate = (rules) => rules.some((rule) => rule.rule_type !== 'exclude' && rule.price_amount !== null && rule.price_amount !== '');

export function isVisibleToCustomers(kind, points, rules) {
    return kind === 'pickup' ? hasActivePoint(points) : hasRate(rules);
}

export function cleanInstructions(instructions) {
    return Object.fromEntries(Object.entries(instructions ?? {}).filter(([, value]) => typeof value === 'string' && value.trim() !== ''));
}

export function normalizeInstructions(instructions) {
    return Array.isArray(instructions) ? {} : (instructions ?? {});
}

export function instructionsFor(instructions, locale) {
    const values = normalizeInstructions(instructions);

    return values[locale] || Object.values(values).find((value) => typeof value === 'string' && value.trim() !== '') || '';
}

export function basicFields(method) {
    return {
        kind: method.kind, name: method.name, active: method.active,
        price_amount: method.price_amount ?? '', free_threshold_amount: method.free_threshold_amount ?? '',
        tax_rate_id: method.tax_rate_id ?? '', supports_cash_on_delivery: method.supports_cash_on_delivery,
        supports_pay_on_pickup: method.supports_pay_on_pickup, pickup_deadline_days: method.pickup_deadline_days ?? '',
    };
}

export function priceSummary({ kind, price_amount: price, rules }, t, currency) {
    if (kind === 'pickup') {
        return price && Number(price) > 0 ? formatMoney(price, currency) : t('delivery.summary_pickup_free', 'Free');
    }

    const prices = rules
        .filter((rule) => rule.rule_type !== 'exclude' && rule.price_amount !== null && rule.price_amount !== '')
        .map((rule) => Number(rule.price_amount));

    if (prices.length === 0) {
        return t('delivery.summary_no_rates', 'No rates yet');
    }

    const lowest = Math.min(...prices);

    return lowest > 0
        ? t('delivery.summary_from', 'from :amount', { amount: formatMoney(lowest, currency) })
        : t('delivery.summary_pickup_free', 'Free');
}
