// Entry point bundled (once) by scripts/build-schedule-x.mjs into
// js/vendor/schedule-x/schedule-x.bundle.js as an IIFE exposing window.MjScheduleX.
//
// We deliberately use Schedule-X's framework-agnostic API (createCalendar().render(el))
// rather than the Preact wrapper, to avoid clashing with the plugin's own Preact.
//
// The theme stylesheet is imported as text and injected at runtime so there is
// exactly one file to deploy (this bundle) — no separate vendor CSS.

import themeCss from '@schedule-x/theme-default/dist/index.css'

import {
  createCalendar,
  createViewDay,
  createViewWeek,
  createViewMonthGrid,
  createViewMonthAgenda,
  createViewList,
} from '@schedule-x/calendar'
import { createDragAndDropPlugin } from '@schedule-x/drag-and-drop'
import { createResizePlugin } from '@schedule-x/resize'
import { createEventsServicePlugin } from '@schedule-x/events-service'
import { createCurrentTimePlugin } from '@schedule-x/current-time'
import { createCalendarControlsPlugin } from '@schedule-x/calendar-controls'

;(function injectTheme() {
  if (typeof document === 'undefined') return
  if (document.getElementById('mj-schedule-x-theme')) return
  var style = document.createElement('style')
  style.id = 'mj-schedule-x-theme'
  style.textContent = themeCss
  // Prepend so the page's own stylesheets (incl. css/agenda.css) can still
  // override where needed, while Schedule-X's class-scoped rules keep winning
  // over generic WP-theme element selectors on specificity.
  if (document.head.firstChild) {
    document.head.insertBefore(style, document.head.firstChild)
  } else {
    document.head.appendChild(style)
  }
})()

window.MjScheduleX = {
  createCalendar,
  views: {
    day: createViewDay,
    week: createViewWeek,
    'month-grid': createViewMonthGrid,
    'month-agenda': createViewMonthAgenda,
    list: createViewList,
  },
  plugins: {
    dragAndDrop: createDragAndDropPlugin,
    resize: createResizePlugin,
    eventsService: createEventsServicePlugin,
    currentTime: createCurrentTimePlugin,
    calendarControls: createCalendarControlsPlugin,
  },
}
