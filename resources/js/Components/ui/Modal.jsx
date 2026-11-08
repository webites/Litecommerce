import { Dialog, DialogPanel, Transition, TransitionChild } from '@headlessui/react';
import { X } from 'lucide-react';

/**
 * Modal primitive for the Litecommerce panel, built on the design system's
 * Card look rather than the Breeze default (resources/js/Components/Modal.jsx),
 * which is unstyled with the design tokens and does not follow the theme.
 */
export default function Modal({ show = false, title, onClose, maxWidth = 480, children }) {
    return (
        <Transition show={show} leave="duration-150">
            <Dialog as="div" className="fixed inset-0 z-50 flex items-center justify-center p-4" onClose={onClose}>
                <TransitionChild
                    enter="ease-out duration-200" enterFrom="opacity-0" enterTo="opacity-100"
                    leave="ease-in duration-150" leaveFrom="opacity-100" leaveTo="opacity-0"
                >
                    <div className="fixed inset-0" style={{ background: 'rgba(0,0,0,0.45)' }} />
                </TransitionChild>

                <TransitionChild
                    enter="ease-out duration-200" enterFrom="opacity-0 scale-95" enterTo="opacity-100 scale-100"
                    leave="ease-in duration-150" leaveFrom="opacity-100 scale-100" leaveTo="opacity-0 scale-95"
                >
                    <DialogPanel
                        style={{
                            position: 'relative',
                            width: '100%',
                            maxWidth,
                            maxHeight: '85vh',
                            overflowY: 'auto',
                            background: 'var(--bg-surface)',
                            border: '1px solid var(--border-subtle)',
                            borderRadius: 'var(--radius-card)',
                            boxShadow: 'var(--shadow-lg, var(--shadow-sm))',
                            fontFamily: 'var(--font-body)',
                        }}
                    >
                        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '14px 20px', borderBottom: '1px solid var(--border-subtle)' }}>
                            <div style={{ fontFamily: 'var(--font-display)', fontWeight: 600, fontSize: 15, color: 'var(--text-primary)' }}>{title}</div>
                            <button
                                type="button"
                                onClick={onClose}
                                style={{ display: 'flex', background: 'none', border: 'none', cursor: 'pointer', color: 'var(--text-tertiary)', padding: 4 }}
                            >
                                <X size={18} />
                            </button>
                        </div>
                        <div style={{ padding: 20 }}>{children}</div>
                    </DialogPanel>
                </TransitionChild>
            </Dialog>
        </Transition>
    );
}
