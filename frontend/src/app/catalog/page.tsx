'use client';

import { useDeferredValue, useMemo, useState } from 'react';
import { ProductCard, ProductDetail, useCatalog, useProductSelection } from '@/components/products';
import { PageTitle, ErrorMessage, Empty, Skeleton } from '@/components/ui';
import { Reveal } from '@/components/hero';
import { categories, type Product } from '@/lib/types';
import { checkedDate } from '@/lib/api';

const pageSize = 6;
const merchants = ['Белый Ветер', 'Vendara', 'Over-Shop'];
const categoryFilters = [['', 'Все детали'], ...Object.entries(categories)];

export default function Catalog() {
  const { products, loading, error, refresh } = useCatalog();
  const [search, setSearch] = useState('');
  const query = useDeferredValue(search.trim().toLowerCase());
  const [category, setCategory] = useState('');
  const [merchant, setMerchant] = useState('');
  const [available, setAvailable] = useState(false);
  const [order, setOrder] = useState('name');
  const [page, setPage] = useState(1);
  const [detail, setDetail] = useState<Product | null>(null);
  const choose = useProductSelection();

  const filtered = useMemo(() => products
    .filter(product => {
      const searchable = `${product.name} ${product.brand} ${product.description}`.toLowerCase();
      return (!category || product.category === category) && searchable.includes(query);
    })
    .flatMap(product => {
      const offer = product.offers.find(item =>
        (!merchant || item.merchant === merchant) && (!available || item.availability === 'in_stock')
      );
      return offer ? [{ ...product, best_offer: offer }] : [];
    })
    .sort((a, b) => {
      if (order === 'price-up') return a.best_offer.price - b.best_offer.price;
      if (order === 'price-down') return b.best_offer.price - a.best_offer.price;
      return a.name.localeCompare(b.name);
    }), [products, category, query, merchant, available, order]);

  const last = Math.max(1, Math.ceil(filtered.length / pageSize));
  const current = Math.min(page, last);

  return (
    <>
      <PageTitle title="Комплектующие" description="Сравните основы, свитчи и кейкапы из магазинов Казахстана." />
      <section className="catalog-controls" aria-label="Фильтры каталога">
        <div className="catalog-tabs" role="group" aria-label="Категория">
          {categoryFilters.map(([id, label]) => (
            <button key={id} aria-pressed={category === id} onClick={() => { setCategory(id); setPage(1); }}>
              {label}
            </button>
          ))}
        </div>
        <div className="retail-filter-row">
          <label>
            Поиск
            <input
              type="search"
              placeholder="Название модели или бренд"
              value={search}
              maxLength={100}
              onChange={event => { setSearch(event.target.value); setPage(1); }}
            />
          </label>
          <label>
            Магазин
            <select value={merchant} onChange={event => { setMerchant(event.target.value); setPage(1); }}>
              <option value="">Все магазины</option>
              {merchants.map(name => <option key={name}>{name}</option>)}
            </select>
          </label>
          <label>
            Сортировка
            <select value={order} onChange={event => { setOrder(event.target.value); setPage(1); }}>
              <option value="name">По названию</option>
              <option value="price-up">Сначала дешевле</option>
              <option value="price-down">Сначала дороже</option>
            </select>
          </label>
        </div>
        <label className="check catalog-availability">
          <input
            type="checkbox"
            checked={available}
            onChange={event => { setAvailable(event.target.checked); setPage(1); }}
          />
          В наличии при проверке
        </label>
      </section>

      <div className="retail-list-heading">
        <span aria-live="polite">{loading && !products.length ? 'Загрузка каталога…' : `Найдено: ${filtered.length}`}</span>
        {products[0] && <small>Цены и наличие проверены {checkedDate(products[0].best_offer.checked_at)}.</small>}
      </div>
      <ErrorMessage error={error} />
      {error && <button onClick={refresh}>Повторить</button>}
      {loading && !products.length ? (
        <Skeleton />
      ) : filtered.length ? (
        <Reveal className="retail-grid" count={filtered.length + current}>
          {filtered.slice((current - 1) * pageSize, current * pageSize).map(product => (
            <div data-reveal key={product.id}>
              <ProductCard product={product} onDetail={setDetail} onSelect={choose} />
            </div>
          ))}
        </Reveal>
      ) : !error && (
        <Empty>Ничего не найдено. Измените запрос или сбросьте фильтры.</Empty>
      )}
      {last > 1 && (
        <div className="pagination">
          <button className="secondary small" disabled={current === 1} onClick={() => setPage(current - 1)}>Назад</button>
          <span aria-live="polite">{current} / {last}</span>
          <button className="secondary small" disabled={current === last} onClick={() => setPage(current + 1)}>Далее</button>
        </div>
      )}
      <p className="source-note">Итоговую цену, наличие в городе и доставку уточняйте на сайте магазина.</p>
      {detail && <ProductDetail product={detail} onClose={() => setDetail(null)} onSelect={choose} />}
    </>
  );
}
