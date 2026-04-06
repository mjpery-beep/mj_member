#!/usr/bin/env python3
"""
Transform registration-manager.php from procedural to class-based structure.
Usage: python transform_registration_manager.py
"""

import re
import sys
import os

INPUT_FILE = os.path.join(
    os.path.dirname(os.path.abspath(__file__)),
    'registration-manager.php'
)

# Complete function mapping: old_name -> (camelCase_method_name, is_public)
FUNC_MAP = {
    'mj_regmgr_get_events': ('getEvents', True),
    'mj_regmgr_verify_request': ('verifyRequest', False),
    'mj_regmgr_to_bool': ('toBool', False),
    'mj_regmgr_format_date': ('formatDate', False),
    'mj_regmgr_build_event_sidebar_item': ('buildEventSidebarItem', False),
    'mj_regmgr_get_member_avatar_url': ('getMemberAvatarUrl', False),
    'mj_regmgr_format_datetime_compact': ('formatDatetimeCompact', False),
    'mj_regmgr_format_date_compact': ('formatDateCompact', False),
    'mj_regmgr_format_time_compact': ('formatTimeCompact', False),
    'mj_regmgr_occurrence_status_from_front': ('occurrenceStatusFromFront', False),
    'mj_regmgr_occurrence_status_to_front': ('occurrenceStatusToFront', False),
    'mj_regmgr_sanitize_time_value': ('sanitizeTimeValue', False),
    'mj_regmgr_sanitize_date_value': ('sanitizeDateValue', False),
    'mj_regmgr_sanitize_occurrence_generator_plan': ('sanitizeOccurrenceGeneratorPlan', False),
    'mj_regmgr_derive_generator_plan_from_schedule': ('deriveGeneratorPlanFromSchedule', False),
    'mj_regmgr_merge_generator_plans': ('mergeGeneratorPlans', False),
    'mj_regmgr_extract_occurrence_generator_from_payload': ('extractOccurrenceGeneratorFromPayload', False),
    'mj_regmgr_extract_occurrence_generator_from_event': ('extractOccurrenceGeneratorFromEvent', False),
    'mj_regmgr_schedule_payload_has_occurrence_entities': ('schedulePayloadHasOccurrenceEntities', False),
    'mj_regmgr_should_allow_occurrence_fallback': ('shouldAllowOccurrenceFallback', False),
    'mj_regmgr_prepare_event_occurrence_rows': ('prepareEventOccurrenceRows', False),
    'mj_regmgr_format_event_occurrences_for_front': ('formatEventOccurrencesForFront', False),
    'mj_regmgr_find_next_occurrence': ('findNextOccurrence', False),
    'mj_regmgr_build_event_schedule_info': ('buildEventScheduleInfo', False),
    'mj_regmgr_get_event_emoji_value': ('getEventEmojiValue', False),
    'mj_regmgr_get_event_cover_url': ('getEventCoverUrl', False),
    'mj_regmgr_decode_json_field': ('decodeJsonField', False),
    'mj_regmgr_serialize_event_summary': ('serializeEventSummary', False),
    'mj_regmgr_prepare_event_form_values': ('prepareEventFormValues', False),
    'mj_regmgr_collect_event_editor_assets': ('collectEventEditorAssets', False),
    'mj_regmgr_build_event_update_payload': ('buildEventUpdatePayload', False),
    'mj_regmgr_get_schedule_weekdays': ('getScheduleWeekdays', False),
    'mj_regmgr_get_schedule_month_ordinals': ('getScheduleMonthOrdinals', False),
    'mj_regmgr_sanitize_weekday_times': ('sanitizeWeekdayTimes', False),
    'mj_regmgr_sanitize_recurrence_exceptions': ('sanitizeRecurrenceExceptions', False),
    'mj_regmgr_ensure_notes_table': ('ensureNotesTable', False),
    'mj_regmgr_get_notes_dynfields': ('getNotesDynfields', False),
    'mj_regmgr_event_allows_attendance_without_registration': ('eventAllowsAttendanceWithoutRegistration', False),
    'mj_regmgr_ensure_attendance_registration': ('ensureAttendanceRegistration', False),
    'mj_regmgr_normalize_hex_color': ('normalizeHexColor', False),
    'mj_regmgr_format_event_datetime': ('formatEventDatetime', False),
    'mj_regmgr_parse_event_datetime': ('parseEventDatetime', False),
    'mj_regmgr_parse_recurrence_until': ('parseRecurrenceUntil', False),
    'mj_regmgr_events_supports_primary_animateur': ('eventsSupportsPrimaryAnimateur', False),
    'mj_regmgr_fill_schedule_values': ('fillScheduleValues', False),
    'mj_regmgr_user_can_manage_locations': ('userCanManageLocations', False),
    'mj_regmgr_build_location_lookup_query': ('buildLocationLookupQuery', False),
    'mj_regmgr_format_location_payload': ('formatLocationPayload', False),
    'mj_regmgr_resolve_schedule_exceptions': ('resolveScheduleExceptions', False),
    'mj_regmgr_get_event_photos': ('getEventPhotos', True),
    'mj_regmgr_upload_event_photo': ('uploadEventPhoto', True),
    'mj_regmgr_update_occurrences': ('updateOccurrences', True),
    'mj_regmgr_save_event_occurrences': ('saveEventOccurrences', True),
    'mj_regmgr_get_members': ('getMembers', True),
    'mj_regmgr_get_member_details': ('getMemberDetails', True),
    'mj_regmgr_update_member': ('updateMember', True),
    'mj_regmgr_update_member_trusted_status': ('updateMemberTrustedStatus', True),
    'mj_regmgr_get_member_registrations': ('getMemberRegistrations', True),
    'mj_regmgr_mark_membership_paid': ('markMembershipPaid', True),
    'mj_regmgr_create_membership_payment_link': ('createMembershipPaymentLink', True),
    'mj_regmgr_update_member_idea': ('updateMemberIdea', True),
    'mj_regmgr_delete_member_idea': ('deleteMemberIdea', True),
    'mj_regmgr_update_member_photo': ('updateMemberPhoto', True),
    'mj_regmgr_delete_member_photo': ('deleteMemberPhoto', True),
    'mj_regmgr_capture_member_photo': ('captureMemberPhoto', True),
    'mj_regmgr_create_member_message': ('createMemberMessage', True),
    'mj_regmgr_delete_member_message': ('deleteMemberMessage', True),
    'mj_regmgr_update_member_notification': ('updateMemberNotification', True),
    'mj_regmgr_delete_member_notification': ('deleteMemberNotification', True),
    'mj_regmgr_reset_member_password': ('resetMemberPassword', True),
    'mj_regmgr_create_member_nextcloud_login': ('createMemberNextcloudLogin', True),
    'mj_regmgr_delete_member': ('deleteMember', True),
    'mj_regmgr_sync_member_badge': ('syncMemberBadge', True),
    'mj_regmgr_adjust_member_xp': ('adjustMemberXp', True),
    'mj_regmgr_toggle_member_trophy': ('toggleMemberTrophy', True),
    'mj_regmgr_award_member_action': ('awardMemberAction', True),
    'mj_regmgr_update_social_link': ('updateSocialLink', True),
    'mj_regmgr_update_member_leave_quotas': ('updateMemberLeaveQuotas', True),
    'mj_regmgr_save_member_work_schedule': ('saveMemberWorkSchedule', True),
    'mj_regmgr_delete_member_work_schedule': ('deleteMemberWorkSchedule', True),
    'mj_regmgr_save_member_dynfields': ('saveMemberDynfields', True),
    'mj_regmgr_get_employee_documents': ('getEmployeeDocuments', True),
    'mj_regmgr_upload_employee_document': ('uploadEmployeeDocument', True),
    'mj_regmgr_update_employee_document': ('updateEmployeeDocument', True),
    'mj_regmgr_delete_employee_document': ('deleteEmployeeDocument', True),
    'mj_regmgr_download_employee_document': ('downloadEmployeeDocument', True),
    'mj_regmgr_save_job_profile': ('saveJobProfile', True),
    'mj_regmgr_get_favorites': ('getFavorites', True),
    'mj_regmgr_toggle_favorite': ('toggleFavorite', True),
    'mj_regmgr_generate_ai_text': ('generateAiText', True),
    'mj_regmgr_publish_event': ('publishEvent', True),
    'mj_regmgr_get_event_details': ('getEventDetails', True),
    'mj_regmgr_get_event_editor': ('getEventEditor', True),
    'mj_regmgr_update_event': ('updateEvent', True),
    'mj_regmgr_create_event': ('createEvent', True),
    'mj_regmgr_delete_event': ('deleteEvent', True),
    'mj_regmgr_get_registrations': ('getRegistrations', True),
    'mj_regmgr_search_members': ('searchMembers', True),
    'mj_regmgr_add_registration': ('addRegistration', True),
    'mj_regmgr_update_registration': ('updateRegistration', True),
    'mj_regmgr_delete_registration': ('deleteRegistration', True),
    'mj_regmgr_update_attendance': ('updateAttendance', True),
    'mj_regmgr_bulk_attendance': ('bulkAttendance', True),
    'mj_regmgr_validate_payment': ('validatePayment', True),
    'mj_regmgr_cancel_payment': ('cancelPayment', True),
    'mj_regmgr_create_quick_member': ('createQuickMember', True),
    'mj_regmgr_get_member_notes': ('getMemberNotes', True),
    'mj_regmgr_save_member_note': ('saveMemberNote', True),
    'mj_regmgr_delete_member_note': ('deleteMemberNote', True),
    'mj_regmgr_get_payment_qr': ('getPaymentQr', True),
    'mj_regmgr_get_location': ('getLocation', True),
    'mj_regmgr_save_location': ('saveLocation', True),
    'mj_regmgr_update_registration_occurrences': ('updateRegistrationOccurrences', True),
    'mj_regmgr_delete_member_testimonial': ('deleteMemberTestimonial', True),
    'mj_regmgr_update_member_testimonial_status': ('updateMemberTestimonialStatus', True),
    'mj_regmgr_toggle_testimonial_featured': ('toggleTestimonialFeatured', True),
    'mj_regmgr_edit_testimonial_content': ('editTestimonialContent', True),
    'mj_regmgr_add_testimonial_comment': ('addTestimonialComment', True),
    'mj_regmgr_edit_testimonial_comment': ('editTestimonialComment', True),
    'mj_regmgr_delete_testimonial_comment': ('deleteTestimonialComment', True),
    'mj_regmgr_add_testimonial_reaction': ('addTestimonialReaction', True),
    'mj_regmgr_remove_testimonial_reaction': ('removeTestimonialReaction', True),
    'mj_regmgr_build_location_map_preview_url': ('buildLocationMapPreviewUrl', False),
    'mj_regmgr_build_location_map_link': ('buildLocationMapLink', False),
    'mj_regmgr_get_member_level_progression': ('getMemberLevelProgression', False),
    'mj_regmgr_get_member_badges_payload': ('getMemberBadgesPayload', False),
    'mj_regmgr_prepare_member_badge_entry': ('prepareMemberBadgeEntry', False),
    'mj_regmgr_normalize_notification_type_key': ('normalizeNotificationTypeKey', False),
    'mj_regmgr_get_notification_type_emoji': ('getNotificationTypeEmoji', False),
    'mj_regmgr_extract_notification_emoji': ('extractNotificationEmoji', False),
    'mj_regmgr_format_member_notification': ('formatMemberNotification', False),
    'mj_regmgr_get_member_trophies_payload': ('getMemberTrophiesPayload', False),
    'mj_regmgr_get_member_actions_payload': ('getMemberActionsPayload', False),
    'mj_regmgr_create_notes_table_if_not_exists': ('createNotesTableIfNotExists', False),
}


