import {Suspense} from 'react';
import {Builder} from '@/components/builder';
import {Skeleton} from '@/components/ui';
export default function BuilderPage(){return <Suspense fallback={<Skeleton/>}><Builder/></Suspense>;}
