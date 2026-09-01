/* Schedule-X 2.36.0 — bundled for mj-member (do not edit; run npm run build:vendor) */
(()=>{var Pr=`.sx__calendar-wrapper ul,
.sx__date-picker-wrapper ul,
.sx__date-picker-popup ul {
  list-style: none;
  padding: 0;
}
.sx__calendar-wrapper input,
.sx__calendar-wrapper button,
.sx__date-picker-wrapper input,
.sx__date-picker-wrapper button,
.sx__date-picker-popup input,
.sx__date-picker-popup button {
  font-family: inherit;
  outline: none;
}
.sx__calendar-wrapper button,
.sx__date-picker-wrapper button,
.sx__date-picker-popup button {
  background-color: inherit;
  outline: 0;
  border: none;
  cursor: pointer;
}

:root {
  --sx-color-primary: #6750a4;
  --sx-color-on-primary: #fff;
  --sx-color-primary-container: #eaddff;
  --sx-color-on-primary-container: #21005e;
  --sx-color-secondary: #625b71;
  --sx-color-on-secondary: #fff;
  --sx-color-secondary-container: #e8def8;
  --sx-color-on-secondary-container: #1e192b;
  --sx-color-tertiary: #7d5260;
  --sx-color-on-tertiary: #fff;
  --sx-color-tertiary-container: #ffd8e4;
  --sx-color-on-tertiary-container: #370b1e;
  --sx-color-surface: #fef7ff;
  --sx-color-surface-dim: #ded8e1;
  --sx-color-surface-bright: #fef7ff;
  --sx-color-on-surface: #1c1b1f;
  --sx-color-surface-container: #f3edf7;
  --sx-color-surface-container-low: #f7f2fa;
  --sx-color-surface-container-high: #ece6f0;
  --sx-color-background: #fff;
  --sx-color-on-background: #1c1b1f;
  --sx-color-outline: #79747e;
  --sx-color-outline-variant: #c4c7c5;
  --sx-color-shadow: #000;
  --sx-color-surface-tint: #6750a4;
  --sx-color-neutral: var(--sx-color-outline);
  --sx-color-neutral-variant: var(--sx-color-outline-variant);
  --sx-internal-color-gray-ripple-background: #e0e0e0;
  --sx-internal-color-light-gray: #fafafa;
  --sx-internal-color-text: #000;
}

.is-dark {
  --sx-color-primary: #d0bcff;
  --sx-color-on-primary: #371e73;
  --sx-color-primary-container: #4f378b;
  --sx-color-on-primary-container: #eaddff;
  --sx-color-secondary: #ccc2dc;
  --sx-color-on-secondary: #332d41;
  --sx-color-secondary-container: #4a4458;
  --sx-color-on-secondary-container: #e8def8;
  --sx-color-tertiary: #efb8c8;
  --sx-color-on-tertiary: #492532;
  --sx-color-tertiary-container: #633b48;
  --sx-color-on-tertiary-container: #ffd8e4;
  --sx-color-surface: #141218;
  --sx-color-surface-dim: #141218;
  --sx-color-surface-bright: #3b383e;
  --sx-color-on-surface: #e6e1e5;
  --sx-color-surface-container: #211f26;
  --sx-color-surface-container-low: #1d1b20;
  --sx-color-surface-container-high: #2b2930;
  --sx-color-background: #141218;
  --sx-color-on-background: #e6e1e5;
  --sx-color-outline: #938f99;
  --sx-color-outline-variant: #444746;
  --sx-color-shadow: #000;
  --sx-color-surface-tint: #d0bcff;
  --sx-internal-color-text: #fff;
}

:root {
  --sx-spacing-padding1: 4px;
  --sx-spacing-padding2: 8px;
  --sx-spacing-padding3: 12px;
  --sx-spacing-padding4: 16px;
  --sx-spacing-padding6: 24px;
  --sx-spacing-modal-padding: 16px;
}

:root {
  --sx-box-shadow-level3: 0 3px 6px 0 rgb(0 0 0 / 16%),
    0 3px 6px 0 rgb(0 0 0 / 23%);
  --sx-rounding-extra-small: 4px;
  --sx-rounding-small: 8px;
  --sx-rounding-extra-large: 28px;
  --sx-border: 1px solid var(--sx-color-outline-variant);
}

.is-dark {
  --sx-border: 1px solid var(--sx-color-outline-variant);
}

:root {
  --sx-font-small: 0.875rem;
  --sx-font-extra-small: 0.75rem;
  --sx-font-large: 1.125rem;
  --sx-font-extra-large: 1.25rem;
}

@keyframes sx-ripple {
  0% {
    width: 0;
    height: 0;
    opacity: 0.16;
  }
  40% {
    width: 100px;
    height: 100px;
    opacity: 0.08;
  }
  100% {
    width: 150px;
    height: 150px;
    opacity: 0;
  }
}
.sx__ripple {
  position: relative;
  overflow: hidden;
}
.sx__ripple::before {
  content: "";
  position: absolute;
  top: 50%;
  left: 50%;
  width: 0;
  height: 0;
  transform: translate(-50%, -50%);
  border-radius: 50%;
  background-color: currentcolor;
  opacity: 0.1;
  visibility: hidden;
  z-index: 2;
}
.sx__ripple:active::before {
  visibility: visible;
}
.sx__ripple:not(:active)::before {
  animation: sx-ripple 0.75s cubic-bezier(0, 0.1, 0.8, 1);
  transition: visibility 0.75s step-end;
}

@keyframes sx-ripple-wide {
  0% {
    width: 0;
    height: 0;
    opacity: 0.16;
  }
  40% {
    width: 300px;
    height: 100px;
    opacity: 0.08;
  }
  100% {
    width: 450px;
    height: 150px;
    opacity: 0;
  }
}
.sx__ripple--wide {
  position: relative;
  overflow: hidden;
}
.sx__ripple--wide::before {
  content: "";
  position: absolute;
  top: 50%;
  left: 50%;
  width: 0;
  height: 0;
  transform: translate(-50%, -50%);
  border-radius: 50%;
  background-color: currentcolor;
  opacity: 0.1;
  visibility: hidden;
  z-index: 2;
}
.sx__ripple--wide:active::before {
  visibility: visible;
}
.sx__ripple--wide::before {
  border-radius: var(--sx-rounding-small);
}
.sx__ripple--wide:not(:active)::before {
  animation: sx-ripple-wide 0.75s cubic-bezier(0, 0.1, 0.8, 1);
  transition: visibility 0.75s step-end;
}

.sx__chevron-wrapper {
  position: relative;
  border-radius: 50%;
  min-height: 48px;
  min-width: 48px;
  cursor: pointer;
  transition: background-color 0.2s ease-in-out;
  font-size: 0;
}
.sx__chevron-wrapper:active {
  background-color: var(--sx-internal-color-gray-ripple-background);
}
.sx__chevron-wrapper:disabled {
  cursor: not-allowed;
  opacity: 0.5;
}
.sx__chevron-wrapper:hover, .sx__chevron-wrapper:focus {
  background-color: var(--sx-color-surface-dim);
}
.is-dark .sx__chevron-wrapper:hover, .is-dark .sx__chevron-wrapper:focus {
  background-color: var(--sx-color-surface-container-high);
}
.sx__chevron-wrapper .sx__chevron {
  position: absolute;
  top: 50%;
  width: 0.6rem;
  height: 0.6rem;
  border-width: 0.2rem 0.2rem 0 0;
  border-style: solid;
  border-color: var(--sx-internal-color-text);
}

.sx__chevron--previous {
  left: calc(50% + 0.125rem);
  transform: translate(-50%, -50%) rotate(225deg);
}
[dir=rtl] .sx__chevron--previous {
  left: calc(50% - 0.125rem);
  transform: translate(-50%, -50%) rotate(45deg);
}

.sx__chevron--next {
  left: calc(50% - 0.125rem);
  transform: translate(-50%, -50%) rotate(45deg);
}
[dir=rtl] .sx__chevron--next {
  left: calc(50% + 0.125rem);
  transform: translate(-50%, -50%) rotate(225deg);
}

.sx__date-picker-wrapper {
  position: relative;
  color: var(--sx-color-on-background);
  width: fit-content;
}
.sx__date-picker-wrapper.has-full-width {
  width: 100%;
}
.sx__date-picker-wrapper.is-disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
.sx__date-picker-wrapper * {
  color: var(--sx-color-on-background);
  box-sizing: border-box;
}

.sx__date-input-wrapper {
  position: relative;
}

.sx__date-input-chevron-wrapper {
  position: absolute;
  top: 50%;
  right: 1rem;
  transform: translateY(-50%);
  display: flex;
  align-items: center;
  padding: 0;
  transition: transform 0.2s ease-in-out;
}
.sx__date-input-chevron-wrapper:focus {
  border: 2px solid var(--sx-color-primary);
}
.is-disabled .sx__date-input-chevron-wrapper {
  pointer-events: none;
  cursor: not-allowed;
}
.sx__date-input--active .sx__date-input-chevron-wrapper {
  transform: translateY(-50%) rotate(180deg);
}
[dir=rtl] .sx__date-input-chevron-wrapper {
  left: 1rem;
  right: auto;
}

.sx__date-input-chevron {
  width: 1rem;
  height: 1rem;
  pointer-events: none;
}

.sx__date-input {
  font-size: 1rem;
  padding: var(--sx-spacing-padding4);
  border: var(--sx-border);
  border-radius: var(--sx-rounding-extra-small);
  cursor: pointer;
  background-color: var(--sx-color-background);
  width: 100%;
}
.is-disabled .sx__date-input {
  pointer-events: none;
}
.sx__date-input--active .sx__date-input {
  border-color: var(--sx-color-primary);
  outline: 1px solid var(--sx-color-primary);
}

.sx__date-input-label {
  position: absolute;
  top: 0;
  inset-inline-start: 12px;
  padding: 0 var(--sx-spacing-padding1);
  background-color: var(--sx-color-background);
  font-size: 0.75rem;
  color: var(--sx-color-neutral);
  line-height: 1rem;
  transform: translateY(-50%);
  transition: transform 0.2s ease-in-out;
  pointer-events: none;
}
.sx__date-input--active .sx__date-input-label {
  color: var(--sx-color-primary);
}
.is-dark .sx__date-input-label {
  display: none;
}

.sx__date-picker-popup {
  position: absolute;
  height: fit-content;
  z-index: 1;
  top: calc(100% + 1px);
  width: 20.75rem;
  max-width: 500px;
  max-height: 400px;
  overflow: scroll;
  box-shadow: var(--sx-box-shadow-level3);
  padding: var(--sx-spacing-modal-padding);
  background-color: var(--sx-color-background);
  color: var(--sx-internal-color-text);
}
.sx__date-picker-popup.is-dark {
  background-color: var(--sx-color-surface-container-high);
}
.sx__date-picker-popup.bottom-end {
  left: auto;
  right: 0;
  transform: translateX(0);
}
.sx__date-picker-popup.top-start {
  inset: auto auto calc(100% + 1rem) 0;
  transform: translateX(0);
}
.sx__date-picker-popup.top-end {
  inset: auto 0 calc(100% + 1rem) auto;
  transform: translateX(0);
}

.sx__date-picker__years-view {
  margin: 0;
}

.sx__date-picker__years-accordion__expand-button {
  width: 100%;
  border-radius: 0;
  background-color: transparent;
  font-size: 1rem;
  padding: 1em;
  transition: background-color 0.2s ease-in-out;
  color: var(--sx-internal-color-text);
}
.sx__is-expanded .sx__date-picker__years-accordion__expand-button {
  background-color: var(--sx-color-surface-container);
}
.sx__date-picker__years-accordion__expand-button:hover {
  background-color: var(--sx-color-surface-dim);
}
.sx__date-picker__years-accordion__expand-button:active {
  background-color: var(--sx-internal-color-gray-ripple-background);
}

.sx__date-picker__years-view-accordion__panel {
  display: flex;
  flex-wrap: wrap;
}

.sx__date-picker__years-view-accordion__month {
  flex: 1 0 33.3333%;
  background-color: transparent;
  border: 0;
  font-size: 0.9rem;
  padding: 0.5em 0;
  border-radius: 25px;
  color: var(--sx-internal-color-text);
}
.sx__date-picker__years-view-accordion__month:hover {
  background-color: var(--sx-color-primary);
  color: var(--sx-color-on-primary);
}

.sx__date-picker__day-names {
  display: flex;
  width: 100%;
  justify-content: space-evenly;
  margin-bottom: 0.5em;
}
.sx__date-picker__day-names .sx__date-picker__day,
.sx__date-picker__day-names .sx__date-picker__day-name {
  flex: 1;
  text-align: center;
}

.sx__date-picker__day-name {
  font-weight: 700;
  color: var(--sx-color-neutral-variant);
}

.sx__date-picker__month-view-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 1em;
}
.sx__date-picker__month-view-header .sx__chevron-wrapper:hover {
  background-color: var(--sx-color-surface-dim);
}

.sx__date-picker__month-view-header__month-year {
  font-size: 1.5rem;
  font-weight: 300;
  color: var(--sx-internal-color-text);
}
.sx__date-picker__month-view-header__month-year:hover {
  color: var(--sx-color-primary);
  text-decoration: underline;
}

.sx__date-picker__week {
  display: flex;
  width: 100%;
  justify-content: space-evenly;
  margin-bottom: 0.5em;
}
.sx__date-picker__week .sx__date-picker__day,
.sx__date-picker__week .sx__date-picker__day-name {
  flex: 1;
  text-align: center;
}

.sx__date-picker__day {
  background-color: transparent;
  border-radius: 50%;
  width: 2.5rem;
  height: 2.5rem;
  color: var(--sx-internal-color-text);
}
.sx__date-picker__day:hover {
  background-color: var(--sx-color-surface-dim);
}
.sx__date-picker__day:focus {
  outline-offset: -2px;
  outline: 2px solid var(--sx-color-primary);
}
.sx__date-picker__day:disabled {
  color: var(--sx-color-neutral-variant);
  cursor: not-allowed;
}
.sx__date-picker__day.is-leading-or-trailing {
  color: var(--sx-color-neutral-variant);
}
.sx__date-picker__day.sx__date-picker__day--selected {
  background-color: var(--sx-color-primary-container);
  color: var(--sx-color-on-primary-container);
}
.sx__date-picker__day.sx__date-picker__day--today {
  background-color: var(--sx-color-primary);
  color: var(--sx-color-on-primary);
}

:root {
  --sx-calendar-header-input-font-size: clamp(12px, 0.875rem, 28px);
  --sx-calendar-header-popup-z-index: 3;
  --sx-calendar-week-grid-padding-left: 75px;
}
:root .sx__date-picker-popup.is-teleported {
  z-index: 3;
}

.sx__calendar-wrapper {
  height: 100%;
  display: flex;
  color: var(--sx-internal-color-text);
}
.sx__calendar-wrapper * {
  box-sizing: border-box;
}

.sx__calendar {
  position: relative;
  flex: 1;
  height: 100%;
  border: var(--sx-border);
  border-radius: var(--sx-rounding-small);
  display: flex;
  flex-flow: column;
  background-color: var(--sx-color-background);
  overflow: hidden;
}

.sx__view-container {
  position: relative;
  flex: 1;
  overflow-y: auto;
  scroll-behavior: smooth;
}

.sx__slide-left {
  animation: sx-slide-left 0.3s ease-out;
}

@keyframes sx-slide-left {
  0% {
    transform: translateX(8%);
    filter: blur(0.25rem);
    opacity: 0.1;
  }
  100% {
    transform: translateX(0);
    filter: blur(0);
    opacity: 1;
  }
}
.sx__slide-right {
  animation: sx-slide-right 0.3s ease-out;
}

@keyframes sx-slide-right {
  0% {
    transform: translateX(-8%);
    filter: blur(0.25rem);
    opacity: 0.1;
  }
  100% {
    transform: translateX(0);
    filter: blur(0);
    opacity: 1;
  }
}
.sx__calendar-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  padding: var(--sx-spacing-padding4);
  gap: var(--sx-spacing-padding4);
}
.sx__calendar-header .sx__date-input {
  padding: var(--sx-spacing-padding3) var(--sx-spacing-padding4);
  font-size: var(--sx-calendar-header-input-font-size);
}
.sx__calendar-header .sx__date-picker-popup {
  z-index: var(--sx-calendar-header-popup-z-index);
}

.sx__calendar-header-content {
  display: flex;
  align-items: center;
  gap: var(--sx-spacing-padding4);
}

.sx__forward-backward-navigation {
  height: 45px;
}
.sx__is-calendar-small .sx__forward-backward-navigation, .is-list-view .sx__forward-backward-navigation {
  display: none;
}

.sx__calendar-header__week-number {
  border-radius: 4px;
  background-color: #eceef1;
  color: var(--sx-color-on-surface);
  padding: var(--sx-spacing-padding1) var(--sx-spacing-padding2);
  font-size: 0.75rem;
  font-weight: 500;
}
.is-dark .sx__calendar-header__week-number {
  background-color: #4a4458;
}

.sx__range-heading {
  font-size: clamp(16px, 1.25rem, 24px);
}
.sx__is-calendar-small .sx__range-heading {
  font-size: 16px;
}
.is-list-view .sx__range-heading {
  display: none;
}

.sx__today-button {
  padding: var(--sx-spacing-padding3) var(--sx-spacing-padding4);
  border-radius: var(--sx-rounding-extra-small);
  font-size: var(--sx-calendar-header-input-font-size);
  color: var(--sx-internal-color-text);
}
.sx__today-button:active {
  background-color: var(--sx-internal-color-gray-ripple-background);
}
.sx__is-calendar-small .sx__today-button {
  display: none;
}
.sx__calendar-header .sx__today-button {
  border: var(--sx-border);
}
.sx__today-button:hover, .sx__today-button:focus {
  background-color: var(--sx-internal-color-light-gray);
}
.is-dark .sx__today-button:hover, .is-dark .sx__today-button:focus {
  background-color: var(--sx-color-surface-container-low);
}

.sx__view-selection {
  position: relative;
  font-size: var(--sx-calendar-header-input-font-size);
}

.sx__view-selection-selected-item {
  height: 100%;
  width: fit-content;
  padding: var(--sx-spacing-padding3) var(--sx-spacing-padding4);
  cursor: pointer;
  border-radius: var(--sx-rounding-extra-small);
  border: var(--sx-border);
}
.sx__view-selection-selected-item:hover {
  background-color: var(--sx-internal-color-light-gray);
}
.is-dark .sx__view-selection-selected-item:hover {
  background-color: var(--sx-color-surface-container-low);
}

.sx__view-selection-items {
  position: absolute;
  top: 100%;
  box-shadow: var(--sx-box-shadow-level3);
  margin: 0;
  background-color: var(--sx-color-background);
  z-index: var(--sx-calendar-header-popup-z-index);
}
.is-dark .sx__view-selection-items {
  background-color: var(--sx-color-surface-container-high);
}

.sx__view-selection-item {
  padding: var(--sx-spacing-padding4) var(--sx-spacing-padding6);
  cursor: pointer;
}
.sx__view-selection-item:hover, .sx__view-selection-item:focus {
  background-color: var(--sx-color-primary);
  color: var(--sx-color-on-primary);
}
.sx__view-selection-item.is-selected {
  background-color: var(--sx-color-surface-dim);
}
.sx__view-selection-item.is-selected:hover, .sx__view-selection-item.is-selected:focus {
  background-color: var(--sx-color-primary);
  color: var(--sx-color-on-primary);
}

.sx__month-grid-wrapper {
  display: flex;
  flex-flow: column;
  height: 100%;
}

.sx__month-grid-week__week-number {
  display: flex;
  justify-content: center;
  padding-top: 12px;
  background-color: #eceef1;
  color: var(--sx-color-on-surface);
  width: 1.5rem;
  font-size: 0.75rem;
}
.is-dark .sx__month-grid-week__week-number {
  background-color: #4a4458;
}

.sx__month-grid-week {
  border-top: var(--sx-border);
  flex: 1;
  display: flex;
}
.sx__month-grid-week:first-child .sx__month-grid-week__week-number {
  padding-top: 26px;
}

.sx__month-grid-day {
  position: relative;
  padding: var(--sx-spacing-padding2) 0;
  flex: 1;
}
.sx__month-grid-day:not(:last-child) {
  border-inline-end: var(--sx-border);
}

.sx__month-grid-day--dragover {
  background-color: var(--sx-color-surface-container);
}

.sx__month-grid-day__header {
  display: flex;
  flex-flow: column;
  align-items: center;
}

.sx__month-grid-day__header-day-name {
  font-size: 11px;
  text-transform: uppercase;
  color: var(--sx-color-neutral);
}

.sx__month-grid-day__header-date {
  font-size: var(--sx-font-extra-small);
  margin-bottom: var(--sx-spacing-padding1);
  border-radius: 50%;
  height: 24px;
  width: 24px;
  display: flex;
  align-items: center;
  justify-content: center;
}
.sx__month-grid-day__header-date.sx__is-today {
  background-color: var(--sx-color-primary);
  color: var(--sx-color-on-primary);
}

.sx__month-grid-day__events-more {
  width: calc(100% - 10px);
  font-size: var(--sx-font-extra-small);
  color: var(--sx-color-neutral);
  margin: var(--sx-spacing-padding1) 0;
  padding: var(--sx-spacing-padding1);
  border-radius: var(--sx-rounding-extra-small);
  cursor: pointer;
  transition: background-color 0.2s ease-in-out, color 0.2s ease-in-out;
}
.sx__month-grid-day__events-more:hover {
  background-color: var(--sx-color-surface-container);
  color: var(--sx-color-on-surface);
}

.sx__month-grid-background-event {
  position: absolute;
  top: 0;
  left: 0;
  height: 100%;
  width: 100%;
}

.sx__month-grid-day__events {
  display: grid;
  grid-gap: 4px;
}

.sx__month-grid-cell {
  height: clamp(20px, 1.25rem, 24px);
}

.sx__month-grid-event {
  position: relative;
  display: flex;
  align-items: center;
  padding: var(--sx-spacing-padding1);
  border-radius: var(--sx-rounding-extra-small);
  font-size: clamp(12px, var(--sx-font-extra-small), 14px);
  overflow: hidden;
  white-space: nowrap;
  z-index: 1;
}
.sx__month-grid-event.is-event-new {
  animation: sx-grow-event 0.3s ease-in-out forwards;
}
@keyframes sx-grow-event {
  0% {
    transform: scale(0);
    opacity: 0;
  }
  100% {
    transform: scale(1);
    opacity: 1;
  }
}

.sx__month-grid-event-time {
  margin-right: 4px;
}

.sx__month-grid-blocker {
  pointer-events: none;
}

.sx__month-agenda-week {
  display: flex;
}
.sx__month-agenda-week:not(:first-child) {
  border-top: var(--sx-border);
}

.sx__month-agenda-week__week-number {
  text-align: center;
  background-color: #eceef1;
  color: var(--sx-color-on-surface);
  width: 1.5rem;
  font-size: 0.75rem;
  padding-top: 9px;
}
.is-dark .sx__month-agenda-week__week-number {
  background-color: #4a4458;
}

.sx__month-agenda-day {
  padding: var(--sx-spacing-padding2);
  flex: 1;
  display: flex;
  flex-flow: column;
  align-items: center;
  height: 3rem;
  border-radius: var(--sx-rounding-extra-small);
  color: var(--sx-internal-color-text);
}

.sx__month-agenda-day--active {
  box-shadow: inset 0 0 0 3px var(--sx-color-primary);
}

.sx__month-agenda-day__event-icons {
  margin-top: 4px;
  display: flex;
  grid-gap: 3px;
}

.sx__month-agenda-day__event-icon {
  height: 6px;
  width: 6px;
  border-radius: 50%;
  filter: brightness(1.6);
}
.is-dark .sx__month-agenda-day__event-icon {
  filter: initial;
}

.sx__month-agenda-day-names {
  display: flex;
  padding: var(--sx-spacing-padding2) 0;
  font-size: var(--sx-font-extra-small);
  color: var(--sx-color-neutral);
}
.sx__month-agenda-day-names.sx__has-week-numbers {
  padding-inline-start: 1.5rem;
}

.sx__month-agenda-day-name {
  flex: 1;
  display: flex;
  justify-content: center;
}

.sx__month-agenda-events {
  padding: 0 var(--sx-spacing-padding2);
}

.sx__month-agenda-event {
  padding: var(--sx-spacing-padding2);
  margin-bottom: var(--sx-spacing-padding2);
  border-radius: var(--sx-rounding-extra-small);
  font-size: var(--sx-font-small);
}
.sx__month-agenda-event.is-event-new {
  animation: sx-grow-event 0.3s ease-in-out forwards;
}
@keyframes sx-grow-event {
  0% {
    transform: scale(0);
    opacity: 0;
  }
  100% {
    transform: scale(1);
    opacity: 1;
  }
}
.sx__month-agenda-event:first-child {
  margin-top: var(--sx-spacing-padding2);
}

.sx__month-agenda-event__title {
  font-weight: 600;
}

.sx__month-agenda-event__has-icon {
  display: flex;
  align-items: center;
}

.sx__month-agenda-events__empty {
  margin-top: var(--sx-spacing-padding4);
  display: flex;
  justify-content: center;
}

.sx__week-wrapper {
  position: relative;
}

.sx__week-grid {
  position: relative;
  padding-left: var(--sx-calendar-week-grid-padding-left);
  display: flex;
  height: var(--sx-week-grid-height);
  overflow: hidden;
}

.sx__week-header {
  position: sticky;
  top: 0;
  z-index: 2;
  background-color: var(--sx-color-background);
}

.sx__week-header-content {
  position: relative;
}

.sx__week-header-border {
  position: absolute;
  width: 100%;
  bottom: 0;
  border-bottom: var(--sx-border);
  border-left: 250px solid transparent;
}

.sx__list-wrapper {
  padding: 0;
  background-color: var(--sx-color-background);
  height: 100%;
  overflow-y: auto;
  position: relative;
  scroll-behavior: smooth;
}

.sx__list-day {
  padding: 0;
  background-color: var(--sx-color-background);
  will-change: opacity;
  transform: translateZ(0);
}

.sx__list-day-header {
  padding: var(--sx-spacing-padding2) var(--sx-spacing-padding4);
  background-color: var(--sx-color-surface-container-low);
  position: sticky;
  top: 0;
  z-index: 1;
}

.sx__list-day-date {
  font-size: var(--sx-font-extra-small);
  font-weight: 600;
  color: var(--sx-color-neutral);
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.sx__list-day-events {
  padding: 0 16px;
  background: var(--sx-color-background);
}

.sx__list-event {
  padding: 0.75rem 0;
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
}

.sx__list-event:not(:first-child) {
  border-top: var(--sx-border);
}

.sx__list-event-color-line {
  width: 3px;
  height: 24px;
  border-radius: 2px;
  flex-shrink: 0;
}

.sx__list-event-content {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  width: 100%;
}

.sx__list-event-title {
  font-size: 1em;
  color: var(--sx-color-on-background);
  flex: 1;
}

.sx__list-event-times {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  min-width: 80px;
  gap: 2px;
}

.sx__list-event-start-time {
  font-size: 0.85em;
  color: var(--sx-color-on-background);
}

.sx__list-event-end-time {
  font-size: 0.85em;
  color: var(--sx-color-neutral);
}

.sx__list-event-arrow {
  font-size: 0.85em;
  color: var(--sx-color-neutral);
  line-height: 1;
}

.sx__list-event-all-day {
  font-size: 0.85em;
  color: var(--sx-color-neutral);
}

.sx__list-day-margin {
  height: 16px;
}

.sx__list-no-events {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  color: var(--sx-color-neutral);
  font-size: var(--sx-font-extra-small);
  text-align: center;
}

.sx__week-grid__time-axis {
  display: flex;
  flex-flow: column;
  position: absolute;
  right: 0;
  top: var(--sx-week-grid-offset-top);
  width: calc(100% - 60px);
}

.sx__week-grid__hour {
  position: relative;
  height: var(--sx-week-grid-hour-height);
  border-top: var(--sx-border);
  font-size: var(--sx-font-extra-small);
}
.sx__week-grid__hour:first-child {
  visibility: hidden;
}

.sx__week-grid__hour-text {
  position: absolute;
  left: -43px;
  top: -0.75em;
  color: var(--sx-color-neutral);
}

.sx__time-grid-day {
  position: relative;
  width: 100%;
  height: 100%;
  border-left: var(--sx-border);
}

.sx__week-grid__date-axis {
  padding-left: var(--sx-calendar-week-grid-padding-left);
  display: flex;
}

.sx__week-grid__date {
  flex: 1;
  display: flex;
  flex-flow: column;
  align-items: center;
  padding: var(--sx-spacing-padding3) 0;
  gap: var(--sx-spacing-padding1);
}

.sx__week-grid__day-name {
  text-transform: uppercase;
  font-size: var(--sx-font-extra-small);
  color: var(--sx-color-neutral);
  font-weight: 500;
}
.sx__week-grid__date--is-today .sx__week-grid__day-name {
  color: var(--sx-color-primary);
  font-weight: 700;
}

.sx__week-grid__date-number {
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: var(--sx-font-extra-large);
  font-weight: 500;
  color: var(--sx-color-neutral);
  height: 2em;
  width: 2em;
}
.sx__week-grid__date--is-today .sx__week-grid__date-number {
  background-color: var(--sx-color-primary);
  color: var(--sx-color-on-primary);
  border-radius: 50%;
}

.sx__time-grid-event {
  width: calc(100% - 10px);
  padding: var(--sx-spacing-padding1);
  position: absolute;
  border-radius: var(--sx-rounding-extra-small);
  font-size: var(--sx-font-extra-small);
  overflow: hidden;
  -webkit-user-select: none;
  user-select: none;
}
.sx__time-grid-event.is-event-copy {
  opacity: 0.5;
  box-shadow: var(--sx-box-shadow-level3);
  z-index: 1;
  transition: transform 0.15s ease-in-out;
}
.sx__time-grid-event.is-event-new {
  animation: sx-grow-event 0.3s ease-in-out forwards;
}
@keyframes sx-grow-event {
  0% {
    transform: scale(0);
    opacity: 0;
  }
  100% {
    transform: scale(1);
    opacity: 1;
  }
}

[data-has-dnd=true] .sx__time-grid-event {
  touch-action: none;
}

.sx__is-resizing .sx__time-grid-event:has(+ .is-event-copy) {
  opacity: 0;
}
.sx__is-resizing .is-event-copy {
  opacity: 1;
}

.sx__time-grid-event-inner {
  position: relative;
  height: 100%;
}

.sx__time-grid-event-resize-handle {
  display: block;
  position: absolute;
  width: 100%;
  bottom: 0;
  cursor: ns-resize;
  height: clamp(10px, 20px, 50%);
  touch-action: none;
}

.sx__time-grid-event-title {
  font-weight: 600;
}

.sx__time-grid-event-time,
.sx__time-grid-event-people,
.sx__time-grid-event-location {
  display: flex;
  align-items: center;
  white-space: nowrap;
}

.sx__event-icon {
  min-width: 15px;
  min-height: 15px;
  max-width: 15px;
  max-height: 15px;
  margin-inline-end: var(--sx-spacing-padding2);
}

.sx__date-grid {
  display: flex;
  padding-left: var(--sx-calendar-week-grid-padding-left);
}

.sx__date-grid-day {
  position: relative;
  width: 100%;
  display: grid;
  grid-gap: 2px;
  /* needed for the draw plugin */
}
.sx__date-grid-day .sx__spacer {
  display: var(--sx-draw-plugin-spacer-display, none);
  height: var(--sx-draw-plugin-spacer);
}

.sx__date-grid-event {
  z-index: 1;
  position: relative;
  display: flex;
  align-items: center;
  padding: var(--sx-spacing-padding1);
  border-radius: var(--sx-rounding-extra-small);
  font-size: clamp(12px, var(--sx-font-extra-small), 14px);
  font-weight: 600;
  user-select: none;
}
.sx__date-grid-event:has(.sx__date-grid-event--left-overflow) {
  margin-left: 10px;
}
.sx__date-grid-event:has(.sx__date-grid-event--right-overflow) {
  margin-right: 10px;
}
.sx__date-grid-event.is-event-new {
  animation: sx-grow-event 0.3s ease-in-out forwards;
}
@keyframes sx-grow-event {
  0% {
    transform: scale(0);
    opacity: 0;
  }
  100% {
    transform: scale(1);
    opacity: 1;
  }
}
.sx__date-grid-event .sx__date-grid-event--left-overflow {
  position: absolute;
  z-index: 1;
  width: 10px;
  height: 100%;
  left: -10px;
  clip-path: polygon(100% 0, 0 50%, 100% 100%, 100% 0);
}
.sx__date-grid-event .sx__date-grid-event--right-overflow {
  position: absolute;
  z-index: 1;
  width: 10px;
  height: 100%;
  right: -10px;
  clip-path: polygon(0 0, 100% 50%, 0 100%, 0 0);
}
.sx__date-grid-event.sx__date-grid-event--copy {
  z-index: 2;
  box-shadow: var(--sx-box-shadow-level3);
  transition-property: transform, width;
  transition-duration: 0.15s;
  transition-timing-function: ease-in-out;
}

.sx__date-grid-event-text {
  width: calc(100% - var(--sx-spacing-padding1) * 2);
  left: var(--sx-spacing-padding1);
  position: absolute;
  text-overflow: ellipsis;
  overflow-x: hidden;
  white-space: nowrap;
}
.sx__date-grid-event-text .sx__date-grid-event-time {
  font-weight: initial;
}

.sx__date-grid-cell {
  height: clamp(20px, 1.25rem, 24px);
}

.sx__date-grid-event-resize-handle {
  position: absolute;
  right: 0;
  height: 100%;
  width: clamp(10px, 15px, 50%);
  cursor: ew-resize;
  z-index: 1;
  touch-action: none;
}
[dir=rtl] .sx__date-grid-event-resize-handle {
  left: 0;
  right: auto;
}

.sx__date-grid-background-event {
  position: absolute;
  height: 100%;
  width: 100%;
  top: 0;
  left: 0;
  z-index: -1;
}

:root {
  --sx-week-grid-height: 0;
  --sx-time-axis-height: 0;
  --sx-week-grid-hour-height: 0;
  --sx-week-grid-offset-top: 0;
}

.sx__event-modal {
  visibility: hidden;
  position: fixed;
  top: var(--sx-event-modal-top);
  left: var(--sx-event-modal-left);
  width: 400px;
  max-width: 100%;
  height: fit-content;
  background-color: var(--sx-color-background);
  z-index: 2;
}
.sx__event-modal.is-open {
  animation: slide-sideways;
  animation-duration: 0.3s;
  visibility: initial;
}
.is-dark .sx__event-modal {
  background-color: var(--sx-color-surface-container-high);
}

.sx__event-modal-default {
  padding: var(--sx-spacing-padding6);
  background-color: var(--sx-color-background);
  box-shadow: 0 24px 38px 3px rgba(0, 0, 0, 0.14), 0 9px 46px 8px rgba(0, 0, 0, 0.12), 0 11px 15px -7px rgba(0, 0, 0, 0.2);
  border-radius: var(--sx-rounding-small);
  max-height: 250px;
  overflow-y: scroll;
}

@keyframes slide-sideways {
  from {
    opacity: 0;
    transform: translateX(var(--sx-event-modal-animation-start));
  }
  to {
    transform: translateX(0);
    opacity: 1;
  }
}
.sx__event-modal .sx__event-icon {
  min-width: 16px;
  min-height: 16px;
  max-width: 16px;
  max-height: 16px;
  margin-inline-end: var(--sx-spacing-padding2);
}

.sx__event-modal__color-icon {
  display: inline-block;
  width: 16px;
  height: 16px;
  border-radius: 25%;
  margin-inline-end: var(--sx-spacing-padding3);
}

.sx__has-icon {
  display: grid;
  align-items: flex-start;
  grid-template-columns: 30px 1fr;
  margin-bottom: var(--sx-spacing-padding2);
}
.sx__has-icon .sx__event-icon {
  margin-top: 2px;
}
.sx__has-icon .sx__event-modal__color-icon {
  margin-top: 4px;
}

.sx__event-modal__title {
  font-size: var(--sx-font-large);
}

.sx__event-modal__time {
  font-size: var(--sx-font-small);
}

.sx__current-time-indicator {
  position: absolute;
  left: 0;
  right: 0;
  height: 2px;
  background-color: #f00;
  z-index: 0;
}
.sx__current-time-indicator::before {
  content: "";
  position: absolute;
  left: -5px;
  top: -4px;
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background-color: #f00;
}

.sx__current-time-indicator-full-week {
  width: calc(100% - var(--sx-calendar-week-grid-padding-left));
  position: absolute;
  inset: 0 0 0 var(--sx-calendar-week-grid-padding-left);
  height: 2px;
  background-color: rgba(255, 0, 0, 0.38);
}
`;var ct,x,Mr,$t,me,Sr,Or,Tr,It,it,We,Nr,Ut,Rt,Ft,Ar,ot={},st=[],ja=/acit|ex(?:s|g|n|p|$)|rph|grid|ows|mnc|ntw|ine[ch]|zoo|^ord|itera/i,$e=Array.isArray;function he(e,t){for(var n in t)e[n]=t[n];return e}function Vt(e){e&&e.parentNode&&e.parentNode.removeChild(e)}function ie(e,t,n){var r,i,a,o={};for(a in t)a=="key"?r=t[a]:a=="ref"?i=t[a]:o[a]=t[a];if(arguments.length>2&&(o.children=arguments.length>3?ct.call(arguments,2):n),typeof e=="function"&&e.defaultProps!=null)for(a in e.defaultProps)o[a]===void 0&&(o[a]=e.defaultProps[a]);return at(e,o,r,i,null)}function at(e,t,n,r,i){var a={type:e,props:t,key:n,ref:r,__k:null,__:null,__b:0,__e:null,__c:null,constructor:void 0,__v:i==null?++Mr:i,__i:-1,__u:0};return i==null&&x.vnode!=null&&x.vnode(a),a}function k(e){return e.children}function J(e,t){this.props=e,this.context=t}function xe(e,t){if(t==null)return e.__?xe(e.__,e.__i+1):null;for(var n;t<e.__k.length;t++)if((n=e.__k[t])!=null&&n.__e!=null)return n.__e;return typeof e.type=="function"?xe(e):null}function La(e){if(e.__P&&e.__d){var t=e.__v,n=t.__e,r=[],i=[],a=he({},t);a.__v=t.__v+1,x.vnode&&x.vnode(a),zt(e.__P,a,t,e.__n,e.__P.namespaceURI,32&t.__u?[n]:null,r,n==null?xe(t):n,!!(32&t.__u),i),a.__v=t.__v,a.__.__k[a.__i]=a,Rr(r,a,i),t.__e=t.__=null,a.__e!=n&&Yr(a)}}function Yr(e){if((e=e.__)!=null&&e.__c!=null)return e.__e=e.__c.base=null,e.__k.some(function(t){if(t!=null&&t.__e!=null)return e.__e=e.__c.base=t.__e}),Yr(e)}function Wt(e){(!e.__d&&(e.__d=!0)&&me.push(e)&&!lt.__r++||Sr!=x.debounceRendering)&&((Sr=x.debounceRendering)||Or)(lt)}function lt(){try{for(var e,t=1;me.length;)me.length>t&&me.sort(Tr),e=me.shift(),t=me.length,La(e)}finally{me.length=lt.__r=0}}function jr(e,t,n,r,i,a,o,s,l,c,h){var p,u,v,m,f,_,g=r&&r.__k||st,b=t.length;for(l=Ia(n,t,g,l,b),p=0;p<b;p++)(v=n.__k[p])!=null&&(u=v.__i!=-1&&g[v.__i]||ot,v.__i=p,_=zt(e,v,u,i,a,o,s,l,c,h),m=v.__e,v.ref&&u.ref!=v.ref&&(u.ref&&Ht(u.ref,null,v),h.push(v.ref,v.__c||m,v)),f==null&&m!=null&&(f=m),4&v.__u?(l=Lr(v,l,e),u.__e&&(u.__e=null)):typeof v.type=="function"&&_!==void 0?l=_:m&&(l=m.nextSibling),v.__u&=-7);return n.__e=f,l}function Ia(e,t,n,r,i){var a,o,s,l,c,h=n.length,p=h,u=0;for(e.__k=new Array(i),a=0;a<i;a++)(o=t[a])!=null&&typeof o!="boolean"&&typeof o!="function"?(typeof o=="string"||typeof o=="number"||typeof o=="bigint"||o.constructor==String?o=e.__k[a]=at(null,o,null,null,null):$e(o)?o=e.__k[a]=at(k,{children:o},null,null,null):o.constructor===void 0&&o.__b>0?o=e.__k[a]=at(o.type,o.props,o.key,o.ref?o.ref:null,o.__v):e.__k[a]=o,l=a+u,o.__=e,o.__b=e.__b+1,s=null,(c=o.__i=Ra(o,n,l,p))!=-1&&(p--,(s=n[c])&&(s.__u|=2)),s==null||s.__v==null?(c==-1&&(i>h?u--:i<h&&u++),typeof o.type!="function"&&(o.__u|=4)):c!=l&&(c==l-1?u--:c==l+1?u++:(c>l?u--:u++,o.__u|=4))):e.__k[a]=null;if(p)for(a=0;a<h;a++)(s=n[a])!=null&&(2&s.__u)==0&&(s.__e==r&&(r=xe(s)),Wr(s,s));return r}function Lr(e,t,n){var r,i;if(typeof e.type=="function"){for(r=e.__k,i=0;r&&i<r.length;i++)r[i]&&(r[i].__=e,t=Lr(r[i],t,n));return t}e.__e!=t&&(t&&e.type&&!t.parentNode&&(t=xe(e)),t=n.insertBefore(e.__e,t||null));do t=t&&t.nextSibling;while(t!=null&&t.nodeType==8);return t}function Ue(e,t){return t=t||[],e==null||typeof e=="boolean"||($e(e)?e.some(function(n){Ue(n,t)}):t.push(e)),t}function Ra(e,t,n,r){var i,a,o,s=e.key,l=e.type,c=t[n],h=c!=null&&(2&c.__u)==0;if(c===null&&s==null||h&&s==c.key&&l==c.type)return n;if(r>(h?1:0)){for(i=n-1,a=n+1;i>=0||a<t.length;)if((c=t[o=i>=0?i--:a++])!=null&&(2&c.__u)==0&&s==c.key&&l==c.type)return o}return-1}function Er(e,t,n){t[0]=="-"?e.setProperty(t,n==null?"":n):e[t]=n==null?"":typeof n!="number"||ja.test(t)?n:n+"px"}function rt(e,t,n,r,i){var a,o;e:if(t=="style")if(typeof n=="string")e.style.cssText=n;else{if(typeof r=="string"&&(e.style.cssText=r=""),r)for(t in r)n&&t in n||Er(e.style,t,"");if(n)for(t in n)r&&n[t]==r[t]||Er(e.style,t,n[t])}else if(t[0]=="o"&&t[1]=="n")a=t!=(t=t.replace(Nr,"$1")),o=t.toLowerCase(),t=o in e||t=="onFocusOut"||t=="onFocusIn"?o.slice(2):t.slice(2),e.l||(e.l={}),e.l[t+a]=n,n?r?n[We]=r[We]:(n[We]=Ut,e.addEventListener(t,a?Ft:Rt,a)):e.removeEventListener(t,a?Ft:Rt,a);else{if(i=="http://www.w3.org/2000/svg")t=t.replace(/xlink(H|:h)/,"h").replace(/sName$/,"s");else if(t!="width"&&t!="height"&&t!="href"&&t!="list"&&t!="form"&&t!="tabIndex"&&t!="download"&&t!="rowSpan"&&t!="colSpan"&&t!="role"&&t!="popover"&&t in e)try{e[t]=n==null?"":n;break e}catch{}typeof n=="function"||(n==null||n===!1&&t[4]!="-"?e.removeAttribute(t):e.setAttribute(t,t=="popover"&&n==1?"":n))}}function Cr(e){return function(t){if(this.l){var n=this.l[t.type+e];if(t[it]==null)t[it]=Ut++;else if(t[it]<n[We])return;return n(x.event?x.event(t):t)}}}function zt(e,t,n,r,i,a,o,s,l,c){var h,p,u,v,m,f,_,g,b,D,E,L,A,ne,$,z,w=t.type;if(t.constructor!==void 0)return null;128&n.__u&&(l=!!(32&n.__u),a=[s=t.__e=n.__e]),(h=x.__b)&&h(t);e:if(typeof w=="function"){p=o.length;try{if(b=t.props,D=w.prototype&&w.prototype.render,E=(h=w.contextType)&&r[h.__c],L=h?E?E.props.value:h.__:r,n.__c?g=(u=t.__c=n.__c).__=u.__E:(D?t.__c=u=new w(b,L):(t.__c=u=new J(b,L),u.constructor=w,u.render=Wa),E&&E.sub(u),u.state||(u.state={}),u.__n=r,v=u.__d=!0,u.__h=[],u._sb=[]),D&&u.__s==null&&(u.__s=u.state),D&&w.getDerivedStateFromProps!=null&&(u.__s==u.state&&(u.__s=he({},u.__s)),he(u.__s,w.getDerivedStateFromProps(b,u.__s))),m=u.props,f=u.state,u.__v=t,v)D&&w.getDerivedStateFromProps==null&&u.componentWillMount!=null&&u.componentWillMount(),D&&u.componentDidMount!=null&&u.__h.push(u.componentDidMount);else{if(D&&w.getDerivedStateFromProps==null&&b!==m&&u.componentWillReceiveProps!=null&&u.componentWillReceiveProps(b,L),t.__v==n.__v||!u.__e&&u.shouldComponentUpdate!=null&&u.shouldComponentUpdate(b,u.__s,L)===!1){t.__v!=n.__v&&(u.props=b,u.state=u.__s,u.__d=!1),t.__e=n.__e,t.__k=n.__k,t.__k.some(function(y){y&&(y.__=t)}),st.push.apply(u.__h,u._sb),u._sb=[],u.__h.length&&o.push(u),s=xe(n);break e}u.componentWillUpdate!=null&&u.componentWillUpdate(b,u.__s,L),D&&u.componentDidUpdate!=null&&u.__h.push(function(){u.componentDidUpdate(m,f,_)})}if(u.context=L,u.props=b,u.__P=e,u.__e=!1,A=x.__r,ne=0,D)u.state=u.__s,u.__d=!1,A&&A(t),h=u.render(u.props,u.state,u.context),st.push.apply(u.__h,u._sb),u._sb=[];else do u.__d=!1,A&&A(t),h=u.render(u.props,u.state,u.context),u.state=u.__s;while(u.__d&&++ne<25);u.state=u.__s,u.getChildContext!=null&&(r=he(he({},r),u.getChildContext())),D&&!v&&u.getSnapshotBeforeUpdate!=null&&(_=u.getSnapshotBeforeUpdate(m,f)),$=h!=null&&h.type===k&&h.key==null?Fr(h.props.children):h,s=jr(e,$e($)?$:[$],t,n,r,i,a,o,s,l,c),u.base=t.__e,t.__u&=-161,u.__h.length&&o.push(u),g&&(u.__E=u.__=null)}catch(y){if(o.length=p,t.__v=null,l||a!=null){if(y.then){for(t.__u|=l?160:128;s&&s.nodeType==8&&s.nextSibling;)s=s.nextSibling;a!=null&&(a[a.indexOf(s)]=null),t.__e=s}else if(a!=null)for(z=a.length;z--;)Vt(a[z])}else t.__e=n.__e;t.__k==null&&(t.__k=n.__k||[]),y.then||Ir(t),x.__e(y,t,n)}}else a==null&&t.__v==n.__v?(t.__k=n.__k,t.__e=n.__e):s=t.__e=Fa(n.__e,t,n,r,i,a,o,l,c);return(h=x.diffed)&&h(t),128&t.__u?void 0:s}function Ir(e){e&&(e.__c&&(e.__c.__e=!0),e.__k&&e.__k.some(Ir))}function Rr(e,t,n){for(var r=0;r<n.length;r++)Ht(n[r],n[++r],n[++r]);x.__c&&x.__c(t,e),e.some(function(i){try{e=i.__h,i.__h=[],e.some(function(a){a.call(i)})}catch(a){x.__e(a,i.__v)}})}function Fr(e){return typeof e!="object"||e==null||e.__b>0?e:$e(e)?e.map(Fr):e.constructor!==void 0?null:he({},e)}function Fa(e,t,n,r,i,a,o,s,l){var c,h,p,u,v,m,f,_=n.props||ot,g=t.props,b=t.type;if(b=="svg"?i="http://www.w3.org/2000/svg":b=="math"?i="http://www.w3.org/1998/Math/MathML":i||(i="http://www.w3.org/1999/xhtml"),a!=null){for(c=0;c<a.length;c++)if((v=a[c])&&"setAttribute"in v==!!b&&(b?v.localName==b:v.nodeType==3)){e=v,a[c]=null;break}}if(e==null){if(b==null)return document.createTextNode(g);e=document.createElementNS(i,b,g.is&&g),s&&(x.__m&&x.__m(t,a),s=!1),a=null}if(b==null)_===g||s&&e.data==g||(e.data=g);else{if(a=b=="textarea"&&g.defaultValue!=null?null:a&&ct.call(e.childNodes),!s&&a!=null)for(_={},c=0;c<e.attributes.length;c++)_[(v=e.attributes[c]).name]=v.value;for(c in _)v=_[c],c=="dangerouslySetInnerHTML"?p=v:c=="children"||c in g||c=="value"&&"defaultValue"in g||c=="checked"&&"defaultChecked"in g||rt(e,c,null,v,i);for(c in g)v=g[c],c=="children"?u=v:c=="dangerouslySetInnerHTML"?h=v:c=="value"?m=v:c=="checked"?f=v:s&&typeof v!="function"||_[c]===v||rt(e,c,v,_[c],i);if(h)s||p&&(h.__html==p.__html||h.__html==e.innerHTML)||(e.innerHTML=h.__html),t.__k=[];else if(p&&(e.innerHTML=""),jr(t.type=="template"?e.content:e,$e(u)?u:[u],t,n,r,b=="foreignObject"?"http://www.w3.org/1999/xhtml":i,a,o,a?a[0]:n.__k&&xe(n,0),s,l),a!=null)for(c=a.length;c--;)Vt(a[c]);s&&b!="textarea"||(c="value",b=="progress"&&m==null?e.removeAttribute("value"):m!=null&&(m!==e[c]||b=="progress"&&!m||b=="option"&&m!=_[c])&&rt(e,c,m,_[c],i),c="checked",f!=null&&f!=e[c]&&rt(e,c,f,_[c],i))}return e}function Ht(e,t,n){try{if(typeof e=="function"){var r=typeof e.__u=="function";r&&e.__u(),r&&t==null||(e.__u=e(t))}else e.current=t}catch(i){x.__e(i,n)}}function Wr(e,t,n){var r,i;if(x.unmount&&x.unmount(e),(r=e.ref)&&(r.current&&r.current!=e.__e||Ht(r,null,t)),(r=e.__c)!=null){if(r.componentWillUnmount)try{r.componentWillUnmount()}catch(a){x.__e(a,t)}r.base=r.__P=r.__n=null}if(r=e.__k)for(i=0;i<r.length;i++)r[i]&&Wr(r[i],t,n||typeof e.type!="function");n||Vt(e.__e),e.__c=e.__=e.__e=void 0}function Wa(e,t,n){return this.constructor(e,n)}function De(e,t,n){var r,i,a,o;t==document&&(t=document.documentElement),x.__&&x.__(e,t),i=(r=typeof n=="function")?null:n&&n.__k||t.__k,a=[],o=[],zt(t,e=(!r&&n||t).__k=ie(k,null,[e]),i||ot,ot,t.namespaceURI,!r&&n?[n]:i?null:t.firstChild?ct.call(t.childNodes):null,a,!r&&n?n:i?i.__e:t.firstChild,r,o),Rr(a,e,o),e.props.children=null}function Ve(e){function t(n){var r,i;return this.getChildContext||(r=new Set,(i={})[t.__c]=this,this.getChildContext=function(){return i},this.componentWillUnmount=function(){r=null},this.shouldComponentUpdate=function(a){this.props.value!=a.value&&r.forEach(function(o){o.__e=!0,Wt(o)})},this.sub=function(a){r.add(a);var o=a.componentWillUnmount;a.componentWillUnmount=function(){r&&r.delete(a),o&&o.call(a)}}),n.children}return t.__c="__cC"+Ar++,t.__=e,t.Provider=t.__l=(t.Consumer=function(n,r){return n.children(r)}).contextType=t,t}ct=st.slice,x={__e:function(e,t,n,r){for(var i,a,o;t=t.__;)if((i=t.__c)&&!i.__)try{if((a=i.constructor)&&a.getDerivedStateFromError!=null&&(i.setState(a.getDerivedStateFromError(e)),o=i.__d),i.componentDidCatch!=null&&(i.componentDidCatch(e,r||{}),o=i.__d),o)return i.__E=i}catch(s){e=s}throw e}},Mr=0,$t=function(e){return e!=null&&e.constructor===void 0},J.prototype.setState=function(e,t){var n;n=this.__s!=null&&this.__s!=this.state?this.__s:this.__s=he({},this.state),typeof e=="function"&&(e=e(he({},n),this.props)),e&&he(n,e),e!=null&&this.__v&&(t&&this._sb.push(t),Wt(this))},J.prototype.forceUpdate=function(e){this.__v&&(this.__e=!0,e&&this.__h.push(e),Wt(this))},J.prototype.render=k,me=[],Or=typeof Promise=="function"?Promise.prototype.then.bind(Promise.resolve()):setTimeout,Tr=function(e,t){return e.__v.__b-t.__v.__b},lt.__r=0,It=Math.random().toString(8),it="__d"+It,We="__a"+It,Nr=/(PointerCapture)$|Capture$/i,Ut=0,Rt=Cr(!1),Ft=Cr(!0),Ar=0;var $a=0;function d(e,t,n,r,i,a){t||(t={});var o,s,l=t;if("ref"in l)for(s in l={},t)s=="ref"?o=t[s]:l[s]=t[s];var c={type:e,props:l,key:n,ref:o,__k:null,__:null,__b:0,__e:null,__c:null,constructor:void 0,__v:--$a,__i:-1,__u:0,__source:i,__self:a};if(typeof e=="function"&&(o=e.defaultProps))for(s in o)l[s]===void 0&&(l[s]=o[s]);return x.vnode&&x.vnode(c),c}var Me,R,Gt,$r,dt=0,Xr=[],U=x,Ur=U.__b,Vr=U.__r,zr=U.diffed,Hr=U.__c,Gr=U.unmount,Kr=U.__;function ht(e,t){U.__h&&U.__h(R,e,dt||t),dt=0;var n=R.__H||(R.__H={__:[],__h:[]});return e>=n.__.length&&n.__.push({}),n.__[e]}function P(e){return dt=1,qr(Zr,e)}function qr(e,t,n){var r=ht(Me++,2);if(r.t=e,!r.__c&&(r.__=[n?n(t):Zr(void 0,t),function(s){var l=r.__N?r.__N[0]:r.__[0],c=r.t(l,s);l!==c&&(r.__N=[c,r.__[1]],r.__c.setState({}))}],r.__c=R,!R.__f)){var i=function(s,l,c){if(!r.__c.__H)return!0;var h=!1,p=r.__c.props!==s;if(r.__c.__H.__.some(function(v){if(v.__N){h=!0;var m=v.__[0];v.__=v.__N,v.__N=void 0,m!==v.__[0]&&(p=!0)}}),a){var u=a.call(this,s,l,c);return h?u||p:u}return!h||p};R.__f=!0;var a=R.shouldComponentUpdate,o=R.componentWillUpdate;R.componentWillUpdate=function(s,l,c){if(this.__e){var h=a;a=void 0,i(s,l,c),a=h}o&&o.call(this,s,l,c)},R.shouldComponentUpdate=i}return r.__N||r.__}function M(e,t){var n=ht(Me++,3);!U.__s&&Jr(n.__H,t)&&(n.__=e,n.u=t,R.__H.__h.push(n))}function _e(e){return dt=5,X(function(){return{current:e}},[])}function X(e,t){var n=ht(Me++,7);return Jr(n.__H,t)&&(n.__=e(),n.__H=t,n.__h=e),n.__}function N(e){var t=R.context[e.__c],n=ht(Me++,9);return n.c=e,t?(n.__==null&&(n.__=!0,t.sub(R)),t.props.value):e.__}function Ua(){for(var e;e=Xr.shift();){var t=e.__H;if(e.__P&&t)try{t.__h.some(ut),t.__h.some(Kt),t.__h=[]}catch(n){t.__h=[],U.__e(n,e.__v)}}}U.__b=function(e){R=null,Ur&&Ur(e)},U.__=function(e,t){e&&t.__k&&t.__k.__m&&(e.__m=t.__k.__m),Kr&&Kr(e,t)},U.__r=function(e){Vr&&Vr(e),Me=0;var t=(R=e.__c).__H;t&&(Gt===R?(t.__h=[],R.__h=[],t.__.some(function(n){n.__N&&(n.__=n.__N),n.u=n.__N=void 0})):(t.__h.some(ut),t.__h.some(Kt),t.__h=[],Me=0)),Gt=R},U.diffed=function(e){zr&&zr(e);var t=e.__c;t&&t.__H&&(t.__H.__h.length&&(Xr.push(t)!==1&&$r===U.requestAnimationFrame||(($r=U.requestAnimationFrame)||Va)(Ua)),t.__H.__.some(function(n){n.u&&(n.__H=n.u,n.u=void 0)})),Gt=R=null},U.__c=function(e,t){t.some(function(n){try{n.__h.some(ut),n.__h=n.__h.filter(function(r){return!r.__||Kt(r)})}catch(r){t.some(function(i){i.__h&&(i.__h=[])}),t=[],U.__e(r,n.__v)}}),Hr&&Hr(e,t)},U.unmount=function(e){Gr&&Gr(e);var t,n=e.__c;n&&n.__H&&(n.__H.__.some(function(r){try{ut(r)}catch(i){t=i}}),n.__H=void 0,t&&U.__e(t,n.__v))};var Br=typeof requestAnimationFrame=="function";function Va(e){var t,n=function(){clearTimeout(r),Br&&cancelAnimationFrame(t),setTimeout(e)},r=setTimeout(n,35);Br&&(t=requestAnimationFrame(n))}function ut(e){var t=R,n=e.__c;typeof n=="function"&&(e.__c=void 0,n()),R=t}function Kt(e){var t=R;e.__c=e.__(),R=t}function Jr(e,t){return!e||e.length!==t.length||t.some(function(n,r){return n!==e[r]})}function Zr(e,t){return typeof t=="function"?t(e):t}function Ha(e,t){for(var n in t)e[n]=t[n];return e}function Qr(e,t){for(var n in e)if(n!=="__source"&&!(n in t))return!0;for(var r in t)if(r!=="__source"&&e[r]!==t[r])return!0;return!1}function ei(e,t){this.props=e,this.context=t}(ei.prototype=new J).isPureReactComponent=!0,ei.prototype.shouldComponentUpdate=function(e,t){return Qr(this.props,e)||Qr(this.state,t)};var ti=x.__b;x.__b=function(e){e.type&&e.type.__f&&e.ref&&(e.props.ref=e.ref,e.ref=null),ti&&ti(e)};var Fd=typeof Symbol!="undefined"&&Symbol.for&&Symbol.for("react.forward_ref")||3911;var Ga=x.__e;x.__e=function(e,t,n,r){if(e.then){for(var i,a=t;a=a.__;)if((i=a.__c)&&i.__c)return t.__e==null&&(t.__e=n.__e,t.__k=n.__k||[]),i.__c(e,t)}Ga(e,t,n,r)};var ni=x.unmount;function li(e,t,n){return e&&(e.__c&&e.__c.__H&&(e.__c.__H.__.forEach(function(r){typeof r.__c=="function"&&r.__c()}),e.__c.__H=null),(e=Ha({},e)).__c!=null&&(e.__c.__P===n&&(e.__c.__P=t),e.__c.__e=!0,e.__c=null),e.__k=e.__k&&e.__k.map(function(r){return li(r,t,n)})),e}function ci(e,t,n){return e&&n&&(e.__v=null,e.__k=e.__k&&e.__k.map(function(r){return ci(r,t,n)}),e.__c&&e.__c.__P===t&&(e.__e&&n.appendChild(e.__e),e.__c.__e=!0,e.__c.__P=n)),e}function Bt(){this.__u=0,this.o=null,this.__b=null}function ui(e){var t=e.__&&e.__.__c;return t&&t.__a&&t.__a(e)}function vt(){this.i=null,this.l=null}x.unmount=function(e){var t=e.__c;t&&(t.__z=!0),t&&t.__R&&t.__R(),t&&32&e.__u&&(e.type=null),ni&&ni(e)},(Bt.prototype=new J).__c=function(e,t){var n=t.__c,r=this;r.o==null&&(r.o=[]),r.o.push(n);var i=ui(r.__v),a=!1,o=function(){a||r.__z||(a=!0,n.__R=null,i?i(l):l())};n.__R=o;var s=n.__P;n.__P=null;var l=function(){if(!--r.__u){if(r.state.__a){var c=r.state.__a;r.__v.__k[0]=ci(c,c.__c.__P,c.__c.__O)}var h;for(r.setState({__a:r.__b=null});h=r.o.pop();)h.__P=s,h.forceUpdate()}};r.__u++||32&t.__u||r.setState({__a:r.__b=r.__v.__k[0]}),e.then(o,o)},Bt.prototype.componentWillUnmount=function(){this.o=[]},Bt.prototype.render=function(e,t){if(this.__b){if(this.__v.__k){var n=document.createElement("div"),r=this.__v.__k[0].__c;this.__v.__k[0]=li(this.__b,n,r.__O=r.__P)}this.__b=null}var i=t.__a&&ie(k,null,e.fallback);return i&&(i.__u&=-33),[ie(k,null,t.__a?null:e.children),i]};var ri=function(e,t,n){if(++n[1]===n[0]&&e.l.delete(t),e.props.revealOrder&&(e.props.revealOrder[0]!=="t"||!e.l.size))for(n=e.i;n;){for(;n.length>3;)n.pop()();if(n[1]<n[0])break;e.i=n=n[2]}};function Ka(e){return this.getChildContext=function(){return e.context},e.children}function Ba(e){var t=this,n=e.h;if(t.componentWillUnmount=function(){De(null,t.v),t.v=null,t.h=null},t.h&&t.h!==n&&t.componentWillUnmount(),!t.v){for(var r=t.__v;r!==null&&!r.__m&&r.__!==null;)r=r.__;t.h=n,t.v={nodeType:1,parentNode:n,childNodes:[],__k:{__m:r.__m},contains:function(){return!0},namespaceURI:n.namespaceURI,insertBefore:function(i,a){this.childNodes.push(i),t.h.insertBefore(i,a)},removeChild:function(i){this.childNodes.splice(this.childNodes.indexOf(i)>>>1,1),t.h.removeChild(i)}}}De(ie(Ka,{context:t.context},e.__v),t.v)}function di(e,t){var n=ie(Ba,{__v:e,h:t});return n.containerInfo=t,n}(vt.prototype=new J).__a=function(e){var t=this,n=ui(t.__v),r=t.l.get(e);return r[0]++,function(i){var a=function(){t.props.revealOrder?(r.push(i),ri(t,e,r)):i()};n?n(a):a()}},vt.prototype.render=function(e){this.i=null,this.l=new Map;var t=Ue(e.children);e.revealOrder&&e.revealOrder[0]==="b"&&t.reverse();for(var n=t.length;n--;)this.l.set(t[n],this.i=[1,0,this.i]);return e.children},vt.prototype.componentDidUpdate=vt.prototype.componentDidMount=function(){var e=this;this.l.forEach(function(t,n){ri(e,n,t)})};var Xa=typeof Symbol!="undefined"&&Symbol.for&&Symbol.for("react.element")||60103,qa=/^(?:accent|alignment|arabic|baseline|cap|clip(?!PathU)|color|dominant|fill|flood|font|glyph(?!R)|horiz|image(!S)|letter|lighting|marker(?!H|W|U)|overline|paint|pointer|shape|stop|strikethrough|stroke|text(?!L)|transform|underline|unicode|units|v|vector|vert|word|writing|x(?!C))[A-Z]/,Ja=/^on(Ani|Tra|Tou|BeforeInp|Compo)/,Za=/[A-Z0-9]/g,Qa=typeof document!="undefined",eo=function(e){return(typeof Symbol!="undefined"&&typeof Symbol()=="symbol"?/fil|che|rad/:/fil|che|ra/).test(e)};J.prototype.isReactComponent=!0,["componentWillMount","componentWillReceiveProps","componentWillUpdate"].forEach(function(e){Object.defineProperty(J.prototype,e,{configurable:!0,get:function(){return this["UNSAFE_"+e]},set:function(t){Object.defineProperty(this,e,{configurable:!0,writable:!0,value:t})}})});var ii=x.event;x.event=function(e){return ii&&(e=ii(e)),e.persist=function(){},e.isPropagationStopped=function(){return this.cancelBubble},e.isDefaultPrevented=function(){return this.defaultPrevented},e.nativeEvent=e};var hi,to={configurable:!0,get:function(){return this.class}},ai=x.vnode;x.vnode=function(e){typeof e.type=="string"&&(function(t){var n=t.props,r=t.type,i={},a=r.indexOf("-")==-1;for(var o in n){var s=n[o];if(!(o==="value"&&"defaultValue"in n&&s==null||Qa&&o==="children"&&r==="noscript"||o==="class"||o==="className")){var l=o.toLowerCase();o==="defaultValue"&&"value"in n&&n.value==null?o="value":o==="download"&&s===!0?s="":l==="translate"&&s==="no"?s=!1:l[0]==="o"&&l[1]==="n"?l==="ondoubleclick"?o="ondblclick":l!=="onchange"||r!=="input"&&r!=="textarea"||eo(n.type)?l==="onfocus"?o="onfocusin":l==="onblur"?o="onfocusout":Ja.test(o)&&(o=l):l=o="oninput":a&&qa.test(o)?o=o.replace(Za,"-$&").toLowerCase():s===null&&(s=void 0),l==="oninput"&&i[o=l]&&(o="oninputCapture"),i[o]=s}}r=="select"&&(i.multiple&&Array.isArray(i.value)&&(i.value=Ue(n.children).forEach(function(c){c.props.selected=i.value.indexOf(c.props.value)!=-1})),i.defaultValue!=null&&(i.value=Ue(n.children).forEach(function(c){c.props.selected=i.multiple?i.defaultValue.indexOf(c.props.value)!=-1:i.defaultValue==c.props.value}))),n.class&&!n.className?(i.class=n.class,Object.defineProperty(i,"className",to)):n.className&&(i.class=i.className=n.className),t.props=i})(e),e.$$typeof=Xa,ai&&ai(e)};var oi=x.__r;x.__r=function(e){oi&&oi(e),hi=e.__c};var si=x.diffed;x.diffed=function(e){si&&si(e);var t=e.props,n=e.__e;n!=null&&e.type==="textarea"&&"value"in t&&t.value!==n.value&&(n.value=t.value==null?"":t.value),hi=null};var no=Symbol.for("preact-signals");function _t(){if(ve>1)ve--;else{var e,t=!1;for((function(){var i=pt;for(pt=void 0;i!==void 0;){var a=i.S;if(a.v===i.v)for(var o=a.t;o!==void 0;o=o.x)o.i===i.i&&(o.i=a.i);i=i.o}})();He!==void 0;){var n=He;for(He=void 0,ft++;n!==void 0;){var r=n.u;if(n.u=void 0,n.f&=-3,!(8&n.f)&&fi(n))try{n.c()}catch(i){t||(e=i,t=!0)}n=r}}if(ft=0,ve--,t)throw e}}function Oe(e){if(ve>0)return e();Xt=++ro,ve++;try{return e()}finally{_t()}}var ze,I=void 0;function Ge(e){var t=I,n=ze;I=void 0,ze=void 0;try{return e()}finally{I=t,ze=n}}var He=void 0,ve=0,ft=0,ro=0,Xt=0,pt=void 0,mt=0;function vi(e){if(I!==void 0){var t=e.n;if(t===void 0||t.t!==I)return t={i:0,S:e,p:I.s,n:void 0,t:I,e:void 0,x:void 0,r:t},I.s!==void 0&&(I.s.n=t),I.s=t,e.n=t,32&I.f&&e.S(t),t;if(t.i===-1)return t.i=0,t.n!==void 0&&(t.n.p=t.p,t.p!==void 0&&(t.p.n=t.n),t.p=I.s,t.n=void 0,I.s.n=t,I.s=t),t}}function H(e,t){this.v=e,this.i=0,this.n=void 0,this.t=void 0,this.l=0,this.W=t==null?void 0:t.watched,this.Z=t==null?void 0:t.unwatched,this.name=t==null?void 0:t.name}H.prototype.brand=no;H.prototype.h=function(){return!0};H.prototype.S=function(e){var t=this,n=this.t;n!==e&&e.e===void 0&&(e.x=n,this.t=e,n!==void 0?n.e=e:Ge(function(){var r;(r=t.W)==null||r.call(t)}))};H.prototype.U=function(e){var t=this;if(this.t!==void 0){var n=e.e,r=e.x;n!==void 0&&(n.x=r,e.e=void 0),r!==void 0&&(r.e=n,e.x=void 0),e===this.t&&(this.t=r,r===void 0&&Ge(function(){var i;(i=t.Z)==null||i.call(t)}))}};H.prototype.subscribe=function(e){var t=this;return Z(function(){var n=t.value;Ge(function(){return e(n)})},{name:"sub"})};H.prototype.valueOf=function(){return this.value};H.prototype.toString=function(){return this.value+""};H.prototype.toJSON=function(){return this.value};H.prototype.peek=function(){var e=this;return Ge(function(){return e.value})};Object.defineProperty(H.prototype,"value",{get:function(){var e=vi(this);return e!==void 0&&(e.i=this.i),this.v},set:function(e){if(e!==this.v){if(ft>100)throw new Error("Cycle detected");(function(n){ve!==0&&ft===0&&n.l!==Xt&&(n.l=Xt,pt={S:n,v:n.v,i:n.i,o:pt})})(this),this.v=e,this.i++,mt++,ve++;try{for(var t=this.t;t!==void 0;t=t.x)t.t.N()}finally{_t()}}}});function C(e,t){return new H(e,t)}function fi(e){for(var t=e.s;t!==void 0;t=t.n)if(t.S.i!==t.i||!t.S.h()||t.S.i!==t.i)return!0;return!1}function pi(e){for(var t=e.s;t!==void 0;t=t.n){var n=t.S.n;if(n!==void 0&&(t.r=n),t.S.n=t,t.i=-1,t.n===void 0){e.s=t;break}}}function mi(e){for(var t=e.s,n=void 0;t!==void 0;){var r=t.p;t.i===-1?(t.S.U(t),r!==void 0&&(r.n=t.n),t.n!==void 0&&(t.n.p=r)):n=t,t.S.n=t.r,t.r!==void 0&&(t.r=void 0),t=r}e.s=n}function ke(e,t){H.call(this,void 0,t),this.x=e,this.s=void 0,this.g=mt-1,this.f=4}ke.prototype=new H;ke.prototype.h=function(){if(this.f&=-3,1&this.f)return!1;if((36&this.f)==32||(this.f&=-5,this.g===mt))return!0;if(this.g=mt,this.f|=1,this.i>0&&!fi(this))return this.f&=-2,!0;var e=I;try{pi(this),I=this;var t=this.x();(16&this.f||this.v!==t||this.i===0)&&(this.v=t,this.f&=-17,this.i++)}catch(n){this.v=n,this.f|=16,this.i++}return I=e,mi(this),this.f&=-2,!0};ke.prototype.S=function(e){if(this.t===void 0){this.f|=36;for(var t=this.s;t!==void 0;t=t.n)t.S.S(t)}H.prototype.S.call(this,e)};ke.prototype.U=function(e){if(this.t!==void 0&&(H.prototype.U.call(this,e),this.t===void 0)){this.f&=-33;for(var t=this.s;t!==void 0;t=t.n)t.S.U(t)}};ke.prototype.N=function(){if(!(2&this.f)){this.f|=6;for(var e=this.t;e!==void 0;e=e.x)e.t.N()}};Object.defineProperty(ke.prototype,"value",{get:function(){if(1&this.f)throw new Error("Cycle detected");var e=vi(this);if(this.h(),e!==void 0&&(e.i=this.i),16&this.f)throw this.v;return this.v}});function Pe(e,t){return new ke(e,t)}function _i(e){var t=e.m;if(e.m=void 0,typeof t=="function"){ve++;var n=I;I=void 0;try{t()}catch(r){throw e.f&=-2,e.f|=8,qt(e),r}finally{I=n,_t()}}}function qt(e){for(var t=e.s;t!==void 0;t=t.n)t.S.U(t);e.x=void 0,e.s=void 0,_i(e)}function io(e){if(I!==this)throw new Error("Out-of-order effect");mi(this),I=e,this.f&=-2,8&this.f&&qt(this),_t()}function Te(e,t){this.x=e,this.m=void 0,this.s=void 0,this.u=void 0,this.f=32,this.name=t==null?void 0:t.name,ze&&ze.push(this)}Te.prototype.c=function(){var e=this.S();try{if(8&this.f||this.x===void 0)return;var t=this.x();typeof t=="function"&&(this.m=t)}finally{e()}};Te.prototype.S=function(){if(1&this.f)throw new Error("Cycle detected");this.f|=1,this.f&=-9,_i(this),pi(this),ve++;var e=I;return I=this,io.bind(this,e)};Te.prototype.N=function(){2&this.f||(this.f|=2,this.u=He,He=this)};Te.prototype.d=function(){this.f|=8,1&this.f||qt(this)};Te.prototype.dispose=function(){this.d()};function Z(e,t){var n=new Te(e,t);try{n.c()}catch(i){throw n.d(),i}var r=n.d.bind(n);return r[Symbol.dispose]=r,r}var Jt,yt,gt,ao=typeof window!="undefined"&&!!window.__PREACT_SIGNALS_DEVTOOLS__,gi=[],bi=[];Z(function(){Jt=this.N})();function Ne(e,t){x[e]=t.bind(null,x[e]||function(){})}function bt(e){if(gt){var t=gt;gt=void 0,t()}gt=e&&e.S()}function yi(e){var t=this,n=e.data,r=so(n);r.name="ReactiveDom",r.value=n;var i=X(function(){for(var s=t,l=t.__v;l=l.__;)if(l.__c){l.__c.__$f|=4;break}var c=Pe(function(){var v=r.value.value;return v===0?0:v===!0?"":v||""}),h=Pe(function(){return!Array.isArray(c.value)&&!$t(c.value)}),p=Z(function(){if(this.N=wi,h.value){var v=c.value;s.__v&&s.__v.__e&&s.__v.__e.nodeType===3&&(s.__v.__e.data=v)}}),u=t.__$u.d;return t.__$u.d=function(){p(),u.call(this)},[h,c]},[]),a=i[0],o=i[1];return a.value?o.peek():o.value}yi.displayName="ReactiveTextNode";Object.defineProperties(H.prototype,{constructor:{configurable:!0,value:void 0},type:{configurable:!0,value:yi},props:{configurable:!0,get:function(){var e=this;return{data:{get value(){return e.value}}}}},__b:{configurable:!0,value:1}});Ne("__b",function(e,t){if(typeof t.type=="string"){var n,r=t.props;for(var i in r)if(i!=="children"){var a=r[i];a instanceof H&&(n||(t.__np=n={}),n[i]=a,r[i]=a.peek())}}e(t)});Ne("__r",function(e,t){if(e(t),t.type!==k){bt();var n,r=t.__c;r&&(r.__$f&=-2,(n=r.__$u)===void 0&&(r.__$u=n=(function(i,a){var o;return Z(function(){o=this},{name:a}),o.c=i,o})((function(i){return function(){var a;ao&&((a=this.y)==null||a.call(this)),i.__$f|=1,i.setState({})}})(r),typeof t.type=="function"?t.type.displayName||t.type.name:""))),yt=r,bt(n)}});Ne("__e",function(e,t,n,r){bt(),yt=void 0,e(t,n,r)});Ne("diffed",function(e,t){bt(),yt=void 0;var n;if(typeof t.type=="string"&&(n=t.__e)){var r=t.__np,i=t.props,a=n.U;if(a)for(var o in a){var s=a[o];s===void 0||r&&o in r||(s.d(),a[o]=void 0)}if(r){a||(a={},n.U=a);for(var l in r){var c=a[l],h=r[l];c===void 0?(c=oo(n,l,h,i),a[l]=c):c.o(h,i)}}}e(t)});function oo(e,t,n,r){var i=t in e&&e.ownerSVGElement===void 0,a=C(n);return{o:function(o,s){a.value=o,r=s},d:Z(function(){this.N=wi;var o=a.value.value;r[t]!==o&&(r[t]=o,i?e[t]=o:o!=null&&(o!==!1||t[4]==="-")?e.setAttribute(t,o):e.removeAttribute(t))})}}Ne("unmount",function(e,t){if(typeof t.type=="string"){var n=t.__e;if(n){var r=n.U;if(r){n.U=void 0;for(var i in r){var a=r[i];a&&a.d()}}}var o=t.__np;if(o){var s=t.props;for(var l in o)s[l]=o[l]}t.__np=void 0}else{var c=t.__c;if(c){var h=c.__$u;h&&(c.__$u=void 0,h.d())}}e(t)});Ne("__h",function(e,t,n,r){r<3&&(t.__$f|=2),e(t,n,r)});J.prototype.shouldComponentUpdate=function(e,t){if(this.__R)return!0;var n=this.__$u,r=n&&n.s!==void 0;for(var i in t)return!0;if(this.__f||typeof this.u=="boolean"&&this.u===!0){var a=2&this.__$f;if(!(r||a||4&this.__$f)||1&this.__$f)return!0}else if(!(r||4&this.__$f)||3&this.__$f)return!0;for(var o in e)if(o!=="__source"&&e[o]!==this.props[o])return!0;for(var s in this.props)if(!(s in e))return!0;return!1};function so(e,t){return X(function(){return C(e,t)},[])}function Zt(e,t){var n=_e(e);return n.current=e,yt.__$f|=4,X(function(){return Pe(function(){return n.current()},t)},[])}var lo=typeof requestAnimationFrame=="undefined"?setTimeout:function(e){var t=function(){clearTimeout(n),cancelAnimationFrame(r),e()},n=setTimeout(t,35),r=requestAnimationFrame(t)},co=function(e){queueMicrotask(function(){queueMicrotask(e)})};function uo(){Oe(function(){for(var e;e=gi.shift();)Jt.call(e)})}function ho(){gi.push(this)===1&&(x.requestAnimationFrame||lo)(uo)}function vo(){Oe(function(){for(var e;e=bi.shift();)Jt.call(e)})}function wi(){bi.push(this)===1&&(x.requestAnimationFrame||co)(vo)}function re(e,t){var n=_e(e);n.current=e,M(function(){return Z(function(){return this.N=ho,n.current()},t)},[])}var pe=Ve({}),Ae={DATE_STRING:/^\d{4}-\d{2}-\d{2}$/,DATE_TIME_STRING:/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/},en=class extends Error{constructor(t){super(`Invalid date time specification: ${t}`)}},S=e=>{if(!Ae.DATE_TIME_STRING.test(e)&&!Ae.DATE_STRING.test(e))throw new en(e);return new Date(Number(e.slice(0,4)),Number(e.slice(5,7))-1,Number(e.slice(8,10)),Number(e.slice(11,13)),Number(e.slice(14,16)))},Q=e=>{let t=e.slice(11,13),n=e.slice(14,16);return{year:Number(e.slice(0,4)),month:Number(e.slice(5,7))-1,date:Number(e.slice(8,10)),hours:t!==""?Number(t):void 0,minutes:n!==""?Number(n):void 0}},Ei=(e,t)=>e.toLocaleString(t,{month:"long"}),Ci=(e,t)=>e.toLocaleString(t,{month:"numeric",day:"numeric",year:"numeric"}),fo=(e,t)=>e.map(n=>n.toLocaleString(t,{weekday:"short"}).charAt(0)),En=(e,t)=>t==="he-IL"?e.toLocaleString(t,{weekday:"narrow"}):e.toLocaleString(t,{weekday:"short"}),po=(e,t)=>e.map(n=>En(n,t)),Mi=(e,t)=>["zh-cn","zh-tw","ca-es","he-il"].includes(t.toLowerCase())?po(e,t):fo(e,t),mo="data:image/svg+xml,%3c%3fxml version='1.0' encoding='utf-8'%3f%3e%3c!-- Uploaded to: SVG Repo%2c www.svgrepo.com%2c Generator: SVG Repo Mixer Tools --%3e%3csvg width='800px' height='800px' viewBox='0 0 24 24' fill='none' xmlns='http://www.w3.org/2000/svg'%3e%3cpath d='M6 9L12 15L18 9' stroke='%23DED8E1' stroke-width='4' stroke-linecap='round' stroke-linejoin='round'/%3e%3c/svg%3e",V=()=>"s"+Math.random().toString(36).substring(2,11),kt=e=>e.key==="Enter"||e.key===" ";function _o(){let e=V(),t=V(),n=V(),r=N(pe),i=u=>u===""?r.translate("MM/DD/YYYY"):Ci(S(u),r.config.locale.value);M(()=>{r.datePickerState.inputDisplayedValue.value=i(r.datePickerState.selectedDate.value)},[r.datePickerState.selectedDate.value,r.config.locale.value]);let[a,o]=P([]),s=()=>{let u=document.getElementById(n);r.datePickerState.inputWrapperElement.value=u instanceof HTMLDivElement?u:void 0};M(()=>{r.config.teleportTo&&s();let u=["sx__date-input-wrapper"];r.datePickerState.isOpen.value&&u.push("sx__date-input--active"),o(u)},[r.datePickerState.isOpen.value]);let l=u=>{u.key==="Enter"&&c(u)},c=u=>{u.stopPropagation();try{r.datePickerState.inputDisplayedValue.value=u.target.value,r.datePickerState.close()}catch(v){console.log("Error setting input value:"+v)}};M(()=>{let u=document.getElementById(e);if(u!==null)return u.addEventListener("change",c),()=>u.removeEventListener("change",c)});let h=u=>{c(u),r.datePickerState.open()},p=u=>{kt(u)&&(u.preventDefault(),r.datePickerState.open(),setTimeout(()=>{let v=document.querySelector('[data-focus="true"]');v instanceof HTMLElement&&v.focus()},50))};return d(k,{children:d("div",{className:a.join(" "),id:n,children:[d("label",{for:e,id:t,className:"sx__date-input-label",children:r.config.label||r.translate("Date")}),d("input",{id:e,tabIndex:r.datePickerState.isDisabled.value?-1:0,name:r.config.name||"date","aria-describedby":t,value:r.datePickerState.inputDisplayedValue.value,"data-testid":"date-picker-input",className:"sx__date-input",onClick:h,onKeyUp:l,type:"text"}),d("button",{type:"button",tabIndex:r.datePickerState.isDisabled.value?-1:0,"aria-label":r.translate("Choose Date"),onKeyDown:p,onClick:()=>r.datePickerState.open(),className:"sx__date-input-chevron-wrapper",children:d("img",{className:"sx__date-input-chevron",src:mo,alt:""})})]})})}var Ee;(function(e){e.MONTH_DAYS="month-days",e.YEARS="years"})(Ee||(Ee={}));var go="years-view",bo="months-view",yo="date-picker-week",tn=class extends Error{constructor(t,n){super(`Number must be between ${t} and ${n}.`),Object.defineProperty(this,"min",{enumerable:!0,configurable:!0,writable:!0,value:t}),Object.defineProperty(this,"max",{enumerable:!0,configurable:!0,writable:!0,value:n})}},B=e=>{if(e<0||e>99)throw new tn(0,99);return String(e).padStart(2,"0")},F=e=>`${e.getFullYear()}-${B(e.getMonth()+1)}-${B(e.getDate())}`,wo=e=>`${B(e.getHours())}:${B(e.getMinutes())}`,Mt=e=>`${F(e)} ${wo(e)}`,Ze=(e,t)=>{let{year:n,month:r,date:i,hours:a,minutes:o}=Q(e),s=a!==void 0&&o!==void 0,l=new Date(n,r,i,a!=null?a:0,o!=null?o:0),c=(l.getMonth()+t)%12;return c<0&&(c+=12),l.setMonth(l.getMonth()+t),l.getMonth()>c?l.setDate(0):l.getMonth()<c&&(l.setMonth(l.getMonth()+1),l.setDate(0)),s?Mt(l):F(l)},se=(e,t)=>{let{year:n,month:r,date:i,hours:a,minutes:o}=Q(e),s=a!==void 0&&o!==void 0,l=new Date(n,r,i,a!=null?a:0,o!=null?o:0);return l.setDate(l.getDate()+t),s?Mt(l):F(l)},O=e=>e.slice(0,10),Ce=e=>e.slice(11),Oi=(e,t)=>(e=e.slice(0,8)+B(t)+e.slice(10),e),xo=e=>(e=Ze(e,-1),Oi(e,1)),Do=e=>(e=Ze(e,1),Oi(e,1)),Qt=(e,t)=>`${F(S(e))} ${t}`;function Pt({direction:e,onClick:t,buttonText:n,disabled:r=!1}){return d("button",{type:"button",disabled:r,className:"sx__chevron-wrapper sx__ripple",onMouseUp:t,onKeyDown:a=>{kt(a)&&t()},tabIndex:0,children:d("i",{className:`sx__chevron sx__chevron--${e}`,children:n})})}function ko({setYearsView:e}){let t=N(pe),n=p=>{let u=S(p);return Ei(u,t.config.locale.value)},r=p=>Q(p).year,[i,a]=P(n(t.datePickerState.datePickerDate.value)),[o,s]=P(r(t.datePickerState.datePickerDate.value)),l=()=>{t.datePickerState.datePickerDate.value=xo(t.datePickerState.datePickerDate.value)},c=()=>{t.datePickerState.datePickerDate.value=Do(t.datePickerState.datePickerDate.value)};M(()=>{a(n(t.datePickerState.datePickerDate.value)),s(r(t.datePickerState.datePickerDate.value))},[t.datePickerState.datePickerDate.value]);let h=p=>{p.stopPropagation(),e()};return d(k,{children:d("header",{className:"sx__date-picker__month-view-header",children:[d(Pt,{direction:"previous",onClick:()=>l(),buttonText:t.translate("Previous month")}),d("button",{type:"button",className:"sx__date-picker__month-view-header__month-year",onClick:p=>h(p),children:i+" "+o}),d(Pt,{direction:"next",onClick:()=>c(),buttonText:t.translate("Next month")})]})})}function Po(){let e=N(pe),t=e.timeUnitsImpl.getWeekFor(S(e.datePickerState.datePickerDate.value)),n=Mi(t,e.config.locale.value);return d("div",{className:"sx__date-picker__day-names",children:n.map(r=>d("span",{"data-testid":"day-name",className:"sx__date-picker__day-name",children:r}))})}var Cn=e=>{let t=new Date;return e.getDate()===t.getDate()&&e.getMonth()===t.getMonth()&&e.getFullYear()===t.getFullYear()},So=(e,t)=>e.getMonth()===t.getMonth()&&e.getFullYear()===t.getFullYear();function Ti({strokeColor:e}){return d(k,{children:d("svg",{className:"sx__event-icon",viewBox:"0 0 24 24",fill:"none",xmlns:"http://www.w3.org/2000/svg",children:[d("g",{id:"SVGRepo_bgCarrier","stroke-width":"0"}),d("g",{id:"SVGRepo_tracerCarrier","stroke-linecap":"round","stroke-linejoin":"round"}),d("g",{id:"SVGRepo_iconCarrier",children:[d("path",{d:"M12 8V12L15 15",stroke:e,"stroke-width":"2","stroke-linecap":"round"}),d("circle",{cx:"12",cy:"12",r:"9",stroke:e,"stroke-width":"2"})]})]})})}function Eo({strokeColor:e}){return d(k,{children:d("svg",{className:"sx__event-icon",viewBox:"0 0 24 24",fill:"none",xmlns:"http://www.w3.org/2000/svg",children:[d("g",{id:"SVGRepo_bgCarrier","stroke-width":"0"}),d("g",{id:"SVGRepo_tracerCarrier","stroke-linecap":"round","stroke-linejoin":"round"}),d("g",{id:"SVGRepo_iconCarrier",children:[d("path",{d:"M15 7C15 8.65685 13.6569 10 12 10C10.3431 10 9 8.65685 9 7C9 5.34315 10.3431 4 12 4C13.6569 4 15 5.34315 15 7Z",stroke:e,"stroke-width":"2"}),d("path",{d:"M5 19.5C5 15.9101 7.91015 13 11.5 13H12.5C16.0899 13 19 15.9101 19 19.5V20C19 20.5523 18.5523 21 18 21H6C5.44772 21 5 20.5523 5 20V19.5Z",stroke:e,"stroke-width":"2"})]})]})})}function Co({strokeColor:e}){return d(k,{children:d("svg",{className:"sx__event-icon",viewBox:"0 0 24 24",fill:"none",xmlns:"http://www.w3.org/2000/svg",children:[d("g",{id:"SVGRepo_bgCarrier","stroke-width":"0"}),d("g",{id:"SVGRepo_tracerCarrier","stroke-linecap":"round","stroke-linejoin":"round"}),d("g",{id:"SVGRepo_iconCarrier",children:[d("g",{"clip-path":"url(#clip0_429_11046)",children:[d("rect",{x:"12",y:"11",width:"0.01",height:"0.01",stroke:e,"stroke-width":"2","stroke-linejoin":"round"}),d("path",{d:"M12 22L17.5 16.5C20.5376 13.4624 20.5376 8.53757 17.5 5.5C14.4624 2.46244 9.53757 2.46244 6.5 5.5C3.46244 8.53757 3.46244 13.4624 6.5 16.5L12 22Z",stroke:e,"stroke-width":"2","stroke-linejoin":"round"})]}),d("defs",{children:d("clipPath",{id:"clip0_429_11046",children:d("rect",{width:"24",height:"24",fill:"white"})})})]})]})})}var Mo=/^(0[0-9]|1[0-9]|2[0-3]):[0-5][0-9]$/,ae=/^(\d{4})-(\d{2})-(\d{2}) (0[0-9]|1[0-9]|2[0-3]):[0-5][0-9]$/,K=/^(\d{4})-(\d{2})-(\d{2})$/,nn=class extends Error{constructor(t){super(`Invalid time string: ${t}`)}},Mn=1.6666666666666667,oe=e=>{if(!Mo.test(e)&&e!=="24:00")throw new nn(e);let[t,n]=e.split(":").map(i=>parseInt(i,10)),r=(n*Mn).toString();return r.split(".")[0].length<2&&(r=`0${r}`),Number(t+r)},be=e=>{let t=Math.floor(e/100),n=Math.round(e%100/Mn);return`${B(t)}:${B(n)}`},Ni=(e,t)=>{let n=t/Mn,r=S(e);return r.setMinutes(r.getMinutes()+n),Mt(r)},rn;(function(e){e[e.SUNDAY=0]="SUNDAY",e[e.MONDAY=1]="MONDAY",e[e.TUESDAY=2]="TUESDAY",e[e.WEDNESDAY=3]="WEDNESDAY",e[e.THURSDAY=4]="THURSDAY",e[e.FRIDAY=5]="FRIDAY",e[e.SATURDAY=6]="SATURDAY"})(rn||(rn={}));var St="en-US",On=rn.MONDAY,Oo="primary",an=class{constructor(t,n,r,i,a,o,s,l,c,h=void 0,p={},u={}){Object.defineProperty(this,"_config",{enumerable:!0,configurable:!0,writable:!0,value:t}),Object.defineProperty(this,"id",{enumerable:!0,configurable:!0,writable:!0,value:n}),Object.defineProperty(this,"start",{enumerable:!0,configurable:!0,writable:!0,value:r}),Object.defineProperty(this,"end",{enumerable:!0,configurable:!0,writable:!0,value:i}),Object.defineProperty(this,"title",{enumerable:!0,configurable:!0,writable:!0,value:a}),Object.defineProperty(this,"people",{enumerable:!0,configurable:!0,writable:!0,value:o}),Object.defineProperty(this,"location",{enumerable:!0,configurable:!0,writable:!0,value:s}),Object.defineProperty(this,"description",{enumerable:!0,configurable:!0,writable:!0,value:l}),Object.defineProperty(this,"calendarId",{enumerable:!0,configurable:!0,writable:!0,value:c}),Object.defineProperty(this,"_options",{enumerable:!0,configurable:!0,writable:!0,value:h}),Object.defineProperty(this,"_customContent",{enumerable:!0,configurable:!0,writable:!0,value:p}),Object.defineProperty(this,"_foreignProperties",{enumerable:!0,configurable:!0,writable:!0,value:u}),Object.defineProperty(this,"_previousConcurrentEvents",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"_totalConcurrentEvents",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"_maxConcurrentEvents",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"_nDaysInGrid",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"_createdAt",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"_eventFragments",{enumerable:!0,configurable:!0,writable:!0,value:{}})}get _isSingleDayTimed(){return ae.test(this.start)&&ae.test(this.end)&&O(this.start)===O(this.end)}get _isSingleDayFullDay(){return K.test(this.start)&&K.test(this.end)&&this.start===this.end}get _isMultiDayTimed(){return ae.test(this.start)&&ae.test(this.end)&&O(this.start)!==O(this.end)}get _isMultiDayFullDay(){return K.test(this.start)&&K.test(this.end)&&this.start!==this.end}get _isSingleHybridDayTimed(){if(!this._config.isHybridDay||!ae.test(this.start)||!ae.test(this.end))return!1;let t=O(this.start),n=O(this.end),r=F(new Date(S(n).getTime()-864e5));if(t!==n&&t!==r)return!1;let i=this._config.dayBoundaries.value,a=oe(Ce(this.start)),o=oe(Ce(this.end));return a>=i.start&&(o<=i.end||o>a)||a<i.end&&o<=i.end}get _color(){return this.calendarId&&this._config.calendars.value&&this.calendarId in this._config.calendars.value?this._config.calendars.value[this.calendarId].colorName:Oo}_getForeignProperties(){return this._foreignProperties}_getExternalEvent(){return{id:this.id,start:this.start,end:this.end,title:this.title,people:this.people,location:this.location,description:this.description,calendarId:this.calendarId,_options:this._options,...this._getForeignProperties()}}},Et=class{constructor(t,n,r,i){Object.defineProperty(this,"_config",{enumerable:!0,configurable:!0,writable:!0,value:t}),Object.defineProperty(this,"id",{enumerable:!0,configurable:!0,writable:!0,value:n}),Object.defineProperty(this,"start",{enumerable:!0,configurable:!0,writable:!0,value:r}),Object.defineProperty(this,"end",{enumerable:!0,configurable:!0,writable:!0,value:i}),Object.defineProperty(this,"people",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"location",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"description",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"title",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"calendarId",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"_foreignProperties",{enumerable:!0,configurable:!0,writable:!0,value:{}}),Object.defineProperty(this,"_options",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"_customContent",{enumerable:!0,configurable:!0,writable:!0,value:{}})}build(){return new an(this._config,this.id,this.start,this.end,this.title,this.people,this.location,this.description,this.calendarId,this._options,this._customContent,this._foreignProperties)}withTitle(t){return this.title=t,this}withPeople(t){return this.people=t,this}withLocation(t){return this.location=t,this}withDescription(t){return this.description=t,this}withForeignProperties(t){return this._foreignProperties=t,this}withCalendarId(t){return this.calendarId=t,this}withOptions(t){return this._options=t,this}withCustomContent(t){return this._customContent=t,this}},Be=(e,t)=>{let n=new Et(t.config,e.id,e.start,e.end).withTitle(e.title).withPeople(e.people).withCalendarId(e.calendarId).withForeignProperties(JSON.parse(JSON.stringify(e._getForeignProperties()))).withLocation(e.location).withDescription(e.description).withOptions(e._options).withCustomContent(e._customContent).build();return n._nDaysInGrid=e._nDaysInGrid,n},To=e=>e.reduce((t,n,r)=>r===0?n:r===e.length-1?`${t} & ${n}`:`${t}, ${n}`,""),ge=(e,t)=>{let{year:n,month:r,date:i}=Q(e);return new Date(n,r,i).toLocaleDateString(t,{day:"numeric",month:"long",year:"numeric"})},ye=ge,Se=(e,t)=>{let{year:n,month:r,date:i,hours:a,minutes:o}=Q(e);return new Date(n,r,i,a,o).toLocaleTimeString(t,{hour:"numeric",minute:"numeric"})},Ai=(e,t,n="\u2013")=>{let r={start:e.start,end:e.end};return e._isSingleDayFullDay?ge(r.start,t):e._isMultiDayFullDay?`${ge(r.start,t)} ${n} ${ge(r.end,t)}`:e._isSingleDayTimed&&r.start!==r.end?`${ge(r.start,t)} <span aria-hidden="true">\u22C5</span> ${Se(r.start,t)} ${n} ${Se(r.end,t)}`:e._isSingleDayTimed&&e.start===e.end?`${ge(r.start,t)}, ${Se(r.start,t)}`:`${ge(r.start,t)}, ${Se(r.start,t)} ${n} ${ge(r.end,t)}, ${Se(r.end,t)}`};function No({week:e}){let t=N(pe),n=e.map(s=>{let l=["sx__date-picker__day"];return Cn(s)&&l.push("sx__date-picker__day--today"),F(s)===t.datePickerState.selectedDate.value&&l.push("sx__date-picker__day--selected"),So(s,S(t.datePickerState.datePickerDate.value))||l.push("is-leading-or-trailing"),{day:s,classes:l}}),r=s=>{let l=F(s);return l>=t.config.min&&l<=t.config.max},i=s=>{t.datePickerState.selectedDate.value=F(s),t.datePickerState.close()},a=s=>F(s.day)===t.datePickerState.datePickerDate.value,o=s=>{if(s.key==="Enter"){t.datePickerState.selectedDate.value=t.datePickerState.datePickerDate.value,t.datePickerState.close();return}let l=new Map([["ArrowDown",7],["ArrowUp",-7],["ArrowLeft",-1],["ArrowRight",1]]);t.datePickerState.datePickerDate.value=se(t.datePickerState.datePickerDate.value,l.get(s.key)||0)};return d(k,{children:d("div",{"data-testid":yo,className:"sx__date-picker__week",children:n.map(s=>d("button",{type:"button",tabIndex:a(s)?0:-1,disabled:!r(s.day),"aria-label":ye(t.datePickerState.datePickerDate.value,t.config.locale.value),className:s.classes.join(" "),"data-focus":a(s)?"true":void 0,onClick:()=>i(s.day),onKeyDown:o,children:s.day.getDate()}))})})}function Ao({seatYearsView:e}){let t=V(),n=N(pe),[r,i]=P([]),a=()=>{let o=S(n.datePickerState.datePickerDate.value);i(n.timeUnitsImpl.getMonthWithTrailingAndLeadingDays(o.getFullYear(),o.getMonth()))};return M(()=>{a()},[n.datePickerState.datePickerDate.value]),M(()=>{let o=new MutationObserver(l=>{l.forEach(c=>{let h=c.target;h.dataset.focus==="true"&&h.focus()})}),s=document.getElementById(t);return o.observe(s,{childList:!0,subtree:!0,attributes:!0}),()=>o.disconnect()},[]),d(k,{children:d("div",{id:t,"data-testid":bo,className:"sx__date-picker__month-view",children:[d(ko,{setYearsView:e}),d(Po,{}),r.map(o=>d(No,{week:o}))]})})}function Yo({year:e,setYearAndMonth:t,isExpanded:n,expand:r}){let i=N(pe),a=i.timeUnitsImpl.getMonthsFor(e),o=(s,l)=>{s.stopPropagation(),t(e,l.getMonth())};return d(k,{children:d("li",{className:n?"sx__is-expanded":"",children:[d("button",{type:"button",className:"sx__date-picker__years-accordion__expand-button sx__ripple--wide",onClick:()=>r(e),children:e}),n&&d("div",{className:"sx__date-picker__years-view-accordion__panel",children:a.map(s=>d("button",{type:"button",className:"sx__date-picker__years-view-accordion__month",onClick:l=>o(l,s),children:Ei(s,i.config.locale.value)}))})]})})}function jo({setMonthView:e}){let t=N(pe),n=S(t.config.min).getFullYear(),r=S(t.config.max).getFullYear(),i=Array.from({length:r-n+1},(c,h)=>n+h),{year:a}=Q(t.datePickerState.selectedDate.value),[o,s]=P(a),l=(c,h)=>{t.datePickerState.datePickerDate.value=F(new Date(c,h,1)),e()};return M(()=>{var c;let h=(c=document.querySelector(".sx__date-picker__years-view"))===null||c===void 0?void 0:c.querySelector(".sx__is-expanded");h&&h.scrollIntoView({block:"center"})},[]),d(k,{children:d("ul",{className:"sx__date-picker__years-view","data-testid":go,children:i.map(c=>d(Yo,{year:c,setYearAndMonth:(h,p)=>l(h,p),isExpanded:o===c,expand:h=>s(h)}))})})}var Lo=e=>{if(e){let t=e.scrollHeight>e.clientHeight,r=window.getComputedStyle(e).overflowY.indexOf("hidden")!==-1;return t&&!r}return!0},Yi=(e,t=[])=>!e||e===document.body||e.nodeType===Node.DOCUMENT_FRAGMENT_NODE?(t.push(window),t):(Lo(e)&&t.push(e),Yi(e.assignedSlot?e.assignedSlot.parentNode:e.parentNode,t)),xi="sx__date-picker-popup";function Io(){let e=N(pe),[t,n]=P(Ee.MONTH_DAYS),r=X(()=>{let u=[xi,e.datePickerState.isDark.value?"is-dark":"",e.config.teleportTo?"is-teleported":""];return e.config.placement&&!e.config.teleportTo&&u.push(e.config.placement),u},[e.datePickerState.isDark.value,e.config.placement,e.config.teleportTo]),i=u=>{u.target.closest(`.${xi}`)||e.datePickerState.close()},a=u=>{u.key==="Escape"&&(e.config.listeners.onEscapeKeyDown?e.config.listeners.onEscapeKeyDown(e):e.datePickerState.close())};M(()=>(document.addEventListener("click",i),document.addEventListener("keydown",a),()=>{document.removeEventListener("click",i),document.removeEventListener("keydown",a)}),[]);let o=Number(getComputedStyle(document.documentElement).fontSize.split("px")[0]),s=362,l=332,c=()=>{let u=e.datePickerState.inputWrapperElement.value,v=u==null?void 0:u.getBoundingClientRect();if(!(u===void 0||!(v instanceof DOMRect)))return{top:e.config.placement.includes("bottom")?v.height+v.y+1:v.y-o-s,left:e.config.placement.includes("start")?v.x:v.x+v.width-l,width:l,position:"fixed"}},[h,p]=P(c());return M(()=>{let u=e.datePickerState.inputWrapperElement.value;if(u===void 0)return;let v=Yi(u),m=()=>p(c());return v.forEach(f=>f.addEventListener("scroll",m)),()=>v.forEach(f=>f.removeEventListener("scroll",m))},[]),d(k,{children:d("div",{style:e.config.teleportTo?h:void 0,"data-testid":"date-picker-popup",className:r.join(" "),children:t===Ee.MONTH_DAYS?d(Ao,{seatYearsView:()=>n(Ee.YEARS)}):d(jo,{setMonthView:()=>n(Ee.MONTH_DAYS)})})})}function Ro({$app:e}){let t=["sx__date-picker-wrapper"],[n,r]=P(t);M(()=>{var a;let o=[...t];e.datePickerState.isDark.value&&o.push("is-dark"),!((a=e.config.style)===null||a===void 0)&&a.fullWidth&&o.push("has-full-width"),e.datePickerState.isDisabled.value&&o.push("is-disabled"),r(o)},[e.datePickerState.isDark.value,e.datePickerState.isDisabled.value]);let i=d(Io,{});return e.config.teleportTo&&(i=di(i,e.config.teleportTo)),d(k,{children:d("div",{className:n.join(" "),children:d(pe.Provider,{value:e,children:[d(_o,{}),e.datePickerState.isOpen.value&&i]})})})}var j=Ve({}),on=class{constructor(t,n,r,i){Object.defineProperty(this,"datePickerState",{enumerable:!0,configurable:!0,writable:!0,value:t}),Object.defineProperty(this,"config",{enumerable:!0,configurable:!0,writable:!0,value:n}),Object.defineProperty(this,"timeUnitsImpl",{enumerable:!0,configurable:!0,writable:!0,value:r}),Object.defineProperty(this,"translate",{enumerable:!0,configurable:!0,writable:!0,value:i})}},sn=class{constructor(){Object.defineProperty(this,"datePickerState",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"config",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"timeUnitsImpl",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"translate",{enumerable:!0,configurable:!0,writable:!0,value:void 0})}build(){return new on(this.datePickerState,this.config,this.timeUnitsImpl,this.translate)}withDatePickerState(t){return this.datePickerState=t,this}withConfig(t){return this.config=t,this}withTimeUnitsImpl(t){return this.timeUnitsImpl=t,this}withTranslate(t){return this.translate=t,this}},G;(function(e){e.Day="day",e.Week="week",e.MonthGrid="month-grid",e.MonthAgenda="month-agenda",e.List="list"})(G||(G={}));var ln=e=>[e.config.locale.value,{month:"long"}],cn=e=>[e.config.locale.value,{year:"numeric"}],Fo=(e,t,n)=>{let r=S(t).toLocaleString(...ln(e)),i=S(t).toLocaleString(...cn(e)),a=S(n).toLocaleString(...ln(e)),o=S(n).toLocaleString(...cn(e));return r===a&&i===o?`${r} ${i}`:r!==a&&i===o?`${r} \u2013 ${a} ${i}`:`${r} ${i} \u2013 ${a} ${o}`},Wo=e=>{let t=S(e.datePickerState.selectedDate.value).toLocaleString(...ln(e)),n=S(e.datePickerState.selectedDate.value).toLocaleString(...cn(e));return`${t} ${n}`};function $o(){let e=N(j),[t,n]=P("");return re(()=>{e.calendarState.view.value===G.Week&&n(Fo(e,e.calendarState.range.value.start,e.calendarState.range.value.end)),(e.calendarState.view.value===G.MonthGrid||e.calendarState.view.value===G.Day||e.calendarState.view.value===G.MonthAgenda)&&n(Wo(e))}),d("span",{className:"sx__range-heading",children:t})}function Uo(){let e=N(j);return d("button",{type:"button",className:"sx__today-button sx__ripple",onClick:()=>{e.datePickerState.selectedDate.value=F(new Date)},children:e.translate("Today")})}function Vo(){let e=N(j),[t,n]=P([]);re(()=>{e.calendarState.isCalendarSmall.value?n(e.config.views.value.filter(f=>f.hasSmallScreenCompat)):n(e.config.views.value.filter(f=>f.hasWideScreenCompat))});let[r,i]=P("");re(()=>{let f=e.config.views.value.find(_=>_.name===e.calendarState.view.value);f&&i(e.translate(f.label))});let[a,o]=P(!1),s=f=>{let _=f.target;_ instanceof HTMLElement&&!_.closest(".sx__view-selection")&&o(!1)};M(()=>(document.addEventListener("click",s),()=>document.removeEventListener("click",s)),[]);let l=f=>{o(!1),e.calendarState.setView(f,e.datePickerState.selectedDate.value)},[c,h]=P(),[p,u]=P(0),v=f=>{kt(f)&&o(!a),setTimeout(()=>{var _;let g=(_=e.elements.calendarWrapper)===null||_===void 0?void 0:_.querySelectorAll(".sx__view-selection-item");if(!g)return;h(g);let b=g[0];b instanceof HTMLElement&&(u(0),b.focus())},50)},m=(f,_)=>{if(c)if(f.key==="ArrowDown"){let g=c[p+1];g instanceof HTMLElement&&(u(p+1),g.focus())}else if(f.key==="ArrowUp"){let g=c[p-1];g instanceof HTMLElement&&(u(p-1),g.focus())}else kt(f)&&l(_)};return d("div",{className:"sx__view-selection",children:[d("div",{tabIndex:0,role:"button","aria-label":e.translate("Select View"),className:"sx__view-selection-selected-item sx__ripple",onClick:()=>o(!a),onKeyDown:v,children:r}),a&&d("ul",{"data-testid":"view-selection-items",className:"sx__view-selection-items",children:t.map(f=>d("li",{"aria-label":e.translate("Select View")+" "+e.translate(f.label),tabIndex:-1,role:"button",onKeyDown:_=>m(_,f.name),onClick:()=>l(f.name),className:"sx__view-selection-item"+(f.name===e.calendarState.view.value?" is-selected":""),children:e.translate(f.label)}))})]})}function zo(){let e=N(j),t=l=>{let c=e.config.views.value.find(h=>h.name===e.calendarState.view.value);c&&(e.datePickerState.selectedDate.value=c.backwardForwardFn(e.datePickerState.selectedDate.value,l==="forwards"?c.backwardForwardUnits:-c.backwardForwardUnits))},[n,r]=P("");re(()=>{r(`${ye(e.calendarState.range.value.start,e.config.locale.value)} ${e.translate("to")} ${ye(e.calendarState.range.value.end,e.config.locale.value)}`)});let[i,a]=P(""),[o,s]=P("");return M(()=>{let l=e.config.views.value.find(c=>c.name===e.calendarState.view.value);l&&(a(l.setDateRange({range:e.calendarState.range,calendarConfig:e.config,timeUnitsImpl:e.timeUnitsImpl,date:l.backwardForwardFn(e.datePickerState.selectedDate.value,-l.backwardForwardUnits)}).end),s(l.setDateRange({range:e.calendarState.range,calendarConfig:e.config,timeUnitsImpl:e.timeUnitsImpl,date:l.backwardForwardFn(e.datePickerState.selectedDate.value,l.backwardForwardUnits)}).start))},[e.datePickerState.selectedDate.value,e.calendarState.view.value]),d(k,{children:d("div",{className:"sx__forward-backward-navigation","aria-label":n,"aria-live":"polite",children:[d(Pt,{disabled:!!(e.config.minDate.value&&O(i)<e.config.minDate.value),onClick:()=>t("backwards"),direction:"previous",buttonText:e.translate("Previous period")}),d(Pt,{disabled:!!(e.config.maxDate.value&&O(o)>e.config.maxDate.value),onClick:()=>t("forwards"),direction:"next",buttonText:e.translate("Next period")})]})})}var fe=e=>document.querySelector(`[data-ccid="${e}"]`),un=class{constructor(t){Object.defineProperty(this,"randomId",{enumerable:!0,configurable:!0,writable:!0,value:V()}),Object.defineProperty(this,"name",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"label",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"Component",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"setDateRange",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"hasSmallScreenCompat",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"hasWideScreenCompat",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"backwardForwardFn",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"backwardForwardUnits",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),this.name=t.name,this.label=t.label,this.Component=t.Component,this.setDateRange=t.setDateRange,this.hasSmallScreenCompat=t.hasSmallScreenCompat,this.hasWideScreenCompat=t.hasWideScreenCompat,this.backwardForwardFn=t.backwardForwardFn,this.backwardForwardUnits=t.backwardForwardUnits}render(t,n){De(ie(this.Component,{$app:n,id:this.randomId}),t)}destroy(){let t=document.getElementById(this.randomId);t&&t.remove()}},ce=e=>new un(e),Ke=(e,t,n)=>{if(n<t.start){let r=2400-t.start;return(n+r)/e*100}return(n-t.start)/e*100},ji=(e,t,n,r)=>e===t?Ke(r,n,oe(Ce(Ni(t,50))))-Ke(r,n,oe(Ce(e))):Ke(r,n,oe(Ce(t)))-Ke(r,n,oe(Ce(e))),Ho=(e,t)=>!e._totalConcurrentEvents||!e._previousConcurrentEvents?0:(e._previousConcurrentEvents||0)/(e._totalConcurrentEvents||0)*t,Go=(e,t,n,r)=>r||!n?t-e:t/n,Ko=e=>e._previousConcurrentEvents?"1px solid #fff":0,Li=e=>"time-grid-event-copy-"+e,Qe=e=>"touches"in e&&typeof e.touches=="object";function et(e){let[t,n]=P(),r=c=>{if(!c)return n(void 0);n(Be(c,e))},[i,a]=P(),o=(c,h)=>{a(setTimeout(()=>c(h),150))},s=(c,h)=>{if(Qe(c)&&c.touches.length===0||!e.config.plugins.eventModal)return;let p=c.target;if(!(p instanceof HTMLElement))return;let u=p.classList.contains("sx__event")?p:p.closest(".sx__event");u instanceof HTMLElement&&(e.config.plugins.eventModal.calendarEventElement.value=u,e.config.plugins.eventModal.setCalendarEvent(h,u.getBoundingClientRect()))};return{eventCopy:t,updateCopy:r,createDragStartTimeout:o,setClickedEventIfNotDragging:(c,h)=>{i&&(clearTimeout(i),s(h,c)),a(void 0)},setClickedEvent:s}}var Bo=(e,t)=>{let n=e?"custom-time-grid-event-"+V():void 0;return n&&t&&(n+="-copy"),n},le=(e,t,n)=>{e.config.callbacks.onEventClick&&e.config.callbacks.onEventClick(t._getExternalEvent(),n)},tt=(e,t,n)=>{e.config.callbacks.onDoubleClickEvent&&e.config.callbacks.onDoubleClickEvent(t._getExternalEvent(),n)},Ii=e=>{let t=Qe(e)?e.touches[0]:e;return{clientX:t.clientX,clientY:t.clientY}},Ri=(e,t,n)=>Ke(n,t,oe(Ce(e))),Ye=e=>{setTimeout(()=>{e()})},nt=e=>{let t=e.elements.calendarWrapper;if(!(t instanceof HTMLElement))return;let n=t.querySelector(".sx__event-modal");n instanceof HTMLElement&&setTimeout(()=>{n.focus()},100)},Ot=e=>e._createdAt&&Date.now()-e._createdAt.getTime()<1e3;function Fi({calendarEvent:e,dayBoundariesDateTime:t,isCopy:n,setMouseDown:r}){var i,a,o,s;let l=N(j),{eventCopy:c,updateCopy:h,createDragStartTimeout:p,setClickedEventIfNotDragging:u,setClickedEvent:v}=et(l),m=[l.config.locale.value,{hour:"numeric",minute:"numeric"}],f=(Y,de)=>{let Fe=S(Y).toLocaleTimeString(...m);if(Y===de)return Fe;let Aa=S(de).toLocaleTimeString(...m);return`${Fe} \u2013 ${Aa}`},_={borderInlineStart:`4px solid var(--sx-color-${e._color})`,textColor:`var(--sx-color-on-${e._color}-container)`,backgroundColor:`var(--sx-color-${e._color}-container)`,iconStroke:`var(--sx-color-on-${e._color}-container)`},g=Ho(e,l.config.weekOptions.value.eventWidth),b=Y=>{var de;if(Qe(Y)&&Y.preventDefault(),n||!Y.target||!l.config.plugins.dragAndDrop||!((de=e._options)===null||de===void 0)&&de.disableDND||Dr)return;let Fe=Be(e,l);h(Fe),l.config.plugins.dragAndDrop.createTimeGridDragHandler({$app:l,eventCoordinates:Ii(Y),updateCopy:h,eventCopy:Fe},t)},D=l.config._customComponentFns.timeGridEvent,E=Bo(D,n);M(()=>{D&&D(fe(E),{calendarEvent:e._getExternalEvent()})},[e,c]);let L=Y=>{Y.stopPropagation(),le(l,e,Y)},A=Y=>{Y.stopPropagation(),tt(l,e,Y)},ne=Y=>{(Y.key==="Enter"||Y.key===" ")&&(Y.stopPropagation(),v(Y,e),le(l,e,Y),Ye(()=>{nt(l)}))},$=Y=>{if(r(!0),Y.stopPropagation(),!n&&l.config.plugins.resize){let de=Be(e,l);h(de),l.config.plugins.resize.createTimeGridEventResizer(de,h,Y,t)}},z=Ko(e),w=["sx__time-grid-event","sx__event"];Ot(e)&&w.push("is-event-new"),n&&w.push("is-event-copy"),!l.config.weekOptions.value.eventOverlap&&e._maxConcurrentEvents&&e._maxConcurrentEvents>1&&w.push("is-event-overlap"),!((i=e._options)===null||i===void 0)&&i.additionalClasses&&w.push(...e._options.additionalClasses);let y=Y=>{r(!0),p(b,Y)},W=Y=>{Ye(()=>r(!1)),u(e,Y)},Re=(a=e._customContent)===null||a===void 0?void 0:a.timeGrid,Dr=t&&e.start<t.start&&e.end>=t.start,kr=Dr?t==null?void 0:t.start:e.start;return d(k,{children:[d("div",{id:n?Li(e.id):void 0,"data-event-id":e.id,onClick:L,onDblClick:A,onKeyDown:ne,onMouseDown:y,onMouseUp:W,onTouchStart:y,onTouchEnd:W,className:w.join(" "),tabIndex:0,role:"button",style:{top:`${Ri(kr,l.config.dayBoundaries.value,l.config.timePointsPerDay)}%`,height:`${ji(kr,e.end,l.config.dayBoundaries.value,l.config.timePointsPerDay)}%`,insetInlineStart:`${g}%`,width:`${Go(g,n?100:l.config.weekOptions.value.eventWidth,e._maxConcurrentEvents,l.config.weekOptions.value.eventOverlap)}%`,backgroundColor:D?void 0:_.backgroundColor,color:D?void 0:_.textColor,borderTop:z,borderInlineEnd:z,borderBottom:z,borderInlineStart:D?void 0:_.borderInlineStart,padding:D?"0":void 0},children:d("div",{"data-ccid":E,className:"sx__time-grid-event-inner",children:[!D&&!Re&&d(k,{children:[e.title&&d("div",{className:"sx__time-grid-event-title",children:e.title}),d("div",{className:"sx__time-grid-event-time",children:[d(Ti,{strokeColor:_.iconStroke}),f(e.start,e.end)]}),e.people&&e.people.length>0&&d("div",{className:"sx__time-grid-event-people",children:[d(Eo,{strokeColor:_.iconStroke}),To(e.people)]}),e.location&&d("div",{className:"sx__time-grid-event-location",children:[d(Co,{strokeColor:_.iconStroke}),e.location]})]}),Re&&d("div",{dangerouslySetInnerHTML:{__html:((o=e._customContent)===null||o===void 0?void 0:o.timeGrid)||""}}),l.config.plugins.resize&&!(!((s=e._options)===null||s===void 0)&&s.disableResize)&&d("div",{className:"sx__time-grid-event-resize-handle",onMouseDown:$,onTouchStart:$})]})}),c&&d(Fi,{calendarEvent:c,isCopy:!0,setMouseDown:r,dayBoundariesDateTime:t})]})}var Tn=(e,t)=>e.start===t.start?e.end<t.end?1:e.end>t.end?-1:0:e.start<t.start?-1:e.start>t.start?1:0,Xo=(e,t)=>{let n=O(e.start),r=O(t.start),i=O(e.end),a=O(t.end);return n===r&&i===a&&e.start<t.start?-1:n===r?i<a?1:i>a?-1:0:n<r?-1:n>r?1:0},Di=e=>(e==null?void 0:e.start)===(e==null?void 0:e.end)&&Ae.DATE_TIME_STRING.test((e==null?void 0:e.start)||""),wt=(e,t)=>Di(e)&&Di(t)&&(e==null?void 0:e.start)===(t==null?void 0:t.start),dn=(e,t=[],n=0)=>{for(let r=n;r<e.length;r++){let i=e[r],a=e[r+1],o=wt(i,a);if(t.length&&(!a||t.every(s=>s.end<=a.start)&&!o)){t.push(i);for(let s=0;s<t.length;s++){let l=t[s],c=t.filter((m,f)=>m===l||f>s?!1:wt(m,l)?!0:m.start<=l.start&&m.end>l.start).length,h=t.filter((m,f)=>m===l||f<s?!1:wt(m,l)?!0:m.start<l.end&&m.end>=l.start).length;l._totalConcurrentEvents=c+h+1,l._previousConcurrentEvents=c;let p=0,u=[];t.forEach(m=>{(m.end>l.start&&m.start<l.end||wt(m,l))&&(u.push({time:m.start,type:"start"}),u.push({time:m.end,type:"end"}))}),u.sort((m,f)=>m.time.localeCompare(f.time)||(m.type==="end"?-1:1));let v=0;u.forEach(m=>{m.type==="start"?(v++,p=Math.max(p,v)):v--}),l._maxConcurrentEvents=p}return t=[],dn(e,t,r+1)}if(a&&i.end>a.start||t.some(s=>s.end>i.start)||o)return t.push(i),dn(e,t,r+1);i._totalConcurrentEvents=1,i._previousConcurrentEvents=0,i._maxConcurrentEvents=1}return e},ki=(e,t,n)=>{if(!(e.target instanceof HTMLElement))return;let r="sx__time-grid-day",i=e.target.classList.contains(r)?e.target:e.target.closest("."+r),o=(e.clientY-i.getBoundingClientRect().top)/i.getBoundingClientRect().height*100,s=Math.round(t.config.timePointsPerDay/100*o);return Ni(n,s)},Tt=e=>{switch(e){case 0:return"sx__sunday";case 1:return"sx__monday";case 2:return"sx__tuesday";case 3:return"sx__wednesday";case 4:return"sx__thursday";case 5:return"sx__friday";case 6:return"sx__saturday";default:throw new Error("Invalid weekday")}};function qo({backgroundEvent:e,date:t}){let n=N(j),r=e.start,i=e.end,a=O(r)!==t,o=O(i)!==t;return(K.test(r)||a)&&(r=r.substring(0,10)+" 00:00"),(K.test(i)||o)&&(i=i.substring(0,10)+" 23:59"),a&&(r=t+" "+r.split(" ")[1]),o&&(i=t+" "+i.split(" ")[1]),oe(r.split(" ")[1])<n.config.dayBoundaries.value.start&&(r=t+" "+be(n.config.dayBoundaries.value.start)),r===i?null:d(k,{children:d("div",{class:"sx__time-grid-background-event",title:e.title,style:{...e.style,position:"absolute",zIndex:0,top:`${Ri(r,n.config.dayBoundaries.value,n.config.timePointsPerDay)}%`,height:`${ji(r,i,n.config.dayBoundaries.value,n.config.timePointsPerDay)}%`,width:"100%"}})})}function Jo({calendarEvents:e,date:t,backgroundEvents:n}){let[r,i]=P(!1),a=N(j),o=be(a.config.dayBoundaries.value.start),s=be(a.config.dayBoundaries.value.end),l=Qt(t,o),c=a.config.isHybridDay?se(Qt(t,s),1):Qt(t,s),h={start:l,end:c},p=X(()=>{let g=e.sort(Tn);return dn(g)},[e]),u=(g,b)=>{if(!b||r)return;let D=ki(g,a,l);D&&b(D,g)},v=g=>{let b=a.config.callbacks.onMouseDownDateTime;if(!b||r)return;let D=ki(g,a,l);D&&b(D,g)},m=()=>{setTimeout(()=>{i(!1)},10)},f=["sx__time-grid-day",Tt(S(t).getDay())],_=Zt(()=>{let g=[...f];return a.datePickerState.selectedDate.value===t&&g.push("is-selected"),g});return d("div",{className:_.value.join(" "),"data-time-grid-date":t,onClick:g=>u(g,a.config.callbacks.onClickDateTime),onDblClick:g=>u(g,a.config.callbacks.onDoubleClickDateTime),"aria-label":ye(t,a.config.locale.value),onMouseLeave:()=>i(!1),onMouseUp:m,onTouchEnd:m,onMouseDown:v,children:[n.map(g=>d(k,{children:d(qo,{backgroundEvent:g,date:t})})),p.map(g=>d(Fi,{calendarEvent:g,dayBoundariesDateTime:h,setMouseDown:i},g.id))]})}var Zo=({start:e,end:t},n)=>{let r=[],i=Math.floor(e/100);if(n){for(;i<24;)r.push(i),i+=1;i=0}let a=t===0?24:Math.ceil(t/100);for(;i<a;)r.push(i),i+=1;return r};function Qo(){let e=N(j),[t,n]=P([]);re(()=>{n(Zo(e.config.dayBoundaries.value,e.config.isHybridDay));let o=e.config.timePointsPerDay/100,s=e.config.weekOptions.value.gridHeight/o;document.documentElement.style.setProperty("--sx-week-grid-hour-height",`${s}px`)});let r=new Intl.DateTimeFormat(e.config.locale.value,e.config.weekOptions.value.timeAxisFormatOptions),i=e.config._customComponentFns.weekGridHour,a=X(()=>i?t.map(()=>`custom-week-grid-hour-${V()}`):[],[t]);return M(()=>{i&&a.length&&t.forEach((o,s)=>{let l=document.querySelector(`[data-ccid="${a[s]}"]`);if(!(l instanceof HTMLElement))return console.warn("Could not find element for custom component weekGridHour");i(l,{hour:o})})},[t,a]),d(k,{children:d("div",{className:"sx__week-grid__time-axis",children:t.map((o,s)=>d("div",{className:"sx__week-grid__hour",children:[i&&a.length&&d("div",{"data-ccid":a[s]}),!i&&d("span",{className:"sx__week-grid__hour-text",children:r.format(new Date(0,0,0,o))})]}))})})}function es({week:e}){let t=N(j),n=a=>{let o=["sx__week-grid__date",Tt(a.getDay())];return Cn(a)&&o.push("sx__week-grid__date--is-today"),o.join(" ")},r=t.config._customComponentFns.weekGridDate,i=P(()=>Array.from({length:7},()=>`custom-week-grid-date-${V()}`));return M(()=>{r&&e.forEach((a,o)=>{let s=document.querySelector(`[data-ccid="${i[0][o]}"]`);if(!(s instanceof HTMLElement))return console.warn("Could not find element for custom component weekGridDate");r(s,{date:F(a)})})},[e]),d(k,{children:d("div",{className:"sx__week-grid__date-axis",children:e.map((a,o)=>d("div",{className:n(a),"data-date":F(a),children:[r&&d("div",{"data-ccid":i[0][o]}),!r&&d(k,{children:[d("div",{className:"sx__week-grid__day-name",children:En(a,t.config.locale.value)}),d("div",{className:"sx__week-grid__date-number",children:a.getDate()})]})]}))})})}var ts=e=>{let t=[],n=[];for(let r of e){if(r._isSingleDayTimed||r._isSingleHybridDayTimed){n.push(r);continue}(r._isSingleDayFullDay||r._isMultiDayFullDay||r._isMultiDayTimed)&&t.push(r)}return{timeGridEvents:n,dateGridEvents:t}},Pi=(e,t)=>{let n=F(t);return e[n]={date:n,timeGridEvents:[],dateGridEvents:{},backgroundEvents:[]},e},ns=e=>e.calendarState.view.value===G.Day?Pi({},S(e.calendarState.range.value.start)):e.timeUnitsImpl.getWeekFor(S(e.datePickerState.selectedDate.value)).slice(0,e.config.weekOptions.value.nDays).reduce(Pi,{}),rs=(e,t,n)=>{var r;for(let i of e){let a=n.calendarState.range.value;if(i.start>=a.start&&i.end<=a.end){let o=O(i.start);if(n.config.isHybridDay){let s=`${se(o,-1)} ${be(n.config.dayBoundaries.value.start)}`,l=`${o} ${be(n.config.dayBoundaries.value.end)}`,c=`${o} ${be(n.config.dayBoundaries.value.start)}`;i.start>s&&i.start<l&&i.start<c&&(o=se(o,-1))}(r=t[o])===null||r===void 0||r.timeGridEvents.push(i)}}return t};G.Week;var Wi={start:0,end:2400},is=1600,Xe="blocker",as=(e,t)=>{let n=Object.keys(t).sort(),r=n[0],i=n[n.length-1],a=new Set;for(let o of e){let s=O(o.start),l=O(o.end),c=!!t[s],h=c;if(!c&&s<r&&l>=r&&(h=!0),!h)continue;let p=c?s:r,u=l<=i?l:i,v=Object.values(t).filter(_=>_.date>=p&&_.date<=u),m,f=0;for(;m===void 0;)v.every(g=>!g.dateGridEvents[f])?(m=f,a.add(f)):f++;for(let[_,g]of v.entries())_===0?(o._nDaysInGrid=v.length,g.dateGridEvents[m]=o):g.dateGridEvents[m]=Xe}for(let o of Array.from(a))for(let[,s]of Object.entries(t))s.dateGridEvents[o]||(s.dateGridEvents[o]=void 0);return t},os=(e,t,n)=>{let r=2,i=10;return e&&n&&(r+=i),t&&n&&(r+=i),r},ss=(e,t,n)=>({borderBottomLeftRadius:e||n?0:void 0,borderTopLeftRadius:e||n?0:void 0,borderBottomRightRadius:t||n?0:void 0,borderTopRightRadius:t||n?0:void 0});function $i({calendarEvent:e,gridRow:t,isCopy:n}){var r,i,a,o;let s=N(j),{eventCopy:l,updateCopy:c,createDragStartTimeout:h,setClickedEventIfNotDragging:p,setClickedEvent:u}=et(s),v={borderInlineStart:`4px solid var(--sx-color-${e._color})`,color:`var(--sx-color-on-${e._color}-container)`,backgroundColor:`var(--sx-color-${e._color}-container)`},m=y=>{var W;if(!s.config.plugins.dragAndDrop||!((W=e._options)===null||W===void 0)&&W.disableDND)return;Qe(y)&&y.preventDefault();let Re=Be(e,s);c(Re),s.config.plugins.dragAndDrop.createDateGridDragHandler({eventCoordinates:Ii(y),eventCopy:Re,updateCopy:c,$app:s})},f=O(e.start)<O(s.calendarState.range.value.start),_=O(e.end)>O(s.calendarState.range.value.end),g=X(()=>s.config.direction==="ltr"?f:_,[f,_]),b=X(()=>s.config.direction==="ltr"?_:f,[f,_]),D={backgroundColor:v.backgroundColor},E=s.config._customComponentFns.dateGridEvent,L=E?"custom-date-grid-event-"+V():void 0;n&&L&&(L+="-copy"),M(()=>{E&&E(fe(L),{calendarEvent:e._getExternalEvent()})},[e,l]);let A=y=>{y.stopPropagation();let W=Be(e,s);c(W),s.config.plugins.resize.createDateGridEventResizer(W,c,y)},ne=y=>{(y.key==="Enter"||y.key===" ")&&(y.stopPropagation(),u(y,e),le(s,e,y),Ye(()=>{nt(s)}))},$=["sx__event","sx__date-grid-event","sx__date-grid-cell"];n&&$.push("sx__date-grid-event--copy"),Ot(e)&&$.push("is-event-new"),g&&$.push("sx__date-grid-event--overflow-left"),b&&$.push("sx__date-grid-event--overflow-right"),!((r=e._options)===null||r===void 0)&&r.additionalClasses&&$.push(...e._options.additionalClasses);let z=f?"none":v.borderInlineStart,w=(i=e._customContent)===null||i===void 0?void 0:i.dateGrid;return d(k,{children:[d("div",{id:n?Li(e.id):void 0,tabIndex:0,"aria-label":e.title+" "+Ai(e,s.config.locale.value,s.translate("to")),role:"button","data-ccid":L,"data-event-id":e.id,onMouseDown:y=>h(m,y),onMouseUp:y=>p(e,y),onTouchStart:y=>h(m,y),onTouchEnd:y=>p(e,y),onClick:y=>le(s,e,y),onDblClick:y=>tt(s,e,y),onKeyDown:ne,className:$.join(" "),style:{width:`calc(${e._nDaysInGrid*100}% - ${os(g,b,!E)}px)`,gridRow:t,display:l?"none":"flex",padding:E?"0px":void 0,borderInlineStart:E?void 0:z,color:E?void 0:v.color,backgroundColor:E?void 0:v.backgroundColor,...ss(g,b,!!E)},children:[!E&&!w&&d(k,{children:[g&&d("div",{className:"sx__date-grid-event--left-overflow",style:D}),d("span",{className:"sx__date-grid-event-text",children:[e.title," \xA0",ae.test(e.start)&&d("span",{className:"sx__date-grid-event-time",children:Se(e.start,s.config.locale.value)})]}),b&&d("div",{className:"sx__date-grid-event--right-overflow",style:D})]}),w&&d("div",{dangerouslySetInnerHTML:{__html:((a=e._customContent)===null||a===void 0?void 0:a.dateGrid)||""}}),s.config.plugins.resize&&!(!((o=e._options)===null||o===void 0)&&o.disableResize)&&!_&&d("div",{className:"sx__date-grid-event-resize-handle",onMouseDown:A,onTouchStart:A})]}),l&&d($i,{calendarEvent:l,gridRow:t,isCopy:!0})]})}function ls({calendarEvents:e,date:t,backgroundEvents:n}){let r=N(j),i=t+" 00:00",a=t+" 23:59",o=n.find(l=>{let c=K.test(l.start)?l.start+" 00:00":l.start,h=K.test(l.end)?l.end+" 23:59":l.end;return c<=i&&h>=a}),s=l=>{let c=r.config.callbacks.onMouseDownDateGridDate;c&&c(t,l)};return d("div",{className:"sx__date-grid-day","data-date-grid-date":t,children:[o&&d("div",{className:"sx__date-grid-background-event",title:o.title,style:{...o.style}}),Object.values(e).map((l,c)=>l===Xe||!l?d("div",{className:"sx__date-grid-cell",style:{gridRow:c+1},onMouseDown:s}):d($i,{calendarEvent:l,gridRow:c+1},l.start+l.end)),d("div",{className:"sx__spacer",onMouseDown:s})]})}var Ui=(e,t)=>e.filter(n=>{let r=t.start,i=t.end;K.test(r)&&(r=r+" 00:00"),K.test(i)&&(i=i+" 23:59");let a=n.start,o=n.end;K.test(a)&&(a=a+" 00:00"),K.test(o)&&(o=o+" 23:59");let s=a>=r&&a<=i,l=o>=r&&o<=i,c=a<r&&o>i;return s||l||c}),Vi=({$app:e,id:t})=>{document.documentElement.style.setProperty("--sx-week-grid-height",`${e.config.weekOptions.value.gridHeight}px`);let n=Zt(()=>{var r,i;let a=(r=e.calendarState.range.value)===null||r===void 0?void 0:r.start,o=(i=e.calendarState.range.value)===null||i===void 0?void 0:i.end;if(!a||!o)return{};let s=ns(e),l=e.calendarEvents.filterPredicate.value?e.calendarEvents.list.value.filter(e.calendarEvents.filterPredicate.value):e.calendarEvents.list.value,{dateGridEvents:c,timeGridEvents:h}=ts(l);return s=as(c.sort(Tn),s),Object.entries(s).forEach(([p,u])=>{u.backgroundEvents=Ui(e.calendarEvents.backgroundEvents.value,{start:p,end:p})}),s=rs(h,s,e),s});return d(k,{children:d(j.Provider,{value:e,children:d("div",{className:"sx__week-wrapper",id:t,children:[d("div",{className:"sx__week-header",children:d("div",{className:"sx__week-header-content",children:[d(es,{week:Object.values(n.value).map(r=>S(r.date))}),d("div",{className:"sx__date-grid","aria-label":e.translate("Full day- and multiple day events"),children:Object.values(n.value).map(r=>d(ls,{date:r.date,calendarEvents:r.dateGridEvents,backgroundEvents:r.backgroundEvents},r.date))}),d("div",{className:"sx__week-header-border"})]})}),d("div",{className:"sx__week-grid",children:[d(Qo,{}),Object.values(n.value).map(r=>d(Jo,{calendarEvents:r.timeGridEvents,backgroundEvents:r.backgroundEvents,date:r.date},r.date))]})]})})})},zi=(e,t)=>`${F(t)} ${be(e.dayBoundaries.value.start)}`,Hi=(e,t)=>{let n=be(e.dayBoundaries.value.end),r=F(t);return e.isHybridDay&&(r=se(r,1)),e.dayBoundaries.value.end===2400&&(n="23:59"),`${r} ${n}`},cs=e=>{let t=e.timeUnitsImpl.getWeekFor(S(e.date)).slice(0,e.calendarConfig.weekOptions.value.nDays);return{start:zi(e.calendarConfig,t[0]),end:Hi(e.calendarConfig,t[t.length-1])}},Nn=e=>{let{year:t,month:n}=Q(e.date),r=e.timeUnitsImpl.getMonthWithTrailingAndLeadingDays(t,n),i=F(r[r.length-1][r[r.length-1].length-1]);return{start:Mt(r[0][0]),end:`${i} 23:59`}},us=e=>({start:zi(e.calendarConfig,S(e.date)),end:Hi(e.calendarConfig,S(e.date))}),Gi={name:G.Week,label:"Week",Component:Vi,setDateRange:cs,hasSmallScreenCompat:!1,hasWideScreenCompat:!0,backwardForwardFn:se,backwardForwardUnits:7},ds=ce(Gi),Ki=()=>ce(Gi),hs=({$app:e,id:t})=>d(Vi,{$app:e,id:t}),Bi={name:G.Day,label:"Day",setDateRange:us,hasWideScreenCompat:!0,hasSmallScreenCompat:!0,Component:hs,backwardForwardFn:se,backwardForwardUnits:1},vs=ce(Bi),Xi=()=>ce(Bi),An=(e,t)=>{e=new Date(Date.UTC(e.getFullYear(),e.getMonth(),e.getDate()));let n=(e.getUTCDay()-t+7)%7;e.setUTCDate(e.getUTCDate()-n+3);let r=new Date(Date.UTC(e.getUTCFullYear(),0,1)),i=(r.getUTCDay()-t+7)%7;r.setUTCDate(r.getUTCDate()-i);let a=Math.ceil(((e.getTime()-r.getTime())/864e5+1)/7),o=new Date(Date.UTC(e.getUTCFullYear()+1,0,1)),s=(o.getUTCDay()-t+7)%7;return o.setUTCDate(o.getUTCDate()-s),e>=o?1:a};function fs(){let e=N(j);return d("div",{className:"sx__calendar-header__week-number",children:e.translate("CW",{week:An(S(e.datePickerState.selectedDate.value),e.config.firstDayOfWeek.value)})})}function ps(){let e=N(j),t=new sn().withDatePickerState(e.datePickerState).withConfig(e.datePickerConfig).withTranslate(e.translate).withTimeUnitsImpl(e.timeUnitsImpl).build(),n=e.config._customComponentFns.headerContent,r=P(n?V():void 0)[0],i=e.config._customComponentFns.headerContentLeftPrepend,a=P(i?V():void 0)[0],o=e.config._customComponentFns.headerContentLeftAppend,s=P(o?V():void 0)[0],l=e.config._customComponentFns.headerContentRightPrepend,c=P(l?V():void 0)[0],h=e.config._customComponentFns.headerContentRightAppend,p=P(h?V():void 0)[0];M(()=>{n&&n(fe(r),{$app:e}),i&&a&&i(fe(a),{$app:e}),o&&o(fe(s),{$app:e}),l&&l(fe(c),{$app:e}),h&&h(fe(p),{$app:e})},[e.datePickerState.selectedDate.value,e.calendarState.range.value,e.calendarState.isDark.value,e.calendarState.isCalendarSmall.value]);let u=e.config.locale.value,v=X(()=>[ds.name,vs.name].includes(e.calendarState.view.value),[e.calendarState.view.value]);return d("header",{className:"sx__calendar-header","data-ccid":r,children:!n&&d(k,{children:[d("div",{className:"sx__calendar-header-content",children:[a&&d("div",{"data-ccid":a}),d(Uo,{}),d(zo,{}),d($o,{},e.config.locale.value),e.config.showWeekNumbers.value&&v&&d(fs,{}),s&&d("div",{"data-ccid":s})]}),d("div",{className:"sx__calendar-header-content",children:[c&&d("div",{"data-ccid":c}),e.config.views.value.length>1&&d(Vo,{},u+"-view-selection"),d(Ro,{$app:t}),p&&d("div",{"data-ccid":p})]})]})})}var ms=(e,t)=>{e.elements.calendarWrapper=document.getElementById(t)},_s=(e,t)=>{let n=e.config.views.value.find(r=>r.name===e.calendarState.view.value);if(t){if(n.hasSmallScreenCompat)return;let r=e.config.views.value.find(i=>i.hasSmallScreenCompat);r&&e.calendarState.setView(r.name,e.datePickerState.selectedDate.value)}else{if(n.hasWideScreenCompat)return;let r=e.config.views.value.find(i=>i.hasWideScreenCompat);r&&e.calendarState.setView(r.name,e.datePickerState.selectedDate.value)}},gs=e=>{let t=document.documentElement,n=e.elements.calendarWrapper,r=+window.getComputedStyle(t).fontSize.split("p")[0],i=700,a=16/r,o=i/a;if(!n)return;let s=e.config.callbacks.isCalendarSmall?e.config.callbacks.isCalendarSmall(e):n.clientWidth<o;s!==e.calendarState.isCalendarSmall.value&&(e.calendarState.isCalendarSmall.value=s,_s(e,s))},Si=e=>`is-${e.calendarState.view.value}-view`;function bs(e){let t="sx__calendar-wrapper",[n,r]=P([t,Si(e)]);return re(()=>{let i=[t];e.calendarState.isCalendarSmall.value&&i.push("sx__is-calendar-small"),e.calendarState.isDark.value&&i.push("is-dark"),e.config.theme==="shadcn"&&i.push("is-shadcn"),i.push(Si(e)),r(i)}),n}var ys=e=>{Object.values(e.config.plugins).forEach(t=>{t!=null&&t.onRender&&t.onRender(e)})},ws=e=>{Object.values(e.config.plugins).forEach(t=>{t!=null&&t.destroy&&t.destroy()})},xs=e=>{Object.values(e.config.plugins).forEach(t=>{t!=null&&t.beforeRender&&t.beforeRender(e)})};function Ds({$app:e}){var t;let n=V(),r=V();M(()=>{var u;return ms(e,n),ys(e),!((u=e.config.callbacks)===null||u===void 0)&&u.onRender&&e.config.callbacks.onRender(e),()=>ws(e)},[]);let i=()=>{gs(e)};M(()=>{if(e.config.isResponsive)return i(),window.addEventListener("resize",i),()=>window.removeEventListener("resize",i)},[]);let a=bs(e),[o,s]=P();re(()=>{let u=e.config.views.value.find(m=>m.name===e.calendarState.view.value),v=document.getElementById(r);!u||!v||u.name===(o==null?void 0:o.name)||(o&&o.destroy(),s(u),u.render(v,e))});let[l,c]=P(""),[h,p]=P("");return re(()=>{var u,v;if(e.calendarState.view.value===G.List)return;let m=(((u=e.calendarState.range.value)===null||u===void 0?void 0:u.start)||"")>l;p(m?"sx__slide-left":"sx__slide-right"),setTimeout(()=>{p("")},300),c(((v=e.calendarState.range.value)===null||v===void 0?void 0:v.start)||"")}),re(()=>{e.datePickerConfig.locale.value=e.config.locale.value}),d(k,{children:d("div",{className:a.join(" "),id:n,children:d("div",{className:"sx__calendar",children:d(j.Provider,{value:e,children:[d(ps,{}),d("div",{className:["sx__view-container",h].join(" "),id:r}),e.config.plugins.eventModal&&e.config.plugins.eventModal.calendarEvent.value&&d(e.config.plugins.eventModal.ComponentFn,{$app:e},(t=e.config.plugins.eventModal.calendarEvent.value)===null||t===void 0?void 0:t.id)]})})})})}var Dt=(e,t)=>{let{id:n,start:r,end:i,title:a,description:o,location:s,people:l,_options:c,...h}=e;return new Et(t,n,r,i).withTitle(a).withDescription(o).withLocation(s).withPeople(l).withCalendarId(e.calendarId).withOptions(c).withForeignProperties(h).withCustomContent(e._customContent).build()},hn=class{constructor(t){Object.defineProperty(this,"$app",{enumerable:!0,configurable:!0,writable:!0,value:t})}set(t){this.$app.calendarEvents.list.value=t.map(n=>Dt(n,this.$app.config))}add(t){let n=Dt(t,this.$app.config);n._createdAt=new Date;let r=[...this.$app.calendarEvents.list.value];r.push(n),this.$app.calendarEvents.list.value=r}get(t){var n;return(n=this.$app.calendarEvents.list.value.find(r=>r.id===t))===null||n===void 0?void 0:n._getExternalEvent()}getAll(){return this.$app.calendarEvents.list.value.map(t=>t._getExternalEvent())}remove(t){let n=this.$app.calendarEvents.list.value.findIndex(i=>i.id===t),r=[...this.$app.calendarEvents.list.value];r.splice(n,1),this.$app.calendarEvents.list.value=r}update(t){let n=this.$app.calendarEvents.list.value.findIndex(i=>i.id===t.id),r=[...this.$app.calendarEvents.list.value];r.splice(n,1,Dt(t,this.$app.config)),this.$app.calendarEvents.list.value=r}},vn=class{constructor(t){var n;Object.defineProperty(this,"$app",{enumerable:!0,configurable:!0,writable:!0,value:t}),Object.defineProperty(this,"events",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"calendarContainerEl",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),this.events=new hn(this.$app),xs(this.$app),Object.values(this.$app.config.plugins).forEach(r=>{r!=null&&r.name&&(this[r.name]=r)}),!((n=t.config.callbacks)===null||n===void 0)&&n.beforeRender&&t.config.callbacks.beforeRender(t)}render(t){this.calendarContainerEl=t,De(ie(Ds,{$app:this.$app}),t)}destroy(){Object.values(this.$app.config.plugins||{}).forEach(t=>{!t||!t.destroy||t.destroy()}),this.calendarContainerEl&&De(null,this.calendarContainerEl)}setTheme(t){this.$app.calendarState.isDark.value=t==="dark"}getTheme(){return this.$app.calendarState.isDark.value?"dark":"light"}_setCustomComponentFn(t,n){this.$app.config._customComponentFns[t]=n}},fn=class{constructor(t,n,r,i,a,o,s,l={calendarWrapper:void 0}){Object.defineProperty(this,"config",{enumerable:!0,configurable:!0,writable:!0,value:t}),Object.defineProperty(this,"timeUnitsImpl",{enumerable:!0,configurable:!0,writable:!0,value:n}),Object.defineProperty(this,"calendarState",{enumerable:!0,configurable:!0,writable:!0,value:r}),Object.defineProperty(this,"datePickerState",{enumerable:!0,configurable:!0,writable:!0,value:i}),Object.defineProperty(this,"translate",{enumerable:!0,configurable:!0,writable:!0,value:a}),Object.defineProperty(this,"datePickerConfig",{enumerable:!0,configurable:!0,writable:!0,value:o}),Object.defineProperty(this,"calendarEvents",{enumerable:!0,configurable:!0,writable:!0,value:s}),Object.defineProperty(this,"elements",{enumerable:!0,configurable:!0,writable:!0,value:l})}},pn=class{constructor(){Object.defineProperty(this,"config",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"timeUnitsImpl",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"datePickerState",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"calendarState",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"translate",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"datePickerConfig",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"calendarEvents",{enumerable:!0,configurable:!0,writable:!0,value:void 0})}build(){return new fn(this.config,this.timeUnitsImpl,this.calendarState,this.datePickerState,this.translate,this.datePickerConfig,this.calendarEvents)}withConfig(t){return this.config=t,this}withTimeUnitsImpl(t){return this.timeUnitsImpl=t,this}withDatePickerState(t){return this.datePickerState=t,this}withCalendarState(t){return this.calendarState=t,this}withTranslate(t){return this.translate=t,this}withDatePickerConfig(t){return this.datePickerConfig=t,this}withCalendarEvents(t){return this.calendarEvents=t,this}},ee;(function(e){e.SLASH="/",e.DASH="-",e.PERIOD="."})(ee||(ee={}));var te;(function(e){e.DMY="DMY",e.MDY="MDY",e.YMD="YMD"})(te||(te={}));var T={slashMDY:{delimiter:ee.SLASH,order:te.MDY},slashDMY:{delimiter:ee.SLASH,order:te.DMY},slashYMD:{delimiter:ee.SLASH,order:te.YMD},periodDMY:{delimiter:ee.PERIOD,order:te.DMY},dashYMD:{delimiter:ee.DASH,order:te.YMD},dashDMY:{delimiter:ee.DASH,order:te.DMY}},ks=new Map([["ca-ES",T.slashDMY],["cs-CZ",T.periodDMY],["da-DK",T.periodDMY],["de-DE",T.periodDMY],["en-GB",T.slashDMY],["en-US",T.slashMDY],["es-ES",T.slashDMY],["et-EE",T.periodDMY],["fi-FI",T.periodDMY],["fr-FR",T.slashDMY],["fr-CH",T.periodDMY],["hr-HR",T.periodDMY],["id-ID",T.slashDMY],["it-IT",T.slashDMY],["ja-JP",T.slashYMD],["ko-KR",T.slashYMD],["ky-KG",T.slashDMY],["lt-LT",T.dashYMD],["mk-MK",T.periodDMY],["nl-NL",T.dashDMY],["pl-PL",T.periodDMY],["pt-BR",T.slashDMY],["ro-RO",T.periodDMY],["ru-RU",T.periodDMY],["sk-SK",T.periodDMY],["sl-SI",T.periodDMY],["sr-Latn-RS",T.periodDMY],["sr-RS",T.periodDMY],["sv-SE",T.dashYMD],["tr-TR",T.periodDMY],["uk-UA",T.periodDMY],["zh-CN",T.slashYMD],["zh-TW",T.slashYMD]]),mn=class extends Error{constructor(t){super(`Locale not supported: ${t}`)}},Ct=class extends Error{constructor(t,n){super(`Invalid date format: ${t} for locale: ${n}`)}},xt=(e,t,n)=>{let r=e.match(t);if(!r)throw new Ct(e,n);return r},Ps=(e,t)=>{if(/^\d{4}-\d{2}-\d{2}$/.test(e))return e;let r=ks.get(t);if(!r)throw new mn(t);let{order:i,delimiter:a}=r,o=/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/,s=/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/,l=/^(\d{4})\/(\d{1,2})\/(\d{1,2})$/;if(i===te.DMY&&a===ee.SLASH){let c=xt(e,o,t),[,h,p,u]=c;return`${u}-${B(+p)}-${B(+h)}`}if(i===te.MDY&&a===ee.SLASH){let c=xt(e,o,t),[,h,p,u]=c;return`${u}-${B(+h)}-${B(+p)}`}if(i===te.YMD&&a===ee.SLASH){let c=xt(e,l,t),[,h,p,u]=c;return`${h}-${B(+p)}-${B(+u)}`}if(i===te.DMY&&a===ee.PERIOD){let c=xt(e,s,t),[,h,p,u]=c;return`${u}-${B(+p)}-${B(+h)}`}throw new Ct(e,t)},Ss=(e,t)=>{var n;let r=F(new Date),i=typeof t=="string"?t:r,a=C(!1),o=C(e.disabled||!1),s=C(Ee.MONTH_DAYS),l=C(i),c=C(i||r),h=C(((n=e.style)===null||n===void 0?void 0:n.dark)||!1),p=C(t?Ci(S(t),e.locale.value):""),u=C(p.value);Z(()=>{try{let f=Ps(p.value,e.locale.value);if(f<e.min||f>e.max){p.value=u.value;return}l.value=f,c.value=f,u.value=p.value}catch{}});let v=!1,m=f=>{if(!v)return v=!0;e.listeners.onChange(f)};return Z(()=>{var f;!((f=e.listeners)===null||f===void 0)&&f.onChange&&m(l.value)}),{inputWrapperElement:C(void 0),isOpen:a,isDisabled:o,datePickerView:s,selectedDate:l,datePickerDate:c,inputDisplayedValue:p,isDark:h,open:()=>a.value=!0,close:()=>a.value=!1,toggle:()=>a.value=!a.value,setView:f=>s.value=f}},Es={Date:"\u0627\u0644\u062A\u0627\u0631\u064A\u062E","MM/DD/YYYY":"DD/MM/YYYY","Next month":"\u0627\u0644\u0634\u0647\u0631 \u0627\u0644\u0642\u0627\u062F\u0645","Previous month":"\u0627\u0644\u0634\u0647\u0631 \u0627\u0644\u0633\u0627\u0628\u0642","Choose Date":"\u0627\u062E\u062A\u0631 \u0627\u0644\u062A\u0627\u0631\u064A\u062E"},Cs={Time:"\u0627\u0644\u0648\u0642\u062A",AM:"\u0635",PM:"\u0645",Cancel:"\u0625\u0644\u063A\u0627\u0621",OK:"\u0645\u0648\u0627\u0641\u0642","Select time":"\u0627\u062E\u062A\u0631 \u0627\u0644\u0648\u0642\u062A"},Ms={Today:"\u0627\u0644\u064A\u0648\u0645",Month:"\u0627\u0644\u0634\u0647\u0631",Week:"\u0627\u0644\u0623\u0633\u0628\u0648\u0639",Day:"\u0627\u0644\u064A\u0648\u0645",List:"\u0627\u0644\u0642\u0627\u0626\u0645\u0629","Select View":"\u0627\u062E\u062A\u0631 \u0627\u0644\u0639\u0631\u0636","+ {{n}} events":"+ {{n}} \u0627\u0644\u0623\u062D\u062F\u0627\u062B","+ 1 event":"+ 1 \u062D\u062F\u062B","No events":"\u0644\u0627 \u062A\u0648\u062C\u062F \u0623\u062D\u062F\u0627\u062B","Next period":"\u0627\u0644\u0641\u062A\u0631\u0629 \u0627\u0644\u062A\u0627\u0644\u064A\u0629","Previous period":"\u0627\u0644\u0641\u062A\u0631\u0629 \u0627\u0644\u0633\u0627\u0628\u0642\u0629",to:"\u0625\u0644\u0649","Full day- and multiple day events":"\u0623\u062D\u062F\u0627\u062B \u0644\u064A\u0648\u0645 \u0643\u0627\u0645\u0644 \u0623\u0648 \u0644\u0639\u062F\u0629 \u0623\u064A\u0627\u0645","Link to {{n}} more events on {{date}}":"\u0631\u0627\u0628\u0637 \u0625\u0644\u0649 {{n}} \u0623\u062D\u062F\u0627\u062B \u0623\u062E\u0631\u0649 \u0641\u064A {{date}}","Link to 1 more event on {{date}}":"\u0631\u0627\u0628\u0637 \u0625\u0644\u0649 \u062D\u062F\u062B \u0622\u062E\u0631 \u0641\u064A {{date}}",CW:"\u0627\u0644\u0623\u0633\u0628\u0648\u0639 {{week}}"},Os={...Ms,...Es,...Cs},Ts={Date:"Datum","MM/DD/YYYY":"TT.MM.JJJJ","Next month":"N\xE4chster Monat","Previous month":"Vorheriger Monat","Choose Date":"Datum ausw\xE4hlen"},Ns={Today:"Heute",Month:"Monat",Week:"Woche",Day:"Tag",List:"Liste","Select View":"Ansicht ausw\xE4hlen","+ {{n}} events":"+ {{n}} Ereignisse","+ 1 event":"+ 1 Ereignis","No events":"Keine Ereignisse","Next period":"N\xE4chster Zeitraum","Previous period":"Vorheriger Zeitraum",to:"bis","Full day- and multiple day events":"Ganzt\xE4gige und mehrt\xE4gige Termine","Link to {{n}} more events on {{date}}":"Link zu {{n}} weiteren Terminen am {{date}}","Link to 1 more event on {{date}}":"Link zu 1 weiterem Termin am {{date}}",CW:"KW {{week}}"},As={Time:"Uhrzeit",AM:"AM",PM:"PM",Cancel:"Abbrechen",OK:"OK","Select time":"Uhrzeit ausw\xE4hlen"},Ys={...Ts,...Ns,...As},js={Date:"Date","MM/DD/YYYY":"MM/DD/YYYY","Next month":"Next month","Previous month":"Previous month","Choose Date":"Choose Date"},Ls={Today:"Today",Month:"Month",Week:"Week",Day:"Day",List:"List","Select View":"Select View","+ {{n}} events":"+ {{n}} events","+ 1 event":"+ 1 event","No events":"No events","Next period":"Next period","Previous period":"Previous period",to:"to","Full day- and multiple day events":"Full day- and multiple day events","Link to {{n}} more events on {{date}}":"Link to {{n}} more events on {{date}}","Link to 1 more event on {{date}}":"Link to 1 more event on {{date}}",CW:"Week {{week}}"},Is={Time:"Time",AM:"AM",PM:"PM",Cancel:"Cancel",OK:"OK","Select time":"Select time"},Rs={...js,...Ls,...Is},Fs={Date:"Data","MM/DD/YYYY":"DD/MM/YYYY","Next month":"Mese successivo","Previous month":"Mese precedente","Choose Date":"Scegli la data"},Ws={Today:"Oggi",Month:"Mese",Week:"Settimana",Day:"Giorno",List:"Lista","Select View":"Seleziona la vista","+ {{n}} events":"+ {{n}} eventi","+ 1 event":"+ 1 evento","No events":"Nessun evento","Next period":"Periodo successivo","Previous period":"Periodo precedente",to:"a","Full day- and multiple day events":"Eventi della giornata e plurigiornalieri","Link to {{n}} more events on {{date}}":"Link a {{n}} eventi in pi\xF9 il {{date}}","Link to 1 more event on {{date}}":"Link a 1 evento in pi\xF9 il {{date}}",CW:"Settimana {{week}}"},$s={Time:"Ora",AM:"AM",PM:"PM",Cancel:"Annulla",OK:"OK","Select time":"Seleziona ora"},Us={...Fs,...Ws,...$s},Vs={Date:"Date","MM/DD/YYYY":"DD/MM/YYYY","Next month":"Next month","Previous month":"Previous month","Choose Date":"Choose Date"},zs={Today:"Today",Month:"Month",Week:"Week",Day:"Day",List:"List","Select View":"Select View","+ {{n}} events":"+ {{n}} events","+ 1 event":"+ 1 event","No events":"No events","Next period":"Next period","Previous period":"Previous period",to:"to","Full day- and multiple day events":"Full day- and multiple day events","Link to {{n}} more events on {{date}}":"Link to {{n}} more events on {{date}}","Link to 1 more event on {{date}}":"Link to 1 more event on {{date}}",CW:"Week {{week}}"},Hs={Time:"Time",AM:"AM",PM:"PM",Cancel:"Cancel",OK:"OK","Select time":"Select time"},Gs={...Vs,...zs,...Hs},Ks={Date:"Datum","MM/DD/YYYY":"\xC5\xC5\xC5\xC5-MM-DD","Next month":"N\xE4sta m\xE5nad","Previous month":"F\xF6reg\xE5ende m\xE5nad","Choose Date":"V\xE4lj datum"},Bs={Today:"Idag",Month:"M\xE5nad",Week:"Vecka",Day:"Dag",List:"Lista","Select View":"V\xE4lj vy","+ {{n}} events":"+ {{n}} h\xE4ndelser","+ 1 event":"+ 1 h\xE4ndelse","No events":"Inga h\xE4ndelser","Next period":"N\xE4sta period","Previous period":"F\xF6reg\xE5ende period",to:"till","Full day- and multiple day events":"Heldags- och flerdagsh\xE4ndelser","Link to {{n}} more events on {{date}}":"L\xE4nk till {{n}} fler h\xE4ndelser den {{date}}","Link to 1 more event on {{date}}":"L\xE4nk till 1 h\xE4ndelse till den {{date}}",CW:"Vecka {{week}}"},Xs={Time:"Tid",AM:"FM",PM:"EM",Cancel:"Avbryt",OK:"OK","Select time":"V\xE4lj tid"},qs={...Ks,...Bs,...Xs},Js={Date:"\u65E5\u671F","MM/DD/YYYY":"\u5E74/\u6708/\u65E5","Next month":"\u4E0B\u4E2A\u6708","Previous month":"\u4E0A\u4E2A\u6708","Choose Date":"\u9009\u62E9\u65E5\u671F"},Zs={Today:"\u4ECA\u5929",Month:"\u6708",Week:"\u5468",Day:"\u65E5",List:"\u5217\u8868","Select View":"\u9009\u62E9\u89C6\u56FE","+ {{n}} events":"+ {{n}} \u573A\u6D3B\u52A8","+ 1 event":"+ 1 \u6D3B\u52A8","No events":"\u6CA1\u6709\u6D3B\u52A8","Next period":"\u4E0B\u4E00\u6BB5\u65F6\u95F4","Previous period":"\u4E0A\u4E00\u6BB5\u65F6\u95F4",to:"\u81F3","Full day- and multiple day events":"\u5168\u5929\u548C\u591A\u5929\u6D3B\u52A8","Link to {{n}} more events on {{date}}":"\u94FE\u63A5\u5230{{date}}\u4E0A\u7684{{n}}\u4E2A\u66F4\u591A\u6D3B\u52A8","Link to 1 more event on {{date}}":"\u94FE\u63A5\u5230{{date}}\u4E0A\u76841\u4E2A\u66F4\u591A\u6D3B\u52A8",CW:"\u7B2C{{week}}\u5468"},Qs={Time:"\u65F6\u95F4",AM:"\u4E0A\u5348",PM:"\u4E0B\u5348",Cancel:"\u53D6\u6D88",OK:"\u786E\u5B9A","Select time":"\u9009\u62E9\u65F6\u95F4"},el={...Js,...Zs,...Qs},tl={Date:"\u65E5\u671F","MM/DD/YYYY":"\u5E74/\u6708/\u65E5","Next month":"\u4E0B\u500B\u6708","Previous month":"\u4E0A\u500B\u6708","Choose Date":"\u9078\u64C7\u65E5\u671F"},nl={Today:"\u4ECA\u5929",Month:"\u6708",Week:"\u5468",Day:"\u65E5",List:"\u5217\u8868","Select View":"\u9078\u64C7\u6AA2\u8996\u6A21\u5F0F","+ {{n}} events":"+ {{n}} \u5834\u6D3B\u52D5","+ 1 event":"+ 1 \u6D3B\u52D5","No events":"\u6C92\u6709\u6D3B\u52D5","Next period":"\u4E0B\u4E00\u6BB5\u6642\u9593","Previous period":"\u4E0A\u4E00\u6BB5\u6642\u9593",to:"\u5230","Full day- and multiple day events":"\u5168\u5929\u548C\u591A\u5929\u6D3B\u52D5","Link to {{n}} more events on {{date}}":"\u9023\u63A5\u5230{{date}}\u4E0A\u7684{{n}}\u500B\u66F4\u591A\u6D3B\u52D5","Link to 1 more event on {{date}}":"\u9023\u63A5\u5230{{date}}\u4E0A\u76841\u500B\u66F4\u591A\u6D3B\u52D5",CW:"\u7B2C{{week}}\u5468"},rl={Time:"\u6642\u9593",AM:"\u4E0A\u5348",PM:"\u4E0B\u5348",Cancel:"\u53D6\u6D88",OK:"\u78BA\u5B9A","Select time":"\u9078\u64C7\u6642\u9593"},il={...tl,...nl,...rl},al={Date:"\u65E5\u4ED8","MM/DD/YYYY":"\u5E74/\u6708/\u65E5","Next month":"\u6B21\u306E\u6708","Previous month":"\u524D\u306E\u6708","Choose Date":"\u65E5\u4ED8\u3092\u9078\u629E"},ol={Today:"\u4ECA\u65E5",Month:"\u6708",Week:"\u9031",Day:"\u65E5",List:"\u30EA\u30B9\u30C8","Select View":"\u30D3\u30E5\u30FC\u3092\u9078\u629E","+ {{n}} events":"+ {{n}} \u30A4\u30D9\u30F3\u30C8","+ 1 event":"+ 1 \u30A4\u30D9\u30F3\u30C8","No events":"\u30A4\u30D9\u30F3\u30C8\u306A\u3057","Next period":"\u6B21\u306E\u671F\u9593","Previous period":"\u524D\u306E\u671F\u9593",to:"\u304B\u3089","Full day- and multiple day events":"\u7D42\u65E5\u304A\u3088\u3073\u8907\u6570\u65E5\u30A4\u30D9\u30F3\u30C8","Link to {{n}} more events on {{date}}":"{{date}} \u306B{{n}}\u4EF6\u306E\u30A4\u30D9\u30F3\u30C8\u3078\u306E\u30EA\u30F3\u30AF","Link to 1 more event on {{date}}":"{{date}} \u306B1\u4EF6\u306E\u30A4\u30D9\u30F3\u30C8\u3078\u306E\u30EA\u30F3\u30AF",CW:"\u9031 {{week}}"},sl={Time:"\u6642\u9593",AM:"\u5348\u524D",PM:"\u5348\u5F8C",Cancel:"\u30AD\u30E3\u30F3\u30BB\u30EB",OK:"OK","Select time":"\u6642\u9593\u3092\u9078\u629E"},ll={...al,...ol,...sl},cl={Date:"\u0414\u0430\u0442\u0430","MM/DD/YYYY":"\u041C\u041C/\u0414\u0414/\u0413\u0413\u0413\u0413","Next month":"\u0421\u043B\u0435\u0434\u0443\u044E\u0449\u0438\u0439 \u043C\u0435\u0441\u044F\u0446","Previous month":"\u041F\u0440\u043E\u0448\u043B\u044B\u0439 \u043C\u0435\u0441\u044F\u0446","Choose Date":"\u0412\u044B\u0431\u0435\u0440\u0438\u0442\u0435 \u0434\u0430\u0442\u0443"},ul={Today:"\u0421\u0435\u0433\u043E\u0434\u043D\u044F",Month:"\u041C\u0435\u0441\u044F\u0446",Week:"\u041D\u0435\u0434\u0435\u043B\u044F",Day:"\u0414\u0435\u043D\u044C",List:"\u0421\u043F\u0438\u0441\u043E\u043A","Select View":"\u0412\u044B\u0431\u0435\u0440\u0438\u0442\u0435 \u0432\u0438\u0434","+ {{n}} events":"+ {{n}} \u0441\u043E\u0431\u044B\u0442\u0438\u044F","+ 1 event":"+ 1 \u0441\u043E\u0431\u044B\u0442\u0438\u0435","No events":"\u041D\u0435\u0442 \u0441\u043E\u0431\u044B\u0442\u0438\u0439","Next period":"\u0421\u043B\u0435\u0434\u0443\u044E\u0449\u0438\u0439 \u043F\u0435\u0440\u0438\u043E\u0434","Previous period":"\u041F\u0440\u043E\u0448\u043B\u044B\u0439 \u043F\u0435\u0440\u0438\u043E\u0434",to:"\u043F\u043E","Full day- and multiple day events":"\u0421\u043E\u0431\u044B\u0442\u0438\u044F \u043D\u0430 \u0446\u0435\u043B\u044B\u0439 \u0434\u0435\u043D\u044C \u0438 \u043D\u0435\u0441\u043A\u043E\u043B\u044C\u043A\u043E \u0434\u043D\u0435\u0439 \u043F\u043E\u0434\u0440\u044F\u0434","Link to {{n}} more events on {{date}}":"\u0421\u0441\u044B\u043B\u043A\u0430 \u043D\u0430 {{n}} \u0434\u043E\u043F\u043E\u043B\u043D\u0438\u0442\u0435\u043B\u044C\u043D\u044B\u0445 \u0441\u043E\u0431\u044B\u0442\u0438\u0439 \u043D\u0430 {{date}}","Link to 1 more event on {{date}}":"\u0421\u0441\u044B\u043B\u043A\u0430 \u043D\u0430 1 \u0434\u043E\u043F\u043E\u043B\u043D\u0438\u0442\u0435\u043B\u044C\u043D\u043E\u0435 \u0441\u043E\u0431\u044B\u0442\u0438\u0435 \u043D\u0430 {{date}}",CW:"\u041D\u0435\u0434\u0435\u043B\u044F {{week}}"},dl={Time:"\u0412\u0440\u0435\u043C\u044F",AM:"AM",PM:"PM",Cancel:"\u041E\u0442\u043C\u0435\u043D\u0430",OK:"\u041E\u041A","Select time":"\u0412\u044B\u0431\u0435\u0440\u0438\u0442\u0435 \u0432\u0440\u0435\u043C\u044F"},hl={...cl,...ul,...dl},vl={Date:"\uC77C\uC790","MM/DD/YYYY":"\uB144/\uC6D4/\uC77C","Next month":"\uB2E4\uC74C \uB2EC","Previous month":"\uC774\uC804 \uB2EC","Choose Date":"\uB0A0\uC9DC \uC120\uD0DD"},fl={Today:"\uC624\uB298",Month:"\uC6D4",Week:"\uC8FC",Day:"\uC77C",List:"\uBAA9\uB85D","Select View":"\uBCF4\uAE30 \uC120\uD0DD","+ {{n}} events":"+ {{n}} \uC77C\uC815\uB4E4","+ 1 event":"+ 1 \uC77C\uC815","No events":"\uC77C\uC815 \uC5C6\uC74C","Next period":"\uB2E4\uC74C","Previous period":"\uC774\uC804",to:"\uBD80\uD130","Full day- and multiple day events":"\uC885\uC77C \uBC0F \uBCF5\uC218\uC77C \uC77C\uC815","Link to {{n}} more events on {{date}}":"{{date}}\uC5D0 {{n}}\uAC1C \uC774\uC0C1\uC758 \uC774\uBCA4\uD2B8\uB85C \uC774\uB3D9","Link to 1 more event on {{date}}":"{{date}}\uC5D0 1\uAC1C \uC774\uC0C1\uC758 \uC774\uBCA4\uD2B8\uB85C \uC774\uB3D9",CW:"{{week}}\uC8FC"},pl={Time:"\uC2DC\uAC04",AM:"\uC624\uC804",PM:"\uC624\uD6C4",Cancel:"\uCDE8\uC18C",OK:"\uD655\uC778","Select time":"\uC2DC\uAC04 \uC120\uD0DD"},ml={...vl,...fl,...pl},_l={Date:"Date","MM/DD/YYYY":"JJ/MM/AAAA","Next month":"Mois suivant","Previous month":"Mois pr\xE9c\xE9dent","Choose Date":"Choisir une date"},gl={Today:"Aujourd'hui",Month:"Mois",Week:"Semaine",Day:"Jour",List:"Liste","Select View":"S\xE9lectionner la vue","+ {{n}} events":"+ {{n}} \xE9v\xE9nements","+ 1 event":"+ 1 \xE9v\xE9nement","No events":"Aucun \xE9v\xE9nement","Next period":"P\xE9riode suivante","Previous period":"P\xE9riode pr\xE9c\xE9dente",to:"au","Full day- and multiple day events":"\xC9v\xE9nements sur une journ\xE9e ou plusieurs jours","Link to {{n}} more events on {{date}}":"Lien vers {{n}} \xE9v\xE9nements suppl\xE9mentaires le {{date}}","Link to 1 more event on {{date}}":"Lien vers 1 \xE9v\xE9nement suppl\xE9mentaire le {{date}}",CW:"S{{week}}"},bl={Time:"Heure",AM:"AM",PM:"PM",Cancel:"Annuler",OK:"OK","Select time":"S\xE9lectionner l'heure"},yl={..._l,...gl,...bl},wl={Date:"Dato","MM/DD/YYYY":"\xC5\xC5\xC5\xC5-MM-DD","Next month":"N\xE6ste m\xE5ned","Previous month":"Foreg\xE5ende m\xE5ned","Choose Date":"V\xE6lg dato"},xl={Today:"I dag",Month:"M\xE5ned",Week:"Uge",Day:"Dag",List:"Liste","Select View":"V\xE6lg visning","+ {{n}} events":"+ {{n}} begivenheder","+ 1 event":"+ 1 begivenhed","No events":"Ingen begivenheder","Next period":"N\xE6ste periode","Previous period":"Forg\xE5ende periode",to:"til","Full day- and multiple day events":"Heldagsbegivenheder og flerdagsbegivenheder","Link to {{n}} more events on {{date}}":"Link til {{n}} flere begivenheder den {{date}}","Link to 1 more event on {{date}}":"Link til 1 mere begivenhed den {{date}}",CW:"Uge {{week}}"},Dl={Time:"Tid",AM:"AM",PM:"PM",Cancel:"Annuller",OK:"OK","Select time":"V\xE6lg tid"},kl={...wl,...xl,...Dl},Pl={Date:"Data","MM/DD/YYYY":"DD/MM/YYYY","Next month":"Nast\u0119pny miesi\u0105c","Previous month":"Poprzedni miesi\u0105c","Choose Date":"Wybiewrz dat\u0119"},Sl={Today:"Dzisiaj",Month:"Miesi\u0105c",Week:"Tydzie\u0144",Day:"Dzie\u0144",List:"Lista","Select View":"Wybierz widok","+ {{n}} events":"+ {{n}} wydarzenia","+ 1 event":"+ 1 wydarzenie","No events":"Brak wydarze\u0144","Next period":"Nast\u0119pny okres","Previous period":"Poprzedni okres",to:"do","Full day- and multiple day events":"Wydarzenia ca\u0142odniowe i wielodniowe","Link to {{n}} more events on {{date}}":"Link do {{n}} kolejnych wydarze\u0144 w dniu {{date}}","Link to 1 more event on {{date}}":"Link do 1 kolejnego wydarzenia w dniu {{date}}",CW:"Tydzie\u0144 {{week}}"},El={Time:"Godzina",AM:"AM",PM:"PM",Cancel:"Anuluj",OK:"OK","Select time":"Wybierz godzin\u0119"},Cl={...Pl,...Sl,...El},Ml={Date:"Fecha","MM/DD/YYYY":"DD/MM/YYYY","Next month":"Siguiente mes","Previous month":"Mes anterior","Choose Date":"Seleccione una fecha"},Ol={Today:"Hoy",Month:"Mes",Week:"Semana",Day:"D\xEDa",List:"Lista","Select View":"Seleccionar vista","+ {{n}} events":"+ {{n}} eventos","+ 1 event":"+ 1 evento","No events":"No hay eventos","Next period":"Siguiente per\xEDodo","Previous period":"Per\xEDodo anterior",to:"a","Full day- and multiple day events":"D\xEDa completo y eventos de m\xFAltiples d\xEDas","Link to {{n}} more events on {{date}}":"Enlace a {{n}} eventos m\xE1s el {{date}}","Link to 1 more event on {{date}}":"Enlace a 1 evento m\xE1s el {{date}}",CW:"Semana {{week}}"},Tl={Time:"Hora",AM:"AM",PM:"PM",Cancel:"Cancelar",OK:"Aceptar","Select time":"Seleccionar hora"},Nl={...Ml,...Ol,...Tl},Al={Today:"Vandaag",Month:"Maand",Week:"Week",Day:"Dag",List:"Lijst","Select View":"Kies weergave","+ {{n}} events":"+ {{n}} gebeurtenissen","+ 1 event":"+ 1 gebeurtenis","No events":"Geen gebeurtenissen","Next period":"Volgende periode","Previous period":"Vorige periode",to:"tot","Full day- and multiple day events":"Evenementen van een hele dag en meerdere dagen","Link to {{n}} more events on {{date}}":"Link naar {{n}} meer evenementen op {{date}}","Link to 1 more event on {{date}}":"Link naar 1 meer evenement op {{date}}",CW:"Week {{week}}"},Yl={Date:"Datum","MM/DD/YYYY":"DD-MM-JJJJ","Next month":"Volgende maand","Previous month":"Vorige maand","Choose Date":"Kies datum"},jl={Time:"Tijd",AM:"AM",PM:"PM",Cancel:"Annuleren",OK:"OK","Select time":"Selecteer tijd"},Ll={...Yl,...Al,...jl},Il={Date:"Data","MM/DD/YYYY":"DD/MM/YYYY","Next month":"M\xEAs seguinte","Previous month":"M\xEAs anterior","Choose Date":"Escolha uma data"},Rl={Today:"Hoje",Month:"M\xEAs",Week:"Semana",Day:"Dia",List:"Lista","Select View":"Selecione uma visualiza\xE7\xE3o","+ {{n}} events":"+ {{n}} eventos","+ 1 event":"+ 1 evento","No events":"Sem eventos","Next period":"Per\xEDodo seguinte","Previous period":"Per\xEDodo anterior",to:"a","Full day- and multiple day events":"Dia inteiro e eventos de v\xE1rios dias","Link to {{n}} more events on {{date}}":"Link para mais {{n}} eventos em {{date}}","Link to 1 more event on {{date}}":"Link para mais 1 evento em {{date}}",CW:"Semana {{week}}"},Fl={Time:"Hora",AM:"AM",PM:"PM",Cancel:"Cancelar",OK:"OK","Select time":"Selecionar hora"},Wl={...Il,...Rl,...Fl},$l={Date:"D\xE1tum","MM/DD/YYYY":"DD/MM/YYYY","Next month":"\u010Eal\u0161\xED mesiac","Previous month":"Predch\xE1dzaj\xFAci mesiac","Choose Date":"Vyberte d\xE1tum"},Ul={Today:"Dnes",Month:"Mesiac",Week:"T\xFD\u017Ede\u0148",Day:"De\u0148",List:"Zoznam","Select View":"Vyberte zobrazenie","+ {{n}} events":"+ {{n}} udalosti","+ 1 event":"+ 1 udalos\u0165","No events":"\u017Diadne udalosti","Next period":"\u010Eal\u0161ie obdobie","Previous period":"Predch\xE1dzaj\xFAce obdobie",to:"do","Full day- and multiple day events":"Celodenn\xE9 a viacd\u0148ov\xE9 udalosti","Link to {{n}} more events on {{date}}":"Odkaz na {{n}} \u010Fal\u0161\xEDch udalost\xED d\u0148a {{date}}","Link to 1 more event on {{date}}":"Odkaz na 1 \u010Fal\u0161iu udalos\u0165 d\u0148a {{date}}",CW:"{{week}}. t\xFD\u017Ede\u0148"},Vl={Time:"\u010Cas",AM:"AM",PM:"PM",Cancel:"Zru\u0161i\u0165",OK:"OK","Select time":"Vybra\u0165 \u010Das"},zl={...$l,...Ul,...Vl},Hl={Date:"\u0414\u0430\u0442\u0443\u043C","MM/DD/YYYY":"DD/MM/YYYY","Next month":"\u0421\u043B\u0435\u0434\u0435\u043D \u043C\u0435\u0441\u0435\u0446","Previous month":"\u041F\u0440\u0435\u0442\u0445\u043E\u0434\u0435\u043D \u043C\u0435\u0441\u0435\u0446","Choose Date":"\u0418\u0437\u0431\u0435\u0440\u0438 \u0414\u0430\u0442\u0443\u043C"},Gl={Today:"\u0414\u0435\u043D\u0435\u0441",Month:"\u041C\u0435\u0441\u0435\u0446",Week:"\u041D\u0435\u0434\u0435\u043B\u0430",Day:"\u0414\u0435\u043D",List:"\u041B\u0438\u0441\u0442\u0430","Select View":"\u0418\u0437\u0431\u0435\u0440\u0438 \u041F\u0440\u0435\u0433\u043B\u0435\u0434","+ {{n}} events":"+ {{n}} \u043D\u0430\u0441\u0442\u0430\u043D\u0438","+ 1 event":"+ 1 \u043D\u0430\u0441\u0442\u0430\u043D","No events":"\u041D\u0435\u043C\u0430 \u043D\u0430\u0441\u0442\u0430\u043D\u0438","Next period":"\u0421\u043B\u0435\u0434\u0435\u043D \u043F\u0435\u0440\u0438\u043E\u0434","Previous period":"\u041F\u0440\u0435\u0442\u0445\u043E\u0434\u0435\u043D \u043F\u0435\u0440\u0438\u043E\u0434",to:"\u0434\u043E","Full day- and multiple day events":"\u0426\u0435\u043B\u043E\u0434\u043D\u0435\u0432\u043D\u0438 \u0438 \u043F\u043E\u0432\u0435\u045C\u0435\u0434\u043D\u0435\u0432\u043D\u0438 \u043D\u0430\u0441\u0442\u0430\u043D\u0438","Link to {{n}} more events on {{date}}":"\u041B\u0438\u043D\u043A \u0434\u043E {{n}} \u043F\u043E\u0432\u0435\u045C\u0435 \u043D\u0430\u0441\u0442\u0430\u043D\u0438 \u043D\u0430 {{date}}","Link to 1 more event on {{date}}":"\u041B\u0438\u043D\u043A \u0434\u043E 1 \u043F\u043E\u0432\u0435\u045C\u0435 \u043D\u0430\u0441\u0442\u0430\u043D \u043D\u0430 {{date}}",CW:"\u041D\u0435\u0434\u0435\u043B\u0430 {{week}}"},Kl={Time:"\u0412\u0440\u0435\u043C\u0435",AM:"AM",PM:"PM",Cancel:"\u041E\u0442\u043A\u0430\u0436\u0438",OK:"\u0423 \u0440\u0435\u0434\u0443","Select time":"\u0418\u0437\u0431\u0435\u0440\u0438 \u0432\u0440\u0435\u043C\u0435"},Bl={...Hl,...Gl,...Kl},Xl={Date:"Tarih","MM/DD/YYYY":"GG/AA/YYYY","Next month":"Sonraki ay","Previous month":"\xD6nceki ay","Choose Date":"Tarih Se\xE7"},ql={Today:"Bug\xFCn",Month:"Ayl\u0131k",Week:"Haftal\u0131k",Day:"G\xFCnl\xFCk",List:"Liste","Select View":"G\xF6r\xFCn\xFCm Se\xE7","+ {{n}} events":"+ {{n}} etkinlikler","+ 1 event":"+ 1 etkinlik","No events":"Etkinlik yok","Next period":"Sonraki d\xF6nem","Previous period":"\xD6nceki d\xF6nem",to:"dan","Full day- and multiple day events":"T\xFCm g\xFCn ve \xE7oklu g\xFCn etkinlikleri","Link to {{n}} more events on {{date}}":"{{date}} tarihinde {{n}} etkinli\u011Fe ba\u011Flant\u0131","Link to 1 more event on {{date}}":"{{date}} tarihinde 1 etkinli\u011Fe ba\u011Flant\u0131",CW:"{{week}}. Hafta"},Jl={Time:"Zaman",AM:"\xD6\xD6",PM:"\xD6S",Cancel:"\u0130ptal",OK:"Tamam","Select time":"Zaman\u0131 se\xE7"},Zl={...Xl,...ql,...Jl},Ql={Date:"\u0414\u0430\u0442\u0430\u0441\u044B","MM/DD/YYYY":"\u0410\u0410/\u041A\u041A/\u0416\u0416\u0416\u0416","Next month":"\u041A\u0438\u0439\u0438\u043D\u043A\u0438 \u0430\u0439","Previous month":"\u04E8\u0442\u043A\u04E9\u043D \u0430\u0439","Choose Date":"\u041A\u04AF\u043D\u0434\u04AF \u0442\u0430\u043D\u0434\u0430\u04A3\u044B\u0437"},ec={Today:"\u0411\u04AF\u0433\u04AF\u043D",Month:"\u0410\u0439",Week:"\u0410\u043F\u0442\u0430",Day:"\u041A\u04AF\u043D",List:"\u0422\u0438\u0437\u043C\u0435","Select View":"\u041A\u04E9\u0440\u04AF\u043D\u04AF\u0448\u0442\u04AF \u0442\u0430\u043D\u0434\u0430\u04A3\u044B\u0437","+ {{n}} events":"+ {{n}} \u041E\u043A\u0443\u044F\u043B\u0430\u0440","+ 1 event":"+ 1 \u041E\u043A\u0443\u044F","No events":"\u041E\u043A\u0443\u044F \u0436\u043E\u043A","Next period":"\u041A\u0438\u0439\u0438\u043D\u043A\u0438 \u043C\u0435\u0437\u0433\u0438\u043B","Previous period":"\u04E8\u0442\u043A\u04E9\u043D \u043C\u0435\u0437\u0433\u0438\u043B",to:"\u0447\u0435\u0439\u0438\u043D","Full day- and multiple day events":"\u041A\u04AF\u043D \u0431\u043E\u044E \u0436\u0430\u043D\u0430 \u0431\u0438\u0440 \u043D\u0435\u0447\u0435 \u043A\u04AF\u043D \u043A\u0430\u0442\u0430\u0440\u044B \u043C\u0435\u043D\u0435\u043D \u0431\u043E\u043B\u0433\u043E\u043D \u043E\u043A\u0443\u044F\u043B\u0430\u0440","Link to {{n}} more events on {{date}}":"{{date}} \u043A\u04AF\u043D\u04AF\u043D\u0434\u04E9 {{n}} \u043E\u043A\u0443\u044F\u0433\u0430 \u0431\u0430\u0439\u043B\u0430\u043D\u044B\u0448","Link to 1 more event on {{date}}":"{{date}} \u043A\u04AF\u043D\u04AF\u043D\u0434\u04E9 1 \u043E\u043A\u0443\u044F\u0433\u0430 \u0431\u0430\u0439\u043B\u0430\u043D\u044B\u0448",CW:"\u0410\u043F\u0442\u0430 {{week}}"},tc={Time:"\u0423\u0431\u0430\u043A\u0442\u044B",AM:"AM",PM:"PM",Cancel:"\u0411\u043E\u043B\u0431\u043E\u0439",OK:"\u041E\u043E\u0431\u0430","Select time":"\u0423\u0431\u0430\u043A\u0442\u044B \u0442\u0430\u043D\u0434\u0430\u04A3\u044B\u0437"},nc={...Ql,...ec,...tc},rc={Date:"Tanggal","MM/DD/YYYY":"DD.MM.YYYY","Next month":"Bulan depan","Previous month":"Bulan sebelumnya","Choose Date":"Pilih tanggal"},ic={Today:"Hari Ini",Month:"Bulan",Week:"Minggu",Day:"Hari",List:"Daftar","Select View":"Pilih tampilan","+ {{n}} events":"+ {{n}} Acara","+ 1 event":"+ 1 Acara","No events":"Tidak ada acara","Next period":"Periode selanjutnya","Previous period":"Periode sebelumnya",to:"sampai","Full day- and multiple day events":"Sepanjang hari dan acara beberapa hari ","Link to {{n}} more events on {{date}}":"Tautan ke {{n}} acara lainnya pada {{date}}","Link to 1 more event on {{date}}":"Tautan ke 1 acara lainnya pada {{date}}",CW:"Minggu {{week}}"},ac={Time:"Waktu",AM:"AM",PM:"PM",Cancel:"Batalkan",OK:"OK","Select time":"Pilih waktu"},oc={...rc,...ic,...ac},sc={Date:"Datum","MM/DD/YYYY":"DD/MM/YYYY","Next month":"Dal\u0161\xED m\u011Bs\xEDc","Previous month":"P\u0159edchoz\xED m\u011Bs\xEDc","Choose Date":"Vyberte datum"},lc={Today:"Dnes",Month:"M\u011Bs\xEDc",Week:"T\xFDden",Day:"Den",List:"Seznam","Select View":"Vyberte zobrazen\xED","+ {{n}} events":"+ {{n}} ud\xE1losti","+ 1 event":"+ 1 ud\xE1lost","No events":"\u017D\xE1dn\xE9 ud\xE1losti","Next period":"P\u0159\xED\u0161t\xED obdob\xED","Previous period":"P\u0159edchoz\xED obdob\xED",to:"do","Full day- and multiple day events":"Celodenn\xED a v\xEDcedenn\xED ud\xE1losti","Link to {{n}} more events on {{date}}":"Odkaz na {{n}} dal\u0161\xEDch ud\xE1lost\xED dne {{date}}","Link to 1 more event on {{date}}":"Odkaz na 1 dal\u0161\xED ud\xE1lost dne {{date}}",CW:"T\xFDden {{week}}"},cc={Time:"\u010Cas",AM:"Dopoledne",PM:"Odpoledne",Cancel:"Zru\u0161it",OK:"OK","Select time":"Vyberte \u010Das"},uc={...sc,...lc,...cc},dc={Date:"Kuup\xE4ev","MM/DD/YYYY":"PP.KK.AAAA","Next month":"J\xE4rgmine kuu","Previous month":"Eelmine kuu","Choose Date":"Vali kuup\xE4ev"},hc={Today:"T\xE4na",Month:"Kuu",Week:"N\xE4dal",Day:"P\xE4ev",List:"Nimekiri","Select View":"Vali vaade","+ {{n}} events":"+ {{n}} s\xFCndmused","+ 1 event":"+ 1 s\xFCndmus","No events":"Pole s\xFCndmusi","Next period":"J\xE4rgmine periood","Previous period":"Eelmine periood",to:"kuni","Full day- and multiple day events":"T\xE4isp\xE4eva- ja mitmep\xE4evas\xFCndmused","Link to {{n}} more events on {{date}}":"Link {{n}} rohkematele s\xFCndmustele kuup\xE4eval {{date}}","Link to 1 more event on {{date}}":"Link \xFChele lisas\xFCndmusele kuup\xE4eval {{date}}",CW:"N\xE4dala number {{week}}"},vc={Time:"Aeg",AM:"AM",PM:"PM",Cancel:"Loobu",OK:"OK","Select time":"Vali aeg"},fc={...dc,...hc,...vc},pc={Date:"\u0414\u0430\u0442\u0430","MM/DD/YYYY":"\u041C\u041C/\u0414\u0414/\u0420\u0420\u0420\u0420","Next month":"\u041D\u0430\u0441\u0442\u0443\u043F\u043D\u0438\u0439 \u043C\u0456\u0441\u044F\u0446\u044C","Previous month":"\u041C\u0438\u043D\u0443\u043B\u0438\u0439 \u043C\u0456\u0441\u044F\u0446\u044C","Choose Date":"\u0412\u0438\u0431\u0435\u0440\u0456\u0442\u044C \u0434\u0430\u0442\u0443"},mc={Today:"\u0421\u044C\u043E\u0433\u043E\u0434\u043D\u0456",Month:"\u041C\u0456\u0441\u044F\u0446\u044C",Week:"\u0422\u0438\u0436\u0434\u0435\u043D\u044C",Day:"\u0414\u0435\u043D\u044C",List:"\u0421\u043F\u0438\u0441\u043E\u043A","Select View":"\u0412\u0438\u0431\u0435\u0440\u0456\u0442\u044C \u0432\u0438\u0433\u043B\u044F\u0434","+ {{n}} events":"+ {{n}} \u043F\u043E\u0434\u0456\u0457","+ 1 event":"+ 1 \u043F\u043E\u0434\u0456\u044F","No events":"\u041D\u0435\u043C\u0430\u0454 \u043F\u043E\u0434\u0456\u0439","Next period":"\u041D\u0430\u0441\u0442\u0443\u043F\u043D\u0438\u0439 \u043F\u0435\u0440\u0456\u043E\u0434","Previous period":"\u041C\u0438\u043D\u0443\u043B\u0438\u0439 \u043F\u0435\u0440\u0456\u043E\u0434",to:"\u043F\u043E","Full day- and multiple day events":"\u041F\u043E\u0434\u0456\u0457 \u043D\u0430 \u0446\u0456\u043B\u0438\u0439 \u0434\u0435\u043D\u044C \u0456 \u043A\u0456\u043B\u044C\u043A\u0430 \u0434\u043D\u0456\u0432 \u043F\u043E\u0441\u043F\u0456\u043B\u044C","Link to {{n}} more events on {{date}}":"\u041F\u043E\u0441\u0438\u043B\u0430\u043D\u043D\u044F \u043D\u0430 {{n}} \u0434\u043E\u0434\u0430\u0442\u043A\u043E\u0432\u0456 \u043F\u043E\u0434\u0456\u0457 \u043D\u0430 {{date}}","Link to 1 more event on {{date}}":"\u041F\u043E\u0441\u0438\u043B\u0430\u043D\u043D\u044F \u043D\u0430 1 \u0434\u043E\u0434\u0430\u0442\u043A\u043E\u0432\u0443 \u043F\u043E\u0434\u0456\u044E \u043D\u0430 {{date}}",CW:"\u0422\u0438\u0436\u0434\u0435\u043D\u044C {{week}}"},_c={Time:"\u0427\u0430\u0441",AM:"AM",PM:"PM",Cancel:"\u0421\u043A\u0430\u0441\u0443\u0432\u0430\u0442\u0438",OK:"\u0413\u0430\u0440\u0430\u0437\u0434","Select time":"\u0412\u0438\u0431\u0435\u0440\u0456\u0442\u044C \u0447\u0430\u0441"},gc={...pc,...mc,..._c},bc={Date:"Datum","MM/DD/YYYY":"DD/MM/YYYY","Next month":"Slede\u0107i mesec","Previous month":"Prethodni mesec","Choose Date":"Izaberite datum"},yc={Today:"Danas",Month:"Mesec",Week:"Nedelja",Day:"Dan",List:"Lista","Select View":"Odaberite pregled","+ {{n}} events":"+ {{n}} Doga\u0111aji","+ 1 event":"+ 1 Doga\u0111aj","No events":"Nema doga\u0111aja","Next period":"Naredni period","Previous period":"Prethodni period",to:"do","Full day- and multiple day events":"Celodnevni i vi\u0161ednevni doga\u0111aji","Link to {{n}} more events on {{date}}":"Link do jo\u0161 {{n}} doga\u0111aja na {{date}}","Link to 1 more event on {{date}}":"Link do jednog doga\u0111aja na {{date}}",CW:"Nedelja {{week}}"},wc={Time:"Vrijeme",AM:"AM",PM:"PM",Cancel:"Otka\u017Ei",OK:"U redu","Select time":"Odaberi vrijeme"},xc={...bc,...yc,...wc},Dc={Date:"Data","MM/DD/YYYY":"DD/MM/YYYY","Next month":"Seg\xFCent mes","Previous month":"Mes anterior","Choose Date":"Selecciona una data"},kc={Today:"Avui",Month:"Mes",Week:"Setmana",Day:"Dia",List:"Llista","Select View":"Selecciona una vista","+ {{n}} events":"+ {{n}} Esdeveniments","+ 1 event":"+ 1 Esdeveniment","No events":"Sense esdeveniments","Next period":"Seg\xFCent per\xEDode","Previous period":"Per\xEDode anterior",to:"a","Full day- and multiple day events":"Esdeveniments de dia complet i de m\xFAltiples dies","Link to {{n}} more events on {{date}}":"Enlla\xE7 a {{n}} esdeveniments m\xE9s el {{date}}","Link to 1 more event on {{date}}":"Enlla\xE7 a 1 esdeveniment m\xE9s el {{date}}",CW:"Setmana {{week}}"},Pc={Time:"Hora",AM:"AM",PM:"PM",Cancel:"Cancel\xB7lar",OK:"Acceptar","Select time":"Selecciona una hora"},Sc={...Dc,...kc,...Pc},Ec={Date:"\u0414\u0430\u0442\u0443\u043C","MM/DD/YYYY":"DD/MM/YYYY","Next month":"\u0421\u043B\u0435\u0434\u0435\u045B\u0438 \u043C\u0435\u0441\u0435\u0446","Previous month":"\u041F\u0440\u0435\u0442\u0445\u043E\u0434\u043D\u0438 \u043C\u0435\u0441\u0435\u0446","Choose Date":"\u0418\u0437\u0430\u0431\u0435\u0440\u0438\u0442\u0435 \u0414\u0430\u0442\u0443\u043C"},Cc={Today:"\u0414\u0430\u043D\u0430\u0441",Month:"\u041C\u0435\u0441\u0435\u0446",Week:"\u041D\u0435\u0434\u0435\u0459\u0430",Day:"\u0414\u0430\u043D",List:"\u041B\u0438\u0441\u0442\u0430","Select View":"\u0418\u0437\u0430\u0431\u0435\u0440\u0438\u0442\u0435 \u043F\u0440\u0435\u0433\u043B\u0435\u0434","+ {{n}} events":"+ {{n}} \u0414\u043E\u0433\u0430\u0452\u0430\u0458\u0438","+ 1 event":"+ 1 \u0414\u043E\u0433\u0430\u0452\u0430\u0458","No events":"\u041D\u0435\u043C\u0430 \u0434\u043E\u0433\u0430\u0452\u0430\u0458\u0430","Next period":"\u0421\u043B\u0435\u0434\u0435\u045B\u0438 \u043F\u0435\u0440\u0438\u043E\u0434","Previous period":"\u041F\u0440\u0435\u0442\u0445\u043E\u0434\u043D\u0438 \u043F\u0435\u0440\u0438\u043E\u0434",to:"\u0434\u0430","Full day- and multiple day events":"\u0426\u0435\u043B\u043E\u0434\u043D\u0435\u0432\u043D\u0438 \u0438 \u0432\u0438\u0448\u0435\u0434\u043D\u0435\u0432\u043D\u0438 \u0434\u043E\u0433\u0430\u0452\u0430\u0458\u0438","Link to {{n}} more events on {{date}}":"\u041B\u0438\u043D\u043A \u0434\u043E \u0458\u043E\u0448 {{n}} \u0434\u043E\u0433\u0430\u0452\u0430\u0458\u0430 \u043D\u0430 {{date}}","Link to 1 more event on {{date}}":"\u041B\u0438\u043D\u043A \u0434\u043E \u0458\u043E\u0448 1 \u0434\u043E\u0433\u0430\u0452\u0430\u0458\u0430 {{date}}",CW:"\u041D\u0435\u0434\u0435\u0459\u0430 {{week}}"},Mc={Time:"\u0412\u0440\u0435\u043C\u0435",AM:"AM",PM:"PM",Cancel:"\u041E\u0442\u043A\u0430\u0436\u0438",OK:"\u0423 \u0440\u0435\u0434\u0443","Select time":"\u0418\u0437\u0430\u0431\u0435\u0440\u0438 \u0432\u0440\u0435\u043C\u0435"},Oc={...Ec,...Cc,...Mc},Tc={Date:"Data","MM/DD/YYYY":"MMMM-MM-DD","Next month":"Kitas m\u0117nuo","Previous month":"Ankstesnis m\u0117nuo","Choose Date":"Pasirinkite dat\u0105"},Nc={Today:"\u0160iandien",Month:"M\u0117nuo",Week:"Savait\u0117",Day:"Diena",List:"S\u0105ra\u0161as","Select View":"Pasirinkite vaizd\u0105","+ {{n}} events":"+ {{n}} \u012Fvykiai","+ 1 event":"+ 1 \u012Fvykis","No events":"\u012Evyki\u0173 n\u0117ra","Next period":"Kitas laikotarpis","Previous period":"Ankstesnis laikotarpis",to:"iki","Full day- and multiple day events":"Visos dienos ir keli\u0173 dien\u0173 \u012Fvykiai","Link to {{n}} more events on {{date}}":"Nuoroda \u012F dar {{n}} \u012Fvykius {{date}}","Link to 1 more event on {{date}}":"Nuoroda \u012F dar 1 vien\u0105 \u012Fvyk\u012F {{date}}",CW:"{{week}} savait\u0117"},Ac={Time:"Laikas",AM:"AM",PM:"PM",Cancel:"At\u0161aukti",OK:"Gerai","Select time":"Pasirinkite laik\u0105"},Yc={...Tc,...Nc,...Ac},jc={Date:"Datum","MM/DD/YYYY":"DD/MM/YYYY","Next month":"Sljede\u0107i mjesec","Previous month":"Prethodni mjesec","Choose Date":"Izaberite datum"},Lc={Today:"Danas",Month:"Mjesec",Week:"Nedjelja",Day:"Dan",List:"Lista","Select View":"Odaberite pregled","+ {{n}} events":"+ {{n}} Doga\u0111aji","+ 1 event":"+ 1 Doga\u0111aj","No events":"Nema doga\u0111aja","Next period":"Sljede\u0107i period","Previous period":"Prethodni period",to:"do","Full day- and multiple day events":"Cjelodnevni i vi\u0161ednevni doga\u0111aji","Link to {{n}} more events on {{date}}":"Link do jo\u0161 {{n}} doga\u0111aja na {{date}}","Link to 1 more event on {{date}}":"Link do jo\u0161 jednog doga\u0111aja na {{date}}",CW:"{{week}}. tjedan"},Ic={Time:"Vrijeme",AM:"AM",PM:"PM",Cancel:"Otka\u017Ei",OK:"U redu","Select time":"Odaberi vrijeme"},Rc={...jc,...Lc,...Ic},Fc={Date:"Datum","MM/DD/YYYY":"MM.DD.YYYY","Next month":"Naslednji mesec","Previous month":"Prej\u0161nji mesec","Choose Date":"Izberi datum"},Wc={Today:"Danes",Month:"Mesec",Week:"Teden",Day:"Dan",List:"Seznam","Select View":"Izberi pogled","+ {{n}} events":"+ {{n}} dogodki","+ 1 event":"+ 1 dogodek","No events":"Ni dogodkov","Next period":"Naslednji dogodek","Previous period":"Prej\u0161nji dogodek",to:"do","Full day- and multiple day events":"Celodnevni in ve\u010Ddnevni dogodki","Link to {{n}} more events on {{date}}":"Povezava do {{n}} drugih dogodkov dne {{date}}","Link to 1 more event on {{date}}":"Povezava do \u0161e enega dogodka dne {{date}}",CW:"Teden {{week}}"},$c={Time:"\u010Cas",AM:"AM",PM:"PM",Cancel:"Prekli\u010Di",OK:"V redu","Select time":"Izberite \u010Das"},Uc={...Fc,...Wc,...$c},Vc={Date:"P\xE4iv\xE4m\xE4\xE4r\xE4","MM/DD/YYYY":"VVVV-KK-PP","Next month":"Seuraava kuukausi","Previous month":"Edellinen kuukausi","Choose Date":"Valitse p\xE4iv\xE4m\xE4\xE4r\xE4"},zc={Today:"T\xE4n\xE4\xE4n",Month:"Kuukausi",Week:"Viikko",Day:"P\xE4iv\xE4",List:"Lista","Select View":"Valitse n\xE4kym\xE4","+ {{n}} events":"+ {{n}} tapahtumaa","+ 1 event":"+ 1 tapahtuma","No events":"Ei tapahtumia","Next period":"Seuraava ajanjakso","Previous period":"Edellinen ajanjakso",to:"-","Full day- and multiple day events":"Koko ja usean p\xE4iv\xE4n tapahtumat","Link to {{n}} more events on {{date}}":"Linkki {{n}} lis\xE4tapahtumaan p\xE4iv\xE4m\xE4\xE4r\xE4ll\xE4 {{date}}","Link to 1 more event on {{date}}":"Linkki 1 lis\xE4tapahtumaan p\xE4iv\xE4m\xE4\xE4r\xE4ll\xE4 {{date}}",CW:"Viikko {{week}}"},Hc={Time:"Aika",AM:"ap.",PM:"ip.",Cancel:"Peruuta",OK:"OK","Select time":"Valitse aika"},Gc={...Vc,...zc,...Hc},Kc={Date:"Data","MM/DD/YYYY":"LL/ZZ/AAAA","Next month":"Luna urm\u0103toare","Previous month":"Luna anterioar\u0103","Choose Date":"Alege data"},Bc={Today:"Ast\u0103zi",Month:"Lun\u0103",Week:"S\u0103pt\u0103m\xE2n\u0103",Day:"Zi",List:"List\u0103","Select View":"Selecteaz\u0103 vizualizarea","+ {{n}} events":"+ {{n}} evenimente","+ 1 event":"+ 1 eveniment","No events":"F\u0103r\u0103 evenimente","Next period":"Perioada urm\u0103toare","Previous period":"Perioada anterioar\u0103",to:"p\xE2n\u0103 la","Full day- and multiple day events":"Evenimente pe durata \xEEntregii zile \u0219i pe durata mai multor zile","Link to {{n}} more events on {{date}}":"Link c\u0103tre {{n}} evenimente suplimentare pe {{date}}","Link to 1 more event on {{date}}":"Link c\u0103tre 1 eveniment suplimentar pe {{date}}",CW:"S\u0103pt\u0103m\xE2na {{week}}"},Xc={Time:"Timp",AM:"AM",PM:"PM",Cancel:"Anuleaz\u0103",OK:"OK","Select time":"Selecta\u021Bi ora"},qc={...Kc,...Bc,...Xc},Jc={Date:"\u062A\u0627\u0631\u06CC\u062E","MM/DD/YYYY":"MM/DD/YYYY","Next month":"\u0645\u0627\u0647 \u0628\u0639\u062F","Previous month":"\u0645\u0627\u0647 \u0642\u0628\u0644","Choose Date":"\u0627\u0646\u062A\u062E\u0627\u0628 \u062A\u0627\u0631\u06CC\u062E"},Zc={Today:"\u0627\u0645\u0631\u0648\u0632",Month:"\u0645\u0627\u0647",Week:"\u0647\u0641\u062A\u0647",Day:"\u0631\u0648\u0632",List:"\u0644\u06CC\u0633\u062A","Select View":"\u0627\u0646\u062A\u062E\u0627\u0628 \u0646\u0645\u0627","+ {{n}} events":"+ {{n}} \u0631\u0648\u06CC\u062F\u0627\u062F\u0647\u0627","+ 1 event":"+ 1 \u0631\u0648\u06CC\u062F\u0627\u062F","No events":"\u0631\u0648\u06CC\u062F\u0627\u062F\u06CC \u0648\u062C\u0648\u062F \u0646\u062F\u0627\u0631\u062F","Next period":"\u062F\u0648\u0631\u0647 \u0628\u0639\u062F\u06CC","Previous period":"\u062F\u0648\u0631\u0647 \u0642\u0628\u0644\u06CC",to:"\u062A\u0627","Full day- and multiple day events":"\u0631\u0648\u06CC\u062F\u0627\u062F\u0647\u0627\u06CC \u062A\u0645\u0627\u0645 \u0631\u0648\u0632 \u0648 \u0686\u0646\u062F \u0631\u0648\u0632\u0647","Link to {{n}} more events on {{date}}":"\u0644\u06CC\u0646\u06A9 \u0628\u0647 {{n}} \u0631\u0648\u06CC\u062F\u0627\u062F \u0628\u06CC\u0634\u062A\u0631 \u062F\u0631 \u062A\u0627\u0631\u06CC\u062E {{date}}","Link to 1 more event on {{date}}":"\u0644\u06CC\u0646\u06A9 \u0628\u0647 1 \u0631\u0648\u06CC\u062F\u0627\u062F \u0628\u06CC\u0634\u062A\u0631 \u062F\u0631 \u062A\u0627\u0631\u06CC\u062E {{date}}",CW:"\u0647\u0641\u062A\u0647 {{week}}"},Qc={Time:"\u0632\u0645\u0627\u0646",AM:"\u0642.\u0638",PM:"\u0628.\u0638",Cancel:"\u0644\u063A\u0648",OK:"\u062A\u0627\u06CC\u06CC\u062F","Select time":"\u0627\u0646\u062A\u062E\u0627\u0628 \u0632\u0645\u0627\u0646"},eu={...Jc,...Zc,...Qc},_n=class extends Error{constructor(t){super(`Invalid locale: ${t}`)}},tu=(e,t)=>(n,r)=>{if(!/^[a-z]{2}-[A-Z]{2}$/.test(e.value)&&e.value!=="sr-Latn-RS")throw new _n(e.value);let i=e.value.replaceAll("-",""),a=t.value[i];if(!a)return n;let o=a[n]||n;return Object.keys(r||{}).forEach(s=>{let l=String(r==null?void 0:r[s]);l&&(o=o.replace(`{{${s}}}`,l))}),o},nu={Date:"\u05EA\u05B7\u05D0\u05B2\u05E8\u05B4\u05D9\u05DA","MM/DD/YYYY":"MM/DD/YYYY","Next month":"\u05D7\u05D5\u05D3\u05E9 \u05D4\u05D1\u05D0","Previous month":"\u05D7\u05D5\u05D3\u05E9 \u05E7\u05D5\u05D3\u05DD","Choose Date":"\u05D1\u05D7\u05E8 \u05EA\u05D0\u05E8\u05D9\u05DA"},ru={Today:"\u05D4\u05B7\u05D9\u05D5\u05B9\u05DD",Month:"\u05D7\u05D5\u05B9\u05D3\u05B6\u05E9\u05C1",Week:"\u05E9\u05C1\u05B8\u05D1\u05D5\u05BC\u05E2\u05B7",Day:"\u05D9\u05D5\u05B9\u05DD",List:"\u05E8\u05E9\u05D9\u05DE\u05D4","Select View":"\u05D1\u05D7\u05E8 \u05EA\u05E6\u05D5\u05D2\u05D4","+ {{n}} events":"+ {{n}} \u05D0\u05D9\u05E8\u05D5\u05E2\u05D9\u05DD","+ 1 event":"+ 1 \u05D0\u05D9\u05E8\u05D5\u05E2","No events":"\u05D0\u05D9\u05DF \u05D0\u05D9\u05E8\u05D5\u05E2\u05D9\u05DD","Next period":"\u05EA\u05E7\u05D5\u05E4\u05D4 \u05D4\u05D1\u05D0\u05D4","Previous period":"\u05EA\u05E7\u05D5\u05E4\u05D4 \u05E7\u05D5\u05D3\u05DE\u05EA",to:"\u05E2\u05D3","Full day- and multiple day events":"\u05D0\u05D9\u05E8\u05D5\u05E2\u05D9\u05DD \u05DC\u05DB\u05DC \u05D4\u05D9\u05D5\u05DD \u05D5\u05DC\u05DE\u05E1\u05E4\u05E8 \u05D9\u05DE\u05D9\u05DD","Link to {{n}} more events on {{date}}":"\u05E7\u05D9\u05E9\u05D5\u05E8 \u05DC\u05E2\u05D5\u05D3 {{n}} \u05D0\u05D9\u05E8\u05D5\u05E2\u05D9\u05DD \u05D1-{{date}}","Link to 1 more event on {{date}}":"\u05E7\u05D9\u05E9\u05D5\u05E8 \u05DC\u05D0\u05D9\u05E8\u05D5\u05E2 \u05E0\u05D5\u05E1\u05E3 \u05D1-{{date}}",CW:"{{week}} \u05E9\u05C1\u05B8\u05D1\u05D5\u05BC\u05E2\u05B7"},iu={Time:"\u05E9\u05E2\u05D4",AM:'\u05DC\u05E4\u05E0\u05D4"\u05E6',PM:'\u05D0\u05D7\u05D4"\u05E6',Cancel:"\u05D1\u05D9\u05D8\u05D5\u05DC",OK:"\u05D0\u05D9\u05E9\u05D5\u05E8","Select time":"\u05D1\u05D7\u05E8 \u05E9\u05E2\u05D4"},au={...nu,...ru,...iu},ou={deDE:Ys,enUS:Rs,itIT:Us,enGB:Gs,svSE:qs,zhCN:el,zhTW:il,jaJP:ll,ruRU:hl,koKR:ml,frFR:yl,daDK:kl,mkMK:Bl,plPL:Cl,heIL:au,esES:Nl,nlNL:Ll,ptBR:Wl,skSK:zl,trTR:Zl,kyKG:nc,idID:oc,csCZ:uc,etEE:fc,ukUA:gc,caES:Sc,srLatnRS:xc,srRS:Oc,ltLT:Yc,hrHR:Rc,slSI:Uc,fiFI:Gc,roRO:qc,faIR:eu,arEG:Os},gn=class{constructor(t){Object.defineProperty(this,"config",{enumerable:!0,configurable:!0,writable:!0,value:t})}setLight(){Object.entries(this.config.calendars.value||{}).forEach(([t,n])=>{if(!n.lightColors){console.warn(`No light colors defined for calendar ${t}`);return}this.setColors(n.colorName,n.lightColors)})}setDark(){Object.entries(this.config.calendars.value||{}).forEach(([t,n])=>{if(!n.darkColors){console.warn(`No dark colors defined for calendar ${t}`);return}this.setColors(n.colorName,n.darkColors)})}setColors(t,n){document.documentElement.style.setProperty(`--sx-color-${t}`,n.main),document.documentElement.style.setProperty(`--sx-color-${t}-container`,n.container),document.documentElement.style.setProperty(`--sx-color-on-${t}-container`,n.onContainer)}},su=(e,t,n)=>{var r;let i=C(((r=e.views.value.find(v=>v.name===e.defaultView))===null||r===void 0?void 0:r.name)||e.views.value[0].name),a=Pe(()=>i.value),o=C(null),s=!1,l=null,c=v=>{if(!s)return s=!0;e.callbacks.onRangeUpdate&&v.value&&e.callbacks.onRangeUpdate(v.value);let m=l;v.value&&((m==null?void 0:m.start)===v.value.start&&(m==null?void 0:m.end)===v.value.end||Object.values(e.plugins||{}).forEach(f=>{var _;(_=f==null?void 0:f.onRangeUpdate)===null||_===void 0||_.call(f,v.value),l=v.value}))};Z(()=>{o.value&&c(o)});let h=v=>{var m,f;let g=e.views.value.find(b=>b.name===i.value).setDateRange({calendarConfig:e,date:v,range:o,timeUnitsImpl:t});g.start===((m=o.value)===null||m===void 0?void 0:m.start)&&g.end===((f=o.value)===null||f===void 0?void 0:f.end)||(o.value=g)};h(n||F(new Date));let p=C(void 0),u=C(e.isDark.value||!1);return Z(()=>{let v=new gn(e);u.value?v.setDark():v.setLight()}),{view:a,isDark:u,setRange:h,range:o,isCalendarSmall:p,setView:(v,m)=>{Oe(()=>{i.value=v,h(m)})}}},lu=(e,t,n)=>{let r=C(e.map(a=>Dt(a,n))),i=C(void 0);return{list:r,filterPredicate:i,backgroundEvents:C(t)}},cu=(e,t,n)=>e===t?2400:n?2400-e+t:t-e,qi=()=>{let e=document.querySelector("html");return e&&e.getAttribute("dir")==="rtl"?"rtl":"ltr"},bn=class{constructor(t=St,n=On,r=G.Week,i=[],a=Wi,o,s={},l={},c=!1,h=!0,p={},u={},v=void 0,m=void 0,f={nEventsPerDay:4},_=void 0,g={},b=!1){Object.defineProperty(this,"defaultView",{enumerable:!0,configurable:!0,writable:!0,value:r}),Object.defineProperty(this,"plugins",{enumerable:!0,configurable:!0,writable:!0,value:l}),Object.defineProperty(this,"isResponsive",{enumerable:!0,configurable:!0,writable:!0,value:h}),Object.defineProperty(this,"callbacks",{enumerable:!0,configurable:!0,writable:!0,value:p}),Object.defineProperty(this,"_customComponentFns",{enumerable:!0,configurable:!0,writable:!0,value:u}),Object.defineProperty(this,"firstDayOfWeek",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"views",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"dayBoundaries",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"weekOptions",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"calendars",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"isDark",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"minDate",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"maxDate",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"monthGridOptions",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"locale",{enumerable:!0,configurable:!0,writable:!0,value:C(St)}),Object.defineProperty(this,"theme",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"translations",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"showWeekNumbers",{enumerable:!0,configurable:!0,writable:!0,value:C(!1)}),Object.defineProperty(this,"direction",{enumerable:!0,configurable:!0,writable:!0,value:"ltr"}),this.locale=C(t),this.firstDayOfWeek=C(n),this.views=C(i),this.dayBoundaries=C(a),this.weekOptions=C(o),this.calendars=C(s),this.isDark=C(c),this.minDate=C(v),this.maxDate=C(m),this.monthGridOptions=C(f),this.theme=_,this.translations=C(g),this.showWeekNumbers=C(b),this.direction=qi()}get isHybridDay(){return this.dayBoundaries.value.start>this.dayBoundaries.value.end||this.dayBoundaries.value.start!==0&&this.dayBoundaries.value.start===this.dayBoundaries.value.end}get timePointsPerDay(){return cu(this.dayBoundaries.value.start,this.dayBoundaries.value.end,this.isHybridDay)}},yn=class{constructor(){Object.defineProperty(this,"locale",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"firstDayOfWeek",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"defaultView",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"views",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"dayBoundaries",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"weekOptions",{enumerable:!0,configurable:!0,writable:!0,value:{gridHeight:is,nDays:7,eventWidth:100,timeAxisFormatOptions:{hour:"numeric"},eventOverlap:!0}}),Object.defineProperty(this,"monthGridOptions",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"calendars",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"plugins",{enumerable:!0,configurable:!0,writable:!0,value:{}}),Object.defineProperty(this,"isDark",{enumerable:!0,configurable:!0,writable:!0,value:!1}),Object.defineProperty(this,"isResponsive",{enumerable:!0,configurable:!0,writable:!0,value:!0}),Object.defineProperty(this,"callbacks",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"minDate",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"maxDate",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"backgroundEvents",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"theme",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"translations",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"showWeekNumbers",{enumerable:!0,configurable:!0,writable:!0,value:void 0})}build(){return new bn(this.locale||St,typeof this.firstDayOfWeek=="number"?this.firstDayOfWeek:On,this.defaultView||G.Week,this.views||[],this.dayBoundaries||Wi,this.weekOptions,this.calendars,this.plugins,this.isDark,this.isResponsive,this.callbacks,{},this.minDate,this.maxDate,this.monthGridOptions,this.theme,this.translations,this.showWeekNumbers)}withLocale(t){return this.locale=t,this}withTranslations(t){return this.translations=t,this}withFirstDayOfWeek(t){return this.firstDayOfWeek=t,this}withDefaultView(t){return this.defaultView=t,this}withViews(t){return this.views=t,this}withDayBoundaries(t){return t?(this.dayBoundaries={start:oe(t.start),end:oe(t.end)},this):this}withWeekOptions(t){return this.weekOptions={...this.weekOptions,...t},this}withCalendars(t){return this.calendars=t,this}withPlugins(t){return t?(t.forEach(n=>{this.plugins[n.name]=n}),this):this}withIsDark(t){return this.isDark=t,this}withIsResponsive(t){return this.isResponsive=t,this}withCallbacks(t){return this.callbacks=t,this}withMinDate(t){return this.minDate=t,this}withMaxDate(t){return this.maxDate=t,this}withMonthGridOptions(t){return this.monthGridOptions=t,this}withBackgroundEvents(t){return this.backgroundEvents=t,this}withTheme(t){return this.theme=t,this}withWeekNumbers(t){return this.showWeekNumbers=t,this}},uu=(e,t)=>new yn().withLocale(e.locale).withFirstDayOfWeek(e.firstDayOfWeek).withDefaultView(e.defaultView).withViews(e.views).withDayBoundaries(e.dayBoundaries).withWeekOptions(e.weekOptions).withCalendars(e.calendars).withPlugins(t).withIsDark(e.isDark).withIsResponsive(e.isResponsive).withCallbacks(e.callbacks).withMinDate(e.minDate).withMaxDate(e.maxDate).withMonthGridOptions(e.monthGridOptions).withBackgroundEvents(e.backgroundEvents).withTheme(e.theme).withTranslations(e.translations||ou).withWeekNumbers(e.showWeekNumbers).build(),wn;(function(e){e[e.JANUARY=0]="JANUARY",e[e.FEBRUARY=1]="FEBRUARY",e[e.MARCH=2]="MARCH",e[e.APRIL=3]="APRIL",e[e.MAY=4]="MAY",e[e.JUNE=5]="JUNE",e[e.JULY=6]="JULY",e[e.AUGUST=7]="AUGUST",e[e.SEPTEMBER=8]="SEPTEMBER",e[e.OCTOBER=9]="OCTOBER",e[e.NOVEMBER=10]="NOVEMBER",e[e.DECEMBER=11]="DECEMBER"})(wn||(wn={}));var qe=class extends Error{constructor(){super("Year zero does not exist in the Gregorian calendar.")}},xn=class extends Date{constructor(t,n,r){if(super(t,n,r),t===0)throw new qe;this.setFullYear(t)}get year(){return this.getFullYear()}get month(){return this.getMonth()}get date(){return this.getDate()}},Dn=class{constructor(t){Object.defineProperty(this,"config",{enumerable:!0,configurable:!0,writable:!0,value:t})}get firstDayOfWeek(){return this.config.firstDayOfWeek.value}set firstDayOfWeek(t){this.config.firstDayOfWeek.value=t}getMonth(t,n){if(t===0)throw new qe;let r=new Date(t,n,1),i=new Date(t,n+1,0),a=[];for(let o=new Date(r);o<=i;o.setDate(o.getDate()+1))a.push(new Date(o));return a}getMonthWithTrailingAndLeadingDays(t,n){if(t===0)throw new qe;let r=new Date(t,n,1),i=[this.getWeekFor(r)],a=!0,o=i[0][0];for(;a;){let s=new Date(o.getFullYear(),o.getMonth(),o.getDate()+7);s.getMonth()===n?(i.push(this.getWeekFor(s)),o=s):a=!1}return i}getWeekFor(t){let n=[this.getFirstDateOfWeek(t)];for(;n.length<7;){let r=n[n.length-1],i=new Date(r);i.setDate(r.getDate()+1),n.push(i)}return n}getMonthsFor(t){return Object.values(wn).filter(n=>!isNaN(Number(n))).map(n=>new xn(t,Number(n),1))}getFirstDateOfWeek(t){let n=t.getDay()-this.firstDayOfWeek,r=t;return n===0||(n>0?r.setDate(t.getDate()-n):r.setDate(t.getDate()-(7+n))),r}},kn=class{constructor(){Object.defineProperty(this,"config",{enumerable:!0,configurable:!0,writable:!0,value:void 0})}build(){return new Dn(this.config)}withConfig(t){return this.config=t,this}},du=e=>new kn().withConfig(e).build(),Je;(function(e){e.TOP_START="top-start",e.TOP_END="top-end",e.BOTTOM_START="bottom-start",e.BOTTOM_END="bottom-end"})(Je||(Je={}));var Pn=class{constructor(t=St,n=On,r=F(new Date(1970,0,1)),i=F(new Date(new Date().getFullYear()+50,11,31)),a=Je.BOTTOM_START,o={},s={},l,c,h,p){Object.defineProperty(this,"min",{enumerable:!0,configurable:!0,writable:!0,value:r}),Object.defineProperty(this,"max",{enumerable:!0,configurable:!0,writable:!0,value:i}),Object.defineProperty(this,"placement",{enumerable:!0,configurable:!0,writable:!0,value:a}),Object.defineProperty(this,"listeners",{enumerable:!0,configurable:!0,writable:!0,value:o}),Object.defineProperty(this,"style",{enumerable:!0,configurable:!0,writable:!0,value:s}),Object.defineProperty(this,"teleportTo",{enumerable:!0,configurable:!0,writable:!0,value:l}),Object.defineProperty(this,"label",{enumerable:!0,configurable:!0,writable:!0,value:c}),Object.defineProperty(this,"name",{enumerable:!0,configurable:!0,writable:!0,value:h}),Object.defineProperty(this,"disabled",{enumerable:!0,configurable:!0,writable:!0,value:p}),Object.defineProperty(this,"locale",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"firstDayOfWeek",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),this.locale=C(t),this.firstDayOfWeek=C(n)}},Sn=class{constructor(){Object.defineProperty(this,"locale",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"firstDayOfWeek",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"min",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"max",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"placement",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"listeners",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"style",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"teleportTo",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"label",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"name",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"disabled",{enumerable:!0,configurable:!0,writable:!0,value:void 0})}build(){return new Pn(this.locale,this.firstDayOfWeek,this.min,this.max,this.placement,this.listeners,this.style,this.teleportTo,this.label,this.name,this.disabled)}withLocale(t){return this.locale=t,this}withFirstDayOfWeek(t){return this.firstDayOfWeek=t,this}withMin(t){return this.min=t,this}withMax(t){return this.max=t,this}withPlacement(t){return this.placement=t,this}withListeners(t){return this.listeners=t,this}withStyle(t){return this.style=t,this}withTeleportTo(t){return this.teleportTo=t,this}withLabel(t){return this.label=t,this}withName(t){return this.name=t,this}withDisabled(t){return this.disabled=t,this}},hu=(e,t)=>{var n,r;let i=qi()==="rtl",a;!((n=e.datePicker)===null||n===void 0)&&n.teleportTo?a=e.datePicker.teleportTo:i&&(a=document.body);let o=Je.BOTTOM_END;return i&&(o=Je.BOTTOM_START),new Sn().withLocale(e.locale).withFirstDayOfWeek(e.firstDayOfWeek).withMin(e.minDate).withMax(e.maxDate).withTeleportTo(a).withStyle((r=e.datePicker)===null||r===void 0?void 0:r.style).withPlacement(o).withListeners({onChange:t}).build()},vu=(e,t)=>{let n=null;return r=>{var i;e.setRange(r),!((i=t.callbacks)===null||i===void 0)&&i.onSelectedDateUpdate&&r!==n&&(n=r,t.callbacks.onSelectedDateUpdate(r))}},fu=(e,t)=>{if(e&&t)throw new Error("You cannot provide plugins over the config object and as an argument to createCalendar.")},pu=e=>{var t,n,r,i;if(e.selectedDate&&!Ae.DATE_STRING.test(e.selectedDate))throw new Error("[Schedule-X error]: selectedDate must have the format YYYY-MM-DD");if(e.minDate&&!Ae.DATE_STRING.test(e.minDate))throw new Error("[Schedule-X error]: minDate must have the format YYYY-MM-DD");if(e.maxDate&&!Ae.DATE_STRING.test(e.maxDate))throw new Error("[Schedule-X error]: maxDate must have the format YYYY-MM-DD");if(typeof e.firstDayOfWeek!="undefined"&&(e.firstDayOfWeek<0||e.firstDayOfWeek>6))throw new Error("[Schedule-X error]: firstDayOfWeek must be a number between 0 and 6");if(typeof((t=e.weekOptions)===null||t===void 0?void 0:t.gridHeight)!="undefined"&&e.weekOptions.gridHeight<0)throw new Error("[Schedule-X error]: weekOptions.gridHeight must be a positive number");if(typeof((n=e.weekOptions)===null||n===void 0?void 0:n.nDays)!="undefined"&&(e.weekOptions.nDays<1||e.weekOptions.nDays>7))throw new Error("[Schedule-X error]: weekOptions.nDays must be a number between 1 and 7");if(typeof((r=e.weekOptions)===null||r===void 0?void 0:r.eventWidth)!="undefined"&&(e.weekOptions.eventWidth<1||e.weekOptions.eventWidth>100))throw new Error("[Schedule-X error]: weekOptions.eventWidth must be an integer between 1 and 100");if(typeof((i=e.monthGridOptions)===null||i===void 0?void 0:i.nEventsPerDay)!="undefined"&&e.monthGridOptions.nEventsPerDay<0)throw new Error("[Schedule-X error]: monthGridOptions.nEventsPerDay must be a positive number");let a=/^\d{2}:00$/;if(typeof e.dayBoundaries!="undefined"){let o=!a.test(e.dayBoundaries.start),s=!a.test(e.dayBoundaries.end);if(o||s)throw new Error('[Schedule-X error]: dayBoundaries must be an object with "start"- and "end" properties, each with the format HH:mm')}},mu=(e=[])=>{e==null||e.forEach(t=>{if(!ae.test(t.start)&&!K.test(t.start))throw new Error(`[Schedule-X error]: Event start time ${t.start} is not a valid time format. Please refer to the docs for more information.`);if(!ae.test(t.end)&&!K.test(t.end))throw new Error(`[Schedule-X error]: Event end time ${t.end} is not a valid time format. Please refer to the docs for more information.`);if(typeof t.id=="number"&&t.id%1!==0)throw new Error(`[Schedule-X error]: Event id ${t.id} is not a valid id. Only non-unicode characters that can be used by document.querySelector is allowed, see: https://developer.mozilla.org/en-US/docs/Web/CSS/ident. We recommend using uuids or integers.`);if(typeof t.id=="string"&&!/^[a-zA-Z0-9_-]*$/.test(t.id))throw new Error(`[Schedule-X error]: Event id ${t.id} is not a valid id. Only non-unicode characters that can be used by document.querySelector is allowed, see: https://developer.mozilla.org/en-US/docs/Web/CSS/ident. We recommend using uuids or integers.`);if(typeof t.id!="string"&&typeof t.id!="number")throw new Error(`[Schedule-X error]: Event id ${t.id} is not a valid id. Only non-unicode characters that can be used by document.querySelector is allowed, see: https://developer.mozilla.org/en-US/docs/Web/CSS/ident. We recommend using uuids or integers.`)})},_u=(e,t)=>{var n;let r=uu(e,t),i=du(r),a=su(r,i,e.selectedDate),o=vu(a,e),s=hu(e,o),l=Ss(s,e.selectedDate||((n=e.datePicker)===null||n===void 0?void 0:n.selectedDate)),c=lu(e.events||[],e.backgroundEvents||[],r);return new pn().withConfig(r).withTimeUnitsImpl(i).withDatePickerState(l).withCalendarEvents(c).withDatePickerConfig(s).withCalendarState(a).withTranslate(tu(r.locale,r.translations)).build()},Ji=(e,t)=>(fu(e.plugins,t),e.skipValidation!==!0&&(mu(e.events),pu(e)),new vn(_u(e,t||e.plugins||[]))),gu=(e,t)=>(e.push({date:F(t),events:{},backgroundEvents:[]}),e),bu=(e,t)=>{let{year:n,month:r}=Q(e),i=t.getMonthWithTrailingAndLeadingDays(n,r),a=[];for(let o of i)a.push(o.reduce(gu,[]));return a};function yu({gridRow:e,calendarEvent:t,date:n,isFirstWeek:r,isLastWeek:i}){var a,o,s,l,c;let h=N(j),p=r&&((a=h.calendarState.range.value)===null||a===void 0?void 0:a.start)&&O(t.start)<O(h.calendarState.range.value.start),u=i&&((o=h.calendarState.range.value)===null||o===void 0?void 0:o.end)&&O(t.end)>O(h.calendarState.range.value.end),{createDragStartTimeout:v,setClickedEventIfNotDragging:m,setClickedEvent:f}=et(h),_=O(t.start)===n,g=t._eventFragments[n],b={borderInlineStart:_?`4px solid var(--sx-color-${t._color})`:void 0,color:`var(--sx-color-on-${t._color}-container)`,backgroundColor:`var(--sx-color-${t._color}-container)`,width:`calc(${g*100+"%"} + ${g}px - 10px)`},D=y=>{var W;Qe(y)&&y.preventDefault(),y.target&&(!h.config.plugins.dragAndDrop||!((W=t._options)===null||W===void 0)&&W.disableDND||h.config.plugins.dragAndDrop.createMonthGridDragHandler(t,h))},E=h.config._customComponentFns.monthGridEvent,L=E?"custom-month-grid-event-"+V():void 0;M(()=>{E&&E(fe(L),{calendarEvent:t._getExternalEvent(),hasStartDate:_})},[t]);let A=y=>{y.stopPropagation(),le(h,t,y)},ne=y=>{y.stopPropagation(),tt(h,t,y)},$=y=>{(y.key==="Enter"||y.key===" ")&&(y.stopPropagation(),f(y,t),le(h,t,y),Ye(()=>{nt(h)}))},z=["sx__event","sx__month-grid-event","sx__month-grid-cell"];!((s=t._options)===null||s===void 0)&&s.additionalClasses&&z.push(...t._options.additionalClasses),Ot(t)&&z.push("is-event-new"),p&&z.push("sx__month-grid-event--overflow-left"),u&&z.push("sx__month-grid-event--overflow-right");let w=(l=t._customContent)===null||l===void 0?void 0:l.monthGrid;return d("div",{draggable:!!h.config.plugins.dragAndDrop,"data-event-id":t.id,"data-ccid":L,onMouseDown:y=>v(D,y),onMouseUp:y=>m(t,y),onTouchStart:y=>v(D,y),onTouchEnd:y=>m(t,y),onClick:A,onDblClick:ne,onKeyDown:$,className:z.join(" "),style:{gridRow:e,width:b.width,padding:E?"0px":void 0,borderInlineStart:E?void 0:b.borderInlineStart,color:E?void 0:b.color,backgroundColor:E?void 0:b.backgroundColor},tabIndex:0,role:"button",children:[!E&&!w&&d(k,{children:[ae.test(t.start)&&d("div",{className:"sx__month-grid-event-time",children:Se(t.start,h.config.locale.value)}),d("div",{className:"sx__month-grid-event-title",children:t.title})]}),w&&d("div",{dangerouslySetInnerHTML:{__html:((c=t._customContent)===null||c===void 0?void 0:c.monthGrid)||""}})]})}function wu({day:e,isFirstWeek:t,isLastWeek:n}){let r=N(j),i=Object.values(e.events).filter(w=>typeof w=="object"||w===Xe).length,a=w=>w===1?r.translate("+ 1 event"):r.translate("+ {{n}} events",{n:w}),o=w=>w===1?r.translate("Link to 1 more event on {{date}}",{date:ye(e.date,r.config.locale.value)}):r.translate("Link to {{n}} more events on {{date}}",{date:ye(e.date,r.config.locale.value),n:i-r.config.monthGridOptions.value.nEventsPerDay}),s=w=>{w.stopPropagation(),r.config.callbacks.onClickPlusEvents&&r.config.callbacks.onClickPlusEvents(e.date,w),r.config.views.value.find(y=>y.name===G.Day)&&setTimeout(()=>{r.datePickerState.selectedDate.value=e.date,r.calendarState.setView(G.Day,e.date)},250)},l=["sx__month-grid-day__header-date"],c=S(e.date),h=c;Cn(h)&&l.push("sx__is-today");let{month:p}=Q(r.datePickerState.selectedDate.value),{month:u}=Q(e.date),v=["sx__month-grid-day",Tt(c.getDay())],[m,f]=P(v);M(()=>{let w=[...v];u!==p&&w.push("is-leading-or-trailing"),r.datePickerState.selectedDate.value===e.date&&w.push("is-selected"),f(w)},[r.datePickerState.selectedDate.value]);let g=Object.values(e.events).slice(r.config.monthGridOptions.value.nEventsPerDay).filter(w=>w===Xe||typeof w=="object").length,b=e.date+" 00:00",D=e.date+" 23:59",E=e.backgroundEvents.find(w=>{let y=K.test(w.start)?w.start+" 00:00":w.start,W=K.test(w.end)?w.end+" 23:59":w.end;return y<=b&&W>=D}),L=w=>{if(!w.target.classList.contains("sx__month-grid-day"))return;let W=r.config.callbacks.onMouseDownMonthGridDate;W&&W(e.date,w)},A=r.config._customComponentFns.monthGridDayName,ne=P(A?V():"")[0];M(()=>{if(!A)return;let w=document.querySelector(`[data-ccid="${ne}"]`);w instanceof HTMLElement&&A(w,{day:S(e.date).getDay()})},[e]);let $=r.config._customComponentFns.monthGridDate,z=P($?V():"")[0];return M(()=>{if(!$)return;let w=document.querySelector(`[data-ccid="${z}"]`);w instanceof HTMLElement&&$(w,{date:S(e.date).getDate(),jsDate:S(e.date)})},[e]),d("div",{className:m.join(" "),"data-date":e.date,onClick:w=>r.config.callbacks.onClickDate&&r.config.callbacks.onClickDate(e.date,w),"aria-label":ye(e.date,r.config.locale.value),onDblClick:w=>{var y,W;return(W=(y=r.config.callbacks).onDoubleClickDate)===null||W===void 0?void 0:W.call(y,e.date,w)},onMouseDown:L,children:[E&&d(k,{children:d("div",{className:"sx__month-grid-background-event",title:E.title,style:{...E.style}})}),d("div",{className:"sx__month-grid-day__header",children:[t?d(k,{children:A?d("div",{"data-ccid":ne}):d("div",{className:"sx__month-grid-day__header-day-name",children:En(h,r.config.locale.value)})}):null,z?d("div",{"data-ccid":z}):d("div",{className:l.join(" "),children:h.getDate()})]}),d("div",{className:"sx__month-grid-day__events",children:Object.values(e.events).slice(0,r.config.monthGridOptions.value.nEventsPerDay).map((w,y)=>typeof w!="object"?d("div",{className:"sx__month-grid-blocker sx__month-grid-cell",style:{gridRow:y+1}}):d(yu,{gridRow:y+1,calendarEvent:w,date:e.date,isFirstWeek:t,isLastWeek:n}))}),g>0?d("button",{type:"button",className:"sx__month-grid-day__events-more sx__ripple--wide","aria-label":o(g),onClick:s,children:a(g)}):null]})}function xu({week:e,isFirstWeek:t,isLastWeek:n}){let r=N(j);return d("div",{className:"sx__month-grid-week",children:[r.config.showWeekNumbers.value&&d("div",{className:"sx__month-grid-week__week-number",children:An(S(e[0].date),r.config.firstDayOfWeek.value)}),e.map(i=>{let a=i.date;return d(wu,{day:i,isFirstWeek:t,isLastWeek:n},a)})]})}var Du=(e,t)=>{let n=Object.keys(t).sort(),r=n[0],i=n[n.length-1],a=new Set;for(let o of e){let s=O(o.start),l=O(o.end),c=!!t[s],h=c;if(!c&&s<r&&l>=r&&(h=!0),!h)continue;let p=c?s:r,u=l<=i?l:i,v=Object.values(t).filter(_=>_.date>=p&&_.date<=u),m,f=0;for(;m===void 0;)v.every(g=>!g.events[f])?(m=f,a.add(f)):f++;for(let[_,g]of v.entries())_===0?(o._eventFragments[p]=v.length,g.events[m]=o):g.events[m]=Xe}for(let o of Array.from(a))for(let[,s]of Object.entries(t))s.events[o]||(s.events[o]=void 0);return t},ku=(e,t)=>{let n=[];return e.forEach(r=>{let i={};r.forEach(a=>i[a.date]=a),n.push(i)}),n.forEach(r=>Du(t,r)),e},Pu=({$app:e,id:t})=>{let[n,r]=P([]);return re(()=>{e.calendarEvents.list.value.forEach(o=>{o._eventFragments={}});let i=bu(e.datePickerState.selectedDate.value,e.timeUnitsImpl);i.forEach(o=>{o.forEach(s=>{s.backgroundEvents=Ui(e.calendarEvents.backgroundEvents.value,{start:s.date,end:s.date})})});let a=e.calendarEvents.filterPredicate.value?e.calendarEvents.list.value.filter(e.calendarEvents.filterPredicate.value):e.calendarEvents.list.value;r(ku(i,a.sort(Xo)))}),d(j.Provider,{value:e,children:d("div",{id:t,className:"sx__month-grid-wrapper",children:n.map((i,a)=>d(xu,{week:i,isFirstWeek:a===0,isLastWeek:a===n.length-1},a))})})},Zi={name:G.MonthGrid,label:"Month",setDateRange:Nn,Component:Pu,hasWideScreenCompat:!0,hasSmallScreenCompat:!1,backwardForwardFn:Ze,backwardForwardUnits:1},nh=ce(Zi),Qi=()=>ce(Zi),Su=(e,t)=>{let{year:n,month:r}=Q(e);return{weeks:t.getMonthWithTrailingAndLeadingDays(n,r).map(a=>a.map(o=>({date:F(o),events:[]})))}};function Eu({day:e,isActive:t,setActiveDate:n}){let r=N(j),{month:i}=Q(r.datePickerState.selectedDate.value),{month:a}=Q(e.date),o=S(e.date),s=["sx__month-agenda-day",Tt(o.getDay())];t&&s.push("sx__month-agenda-day--active"),a!==i&&s.push("is-leading-or-trailing");let l=(v,m)=>{n(e.date),m&&m(e.date,v)},c=v=>v.date===r.datePickerState.selectedDate.value,h=v=>{let m=new Map([["ArrowDown",7],["ArrowUp",-7],["ArrowLeft",-1],["ArrowRight",1]]);r.datePickerState.selectedDate.value=se(r.datePickerState.selectedDate.value,m.get(v.key)||0)},p=!!(r.config.minDate.value&&e.date<r.config.minDate.value),u=!!(r.config.maxDate.value&&e.date>r.config.maxDate.value);return d("button",{type:"button",className:s.join(" "),onClick:v=>l(v,r.config.callbacks.onClickAgendaDate),onDblClick:v=>l(v,r.config.callbacks.onDoubleClickAgendaDate),disabled:p||u,"aria-label":ye(e.date,r.config.locale.value),tabIndex:c(e)?0:-1,"data-agenda-focus":c(e)?"true":void 0,onKeyDown:h,children:[d("div",{children:o.getDate()}),d("div",{className:"sx__month-agenda-day__event-icons",children:e.events.slice(0,3).map(v=>d("div",{style:{backgroundColor:`var(--sx-color-${v._color})`},className:"sx__month-agenda-day__event-icon"}))})]})}function Cu({week:e,setActiveDate:t,activeDate:n}){let r=N(j);return d("div",{className:"sx__month-agenda-week",children:[r.config.showWeekNumbers.value&&d("div",{className:"sx__month-agenda-week__week-number",children:An(S(e[0].date),r.config.firstDayOfWeek.value)}),e.map((i,a)=>d(Eu,{setActiveDate:t,day:i,isActive:n===i.date},a+i.date))]})}function Mu({week:e}){let t=N(j),n=Mi(e.map(i=>S(i.date)),t.config.locale.value),r=X(()=>{let i=["sx__month-agenda-day-names"];return t.config.showWeekNumbers.value&&i.push("sx__has-week-numbers"),i.join(" ")},[t.config.showWeekNumbers.value]);return d("div",{className:r,children:n.map(i=>d("div",{className:"sx__month-agenda-day-name",children:i}))})}var Ou=(e,t)=>{let n=e,r=[n];for(;n<t;)n=se(n,1),r.push(n);return r},Tu=e=>t=>{Ou(O(t.start),O(t.end)).forEach(n=>{e[n]&&e[n].events.push(t)})},Nu=(e,t)=>{let n=e.weeks.reduce((r,i)=>(i.forEach(a=>{r[a.date]=a}),r),{});return t.forEach(Tu(n)),e};function Au({calendarEvent:e}){var t,n;let r=N(j),{setClickedEvent:i}=et(r),a={backgroundColor:`var(--sx-color-${e._color}-container)`,color:`var(--sx-color-on-${e._color}-container)`,borderInlineStart:`4px solid var(--sx-color-${e._color})`},o=r.config._customComponentFns.monthAgendaEvent,s=o?"custom-month-agenda-event-"+V():void 0;M(()=>{o&&o(fe(s),{calendarEvent:e._getExternalEvent()})},[e]);let l=v=>{i(v,e),le(r,e,v)},c=v=>{i(v,e),tt(r,e,v)},h=v=>{(v.key==="Enter"||v.key===" ")&&(v.stopPropagation(),i(v,e),le(r,e,v),Ye(()=>{nt(r)}))},p=(t=e._customContent)===null||t===void 0?void 0:t.monthAgenda,u=["sx__event","sx__month-agenda-event"];return Ot(e)&&u.push("is-event-new"),d("div",{className:u.join(" "),"data-ccid":s,"data-event-id":e.id,style:{backgroundColor:o?void 0:a.backgroundColor,color:o?void 0:a.color,borderInlineStart:o?void 0:a.borderInlineStart,padding:o?"0px":void 0},onClick:v=>l(v),onDblClick:v=>c(v),onKeyDown:h,tabIndex:0,role:"button",children:[!o&&!p&&d(k,{children:[d("div",{className:"sx__month-agenda-event__title",children:e.title}),d("div",{className:"sx__month-agenda-event__time sx__month-agenda-event__has-icon",children:[d(Ti,{strokeColor:`var(--sx-color-on-${e._color}-container)`}),d("div",{dangerouslySetInnerHTML:{__html:Ai(e,r.config.locale.value)}})]})]}),p&&d("div",{dangerouslySetInnerHTML:{__html:((n=e._customContent)===null||n===void 0?void 0:n.monthAgenda)||""}})]})}function Yu({events:e}){let t=N(j);return d("div",{className:"sx__month-agenda-events",children:e.length?e.map(n=>d(Au,{calendarEvent:n},n.id)):d("div",{className:"sx__month-agenda-events__empty",children:t.translate("No events")})})}var ju=({$app:e,id:t})=>{var n;let r=()=>{let o=e.calendarEvents.filterPredicate.value?e.calendarEvents.list.value.filter(e.calendarEvents.filterPredicate.value):e.calendarEvents.list.value;return Nu(Su(e.datePickerState.selectedDate.value,e.timeUnitsImpl),o.sort(Tn))},[i,a]=P(r());return M(()=>{a(r())},[e.datePickerState.selectedDate.value,e.calendarEvents.list.value,e.calendarEvents.filterPredicate.value]),M(()=>{let o=new MutationObserver(l=>{l.forEach(c=>{let h=c.target;h.dataset.agendaFocus==="true"&&h.focus()})}),s=document.getElementById(t);return o.observe(s,{childList:!0,subtree:!0,attributes:!0}),()=>o.disconnect()},[]),d(j.Provider,{value:e,children:d("div",{id:t,className:"sx__month-agenda-wrapper",children:[d(Mu,{week:i.weeks[0]}),d("div",{className:"sx__month-agenda-weeks",children:i.weeks.map((o,s)=>d(Cu,{week:o,setActiveDate:l=>e.datePickerState.selectedDate.value=l,activeDate:e.datePickerState.selectedDate.value},s))}),d(Yu,{events:((n=i.weeks.flat().find(o=>o.date===e.datePickerState.selectedDate.value))===null||n===void 0?void 0:n.events)||[]},e.datePickerState.selectedDate.value)]})})},ea={name:G.MonthAgenda,label:"Month",setDateRange:Nn,Component:ju,hasSmallScreenCompat:!0,hasWideScreenCompat:!1,backwardForwardFn:Ze,backwardForwardUnits:1},rh=ce(ea),ta=()=>ce(ea),Lu=(e,t)=>{if(!t.current)return;let n=e.datePickerState.selectedDate.value,r=t.current.querySelector(`.sx__list-day[data-date="${n}"]`);r instanceof HTMLElement&&requestAnimationFrame(()=>{r.scrollIntoView({behavior:"instant",block:"start"})})},Iu=({$app:e,id:t})=>{let[n,r]=P([]),i=_e(null),{setClickedEvent:a}=et(e),o=_e(!1),s=_e(null),l=f=>{let _=f.reduce((b,D)=>{let E=O(D.start),L=O(D.end),A=E;for(;A<=L;)b[A]||(b[A]=[]),b[A].push(D),A=se(A,1);return b},{}),g=Object.entries(_).map(([b,D])=>({date:b,events:D.sort((E,L)=>E.start.localeCompare(L.start))})).sort((b,D)=>b.date.localeCompare(D.date));r(g),s.current&&clearTimeout(s.current),s.current=setTimeout(()=>{o.current=!1,s.current=null},100)};M(()=>{o.current=!0,l(e.calendarEvents.list.value)},[e.calendarEvents.list.value]),M(()=>{let f=()=>{s.current&&(clearTimeout(s.current),s.current=null,o.current=!1)},_=i.current;if(_)return _.addEventListener("scroll",f),()=>{_.removeEventListener("scroll",f)}},[]);let[c,h]=P(null);M(()=>{if(!i.current||!e.config.callbacks.onScrollDayIntoView)return;let f=c||new IntersectionObserver(g=>{g.forEach(b=>{if(b.isIntersecting){let D=b.target.getAttribute("data-date");D&&e.config.callbacks.onScrollDayIntoView&&!o.current&&e.config.callbacks.onScrollDayIntoView(D)}})},{root:i.current,rootMargin:"0px",threshold:.1});return i.current.querySelectorAll(".sx__list-day").forEach(g=>{f.observe(g)}),h(f),()=>{f.disconnect()}},[n]),M(()=>{Lu(e,i)},[n,e.datePickerState.selectedDate.value]);let p=(f,_)=>{let g=O(f.start),b=O(f.end),D=g===_,E=b===_,L=g!==b,A={hour:"numeric",minute:"numeric",hour12:e.config.locale.value==="en-US"};return L?D?d(k,{children:[d("div",{className:"sx__list-event-start-time",children:S(f.start).toLocaleTimeString(e.config.locale.value,A)}),d("div",{className:"sx__list-event-arrow",children:"\u2192"})]}):E?d(k,{children:[d("div",{className:"sx__list-event-arrow",children:"\u2190"}),d("div",{className:"sx__list-event-end-time",children:S(f.end).toLocaleTimeString(e.config.locale.value,A)})]}):d("div",{className:"sx__list-event-arrow",children:"\u2194"}):d(k,{children:[d("div",{className:"sx__list-event-start-time",children:S(f.start).toLocaleTimeString(e.config.locale.value,A)}),f.end&&d("div",{className:"sx__list-event-end-time",children:S(f.end).toLocaleTimeString(e.config.locale.value,A)})]})},u=(f,_)=>{a(f,_),le(e,_,f)},v=(f,_)=>{a(f,_),tt(e,_,f)},m=(f,_)=>{(f.key==="Enter"||f.key===" ")&&(f.stopPropagation(),a(f,_),le(e,_,f),Ye(()=>{nt(e)}))};return d(j.Provider,{value:e,children:d("div",{id:t,className:"sx__list-wrapper",ref:i,children:n.length===0?d("div",{className:"sx__list-no-events",children:e.translate("No events")}):n.map(f=>d("div",{className:"sx__list-day","data-date":f.date,children:[d("div",{className:"sx__list-day-header",children:d("div",{className:"sx__list-day-date",children:S(f.date).toLocaleDateString(e.config.locale.value,{weekday:"long",year:"numeric",month:"long",day:"numeric"})})}),d("div",{className:"sx__list-day-events",children:f.events.map(_=>d("div",{className:"sx__event sx__list-event",onClick:g=>u(g,_),onDblClick:g=>v(g,_),onKeyDown:g=>m(g,_),tabIndex:0,role:"button",children:[d("div",{className:"sx__list-event-color-line",style:{backgroundColor:`var(--sx-color-${_._color})`}}),d("div",{className:"sx__list-event-content",children:[d("div",{className:"sx__list-event-title",children:_.title}),d("div",{className:"sx__list-event-times",children:p(_,f.date)})]})]},_.id))}),d("div",{className:"sx__list-day-margin"})]},f.date))})})},na={name:G.List,label:"List",setDateRange:Nn,Component:Iu,hasSmallScreenCompat:!0,hasWideScreenCompat:!0,backwardForwardFn:Ze,backwardForwardUnits:1},ih=ce(na),ra=()=>ce(na);var Yn;(function(e){e.DragAndDrop="dragAndDrop",e.EventModal="eventModal",e.ScrollController="scrollController",e.EventRecurrence="eventRecurrence",e.Resize="resize",e.CalendarControls="calendarControls",e.CurrentTime="currentTime"})(Yn||(Yn={}));var jn=class extends Error{constructor(t){super(`Invalid time string: ${t}`)}},Ru=/^(0[0-9]|1[0-9]|2[0-3]):[0-5][0-9]$/,je=/^(\d{4})-(\d{2})-(\d{2}) (0[0-9]|1[0-9]|2[0-3]):[0-5][0-9]$/,Nt=/^(\d{4})-(\d{2})-(\d{2})$/,ia={DATE_STRING:/^\d{4}-\d{2}-\d{2}$/,DATE_TIME_STRING:/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/},Ln=class extends Error{constructor(t){super(`Invalid date time specification: ${t}`)}},ua=e=>{if(!ia.DATE_TIME_STRING.test(e)&&!ia.DATE_STRING.test(e))throw new Ln(e);return new Date(Number(e.slice(0,4)),Number(e.slice(5,7))-1,Number(e.slice(8,10)),Number(e.slice(11,13)),Number(e.slice(14,16)))},In=e=>{let t=e.slice(11,13),n=e.slice(14,16);return{year:Number(e.slice(0,4)),month:Number(e.slice(5,7))-1,date:Number(e.slice(8,10)),hours:t!==""?Number(t):void 0,minutes:n!==""?Number(n):void 0}},Rn=class extends Error{constructor(t,n){super(`Number must be between ${t} and ${n}.`),Object.defineProperty(this,"min",{enumerable:!0,configurable:!0,writable:!0,value:t}),Object.defineProperty(this,"max",{enumerable:!0,configurable:!0,writable:!0,value:n})}},At=e=>{if(e<0||e>99)throw new Rn(0,99);return String(e).padStart(2,"0")},Kn=e=>`${e.getFullYear()}-${At(e.getMonth()+1)}-${At(e.getDate())}`,Fu=e=>`${At(e.getHours())}:${At(e.getMinutes())}`,da=e=>`${Kn(e)} ${Fu(e)}`,ha=1.6666666666666667,aa=e=>{if(!Ru.test(e)&&e!=="24:00")throw new jn(e);let[t,n]=e.split(":").map(i=>parseInt(i,10)),r=(n*ha).toString();return r.split(".")[0].length<2&&(r=`0${r}`),Number(t+r)},oa=(e,t)=>{let n=t/ha,r=ua(e);return r.setMinutes(r.getMinutes()+n),da(r)},ue=(e,t)=>{let{year:n,month:r,date:i,hours:a,minutes:o}=In(e),s=a!==void 0&&o!==void 0,l=new Date(n,r,i,a!=null?a:0,o!=null?o:0);return l.setDate(l.getDate()+t),s?da(l):Kn(l)},q=e=>e.slice(0,10),Fn=e=>e.slice(11),sa=(e,t)=>{let n=Fn(e);return`${t} ${n}`},va=e=>"time-grid-event-copy-"+e,Wu=(e,t,n)=>{var r;(r=e.config.plugins.eventRecurrence)===null||r===void 0||r.updateRecurrenceDND(t.id,n,t.start)},$u=(e,t)=>{let n=e.calendarEvents.list.value.find(r=>r.id===t.id);n&&(n.start=t.start,n.end=t.end,e.calendarEvents.list.value=[...e.calendarEvents.list.value])},Bn=(e,t,n)=>{"rrule"in t._getForeignProperties()&&e.config.plugins.eventRecurrence?Wu(e,t,n):$u(e,t),e.config.callbacks.onEventUpdate&&e.config.callbacks.onEventUpdate(t._getExternalEvent())},Uu=e=>"touches"in e&&typeof e.touches=="object",fa=e=>{let t=Uu(e)?e.touches[0]:e;return{clientX:t.clientX,clientY:t.clientY}},Vu=e=>e.config.timePointsPerDay/e.config.weekOptions.value.gridHeight,Xn=async(e,t,n,r,i)=>{let a=e.config.callbacks.onBeforeEventUpdateAsync||e.config.callbacks.onBeforeEventUpdate;if(a){let o=t._getExternalEvent();o.start=n,o.end=r;let s=t._getExternalEvent();if(!await a(o,s,e))return i==null||i(void 0),!0}return!1},Wn=class{constructor(t,n,r,i,a,o){Object.defineProperty(this,"$app",{enumerable:!0,configurable:!0,writable:!0,value:t}),Object.defineProperty(this,"eventCoordinates",{enumerable:!0,configurable:!0,writable:!0,value:n}),Object.defineProperty(this,"eventCopy",{enumerable:!0,configurable:!0,writable:!0,value:r}),Object.defineProperty(this,"updateCopy",{enumerable:!0,configurable:!0,writable:!0,value:i}),Object.defineProperty(this,"dayBoundariesDateTime",{enumerable:!0,configurable:!0,writable:!0,value:a}),Object.defineProperty(this,"CHANGE_THRESHOLD_IN_TIME_POINTS",{enumerable:!0,configurable:!0,writable:!0,value:o}),Object.defineProperty(this,"dayWidth",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"startY",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"startX",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"lastIntervalDiff",{enumerable:!0,configurable:!0,writable:!0,value:0}),Object.defineProperty(this,"lastDaysDiff",{enumerable:!0,configurable:!0,writable:!0,value:0}),Object.defineProperty(this,"originalStart",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"originalEnd",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"handleMouseOrTouchMove",{enumerable:!0,configurable:!0,writable:!0,value:s=>{let{clientX:l,clientY:c}=fa(s),p=(c-this.startY)*this.timePointsPerPixel(),u=Math.round(p/this.CHANGE_THRESHOLD_IN_TIME_POINTS),v=l-this.startX,m=Math.round(v/this.dayWidth);this.handleVerticalMouseOrTouchMove(u),this.handleHorizontalMouseOrTouchMove(m)}}),Object.defineProperty(this,"handleMouseUpOrTouchEnd",{enumerable:!0,configurable:!0,writable:!0,value:async()=>{document.removeEventListener("mousemove",this.handleMouseOrTouchMove),document.removeEventListener("touchmove",this.handleMouseOrTouchMove),document.removeEventListener("mouseup",this.handleMouseUpOrTouchEnd),document.removeEventListener("touchend",this.handleMouseUpOrTouchEnd),this.updateCopy(void 0),!await Xn(this.$app,this.eventCopy,this.originalStart,this.originalEnd,this.updateCopy)&&this.updateOriginalEvent()}}),this.dayWidth=t.elements.calendarWrapper.querySelector(".sx__time-grid-day").clientWidth,this.startY=this.eventCoordinates.clientY,this.startX=this.eventCoordinates.clientX,this.originalStart=this.eventCopy.start,this.originalEnd=this.eventCopy.end,this.init()}init(){document.addEventListener("mousemove",this.handleMouseOrTouchMove),document.addEventListener("mouseup",this.handleMouseUpOrTouchEnd),document.addEventListener("touchmove",this.handleMouseOrTouchMove,{passive:!1}),document.addEventListener("touchend",this.handleMouseUpOrTouchEnd)}timePointsPerPixel(){return Vu(this.$app)}handleVerticalMouseOrTouchMove(t){if(t===this.lastIntervalDiff)return;let n=t>this.lastIntervalDiff?this.CHANGE_THRESHOLD_IN_TIME_POINTS:-this.CHANGE_THRESHOLD_IN_TIME_POINTS;this.setTimeForEventCopy(n),this.lastIntervalDiff=t}setTimeForEventCopy(t){let n=oa(this.eventCopy.start,t),r=oa(this.eventCopy.end,t),i=this.lastDaysDiff;this.$app.config.direction==="rtl"&&(i=-i),!(n<ue(this.dayBoundariesDateTime.start,i))&&(r>ue(this.dayBoundariesDateTime.end,i)||(this.eventCopy.start=n,this.eventCopy.end=r,this.updateCopy(this.eventCopy)))}handleHorizontalMouseOrTouchMove(t){if(t===this.lastDaysDiff)return;let n=t-this.lastDaysDiff;this.$app.config.direction==="rtl"&&(n=-n);let r=ue(q(this.eventCopy.start),n),i=ue(q(this.eventCopy.end),n),a=sa(this.eventCopy.start,r),o=sa(this.eventCopy.end,i);a<this.$app.calendarState.range.value.start||o>this.$app.calendarState.range.value.end||(this.setDateForEventCopy(a,o),this.transformEventCopyPosition(t),this.lastDaysDiff=t)}setDateForEventCopy(t,n){this.eventCopy.start=t,this.eventCopy.end=n,this.updateCopy(this.eventCopy)}transformEventCopyPosition(t){let n=this.$app.elements.calendarWrapper.querySelector("#"+va(this.eventCopy.id));n.style.transform=`translateX(calc(${t*100}% + ${t}px))`}updateOriginalEvent(){if(this.lastIntervalDiff===0&&this.lastDaysDiff===0)return;let t=this.lastDaysDiff===0,n=document.querySelector(`[data-event-id="${this.eventCopy.id}"]`);!t&&n instanceof HTMLElement&&(n.style.display="none"),Bn(this.$app,this.eventCopy,this.originalStart)}},zu=e=>e.elements.calendarWrapper.querySelector(".sx__time-grid-day").clientWidth,Hu=(e,t)=>e.elements.calendarWrapper.querySelector("#"+va(t.id)),la=1e3*60*60*24,$n=class{constructor(t,n,r,i){Object.defineProperty(this,"$app",{enumerable:!0,configurable:!0,writable:!0,value:t}),Object.defineProperty(this,"eventCopy",{enumerable:!0,configurable:!0,writable:!0,value:r}),Object.defineProperty(this,"updateCopy",{enumerable:!0,configurable:!0,writable:!0,value:i}),Object.defineProperty(this,"startX",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"dayWidth",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"originalStart",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"originalEnd",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"rangeStartDate",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"rangeEndDate",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"lastDaysDiff",{enumerable:!0,configurable:!0,writable:!0,value:0}),Object.defineProperty(this,"handleMouseOrTouchMove",{enumerable:!0,configurable:!0,writable:!0,value:a=>{let{clientX:o}=fa(a),s=o-this.startX,l=Math.round(s/this.dayWidth);if(this.$app.config.direction==="rtl"&&(l*=-1),l===this.lastDaysDiff)return;let c=ue(this.originalStart,l),h=ue(this.originalEnd,l),p=q(c),u=q(h);if(p>this.rangeEndDate||u<this.rangeStartDate)return;this.eventCopy.start=c,this.eventCopy.end=h;let m=p>=this.rangeStartDate&&p<=this.rangeEndDate?p:this.rangeStartDate,_=u>=this.rangeStartDate&&u<=this.rangeEndDate?u:this.rangeEndDate;this.eventCopy._nDaysInGrid=Math.round((new Date(_).getTime()-new Date(m).getTime())/la)+1,p>=this.rangeStartDate&&this.transformEventCopyPosition(p),this.updateCopy(this.eventCopy),this.lastDaysDiff=l}}),Object.defineProperty(this,"handleMouseUpOrTouchEnd",{enumerable:!0,configurable:!0,writable:!0,value:async()=>{document.removeEventListener("mousemove",this.handleMouseOrTouchMove),document.removeEventListener("touchmove",this.handleMouseOrTouchMove),!await Xn(this.$app,this.eventCopy,this.originalStart,this.originalEnd,this.updateCopy)&&(this.updateOriginalEvent(),setTimeout(()=>{this.updateCopy(void 0)},10))}}),this.startX=n.clientX,this.dayWidth=zu(this.$app),this.originalStart=this.eventCopy.start,this.originalEnd=this.eventCopy.end,this.rangeStartDate=q(this.$app.calendarState.range.value.start),this.rangeEndDate=ue(this.rangeStartDate,t.config.weekOptions.value.nDays-1),this.init()}init(){document.addEventListener("mousemove",this.handleMouseOrTouchMove),document.addEventListener("mouseup",this.handleMouseUpOrTouchEnd,{once:!0}),document.addEventListener("touchmove",this.handleMouseOrTouchMove,{passive:!1}),document.addEventListener("touchend",this.handleMouseUpOrTouchEnd,{once:!0})}transformEventCopyPosition(t){let n=q(this.originalStart),r=Math.round((new Date(t).getTime()-new Date(n>=this.rangeStartDate?n:this.rangeStartDate).getTime())/la);this.$app.config.direction==="rtl"&&(r*=-1),Hu(this.$app,this.eventCopy).style.transform=`translateX(calc(${r*this.dayWidth}px + ${r}px))`}updateOriginalEvent(){this.lastDaysDiff!==0&&Bn(this.$app,this.eventCopy,this.originalStart)}},ca=(e,t)=>{let{year:n,month:r,date:i}=In(e),{year:a,month:o,date:s}=In(t),l=new Date(n,r,i),h=new Date(a,o,s).getTime()-l.getTime();return Math.round(h/(1e3*3600*24))},Un;(function(e){e[e.SUNDAY=0]="SUNDAY",e[e.MONDAY=1]="MONDAY",e[e.TUESDAY=2]="TUESDAY",e[e.WEDNESDAY=3]="WEDNESDAY",e[e.THURSDAY=4]="THURSDAY",e[e.FRIDAY=5]="FRIDAY",e[e.SATURDAY=6]="SATURDAY"})(Un||(Un={}));Un.MONDAY;var Gu="primary",Vn=class{constructor(t,n,r,i,a,o,s,l,c,h=void 0,p={},u={}){Object.defineProperty(this,"_config",{enumerable:!0,configurable:!0,writable:!0,value:t}),Object.defineProperty(this,"id",{enumerable:!0,configurable:!0,writable:!0,value:n}),Object.defineProperty(this,"start",{enumerable:!0,configurable:!0,writable:!0,value:r}),Object.defineProperty(this,"end",{enumerable:!0,configurable:!0,writable:!0,value:i}),Object.defineProperty(this,"title",{enumerable:!0,configurable:!0,writable:!0,value:a}),Object.defineProperty(this,"people",{enumerable:!0,configurable:!0,writable:!0,value:o}),Object.defineProperty(this,"location",{enumerable:!0,configurable:!0,writable:!0,value:s}),Object.defineProperty(this,"description",{enumerable:!0,configurable:!0,writable:!0,value:l}),Object.defineProperty(this,"calendarId",{enumerable:!0,configurable:!0,writable:!0,value:c}),Object.defineProperty(this,"_options",{enumerable:!0,configurable:!0,writable:!0,value:h}),Object.defineProperty(this,"_customContent",{enumerable:!0,configurable:!0,writable:!0,value:p}),Object.defineProperty(this,"_foreignProperties",{enumerable:!0,configurable:!0,writable:!0,value:u}),Object.defineProperty(this,"_previousConcurrentEvents",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"_totalConcurrentEvents",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"_maxConcurrentEvents",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"_nDaysInGrid",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"_createdAt",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"_eventFragments",{enumerable:!0,configurable:!0,writable:!0,value:{}})}get _isSingleDayTimed(){return je.test(this.start)&&je.test(this.end)&&q(this.start)===q(this.end)}get _isSingleDayFullDay(){return Nt.test(this.start)&&Nt.test(this.end)&&this.start===this.end}get _isMultiDayTimed(){return je.test(this.start)&&je.test(this.end)&&q(this.start)!==q(this.end)}get _isMultiDayFullDay(){return Nt.test(this.start)&&Nt.test(this.end)&&this.start!==this.end}get _isSingleHybridDayTimed(){if(!this._config.isHybridDay||!je.test(this.start)||!je.test(this.end))return!1;let t=q(this.start),n=q(this.end),r=Kn(new Date(ua(n).getTime()-864e5));if(t!==n&&t!==r)return!1;let i=this._config.dayBoundaries.value,a=aa(Fn(this.start)),o=aa(Fn(this.end));return a>=i.start&&(o<=i.end||o>a)||a<i.end&&o<=i.end}get _color(){return this.calendarId&&this._config.calendars.value&&this.calendarId in this._config.calendars.value?this._config.calendars.value[this.calendarId].colorName:Gu}_getForeignProperties(){return this._foreignProperties}_getExternalEvent(){return{id:this.id,start:this.start,end:this.end,title:this.title,people:this.people,location:this.location,description:this.description,calendarId:this.calendarId,_options:this._options,...this._getForeignProperties()}}},zn=class{constructor(t,n,r,i){Object.defineProperty(this,"_config",{enumerable:!0,configurable:!0,writable:!0,value:t}),Object.defineProperty(this,"id",{enumerable:!0,configurable:!0,writable:!0,value:n}),Object.defineProperty(this,"start",{enumerable:!0,configurable:!0,writable:!0,value:r}),Object.defineProperty(this,"end",{enumerable:!0,configurable:!0,writable:!0,value:i}),Object.defineProperty(this,"people",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"location",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"description",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"title",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"calendarId",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"_foreignProperties",{enumerable:!0,configurable:!0,writable:!0,value:{}}),Object.defineProperty(this,"_options",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"_customContent",{enumerable:!0,configurable:!0,writable:!0,value:{}})}build(){return new Vn(this._config,this.id,this.start,this.end,this.title,this.people,this.location,this.description,this.calendarId,this._options,this._customContent,this._foreignProperties)}withTitle(t){return this.title=t,this}withPeople(t){return this.people=t,this}withLocation(t){return this.location=t,this}withDescription(t){return this.description=t,this}withForeignProperties(t){return this._foreignProperties=t,this}withCalendarId(t){return this.calendarId=t,this}withOptions(t){return this._options=t,this}withCustomContent(t){return this._customContent=t,this}},Ku=(e,t)=>{let n=new zn(t.config,e.id,e.start,e.end).withTitle(e.title).withPeople(e.people).withCalendarId(e.calendarId).withForeignProperties(JSON.parse(JSON.stringify(e._getForeignProperties()))).withLocation(e.location).withDescription(e.description).withOptions(e._options).withCustomContent(e._customContent).build();return n._nDaysInGrid=e._nDaysInGrid,n},Hn=class{constructor(t,n){Object.defineProperty(this,"calendarEvent",{enumerable:!0,configurable:!0,writable:!0,value:t}),Object.defineProperty(this,"$app",{enumerable:!0,configurable:!0,writable:!0,value:n}),Object.defineProperty(this,"allDayElements",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"currentDragoverDate",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"eventNDays",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"originalStart",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"originalEnd",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"MONTH_DAY_CLASS_NAME",{enumerable:!0,configurable:!0,writable:!0,value:"sx__month-grid-day"}),Object.defineProperty(this,"MONTH_DAY_SELECTOR",{enumerable:!0,configurable:!0,writable:!0,value:`.${this.MONTH_DAY_CLASS_NAME}`}),Object.defineProperty(this,"DAY_DRAGOVER_CLASS_NAME",{enumerable:!0,configurable:!0,writable:!0,value:"sx__month-grid-day--dragover"}),Object.defineProperty(this,"handleDragOver",{enumerable:!0,configurable:!0,writable:!0,value:r=>{r.preventDefault();let i=r.target;if((!(i instanceof HTMLDivElement)||!i.classList.contains(this.MONTH_DAY_CLASS_NAME))&&(i=r.target.closest(this.MONTH_DAY_SELECTOR)),this.currentDragoverDate===i.dataset.date)return;this.currentDragoverDate=i.dataset.date;let a=ue(this.currentDragoverDate,this.eventNDays-1);this.allDayElements.forEach(o=>{let s=o.dataset.date;s>=this.currentDragoverDate&&s<=a?o.classList.add(this.DAY_DRAGOVER_CLASS_NAME):o.classList.remove(this.DAY_DRAGOVER_CLASS_NAME)})}}),Object.defineProperty(this,"handleDragEnd",{enumerable:!0,configurable:!0,writable:!0,value:async()=>{this.allDayElements.forEach(a=>{a.removeEventListener("dragover",this.handleDragOver),a.classList.remove(this.DAY_DRAGOVER_CLASS_NAME)}),this.setCalendarEventPointerEventsTo("auto");let r=this.createUpdatedEvent();await Xn(this.$app,r,this.originalStart,this.originalEnd)||this.updateCalendarEvent(r)}}),Object.defineProperty(this,"setCalendarEventPointerEventsTo",{enumerable:!0,configurable:!0,writable:!0,value:r=>{var i;((i=this.$app.elements.calendarWrapper)===null||i===void 0?void 0:i.querySelectorAll(".sx__event")).forEach(a=>{String(a.dataset.eventId)!==String(this.calendarEvent.id)&&(a.style.pointerEvents=r)})}}),Object.defineProperty(this,"createUpdatedEvent",{enumerable:!0,configurable:!0,writable:!0,value:()=>{let r=Ku(this.calendarEvent,this.$app),i=ca(q(this.calendarEvent.start),q(this.currentDragoverDate));return r.start=ue(r.start,i),r.end=ue(r.end,i),r}}),Object.defineProperty(this,"updateCalendarEvent",{enumerable:!0,configurable:!0,writable:!0,value:r=>{Bn(this.$app,r,this.originalStart)}}),this.originalStart=this.calendarEvent.start,this.originalEnd=this.calendarEvent.end,this.allDayElements=n.elements.calendarWrapper.querySelectorAll(this.MONTH_DAY_SELECTOR),this.eventNDays=ca(this.calendarEvent.start,this.calendarEvent.end)+1,this.init()}init(){document.addEventListener("dragend",this.handleDragEnd,{once:!0}),this.allDayElements.forEach(t=>{t.addEventListener("dragover",this.handleDragOver)}),this.setCalendarEventPointerEventsTo("none")}},Bu=(e,t)=>(t.name=e,t),Gn=class{onRender(t){t.elements.calendarWrapper&&(t.elements.calendarWrapper.dataset.hasDnd="true")}constructor(t){Object.defineProperty(this,"minutesPerInterval",{enumerable:!0,configurable:!0,writable:!0,value:t}),Object.defineProperty(this,"name",{enumerable:!0,configurable:!0,writable:!0,value:Yn.DragAndDrop})}createTimeGridDragHandler(t,n){return new Wn(t.$app,t.eventCoordinates,t.eventCopy,t.updateCopy,n,this.getTimePointsForIntervalConfig())}getTimePointsForIntervalConfig(){return this.minutesPerInterval===60?100:this.minutesPerInterval===30?50:25}createDateGridDragHandler(t){return new $n(t.$app,t.eventCoordinates,t.eventCopy,t.updateCopy)}createMonthGridDragHandler(t,n){return new Hn(t,n)}},pa=(e=15)=>Bu("dragAndDrop",new Gn(e));var Xu=e=>e.config.timePointsPerDay/e.config.weekOptions.value.gridHeight,ma={DATE_STRING:/^\d{4}-\d{2}-\d{2}$/,DATE_TIME_STRING:/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/},qn=class extends Error{constructor(t){super(`Invalid date time specification: ${t}`)}},_a=e=>{if(!ma.DATE_TIME_STRING.test(e)&&!ma.DATE_STRING.test(e))throw new qn(e);return new Date(Number(e.slice(0,4)),Number(e.slice(5,7))-1,Number(e.slice(8,10)),Number(e.slice(11,13)),Number(e.slice(14,16)))},qu=e=>{let t=e.slice(11,13),n=e.slice(14,16);return{year:Number(e.slice(0,4)),month:Number(e.slice(5,7))-1,date:Number(e.slice(8,10)),hours:t!==""?Number(t):void 0,minutes:n!==""?Number(n):void 0}},Jn=class extends Error{constructor(t,n){super(`Number must be between ${t} and ${n}.`),Object.defineProperty(this,"min",{enumerable:!0,configurable:!0,writable:!0,value:t}),Object.defineProperty(this,"max",{enumerable:!0,configurable:!0,writable:!0,value:n})}},Yt=e=>{if(e<0||e>99)throw new Jn(0,99);return String(e).padStart(2,"0")},rr=e=>`${e.getFullYear()}-${Yt(e.getMonth()+1)}-${Yt(e.getDate())}`,Ju=e=>`${Yt(e.getHours())}:${Yt(e.getMinutes())}`,ga=e=>`${rr(e)} ${Ju(e)}`,Zu=1.6666666666666667,Qu=(e,t)=>{let n=t/Zu,r=_a(e);return r.setMinutes(r.getMinutes()+n),ga(r)},ba=(e,t,n,r)=>{if(t._getForeignProperties().rrule&&e.config.plugins.eventRecurrence){e.config.plugins.eventRecurrence.updateRecurrenceOnResize(t.id,n,r);return}let a=e.calendarEvents.list.value.find(o=>o.id===t.id);a&&(a.end=t.end,e.calendarEvents.list.value=[...e.calendarEvents.list.value])},ed=e=>"touches"in e&&typeof e.touches=="object",jt=e=>{let t=ed(e)?e.touches[0]:e;return{clientX:t.clientX,clientY:t.clientY}},Zn=class{constructor(t,n,r,i,a,o){Object.defineProperty(this,"$app",{enumerable:!0,configurable:!0,writable:!0,value:t}),Object.defineProperty(this,"eventCopy",{enumerable:!0,configurable:!0,writable:!0,value:n}),Object.defineProperty(this,"updateCopy",{enumerable:!0,configurable:!0,writable:!0,value:r}),Object.defineProperty(this,"initialY",{enumerable:!0,configurable:!0,writable:!0,value:i}),Object.defineProperty(this,"CHANGE_THRESHOLD_IN_TIME_POINTS",{enumerable:!0,configurable:!0,writable:!0,value:a}),Object.defineProperty(this,"dayBoundariesDateTime",{enumerable:!0,configurable:!0,writable:!0,value:o}),Object.defineProperty(this,"originalEventEnd",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"lastIntervalDiff",{enumerable:!0,configurable:!0,writable:!0,value:0}),Object.defineProperty(this,"lastValidEnd",{enumerable:!0,configurable:!0,writable:!0,value:""}),Object.defineProperty(this,"handleMouseOrTouchMove",{enumerable:!0,configurable:!0,writable:!0,value:l=>{let{clientY:c}=jt(l),p=(c-this.initialY)*Xu(this.$app),u=Math.round(p/this.CHANGE_THRESHOLD_IN_TIME_POINTS);u!==this.lastIntervalDiff&&(this.lastIntervalDiff=u,this.setNewTimeForEventEnd(this.CHANGE_THRESHOLD_IN_TIME_POINTS*u))}}),Object.defineProperty(this,"handleMouseUpOrTouchEnd",{enumerable:!0,configurable:!0,writable:!0,value:async()=>{let l=this.$app.config.callbacks.onBeforeEventUpdateAsync||this.$app.config.callbacks.onBeforeEventUpdate;if(l){let c=this.eventCopy._getExternalEvent();c.end=this.originalEventEnd;let h=this.eventCopy._getExternalEvent();if(!await l(c,h,this.$app)){this.eventCopy.end=this.originalEventEnd,this.finish();return}}this.setNewTimeForEventEnd(this.CHANGE_THRESHOLD_IN_TIME_POINTS*this.lastIntervalDiff),ba(this.$app,this.eventCopy,this.originalEventEnd,this.lastValidEnd),this.finish(),this.$app.config.callbacks.onEventUpdate&&this.$app.config.callbacks.onEventUpdate(this.eventCopy._getExternalEvent())}}),this.originalEventEnd=this.eventCopy.end,this.lastValidEnd=this.eventCopy.end;let s=this.$app.elements.calendarWrapper;s&&(s.classList.add("sx__is-resizing"),this.setupEventListeners())}setupEventListeners(){this.$app.elements.calendarWrapper.addEventListener("mousemove",this.handleMouseOrTouchMove),document.addEventListener("mouseup",this.handleMouseUpOrTouchEnd,{once:!0}),this.$app.elements.calendarWrapper.addEventListener("touchmove",this.handleMouseOrTouchMove,{passive:!1}),document.addEventListener("touchend",this.handleMouseUpOrTouchEnd,{once:!0})}setNewTimeForEventEnd(t){let n=Qu(this.originalEventEnd,t);n>this.dayBoundariesDateTime.end||n<=this.eventCopy.start||(this.lastValidEnd=n,this.eventCopy.end=this.lastValidEnd,this.updateCopy(this.eventCopy))}finish(){this.updateCopy(void 0),this.$app.elements.calendarWrapper.classList.remove("sx__is-resizing"),this.$app.elements.calendarWrapper.removeEventListener("mousemove",this.handleMouseOrTouchMove),this.$app.elements.calendarWrapper.removeEventListener("touchmove",this.handleMouseOrTouchMove)}},Qn;(function(e){e.DragAndDrop="dragAndDrop",e.EventModal="eventModal",e.ScrollController="scrollController",e.EventRecurrence="eventRecurrence",e.Resize="resize",e.CalendarControls="calendarControls",e.CurrentTime="currentTime"})(Qn||(Qn={}));var td=e=>e.elements.calendarWrapper.querySelector(".sx__time-grid-day").clientWidth,nd=(e,t)=>(t.name=e,t),er;(function(e){e[e.SUNDAY=0]="SUNDAY",e[e.MONDAY=1]="MONDAY",e[e.TUESDAY=2]="TUESDAY",e[e.WEDNESDAY=3]="WEDNESDAY",e[e.THURSDAY=4]="THURSDAY",e[e.FRIDAY=5]="FRIDAY",e[e.SATURDAY=6]="SATURDAY"})(er||(er={}));er.MONDAY;var rd=(e,t)=>{let{year:n,month:r,date:i,hours:a,minutes:o}=qu(e),s=a!==void 0&&o!==void 0,l=new Date(n,r,i,a!=null?a:0,o!=null?o:0);return l.setDate(l.getDate()+t),s?ga(l):rr(l)},tr=class{constructor(t,n,r,i){Object.defineProperty(this,"$app",{enumerable:!0,configurable:!0,writable:!0,value:t}),Object.defineProperty(this,"eventCopy",{enumerable:!0,configurable:!0,writable:!0,value:n}),Object.defineProperty(this,"updateCopy",{enumerable:!0,configurable:!0,writable:!0,value:r}),Object.defineProperty(this,"initialX",{enumerable:!0,configurable:!0,writable:!0,value:i}),Object.defineProperty(this,"dayWidth",{enumerable:!0,configurable:!0,writable:!0,value:0}),Object.defineProperty(this,"originalEventEnd",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"ORIGINAL_NDAYS",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"lastNDaysDiff",{enumerable:!0,configurable:!0,writable:!0,value:0}),Object.defineProperty(this,"handleMouseOrTouchMove",{enumerable:!0,configurable:!0,writable:!0,value:o=>{let{clientX:s}=jt(o),l=s-this.initialX,c=Math.floor(l/this.dayWidth);this.$app.config.direction==="rtl"&&(c*=-1),this.lastNDaysDiff=c,this.setNewTimeForEventEnd()}}),Object.defineProperty(this,"handleMouseUpOrTouchEnd",{enumerable:!0,configurable:!0,writable:!0,value:async()=>{let o=this.$app.config.callbacks.onBeforeEventUpdateAsync||this.$app.config.callbacks.onBeforeEventUpdate;if(o){let s=this.eventCopy._getExternalEvent();s.end=this.originalEventEnd;let l=this.eventCopy._getExternalEvent();if(!await o(s,l,this.$app)){this.eventCopy.end=this.originalEventEnd,this.finish();return}}ba(this.$app,this.eventCopy,this.originalEventEnd,this.eventCopy.end),this.finish(),this.$app.config.callbacks.onEventUpdate&&this.$app.config.callbacks.onEventUpdate(this.eventCopy._getExternalEvent())}}),this.originalEventEnd=n.end,this.ORIGINAL_NDAYS=n._nDaysInGrid||0;let a=this.$app.elements.calendarWrapper;a&&(a.classList.add("sx__is-resizing"),this.dayWidth=td(this.$app),this.setupEventListeners())}setupEventListeners(){this.$app.elements.calendarWrapper.addEventListener("mousemove",this.handleMouseOrTouchMove),document.addEventListener("mouseup",this.handleMouseUpOrTouchEnd,{once:!0}),this.$app.elements.calendarWrapper.addEventListener("touchmove",this.handleMouseOrTouchMove,{passive:!1}),document.addEventListener("touchend",this.handleMouseUpOrTouchEnd,{once:!0})}setNewTimeForEventEnd(){let t=rd(this.originalEventEnd,this.lastNDaysDiff);t>this.$app.calendarState.range.value.end||t<this.eventCopy.start||t<rr(_a(this.$app.calendarState.range.value.start))||(this.eventCopy.end=t,this.eventCopy._nDaysInGrid=this.ORIGINAL_NDAYS+this.lastNDaysDiff,this.updateCopy(this.eventCopy))}finish(){this.updateCopy(void 0),this.$app.elements.calendarWrapper.classList.remove("sx__is-resizing"),this.$app.elements.calendarWrapper.removeEventListener("mousemove",this.handleMouseOrTouchMove),this.$app.elements.calendarWrapper.removeEventListener("touchmove",this.handleMouseOrTouchMove)}},nr=class{constructor(t){Object.defineProperty(this,"minutesPerInterval",{enumerable:!0,configurable:!0,writable:!0,value:t}),Object.defineProperty(this,"name",{enumerable:!0,configurable:!0,writable:!0,value:Qn.Resize}),Object.defineProperty(this,"$app",{enumerable:!0,configurable:!0,writable:!0,value:null})}onRender(t){this.$app=t}createTimeGridEventResizer(t,n,r,i){if(!this.$app)return this.logError();let{clientY:a}=jt(r);new Zn(this.$app,t,n,a,this.getTimePointsForIntervalConfig(),i)}createDateGridEventResizer(t,n,r){if(!this.$app)return this.logError();let{clientX:i}=jt(r);new tr(this.$app,t,n,i)}getTimePointsForIntervalConfig(){return this.minutesPerInterval===60?100:this.minutesPerInterval===30?50:25}logError(){console.error("The calendar is not yet initialized. Cannot resize events.")}},ya=(e=15)=>nd("resize",new nr(e));var id=/^(0[0-9]|1[0-9]|2[0-3]):[0-5][0-9]$/,we=/^(\d{4})-(\d{2})-(\d{2}) (0[0-9]|1[0-9]|2[0-3]):[0-5][0-9]$/,Ie=/^(\d{4})-(\d{2})-(\d{2})$/,wa={DATE_STRING:/^\d{4}-\d{2}-\d{2}$/,DATE_TIME_STRING:/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/},or=class extends Error{constructor(t){super(`Invalid date time specification: ${t}`)}},ad=e=>{if(!wa.DATE_TIME_STRING.test(e)&&!wa.DATE_STRING.test(e))throw new or(e);return new Date(Number(e.slice(0,4)),Number(e.slice(5,7))-1,Number(e.slice(8,10)),Number(e.slice(11,13)),Number(e.slice(14,16)))},sr=class extends Error{constructor(t,n){super(`Number must be between ${t} and ${n}.`),Object.defineProperty(this,"min",{enumerable:!0,configurable:!0,writable:!0,value:t}),Object.defineProperty(this,"max",{enumerable:!0,configurable:!0,writable:!0,value:n})}},xa=e=>{if(e<0||e>99)throw new sr(0,99);return String(e).padStart(2,"0")},od=e=>`${e.getFullYear()}-${xa(e.getMonth()+1)}-${xa(e.getDate())}`,lr=class extends Error{constructor(t){super(`Invalid time string: ${t}`)}},sd=1.6666666666666667,Da=e=>{if(!id.test(e)&&e!=="24:00")throw new lr(e);let[t,n]=e.split(":").map(i=>parseInt(i,10)),r=(n*sd).toString();return r.split(".")[0].length<2&&(r=`0${r}`),Number(t+r)},Le=e=>e.slice(0,10),ka=e=>e.slice(11),cr;(function(e){e[e.SUNDAY=0]="SUNDAY",e[e.MONDAY=1]="MONDAY",e[e.TUESDAY=2]="TUESDAY",e[e.WEDNESDAY=3]="WEDNESDAY",e[e.THURSDAY=4]="THURSDAY",e[e.FRIDAY=5]="FRIDAY",e[e.SATURDAY=6]="SATURDAY"})(cr||(cr={}));cr.MONDAY;var ld="primary",ur=class{constructor(t,n,r,i,a,o,s,l,c,h=void 0,p={},u={}){Object.defineProperty(this,"_config",{enumerable:!0,configurable:!0,writable:!0,value:t}),Object.defineProperty(this,"id",{enumerable:!0,configurable:!0,writable:!0,value:n}),Object.defineProperty(this,"start",{enumerable:!0,configurable:!0,writable:!0,value:r}),Object.defineProperty(this,"end",{enumerable:!0,configurable:!0,writable:!0,value:i}),Object.defineProperty(this,"title",{enumerable:!0,configurable:!0,writable:!0,value:a}),Object.defineProperty(this,"people",{enumerable:!0,configurable:!0,writable:!0,value:o}),Object.defineProperty(this,"location",{enumerable:!0,configurable:!0,writable:!0,value:s}),Object.defineProperty(this,"description",{enumerable:!0,configurable:!0,writable:!0,value:l}),Object.defineProperty(this,"calendarId",{enumerable:!0,configurable:!0,writable:!0,value:c}),Object.defineProperty(this,"_options",{enumerable:!0,configurable:!0,writable:!0,value:h}),Object.defineProperty(this,"_customContent",{enumerable:!0,configurable:!0,writable:!0,value:p}),Object.defineProperty(this,"_foreignProperties",{enumerable:!0,configurable:!0,writable:!0,value:u}),Object.defineProperty(this,"_previousConcurrentEvents",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"_totalConcurrentEvents",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"_maxConcurrentEvents",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"_nDaysInGrid",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"_createdAt",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"_eventFragments",{enumerable:!0,configurable:!0,writable:!0,value:{}})}get _isSingleDayTimed(){return we.test(this.start)&&we.test(this.end)&&Le(this.start)===Le(this.end)}get _isSingleDayFullDay(){return Ie.test(this.start)&&Ie.test(this.end)&&this.start===this.end}get _isMultiDayTimed(){return we.test(this.start)&&we.test(this.end)&&Le(this.start)!==Le(this.end)}get _isMultiDayFullDay(){return Ie.test(this.start)&&Ie.test(this.end)&&this.start!==this.end}get _isSingleHybridDayTimed(){if(!this._config.isHybridDay||!we.test(this.start)||!we.test(this.end))return!1;let t=Le(this.start),n=Le(this.end),r=od(new Date(ad(n).getTime()-864e5));if(t!==n&&t!==r)return!1;let i=this._config.dayBoundaries.value,a=Da(ka(this.start)),o=Da(ka(this.end));return a>=i.start&&(o<=i.end||o>a)||a<i.end&&o<=i.end}get _color(){return this.calendarId&&this._config.calendars.value&&this.calendarId in this._config.calendars.value?this._config.calendars.value[this.calendarId].colorName:ld}_getForeignProperties(){return this._foreignProperties}_getExternalEvent(){return{id:this.id,start:this.start,end:this.end,title:this.title,people:this.people,location:this.location,description:this.description,calendarId:this.calendarId,_options:this._options,...this._getForeignProperties()}}},dr=class{constructor(t,n,r,i){Object.defineProperty(this,"_config",{enumerable:!0,configurable:!0,writable:!0,value:t}),Object.defineProperty(this,"id",{enumerable:!0,configurable:!0,writable:!0,value:n}),Object.defineProperty(this,"start",{enumerable:!0,configurable:!0,writable:!0,value:r}),Object.defineProperty(this,"end",{enumerable:!0,configurable:!0,writable:!0,value:i}),Object.defineProperty(this,"people",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"location",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"description",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"title",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"calendarId",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"_foreignProperties",{enumerable:!0,configurable:!0,writable:!0,value:{}}),Object.defineProperty(this,"_options",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"_customContent",{enumerable:!0,configurable:!0,writable:!0,value:{}})}build(){return new ur(this._config,this.id,this.start,this.end,this.title,this.people,this.location,this.description,this.calendarId,this._options,this._customContent,this._foreignProperties)}withTitle(t){return this.title=t,this}withPeople(t){return this.people=t,this}withLocation(t){return this.location=t,this}withDescription(t){return this.description=t,this}withForeignProperties(t){return this._foreignProperties=t,this}withCalendarId(t){return this.calendarId=t,this}withOptions(t){return this._options=t,this}withCustomContent(t){return this._customContent=t,this}},ir=(e,t)=>{let{id:n,start:r,end:i,title:a,description:o,location:s,people:l,_options:c,...h}=e;return new dr(t,n,r,i).withTitle(a).withDescription(o).withLocation(s).withPeople(l).withCalendarId(e.calendarId).withOptions(c).withForeignProperties(h).withCustomContent(e._customContent).build()},hr=class{constructor(t){Object.defineProperty(this,"$app",{enumerable:!0,configurable:!0,writable:!0,value:t})}set(t){this.$app.calendarEvents.list.value=t.map(n=>ir(n,this.$app.config))}add(t){let n=ir(t,this.$app.config);n._createdAt=new Date;let r=[...this.$app.calendarEvents.list.value];r.push(n),this.$app.calendarEvents.list.value=r}get(t){var n;return(n=this.$app.calendarEvents.list.value.find(r=>r.id===t))===null||n===void 0?void 0:n._getExternalEvent()}getAll(){return this.$app.calendarEvents.list.value.map(t=>t._getExternalEvent())}remove(t){let n=this.$app.calendarEvents.list.value.findIndex(i=>i.id===t),r=[...this.$app.calendarEvents.list.value];r.splice(n,1),this.$app.calendarEvents.list.value=r}update(t){let n=this.$app.calendarEvents.list.value.findIndex(i=>i.id===t.id),r=[...this.$app.calendarEvents.list.value];r.splice(n,1,ir(t,this.$app.config)),this.$app.calendarEvents.list.value=r}},cd=(e,t)=>(t.name=e,t),ar=(e=[])=>{e==null||e.forEach(t=>{if(!we.test(t.start)&&!Ie.test(t.start))throw new Error(`[Schedule-X error]: Event start time ${t.start} is not a valid time format. Please refer to the docs for more information.`);if(!we.test(t.end)&&!Ie.test(t.end))throw new Error(`[Schedule-X error]: Event end time ${t.end} is not a valid time format. Please refer to the docs for more information.`);if(typeof t.id=="number"&&t.id%1!==0)throw new Error(`[Schedule-X error]: Event id ${t.id} is not a valid id. Only non-unicode characters that can be used by document.querySelector is allowed, see: https://developer.mozilla.org/en-US/docs/Web/CSS/ident. We recommend using uuids or integers.`);if(typeof t.id=="string"&&!/^[a-zA-Z0-9_-]*$/.test(t.id))throw new Error(`[Schedule-X error]: Event id ${t.id} is not a valid id. Only non-unicode characters that can be used by document.querySelector is allowed, see: https://developer.mozilla.org/en-US/docs/Web/CSS/ident. We recommend using uuids or integers.`);if(typeof t.id!="string"&&typeof t.id!="number")throw new Error(`[Schedule-X error]: Event id ${t.id} is not a valid id. Only non-unicode characters that can be used by document.querySelector is allowed, see: https://developer.mozilla.org/en-US/docs/Web/CSS/ident. We recommend using uuids or integers.`)})},vr=class{constructor(){Object.defineProperty(this,"name",{enumerable:!0,configurable:!0,writable:!0,value:"EventsServicePlugin"}),Object.defineProperty(this,"$app",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"eventsFacade",{enumerable:!0,configurable:!0,writable:!0,value:void 0})}beforeRender(t){this.$app=t,this.eventsFacade=new hr(t)}update(t){ar([t]),this.eventsFacade.update(t)}add(t){ar([t]),this.eventsFacade.add(t)}remove(t){this.eventsFacade.remove(t)}get(t){return this.eventsFacade.get(t)}getAll(){return this.eventsFacade.getAll()}set(t){ar(t),this.eventsFacade.set(t)}setBackgroundEvents(t){this.$app.calendarEvents.backgroundEvents.value=t}},Pa=()=>cd("eventsService",new vr);var fr=class extends Error{constructor(t,n){super(`Number must be between ${t} and ${n}.`),Object.defineProperty(this,"min",{enumerable:!0,configurable:!0,writable:!0,value:t}),Object.defineProperty(this,"max",{enumerable:!0,configurable:!0,writable:!0,value:n})}},Lt=e=>{if(e<0||e>99)throw new fr(0,99);return String(e).padStart(2,"0")},gr=e=>`${e.getFullYear()}-${Lt(e.getMonth()+1)}-${Lt(e.getDate())}`,ud=e=>`${Lt(e.getHours())}:${Lt(e.getMinutes())}`,Sa=e=>`${gr(e)} ${ud(e)}`,dd=(e,t,n)=>{if(n<t.start){let r=2400-t.start;return(n+r)/e*100}return(n-t.start)/e*100},pr=class extends Error{constructor(t){super(`Invalid time string: ${t}`)}},hd=/^(0[0-9]|1[0-9]|2[0-3]):[0-5][0-9]$/,vd=e=>{let t=e.slice(11,13),n=e.slice(14,16);return{year:Number(e.slice(0,4)),month:Number(e.slice(5,7))-1,date:Number(e.slice(8,10)),hours:t!==""?Number(t):void 0,minutes:n!==""?Number(n):void 0}},fd=1.6666666666666667,pd=e=>{if(!hd.test(e)&&e!=="24:00")throw new pr(e);let[t,n]=e.split(":").map(i=>parseInt(i,10)),r=(n*fd).toString();return r.split(".")[0].length<2&&(r=`0${r}`),Number(t+r)},md=e=>e.slice(11),_d=(e,t,n)=>dd(n,t,pd(md(e))),gd=(e,t)=>(t.name=e,t),mr;(function(e){e[e.SUNDAY=0]="SUNDAY",e[e.MONDAY=1]="MONDAY",e[e.TUESDAY=2]="TUESDAY",e[e.WEDNESDAY=3]="WEDNESDAY",e[e.THURSDAY=4]="THURSDAY",e[e.FRIDAY=5]="FRIDAY",e[e.SATURDAY=6]="SATURDAY"})(mr||(mr={}));mr.MONDAY;var bd=(e,t)=>{let{year:n,month:r,date:i,hours:a,minutes:o}=vd(e),s=a!==void 0&&o!==void 0,l=new Date(n,r,i,a!=null?a:0,o!=null?o:0);return l.setMinutes(l.getMinutes()+t),s?Sa(l):gr(l)},_r=class{constructor(t={}){if(Object.defineProperty(this,"config",{enumerable:!0,configurable:!0,writable:!0,value:t}),Object.defineProperty(this,"name",{enumerable:!0,configurable:!0,writable:!0,value:"currentTime"}),Object.defineProperty(this,"$app",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"observer",{enumerable:!0,configurable:!0,writable:!0,value:null}),typeof t.timeZoneOffset=="number"&&(t.timeZoneOffset<-720||t.timeZoneOffset>840))throw new Error("Invalid time zone offset: "+t.timeZoneOffset)}onRender(t){this.$app=t,this.observer=new MutationObserver(r=>{for(let i of r)i.type==="childList"&&this.setIndicator()});let n=t.elements.calendarWrapper;if(!n)throw new Error("Calendar wrapper not found");this.observer.observe(n,{childList:!0,subtree:!0})}setIndicator(t=!1){let n=gr(new Date),r=Sa(new Date);this.config.timeZoneOffset&&(r=bd(r,this.config.timeZoneOffset));let i=this.$app.elements.calendarWrapper.querySelector(`[data-time-grid-date="${n}"]`);if(!i)return;let a=i.querySelector(".sx__current-time-indicator");if(a&&t&&a.remove(),i&&!a){let o=document.createElement("div");o.classList.add("sx__current-time-indicator");let s=_d(r,this.$app.config.dayBoundaries.value,this.$app.config.timePointsPerDay)+"%";o.style.top=s,i.appendChild(o),this.config.fullWeekWidth&&this.createFullWidthIndicator(s),setTimeout(this.setIndicator.bind(this,!0),6e4-Date.now()%6e4)}}createFullWidthIndicator(t){let n=document.createElement("div");n.classList.add("sx__current-time-indicator-full-week"),n.style.top=t;let r=document.querySelector(".sx__week-grid"),i=r==null?void 0:r.querySelector(".sx__current-time-indicator-full-week");i&&i.remove(),r&&r.appendChild(n)}destroy(){this.observer&&this.observer.disconnect()}},Ea=e=>gd("currentTime",new _r(e));var br;(function(e){e.DragAndDrop="dragAndDrop",e.EventModal="eventModal",e.ScrollController="scrollController",e.EventRecurrence="eventRecurrence",e.Resize="resize",e.CalendarControls="calendarControls",e.CurrentTime="currentTime"})(br||(br={}));var yd=/^(0[0-9]|1[0-9]|2[0-3]):[0-5][0-9]$/,wd=/^(\d{4})-(\d{2})-(\d{2})$/,yr=class extends Error{constructor(t){super(`Invalid time string: ${t}`)}},wr=class extends Error{constructor(t,n){super(`Number must be between ${t} and ${n}.`),Object.defineProperty(this,"min",{enumerable:!0,configurable:!0,writable:!0,value:t}),Object.defineProperty(this,"max",{enumerable:!0,configurable:!0,writable:!0,value:n})}},Ca=e=>{if(e<0||e>99)throw new wr(0,99);return String(e).padStart(2,"0")},Ta=1.6666666666666667,Ma=e=>{if(!yd.test(e)&&e!=="24:00")throw new yr(e);let[t,n]=e.split(":").map(i=>parseInt(i,10)),r=(n*Ta).toString();return r.split(".")[0].length<2&&(r=`0${r}`),Number(t+r)},Oa=e=>{let t=Math.floor(e/100),n=Math.round(e%100/Ta);return`${Ca(t)}:${Ca(n)}`},xd=(e,t)=>(t.name=e,t),xr=class{constructor(){Object.defineProperty(this,"name",{enumerable:!0,configurable:!0,writable:!0,value:br.CalendarControls}),Object.defineProperty(this,"$app",{enumerable:!0,configurable:!0,writable:!0,value:void 0}),Object.defineProperty(this,"getDate",{enumerable:!0,configurable:!0,writable:!0,value:()=>this.$app.datePickerState.selectedDate.value}),Object.defineProperty(this,"getView",{enumerable:!0,configurable:!0,writable:!0,value:()=>this.$app.calendarState.view.value}),Object.defineProperty(this,"getFirstDayOfWeek",{enumerable:!0,configurable:!0,writable:!0,value:()=>this.$app.config.firstDayOfWeek.value}),Object.defineProperty(this,"getLocale",{enumerable:!0,configurable:!0,writable:!0,value:()=>this.$app.config.locale.value}),Object.defineProperty(this,"getViews",{enumerable:!0,configurable:!0,writable:!0,value:()=>this.$app.config.views.value}),Object.defineProperty(this,"getDayBoundaries",{enumerable:!0,configurable:!0,writable:!0,value:()=>({start:Oa(this.$app.config.dayBoundaries.value.start),end:Oa(this.$app.config.dayBoundaries.value.end)})}),Object.defineProperty(this,"getWeekOptions",{enumerable:!0,configurable:!0,writable:!0,value:()=>this.$app.config.weekOptions.value}),Object.defineProperty(this,"getCalendars",{enumerable:!0,configurable:!0,writable:!0,value:()=>this.$app.config.calendars.value}),Object.defineProperty(this,"getMinDate",{enumerable:!0,configurable:!0,writable:!0,value:()=>this.$app.config.minDate.value}),Object.defineProperty(this,"getMaxDate",{enumerable:!0,configurable:!0,writable:!0,value:()=>this.$app.config.maxDate.value}),Object.defineProperty(this,"getMonthGridOptions",{enumerable:!0,configurable:!0,writable:!0,value:()=>this.$app.config.monthGridOptions.value}),Object.defineProperty(this,"getRange",{enumerable:!0,configurable:!0,writable:!0,value:()=>this.$app.calendarState.range.value})}beforeRender(t){this.$app=t}onRender(t){this.$app=t}setDate(t){if(!wd.test(t))throw new Error("Invalid date. Expected format YYYY-MM-DD");this.$app.datePickerState.selectedDate.value=t}setView(t){if(!this.$app.config.views.value.find(r=>r.name===t))throw new Error(`Invalid view name. Expected one of ${this.$app.config.views.value.map(r=>r.name).join(", ")}`);this.$app.calendarState.setView(t,this.$app.datePickerState.selectedDate.value)}setFirstDayOfWeek(t){this.$app.config.firstDayOfWeek.value=t}setLocale(t){this.$app.config.locale.value=t}setViews(t){let n=this.$app.calendarState.view.value;if(!t.some(i=>i.name===n))throw new Error(`Currently active view is not in given views. Expected to find ${n} in ${t.map(i=>i.name).join(",")}`);this.$app.config.views.value=t}setDayBoundaries(t){this.$app.config.dayBoundaries.value={start:Ma(t.start),end:Ma(t.end)}}setWeekOptions(t){this.$app.config.weekOptions.value={...this.$app.config.weekOptions.value,...t}}setCalendars(t){this.$app.config.calendars.value=t}setMinDate(t){this.$app.config.minDate.value=t}setMaxDate(t){this.$app.config.maxDate.value=t}setMonthGridOptions(t){this.$app.config.monthGridOptions.value=t}},Na=()=>xd("calendarControls",new xr);(function(){if(typeof document!="undefined"&&!document.getElementById("mj-schedule-x-theme")){var t=document.createElement("style");t.id="mj-schedule-x-theme",t.textContent=Pr,document.head.firstChild?document.head.insertBefore(t,document.head.firstChild):document.head.appendChild(t)}})();window.MjScheduleX={createCalendar:Ji,views:{day:Xi,week:Ki,"month-grid":Qi,"month-agenda":ta,list:ra},plugins:{dragAndDrop:pa,resize:ya,eventsService:Pa,currentTime:Ea,calendarControls:Na}};})();