# ── Step helpers ──────────────────────────────────────────────────────────────

def remove_function_exists_wrappers(text):
    """
    Remove  if (!function_exists('mj_regmgr_*')) { ... }  blocks that sit at
    column 0, keeping their inner content de-indented by one level (4 spaces).
    """
    lines = text.split('\n')
    result = []
    i = 0
    FE_PAT = re.compile(
        r"^if\s*\(\s*!function_exists\s*\(\s*'mj_regmgr_\w+'\s*\)\s*\)\s*\{"
    )

    while i < len(lines):
        line = lines[i]
        stripped = line.strip()

        # Only trigger on lines at column 0 that match the pattern
        if (
            not line.startswith(' ')
            and not line.startswith('\t')
            and FE_PAT.match(stripped)
        ):
            # Use brace counting to find the matching closing '}'
            depth = 0
            j = i
            while j < len(lines):
                for ch in lines[j]:
                    if ch == '{':
                        depth += 1
                    elif ch == '}':
                        depth -= 1
                if depth == 0 and j > i:
                    break
                j += 1

            # j = index of the closing '}' of the if-block
            # Emit inner lines (i+1 ... j-1) stripped of one indent level
            for k in range(i + 1, j):
                inner = lines[k]
                if inner.startswith('    '):
                    result.append(inner[4:])
                elif inner.startswith('\t'):
                    result.append(inner[1:])
                else:
                    result.append(inner)

            i = j + 1   # skip the closing '}'
            continue

        result.append(line)
        i += 1

    return '\n'.join(result)


