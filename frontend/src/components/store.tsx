'use client';
import {createContext,useContext,useEffect,useReducer,useCallback,type ReactNode} from 'react';
import {api,tokenStore,ApiError,defaultDraft} from '@/lib/api';
import type {User,Draft,Product} from '@/lib/types';

type State={user:User|null;authLoading:boolean;draft:Draft;draftReady:boolean;products:Product[];notice:{message:string;error:boolean}|null};
type Action={type:'user';user:User|null}|{type:'auth-ready'}|{type:'draft';draft:Draft}|{type:'draft-ready';draft:Draft}|{type:'products';products:Product[]}|{type:'notice';message:string;error:boolean}|{type:'dismiss'};
const initial:State={user:null,authLoading:true,draft:defaultDraft(),draftReady:false,products:[],notice:null};
function reducer(state:State,action:Action):State{
 switch(action.type){
  case 'user':return {...state,user:action.user,authLoading:false};
  case 'auth-ready':return {...state,authLoading:false};
  case 'draft':return {...state,draft:action.draft};
  case 'draft-ready':return {...state,draft:action.draft,draftReady:true};
  case 'products':return {...state,products:action.products};
  case 'notice':return {...state,notice:{message:action.message,error:action.error}};
  case 'dismiss':return {...state,notice:null};
 }
}
type Store=State&{setDraft:(draft:Draft)=>void;setProducts:(products:Product[])=>void;notify:(message:string,error?:boolean)=>void;dismiss:()=>void;authenticate:(data:Record<string,string>,register?:boolean)=>Promise<User>;logout:()=>Promise<void>};
const StoreContext=createContext<Store|null>(null);
export function StoreProvider({children}:{children:ReactNode}){
 const [state,dispatch]=useReducer(reducer,initial);
 useEffect(()=>{
  let active=true;
  try{const raw=JSON.parse(localStorage.getItem('keycraft.retail.draft.v1')||'{}');dispatch({type:'draft-ready',draft:{...defaultDraft(),...raw}});}catch{dispatch({type:'draft-ready',draft:defaultDraft()});}
  if(tokenStore.get())api<{data:User}>('auth/me').then(result=>{if(active)dispatch({type:'user',user:result.data});}).catch(error=>{if(active){dispatch({type:'auth-ready'});if(!(error instanceof ApiError)||error.status!==401)dispatch({type:'notice',message:error.message,error:true});}});
  else dispatch({type:'auth-ready'});
  const expired=()=>{dispatch({type:'user',user:null});dispatch({type:'notice',message:'Сессия закончилась. Войдите снова.',error:true});};
  window.addEventListener('keycraft-session-expired',expired);
  return()=>{active=false;window.removeEventListener('keycraft-session-expired',expired);};
 },[]);
 useEffect(()=>{if(state.draftReady)localStorage.setItem('keycraft.retail.draft.v1',JSON.stringify(state.draft));},[state.draft,state.draftReady]);
 const setDraft=useCallback((draft:Draft)=>dispatch({type:'draft',draft}),[]);
 const setProducts=useCallback((products:Product[])=>dispatch({type:'products',products}),[]);
 const notify=useCallback((message:string,error=false)=>dispatch({type:'notice',message,error}),[]);
 const authenticate=async(data:Record<string,string>,register=false)=>{
  const result=await api<{data:User;token:string}>(`auth/${register?'register':'login'}`,{method:'POST',body:data,authenticated:false});
  tokenStore.set(result.token);
  const me=(await api<{data:User}>('auth/me')).data;
  dispatch({type:'user',user:me});
  if(state.draft.ownerId&&state.draft.ownerId!==me.id){const draft={...state.draft};delete draft.buildId;delete draft.ownerId;setDraft(draft);}
  return me;
 };
 const logout=async()=>{await api('auth/logout',{method:'POST'});tokenStore.clear();dispatch({type:'user',user:null});notify('Вы вышли из аккаунта.');};
 return <StoreContext value={{...state,setDraft,setProducts,notify,authenticate,logout,dismiss:()=>dispatch({type:'dismiss'})}}>{children}</StoreContext>;
}
export function useStore(){const store=useContext(StoreContext);if(!store)throw new Error('StoreProvider required');return store;}
