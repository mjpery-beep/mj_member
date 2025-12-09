import { h } from 'preact';
import { useRef, useState, useEffect } from 'preact/hooks';

export function OccurrenceAgenda({ occurrences, selected, onSelect }) {
  const trackRef = useRef(null);
  const [canScrollLeft, setCanScrollLeft] = useState(false);
  const [canScrollRight, setCanScrollRight] = useState(false);

  if (!occurrences || occurrences.length === 0) {
    return null;
  }

  useEffect(() => {
    updateScrollButtons();
    // Scroll selected item into view
    if (trackRef.current && selected) {
      const selectedItem = trackRef.current.querySelector('[data-selected="true"]');
      if (selectedItem) {
        selectedItem.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
      }
    }
  }, [occurrences, selected]);

  const updateScrollButtons = () => {
    if (!trackRef.current) return;
    const { scrollLeft, scrollWidth, clientWidth } = trackRef.current;
    setCanScrollLeft(scrollLeft > 0);
    setCanScrollRight(scrollLeft + clientWidth < scrollWidth - 1);
  };

  const handleScroll = (direction) => {
    if (!trackRef.current) return;
    const scrollAmount = 200;
    const newScrollLeft = direction === 'prev'
      ? trackRef.current.scrollLeft - scrollAmount
      : trackRef.current.scrollLeft + scrollAmount;
    
    trackRef.current.scrollTo({
      left: newScrollLeft,
      behavior: 'smooth'
    });
    
    setTimeout(updateScrollButtons, 300);
  };

  return (
    <div class="mj-animateur-dashboard__agenda">
      <h3 class="mj-animateur-dashboard__agenda-title">Occurrences</h3>
      
      <div class="mj-animateur-dashboard__agenda-carousel">
        {canScrollLeft && (
          <button
            class="mj-animateur-dashboard__agenda-nav mj-animateur-dashboard__agenda-nav--prev"
            onClick={() => handleScroll('prev')}
            aria-label="Occurrence précédente"
          >
            ←
          </button>
        )}
        
        <div
          ref={trackRef}
          class="mj-animateur-dashboard__agenda-track"
          onScroll={updateScrollButtons}
        >
          {occurrences.map(occurrence => (
            <OccurrenceItem
              key={occurrence.start}
              occurrence={occurrence}
              selected={occurrence.start === selected}
              onSelect={onSelect}
            />
          ))}
        </div>

        {canScrollRight && (
          <button
            class="mj-animateur-dashboard__agenda-nav mj-animateur-dashboard__agenda-nav--next"
            onClick={() => handleScroll('next')}
            aria-label="Occurrence suivante"
          >
            →
          </button>
        )}
      </div>
    </div>
  );
}

function OccurrenceItem({ occurrence, selected, onSelect }) {
  const counts = occurrence.counts || { present: 0, absent: 0, pending: 0 };
  const isPast = occurrence.isPast;
  const isToday = occurrence.isToday;
  const isNext = occurrence.isNext;

  let statusClass = '';
  if (isToday) statusClass = 'is-today';
  else if (isNext) statusClass = 'is-next';
  else if (isPast) statusClass = 'is-past';

  return (
    <button
      class={`mj-animateur-dashboard__agenda-item ${selected ? 'is-selected' : ''} ${statusClass}`}
      onClick={() => onSelect(occurrence.start)}
      data-selected={selected ? 'true' : 'false'}
      data-occurrence={occurrence.start}
    >
      <div class="mj-animateur-dashboard__agenda-item-label">
        {occurrence.label}
      </div>
      <div class="mj-animateur-dashboard__agenda-item-summary">
        {occurrence.summary || 'Aucun pointage'}
      </div>
      {isToday && (
        <span class="mj-animateur-dashboard__agenda-item-badge">
          Aujourd'hui
        </span>
      )}
      {isNext && !isToday && (
        <span class="mj-animateur-dashboard__agenda-item-badge">
          Prochain
        </span>
      )}
    </button>
  );
}
