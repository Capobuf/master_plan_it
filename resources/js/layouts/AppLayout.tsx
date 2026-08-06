import { Link, router, usePage } from '@inertiajs/react';
import { type ReactNode, useState } from 'react';
import type { SharedPageProps } from '../types';
import { cx, FlashMessages } from '../components/ui';
import ThemeToggleButton from '../components/tailadmin/ThemeToggleButton';

type NavItem = { label: string; href: string; enabled: boolean };

export default function AppLayout({ children }: { children: ReactNode }) {
  const { auth, tenant, navigation } = usePage<SharedPageProps>().props;
  const [mobileOpen, setMobileOpen] = useState(false);
  const [userMenu, setUserMenu] = useState(false);
  const nav: NavItem[] = [
    { label: 'Dashboard', href: '/operational', enabled: navigation.canViewDashboard },
    { label: 'Expenses', href: '/operational/expenses', enabled: navigation.canViewExpenses },
    { label: 'Contracts', href: '/operational/contracts', enabled: navigation.canViewContracts },
    { label: 'Planning years', href: '/operational/planning-years', enabled: navigation.canViewPlanningYears },
    { label: 'Cost centers', href: '/operational/cost-centers', enabled: navigation.canViewCostCenters },
    { label: 'Vendors', href: '/operational/vendors', enabled: navigation.canViewVendors },
    { label: 'Users', href: '/operational/users', enabled: navigation.canManageUsers },
    { label: 'Roles', href: '/operational/roles', enabled: navigation.canManageRoles },
  ];
  const path = typeof window === 'undefined' ? '' : window.location.pathname;
  const leave = () => router.post('/platform/tenant/leave');
  const sidebar = (
    <aside className="flex h-full w-[290px] flex-col border-r border-gray-200 bg-white text-gray-700 dark:border-gray-800 dark:bg-gray-dark dark:text-gray-300">
      <div className="flex h-20 items-center border-b border-gray-200 px-6 dark:border-gray-800">
        <Link href="/" className="flex items-center gap-3 font-bold tracking-tight text-gray-800 dark:text-white/90">
          <span className="grid h-9 w-9 place-items-center rounded-lg bg-brand-500 text-lg text-white">M</span>
          <span>Master Plan <span className="text-brand-500">IT</span></span>
        </Link>
      </div>
      <div className="px-4 py-5">
        {tenant.current ? (
          <div className="rounded-2xl border border-gray-200 bg-gray-50 p-3 dark:border-gray-800 dark:bg-white/[0.03]">
            <p className="text-theme-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Current tenant</p>
            <p className="mt-1 truncate text-theme-sm font-semibold text-gray-800 dark:text-white/90">{tenant.current.name}</p>
            <p className="text-theme-xs text-gray-500 dark:text-gray-400">{tenant.current.code}</p>
            {auth.user?.isPlatformAdministrator && (
              <button onClick={leave} className="mt-3 text-theme-xs font-semibold text-brand-500 hover:text-brand-600">Leave tenant</button>
            )}
          </div>
        ) : auth.user?.isPlatformAdministrator ? (
          <Link href="/platform/tenants" className="block rounded-xl bg-brand-50 p-3 text-theme-sm font-medium text-brand-500 hover:bg-brand-100 dark:bg-brand-500/15 dark:text-brand-400">Select a tenant</Link>
        ) : null}
      </div>
      <nav className="flex-1 space-y-1 overflow-y-auto px-3 pb-6" aria-label="Main navigation">
        {auth.user?.isPlatformAdministrator && navigation.canViewPlatformTenants && (
          <NavLink href="/platform/tenants" label="Tenants" active={path.startsWith('/platform/tenants')} />
        )}
        {nav.filter((item) => item.enabled).map((item) => (
          <NavLink key={item.href} {...item} active={path === item.href || (item.href !== '/operational' && path.startsWith(`${item.href}/`))} />
        ))}
      </nav>
      <div className="border-t border-gray-200 p-4 text-theme-xs text-gray-500 dark:border-gray-800 dark:text-gray-400">Secure financial planning</div>
    </aside>
  );

  return (
    <div className="min-h-screen bg-gray-50 dark:bg-gray-900">
      <div className="fixed inset-y-0 left-0 z-40 hidden lg:block">{sidebar}</div>
      {mobileOpen && (
        <div className="fixed inset-0 z-50 lg:hidden">
          <button className="absolute inset-y-0 left-[290px] right-0 bg-gray-900/50" aria-label="Close navigation" onClick={() => setMobileOpen(false)} />
          <div className="relative h-full w-[290px]">{sidebar}</div>
        </div>
      )}
      <div className="lg:pl-[290px]">
        <header className="sticky top-0 z-30 flex h-20 items-center justify-between border-b border-gray-200 bg-white/90 px-4 backdrop-blur dark:border-gray-800 dark:bg-gray-900/90 sm:px-7">
          <button onClick={() => setMobileOpen(true)} className="rounded-lg p-2 text-gray-500 hover:bg-gray-100 dark:hover:bg-white/5 lg:hidden" aria-label="Open navigation">☰</button>
          <div className="hidden text-theme-sm text-gray-500 dark:text-gray-400 sm:block">{tenant.current ? `${tenant.current.name} · ${tenant.current.currency}` : 'Platform administration'}</div>
          <div className="ml-auto flex items-center gap-3">
            <ThemeToggleButton />
            <div className="relative">
            <button onClick={() => setUserMenu(!userMenu)} className="flex items-center gap-3 rounded-lg px-2 py-1.5 hover:bg-gray-100 dark:hover:bg-white/5">
              <span className="grid h-9 w-9 place-items-center rounded-full bg-brand-50 font-semibold text-brand-500 dark:bg-brand-500/15 dark:text-brand-400">{auth.user?.name?.slice(0, 1).toUpperCase()}</span>
              <span className="hidden text-left sm:block">
                <span className="block text-theme-sm font-semibold text-gray-800 dark:text-white/90">{auth.user?.name}</span>
                <span className="block text-theme-xs text-gray-500 dark:text-gray-400">{auth.user?.email}</span>
              </span>
            </button>
            {userMenu && (
              <div className="absolute right-0 mt-[17px] w-[260px] rounded-2xl border border-gray-200 bg-white p-3 shadow-theme-lg dark:border-gray-800 dark:bg-gray-dark">
                <Link href="/profile" className="block rounded-lg px-3 py-2 text-theme-sm font-medium text-gray-700 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-white/5">Profile</Link>
                <button onClick={() => router.post('/logout')} className="w-full rounded-lg px-3 py-2 text-left text-theme-sm font-medium text-error-600 hover:bg-error-50 dark:text-error-400 dark:hover:bg-error-500/15">Sign out</button>
              </div>
            )}
            </div>
          </div>
        </header>
        <main className="mx-auto max-w-(--breakpoint-2xl) p-4 md:p-6">
          <FlashMessages />
          {children}
        </main>
      </div>
    </div>
  );
}

function NavLink({ href, label, active }: { href: string; label: string; active: boolean }) {
  return <Link href={href} className={cx('relative flex w-full items-center gap-3 rounded-lg px-3 py-2 font-medium text-theme-sm transition', active ? 'bg-brand-50 text-brand-500 dark:bg-brand-500/[0.12] dark:text-brand-400' : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5')}>{label}</Link>;
}
