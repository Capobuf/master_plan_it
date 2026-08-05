import { Link, router, usePage } from '@inertiajs/react';
import { type ReactNode, useState } from 'react';
import type { SharedPageProps } from '../types';
import { cx, FlashMessages } from '../components/ui';

type NavItem = { label: string; href: string; enabled: boolean };

export default function AppLayout({ children }: { children: ReactNode }) {
  const { auth, tenant, navigation } = usePage<SharedPageProps>().props;
  const [mobileOpen, setMobileOpen] = useState(false);
  const [userMenu, setUserMenu] = useState(false);
  const nav: NavItem[] = [
    { label: 'Dashboard', href: '/operational', enabled: navigation.canViewDashboard },
    { label: 'Users', href: '/operational/users', enabled: navigation.canManageUsers },
    { label: 'Roles', href: '/operational/roles', enabled: navigation.canManageRoles },
    { label: 'Planning years', href: '/operational/planning-years', enabled: navigation.canViewPlanningYears },
    { label: 'Cost centers', href: '/operational/cost-centers', enabled: navigation.canViewCostCenters },
    { label: 'Vendors', href: '/operational/vendors', enabled: navigation.canViewVendors },
    { label: 'Expenses', href: '/operational/expenses', enabled: navigation.canViewExpenses },
  ];
  const path = typeof window === 'undefined' ? '' : window.location.pathname;
  const leave = () => router.post('/platform/tenant/leave');
  const sidebar = (
    <aside className="flex h-full w-72 flex-col bg-brand-950 text-slate-200">
      <div className="flex h-20 items-center border-b border-white/10 px-6">
        <Link href="/" className="flex items-center gap-3 font-bold tracking-tight text-white">
          <span className="grid h-9 w-9 place-items-center rounded-lg bg-blue-500 text-lg">M</span>
          <span>Master Plan <span className="text-blue-300">IT</span></span>
        </Link>
      </div>
      <div className="px-4 py-5">
        {tenant.current ? (
          <div className="rounded-xl border border-white/10 bg-white/5 p-3">
            <p className="text-xs font-medium uppercase tracking-wider text-blue-200">Current tenant</p>
            <p className="mt-1 truncate text-sm font-semibold text-white">{tenant.current.name}</p>
            <p className="text-xs text-slate-400">{tenant.current.code}</p>
            {auth.user?.isPlatformAdministrator && (
              <button onClick={leave} className="mt-3 text-xs font-semibold text-blue-200 hover:text-white">Leave tenant</button>
            )}
          </div>
        ) : auth.user?.isPlatformAdministrator ? (
          <Link href="/platform/tenants" className="block rounded-xl bg-blue-500/15 p-3 text-sm font-medium text-blue-100 hover:bg-blue-500/25">Select a tenant</Link>
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
      <div className="border-t border-white/10 p-4 text-xs text-slate-400">Secure financial planning</div>
    </aside>
  );

  return (
    <div className="min-h-screen bg-slate-50">
      <div className="fixed inset-y-0 left-0 z-40 hidden lg:block">{sidebar}</div>
      {mobileOpen && (
        <div className="fixed inset-0 z-50 lg:hidden">
          <button className="absolute inset-y-0 left-72 right-0 bg-slate-950/50" aria-label="Close navigation" onClick={() => setMobileOpen(false)} />
          <div className="relative h-full w-72">{sidebar}</div>
        </div>
      )}
      <div className="lg:pl-72">
        <header className="sticky top-0 z-30 flex h-20 items-center justify-between border-b border-slate-200 bg-white/90 px-4 backdrop-blur sm:px-7">
          <button onClick={() => setMobileOpen(true)} className="rounded-lg p-2 text-slate-600 hover:bg-slate-100 lg:hidden" aria-label="Open navigation">☰</button>
          <div className="hidden text-sm text-slate-500 sm:block">{tenant.current ? `${tenant.current.name} · ${tenant.current.currency}` : 'Platform administration'}</div>
          <div className="relative ml-auto">
            <button onClick={() => setUserMenu(!userMenu)} className="flex items-center gap-3 rounded-lg px-2 py-1.5 hover:bg-slate-100">
              <span className="grid h-9 w-9 place-items-center rounded-full bg-brand-100 font-semibold text-brand-700">{auth.user?.name?.slice(0, 1).toUpperCase()}</span>
              <span className="hidden text-left sm:block">
                <span className="block text-sm font-semibold text-slate-800">{auth.user?.name}</span>
                <span className="block text-xs text-slate-500">{auth.user?.email}</span>
              </span>
            </button>
            {userMenu && (
              <div className="absolute right-0 mt-2 w-52 rounded-xl border border-slate-200 bg-white p-1 shadow-lg">
                <Link href="/profile" className="block rounded-lg px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">Profile</Link>
                <button onClick={() => router.post('/logout')} className="w-full rounded-lg px-3 py-2 text-left text-sm text-red-700 hover:bg-red-50">Sign out</button>
              </div>
            )}
          </div>
        </header>
        <main className="p-4 sm:p-7">
          <FlashMessages />
          {children}
        </main>
      </div>
    </div>
  );
}

function NavLink({ href, label, active }: { href: string; label: string; active: boolean }) {
  return <Link href={href} className={cx('block rounded-lg px-4 py-2.5 text-sm font-medium transition', active ? 'bg-blue-500 text-white shadow-sm' : 'text-slate-300 hover:bg-white/10 hover:text-white')}>{label}</Link>;
}
