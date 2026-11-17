import type {Metadata} from 'next';
import './globals.css';
import {StoreProvider} from '@/components/store';
import {Shell} from '@/components/shell';
export const metadata:Metadata={title:{default:'KeyCraft AI — клавиатура с вашим характером',template:'%s — KeyCraft AI'},description:'Реальные основы, свитчи и кейкапы. Конструктор механических клавиатур с ИИ и прямыми ссылками в магазины Казахстана.'};
export default function RootLayout({children}:{children:React.ReactNode}){return <html lang="ru"><body><StoreProvider><Shell>{children}</Shell></StoreProvider></body></html>;}
