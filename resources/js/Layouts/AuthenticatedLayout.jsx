import Avatar from '@/Components/ui/Avatar';
import Alert from '@/Components/ui/Alert';
import DropdownMenu from '@/Components/ui/DropdownMenu';
import LocaleSwitcher from '@/Components/ui/LocaleSwitcher';
import Sidebar from '@/Components/ui/Sidebar';
import Switch from '@/Components/ui/Switch';
import { useTranslate } from '@/lib/i18n';
import { router, usePage } from '@inertiajs/react';
import { BookOpen, FileText, LayoutDashboard, LayoutTemplate, LogOut, Menu, Package, Palette, Plug, ReceiptText, RotateCcw, Settings, ShoppingCart, Tags, Ticket, UserRound, Users, Warehouse } from 'lucide-react';
import { useEffect, useState } from 'react';

const THEME_STORAGE_KEY = 'lc-theme';

function useTheme() {
    const [dark, setDark] = useState(() => document.documentElement.dataset.theme === 'dark');

    useEffect(() => {
        document.documentElement.dataset.theme = dark ? 'dark' : 'light';
        try {
            localStorage.setItem(THEME_STORAGE_KEY, dark ? 'dark' : 'light');
        } catch {
            // Storage may be unavailable (private browsing) — theme still applies for this load.
        }
    }, [dark]);

    return [dark, setDark];
}

export default function AuthenticatedLayout({ header, children }) {
    const { auth, settingsTabs = [], maintenance = null } = usePage().props;
    const { user, can } = auth;
    const [dark, setDark] = useTheme();
    const [navigationOpen, setNavigationOpen] = useState(false);
    const t = useTranslate();

    const productsActive = ['panel.catalog.products.index', 'panel.catalog.products.create', 'panel.catalog.products.edit'].some(
        (name) => route().current(name),
    );

    const navItems = [
        { href: route('dashboard'), label: t('app.nav_dashboard'), icon: LayoutDashboard, active: route().current('dashboard') },
    ];

    if (can.manageCatalog) {
        navItems.push({ section: true, label: t('app.section_catalog') });
        navItems.push({ href: route('panel.catalog.products.index'), label: t('app.nav_products'), icon: Package, active: productsActive });
        navItems.push({
            href: route('panel.catalog.products.references.index'),
            label: t('app.nav_brands_categories'),
            icon: Tags,
            active: route().current('panel.catalog.products.references.index'),
        });
    }

    if (can.manageInventory) {
        navItems.push({ section: true, label: t('app.section_inventory') });
        navItems.push({ href: route('panel.warehouse.index'), label: t('app.nav_warehouse'), icon: Warehouse, active: route().current('panel.warehouse.*') });
    }

    if (can.viewOrders) {
        navItems.push({ section: true, label: t('app.section_sales') });
        navItems.push({ href: route('panel.orders.index'), label: t('app.nav_orders'), icon: ReceiptText, active: route().current('panel.orders.*') });
        navItems.push({ href: route('panel.cart.index'), label: t('app.nav_carts'), icon: ShoppingCart, active: route().current('panel.cart.*') });
        if (can.manageRefunds) {
            navItems.push({ href: route('panel.returns.index'), label: t('app.nav_returns'), icon: RotateCcw, active: route().current('panel.returns.*') });
        }
    }

    if (can.manageCoupons) {
        navItems.push({ section: true, label: t('app.section_marketing') });
        navItems.push({ href: route('panel.coupons.index'), label: t('app.nav_coupons'), icon: Ticket, active: route().current('panel.coupons.*') });
    }

    if (can.manageStaff) {
        navItems.push({ section: true, label: t('app.section_staff') });
        navItems.push({ href: route('panel.staff.index'), label: t('app.nav_staff'), icon: Users, active: route().current('panel.staff.*') });
    }

    if (settingsTabs.length > 0) {
        navItems.push({ section: true, label: t('app.section_store') });
        navItems.push({ href: `${route('panel.settings.index')}#${settingsTabs[0].id}`, label: t('app.nav_store_settings'), icon: Settings, active: route().current('panel.settings.*') });
    }

    if (can.manageContent) {
        navItems.push({ section: true, label: t('app.section_content') });
        navItems.push({ href: route('panel.cms.pages.index'), label: t('app.nav_cms'), icon: FileText, active: route().current('panel.cms.pages.*') });
        if (can.blogEnabled) {
            navItems.push({ href: route('panel.blog.posts.index'), label: t('blog.nav_blog'), icon: BookOpen, active: route().current('panel.blog.*') });
        }
    }

    if (can.manageContent || can.manageThemes) {
        navItems.push({ section: true, label: t('app.section_appearance') });
        if (can.manageThemes) {
            navItems.push({ href: route('panel.themes.index'), label: t('app.nav_themes'), icon: Palette, active: route().current('panel.themes.*') });
        }
        if (can.manageContent) {
            navItems.push({ href: route('panel.cms.archetypes.index'), label: t('app.nav_cms_archetypes'), icon: LayoutTemplate, active: route().current('panel.cms.archetypes.*') });
            navItems.push({ href: route('panel.cms.menus.index'), label: t('app.nav_cms_menus'), icon: Menu, active: route().current('panel.cms.menus.*') });
        }
    }

    if (can.manageExtensions) {
        navItems.push({ section: true, label: t('app.section_extensions') });
        navItems.push({ href: route('panel.extensions.index'), label: t('app.nav_extensions'), icon: Plug, active: route().current('panel.extensions.*') });
    }

    return (
        <div style={{ display: 'flex', height: '100vh', background: 'var(--bg-canvas)' }}>
            <Sidebar items={navItems} open={navigationOpen} onClose={() => setNavigationOpen(false)} />
            <div style={{ flex: 1, display: 'flex', flexDirection: 'column', minWidth: 0 }}>
                <div
                    className="lc-topbar"
                    style={{
                        height: 'var(--layout-topbar-h)',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'space-between',
                        gap: 16,
                        padding: '0 24px',
                        borderBottom: '1px solid var(--border-subtle)',
                        flexShrink: 0,
                        background: 'var(--bg-surface)',
                    }}
                >
                    <button
                        type="button"
                        className="lc-mobile-menu-button"
                        aria-label={t('app.open_navigation')}
                        aria-expanded={navigationOpen}
                        onClick={() => setNavigationOpen(true)}
                    >
                        <Menu size={20} />
                    </button>
                    <div style={{ flex: 1, minWidth: 0 }}>{header}</div>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 16, flexShrink: 0 }}>
                        <LocaleSwitcher />
                        <Switch label={t('app.dark_mode')} checked={dark} onChange={setDark} />
                        <DropdownMenu
                            trigger={
                                <div style={{ display: 'flex', alignItems: 'center', gap: 8, cursor: 'pointer' }}>
                                    <Avatar name={user.name} size={30} />
                                </div>
                            }
                            items={[
                                {
                                    label: t('app.profile'),
                                    icon: <UserRound size={15} />,
                                    onClick: () => router.visit(route('profile.edit')),
                                },
                                {
                                    label: t('app.logout'),
                                    icon: <LogOut size={15} />,
                                    danger: true,
                                    onClick: () => router.post(route('logout')),
                                },
                            ]}
                        />
                    </div>
                </div>
                {maintenance && (
                    <div style={{ padding: '8px 16px' }}>
                        <Alert tone="warning">{maintenance.reason === 'update' ? t('maintenance.banner_update') : t('maintenance.banner_manual')}</Alert>
                    </div>
                )}
                <main style={{ flex: 1, overflowY: 'auto' }}>{children}</main>
            </div>
        </div>
    );
}
