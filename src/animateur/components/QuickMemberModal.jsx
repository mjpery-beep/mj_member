import { h } from 'preact';
import { useState } from 'preact/hooks';
import { wpAjax } from '../utils/helpers';

export function QuickMemberModal({ global, onClose, onMemberCreated }) {
  const [formData, setFormData] = useState({
    first_name: '',
    last_name: '',
    birth_date: '',
    email: ''
  });
  const [errors, setErrors] = useState({});
  const [loading, setLoading] = useState(false);
  const [feedback, setFeedback] = useState('');

  const handleChange = (field, value) => {
    setFormData(prev => ({ ...prev, [field]: value }));
    if (errors[field]) {
      setErrors(prev => {
        const newErrors = { ...prev };
        delete newErrors[field];
        return newErrors;
      });
    }
  };

  const validate = () => {
    const newErrors = {};
    
    if (!formData.first_name.trim()) {
      newErrors.first_name = 'Le prénom est obligatoire.';
    }
    if (!formData.last_name.trim()) {
      newErrors.last_name = 'Le nom est obligatoire.';
    }
    if (!formData.birth_date) {
      newErrors.birth_date = 'La date de naissance est obligatoire.';
    } else if (!/^\d{4}-\d{2}-\d{2}$/.test(formData.birth_date)) {
      newErrors.birth_date = 'Format de date invalide.';
    }
    if (formData.email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(formData.email)) {
      newErrors.email = 'Adresse email invalide.';
    }

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    
    if (!validate()) return;

    try {
      setLoading(true);
      setFeedback('');
      
      const data = await wpAjax('mj_member_animateur_quick_create_member', formData);
      
      setFeedback(data.message || 'Membre créé avec succès.');
      
      setTimeout(() => {
        onMemberCreated(data.member);
      }, 1000);
    } catch (error) {
      setFeedback(error.message || 'Impossible de créer le membre.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div class="mj-animateur-dashboard__modal-overlay" onClick={onClose}>
      <div 
        class="mj-animateur-dashboard__modal"
        onClick={(e) => e.stopPropagation()}
      >
        <div class="mj-animateur-dashboard__modal-header">
          <h3>Créer un nouveau membre</h3>
          <button 
            class="mj-animateur-dashboard__modal-close"
            onClick={onClose}
          >
            ✕
          </button>
        </div>

        <form onSubmit={handleSubmit} class="mj-animateur-dashboard__modal-body">
          {feedback && (
            <div class="mj-animateur-dashboard__feedback is-info">
              {feedback}
            </div>
          )}

          <div class="mj-animateur-dashboard__form-field">
            <label>Prénom *</label>
            <input
              type="text"
              value={formData.first_name}
              onInput={(e) => handleChange('first_name', e.target.value)}
              disabled={loading}
              class={errors.first_name ? 'has-error' : ''}
            />
            {errors.first_name && (
              <span class="mj-animateur-dashboard__field-error">
                {errors.first_name}
              </span>
            )}
          </div>

          <div class="mj-animateur-dashboard__form-field">
            <label>Nom *</label>
            <input
              type="text"
              value={formData.last_name}
              onInput={(e) => handleChange('last_name', e.target.value)}
              disabled={loading}
              class={errors.last_name ? 'has-error' : ''}
            />
            {errors.last_name && (
              <span class="mj-animateur-dashboard__field-error">
                {errors.last_name}
              </span>
            )}
          </div>

          <div class="mj-animateur-dashboard__form-field">
            <label>Date de naissance *</label>
            <input
              type="date"
              value={formData.birth_date}
              onInput={(e) => handleChange('birth_date', e.target.value)}
              disabled={loading}
              max={new Date().toISOString().split('T')[0]}
              class={errors.birth_date ? 'has-error' : ''}
            />
            {errors.birth_date && (
              <span class="mj-animateur-dashboard__field-error">
                {errors.birth_date}
              </span>
            )}
          </div>

          <div class="mj-animateur-dashboard__form-field">
            <label>Email</label>
            <input
              type="email"
              value={formData.email}
              onInput={(e) => handleChange('email', e.target.value)}
              disabled={loading}
              class={errors.email ? 'has-error' : ''}
            />
            {errors.email && (
              <span class="mj-animateur-dashboard__field-error">
                {errors.email}
              </span>
            )}
          </div>

          <div class="mj-animateur-dashboard__modal-footer">
            <button
              type="submit"
              class="mj-animateur-dashboard__button"
              disabled={loading}
            >
              {loading ? 'Création...' : 'Créer le membre'}
            </button>
            <button
              type="button"
              class="mj-animateur-dashboard__button mj-animateur-dashboard__button--secondary"
              onClick={onClose}
              disabled={loading}
            >
              Annuler
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