def convert_function_defs(text, func_map):
    """
    Replace  function mj_regmgr_X(  with  visibility function Y(
    Must run BEFORE convert_function_calls so that definitions are no longer
    named mj_regmgr_* when we scan for call-sites.
    """
    def replacer(m):
        func_name = m.group(1)
        paren     = m.group(2)
        method_name, is_public = func_map.get(func_name, (func_name, False))
        vis = 'public' if is_public else 'private'
        return '{} function {}{}'.format(vis, method_name, paren)

    return re.sub(r'\bfunction\s+(mj_regmgr_\w+)(\s*\()', replacer, text)


def convert_function_calls(text, func_map):
    """
    Replace every call-site  mj_regmgr_X(  with  $this->Y(
    Works on the body text after definitions have already been renamed,
    so no risk of accidentally mangling the 'function Y(' lines.
    Sort longest names first to prevent partial-name matches.
    """
    for old_name in sorted(func_map, key=len, reverse=True):
        new_name = func_map[old_name][0]
        pattern     = r'\b' + re.escape(old_name) + r'\s*\('
        replacement = '$this->{}('.format(new_name)
        text = re.sub(pattern, replacement, text)
    return text


def convert_add_action_line(stripped, func_map):
    """
    add_action('wp_ajax_X', 'mj_regmgr_Y')
    -> add_action('wp_ajax_X', [$this, 'Y'])
    """
    m = re.match(
        r"add_action\s*\(\s*'([^']+)'\s*,\s*'(mj_regmgr_\w+)'\s*\)(;?)",
        stripped
    )
    if m:
        action     = m.group(1)
        func_name  = m.group(2)
        semi       = m.group(3)
        method     = func_map.get(func_name, (func_name, True))[0]
        return "add_action('{}', [$this, '{}']){}".format(action, method, semi)
    return stripped


