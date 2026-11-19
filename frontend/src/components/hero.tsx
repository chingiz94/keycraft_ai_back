'use client';
import {useRef,useState,type CSSProperties,type ReactNode} from 'react';
import Link from 'next/link';
import gsap from 'gsap';
import {useGSAP} from '@gsap/react';
import {ScrollTrigger} from 'gsap/ScrollTrigger';
const rows=[['esc','F1','F2','F3','F4','F5','F6','F7','F8','F9','F10','F11','F12','del'],['~','1','2','3','4','5','6','7','8','9','0','−','=','⌫','home'],['tab','Q','W','E','R','T','Y','U','I','O','P','[',']','\\','pgup'],['caps','A','S','D','F','G','H','J','K','L',';','\'','enter','pgdn'],['shift','Z','X','C','V','B','N','M',',','.','/','shift','↑','end'],['ctrl','⌘','alt','space','alt','fn','ctrl','←','↓','→']];
export function Hero(){
 const ref=useRef<HTMLElement>(null);const[expanded,setExpanded]=useState(false);const open=useRef(false);
 const {contextSafe}=useGSAP(()=>{
  gsap.registerPlugin(ScrollTrigger,useGSAP);const media=gsap.matchMedia();
  media.add({all:'(min-width:0px)',reduce:'(prefers-reduced-motion:reduce)',fine:'(pointer:fine) and (min-width:900px)'},context=>{
   if(context.conditions?.reduce)return;
   const select=gsap.utils.selector(ref);
   gsap.timeline({defaults:{duration:.7,ease:'power3.out'}}).from(select('.hero-enter'),{y:24,autoAlpha:0,stagger:.09},0).from(select('.hero-key'),{y:-12,autoAlpha:0,stagger:{amount:.45,from:'center'},duration:.55},.2);
   const stage=ref.current?.querySelector<HTMLElement>('.keyboard-stage');const stack=ref.current?.querySelector<HTMLElement>('.keyboard-float');
   if(!context.conditions?.fine||!stage||!stack)return;
   const x=gsap.quickTo(stack,'x',{duration:.8,ease:'power3.out'}),y=gsap.quickTo(stack,'y',{duration:.8,ease:'power3.out'});
   const move=(event:PointerEvent)=>{const rect=stage.getBoundingClientRect();x(((event.clientX-rect.left)/rect.width-.5)*16);y(((event.clientY-rect.top)/rect.height-.5)*12);};
   const leave=()=>{x(0);y(0);};stage.addEventListener('pointermove',move);stage.addEventListener('pointerleave',leave);
   return()=>{stage.removeEventListener('pointermove',move);stage.removeEventListener('pointerleave',leave);};
  },ref);
  return()=>media.revert();
 },{scope:ref});
 const toggle=()=>contextSafe(()=>{open.current=!open.current;setExpanded(open.current);const select=gsap.utils.selector(ref);const duration=window.matchMedia('(prefers-reduced-motion:reduce)').matches?0:.7;gsap.timeline({defaults:{duration,ease:'power3.inOut',overwrite:'auto'}}).to(select('.layer-caps'),{z:open.current?155:18},0).to(select('.layer-plate'),{z:open.current?90:7},0).to(select('.layer-pcb'),{z:open.current?35:2},0);})();
 return (
  <section ref={ref} className="retail-hero">
   <div className="retail-hero-copy">
    <h1 className="hero-enter">Соберите <em>свою клавиатуру</em></h1>
    <p className="hero-enter hero-description">
     Выберите основу, свитчи и кейкапы. Конструктор проверит совместимость и рассчитает количество упаковок.
    </p>
    <div className="hero-enter hero-actions">
     <Link className="button" href="/builder">В конструктор <span aria-hidden="true">↗</span></Link>
     <Link className="under-link" href="/catalog">Смотреть каталог</Link>
    </div>
   </div>
   <div className="keyboard-stage" role="group" aria-label="Интерактивная схема механической клавиатуры">
    <div className="stage-ring" aria-hidden="true" />
    <div className="keyboard-float" aria-hidden="true">
     <div className="keyboard-tilt">
      <div className="keyboard-body" />
      <div className="layer-pcb" />
      <div className="layer-plate" />
      <div className="layer-caps">
       {rows.map((row, i) => (
        <div className={`hero-key-row row-${i}`} key={i}>
         {row.map((key, j) => (
          <span
           className={`hero-key ${['esc','enter','↑','←','↓','→'].includes(key) ? 'key-green' : ''} ${key === 'space' ? 'key-space' : ''}`}
           key={j}
           style={{ '--grow': key === 'space' ? 6.25 : ['⌫','enter','shift'].includes(key) ? 1.8 : ['tab','caps'].includes(key) ? 1.45 : 1 } as CSSProperties}
          >
           {key === 'space' ? 'keycraft' : key}
          </span>
         ))}
        </div>
       ))}
      </div>
      <span className="keyboard-knob" />
     </div>
    </div>
    <div className="stage-bottom">
     <button className="secondary small" aria-pressed={expanded} onClick={toggle}>Показать слои</button>
    </div>
   </div>
  </section>
 );
}
export function Reveal({children,className='',count=0}:{children:ReactNode;className?:string;count?:number}){
 const ref=useRef<HTMLDivElement>(null);
 useGSAP(()=>{gsap.registerPlugin(ScrollTrigger);const media=gsap.matchMedia();media.add('(prefers-reduced-motion:no-preference)',()=>{for(const element of gsap.utils.toArray<HTMLElement>('[data-reveal]',ref.current)){gsap.from(element,{y:24,autoAlpha:0,duration:.65,ease:'power2.out',scrollTrigger:{trigger:element,start:'top 94%',once:true}});}const refresh=()=>ScrollTrigger.refresh();const images=[...ref.current!.querySelectorAll('img')];images.forEach(image=>image.addEventListener('load',refresh,{once:true}));ScrollTrigger.refresh();return()=>images.forEach(image=>image.removeEventListener('load',refresh));},ref);return()=>media.revert();},{scope:ref,dependencies:[count],revertOnUpdate:true});
 return <div ref={ref} className={className}>{children}</div>;
}
