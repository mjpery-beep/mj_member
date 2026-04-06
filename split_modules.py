#!/usr/bin/env python3
"""
split_modules.py — Phase 3 cleanup
Splits each converted module file into:
  - includes/Module/XxxModule.php  (clean namespaced class)
  - includes/xxx.php               (original procedural code, hooks removed)
"""
import os, re

BASE = r'C:\Users\simon\Documents\Sites\mjpery-wordpress\wp-content\plugins\mj-member\includes'

FILES = [
    # (procedural_file, namespace, class_name)
    ('security.php',                  'Mj\\Member\\Module',       'SecurityModule'),
    ('data_retention.php',            'Mj\\Member\\Module',       'DataRetentionModule'),
    ('backup.php',                    'Mj\\Member\\Module',       'BackupModule'),
    ('payment_confirmation.php',      'Mj\\Member\\Module',       'PaymentConfirmationModule'),
    ('notifications.php',             'Mj\\Member\\Module',       'NotificationsModule'),
    ('web_push.php',                  'Mj\\Member\\Module',       'WebPushModule'),
    ('grimlins_gallery.php',          'Mj\\Member\\Module',       'GrimlinsGalleryModule'),
    ('idea_box.php',                  'Mj\\Member\\Module',       'IdeaBoxModule'),
    ('account_menu_icons.php',        'Mj\\Member\\Module',       'AccountMenuIconsModule'),
    ('documents.php',                 'Mj\\Member\\Module',       'DocumentsModule'),
    ('member_accounts.php',           'Mj\\Member\\Module',       'MemberAccountsModule'),
    ('hour_encode.php',               'Mj\\Member\\Module',       'HourEncodeModule'),
    ('notification_listeners.php',    'Mj\\Member\\Module',       'NotificationListenersModule'),
    ('dashboard.php',                 'Mj\\Member\\Module',       'DashboardModule'),
    ('event_photos.php',              'Mj\\Member\\Module',       'EventPhotosModule'),
    ('todos.php',                     'Mj\\Member\\Module',       'TodosModule'),
    ('shortcode_inscription.php',     'Mj\\Member\\Module',       'ShortcodeInscriptionModule'),
    ('photo_grimlins.php',            'Mj\\Member\\Module',       'PhotoGrimlinsModule'),
    ('events_public.php',             'Mj\\Member\\Module',       'EventsPublicModule'),
    ('settings.php',                  'Mj\\Member\\Module\\Admin','SettingsModule'),
    ('contact_messages_admin.php',    'Mj\\Member\\Module\\Admin','ContactMessagesAdminModule'),
    ('badges_admin.php',              'Mj\\Member\\Module\\Admin','BadgesAdminModule'),
    ('cards_pdf_admin.php',           'Mj\\Member\\Module\\Admin','CardsPdfAdminModule'),
    ('event_photos_admin.php',        'Mj\\Member\\Module\\Admin','EventPhotosAdminModule'),
    ('todos_admin.php',               'Mj\\Member\\Module\\Admin','TodosAdminModule'),
]

os.makedirs(os.path.join(BASE, 'Module'), exist_ok=True)
os.makedirs(os.path.join(BASE, 'Module', 'Admin'), exist_ok=True)

ABSPATH_PAT = re.compile(r"^\s*if\s*\(\s*!defined\s*\(\s*['\"]ABSPATH['\"]\s*\)\s*\)")

def find_block_end(lines, start):
    """Find the index of the line containing the matching closing brace for a block
    that opens on `lines[start]`."""
    depth = 0
    for i in range(start, len(lines)):
        depth += lines[i].count('{') - lines[i].count('}')
        if i > start and depth == 0:
            return i
    return len(lines) - 1

def de_indent(line, spaces=4):
    if line.startswith(' ' * spaces):
        return line[spaces:]
    return line

