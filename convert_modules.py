import os, sys

BASE = r"C:\Users\simon\Documents\Sites\mjpery-wordpress\wp-content\plugins\mj-member\includes"

files_config = [
    {
        "file": "event_photos.php",
        "class": "EventPhotosModule",
        "hook_line_numbers": [28, 40, 242, 1167, 1168, 1244, 1245],
    },
    {
        "file": "todos.php",
        "class": "TodosModule",
        "hook_line_numbers": [],
    },
    {
        "file": "shortcode_inscription.php",
        "class": "ShortcodeInscriptionModule",
        "hook_line_numbers": [],
    },
    {
        "file": "photo_grimlins.php",
        "class": "PhotoGrimlinsModule",
        "hook_line_numbers": [789, 790, 898, 970, 1059, 1113, 1161, 1219],
    },
    {
        "file": "notification_listeners.php",
        "class": "NotificationListenersModule",
        "hook_line_numbers": [400, 463, 543, 620, 696, 768, 830, 897, 967, 1074, 1169, 1240, 1321, 1404, 1491, 1570, 1653, 1720, 1764, 1824, 1874, 1924, 1982, 2048, 2095, 2148, 2214, 2261, 2308, 2404],
    },
    {
        "file": "events_public.php",
        "class": "EventsPublicModule",
        "hook_line_numbers": [283, 284, 1347, 1358, 2463, 3037, 3148, 3211, 3247, 3248, 3249, 3649, 4207, 4208, 4318],
    },
]

def parse_header(lines):
    """
    Parse the file header. Returns (docblock_lines, use_statements, body_start_0idx).
    Consumes: <?php, docblock, use statements, ABSPATH guard.
    """
    i = 0
    # Skip <?php
    while i < len(lines) and lines[i].strip() == '':
        i += 1
    if i < len(lines) and lines[i].strip() == '<?php':
        i += 1

    # Skip blank lines
    while i < len(lines) and lines[i].strip() == '':
        i += 1

    # Capture docblock if present
    docblock_lines = []
    if i < len(lines) and (lines[i].strip().startswith('/**') or lines[i].strip().startswith('/*')):
        while i < len(lines) and '*/' not in lines[i]:
            docblock_lines.append(lines[i])
            i += 1
        if i < len(lines):
            docblock_lines.append(lines[i])  # the closing */
            i += 1
        # Skip blank lines after docblock
        while i < len(lines) and lines[i].strip() == '':
            i += 1

    # Collect use statements
    use_statements = []
    while i < len(lines) and lines[i].startswith('use '):
        use_statements.append(lines[i].rstrip('\n'))
        i += 1

    # Skip blank lines
    while i < len(lines) and lines[i].strip() == '':
        i += 1

    # Skip ABSPATH guard if present
    if i < len(lines) and "!defined('ABSPATH')" in lines[i]:
        # Skip until closing }
        while i < len(lines) and lines[i].strip() != '}':
            i += 1
        if i < len(lines):
            i += 1  # consume '}'
        # Skip blank lines after ABSPATH guard
        while i < len(lines) and lines[i].strip() == '':
            i += 1

    return docblock_lines, use_statements, i  # i is 0-indexed body start

def convert_file(config):
    filepath = os.path.join(BASE, config['file'])
    class_name = config['class']
    hook_line_numbers = set(config['hook_line_numbers'])  # 1-indexed

    with open(filepath, 'r', encoding='utf-8') as f:
        lines = f.readlines()

    print(f"\n--- Processing {config['file']} ({len(lines)} lines) ---")

    # Get hook lines content (for register() method)
    hook_lines_content = []
    for ln in sorted(config['hook_line_numbers']):
        line = lines[ln - 1].rstrip('\n')  # 0-indexed
        hook_lines_content.append(line.strip())
        print(f"  Hook line {ln}: {line.strip()[:90]}")

    # Parse header
    docblock_lines, use_statements, body_start = parse_header(lines)
    print(f"  Docblock lines: {len(docblock_lines)}")
    print(f"  Use statements: {len(use_statements)}")
    print(f"  Body starts at 0-idx {body_start} = line {body_start + 1} (1-indexed)")
    if body_start < len(lines):
        print(f"  First body line: {lines[body_start].rstrip()[:80]}")

    # Build register() method body
    register_body = ""
    for hook in hook_lines_content:
        register_body += f"        {hook}\n"

    # Build module namespace block
    module_block = "<?php\n"
    module_block += "namespace Mj\\Member\\Module {\n"
    module_block += "    use Mj\\Member\\Core\\Contracts\\ModuleInterface;\n"
    module_block += "    if (!defined('ABSPATH')) { exit; }\n"
    module_block += "\n"
    module_block += f"    final class {class_name} implements ModuleInterface {{\n"
    module_block += "        public function register(): void {\n"
    module_block += register_body
    module_block += "        }\n"
    module_block += "    }\n"
    module_block += "}\n"
    module_block += "\n"

    # Build global namespace block
    global_block = "namespace {\n"
    global_block += "    if (!defined('ABSPATH')) { exit; }\n"

    if docblock_lines:
        global_block += "\n"
        for dl in docblock_lines:
            global_block += dl  # keep original newlines

    if use_statements:
        global_block += "\n"
        for u in use_statements:
            global_block += u + "\n"

    global_block += "\n"

    # Add body lines (excluding hook lines)
    body_lines = lines[body_start:]
    for j, line in enumerate(body_lines):
        line_number_1idx = body_start + j + 1  # 1-indexed in original file
        if line_number_1idx in hook_line_numbers:
            continue
        global_block += line  # keep original line content

    # Trim trailing whitespace/newlines and add end marker
    global_block = global_block.rstrip('\n')
    global_block += "\n} // end namespace\n"

    new_content = module_block + global_block

    with open(filepath, 'w', encoding='utf-8', newline='\n') as f:
        f.write(new_content)

    print(f"  Written: {len(new_content.splitlines())} lines total")
    return True

for cfg in files_config:
    convert_file(cfg)

print("\nAll conversions complete!")
