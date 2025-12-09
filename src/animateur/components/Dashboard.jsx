import { h } from 'preact';
import { useState, useEffect, useMemo } from 'preact/hooks';
import { EventCarousel } from './EventCarousel';
import { OccurrenceAgenda } from './OccurrenceAgenda';
import { ParticipantsTable } from './ParticipantsTable';
import { SmsBlock } from './SmsBlock';
import { MemberPickerModal } from './MemberPickerModal';
import { QuickMemberModal } from './QuickMemberModal';
import { useDashboardState } from '../hooks/useDashboardState';
import { wpAjax, isDraftStatus } from '../utils/helpers';

export function Dashboard({ config }) {
  const {
    state,
    events,
    allEvents,
    settings,
    global,
    currentEvent,
    currentOccurrence,
    setEventId,
    setOccurrence,
    updateEventSnapshot,
    addEvent,
    removeEvent
  } = useDashboardState(config);

  const [feedback, setFeedback] = useState({ type: '', level: '', message: '' });
  const [loading, setLoading] = useState(false);
  const [memberPickerOpen, setMemberPickerOpen] = useState(false);
  const [quickMemberOpen, setQuickMemberOpen] = useState(false);
  
  // Clear feedback after a delay
  useEffect(() => {
    if (feedback.message) {
      const timer = setTimeout(() => {
        setFeedback({ type: '', level: '', message: '' });
      }, 5000);
      return () => clearTimeout(timer);
    }
  }, [feedback]);

  const handleEventSelect = (eventId) => {
    setEventId(eventId);
    setFeedback({ type: '', level: '', message: '' });
  };

  const handleOccurrenceSelect = (occurrenceStart) => {
    setOccurrence(occurrenceStart);
    setFeedback({ type: '', level: '', message: '' });
  };

  const handleAttendanceChange = async (memberId, registrationId, status) => {
    if (!currentEvent || !currentOccurrence) return;

    try {
      setLoading(true);
      const result = await wpAjax('mj_member_animateur_save_attendance', {
        event_id: currentEvent.id,
        occurrence_start: currentOccurrence,
        entries: [{
          member_id: memberId,
          registration_id: registrationId || 0,
          status: status
        }]
      });

      // Update event with new counts
      if (result.counts) {
        const updatedEvent = { ...currentEvent };
        const occIndex = updatedEvent.occurrences.findIndex(
          occ => occ.start === currentOccurrence
        );
        if (occIndex !== -1) {
          updatedEvent.occurrences[occIndex].counts = result.counts;
        }
        updateEventSnapshot(updatedEvent);
      }

      setFeedback({
        type: 'attendance',
        level: 'success',
        message: 'Présence mise à jour.'
      });
    } catch (error) {
      setFeedback({
        type: 'attendance',
        level: 'error',
        message: error.message || 'Impossible de mettre à jour la présence.'
      });
    } finally {
      setLoading(false);
    }
  };

  const handleSendSms = async (message, recipientIds) => {
    if (!currentEvent) return;

    try {
      setLoading(true);
      const result = await wpAjax('mj_member_animateur_send_sms', {
        event_id: currentEvent.id,
        message: message,
        member_ids: recipientIds
      });

      setFeedback({
        type: 'sms',
        level: 'success',
        message: result.message || 'SMS envoyé.'
      });
    } catch (error) {
      setFeedback({
        type: 'sms',
        level: 'error',
        message: error.message || 'Impossible d\'envoyer le SMS.'
      });
    } finally {
      setLoading(false);
    }
  };

  const handlePaymentToggle = async (registrationId) => {
    if (!currentEvent) return;

    try {
      setLoading(true);
      const result = await wpAjax('mj_member_animateur_toggle_cash_payment', {
        event_id: currentEvent.id,
        registration_id: registrationId
      });

      // Update event participants
      const updatedEvent = { ...currentEvent };
      const participant = updatedEvent.participants.find(
        p => p.registrationId === registrationId
      );
      if (participant && result.payment) {
        participant.payment = result.payment;
      }
      updateEventSnapshot(updatedEvent);

      setFeedback({
        type: 'payment',
        level: 'success',
        message: 'Statut de paiement mis à jour.'
      });
    } catch (error) {
      setFeedback({
        type: 'payment',
        level: 'error',
        message: error.message || 'Impossible de mettre à jour le paiement.'
      });
    } finally {
      setLoading(false);
    }
  };

  const handleRemoveRegistration = async (registrationId) => {
    if (!currentEvent) return;
    if (!confirm('Êtes-vous sûr de vouloir supprimer cette inscription ?')) return;

    try {
      setLoading(true);
      const result = await wpAjax('mj_member_animateur_remove_registration', {
        event_id: currentEvent.id,
        registration_id: registrationId
      });

      if (result.event) {
        updateEventSnapshot(result.event);
      }

      setFeedback({
        type: 'attendance',
        level: 'success',
        message: 'Inscription supprimée.'
      });
    } catch (error) {
      setFeedback({
        type: 'attendance',
        level: 'error',
        message: error.message || 'Impossible de supprimer l\'inscription.'
      });
    } finally {
      setLoading(false);
    }
  };

  const handleMembersAdded = (updatedEvent) => {
    if (updatedEvent) {
      updateEventSnapshot(updatedEvent);
    }
    setMemberPickerOpen(false);
  };

  const handleQuickMemberCreated = () => {
    setQuickMemberOpen(false);
    // Optionally open member picker after creating a member
    if (currentEvent && settings.registrations?.canAdd) {
      setMemberPickerOpen(true);
    }
  };

  const visibleParticipants = useMemo(() => {
    if (!currentEvent || !currentOccurrence) return [];
    
    return currentEvent.participants.filter(participant => {
      const scope = participant.occurrenceScope || { mode: 'all' };
      if (scope.mode !== 'custom') return true;
      if (!scope.occurrences || !scope.occurrences.length) return false;
      return scope.occurrences.includes(currentOccurrence);
    });
  }, [currentEvent, currentOccurrence]);

  return (
    <div class="mj-animateur-dashboard__container">
      {config.title && (
        <h2 class="mj-animateur-dashboard__title">{config.title}</h2>
      )}
      {config.description && (
        <p class="mj-animateur-dashboard__intro">{config.description}</p>
      )}

      {feedback.message && (
        <div class={`mj-animateur-dashboard__feedback is-${feedback.level}`}>
          {feedback.message}
        </div>
      )}

      <EventCarousel
        events={events}
        allEvents={allEvents}
        selectedId={state.eventId}
        onSelect={handleEventSelect}
        settings={settings}
        global={global}
      />

      {currentEvent && (
        <>
          <OccurrenceAgenda
            occurrences={currentEvent.occurrences}
            selected={currentOccurrence}
            onSelect={handleOccurrenceSelect}
          />

          <div class="mj-animateur-dashboard__actions">
            {settings.registrations?.canAdd && (
              <>
                <button
                  class="mj-animateur-dashboard__button"
                  onClick={() => setMemberPickerOpen(true)}
                  disabled={loading}
                >
                  Ajouter un participant
                </button>
                {settings.quickCreate?.enabled && (
                  <button
                    class="mj-animateur-dashboard__button"
                    onClick={() => setQuickMemberOpen(true)}
                    disabled={loading}
                  >
                    Créer un membre
                  </button>
                )}
              </>
            )}
          </div>

          <ParticipantsTable
            participants={visibleParticipants}
            event={currentEvent}
            occurrence={currentOccurrence}
            settings={settings}
            onAttendanceChange={handleAttendanceChange}
            onPaymentToggle={handlePaymentToggle}
            onRemoveRegistration={handleRemoveRegistration}
            loading={loading}
          />

          {settings.sms?.enabled && (
            <SmsBlock
              participants={visibleParticipants}
              onSendSms={handleSendSms}
              loading={loading}
            />
          )}
        </>
      )}

      {memberPickerOpen && currentEvent && (
        <MemberPickerModal
          event={currentEvent}
          occurrence={currentOccurrence}
          global={global}
          onClose={() => setMemberPickerOpen(false)}
          onMembersAdded={handleMembersAdded}
        />
      )}

      {quickMemberOpen && (
        <QuickMemberModal
          global={global}
          onClose={() => setQuickMemberOpen(false)}
          onMemberCreated={handleQuickMemberCreated}
        />
      )}
    </div>
  );
}
