'use client';
export default function ErrorPage({reset}:{error:Error;reset:()=>void}){return <section className="error-page"><p className="eyebrow">Не удалось загрузить страницу</p><h1>Попробуем ещё раз.</h1><p>Ваш черновик остаётся в этом браузере.</p><button onClick={reset}>Повторить</button></section>;}
