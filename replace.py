import sys
path = r'c:\laragon\www\diserwp\wp-content\plugins\aura-business-suite\templates\financial\accounts-page.php'
with open(path, 'r', encoding='utf-8') as f:
    lines = f.readlines()

new_lines = []
skip = False
for i, line in enumerate(lines):
    if 'foreach (get_users(array' in line and not skip:
        # We found the start
        spaces = line[:len(line) - len(line.lstrip())]
        
        replacement = spaces + '<optgroup label="<?php esc_attr_e(\'Usuarios del sistema\', \'aura-suite\'); ?>">\n'
        replacement += spaces + '<?php foreach (get_users(array(\'fields\' => array(\'ID\', \'display_name\'))) as $user) : ?>\n'
        replacement += spaces + '    <option value="wp:<?php echo esc_attr((int) $user->ID); ?>"><?php echo esc_html($user->display_name); ?></option>\n'
        replacement += spaces + '<?php endforeach; ?>\n'
        replacement += spaces + '</optgroup>\n'
        replacement += spaces + '<optgroup label="<?php esc_attr_e(\'Terceros\', \'aura-suite\'); ?>">\n'
        replacement += spaces + '<?php global $wpdb; $third_parties = $wpdb->get_results("SELECT id, full_name FROM {$wpdb->prefix}aura_finance_third_parties WHERE is_active = 1 ORDER BY full_name ASC");\n'
        replacement += spaces + 'foreach ($third_parties as $tp) : ?>\n'
        replacement += spaces + '    <option value="tp:<?php echo esc_attr((int) $tp->id); ?>"><?php echo esc_html($tp->full_name); ?></option>\n'
        replacement += spaces + '<?php endforeach; ?>\n'
        replacement += spaces + '</optgroup>\n'
        
        new_lines.append(replacement)
        skip = True
    elif skip and 'endforeach;' in line:
        skip = False
    elif not skip:
        new_lines.append(line)

with open(path, 'w', encoding='utf-8', newline='') as f:
    f.writelines(new_lines)
print('Done!')
