'use client';
import {useEffect,useRef,type ReactNode} from 'react';
import {ApiError} from '@/lib/api';
export function PageTitle({ title, description, action }: { title: string; description?: string; action?: ReactNode }) {
  return (
    <div className="page-title">
      <div>
        <h1>{title}</h1>
        {description && <p>{description}</p>}
      </div>
      {action}
    </div>
  );
}
export function ErrorMessage({error}:{error:Error|null}){if(!error)return null;return <div className="form-error" role="alert"><p>{error.message}</p>{error instanceof ApiError&&Object.entries(error.errors).map(([name,messages])=><p key={name}>{messages.join(' ')}</p>)}{error instanceof ApiError&&error.requestId&&<small>Код запроса: {error.requestId}</small>}</div>;}
export function Skeleton(){return <div className="grid grid-cols-2 gap-5 md:grid-cols-3" aria-label="Загрузка данных" role="status">{[1,2,3].map(i=><div key={i} className="h-64 animate-pulse rounded-2xl bg-[#edf0e5]"/>)}<span className="sr-only">Загрузка…</span></div>;}
export function Empty({children}:{children:ReactNode}){return <div className="empty">{children}</div>;}
export function Modal({title,onClose,children}:{title:string;onClose:()=>void;children:ReactNode}){
 const ref=useRef<HTMLDialogElement>(null);
 useEffect(() => {
  const dialog = ref.current;
  if (!dialog) return;
  const trigger = document.activeElement;
  dialog.showModal();
  return () => {
   dialog.close();
   if (trigger instanceof HTMLElement && trigger.isConnected) trigger.focus({ preventScroll: true });
  };
 }, []);
 return <dialog ref={ref} aria-label={title} onCancel={event=>{event.preventDefault();onClose();}}><button className="close" aria-label="Закрыть окно" onClick={onClose}>×</button>{children}</dialog>;
}
export function Confirm({message,onClose,onConfirm}:{message:string;onClose:()=>void;onConfirm:()=>void}){return <Modal title="Подтвердите удаление" onClose={onClose}><h2>Подтвердите удаление</h2><p>{message}</p><div className="actions"><button className="secondary" onClick={onClose}>Отмена</button><button className="danger" onClick={onConfirm}>Удалить</button></div></Modal>;}
