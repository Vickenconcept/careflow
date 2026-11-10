import AuthEnterpriseLayout from '@/layouts/auth/auth-enterprise-layout';

export default function AuthLayout({
    title = '',
    description = '',
    children,
}: {
    title?: string;
    description?: string;
    children: React.ReactNode;
}) {
    return (
        <AuthEnterpriseLayout title={title} description={description}>
            {children}
        </AuthEnterpriseLayout>
    );
}
