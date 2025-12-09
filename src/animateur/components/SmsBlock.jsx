import { h } from 'preact';
import { useState } from 'preact/hooks';

export function SmsBlock({ participants, onSendSms, loading }) {
  const [message, setMessage] = useState('');
  const [selectedRecipients, setSelectedRecipients] = useState('all');

  const handleSend = () => {
    if (!message.trim()) {
      alert('Veuillez saisir un message.');
      return;
    }

    let recipientIds = [];
    if (selectedRecipients === 'all') {
      recipientIds = participants
        .filter(p => p.smsAllowed)
        .map(p => p.memberId);
    } else {
      // Could implement individual selection here
      recipientIds = participants
        .filter(p => p.smsAllowed)
        .map(p => p.memberId);
    }

    if (recipientIds.length === 0) {
      alert('Aucun participant ne peut recevoir de SMS.');
      return;
    }

    onSendSms(message, recipientIds);
    setMessage('');
  };

  const smsEnabledCount = participants.filter(p => p.smsAllowed).length;

  return (
    <div class="mj-animateur-dashboard__sms">
      <h3 class="mj-animateur-dashboard__sms-title">
        Envoyer un SMS
      </h3>
      
      <p class="mj-animateur-dashboard__sms-info">
        {smsEnabledCount} participant(s) peuvent recevoir des SMS
      </p>

      <textarea
        class="mj-animateur-dashboard__sms-message"
        placeholder="Votre message..."
        value={message}
        onInput={(e) => setMessage(e.target.value)}
        rows={4}
        disabled={loading}
      />

      <button
        class="mj-animateur-dashboard__button"
        onClick={handleSend}
        disabled={loading || !message.trim() || smsEnabledCount === 0}
      >
        {loading ? 'Envoi...' : 'Envoyer le SMS'}
      </button>
    </div>
  );
}
