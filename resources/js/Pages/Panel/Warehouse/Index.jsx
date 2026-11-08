import Alert from '@/Components/ui/Alert';
import Badge from '@/Components/ui/Badge';
import Button from '@/Components/ui/Button';
import Checkbox from '@/Components/ui/Checkbox';
import EmptyState from '@/Components/ui/EmptyState';
import Input from '@/Components/ui/Input';
import Modal from '@/Components/ui/Modal';
import PageHeader from '@/Components/ui/PageHeader';
import Pagination from '@/Components/ui/Pagination';
import Table from '@/Components/ui/Table';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTranslate } from '@/lib/i18n';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { PackageSearch } from 'lucide-react';
import { useState } from 'react';

function availableTone(available) {
    const value = Number(available);

    if (value <= 0) return 'danger';
    if (value < 5) return 'warning';

    return 'success';
}

export default function Index({ items }) {
    const t = useTranslate();
    const status = usePage().props.flash?.status;
    const [editedVariantId, setEditedVariantId] = useState(null);
    const editingRow = items.data.find((row) => row.variant_id === editedVariantId) || null;

    const receiveForm = useForm({ quantity: '', reason: '' });
    const correctForm = useForm({ on_hand_quantity: '', reason: '' });

    const openStockModal = (row) => {
        setEditedVariantId(row.variant_id);
        receiveForm.reset();
        receiveForm.clearErrors();
        correctForm.setData({ on_hand_quantity: row.on_hand_quantity, reason: '' });
        correctForm.clearErrors();
    };

    const submitReceive = (event) => {
        event.preventDefault();
        receiveForm.post(route('panel.warehouse.variants.receive', editedVariantId), {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => receiveForm.reset(),
        });
    };

    const submitCorrect = (event) => {
        event.preventDefault();
        correctForm.put(route('panel.warehouse.variants.correct', editedVariantId), { preserveScroll: true, preserveState: true });
    };

    const toggleBackorder = (row, allowed) => {
        router.put(route('panel.warehouse.variants.backorder.update', row.variant_id), { allow_backorder: allowed }, {
            preserveScroll: true,
            preserveState: true,
        });
    };

    const pageTitle = t('warehouse.index.title', 'Warehouse');

    return (
        <AuthenticatedLayout header={<PageHeader title={pageTitle} description={t('warehouse.index.description', 'Single warehouse — stock, reservations, and movements')} />}>
            <Head title={pageTitle} />

            <div style={{ padding: 24, display: 'flex', flexDirection: 'column', gap: 16 }}>
                {status && <Alert tone="success">{status}</Alert>}

                {items.data.length === 0 ? (
                    <div style={{ border: '1px solid var(--border-subtle)', borderRadius: 'var(--radius-card)', background: 'var(--bg-surface)' }}>
                        <EmptyState
                            icon={PackageSearch}
                            title={t('warehouse.index.empty_title', 'No physical variants')}
                            description={t('warehouse.index.empty_description', 'Stock levels apply to physical products — add one in the catalog for it to show up here.')}
                        />
                    </div>
                ) : (
                    <>
                        <Table
                            columns={[
                                { header: t('warehouse.index.column_sku', 'SKU'), render: (r) => <span style={{ fontFamily: 'var(--font-mono)' }}>{r.sku}</span> },
                                { header: t('warehouse.index.column_variant', 'Variant'), key: 'name' },
                                { header: t('warehouse.index.column_on_hand', 'On hand'), align: 'right', render: (r) => r.on_hand_quantity },
                                { header: t('warehouse.index.column_reserved', 'Reserved'), align: 'right', render: (r) => r.reserved_quantity },
                                {
                                    header: t('warehouse.index.column_available', 'Available'),
                                    align: 'right',
                                    render: (r) => <Badge tone={availableTone(r.available_quantity)}>{r.available_quantity}</Badge>,
                                },
                                {
                                    header: t('warehouse.index.column_backorder', 'Backorder'),
                                    render: (r) => <Checkbox checked={r.allow_backorder} onChange={(checked) => toggleBackorder(r, checked)} />,
                                },
                                { header: '', align: 'right', render: (r) => <Button variant="outline" size="sm" onClick={() => openStockModal(r)}>{t('warehouse.index.correct', 'Correct')}</Button> },
                            ]}
                            rows={items.data}
                        />
                        <Pagination paginator={items} />
                    </>
                )}
            </div>

            <Modal
                show={editedVariantId !== null}
                title={`${t('warehouse.index.modal_title_prefix', 'Warehouse')}${editingRow ? ' — ' + editingRow.sku : ''}`}
                onClose={() => setEditedVariantId(null)}
                maxWidth={480}
            >
                <div style={{ display: 'flex', flexDirection: 'column', gap: 24 }}>
                    <form onSubmit={submitReceive} style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
                        <div style={{ fontSize: 14, fontWeight: 600, color: 'var(--text-primary)' }}>{t('warehouse.index.receive_title', 'Stock receipt')}</div>
                        <div style={{ fontSize: 12, color: 'var(--text-tertiary)' }}>{t('warehouse.index.receive_description', 'Adds a quantity to the current stock, e.g. a delivery from a supplier.')}</div>
                        <Input
                            label={t('warehouse.index.quantity', 'Quantity')}
                            type="number"
                            min="0"
                            step="any"
                            value={receiveForm.data.quantity}
                            error={receiveForm.errors.quantity}
                            onChange={(e) => receiveForm.setData('quantity', e.target.value)}
                            required
                        />
                        <Input
                            label={t('warehouse.index.reason_optional', 'Reason (optional)')}
                            value={receiveForm.data.reason}
                            error={receiveForm.errors.reason}
                            onChange={(e) => receiveForm.setData('reason', e.target.value)}
                        />
                        <div><Button type="submit" loading={receiveForm.processing}>{t('warehouse.index.save_receipt', 'Save receipt')}</Button></div>
                    </form>

                    <form onSubmit={submitCorrect} style={{ display: 'flex', flexDirection: 'column', gap: 14, borderTop: '1px solid var(--border-subtle)', paddingTop: 20 }}>
                        <div style={{ fontSize: 14, fontWeight: 600, color: 'var(--text-primary)' }}>{t('warehouse.index.correct_title', 'Stock correction')}</div>
                        <div style={{ fontSize: 12, color: 'var(--text-tertiary)' }}>{t('warehouse.index.correct_description', 'Sets the stock to the given value, e.g. after a stocktake. The difference is recorded as a stock movement.')}</div>
                        <Input
                            label={t('warehouse.index.new_stock', 'New stock')}
                            type="number"
                            min="0"
                            step="any"
                            value={correctForm.data.on_hand_quantity}
                            error={correctForm.errors.on_hand_quantity}
                            onChange={(e) => correctForm.setData('on_hand_quantity', e.target.value)}
                            required
                        />
                        <Input
                            label={t('warehouse.index.reason_optional', 'Reason (optional)')}
                            value={correctForm.data.reason}
                            error={correctForm.errors.reason}
                            onChange={(e) => correctForm.setData('reason', e.target.value)}
                        />
                        <div><Button type="submit" loading={correctForm.processing}>{t('warehouse.index.save_correction', 'Save correction')}</Button></div>
                    </form>
                </div>
            </Modal>
        </AuthenticatedLayout>
    );
}
