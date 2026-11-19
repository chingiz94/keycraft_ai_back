'use client';

import Link from 'next/link';
import { usePathname, useRouter } from 'next/navigation';
import { useStore } from './store';

export function Shell({ children }: { children: React.ReactNode }) {
  const { user, authLoading, notice, dismiss, logout, notify } = useStore();
  const pathname = usePathname();
  const router = useRouter();
  const links = [
    { href: '/catalog', label: 'Комплектующие' },
    { href: '/builder', label: 'Конструктор' },
    { href: '/guide', label: 'Как это работает' },
  ];

  if (user?.role === 'buyer') {
    links.push({ href: '/account/builds', label: 'Мои сборки' }, { href: '/ai', label: 'ИИ-подбор' });
  }
  if (user?.role === 'admin') {
    links.push({ href: '/admin', label: 'Управление' });
  }

  async function signOut() {
    try {
      await logout();
      router.push('/');
    } catch (error) {
      notify((error as Error).message, true);
    }
  }

  return (
    <>
      <a className="skip" href="#main">Перейти к содержимому</a>
      <header className="topbar">
        <div className="site-container topbar-inner">
          <Link className="brand" href="/" aria-label="KeyCraft AI — на главную">
            <span className="brand-mark" aria-hidden="true">k.</span>
            keycraft<span className="brand-ai">AI</span>
          </Link>
          <nav className="site-nav" aria-label="Основная навигация">
            {links.map(({ href, label }) => (
              <Link
                key={href}
                href={href}
                aria-current={pathname === href || pathname.startsWith(href + '/') ? 'page' : undefined}
              >
                {label}
              </Link>
            ))}
          </nav>
          <div className="site-account">
            {authLoading ? (
              <span className="muted">Загрузка…</span>
            ) : user ? (
              <>
                <div className="account-profile">
                  <span title={user.name}>{user.name}</span>
                  <small>{user.role === 'admin' ? 'Администратор' : 'Покупатель'}</small>
                </div>
                <button className="secondary small" onClick={signOut}>Выйти</button>
              </>
            ) : (
              <>
                <Link href="/login">Войти</Link>
                <Link className="button secondary" href="/register">Регистрация</Link>
              </>
            )}
          </div>
        </div>
      </header>
      {notice && (
        <div className={`site-container notice ${notice.error ? 'error' : ''}`} role="status">
          <span>{notice.message}</span>
          <button className="notice-close" onClick={dismiss} aria-label="Закрыть уведомление">×</button>
        </div>
      )}
      <main className="site-container" id="main" tabIndex={-1}>{children}</main>
      <footer className="site-footer">
        <div className="site-container site-footer-inner">
          <Link className="brand" href="/" aria-label="KeyCraft AI — на главную">keycraft<span className="brand-ai">AI</span></Link>
          <span>© 2026 KeyCraft AI</span>
          <div className="footer-links">
            <Link href="/guide">Как это работает</Link>
            <a href="http://127.0.0.1:8120/docs" target="_blank" rel="noopener noreferrer">Документация API ↗</a>
          </div>
        </div>
      </footer>
    </>
  );
}