def process(filename, namespace, classname):
    filepath = os.path.join(BASE, filename)
    if not os.path.exists(filepath):
        print(f"  SKIP (not found): {filename}")
        return

    with open(filepath, 'r', encoding='utf-8') as f:
        lines = f.readlines()   # keeps \n

    # ------------------------------------------------------------------ #
    # 1. Locate the first namespace block  namespace Foo\Bar {            #
    # ------------------------------------------------------------------ #
    first_ns_start = None
    for i, line in enumerate(lines):
        stripped = line.rstrip()
        if re.match(r'^namespace Mj\\Member', stripped) and stripped.endswith('{'):
            first_ns_start = i
            break

    if first_ns_start is None:
        # Already a standard (non-bracketed) namespace file — e.g. badges_admin.php
        # Just copy it as-is to Module/Admin/ and leave the original untouched.
        print(f"  Standard namespace (no split needed): {filename}")
        # Read it, find the closing } of the class and extract
        content = ''.join(lines)
        dest = os.path.join(BASE, 'Module', 'Admin' if 'Admin' in namespace else '', classname + '.php')
        dest = os.path.join(BASE, 'Module', classname + '.php')
        if 'Admin' in namespace:
            dest = os.path.join(BASE, 'Module', 'Admin', classname + '.php')
        with open(dest, 'w', encoding='utf-8') as f:
            f.write(content)
        print(f"  Copied as-is -> {dest}")
        return

    first_ns_end = find_block_end(lines, first_ns_start)

    # ------------------------------------------------------------------ #
    # 2. Locate the global namespace block  namespace {                   #
    # ------------------------------------------------------------------ #
    global_ns_start = None
    for i in range(first_ns_end + 1, len(lines)):
        if lines[i].strip() == 'namespace {':
            global_ns_start = i
            break

    if global_ns_start is None:
        print(f"  ERROR: no global namespace block in {filename}")
        return

    global_ns_end = find_block_end(lines, global_ns_start)

    # ------------------------------------------------------------------ #
    # 3. Build Module class file (de-indent by 4, replace namespace decl) #
    # ------------------------------------------------------------------ #
    class_lines_raw = lines[first_ns_start:first_ns_end + 1]

    module_out = ['<?php\n', '\n']
    # First line: "namespace Foo\Bar {" → "namespace Foo\Bar;"
    module_out.append(class_lines_raw[0].rstrip().rstrip('{').rstrip() + ';\n')
    module_out.append('\n')

    # Inner lines (de-indent 4 spaces), skip first and last (the {} wrappers)
    for raw in class_lines_raw[1:-1]:
        module_out.append(de_indent(raw))

    # Ensure trailing newline
    if module_out and not module_out[-1].endswith('\n'):
        module_out.append('\n')

    dest_dir = os.path.join(BASE, 'Module', 'Admin') if 'Admin' in namespace else os.path.join(BASE, 'Module')
    dest = os.path.join(dest_dir, classname + '.php')
    with open(dest, 'w', encoding='utf-8') as f:
        f.writelines(module_out)
    print(f"  Created: Module/{('Admin/' if 'Admin' in namespace else '')}{classname}.php")

    # ------------------------------------------------------------------ #
    # 4. Build cleaned procedural file                                    #
    # ------------------------------------------------------------------ #
    # Content = everything inside the global namespace block EXCEPT:
    #   - the opening "namespace {" line
    #   - any ABSPATH guard lines
    #   - the closing "}" line
    inner = lines[global_ns_start + 1 : global_ns_end]

    # Strip leading blank + ABSPATH guard lines
    while inner and (inner[0].strip() == '' or ABSPATH_PAT.match(inner[0])):
        inner.pop(0)

    proc_out = ['<?php\n', '\n',
                "if (!defined('ABSPATH')) {\n",
                "    exit;\n",
                "}\n",
                '\n']
    proc_out.extend(inner)

    # Trim trailing blank lines, keep one final newline
    while proc_out and proc_out[-1].strip() == '':
        proc_out.pop()
    proc_out.append('\n')

    with open(filepath, 'w', encoding='utf-8') as f:
        f.writelines(proc_out)
    print(f"  Reverted: {filename}")

# ------------------------------------------------------------------ #
# Run                                                                 #
# ------------------------------------------------------------------ #
print("=== split_modules.py ===\n")
for filename, namespace, classname in FILES:
    print(f"[{classname}]")
    process(filename, namespace, classname)

print("\nDone! Don't forget to update Bootstrap.php.")