def fix_static_closures_with_this(text):
    """
    Convert  static function(  to  function(  when the closure body
    contains $this-> (since static closures cannot use $this).
    Uses a simple brace-balanced scan.
    """
    result = []
    i = 0
    lines = text.split('\n')

    # Pattern: optional whitespace + 'static function(' or 'static function ('
    STATIC_FN = re.compile(r'^(\s*)static\s+(function\s*\()')

    while i < len(lines):
        line = lines[i]
        m = STATIC_FN.match(line)
        if m:
            # Scan forward to find the balanced closing brace of this closure
            depth = 0
            j = i
            while j < len(lines):
                for ch in lines[j]:
                    if ch == '{':
                        depth += 1
                    elif ch == '}':
                        depth -= 1
                if depth == 0 and j > i:
                    break
                j += 1
            # Check if any line in i..j contains '$this->'
            body_text = '\n'.join(lines[i:j+1])
            if '$this->' in body_text:
                # Remove 'static ' prefix from this line only
                line = m.group(1) + m.group(2) + line[m.end():]
        result.append(line)
        i += 1

    return '\n'.join(result)


# ── Main ──────────────────────────────────────────────────────────────────────

def main():
    print('Reading {}'.format(INPUT_FILE))
    with open(INPUT_FILE, 'r', encoding='utf-8') as fh:
        raw = fh.read()

    # Normalise Windows line endings
    raw = raw.replace('\r\n', '\n').replace('\r', '\n')
    lines = raw.split('\n')
    print('  Total lines: {}'.format(len(lines)))

    # ── Detect current file state ──
    # The file may already have the class structure in place (lines 1-166)
    # with the procedural functions still outside/after the registerHooks() method.
    # Find the split point: where registerHooks() closes and procedural functions start.

    # Find the line after the closing } of registerHooks()
    # which is the last line inside the class before procedural functions
    split_point = None
    in_register_hooks = False
    register_hooks_depth = 0

    for idx, line in enumerate(lines):
        stripped = line.strip()
        # Look for the registerHooks method
        if 'public function registerHooks' in line:
            in_register_hooks = True
            register_hooks_depth = 0

        if in_register_hooks:
            for ch in line:
                if ch == '{':
                    register_hooks_depth += 1
                elif ch == '}':
                    register_hooks_depth -= 1
            if register_hooks_depth == 0 and idx > 0 and 'registerHooks' not in line:
                # We've found the closing } of registerHooks
                # The next non-empty line should be where the procedural functions start
                split_point = idx + 1
                in_register_hooks = False
                break

    if split_point is None:
        print('ERROR: Could not find end of registerHooks() method')
        return

    print('  Split point (first procedural line): {}'.format(split_point + 1))

    # ── Keep the already-converted header (lines 0..split_point-1) ──
    header_lines = lines[:split_point]

    # ── Check if class is already opened in the header ──
    # and if registerHooks is already properly structured
    # We keep the header as-is (it already has namespace, use statements,
    # class definition, registerHooks with [$this, ...] calls)
    header = '\n'.join(header_lines)

    # ── Process the function body (from split_point onward) ──
    body = '\n'.join(lines[split_point:])

    # Strip any trailing closing } that might already be there for the class
    # (if the file was partially converted and has a stray } at the end)
    body_stripped = body.rstrip()
    if body_stripped.endswith('}'):
        # Check if this lone } is the class closing brace
        last_lines = body_stripped.split('\n')
        if last_lines[-1].strip() == '}':
            # Remove it - we'll add it back properly
            body = '\n'.join(last_lines[:-1])

    # 3a. Strip if(!function_exists('mj_regmgr_*')) wrappers
    body = remove_function_exists_wrappers(body)

    # 3b. Convert function definitions  ->  visibility function Method(
    body = convert_function_defs(body, FUNC_MAP)

    # 3c. Convert call-sites  ->  $this->method(
    body = convert_function_calls(body, FUNC_MAP)

    # 3d. Fix static closures that reference $this
    body = fix_static_closures_with_this(body)

    # 3e. Indent all non-empty lines by 4 spaces (class body)
    indented = []
    for line in body.split('\n'):
        if line.strip():
            indented.append('    ' + line)
        else:
            indented.append('')

    # ── 4. Assemble the output ──
    parts = header_lines[:]
    parts.append('')   # blank line after registerHooks closing }
    parts += indented
    parts += [
        '}',
        '',   # trailing newline
    ]

    result = '\n'.join(parts)

    # ── 5. Write the converted file ──
    print('Writing {} bytes ({} lines)...'.format(len(result), result.count('\n')))
    with open(INPUT_FILE, 'w', encoding='utf-8', newline='\n') as fh:
        fh.write(result)

    print('Done! Run:  php -l "{}"'.format(INPUT_FILE))


if __name__ == '__main__':
    main()
