export const API_BASE = process.env.NEXT_PUBLIC_API_BASE_URL || 'http://127.0.0.1:8120/api/v1';
const tokenKey='keycraft.token';
export const tokenStore={get:()=>typeof window==='undefined'?null:sessionStorage.getItem(tokenKey),set:(value:string)=>sessionStorage.setItem(tokenKey,value),clear:()=>sessionStorage.removeItem(tokenKey)};
export class ApiError extends Error {
  constructor(message:string,public status=0,public errors:Record<string,string[]>={},public requestId=''){super(message);}
}
export async function api<T>(path:string,{method='GET',body,authenticated=true}: {method?:string;body?:unknown;authenticated?:boolean}={}):Promise<T>{
  const headers:Record<string,string>={Accept:'application/json'};
  if(body!==undefined)headers['Content-Type']='application/json';
  const token=authenticated?tokenStore.get():null;
  if(token)headers.Authorization=`Bearer ${token}`;
  let response:Response;
  try{response=await fetch(`${API_BASE}/${path.replace(/^\//,'')}`,{method,headers,credentials:'omit',body:body===undefined?undefined:JSON.stringify(body),signal:AbortSignal.timeout(45000)});}
  catch{throw new ApiError('Нет соединения с сервером. Проверьте запуск API.');}
  const data=response.status===204?null:await response.json().catch(()=>null);
  if(!response.ok){
    if(response.status===401&&authenticated){tokenStore.clear();window.dispatchEvent(new Event('keycraft-session-expired'));}
    throw new ApiError(data?.message||`Ошибка сервера (${response.status}). Повторите позже.`,response.status,data?.errors||{},response.headers.get('X-Request-ID')||'');
  }
  return data as T;
}
export const money=(value:number)=>new Intl.NumberFormat('ru-RU',{maximumFractionDigits:2}).format(value/100)+' ₸';
export const checkedDate=(value:string)=>new Date(value).toLocaleDateString('ru-RU',{timeZone:'Asia/Qyzylorda'});
export function httpsLink(value:string):string{try{const url=new URL(value);return url.protocol==='https:'?url.href:'#';}catch{return '#';}}
export const defaultDraft=()=>({kit_id:null,switch_id:null,keycap_id:null,name:'Моя клавиатура',budget:15000000});
