import { h } from 'preact';

export function ParticipantsTable({ 
  participants, 
  event, 
  occurrence, 
  settings, 
  onAttendanceChange,
  onPaymentToggle,
  onRemoveRegistration,
  loading 
}) {
  if (!participants || participants.length === 0) {
    return (
      <div class="mj-animateur-dashboard__no-data">
        <p>Aucun participant pour cette occurrence.</p>
      </div>
    );
  }

  return (
    <div class="mj-animateur-dashboard__table-wrapper">
      <table class="mj-animateur-dashboard__table">
        <thead>
          <tr>
            <th>Participant</th>
            {settings.attendance?.enabled && <th>Présence</th>}
            {settings.payment?.enabled && <th>Paiement</th>}
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          {participants.map(participant => (
            <ParticipantRow
              key={participant.memberId}
              participant={participant}
              occurrence={occurrence}
              settings={settings}
              onAttendanceChange={onAttendanceChange}
              onPaymentToggle={onPaymentToggle}
              onRemoveRegistration={onRemoveRegistration}
              loading={loading}
            />
          ))}
        </tbody>
      </table>
    </div>
  );
}

function ParticipantRow({
  participant,
  occurrence,
  settings,
  onAttendanceChange,
  onPaymentToggle,
  onRemoveRegistration,
  loading
}) {
  const attendance = participant.attendance?.[occurrence] || 'pending';
  const payment = participant.payment || {};

  return (
    <tr class="mj-animateur-dashboard__table-row">
      <td class="mj-animateur-dashboard__participant-cell">
        <div class="mj-animateur-dashboard__participant-info">
          {participant.avatar?.url && (
            <img 
              src={participant.avatar.url} 
              alt={participant.avatar.alt || ''}
              class="mj-animateur-dashboard__participant-avatar"
            />
          )}
          <div>
            <div class="mj-animateur-dashboard__participant-name">
              {participant.fullName}
            </div>
            {(participant.age || participant.city) && (
              <div class="mj-animateur-dashboard__participant-meta">
                {participant.age && <span>{participant.age} ans</span>}
                {participant.city && <span>{participant.city}</span>}
              </div>
            )}
          </div>
        </div>
      </td>

      {settings.attendance?.enabled && (
        <td class="mj-animateur-dashboard__attendance-cell">
          <AttendanceControl
            value={attendance}
            onChange={(status) => onAttendanceChange(
              participant.memberId,
              participant.registrationId,
              status
            )}
            disabled={loading}
          />
        </td>
      )}

      {settings.payment?.enabled && (
        <td class="mj-animateur-dashboard__payment-cell">
          <button
            class={`mj-animateur-dashboard__payment-toggle ${
              payment.status === 'paid' ? 'is-paid' : ''
            }`}
            onClick={() => onPaymentToggle(participant.registrationId)}
            disabled={loading}
          >
            {payment.status_label || 'À payer'}
          </button>
        </td>
      )}

      <td class="mj-animateur-dashboard__actions-cell">
        {settings.registrations?.canDelete && (
          <button
            class="mj-animateur-dashboard__action-button mj-animateur-dashboard__action-button--danger"
            onClick={() => onRemoveRegistration(participant.registrationId)}
            disabled={loading}
            title="Supprimer"
          >
            ✕
          </button>
        )}
      </td>
    </tr>
  );
}

function AttendanceControl({ value, onChange, disabled }) {
  const options = [
    { value: 'present', label: 'Présent' },
    { value: 'absent', label: 'Absent' },
    { value: 'pending', label: 'À confirmer' }
  ];

  return (
    <div class="mj-animateur-dashboard__attendance-control">
      {options.map(option => (
        <button
          key={option.value}
          class={`mj-animateur-dashboard__attendance-option ${
            value === option.value ? 'is-active' : ''
          }`}
          onClick={() => onChange(option.value)}
          disabled={disabled}
          data-status={option.value}
        >
          {option.label}
        </button>
      ))}
    </div>
  );
}
