import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

export default function SettingsPageFrame({ embedded = false, header, children }) {
    if (embedded) return children;
    return <AuthenticatedLayout header={header}>{children}</AuthenticatedLayout>;
}
