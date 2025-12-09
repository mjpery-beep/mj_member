import { useState, useEffect, useMemo } from 'preact/hooks';
import { toInt, isDraftStatus } from '../utils/helpers';

export function useDashboardState(config) {
  const [state, setState] = useState({
    eventId: null,
    occurrence: null,
    filter: 'assigned'
  });

  const [events, setEvents] = useState([]);
  const [eventsById, setEventsById] = useState({});
  const [allEvents, setAllEvents] = useState([]);
  
  // Initialize from config
  useEffect(() => {
    if (!config) return;

    // Normalize events from config
    const normalizedEvents = (config.events || [])
      .map(event => {
        const id = toInt(event?.id);
        if (id === null) return null;
        
        const statusValue = event?.status || event?.meta?.status || '';
        const isDraft = isDraftStatus(statusValue);
        
        return {
          ...event,
          id,
          __isDraft: isDraft,
          __statusValue: statusValue
        };
      })
      .filter(Boolean);

    setEvents(normalizedEvents);
    
    // Create events by ID lookup
    const byId = {};
    normalizedEvents.forEach(event => {
      byId[event.id] = event;
    });
    setEventsById(byId);

    // Normalize all events summaries
    const summaries = (config.allEvents || []).map(summary => {
      const id = toInt(summary?.id);
      if (id === null) return null;
      
      const statusValue = summary?.status || summary?.statusKey || '';
      const isDraft = isDraftStatus(statusValue);
      
      return {
        id,
        title: summary?.title || `Événement #${id}`,
        dateLabel: summary?.dateLabel || '',
        status: summary?.status || '',
        statusKey: statusValue,
        assigned: !!summary?.assigned,
        isDraft,
        coverUrl: summary?.coverUrl || '',
        locationCoverUrl: summary?.locationCoverUrl || '',
        typeLabel: summary?.typeLabel || '',
        statusLabel: summary?.statusLabel || '',
        priceLabel: summary?.priceLabel || '',
        permalink: summary?.permalink || '',
        articlePermalink: summary?.articlePermalink || ''
      };
    }).filter(Boolean);

    setAllEvents(summaries);

    // Set initial event and occurrence
    if (normalizedEvents.length > 0) {
      const firstEvent = normalizedEvents[0];
      setState(prev => ({
        ...prev,
        eventId: firstEvent.id,
        occurrence: firstEvent.defaultOccurrence || null
      }));
    }
  }, [config]);

  const currentEvent = useMemo(() => {
    if (state.eventId === null) return null;
    return eventsById[state.eventId] || null;
  }, [state.eventId, eventsById]);

  const currentOccurrence = useMemo(() => {
    if (state.occurrence) return state.occurrence;
    if (!currentEvent) return null;
    return currentEvent.defaultOccurrence || null;
  }, [state.occurrence, currentEvent]);

  const settings = useMemo(() => {
    return config?.settings || {};
  }, [config]);

  const global = useMemo(() => {
    const localizedGlobal = window.MjMemberAnimateur || {};
    return { ...localizedGlobal, ...(config?.global || {}) };
  }, [config]);

  const setEventId = (eventId) => {
    setState(prev => {
      const id = toInt(eventId);
      const event = eventsById[id];
      return {
        ...prev,
        eventId: id,
        occurrence: event?.defaultOccurrence || null
      };
    });
  };

  const setOccurrence = (occurrence) => {
    setState(prev => ({
      ...prev,
      occurrence
    }));
  };

  const updateEventSnapshot = (snapshot) => {
    if (!snapshot) return;

    const eventId = toInt(snapshot.id);
    if (eventId === null) return;

    // Update events by ID
    setEventsById(prev => ({
      ...prev,
      [eventId]: snapshot
    }));

    // Update events array
    setEvents(prev => {
      const index = prev.findIndex(e => e.id === eventId);
      if (index !== -1) {
        const newEvents = [...prev];
        newEvents[index] = snapshot;
        return newEvents;
      }
      return prev;
    });

    // If this is the current event, maintain the occurrence
    if (state.eventId === eventId) {
      const prevOccurrence = currentOccurrence;
      if (prevOccurrence && snapshot.occurrences) {
        const stillValid = snapshot.occurrences.some(
          occ => occ.start === prevOccurrence
        );
        if (!stillValid) {
          setState(prev => ({
            ...prev,
            occurrence: snapshot.defaultOccurrence || null
          }));
        }
      }
    }
  };

  const addEvent = (event) => {
    const id = toInt(event?.id);
    if (id === null) return;

    setEvents(prev => {
      if (prev.some(e => e.id === id)) return prev;
      return [...prev, event];
    });

    setEventsById(prev => ({
      ...prev,
      [id]: event
    }));
  };

  const removeEvent = (eventId) => {
    const id = toInt(eventId);
    if (id === null) return;

    setEvents(prev => prev.filter(e => e.id !== id));
    
    setEventsById(prev => {
      const { [id]: removed, ...rest } = prev;
      return rest;
    });

    if (state.eventId === id) {
      const remaining = events.filter(e => e.id !== id);
      setState(prev => ({
        ...prev,
        eventId: remaining.length > 0 ? remaining[0].id : null,
        occurrence: null
      }));
    }
  };

  return {
    state,
    events,
    allEvents,
    eventsById,
    settings,
    global,
    currentEvent,
    currentOccurrence,
    setEventId,
    setOccurrence,
    setState,
    updateEventSnapshot,
    addEvent,
    removeEvent
  };
}
