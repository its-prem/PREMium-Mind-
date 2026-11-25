import React, { useEffect, useRef } from 'react';
import { useNavigate } from 'react-router-dom';
import ProductCard from './ProductCard';
import { CATEGORIES, PRODUCTS, getCategory } from '../data/storeData';
import './Store.css';

/**
 * The store: first 4 big category cards (Notes, E-Books, Resume Templates,
 * Important Questions). Opening one shows the products inside it.
 */
export default function Store({
  activeCategory,
  onSelectCategory,
  owns,
  onPreview,
  onBuy,
  onOpen,
}) {
  const navigate = useNavigate();
  const category = activeCategory ? getCategory(activeCategory) : null;
  const pillsRef = useRef(null);

  // Keep the selected pill in view when there are many categories.
  useEffect(() => {
    const on = pillsRef.current?.querySelector('.is-on');
    on?.scrollIntoView?.({ inline: 'center', block: 'nearest' });
  }, [activeCategory]);
  const products = category
    ? PRODUCTS.filter((p) => p.category === category.id)
    : [];

  /* ---------- Level 1: category cards ---------- */
  if (!category) {
    return (
      <div className="st-cats">
        {CATEGORIES.map((cat) => {
          const items = PRODUCTS.filter((p) => p.category === cat.id);
          const ownedCount = items.filter((p) => owns(p.id)).length;
          return (
            <button
              key={cat.id}
              type="button"
              className="st-cat"
              style={{ '--tint': cat.tint }}
              onClick={() => (cat.to ? navigate(cat.to) : onSelectCategory(cat.id))}
            >
              <span className="st-cat-icon">
                <ion-icon name={cat.icon}></ion-icon>
              </span>
              <span className="st-cat-title">{cat.title}</span>
              <span className="st-cat-desc">{cat.desc}</span>
              <span className="st-cat-foot">
                <span className="st-cat-count">
                  {cat.badge
                    ? cat.badge
                    : items.length === 0
                      ? 'Coming Soon'
                      : `${items.length} ${items.length === 1 ? 'item' : 'items'}`}
                </span>
                {ownedCount > 0 && (
                  <span className="st-cat-owned">
                    <ion-icon name="checkmark-circle"></ion-icon> {ownedCount} yours
                  </span>
                )}
                <span className="st-cat-go" aria-hidden="true">
                  <ion-icon name="arrow-forward"></ion-icon>
                </span>
              </span>
            </button>
          );
        })}
      </div>
    );
  }

  /* ---------- Level 2: products inside a category ---------- */
  return (
    <div className="st-view">
      <div className="st-bar">
        <button
          type="button"
          className="st-back"
          onClick={() => onSelectCategory(null)}
        >
          <ion-icon name="arrow-back-outline"></ion-icon>
          <span className="st-back-text">All categories</span>
        </button>
        <div className="st-pills" role="tablist" aria-label="Categories" ref={pillsRef}>
          {CATEGORIES.filter((cat) => !cat.to).map((cat) => (
            <button
              key={cat.id}
              type="button"
              role="tab"
              aria-selected={cat.id === category.id}
              className={`st-pill ${cat.id === category.id ? 'is-on' : ''}`}
              onClick={() => onSelectCategory(cat.id)}
            >
              <ion-icon name={cat.icon}></ion-icon>
              {cat.title}
            </button>
          ))}
        </div>
      </div>

      <div className="st-head" style={{ '--tint': category.tint }}>
        <span className="st-head-icon">
          <ion-icon name={category.icon}></ion-icon>
        </span>
        <div>
          <h3>{category.title}</h3>
          <p>{category.desc}</p>
        </div>
      </div>

      <div className="pm-cards-grid">
        {products.length === 0 ? (
          <div className="pm-empty-state">
            <div className="pm-empty-icon">
              <ion-icon name="sparkles-outline"></ion-icon>
            </div>
            <h3>New {category.title} Coming Soon!</h3>
            <p>
              {category.soonNote ||
                'Hum is category ke high-quality resources upload kar rahe hain. Tab tak baaki categories dekhein.'}
            </p>
            {category.whatsapp && (
              <a
                className="pm-buy-btn pm-empty-btn"
                href={`https://wa.me/918709911016?text=${encodeURIComponent(category.whatsapp)}`}
                target="_blank"
                rel="noopener noreferrer"
              >
                <ion-icon name="logo-whatsapp"></ion-icon>
                <span>Ask on WhatsApp</span>
              </a>
            )}
            <button
              type="button"
              className="pm-buy-btn pm-empty-btn"
              onClick={() => onSelectCategory(null)}
            >
              <ion-icon name="grid-outline"></ion-icon>
              <span>View All Categories</span>
            </button>
          </div>
        ) : (
          products.map((course, idx) => (
            <ProductCard
              key={`${category.id}-${course.id}`}
              course={course}
              index={idx}
              owned={owns(course.id)}
              onPreview={onPreview}
              onBuy={onBuy}
              onOpen={onOpen}
            />
          ))
        )}
      </div>
    </div>
  );
}
