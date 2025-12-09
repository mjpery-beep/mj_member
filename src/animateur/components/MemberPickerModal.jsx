import { h } from 'preact';
import { useState, useEffect } from 'preact/hooks';
import { wpAjax } from '../utils/helpers';

export function MemberPickerModal({ event, occurrence, global, onClose, onMembersAdded }) {
  const [members, setMembers] = useState([]);
  const [selected, setSelected] = useState({});
  const [search, setSearch] = useState('');
  const [loading, setLoading] = useState(false);
  const [page, setPage] = useState(1);
  const [hasMore, setHasMore] = useState(false);

  useEffect(() => {
    loadMembers(true);
  }, [search]);

  const loadMembers = async (reset = false) => {
    try {
      setLoading(true);
      const data = await wpAjax('mj_member_animateur_search_members', {
        event_id: event.id,
        occurrence: occurrence,
        search,
        page: reset ? 1 : page,
        per_page: 20
      });

      if (reset) {
        setMembers(data.members || []);
        setPage(1);
      } else {
        setMembers(prev => [...prev, ...(data.members || [])]);
      }
      setHasMore(!!data.hasMore);
    } catch (error) {
      console.error('Failed to load members:', error);
    } finally {
      setLoading(false);
    }
  };

  const handleToggle = (memberId) => {
    setSelected(prev => {
      const newSelected = { ...prev };
      if (newSelected[memberId]) {
        delete newSelected[memberId];
      } else {
        newSelected[memberId] = true;
      }
      return newSelected;
    });
  };

  const handleSubmit = async () => {
    const memberIds = Object.keys(selected).map(Number);
    if (memberIds.length === 0) {
      alert('Sélectionnez au moins un membre.');
      return;
    }

    try {
      setLoading(true);
      const data = await wpAjax('mj_member_animateur_add_members', {
        event_id: event.id,
        member_ids: memberIds,
        occurrence_scope: {
          mode: event.type === 'atelier' ? 'custom' : 'all',
          occurrences: event.type === 'atelier' ? [occurrence] : []
        }
      });

      onMembersAdded(data.event);
    } catch (error) {
      alert(error.message || 'Impossible d\'ajouter les membres.');
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
          <h3>Ajouter des participants</h3>
          <button 
            class="mj-animateur-dashboard__modal-close"
            onClick={onClose}
          >
            ✕
          </button>
        </div>

        <div class="mj-animateur-dashboard__modal-body">
          <input
            type="search"
            class="mj-animateur-dashboard__search"
            placeholder="Rechercher un membre..."
            value={search}
            onInput={(e) => setSearch(e.target.value)}
          />

          <div class="mj-animateur-dashboard__member-list">
            {members.map(member => (
              <label key={member.id} class="mj-animateur-dashboard__member-item">
                <input
                  type="checkbox"
                  checked={!!selected[member.id]}
                  onChange={() => handleToggle(member.id)}
                  disabled={member.alreadyAssigned || !member.eligible || loading}
                />
                <span class="mj-animateur-dashboard__member-name">
                  {member.fullName}
                </span>
                {member.age && (
                  <span class="mj-animateur-dashboard__member-meta">
                    {member.age} ans
                  </span>
                )}
                {member.alreadyAssigned && (
                  <span class="mj-animateur-dashboard__member-status">
                    Déjà inscrit
                  </span>
                )}
                {!member.eligible && (
                  <span class="mj-animateur-dashboard__member-status mj-animateur-dashboard__member-status--warning">
                    Non éligible
                  </span>
                )}
              </label>
            ))}
          </div>

          {loading && <div class="mj-animateur-dashboard__loading">Chargement...</div>}
        </div>

        <div class="mj-animateur-dashboard__modal-footer">
          <button
            class="mj-animateur-dashboard__button"
            onClick={handleSubmit}
            disabled={loading || Object.keys(selected).length === 0}
          >
            Ajouter ({Object.keys(selected).length})
          </button>
          <button
            class="mj-animateur-dashboard__button mj-animateur-dashboard__button--secondary"
            onClick={onClose}
            disabled={loading}
          >
            Annuler
          </button>
        </div>
      </div>
    </div>
  );
}
