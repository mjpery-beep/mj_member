/**
 * Escape HTML to prevent XSS
 */
export function escapeHtml(value) {
  return String(value === undefined || value === null ? '' : value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

/**
 * Convert value to integer or null
 */
export function toInt(value) {
  const parsed = parseInt(value, 10);
  return isNaN(parsed) ? null : parsed;
}

/**
 * Check if status is draft
 */
export function isDraftStatus(value) {
  if (!value) return false;
  const normalized = String(value).toLowerCase();
  return normalized === 'brouillon' || normalized === 'draft';
}

/**
 * Normalize timestamp (convert to milliseconds if needed)
 */
export function normalizeTimestamp(value) {
  if (value === null || value === undefined || value === '') {
    return null;
  }
  const numeric = Number(value);
  if (!isFinite(numeric)) {
    return null;
  }
  // Convert to milliseconds if in seconds
  if (Math.abs(numeric) < 1e12) {
    return Math.round(numeric * 1000);
  }
  return Math.round(numeric);
}

/**
 * Parse date value to timestamp
 */
export function parseDateValue(value) {
  if (value === null || value === undefined || value === '') {
    return null;
  }
  if (typeof value === 'number') {
    return normalizeTimestamp(value);
  }
  let stringValue = String(value).trim();
  // Convert space to T for ISO format
  if (stringValue.indexOf(' ') > 0 && stringValue.indexOf('T') === -1) {
    stringValue = stringValue.replace(' ', 'T');
  }
  const parsed = Date.parse(stringValue);
  if (!isNaN(parsed)) {
    return parsed;
  }
  return null;
}

/**
 * Debounce function
 */
export function debounce(func, wait) {
  let timeout;
  return function executedFunction(...args) {
    const later = () => {
      clearTimeout(timeout);
      func(...args);
    };
    clearTimeout(timeout);
    timeout = setTimeout(later, wait);
  };
}

/**
 * Make AJAX request to WordPress
 */
export async function wpAjax(action, data = {}) {
  const ajaxUrl = window.MjMemberAnimateur?.ajaxUrl || '/wp-admin/admin-ajax.php';
  const nonce = window.MjMemberAnimateur?.nonce || '';
  
  const formData = new FormData();
  formData.append('action', action);
  formData.append('nonce', nonce);
  
  Object.keys(data).forEach(key => {
    if (typeof data[key] === 'object' && data[key] !== null) {
      formData.append(key, JSON.stringify(data[key]));
    } else {
      formData.append(key, data[key]);
    }
  });
  
  const response = await fetch(ajaxUrl, {
    method: 'POST',
    credentials: 'same-origin',
    body: formData
  });
  
  if (!response.ok) {
    throw new Error(`HTTP error! status: ${response.status}`);
  }
  
  const json = await response.json();
  
  if (!json.success) {
    const message = json.data?.message || 'An error occurred';
    throw new Error(message);
  }
  
  return json.data;
}
