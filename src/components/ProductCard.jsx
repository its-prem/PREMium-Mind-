import React from 'react';
import { ResumeScaled, SAMPLE_RESUME } from './ResumeDocument';
import { isCustomTemplate } from '../lib/resumeTemplates';
import './ProductCard.css';

export default function ProductCard({ course, index, onPreview, onBuy, onOpen, owned = false }) {
  const isResume = course.category === 'resume';
  // the built-in templates are drawn live; a dropped-in one is shown as a picture so its code is not loaded for visitors
  const free = isResume && Number(course.price) === 0; // a free template: nothing to pay
  const liveResume = isResume && !isCustomTemplate(course.template);
  const discount =
    course.oldPrice && course.oldPrice > course.price
      ? Math.round(((course.oldPrice - course.price) / course.oldPrice) * 100)
      : null;

  return (
    <article
      className="pm-course-card"
      style={{ animationDelay: `${index * 70}ms` }}
    >
      <div className="pm-card-shine" aria-hidden="true" />

      {/* Tall Cover Thumbnail */}
      <div className={`pm-card-thumb ${liveResume ? 'pm-thumb-resume' : ''}`}>
        {course.badge && !owned && (
          <span className="pm-ribbon-badge">{course.badge}</span>
        )}
        {owned ? (
          <span className="pm-owned-badge">
            <ion-icon name="checkmark-circle"></ion-icon> Purchased
          </span>
        ) : null}

        {liveResume ? (
          <ResumeScaled
            template={course.template}
            data={SAMPLE_RESUME}
            accent={course.accent}
          />
        ) : (
          <img
            src={course.image}
            alt={course.title}
            loading="lazy"
            onError={(e) => {
              e.currentTarget.src = '/small-logo.png';
            }}
          />
        )}

        <div className="pm-thumb-gradient" aria-hidden="true" />

        <button
          type="button"
          className="pm-thumb-preview-btn"
          onClick={() => onPreview(course)}
        >
          <ion-icon name="eye-outline"></ion-icon>
          <span>Quick Preview</span>
        </button>
      </div>

      {/* Card Content Body */}
      <div className="pm-card-body">
        <div className="pm-card-title-row">
          <h3 className="pm-card-title">{course.title}</h3>
          {course.rating ? (
            <div className="pm-rating-pill">
              <ion-icon name="star"></ion-icon>
              <span>{course.rating}</span>
            </div>
          ) : null}
        </div>
        <p className="pm-card-desc">{course.desc1}</p>

        {/* Clean Feature Highlights */}
        <div className="pm-card-highlights">
          {isResume ? (
            <>
              <span>
                <ion-icon name="checkmark-circle"></ion-icon> Fully Editable
              </span>
              <span>
                <ion-icon name="checkmark-circle"></ion-icon> Saves as PDF
              </span>
            </>
          ) : (
            <>
              <span>
                <ion-icon name="checkmark-circle"></ion-icon> Instant Access
              </span>
              <span>
                <ion-icon name="checkmark-circle"></ion-icon> Exam Ready
              </span>
            </>
          )}
        </div>

        {/* Bottom Price & Action */}
        <div className="pm-card-bottom">
          <div className="pm-card-meta">
            <div className="pm-price-group">
              <span className="pm-sale-price">{free ? 'FREE' : `₹${course.price}`}</span>
              {!free && course.oldPrice && (
                <span className="pm-old-price">₹{course.oldPrice}</span>
              )}
            </div>
            {owned ? (
              <span className="pm-owned-note">Yours to keep</span>
            ) : (
              <div className="pm-offer-group">
                {!free && discount && <span className="pm-off-chip">{discount}% OFF</span>}
                {isResume && !free && (
                  <span className="pm-offer-chip" title="Customizing is free">
                    Pay when you download
                  </span>
                )}
                {free && <span className="pm-off-chip">Free download</span>}
                {course.timer && (
                  <span className="pm-offer-chip" title="Limited offer">
                    <ion-icon name="time-outline"></ion-icon>
                    {course.timer}
                  </span>
                )}
              </div>
            )}
          </div>

          <div className="pm-card-footer-btn">
            {owned ? (
              <button
                type="button"
                className="pm-open-btn"
                onClick={() => onOpen(course)}
              >
                <ion-icon name={isResume ? 'create-outline' : 'book-outline'}></ion-icon>
                <span>{isResume ? 'Edit Template' : 'Open & Read'}</span>
                <span className="pm-buy-arrow">→</span>
              </button>
            ) : course.btnType === 'coming_soon' ? (
              <button type="button" className="pm-coming-soon-btn" disabled>
                <ion-icon name="time-outline"></ion-icon>
                <span>Coming Soon</span>
              </button>
            ) : isResume ? (
              // A resume template is free to customize; the price is asked when the student downloads the PDF
              <button
                type="button"
                className="pm-buy-btn"
                onClick={() => onOpen(course)}
              >
                <ion-icon name="create-outline"></ion-icon>
                <span>Customize</span>
                <span className="pm-buy-arrow">→</span>
              </button>
            ) : (
              <button
                type="button"
                className="pm-buy-btn"
                onClick={() => onBuy(course)}
              >
                <ion-icon name="cart-outline"></ion-icon>
                <span>Buy Now</span>
                <span className="pm-buy-arrow">→</span>
              </button>
            )}
          </div>
        </div>
      </div>
    </article>
  );
}