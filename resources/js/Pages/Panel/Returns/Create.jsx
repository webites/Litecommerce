import Button from '@/Components/ui/Button';
import Card from '@/Components/ui/Card';
import Checkbox from '@/Components/ui/Checkbox';
import Input from '@/Components/ui/Input';
import PageHeader from '@/Components/ui/PageHeader';
import Select from '@/Components/ui/Select';
import Table from '@/Components/ui/Table';
import Textarea from '@/Components/ui/Textarea';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTranslate } from '@/lib/i18n';
import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';

function money(value, currency, places) { const [whole, fraction = ''] = String(value).split('.'); return `${whole}${places ? `,${fraction.padEnd(places, '0').slice(0, places)}` : ''} ${currency}`; }

export default function Create({ order, lines, reasons }) {
    const t = useTranslate();
    const [selected, setSelected] = useState({});
    const form = useForm({ comment: '', lines: [] });

    const toggle = (line, checked) => {
        setSelected((current) => {
            const next = { ...current };
            if (checked) next[line.id] = { quantity: line.remaining_quantity, reason_id: '', reason_comment: '' };
            else delete next[line.id];

            return next;
        });
    };
    const setLineField = (lineId, field, value) => {
        setSelected((current) => ({ ...current, [lineId]: { ...current[lineId], [field]: value } }));
    };
    const submit = (event) => {
        event.preventDefault();
        form.transform((data) => ({
            comment: data.comment,
            lines: Object.entries(selected).map(([lineId, values]) => ({
                order_line_id: Number(lineId), quantity: values.quantity,
                reason_id: values.reason_id || null, reason_comment: values.reason_comment || null,
            })),
        }));
        form.post(route('panel.orders.returns.store', order.id));
    };
    const anySelected = Object.keys(selected).length > 0;

    return (
        <AuthenticatedLayout header={<PageHeader title={t('returns.create_page_title', 'New return request')} description={t('returns.order_label', 'Order :id', { id: order.id })} actions={<Button href={route('panel.orders.show', order.id)} variant="outline" size="sm">{t('returns.back_to_order', 'Back to order')}</Button>} />}>
            <Head title={t('returns.create_page_title', 'New return request')} />
            <div style={{ padding: 24, maxWidth: 900 }}>
                <Card description={t('returns.create_card_description', 'Select the affected lines and set the quantity being returned.')}>
                    <form onSubmit={submit} style={{ display: 'flex', flexDirection: 'column', gap: 20 }}>
                        <Table columns={[
                            { header: '', render: (line) => <Checkbox checked={Boolean(selected[line.id])} onChange={(checked) => toggle(line, checked)} /> },
                            { header: t('returns.column_line', 'Line'), render: (line) => <div><strong>{line.display_name}</strong><div style={{ fontFamily: 'var(--font-mono)', fontSize: 12, color: 'var(--text-tertiary)' }}>{line.sku}</div></div> },
                            { header: t('returns.column_ordered', 'Ordered'), align: 'right', render: (line) => line.ordered_quantity },
                            { header: t('returns.column_gross', 'Gross'), align: 'right', render: (line) => money(line.gross_amount, order.currency_code, order.currency_decimal_places) },
                            {
                                header: t('returns.column_return_quantity', 'Quantity to return'), align: 'right', render: (line) => selected[line.id] ? (
                                    <Input
                                        style={{ maxWidth: 120 }}
                                        value={selected[line.id].quantity}
                                        onChange={(e) => setLineField(line.id, 'quantity', e.target.value)}
                                    />
                                ) : `≤ ${line.remaining_quantity}`,
                            },
                            {
                                header: t('returns.column_reason', 'Reason'), render: (line) => selected[line.id] && (
                                    <Select
                                        style={{ minWidth: 160 }}
                                        placeholder={t('returns.select_placeholder', 'Select')}
                                        value={selected[line.id].reason_id}
                                        options={reasons.map((r) => ({ label: r.name, value: r.id }))}
                                        onChange={(e) => setLineField(line.id, 'reason_id', e.target.value)}
                                    />
                                ),
                            },
                        ]} rows={lines} />
                        {lines.length === 0 && <div style={{ fontSize: 13, color: 'var(--text-tertiary)' }}>{t('returns.all_lines_returned', 'All lines of this order have already been fully returned.')}</div>}

                        {anySelected && (
                            <div style={{ display: 'flex', flexDirection: 'column', gap: 14, borderTop: '1px solid var(--border-subtle)', paddingTop: 16 }}>
                                {Object.entries(selected).map(([lineId, values]) => {
                                    const line = lines.find((l) => String(l.id) === lineId);

                                    return (
                                        <Textarea
                                            key={lineId}
                                            label={t('returns.line_comment_label', 'Request comment — :name', { name: line?.display_name })}
                                            rows={2}
                                            value={values.reason_comment}
                                            onChange={(v) => setLineField(lineId, 'reason_comment', v)}
                                        />
                                    );
                                })}
                            </div>
                        )}

                        <Textarea label={t('returns.general_comment_label', 'General comment (optional)')} rows={3} value={form.data.comment} error={form.errors.comment} onChange={(v) => form.setData('comment', v)} />
                        {form.errors.lines && <span style={{ fontSize: 12, color: 'var(--danger-text)' }}>{form.errors.lines}</span>}
                        <div><Button type="submit" disabled={!anySelected} loading={form.processing}>{t('returns.create_submit', 'Create request')}</Button></div>
                    </form>
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
