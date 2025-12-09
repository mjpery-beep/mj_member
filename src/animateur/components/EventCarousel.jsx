import { h } from 'preact';
import { useState, useRef, useEffect } from 'preact/hooks';
import { escapeHtml } from '../utils/helpers';

export function EventCarousel({ events, allEvents, selectedId, onSelect, settings, global }) {
  const trackRef = useRef(null);
  const [canScrollLeft, setCanScrollLeft] = useState(false);
  const [canScrollRight, setCanScrollRight] = useState(false);

  const displayEvents = events.length > 0 ? events : allEvents;

  useEffect(() => {
    updateScrollButtons();
  }, [displayEvents]);

  const updateScrollButtons = () => {
    if (!trackRef.current) return;
    const { scrollLeft, scrollWidth, clientWidth } = trackRef.current;
    setCanScrollLeft(scrollLeft > 0);
    setCanScrollRight(scrollLeft + clientWidth < scrollWidth - 1);
  };

  const handleScroll = (direction) => {
    if (!trackRef.current) return;
    const scrollAmount = trackRef.current.clientWidth * 0.8;
    const newScrollLeft = direction === 'prev'
      ? trackRef.current.scrollLeft - scrollAmount
      : trackRef.current.scrollLeft + scrollAmount;
    
    trackRef.current.scrollTo({
      left: newScrollLeft,
      behavior: 'smooth'
    });
    
    setTimeout(updateScrollButtons, 300);
  };

  if (displayEvents.length === 0) {
    return (
      <div class="mj-animateur-dashboard__empty">
        <p>Aucun événement disponible.</p>
      </div>
    );
  }

  return (
    <div class="mj-animateur-dashboard__event-section">
      <div class="mj-animateur-dashboard__event-carousel">
        {canScrollLeft && (
          <button
            class="mj-animateur-dashboard__event-nav mj-animateur-dashboard__event-nav--prev"
            onClick={() => handleScroll('prev')}
            aria-label="Événement précédent"
          >
            ←
          </button>
        )}
        
        <div
          ref={trackRef}
          class="mj-animateur-dashboard__event-track"
          onScroll={updateScrollButtons}
        >
          {displayEvents.map(event => (
            <EventCard
              key={event.id}
              event={event}
              selected={event.id === selectedId}
              onSelect={onSelect}
            />
          ))}
        </div>

        {canScrollRight && (
          <button
            class="mj-animateur-dashboard__event-nav mj-animateur-dashboard__event-nav--next"
            onClick={() => handleScroll('next')}
            aria-label="Événement suivant"
          >
            →
          </button>
        )}
      </div>
    </div>
  );
}

function EventCard({ event, selected, onSelect }) {
  const coverUrl = event.cover?.url || event.cover?.thumb || 
                   event.coverUrl || event.locationCoverUrl || '';
  const title = event.title || `Événement #${event.id}`;
  const dateLabel = event.meta?.dateLabel || event.dateLabel || '';
  const typeLabel = event.meta?.typeLabel || event.typeLabel || '';
  const priceLabel = event.meta?.priceLabel || event.priceLabel || '';
  const participantLabel = event.meta?.participantCountLabel || '';

  return (
    <div
      class={`mj-animateur-dashboard__event-card ${selected ? 'is-selected' : ''}`}
      onClick={() => onSelect(event.id)}
      role="button"
      tabIndex={0}
      data-event-id={event.id}
      onKeyDown={(e) => {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          onSelect(event.id);
        }
      }}
    >
      {coverUrl && (
        <div class="mj-animateur-dashboard__event-card-media">
          <img 
            src={coverUrl} 
            alt={title}
            loading="lazy"
            decoding="async"
          />
        </div>
      )}
      
      <div class="mj-animateur-dashboard__event-card-content">
        <h3 class="mj-animateur-dashboard__event-card-title">
          {title}
        </h3>
        
        <div class="mj-animateur-dashboard__event-card-meta">
          {dateLabel && (
            <span class="mj-animateur-dashboard__event-card-meta-item">
              {dateLabel}
            </span>
          )}
          {typeLabel && (
            <span class="mj-animateur-dashboard__event-card-meta-item">
              {typeLabel}
            </span>
          )}
          {priceLabel && (
            <span class="mj-animateur-dashboard__event-card-meta-item">
              {priceLabel}
            </span>
          )}
          {participantLabel && (
            <span class="mj-animateur-dashboard__event-card-meta-item">
              {participantLabel}
            </span>
          )}
        </div>
      </div>
    </div>
  );
}
