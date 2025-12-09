# Module Animateur - Preact Refactoring

## Overview

This is a refactored version of the animator/facilitator module using Preact instead of jQuery. The module provides a dashboard for event facilitators to manage participants, track attendance, send SMS messages, and handle payments.

## Architecture

### Components

- **Dashboard**: Main component orchestrating all functionality
- **EventCarousel**: Horizontal carousel displaying event cards
- **OccurrenceAgenda**: Shows event occurrences with attendance summaries
- **ParticipantsTable**: Displays participant list with attendance controls
- **SmsBlock**: Interface for sending SMS to participants
- **MemberPickerModal**: Modal for adding members to events
- **QuickMemberModal**: Modal for quickly creating new members

### State Management

State is managed using Preact hooks:
- `useDashboardState`: Custom hook managing dashboard state, events, and participants
- Local component state for UI interactions

### API Communication

All WordPress AJAX calls are handled through the `wpAjax` utility function which:
- Automatically includes nonce for security
- Handles form data serialization
- Provides error handling
- Returns parsed JSON responses

## Development

### Prerequisites

- Node.js 18+
- npm or yarn

### Installation

```bash
npm install
```

### Development Build

```bash
npm run dev
```

### Production Build

```bash
npm run build
```

The built file will be output to `js/dist/animateur-account.js`.

## Project Structure

```
src/animateur/
├── main.jsx                 # Entry point
├── components/              # Preact components
│   ├── Dashboard.jsx
│   ├── EventCarousel.jsx
│   ├── OccurrenceAgenda.jsx
│   ├── ParticipantsTable.jsx
│   ├── SmsBlock.jsx
│   ├── MemberPickerModal.jsx
│   └── QuickMemberModal.jsx
├── hooks/                   # Custom hooks
│   └── useDashboardState.js
└── utils/                   # Utility functions
    └── helpers.js
```

## Migration from jQuery

The original jQuery implementation (~5300 lines) has been refactored into modular Preact components (~1500 lines of source code). Key improvements:

- **Modularity**: Each feature is now a separate component
- **Maintainability**: Easier to understand and modify
- **Performance**: Preact's virtual DOM provides better performance
- **Modern JavaScript**: Uses ES6+ features and hooks
- **No jQuery dependency**: Reduces bundle size

## Features

- ✅ Event carousel with navigation
- ✅ Occurrence selection and agenda
- ✅ Participant attendance tracking
- ✅ Payment status management
- ✅ SMS messaging to participants
- ✅ Member picker for adding participants
- ✅ Quick member creation
- ✅ Real-time updates via AJAX
- ✅ Responsive design
- ✅ Accessibility considerations

## Backend Integration

The Preact app communicates with WordPress through AJAX endpoints:
- `mj_member_animateur_get_event`
- `mj_member_animateur_save_attendance`
- `mj_member_animateur_send_sms`
- `mj_member_animateur_toggle_cash_payment`
- `mj_member_animateur_search_members`
- `mj_member_animateur_quick_create_member`
- `mj_member_animateur_add_members`
- `mj_member_animateur_remove_registration`

All endpoints use WordPress nonces for security.

## Configuration

The dashboard is configured via a `data-config` attribute on the root element:

```json
{
  "events": [...],           // Event data
  "allEvents": [...],        // All available events
  "settings": {
    "attendance": {
      "enabled": true
    },
    "sms": {
      "enabled": true
    },
    "payment": {
      "enabled": true
    },
    "registrations": {
      "canAdd": true,
      "canDelete": true,
      "canEdit": true
    },
    "quickCreate": {
      "enabled": true
    }
  },
  "global": {
    "ajaxUrl": "/wp-admin/admin-ajax.php",
    "nonce": "...",
    "actions": {...}
  }
}
```

## Browser Support

- Chrome/Edge (last 2 versions)
- Firefox (last 2 versions)
- Safari (last 2 versions)

## License

This project is part of the MJ Member WordPress plugin.
