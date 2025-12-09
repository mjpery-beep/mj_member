import { h, render } from 'preact';
import { Dashboard } from './components/Dashboard';

// Initialize dashboards when DOM is ready
function initDashboards() {
  const containers = document.querySelectorAll('.mj-animateur-dashboard');
  
  containers.forEach(container => {
    const configAttr = container.getAttribute('data-config');
    let config = {};
    
    if (configAttr) {
      try {
        config = JSON.parse(configAttr);
      } catch (error) {
        console.error('Failed to parse dashboard config:', error);
      }
    }
    
    render(h(Dashboard, { config }), container);
  });
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initDashboards);
} else {
  initDashboards();
}
