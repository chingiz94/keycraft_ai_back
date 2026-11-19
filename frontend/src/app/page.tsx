'use client';

import { useState } from 'react';
import Link from 'next/link';
import { Hero, Reveal } from '@/components/hero';
import { ProductCard, ProductDetail, useCatalog, useProductSelection } from '@/components/products';
import { ErrorMessage, Skeleton } from '@/components/ui';
import type { Product } from '@/lib/types';

const featuredIds = ['gmmk-pro-black', 'gateron-red', 'gpbt-black-ash'];

export default function Home() {
  const { products, loading, error, refresh } = useCatalog();
  const [detail, setDetail] = useState<Product | null>(null);
  const choose = useProductSelection();
  const featured = featuredIds
    .map(id => products.find(product => product.id === id))
    .filter((product): product is Product => Boolean(product));

  return (
    <>
      <Hero />
      <section className="featured-section">
        <div className="section-heading">
          <h2>Комплектующие</h2>
          <Link className="under-link" href="/catalog">Весь каталог <span aria-hidden="true">↗</span></Link>
        </div>
        <ErrorMessage error={error} />
        {error && <button onClick={refresh}>Повторить</button>}
        {loading && !products.length ? <Skeleton /> : (
          <Reveal className="retail-grid" count={featured.length}>
            {featured.map(product => (
              <div key={product.id} data-reveal>
                <ProductCard product={product} onDetail={setDetail} onSelect={choose} />
              </div>
            ))}
          </Reveal>
        )}
      </section>
      <Reveal>
        <section className="craft-story" data-reveal>
          <h2>Подбор по бюджету</h2>
          <div>
            <p>Укажите бюджет и предпочтения. ИИ предложит совместимые детали из каталога.</p>
            <Link className="button" href="/ai">Перейти к ИИ-подбору <span aria-hidden="true">↗</span></Link>
          </div>
        </section>
      </Reveal>
      {detail && <ProductDetail product={detail} onClose={() => setDetail(null)} onSelect={choose} />}
    </>
  );
}
