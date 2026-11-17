'use client';
import Link from 'next/link';
import {usePathname,useRouter} from 'next/navigation';
import {useStore} from './store';
export function Shell({children}:{children:React.ReactNode}){
 const {user,authLoading,notice,dismiss,logout,notify}=useStore();const path=usePathname();const router=useRouter();
 const links=[['/catalog','Комплектующие'],['/builder','Конструктор'],['/guide','Как это работает']];
 if(user?.role==='buyer')links.push(['/account/builds','Мои сборки'],['/ai','ИИ-подбор']);
 if(user?.role==='admin')links.push(['/admin','Управление']);
 return <><a className="skip" href="#main">Перейти к содержимому</a><header className="topbar"><Link className="brand" href="/" aria-label="KeyCraft AI — на главную"><span className="brand-mark" aria-hidden="true">k.</span>keycraft<span className="brand-ai">AI</span></Link><nav aria-label="Основная навигация">{links.map(([href,label])=><Link key={href} className={path.startsWith(href)?'active':''} href={href}>{label}</Link>)}</nav><div id="account">{authLoading?<span className="text-xs opacity-50">Загрузка…</span>:user?<><div>{user.name}<small>{user.role==='admin'?'Администратор':'Покупатель'}</small></div><button className="secondary small" onClick={async()=>{try{await logout();router.push('/');}catch(error){notify((error as Error).message,true);}}}>Выйти</button></>:<><Link href="/login">Войти</Link><Link className="button secondary" href="/register">Регистрация</Link></>}</div></header>{notice&&<div className={`notice ${notice.error?'error':''}`} role="status" aria-live="polite"><span>{notice.message}</span><button className="notice-close" onClick={dismiss} aria-label="Закрыть уведомление">×</button></div>}<main id="main" tabIndex={-1}>{children}</main><footer><Link className="brand" href="/">keycraft<span className="brand-ai">AI</span></Link><span>Собрано с характером. © 2026</span><div><Link href="/guide">Как это работает</Link><a href="http://127.0.0.1:8120/docs" target="_blank" rel="noopener noreferrer">Документация API ↗</a></div></footer></>;
}
